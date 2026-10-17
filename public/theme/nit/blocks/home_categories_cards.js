/**
 * Behaviour for home_6_categories4.html - the "photo cards" categories section
 * (Figma "home 13"): one card per top-level category, the category IMAGE as the
 * photo, a brand-coloured scrim over it, the category LOGO and the name on top.
 *
 * Served by js.php?name=home_categories_cards so a fix ships with a git pull;
 * the pasted block only carries markup and a four-line loader.
 *
 * Data: window.NIT_CATEGORIES (theme_nit_get_categories()), per category:
 *   image      the category image (local_nit_category), the photo
 *   logolight  the category logo for light mode (Change style tab)
 *   logodark   the category logo for dark mode
 *   name, url
 * A category with no logo for a mode falls back to the SITE logo for that
 * mode, so the card never shows a white mark on a white scrim.
 *
 * Colours: --nit-brand-* only. The scrim is the page background at 66%, the
 * text is the primary text colour, so the light/dark switch (a class on the
 * html element) repaints the cards with no script. Both logo variants are in
 * the card and CSS shows the one for the current mode.
 *
 * Options on the [data-nit-catcards] element:
 *   data-ids    comma list of category ids, drawn in that order (empty = all)
 *   data-limit  how many cards at most (default 6)
 *   data-split-ar / data-split-en  how many words of the name go on the
 *               card's first line, the rest on the second (default 2)
 */
