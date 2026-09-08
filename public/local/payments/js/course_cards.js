// Injects country-resolved price badges onto course cards in any listing
// (frontpage, category grid, course search) by keying off each card's
// /course/view.php?id=N link — theme-agnostic. Loaded (deferred) on every
// page by the before_standard_head_html_generation hook; it self-guards and
// does nothing when a page has no course cards.
//
// "A card in a listing" is the whole contract, and it is enforced twice below:
// the link must not be in site chrome, and it must sit inside a card container.
// A /course/view.php?id=N href on its own proves nothing — the breadcrumb, the
// course-index drawer and the language switcher all carry one — and treating any
// such link as a card is what once printed a price in the navigation bar.
(function () {
    "use strict";

    function esc(s) {
        return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;" }[c];
        });
    }

    // Site chrome — the navigation bar, the drawers, the breadcrumb, the footer,
    // every menu. These are FULL of /course/view.php?id=N links that are not
    // course cards: the course-index drawer heading, the breadcrumb trail, a
    // "My courses" menu row, and — the one that actually shipped a price into
    // the navbar — the language switcher, whose href is the current page plus
    // &lang=xx, which on a course page IS a course link and is usually the very
    // first one in the document.
    //
    // A badge belongs on a card in a listing. Anything in here is not that,
    // whatever its href says.
    var CHROME = [
        "nav", ".navbar", "[role=\"navigation\"]", ".drawer", ".drawercontent",
        ".breadcrumb", ".navbar-nav", ".dropdown-menu", ".langmenu", ".usermenu",
        "#page-footer", "footer", ".courseindex", "#courseindex",
        ".secondary-navigation", ".moremenu", ".block_navigation", ".block_settings",
        ".modal", ".toast", ".popover"
    ].join(",");

    function inChrome(a) {
        return !!a.closest(CHROME);
    }

    // A course card in a listing — the only thing this script decorates. Matching
    // a container (rather than settling for "somewhere near the link") is what
    // keeps the badge inside the card it belongs to and off everything else.
    var CARD = [
        "[data-courseid]", ".coursebox", ".card", ".course-card",
        ".courses-view-course-item", ".dashboard-card"
    ].join(",");

    function cardFor(a) {
        return a.closest(CARD);
    }

    function buildBadge(ctx, labels) {
        // Nothing to sell.
        if (ctx.is_enrolled || ctx.is_free) {
            return "";
        }
        if (ctx.is_purchased) {
            return '<div class="lp-card-badge">' +
                '<span class="lp-badge lp-badge--purchased">' + esc(labels.purchased) + '</span></div>';
        }
        // Signed in with no profile country: prices are per country, so this viewer has none.
        // The badge says so and links to the fix rather than printing an empty amount.
        if (ctx.country_required) {
            return '<div class="lp-card-badge">' +
                '<a class="lp-badge lp-badge--countryrequired" href="' + esc(ctx.country_url) + '"' +
                ' title="' + esc(ctx.country_message) + '">' + esc(ctx.country_short) + "</a></div>";
        }
        var cur = esc(ctx.currency || "");
        if (ctx.is_sale_active) {
            return '<div class="lp-card-badge">' +
                '<span class="lp-badge lp-badge--sale">-' + esc(ctx.discount_pct) + '%</span> ' +
                '<span class="lp-price lp-price--original">' + esc(ctx.original_price) + " " + cur + "</span> " +
                '<span class="lp-price lp-price--sale">' + esc(ctx.sale_price) + " " + cur + "</span>" +
                "</div>";
        }
        return '<div class="lp-card-badge">' +
            '<span class="lp-price lp-price--current">' + esc(ctx.price) + " " + cur + "</span></div>";
    }

    function init() {
        var root = (window.M && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : "";
        var re = /\/course\/view\.php\?id=(\d+)/;
        var links = document.querySelectorAll('a[href*="/course/view.php?id="]');
        if (!links.length) {
            return;
        }

        // The course this page is ABOUT, when it is a course page. Its own price
        // is shown by the page itself (the course hero); a second, floating copy
        // hung off some link in the body would be the same amount twice.
        // M.cfg.courseId is 1 — the site "course" — on every page that is not a
        // course page, so only an id above SITEID means anything here.
        var pageid = (window.M && M.cfg && M.cfg.courseId) ? parseInt(M.cfg.courseId, 10) : 0;
        var pagecourse = pageid > 1 ? String(pageid) : "";

        // First anchor per course id — that's where we hang the badge. Chrome is
        // skipped rather than merely deprioritised: taking the first link in the
        // document without asking where it was is what put a price in the navbar.
        var first = {};
        Array.prototype.forEach.call(links, function (a) {
            var m = re.exec(a.getAttribute("href") || "");
            if (!m || inChrome(a) || !cardFor(a)) {
                return;
            }
            if (pagecourse && m[1] === pagecourse) {
                return;
            }
            if (!first[m[1]]) {
                first[m[1]] = a;
            }
        });

        var ids = Object.keys(first);
        if (!ids.length) {
            return;
        }

        fetch(root + "/local/payments/ajax_prices.php?ids=" + ids.join(","), { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || j.status !== "success" || !j.data) {
                    return;
                }
                ids.forEach(function (id) {
                    var ctx = j.data[id];
                    var a = first[id];
                    if (!ctx || !a) {
                        return;
                    }
                    var html = buildBadge(ctx, j.labels || {});
                    if (!html) {
                        return;
                    }
                    // The badge goes at the bottom of the card, and ONLY there.
                    // There used to be a fallback that dropped it just after the
                    // course link when no card could be found, and that fallback
                    // is what produced both reported faults: a price inside the
                    // navigation bar, and a second copy of the price under the
                    // buttons of every card on the category page — those cards
                    // print their own price server-side and never needed this.
                    // No card means this link is not a listing entry; leave it be.
                    var target = a.closest(CARD);
                    if (!target || target.dataset.lpBadged) {
                        return;
                    }
                    target.dataset.lpBadged = "1";
                    target.insertAdjacentHTML("beforeend", html);
                });
            })
            .catch(function () { /* listing still works without badges */ });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