(function() {
    'use strict';

    var STYLE_ID = 'nit-catcards-style';

    function injectStyle() {
        if (document.getElementById(STYLE_ID)) {
            return;
        }
        var scrimStrong = 'color-mix(in srgb, var(--nit-brand-background) 66%, transparent)';
        var scrimSoft = 'color-mix(in srgb, var(--nit-brand-background) 48%, transparent)';
        var css = [
            '.nit-cc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:25px 32px;',
            'width:100%;max-width:1264px;margin:0 auto}',
            '@media (max-width:991.98px){.nit-cc-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}}',
            '@media (max-width:575.98px){.nit-cc-grid{grid-template-columns:minmax(0,1fr);gap:16px}}',

            '.nit-cc-card{position:relative;display:block;aspect-ratio:400/250;border-radius:12px;',
            'overflow:hidden;isolation:isolate;container-type:inline-size;',
            'background:var(--nit-brand-surface);color:var(--nit-brand-textprimary)!important;',
            'text-decoration:none!important}',
            '.nit-cc-card:focus-visible{outline:3px solid var(--nit-brand-accent);outline-offset:3px}',

            '.nit-cc-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}',

            // Hover: a border in the CATEGORY's own colour (logistics blue,
            // engineering green, ...): --nit-cc-color-light / -dark, set on the
            // card from the Primary role of the group the category wears in that
            // mode. A category with no group falls back to the site Accent.
            // Drawn by an overlay rather than the card's own border because the
            // photo (z-index -2 inside the card) would paint over that, and an
            // overlay adds no width so nothing in the grid moves.
            '.nit-cc-card::after{content:"";position:absolute;inset:0;z-index:1;pointer-events:none;',
            'border-radius:inherit;border:2px solid transparent;transition:border-color .2s ease}',
            '.nit-cc-card:hover::after,.nit-cc-card:focus-visible::after{',
            'border-color:var(--nit-cc-color-light,var(--nit-brand-accent))}',
            'html.nit-mode-dark .nit-cc-card:hover::after,html.nit-mode-dark .nit-cc-card:focus-visible::after{',
            'border-color:var(--nit-cc-color-dark,var(--nit-brand-accent))}',

            // The scrim is darkest behind the text, which sits at the inline
            // start: the right in Arabic, the left in English.
            '.nit-cc-card::before{content:"";position:absolute;inset:0;z-index:-1;',
            'background:linear-gradient(to right,' + scrimStrong + ' 0%,' + scrimStrong + ' 68%,' + scrimSoft + ' 100%)}',
            'html[dir=rtl] .nit-cc-card::before{',
            'background:linear-gradient(to left,' + scrimStrong + ' 0%,' + scrimStrong + ' 68%,' + scrimSoft + ' 100%)}',

            // One ROW, centred on a line 40% down the card (above the middle, so
            // the text sits clear of the busy lower half of the photo), 25px in
            // from the inline start: the logo, a "|" rule, then the name - right
            // to left in Arabic, left to right in English.
            '.nit-cc-body{position:absolute;top:40%;transform:translateY(-50%);inset-inline-start:6.25%;',
            'display:flex;flex-direction:row;align-items:flex-start;gap:10px;max-width:87.5%}',

            // The logo box: 40px wide with 6px either side, and the rule on its
            // end edge - the "|" between the mark and the name.
            '.nit-cc-mark{flex:0 0 auto;box-sizing:border-box;width:clamp(32px,10cqi,40px);padding:0 6px;',
            'border-inline-end:1px solid var(--nit-brand-textprimary)}',
            '.nit-cc-markbox{position:relative;display:block;width:100%;aspect-ratio:1114/872;overflow:hidden}',
            '.nit-cc-logo{position:absolute;inset:0;width:100%;height:100%;max-width:none;object-fit:contain}',
            // A category logo is the full lock-up on the shared 958x308 logo
            // canvas (assets/Eaac logos/New folder (2)): the mark (bars + book)
            // sits at the top-left corner, 284x222. The card shows only the
            // mark, so the box takes the mark's shape and the picture is drawn
            // canvas/mark times larger, pinned at that corner. Physical left/top
            // on purpose: they address the picture, not the reading direction.
            // A logo on another canvas needs these four numbers redone.
            '.nit-cc-logo--crop{inset:auto;left:0;top:0;width:337.8%;height:138.7%;object-fit:fill}',
            'html.nit-mode-dark .nit-cc-logo--light,html:not(.nit-mode-dark) .nit-cc-logo--dark{display:none}',

            '.nit-cc-name{display:block;flex:0 1 auto;min-width:0;margin:0;max-width:72cqi;',
            'font-size:clamp(16px,5.5cqi,22px);line-height:1.364;font-weight:600;',
            'color:var(--nit-brand-textprimary);text-align:start;overflow-wrap:anywhere}',
            '.nit-cc-line{display:block}',

            '@media (prefers-reduced-motion:reduce){.nit-cc-card::after{transition:none}}',

            // The header: the curved band behind the logo (Figma "Rectangle
            // 2691") - the page background with a soft shadow under its curve.
            '[data-nit-catcards-arc]{position:absolute;top:0;left:50%;transform:translateX(-50%);',
            'width:min(1444px,100%);height:clamp(200px,24vw,338px);overflow:visible;pointer-events:none;z-index:0;',
            'filter:drop-shadow(0 4px 11px rgba(0,0,0,.55))}',
            'html.nit-mode-light [data-nit-catcards-arc]{filter:drop-shadow(0 4px 11px rgba(0,0,0,.14))}',
            '[data-nit-catcards-arc] path{fill:var(--nit-brand-background)}',
            '[data-nit-catcards-brand]{height:100%;width:auto;max-width:100%;object-fit:contain}',

            // The corner controls - same look and contract as the hero block's
            // (data-nit-hero-tool), so both blocks can share a page.
            '[data-nit-hero-tool]{transition:background-color .2s ease,color .2s ease,border-color .2s ease}',
            '[data-nit-hero-tool]:hover,[data-nit-hero-tool]:focus-visible{background:var(--nit-brand-hoverbackground)!important;',
            'color:var(--nit-brand-hovertext)!important;border-color:var(--nit-brand-hoverbackground)!important;text-decoration:none}',
            '[data-nit-hero-tool]:focus-visible{outline:2px solid var(--nit-brand-accent);outline-offset:2px}',
            '[data-nit-hero-mode] .nit-mode-icon{display:none!important}',
            '[data-nit-hero-mode][data-nit-mode="light"] .nit-mode-icon-dark,',
            '[data-nit-hero-mode][data-nit-mode="dark"] .nit-mode-icon-light{display:block!important}',
            '@media (max-width:767px){[data-nit-catcards-tools]{top:12px!important}}'
        ].join('');
        var st = document.createElement('style');
        st.id = STYLE_ID;
        st.textContent = css;
        (document.head || document.documentElement).appendChild(st);
    }

    // The site's own mark for a mode: the switch's per-mode logos when the site
    // has two sets, otherwise the one compact mark.
    function siteLogo(mode) {
        var toggle = window.NIT_MODE_TOGGLE;
        var logos = toggle && toggle.logos && toggle.logos[mode];
        if (logos && (logos.compact || logos.navbar)) {
            return logos.compact || logos.navbar;
        }
        return window.NIT_LOGO || window.NIT_LOGO_FULL || '';
    }

    function pick(all, ids, limit) {
        if (ids.length) {
            var byId = {};
            all.forEach(function(c) {
                byId[String(c.id)] = c;
            });
            return ids.map(function(id) {
                return byId[id];
            }).filter(Boolean).slice(0, limit);
        }
        return all.slice(0, limit);
    }

    // crop: true for a category's own logo (cut down to its mark), false for
    // the site-logo fallback, which is drawn whole.
    function logoImg(src, modifier, crop) {
        var img = document.createElement('img');
        img.className = 'nit-cc-logo' + (modifier ? ' nit-cc-logo--' + modifier : '') +
            (crop ? ' nit-cc-logo--crop' : '');
        img.src = src;
        img.alt = '';
        img.decoding = 'async';
        return img;
    }

    // The name in two lines: the first N words, then the rest. N comes from
    // the grid's data-split-ar / data-split-en (Arabic: "منصة إياك" then the
    // field; English: "EAAC ... Platform" then the field). A name of N words
    // or fewer stays on one line.
    function splitName(text, count) {
        var words = String(text).trim().split(/\s+/).filter(Boolean);
        if (!(count > 0) || words.length <= count) {
            return [words.join(' ')];
        }
        return [words.slice(0, count).join(' '), words.slice(count).join(' ')];
    }

    var nameSplit = 0;

    function buildCard(cat) {
        var a = document.createElement('a');
        a.className = 'nit-cc-card';
        a.href = cat.url || '#';
        // The category's own colour per mode, read by the hover border.
        if (cat.colorlight) {
            a.style.setProperty('--nit-cc-color-light', cat.colorlight);
        }
        if (cat.colordark) {
            a.style.setProperty('--nit-cc-color-dark', cat.colordark);
        }

        if (cat.image) {
            var photo = document.createElement('img');
            photo.className = 'nit-cc-photo';
            photo.src = cat.image;
            photo.alt = '';
            photo.loading = 'lazy';
            photo.decoding = 'async';
            a.appendChild(photo);
        }

        var body = document.createElement('span');
        body.className = 'nit-cc-body';

        var light = cat.logolight || siteLogo('light');
        var dark = cat.logodark || siteLogo('dark');
        var mark = document.createElement('span');
        mark.className = 'nit-cc-mark';
        var box = document.createElement('span');
        box.className = 'nit-cc-markbox';
        if (light && light === dark) {
            box.appendChild(logoImg(light, '', !!cat.logolight));
        } else {
            if (light) {
                box.appendChild(logoImg(light, 'light', !!cat.logolight));
            }
            if (dark) {
                box.appendChild(logoImg(dark, 'dark', !!cat.logodark));
            }
        }
        if (box.firstChild) {
            mark.appendChild(box);
            body.appendChild(mark);
        }

        var name = document.createElement('span');
        name.className = 'nit-cc-name';
        splitName(cat.name || '', nameSplit).forEach(function(text) {
            var line = document.createElement('span');
            line.className = 'nit-cc-line';
            line.textContent = text;
            name.appendChild(line);
        });
        body.appendChild(name);

        a.appendChild(body);
        return a;
    }

    function render(section) {
        if (section.getAttribute('data-nit-catcards-done')) {
            return;
        }
        section.setAttribute('data-nit-catcards-done', '1');

        // Direction follows the INTERFACE language, never the text: Arabic
        // starts every card on the right, English on the left, whatever
        // language a category name happens to be written in.
        section.setAttribute('dir', document.documentElement.getAttribute('dir') === 'rtl' ? 'rtl' : 'ltr');

        // The big logo: the site's wide lock-up. data-nit-logo="full" lets the
        // theme's light/dark switch swap it with the navbar's mark. The block's
        // inline script has normally set it already; this covers an older
        // paste without that script, and leaves an equal src alone so the
        // picture is not fetched twice.
        var brand = window.NIT_LOGO_FULL || window.NIT_LOGO;
        if (typeof brand === 'string' && brand) {
            section.querySelectorAll('[data-nit-catcards-brand]').forEach(function(img) {
                if (img.src !== brand) {
                    img.src = brand;
                }
                img.style.opacity = '1';
            });
        }

        var grid = section.querySelector('[data-nit-catcards-grid]');
        if (!grid) {
            return;
        }
        var all = Array.isArray(window.NIT_CATEGORIES) ? window.NIT_CATEGORIES : [];
        var ids = (grid.getAttribute('data-ids') || '').split(',').map(function(s) {
            return s.trim();
        }).filter(Boolean);
        var arabic = /^ar(-|_|$)/i.test(document.documentElement.getAttribute('lang') || '');
        nameSplit = parseInt(grid.getAttribute(arabic ? 'data-split-ar' : 'data-split-en'), 10) ||
            2;
        var limit = parseInt(grid.getAttribute('data-limit'), 10);
        if (!(limit > 0)) {
            limit = 6;
        }

        var cats = pick(all, ids, limit);
        if (!cats.length) {
            section.style.display = 'none';
            return;
        }

        grid.textContent = '';
        grid.classList.add('nit-cc-grid');
        cats.forEach(function(cat) {
            grid.appendChild(buildCard(cat));
        });
    }

    function run() {
        injectStyle();
        document.querySelectorAll('[data-nit-catcards]').forEach(render);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
