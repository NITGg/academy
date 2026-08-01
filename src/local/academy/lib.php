<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add a "Teacher profile" section to the user's Moodle profile page (standard themes).
 */
function local_academy_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!$iscurrentuser) {
        return true;
    }
    if (!\local_academy\teacher_manager::is_teacher($user->id)) {
        return true; // teachers only — admins/students don't get a teacher profile section
    }
    $category = new \core_user\output\myprofile\category(
        'local_academy_cat', get_string('teacherprofile', 'local_academy'), 'contact');
    $tree->add_category($category);
    $url = new moodle_url('/local/academy/teacher_profile.php');
    $node = new \core_user\output\myprofile\node(
        'local_academy_cat', 'local_academy_edit',
        get_string('editmyteacherprofile', 'local_academy'), null, $url);
    $tree->add_node($node);
    return true;
}

/**
 * Add "Edit my teacher profile" under the user's Preferences page.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $context
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_academy_extend_navigation_user_settings($navigation, $user, $context, $course, $coursecontext) {
    global $USER;
    if (empty($USER->id) || $USER->id != $user->id) {
        return; // only on your own preferences page
    }

    // Student hub (book lessons + Flex packages) — available to every logged-in user.
    $studentnode = navigation_node::create(
        get_string('studenthub', 'local_academy'),
        new moodle_url('/local/academy/student.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_academy_studenthub',
        new pix_icon('i/courseevent', '')
    );
    $useraccountstud = $navigation->find('useraccount', navigation_node::TYPE_CONTAINER);
    if ($useraccountstud) {
        $useraccountstud->add_node($studentnode);
    } else {
        $navigation->add_node($studentnode);
    }

    // Coupons & Offers (US-US-CP-*, US-US-OF-*) — available to every logged-in user.
    $couponsnode = navigation_node::create(
        get_string('mycoupons_title', 'local_academy'),
        new moodle_url('/local/academy/coupons.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_academy_coupons',
        new pix_icon('i/tags', '')
    );
    if ($useraccountstud) {
        $useraccountstud->add_node($couponsnode);
    } else {
        $navigation->add_node($couponsnode);
    }

    // B2B subscription dashboard — only for users who own an active B2B subscription (US-B2B-1-8).
    // Guarded: the `type` column is added by the plugin upgrade; before it runs this must not error.
    global $DB;
    $ownsb2b = false;
    try {
        $ownsb2b = $DB->record_exists('academy_sub_purchases',
            array('userid' => $user->id, 'type' => 'b2b', 'status' => 'active'));
    } catch (\Throwable $e) {
        $ownsb2b = false;
    }
    if ($ownsb2b) {
        $b2bnode = navigation_node::create(
            get_string('b2b_dashboard_title', 'local_academy'),
            new moodle_url('/local/academy/b2b_dashboard.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_academy_b2b',
            new pix_icon('i/cohort', '')
        );
        if ($useraccountstud) {
            $useraccountstud->add_node($b2bnode);
        } else {
            $navigation->add_node($b2bnode);
        }
    }

    if (!\local_academy\teacher_manager::is_teacher($user->id)) {
        return; // teachers only — admins/students don't get a teacher profile link
    }
    $node = navigation_node::create(
        get_string('editmyteacherprofile', 'local_academy'),
        new moodle_url('/local/academy/teacher_profile.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_academy_teacherprofile',
        new pix_icon('i/edit', '')
    );
    $lessonsnode = navigation_node::create(
        get_string('mylessons', 'local_academy'),
        new moodle_url('/local/academy/my_lessons.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_academy_mylessons',
        new pix_icon('i/calendar', '')
    );
    $walletnode = navigation_node::create(
        get_string('mywallet', 'local_academy'),
        new moodle_url('/local/academy/wallet.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_academy_mywallet',
        new pix_icon('i/payment', '')
    );
    // Place these inside the "User account" group (with Edit profile, Change password, ...).
    $useraccount = $navigation->find('useraccount', navigation_node::TYPE_CONTAINER);
    if ($useraccount) {
        $useraccount->add_node($node);
        $useraccount->add_node($lessonsnode);
        $useraccount->add_node($walletnode);
    } else {
        $navigation->add_node($node);
        $navigation->add_node($lessonsnode);
        $navigation->add_node($walletnode);
    }
}

/**
 * Build the front-page "Available subscriptions" section: scoped CSS + an empty card grid, plus the
 * JS that renders Udemy/Coursera-style cards. Logged-in students drive /local/academy/api.php with a
 * mobile WS token, exactly like student.php. Guests/visitors get the plan list fetched server-side
 * instead (no token to mint) and their card buttons link to the login page rather than checkout.
 * Returns '' when there is nothing to show (no WS token for a real user, or no active plans for a
 * guest); the section then simply does not appear.
 *
 * @return string HTML to echo before the footer
 */
function local_academy_available_subscriptions_section() {
    global $DB, $CFG, $PAGE;

    // Real (non-guest) logged-in users get the same mobile web-service token student.php uses, so
    // the front-page cards and the student hub exercise identical endpoints. Guests/visitors have no
    // account to mint a token for, so their card data is fetched server-side (no purchase state to
    // merge in) and their buttons send them to log in instead of to checkout.
    $isrealuser = isloggedin() && !isguestuser();
    $token = '';
    $guestrows = null;

    if ($isrealuser) {
        require_once($CFG->dirroot . '/webservice/lib.php');
        try {
            $service = $DB->get_record('external_services',
                array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
            $token = external_generate_token_for_current_user($service)->token;
        } catch (\Exception $e) {
            return '';
        }
    } else {
        $guestrows = \local_academy\subscription_purchase_manager::get_available_subscriptions();
        if (empty($guestrows)) {
            return ''; // nothing to sell — leave the section hidden, same as the logged-in path
        }
    }

    $heading = get_string('availsubs_heading', 'local_academy');
    $desc    = get_string('availsubs_desc', 'local_academy');

    // Scoped CSS (la-subs-* prefix keeps it away from the theme + student.php's st-* styles).
    // Restyled to the "PM Lounge" homepage design (Figma): purple #6c22a6 brand, Cairo typography,
    // #f2f3fa section panel, square call-to-action buttons. Class names are unchanged so the card
    // rendering / purchase JS below is untouched — this is a visual-only refresh.
    $css = <<<CSS
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap');
.la-subs{--pm:#c9922a;--pm-d:#e8b84b;--pm-bg:#080f1d;--pm-ink:#ffffff;--pm-muted:#8a9ab5;--pm-surface:#0d2149;--pm-border:rgba(201,146,42,.2);--pm-line:rgba(201,146,42,.14);--pm-navy:#0a1628;max-width:1280px;margin:3.5rem auto;padding:2.75rem 1.75rem;background:var(--pm-bg);border:1px solid var(--pm-border);border-radius:14px;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-subs-head{margin-bottom:1.75rem}
.la-subs-title{font-family:'Cairo',sans-serif;font-size:1.65rem;font-weight:800;color:var(--pm-ink);margin:0 0 .35rem}
.la-subs-sub{color:var(--pm-d);margin:0;font-size:1.05rem;font-weight:700}
.la-subs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.75rem;align-items:stretch}
.la-subs-card{display:flex;flex-direction:column;height:100%;background:var(--pm-surface);border:1px solid var(--pm-border);border-radius:10px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.25);transition:transform .18s ease,box-shadow .18s ease}
.la-subs-card:hover{transform:translateY(-6px);border-color:rgba(201,146,42,.45);box-shadow:0 18px 36px rgba(0,0,0,.4)}
.la-subs-banner{position:relative;height:180px;flex-shrink:0;display:flex;flex-direction:column;justify-content:space-between;padding:1rem 1.1rem;color:var(--pm-d);overflow:hidden;background:linear-gradient(135deg,#1a3a6b,var(--pm-navy))}
.la-subs-banner::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 15%,rgba(201,146,42,.18),transparent 55%)}
.la-subs-banner svg{width:52px;height:52px;opacity:.95;fill:var(--pm-d);position:relative;z-index:1}
.la-subs-badges{align-self:flex-start;position:relative;z-index:1;display:flex;gap:.4rem;flex-wrap:wrap}
.la-subs-daysbadge{background:rgba(201,146,42,.15);border:1px solid var(--pm-border);color:var(--pm-d);font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-subs-body{padding:1.25rem 1.25rem 1.5rem;display:flex;flex-direction:column;flex:1}
.la-subs-name{font-family:'Cairo',sans-serif;font-weight:700;font-size:1.15rem;color:var(--pm-ink);margin:0 0 .5rem;line-height:1.35;min-height:2.7em}
.la-subs-desc{color:var(--pm-muted);font-size:.92rem;margin:0 0 .9rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:4.1em}
.la-subs-card--active{border-color:var(--pm);box-shadow:0 0 0 2px rgba(201,146,42,.35)}
.la-subs-activebadge{background:#00a99d;color:#04121f;font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-subs-offerbadge{display:inline-flex;align-items:center;gap:.25rem;background:#c0392b;color:#fff;font-weight:800;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem;box-shadow:0 2px 6px rgba(0,0,0,.35)}
.la-subs-offerbadge svg{width:13px;height:13px;fill:#fff}
.la-subs-price-old{font-size:1rem;font-weight:600;color:#6b7c96;text-decoration:line-through;margin-inline-end:.35rem}
.la-subs-dates{font-size:.86rem;color:var(--pm-muted);margin-bottom:1rem;padding-top:.9rem;border-top:1px solid var(--pm-line)}
.la-subs-dates-row{display:flex;justify-content:space-between;margin-top:.3rem}
.la-subs-dates-row b{color:var(--pm-ink)}
.la-subs-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding-top:.5rem}
.la-subs-price{font-size:1.5rem;font-weight:800;color:var(--pm-d)}
.la-subs-price small{font-size:.8rem;font-weight:600;color:var(--pm-muted)}
.la-subs-btn{background:linear-gradient(135deg,var(--pm),var(--pm-d));border:none;color:var(--pm-navy);font-family:'Cairo',sans-serif;font-weight:700;font-size:.95rem;padding:.7rem 1.5rem;border-radius:4px;cursor:pointer;transition:box-shadow .15s ease,transform .1s ease}
.la-subs-btn:hover{box-shadow:0 8px 25px rgba(201,146,42,.45)}
.la-subs-btn:active{transform:scale(.97)}
.la-subs-btn[disabled]{background:rgba(255,255,255,.09);color:var(--pm-muted);cursor:not-allowed;box-shadow:none}
.la-subs-headnote{display:none;align-items:center;gap:.4rem;margin-top:.6rem;color:var(--pm-d);font-size:.85rem;line-height:1.4}
.la-subs-headnote svg{width:15px;height:15px;flex-shrink:0;fill:var(--pm-d)}
/* Confirmation dialog (replaces the native window.confirm). */
.la-subs-modal-bg{position:fixed;inset:0;background:rgba(28,29,36,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:1rem;opacity:0;transition:opacity .18s ease;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-subs-modal-bg.open{display:flex;opacity:1}
.la-subs-modal{background:var(--pm-surface,#0d2149);border:1px solid rgba(201,146,42,.45);border-radius:12px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.6);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .18s ease}
.la-subs-modal-bg.open .la-subs-modal{transform:none}
.la-subs-modal-head{display:flex;align-items:center;gap:.7rem;padding:1.25rem 1.4rem 0}
.la-subs-modal-head svg{width:34px;height:34px;fill:#e8b84b;flex-shrink:0}
.la-subs-modal-head h4{margin:0;font-size:1.2rem;font-weight:800;color:#ffffff}
.la-subs-modal-body{padding:.9rem 1.4rem 0;color:#8a9ab5;font-size:.95rem;line-height:1.5}
.la-subs-modal-plan{margin:.9rem 0;padding:.9rem 1rem;background:rgba(201,146,42,.1);border:1px solid rgba(201,146,42,.2);border-radius:8px}
.la-subs-modal-plan .name{font-weight:700;color:#ffffff;margin-bottom:.35rem}
.la-subs-modal-row{display:flex;justify-content:space-between;font-size:.9rem;color:#8a9ab5;margin-top:.2rem}
.la-subs-modal-row b{color:#ffffff}
.la-subs-modal-secure{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#8a9ab5;margin-top:.2rem}
.la-subs-modal-secure svg{width:14px;height:14px;fill:#00a99d}
.la-subs-modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:1.2rem 1.4rem 1.3rem}
.la-subs-modal-cancel{background:transparent;border:1px solid rgba(201,146,42,.45);color:#8a9ab5;font-weight:600;font-size:.92rem;padding:.6rem 1.1rem;border-radius:4px;cursor:pointer}
.la-subs-modal-cancel:hover{background:rgba(201,146,42,.12);color:#e8b84b}
.la-subs-modal-ok{background:linear-gradient(135deg,#c9922a,#e8b84b);border:none;color:#0a1628;font-weight:700;font-size:.92rem;padding:.6rem 1.3rem;border-radius:4px;cursor:pointer}
.la-subs-modal-ok:hover{box-shadow:0 8px 25px rgba(201,146,42,.45)}
CSS;

    $section = html_writer::tag('style', $css) .
        '<section id="la-subs" class="la-subs" style="display:none">' .
            '<div class="la-subs-head">' .
                html_writer::tag('h3', s($heading), array('class' => 'la-subs-title')) .
                html_writer::tag('p', s($desc), array('class' => 'la-subs-sub')) .
                '<div id="la-subs-headnote" class="la-subs-headnote"></div>' .
            '</div>' .
            '<div id="la-subs-msg" class="alert" style="display:none"></div>' .
            '<div id="la-subs-grid" class="la-subs-grid"></div>' .
        '</section>';

    $cfg = array(
        'endpoint'  => $CFG->wwwroot . '/local/academy/api.php',
        'token'     => $token,
        // Honour an explicit ?lang= in the URL; otherwise use the page's current language.
        'lang'      => optional_param('lang', current_language(), PARAM_LANG),
        'loginurl'  => (string) new moodle_url('/login/index.php'),
        'b2bdashurl' => (string) new moodle_url('/local/academy/b2b_dashboard.php'),
        'guestRows' => $isrealuser ? null : array_values($guestrows),
    );
    $cfgjson = json_encode($cfg, JSON_UNESCAPED_SLASHES);

    // Client-side string map so the JS-rendered cards/modal localize like the rest of the page.
    // {n} is a number placeholder the JS substitutes at render time.
    $str = array(
        'sess_expired'    => get_string('hp_sess_expired', 'local_academy'),
        'req_failed'      => get_string('hp_req_failed', 'local_academy'),
        'confirm_title'   => get_string('hp_sub_confirm_title', 'local_academy'),
        'confirm_body'    => get_string('hp_sub_confirm_body', 'local_academy'),
        'duration'        => get_string('hp_duration', 'local_academy'),
        'days'            => get_string('hp_days', 'local_academy', '{n}'),
        'total'           => get_string('hp_total', 'local_academy'),
        'egp'             => get_string('hp_egp', 'local_academy'),
        'secure'          => get_string('hp_secure', 'local_academy'),
        'cancel'          => get_string('hp_cancel', 'local_academy'),
        'proceed'         => get_string('hp_proceed', 'local_academy'),
        'redirecting'     => get_string('hp_redirecting', 'local_academy'),
        'active'          => get_string('hp_active', 'local_academy'),
        'coupon'          => get_string('hp_coupon', 'local_academy'),
        'apply'           => get_string('hp_apply', 'local_academy'),
        'discount'        => get_string('hp_discount', 'local_academy'),
        'login_to_subscribe' => get_string('hp_login_to_subscribe', 'local_academy'),
        'start_date'      => get_string('hp_start_date', 'local_academy'),
        'end_date'        => get_string('hp_end_date', 'local_academy'),
        'never'           => get_string('hp_never', 'local_academy'),
        'subscribed'      => get_string('hp_subscribed', 'local_academy'),
        'subscribe'       => get_string('hp_subscribe', 'local_academy'),
        'active_note'     => get_string('hp_sub_active_note', 'local_academy'),
        'b2b_business'    => get_string('hp_b2b_business', 'local_academy'),
        'b2b_confirm_title' => get_string('hp_b2b_confirm_title', 'local_academy'),
        'b2b_confirm_body'  => get_string('hp_b2b_confirm_body', 'local_academy'),
        'b2b_capacity'    => get_string('hp_b2b_capacity', 'local_academy'),
        'b2b_users'       => get_string('hp_b2b_users', 'local_academy', '{n}'),
        'b2b_base'        => get_string('hp_b2b_base', 'local_academy'),
        'b2b_discount'    => get_string('hp_b2b_discount', 'local_academy'),
        'b2b_total'       => get_string('hp_b2b_total', 'local_academy'),
        'b2b_success'     => get_string('hp_b2b_success', 'local_academy'),
        'b2b_manage'      => get_string('hp_b2b_manage', 'local_academy'),
    );
    $strjson = json_encode($str, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $js = <<<JS
require([], function() {
    var CFG = {$cfgjson};
    var T = {$strjson};
    var sec = document.getElementById('la-subs');
    if (!sec || (!CFG.token && !(CFG.guestRows && CFG.guestRows.length))) { return; }
    var grid = document.getElementById('la-subs-grid');
    var B2B_OWNED = {}; // subscriptionid -> true for plans the user holds an active B2B sub for

    function el(tag, attrs, html) {
        var e = document.createElement(tag);
        for (var k in (attrs || {})) { e.setAttribute(k, attrs[k]); }
        if (html != null) { e.innerHTML = html; }
        return e;
    }
    function esc(v) {
        return (v == null ? '' : String(v)).replace(/[&<>"]/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }
    function money(n) { return Number(n || 0).toFixed(2); }
    function fmtDate(ts) { if (!ts) { return '—'; } return new Date(ts * 1000).toLocaleDateString(); }
    function showMsg(t, k) {
        var m = document.getElementById('la-subs-msg');
        m.textContent = t; m.className = 'alert alert-' + (k || 'info'); m.style.display = 'block';
    }
    function parse(r) {
        return r.text().then(function(t) {
            var j;
            try { j = JSON.parse(t); } catch (e) { throw new Error(T.sess_expired); }
            if (j.status !== 'success') { throw new Error(j.error || T.req_failed); }
            return j.data;
        });
    }
    function num(tpl, n) { return String(tpl).replace('{n}', n); }
    function apiGet(fn, params) {
        var base = {function: fn, token: CFG.token}; if (CFG.lang) { base.alang = CFG.lang; }
        var q = new URLSearchParams(Object.assign(base, params || {}));
        return fetch(CFG.endpoint + '?' + q.toString()).then(parse);
    }
    function apiPost(fn, params) {
        var base = {function: fn, token: CFG.token}; if (CFG.lang) { base.alang = CFG.lang; }
        var body = new URLSearchParams(Object.assign(base, params || {}));
        return fetch(CFG.endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        }).then(parse);
    }

    // Dark jewel-toned gradients — replaces the old rainbow set, which clashed against the
    // X-Trade navy/gold identity. Kept as six so cards still read as visually distinct.
    var GRADS = [
        'linear-gradient(135deg,#1a3a6b,#0a1628)',
        'linear-gradient(135deg,#0d2149,#1a3a6b)',
        'linear-gradient(135deg,#004d47,#00201d)',
        'linear-gradient(135deg,#5c1a24,#2c0a10)',
        'linear-gradient(135deg,#3a2a0a,#1a1206)',
        'linear-gradient(135deg,#1c2b45,#0a1628)'
    ];
    var CAP = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3 1 8l11 5 9-4.09V17h2V8L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>';
    var INFO = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';

    // A styled confirm dialog (replaces the ugly native window.confirm). Resolves true/false.
    function confirmSubscribe(s) {
        return new Promise(function(resolve) {
            var LOCK = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1a5 5 0 00-5 5v3H6a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2v-9a2 2 0 00-2-2h-1V6a5 5 0 00-5-5zm3 8H9V6a3 3 0 016 0v3z"/></svg>';
            var CROWN = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5 16 3 5l5.5 4L12 3l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/></svg>';
            var bg = el('div', {class: 'la-subs-modal-bg'});
            bg.innerHTML =
                '<div class="la-subs-modal" role="dialog" aria-modal="true">' +
                    '<div class="la-subs-modal-head">' + CROWN + '<h4>' + esc(T.confirm_title) + '</h4></div>' +
                    '<div class="la-subs-modal-body">' +
                        '<p>' + esc(T.confirm_body) + '</p>' +
                        '<div class="la-subs-modal-plan">' +
                            '<div class="name">' + esc(s.name) + '</div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.duration) + '</span><b>' + esc(num(T.days, s.duration_days)) + '</b></div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.total) + '</span><b class="la-subs-orig">' + esc(money(s.price)) + ' ' + esc(T.egp) + '</b></div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.coupon) + '</span><span style="display:flex;gap:.3rem"><input type="text" class="la-subs-coupon" style="max-width:110px;border:1px solid #d1d7dc;border-radius:4px;padding:.15rem .4rem"><button type="button" class="la-subs-apply" style="border:1px solid #6c22a6;background:#fff;color:#6c22a6;border-radius:4px;padding:.15rem .6rem;cursor:pointer">' + esc(T.apply) + '</button></span></div>' +
                            '<div class="la-subs-coupon-msg" style="color:#c0392b;font-size:.8rem;margin:.2rem 0"></div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.discount) + '</span><b class="la-subs-disc" style="color:#1f9d55">0.00 ' + esc(T.egp) + '</b></div>' +
                            '<div class="la-subs-modal-row" style="border-top:1px solid #e4d3f5;margin-top:.35rem;padding-top:.45rem"><span style="font-weight:700">' + esc(T.total) + '</span><b class="la-subs-final" style="color:#6c22a6;font-size:1.2rem">' + esc(money(s.price)) + ' ' + esc(T.egp) + '</b></div>' +
                        '</div>' +
                        '<div class="la-subs-modal-secure">' + LOCK + '<span>' + esc(T.secure) + '</span></div>' +
                    '</div>' +
                    '<div class="la-subs-modal-foot">' +
                        '<button type="button" class="la-subs-modal-cancel">' + esc(T.cancel) + '</button>' +
                        '<button type="button" class="la-subs-modal-ok">' + esc(T.proceed) + '</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(bg);
            // Force reflow so the open transition runs.
            void bg.offsetWidth;
            bg.classList.add('open');

            function close(result) {
                bg.classList.remove('open');
                document.removeEventListener('keydown', onKey);
                setTimeout(function() { if (bg.parentNode) { bg.parentNode.removeChild(bg); } }, 180);
                resolve(result);
            }
            function onKey(e) { if (e.key === 'Escape') { close(null); } }
            document.addEventListener('keydown', onKey);

            // Live discount preview: automatic offer on open, coupon on Apply
            // (US-US-OF-1-2 / US-US-CP-1-2).
            var couponInput = bg.querySelector('.la-subs-coupon');
            var discEl = bg.querySelector('.la-subs-disc');
            var finalEl = bg.querySelector('.la-subs-final');
            var origEl = bg.querySelector('.la-subs-orig');
            var cmsg = bg.querySelector('.la-subs-coupon-msg');
            function preview(code) {
                apiGet('preview_discount', {item_type: 'subscription', item_id: s.id, coupon_code: code || ''})
                    .then(function(d) {
                        discEl.textContent = (d.discount > 0 ? '-' + money(d.discount) : '0.00') + ' ' + T.egp;
                        finalEl.textContent = money(d.final) + ' ' + T.egp;
                        origEl.style.textDecoration = d.discount > 0 ? 'line-through' : 'none';
                        cmsg.textContent = d.coupon_error ? d.coupon_error : '';
                    }).catch(function() {});
            }
            bg.querySelector('.la-subs-apply').onclick = function() { preview(couponInput.value.trim()); };
            couponInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); preview(couponInput.value.trim()); } });
            // Live-update the price as the coupon is typed (debounced), so the student sees the final
            // amount before proceeding — no need to click Apply first.
            var subDeb;
            couponInput.addEventListener('input', function() {
                clearTimeout(subDeb);
                subDeb = setTimeout(function() { preview(couponInput.value.trim()); }, 450);
            });
            preview('');

            bg.querySelector('.la-subs-modal-cancel').onclick = function() { close(null); };
            bg.querySelector('.la-subs-modal-ok').onclick = function() { close(couponInput.value.trim()); };
            bg.onclick = function(e) { if (e.target === bg) { close(null); } };
        });
    }

    function subscribe(s, btn) {
        confirmSubscribe(s).then(function(code) {
            if (code === null) { return; }
            var orig = btn.textContent;
            btn.disabled = true; btn.textContent = T.redirecting;
            apiPost('create_subscription_checkout', {subscriptionid: s.id, coupon_code: code})
                .then(function(d) { window.location.href = d.checkout_url; })
                .catch(function(e) { showMsg(e.message, 'danger'); btn.disabled = false; btn.textContent = orig; });
        });
    }

    // B2B purchase dialog: pick a seat capacity, see base/discount/final, then buy (US-B2B-1-1).
    // Resolves the chosen seats (int) or null if cancelled.
    function confirmB2b(s) {
        return new Promise(function(resolve) {
            var opts = s.seat_options || [];
            var bg = el('div', {class: 'la-subs-modal-bg'});
            var optionsHtml = opts.map(function(o, i) {
                return '<option value="' + i + '">' + esc(num(T.b2b_users, o.seats)) +
                    ' — ' + esc(money(o.b2b_price)) + ' ' + esc(T.egp) + '</option>';
            }).join('');
            bg.innerHTML =
                '<div class="la-subs-modal" role="dialog" aria-modal="true">' +
                    '<div class="la-subs-modal-head">' + CAP + '<h4>' + esc(T.b2b_confirm_title) + '</h4></div>' +
                    '<div class="la-subs-modal-body">' +
                        '<p>' + esc(T.b2b_confirm_body) + '</p>' +
                        '<div class="la-subs-modal-plan">' +
                            '<div class="name">' + esc(s.name) + '</div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.b2b_capacity) + '</span>' +
                                '<select class="la-subs-b2b-cap" style="max-width:60%">' + optionsHtml + '</select></div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.b2b_base) + '</span><b class="la-subs-b2b-base"></b></div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.b2b_discount) + '</span><b class="la-subs-b2b-disc"></b></div>' +
                            '<div class="la-subs-modal-row"><span>' + esc(T.b2b_total) + '</span><b class="la-subs-b2b-total"></b></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="la-subs-modal-foot">' +
                        '<button type="button" class="la-subs-modal-cancel">' + esc(T.cancel) + '</button>' +
                        '<button type="button" class="la-subs-modal-ok">' + esc(T.proceed) + '</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(bg);
            void bg.offsetWidth;
            bg.classList.add('open');

            var sel = bg.querySelector('.la-subs-b2b-cap');
            function refresh() {
                var o = opts[Number(sel.value)] || {};
                bg.querySelector('.la-subs-b2b-base').textContent = money(o.original_price) + ' ' + T.egp;
                bg.querySelector('.la-subs-b2b-disc').textContent = '-' + money(o.discount_amount) + ' ' + T.egp + ' (' + Number(o.discount_percent || 0) + '%)';
                bg.querySelector('.la-subs-b2b-total').textContent = money(o.b2b_price) + ' ' + T.egp;
            }
            sel.onchange = refresh;
            refresh();

            function close(result) {
                bg.classList.remove('open');
                document.removeEventListener('keydown', onKey);
                setTimeout(function() { if (bg.parentNode) { bg.parentNode.removeChild(bg); } }, 180);
                resolve(result);
            }
            function onKey(e) { if (e.key === 'Escape') { close(null); } }
            document.addEventListener('keydown', onKey);
            bg.querySelector('.la-subs-modal-cancel').onclick = function() { close(null); };
            bg.querySelector('.la-subs-modal-ok').onclick = function() {
                var o = opts[Number(sel.value)];
                close(o ? o.seats : null);
            };
            bg.onclick = function(e) { if (e.target === bg) { close(null); } };
        });
    }

    function subscribeB2b(s, btn) {
        confirmB2b(s).then(function(seats) {
            if (!seats) { return; }
            var orig = btn.textContent;
            btn.disabled = true; btn.textContent = T.redirecting;
            // Same gateway flow as a normal subscription: create a checkout, redirect to the
            // payment page; the B2B purchase + role are created on webhook success.
            apiPost('create_subscription_checkout', {subscriptionid: s.id, type: 'b2b', seats: seats})
                .then(function(d) { window.location.href = d.checkout_url; })
                .catch(function(e) { showMsg(e.message, 'danger'); btn.disabled = false; btn.textContent = orig; });
        });
    }

    var TAG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21.41 11.58l-9-9A2 2 0 0011 2H4a2 2 0 00-2 2v7a2 2 0 00.59 1.42l9 9a2 2 0 002.82 0l7-7a2 2 0 000-2.84zM6.5 8A1.5 1.5 0 118 6.5 1.5 1.5 0 016.5 8z"/></svg>';
    // Short label for an offer badge: "-25%" for a percentage, "-<amount> EGP" for a fixed discount.
    function offerBadge(offer) {
        if (!offer) { return ''; }
        var text = offer.label || ('-' + money(offer.discount) + ' ' + T.egp);
        return '<span class="la-subs-offerbadge">' + TAG + esc(text) + '</span>';
    }

    function subCard(s, hasActive, idx, activeSub) {
        var isActive = !!(activeSub && Number(activeSub.subscriptionid) === Number(s.id));
        var card = el('div', {class: 'la-subs-card' + (isActive ? ' la-subs-card--active' : '')});
        var banner = el('div', {class: 'la-subs-banner', style: 'background:' + GRADS[idx % GRADS.length]});
        banner.innerHTML = CAP +
            '<span class="la-subs-badges">' +
                '<span class="la-subs-daysbadge">' + esc(num(T.days, s.duration_days)) + '</span>' +
                (s.offer ? offerBadge(s.offer) : '') +
                (isActive ? '<span class="la-subs-activebadge">' + esc(T.active) + '</span>' : '') +
            '</span>';
        card.appendChild(banner);

        var body = el('div', {class: 'la-subs-body'});
        body.appendChild(el('div', {class: 'la-subs-name'}, esc(s.name)));
        if (s.description) { body.appendChild(el('div', {class: 'la-subs-desc'}, esc(s.description))); }

        if (isActive) {
            var datesBox = el('div', {class: 'la-subs-dates'});
            datesBox.innerHTML =
                '<div class="la-subs-dates-row"><span>' + esc(T.start_date) + '</span><b>' + esc(fmtDate(activeSub.timeactivated)) + '</b></div>' +
                '<div class="la-subs-dates-row"><span>' + esc(T.end_date) + '</span><b>' + (Number(activeSub.expires_at) > 0 ? esc(fmtDate(activeSub.expires_at)) : esc(T.never)) + '</b></div>';
            body.appendChild(datesBox);
        }

        var foot = el('div', {class: 'la-subs-foot'});
        // When an automatic offer applies, strike the original price and show the discounted one.
        var priceHtml = s.offer
            ? '<span class="la-subs-price-old">' + esc(money(s.offer.original)) + '</span>' + esc(money(s.offer.final)) + ' <small>EGP</small>'
            : esc(money(s.price)) + ' <small>EGP</small>';
        foot.appendChild(el('div', {class: 'la-subs-price'}, priceHtml));
        var btn = el('button', {type: 'button', class: 'la-subs-btn'});
        if (!CFG.token) {
            // Guest/visitor — no account to buy with yet, send them to log in first.
            btn.textContent = T.login_to_subscribe;
            btn.onclick = function() { window.location.href = CFG.loginurl; };
        } else if (isActive) {
            btn.textContent = T.active; btn.disabled = true;
        } else if (hasActive) {
            btn.textContent = T.subscribed; btn.disabled = true;
        } else {
            btn.textContent = T.subscribe; btn.onclick = function() { subscribe(s, btn); };
        }
        foot.appendChild(btn);
        body.appendChild(foot);

        // Business (B2B) purchase: shown when the plan is B2B-enabled and has seat options.
        if (s.b2b_enabled && s.seat_options && s.seat_options.length) {
            var b2bBtn = el('button', {type: 'button', class: 'la-subs-btn', style: 'width:100%;margin-top:.6rem;background:transparent;border:1px solid rgba(201,146,42,.45);color:#e8b84b'});
            b2bBtn.textContent = T.b2b_business;
            if (!CFG.token) {
                b2bBtn.onclick = function() { window.location.href = CFG.loginurl; };
            } else if (B2B_OWNED[s.id]) {
                // Already a B2B admin for this plan — send them to the dashboard instead of re-buying.
                b2bBtn.textContent = T.b2b_manage;
                b2bBtn.onclick = function() { window.location.href = CFG.b2bdashurl; };
            } else {
                b2bBtn.onclick = function() { subscribeB2b(s, b2bBtn); };
            }
            body.appendChild(b2bBtn);
        }

        card.appendChild(body);
        return card;
    }

    function renderRows(rows, hasActive, activeSub) {
        if (!rows.length) { return; } // nothing to sell — leave the section hidden

        // Only one subscription can be active at a time — explain why every "Subscribe" button
        // is disabled with a single note under the section heading, instead of repeating it on
        // every card.
        var headnote = document.getElementById('la-subs-headnote');
        if (headnote) {
            if (hasActive) {
                headnote.innerHTML = INFO + '<span>' + esc(T.active_note) + '</span>';
                headnote.style.display = 'flex';
            } else {
                headnote.style.display = 'none';
            }
        }

        grid.innerHTML = '';
        rows.forEach(function(s, i) { grid.appendChild(subCard(s, hasActive, i, activeSub)); });
        sec.style.display = 'block';
    }

    if (CFG.token) {
        Promise.all([apiGet('get_available_subscriptions'), apiGet('get_my_subscriptions')]).then(function(res) {
            var rows = res[0] || [], mine = res[1] || [];
            // Only a NORMAL subscription drives the "one active plan" button state; B2B purchases
            // are a separate system and must not mark the normal cards as already subscribed.
            var activeSub = mine.filter(function(s) {
                return s.status === 'active' && (s.type || 'normal') === 'normal';
            })[0] || null;
            // Plans the user already owns an active B2B subscription for — so the "Business (B2B)"
            // button shows an owned/manage state instead of letting them buy the same plan again.
            B2B_OWNED = {};
            mine.forEach(function(s) {
                if (s.status === 'active' && (s.type || 'normal') === 'b2b') { B2B_OWNED[s.subscriptionid] = true; }
            });
            renderRows(rows, !!activeSub, activeSub);
        }).catch(function() { /* keep the section hidden on any error */ });
    } else {
        // Guest/visitor — server already rendered the available plans; no purchase state to merge.
        renderRows(CFG.guestRows || [], false, null);
    }
});
JS;

    $PAGE->requires->js_amd_inline($js);
    return $section;
}

/**
 * Build the front-page "Available packages" section: same Udemy/Coursera-style cards as the
 * subscriptions section, but for Flex packages (see the "Packages & Flex" tab of student.php).
 * Same logged-in-vs-guest split as local_academy_available_subscriptions_section(): real users get a
 * mobile WS token; guests get the package list fetched server-side and a login button instead of
 * checkout. Returns '' when there is nothing to show, so the section simply does not appear.
 *
 * @return string HTML to echo before the footer
 */
function local_academy_available_packages_section() {
    global $DB, $CFG, $PAGE;

    // See local_academy_available_subscriptions_section() for why guests get server-fetched rows
    // and a login button instead of a web-service token.
    $isrealuser = isloggedin() && !isguestuser();
    $token = '';
    $guestrows = null;

    if ($isrealuser) {
        require_once($CFG->dirroot . '/webservice/lib.php');
        try {
            $service = $DB->get_record('external_services',
                array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
            $token = external_generate_token_for_current_user($service)->token;
        } catch (\Exception $e) {
            return '';
        }
    } else {
        $guestrows = \local_academy\purchase_manager::get_available_packages();
        if (empty($guestrows)) {
            return ''; // nothing to sell — leave the section hidden, same as the logged-in path
        }
    }

    $heading = get_string('availpkgs_heading', 'local_academy');
    $desc    = get_string('availpkgs_desc', 'local_academy');

    // Scoped CSS (la-pkgs-* prefix keeps it away from la-subs-* and student.php's st-* styles).
    // Same "PM Lounge" (Figma) refresh as the subscriptions section: purple #6c22a6 brand, Cairo
    // typography, #f2f3fa panel. Class names are unchanged so the purchase JS below is untouched.
    $css = <<<CSS
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap');
.la-pkgs{--pm:#c9922a;--pm-d:#e8b84b;--pm-bg:#080f1d;--pm-ink:#ffffff;--pm-muted:#8a9ab5;--pm-surface:#0d2149;--pm-border:rgba(201,146,42,.2);--pm-line:rgba(201,146,42,.14);--pm-navy:#0a1628;max-width:1280px;margin:3.5rem auto;padding:2.75rem 1.75rem;background:var(--pm-bg);border:1px solid var(--pm-border);border-radius:14px;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-pkgs-head{margin-bottom:1.75rem}
.la-pkgs-title{font-family:'Cairo',sans-serif;font-size:1.65rem;font-weight:800;color:var(--pm-ink);margin:0 0 .35rem}
.la-pkgs-sub{color:var(--pm-d);margin:0;font-size:1.05rem;font-weight:700}
.la-pkgs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.75rem;align-items:stretch}
.la-pkgs-card{display:flex;flex-direction:column;height:100%;background:var(--pm-surface);border:1px solid var(--pm-border);border-radius:10px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.25);transition:transform .18s ease,box-shadow .18s ease}
.la-pkgs-card:hover{transform:translateY(-6px);border-color:rgba(201,146,42,.45);box-shadow:0 18px 36px rgba(0,0,0,.4)}
.la-pkgs-banner{position:relative;height:180px;flex-shrink:0;display:flex;flex-direction:column;justify-content:space-between;padding:1rem 1.1rem;color:var(--pm-d);overflow:hidden;background:linear-gradient(135deg,#1a3a6b,var(--pm-navy))}
.la-pkgs-banner::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 15%,rgba(201,146,42,.18),transparent 55%)}
.la-pkgs-banner svg{width:52px;height:52px;opacity:.95;fill:var(--pm-d);position:relative;z-index:1}
.la-pkgs-badges{align-self:flex-start;position:relative;z-index:1;display:flex;gap:.4rem;flex-wrap:wrap}
.la-pkgs-flexbadge{background:rgba(201,146,42,.15);border:1px solid var(--pm-border);color:var(--pm-d);font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-pkgs-offerbadge{display:inline-flex;align-items:center;gap:.25rem;background:#c0392b;color:#fff;font-weight:800;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem;box-shadow:0 2px 6px rgba(0,0,0,.35)}
.la-pkgs-offerbadge svg{width:13px;height:13px;fill:#fff}
.la-pkgs-price-old{font-size:1rem;font-weight:600;color:#6b7c96;text-decoration:line-through;margin-inline-end:.35rem}
.la-pkgs-body{padding:1.25rem 1.25rem 1.5rem;display:flex;flex-direction:column;flex:1}
.la-pkgs-name{font-family:'Cairo',sans-serif;font-weight:700;font-size:1.15rem;color:var(--pm-ink);margin:0 0 .5rem;line-height:1.35;min-height:2.7em}
.la-pkgs-desc{color:var(--pm-muted);font-size:.92rem;margin:0 0 .9rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:4.1em}
.la-pkgs-card--active{border-color:var(--pm);box-shadow:0 0 0 2px rgba(201,146,42,.35)}
.la-pkgs-activebadge{background:#00a99d;color:#04121f;font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-pkgs-meta{font-size:.86rem;color:var(--pm-muted);margin-top:auto;margin-bottom:1rem;padding-top:.9rem;border-top:1px solid var(--pm-line)}
.la-pkgs-dates{font-size:.86rem;color:var(--pm-muted);margin-top:auto;margin-bottom:1rem;padding-top:.9rem;border-top:1px solid var(--pm-line)}
.la-pkgs-dates-row{display:flex;justify-content:space-between;margin-top:.3rem}
.la-pkgs-dates-row b{color:var(--pm-ink)}
.la-pkgs-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding-top:.5rem}
.la-pkgs-price{font-size:1.5rem;font-weight:800;color:var(--pm-d)}
.la-pkgs-price small{font-size:.8rem;font-weight:600;color:var(--pm-muted)}
.la-pkgs-btn{background:linear-gradient(135deg,var(--pm),var(--pm-d));border:none;color:var(--pm-navy);font-family:'Cairo',sans-serif;font-weight:700;font-size:.95rem;padding:.7rem 1.5rem;border-radius:4px;cursor:pointer;transition:box-shadow .15s ease,transform .1s ease}
.la-pkgs-btn:hover{box-shadow:0 8px 25px rgba(201,146,42,.45)}
.la-pkgs-btn:active{transform:scale(.97)}
.la-pkgs-btn[disabled]{background:rgba(255,255,255,.09);color:var(--pm-muted);cursor:not-allowed;box-shadow:none}
.la-pkgs-headnote{display:none;align-items:center;gap:.4rem;margin-top:.6rem;color:var(--pm-d);font-size:.85rem;line-height:1.4}
.la-pkgs-headnote svg{width:15px;height:15px;flex-shrink:0;fill:var(--pm-d)}
/* Confirmation dialog (replaces the native window.confirm). */
.la-pkgs-modal-bg{position:fixed;inset:0;background:rgba(28,29,36,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:1rem;opacity:0;transition:opacity .18s ease;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-pkgs-modal-bg.open{display:flex;opacity:1}
.la-pkgs-modal{background:var(--pm-surface,#0d2149);border:1px solid rgba(201,146,42,.45);border-radius:12px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.6);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .18s ease}
.la-pkgs-modal-bg.open .la-pkgs-modal{transform:none}
.la-pkgs-modal-head{display:flex;align-items:center;gap:.7rem;padding:1.25rem 1.4rem 0}
.la-pkgs-modal-head svg{width:34px;height:34px;fill:#e8b84b;flex-shrink:0}
.la-pkgs-modal-head h4{margin:0;font-size:1.2rem;font-weight:800;color:#ffffff}
.la-pkgs-modal-body{padding:.9rem 1.4rem 0;color:#8a9ab5;font-size:.95rem;line-height:1.5}
.la-pkgs-modal-plan{margin:.9rem 0;padding:.9rem 1rem;background:rgba(201,146,42,.1);border:1px solid rgba(201,146,42,.2);border-radius:8px}
.la-pkgs-modal-plan .name{font-weight:700;color:#ffffff;margin-bottom:.35rem}
.la-pkgs-modal-row{display:flex;justify-content:space-between;font-size:.9rem;color:#8a9ab5;margin-top:.2rem}
.la-pkgs-modal-row b{color:#ffffff}
.la-pkgs-modal-secure{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#8a9ab5;margin-top:.2rem}
.la-pkgs-modal-secure svg{width:14px;height:14px;fill:#00a99d}
.la-pkgs-modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:1.2rem 1.4rem 1.3rem}
.la-pkgs-modal-cancel{background:transparent;border:1px solid rgba(201,146,42,.45);color:#8a9ab5;font-weight:600;font-size:.92rem;padding:.6rem 1.1rem;border-radius:4px;cursor:pointer}
.la-pkgs-modal-cancel:hover{background:rgba(201,146,42,.12);color:#e8b84b}
.la-pkgs-modal-ok{background:linear-gradient(135deg,#c9922a,#e8b84b);border:none;color:#0a1628;font-weight:700;font-size:.92rem;padding:.6rem 1.3rem;border-radius:4px;cursor:pointer}
.la-pkgs-modal-ok:hover{box-shadow:0 8px 25px rgba(201,146,42,.45)}
CSS;

    $section = html_writer::tag('style', $css) .
        '<section id="la-pkgs" class="la-pkgs" style="display:none">' .
            '<div class="la-pkgs-head">' .
                html_writer::tag('h3', s($heading), array('class' => 'la-pkgs-title')) .
                html_writer::tag('p', s($desc), array('class' => 'la-pkgs-sub')) .
                '<div id="la-pkgs-headnote" class="la-pkgs-headnote"></div>' .
            '</div>' .
            '<div id="la-pkgs-msg" class="alert" style="display:none"></div>' .
            '<div id="la-pkgs-grid" class="la-pkgs-grid"></div>' .
        '</section>';

    $cfg = array(
        'endpoint'  => $CFG->wwwroot . '/local/academy/api.php',
        'token'     => $token,
        // Honour an explicit ?lang= in the URL; otherwise use the page's current language.
        'lang'      => optional_param('lang', current_language(), PARAM_LANG),
        'loginurl'  => (string) new moodle_url('/login/index.php'),
        'guestRows' => $isrealuser ? null : array_values($guestrows),
    );
    $cfgjson = json_encode($cfg, JSON_UNESCAPED_SLASHES);

    // Client-side string map so the JS-rendered cards/modal localize like the rest of the page.
    $str = array(
        'sess_expired'    => get_string('hp_sess_expired', 'local_academy'),
        'req_failed'      => get_string('hp_req_failed', 'local_academy'),
        'confirm_title'   => get_string('hp_pkg_confirm_title', 'local_academy'),
        'confirm_body'    => get_string('hp_pkg_confirm_body', 'local_academy'),
        'flex_count'      => get_string('hp_flex_count', 'local_academy'),
        'flex'            => get_string('hp_flex', 'local_academy', '{n}'),
        'total'           => get_string('hp_total', 'local_academy'),
        'egp'             => get_string('hp_egp', 'local_academy'),
        'login_to_buy'    => get_string('hp_login_to_buy', 'local_academy'),
        'secure'          => get_string('hp_secure', 'local_academy'),
        'cancel'          => get_string('hp_cancel', 'local_academy'),
        'proceed'         => get_string('hp_proceed', 'local_academy'),
        'redirecting'     => get_string('hp_redirecting', 'local_academy'),
        'active'          => get_string('hp_active', 'local_academy'),
        'coupon'          => get_string('hp_coupon', 'local_academy'),
        'apply'           => get_string('hp_apply', 'local_academy'),
        'discount'        => get_string('hp_discount', 'local_academy'),
        'flex_used_total' => get_string('hp_flex_used_total', 'local_academy'),
        'activated'       => get_string('hp_activated', 'local_academy'),
        'expires'         => get_string('hp_expires', 'local_academy'),
        'never'           => get_string('hp_never', 'local_academy'),
        'never_expires'   => get_string('hp_never_expires', 'local_academy'),
        'valid_for'       => get_string('hp_valid_for', 'local_academy', '{n}'),
        'purchased'       => get_string('hp_purchased', 'local_academy'),
        'buy_package'     => get_string('hp_buy_package', 'local_academy'),
        'active_note'     => get_string('hp_pkg_active_note', 'local_academy'),
    );
    $strjson = json_encode($str, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $js = <<<JS
require([], function() {
    var CFG = {$cfgjson};
    var T = {$strjson};
    var sec = document.getElementById('la-pkgs');
    if (!sec || (!CFG.token && !(CFG.guestRows && CFG.guestRows.length))) { return; }
    var grid = document.getElementById('la-pkgs-grid');

    function el(tag, attrs, html) {
        var e = document.createElement(tag);
        for (var k in (attrs || {})) { e.setAttribute(k, attrs[k]); }
        if (html != null) { e.innerHTML = html; }
        return e;
    }
    function esc(v) {
        return (v == null ? '' : String(v)).replace(/[&<>"]/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }
    function money(n) { return Number(n || 0).toFixed(2); }
    function fmtDate(ts) { if (!ts) { return '—'; } return new Date(ts * 1000).toLocaleDateString(); }
    function showMsg(t, k) {
        var m = document.getElementById('la-pkgs-msg');
        m.textContent = t; m.className = 'alert alert-' + (k || 'info'); m.style.display = 'block';
    }
    function parse(r) {
        return r.text().then(function(t) {
            var j;
            try { j = JSON.parse(t); } catch (e) { throw new Error(T.sess_expired); }
            if (j.status !== 'success') { throw new Error(j.error || T.req_failed); }
            return j.data;
        });
    }
    function num(tpl, n) { return String(tpl).replace('{n}', n); }
    function apiGet(fn, params) {
        var base = {function: fn, token: CFG.token}; if (CFG.lang) { base.alang = CFG.lang; }
        var q = new URLSearchParams(Object.assign(base, params || {}));
        return fetch(CFG.endpoint + '?' + q.toString()).then(parse);
    }
    function apiPost(fn, params) {
        var base = {function: fn, token: CFG.token}; if (CFG.lang) { base.alang = CFG.lang; }
        var body = new URLSearchParams(Object.assign(base, params || {}));
        return fetch(CFG.endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        }).then(parse);
    }

    // Dark jewel-toned gradients — replaces the old rainbow set, which clashed against the
    // X-Trade navy/gold identity. Kept as six so cards still read as visually distinct.
    var GRADS = [
        'linear-gradient(135deg,#1a3a6b,#0a1628)',
        'linear-gradient(135deg,#0d2149,#1a3a6b)',
        'linear-gradient(135deg,#004d47,#00201d)',
        'linear-gradient(135deg,#5c1a24,#2c0a10)',
        'linear-gradient(135deg,#3a2a0a,#1a1206)',
        'linear-gradient(135deg,#1c2b45,#0a1628)'
    ];
    var BOLT = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 21h-1l1-7H7.5c-.58 0-.57-.32-.38-.66.19-.34.05-.08.07-.12C8.72 10.6 10.85 7.08 13 3.5h1l-1 7h4.5c.5 0 .5.33.36.61-.13.28-.08.19-.11.24C15.09 15.34 13 18.85 11 21z"/></svg>';
    var TAG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21.41 11.58l-9-9A2 2 0 0011 2H4a2 2 0 00-2 2v7a2 2 0 00.59 1.42l9 9a2 2 0 002.82 0l7-7a2 2 0 000-2.84zM6.5 8A1.5 1.5 0 118 6.5 1.5 1.5 0 016.5 8z"/></svg>';
    var INFO = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';

    function confirmBuyPackage(p) {
        return new Promise(function(resolve) {
            var LOCK = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1a5 5 0 00-5 5v3H6a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2v-9a2 2 0 00-2-2h-1V6a5 5 0 00-5-5zm3 8H9V6a3 3 0 016 0v3z"/></svg>';
            var BOLT_ICON = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 21h-1l1-7H7.5c-.58 0-.57-.32-.38-.66.19-.34.05-.08.07-.12C8.72 10.6 10.85 7.08 13 3.5h1l-1 7h4.5c.5 0 .5.33.36.61-.13.28-.08.19-.11.24C15.09 15.34 13 18.85 11 21z"/></svg>';
            var bg = el('div', {class: 'la-pkgs-modal-bg'});
            bg.innerHTML =
                '<div class="la-pkgs-modal" role="dialog" aria-modal="true">' +
                    '<div class="la-pkgs-modal-head">' + BOLT_ICON + '<h4>' + esc(T.confirm_title) + '</h4></div>' +
                    '<div class="la-pkgs-modal-body">' +
                        '<p>' + esc(T.confirm_body) + '</p>' +
                        '<div class="la-pkgs-modal-plan">' +
                            '<div class="name">' + esc(p.name) + '</div>' +
                            '<div class="la-pkgs-modal-row"><span>' + esc(T.flex_count) + '</span><b>' + esc(num(T.flex, p.flex_count)) + '</b></div>' +
                            '<div class="la-pkgs-modal-row"><span>' + esc(T.total) + '</span><b class="la-pkgs-orig">' + esc(money(p.price)) + ' ' + esc(T.egp) + '</b></div>' +
                            '<div class="la-pkgs-modal-row"><span>' + esc(T.coupon) + '</span><span style="display:flex;gap:.3rem"><input type="text" class="la-pkgs-coupon" style="max-width:110px;border:1px solid #d1d7dc;border-radius:4px;padding:.15rem .4rem"><button type="button" class="la-pkgs-apply" style="border:1px solid #6c22a6;background:#fff;color:#6c22a6;border-radius:4px;padding:.15rem .6rem;cursor:pointer">' + esc(T.apply) + '</button></span></div>' +
                            '<div class="la-pkgs-coupon-msg" style="color:#c0392b;font-size:.8rem;margin:.2rem 0"></div>' +
                            '<div class="la-pkgs-modal-row"><span>' + esc(T.discount) + '</span><b class="la-pkgs-disc" style="color:#1f9d55">0.00 ' + esc(T.egp) + '</b></div>' +
                            '<div class="la-pkgs-modal-row" style="border-top:1px solid #e4d3f5;margin-top:.35rem;padding-top:.45rem"><span style="font-weight:700">' + esc(T.total) + '</span><b class="la-pkgs-final" style="color:#6c22a6;font-size:1.2rem">' + esc(money(p.price)) + ' ' + esc(T.egp) + '</b></div>' +
                        '</div>' +
                        '<div class="la-pkgs-modal-secure">' + LOCK + '<span>' + esc(T.secure) + '</span></div>' +
                    '</div>' +
                    '<div class="la-pkgs-modal-foot">' +
                        '<button type="button" class="la-pkgs-modal-cancel">' + esc(T.cancel) + '</button>' +
                        '<button type="button" class="la-pkgs-modal-ok">' + esc(T.proceed) + '</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(bg);
            void bg.offsetWidth;
            bg.classList.add('open');

            function close(result) {
                bg.classList.remove('open');
                document.removeEventListener('keydown', onKey);
                setTimeout(function() { if (bg.parentNode) { bg.parentNode.removeChild(bg); } }, 180);
                resolve(result);
            }
            function onKey(e) { if (e.key === 'Escape') { close(null); } }
            document.addEventListener('keydown', onKey);

            // Live discount preview: automatic offer on open, coupon on Apply
            // (US-US-OF-1-2 / US-US-CP-1-2).
            var couponInput = bg.querySelector('.la-pkgs-coupon');
            var discEl = bg.querySelector('.la-pkgs-disc');
            var finalEl = bg.querySelector('.la-pkgs-final');
            var origEl = bg.querySelector('.la-pkgs-orig');
            var cmsg = bg.querySelector('.la-pkgs-coupon-msg');
            function preview(code) {
                apiGet('preview_discount', {item_type: 'package', item_id: p.id, coupon_code: code || ''})
                    .then(function(d) {
                        discEl.textContent = (d.discount > 0 ? '-' + money(d.discount) : '0.00') + ' ' + T.egp;
                        finalEl.textContent = money(d.final) + ' ' + T.egp;
                        origEl.style.textDecoration = d.discount > 0 ? 'line-through' : 'none';
                        cmsg.textContent = d.coupon_error ? d.coupon_error : '';
                    }).catch(function() {});
            }
            bg.querySelector('.la-pkgs-apply').onclick = function() { preview(couponInput.value.trim()); };
            couponInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); preview(couponInput.value.trim()); } });
            // Live-update the price as the coupon is typed (debounced), so the student sees the final
            // amount before proceeding — no need to click Apply first.
            var pkgDeb;
            couponInput.addEventListener('input', function() {
                clearTimeout(pkgDeb);
                pkgDeb = setTimeout(function() { preview(couponInput.value.trim()); }, 450);
            });
            preview('');

            bg.querySelector('.la-pkgs-modal-cancel').onclick = function() { close(null); };
            bg.querySelector('.la-pkgs-modal-ok').onclick = function() { close(couponInput.value.trim()); };
            bg.onclick = function(e) { if (e.target === bg) { close(null); } };
        });
    }

    function subscribe(p, btn) {
        confirmBuyPackage(p).then(function(code) {
            if (code === null) { return; }
            var orig = btn.textContent;
            btn.disabled = true; btn.textContent = T.redirecting;
            apiPost('create_package_checkout', {packageid: p.id, coupon_code: code})
                .then(function(d) { window.location.href = d.checkout_url; })
                .catch(function(e) { showMsg(e.message, 'danger'); btn.disabled = false; btn.textContent = orig; });
        });
    }

    function pkgCard(p, hasActive, idx, activePkg) {
        var isActive = !!(activePkg && Number(activePkg.packageid) === Number(p.id));
        var card = el('div', {class: 'la-pkgs-card' + (isActive ? ' la-pkgs-card--active' : '')});
        var banner = el('div', {class: 'la-pkgs-banner', style: 'background:' + GRADS[idx % GRADS.length]});
        banner.innerHTML = BOLT +
            '<span class="la-pkgs-badges">' +
                '<span class="la-pkgs-flexbadge">' + esc(num(T.flex, p.flex_count)) + '</span>' +
                (p.offer ? '<span class="la-pkgs-offerbadge">' + TAG + esc(p.offer.label || ('-' + money(p.offer.discount) + ' ' + T.egp)) + '</span>' : '') +
                (isActive ? '<span class="la-pkgs-activebadge">' + esc(T.active) + '</span>' : '') +
            '</span>';
        card.appendChild(banner);

        var body = el('div', {class: 'la-pkgs-body'});
        body.appendChild(el('div', {class: 'la-pkgs-name'}, esc(p.name)));
        if (p.description) { body.appendChild(el('div', {class: 'la-pkgs-desc'}, esc(p.description))); }

        if (isActive) {
            var datesBox = el('div', {class: 'la-pkgs-dates'});
            datesBox.innerHTML =
                '<div class="la-pkgs-dates-row"><span>' + esc(T.flex_used_total) + '</span><b>' + esc(activePkg.used_flex) + ' / ' + esc(activePkg.total_flex) + '</b></div>' +
                '<div class="la-pkgs-dates-row"><span>' + esc(T.activated) + '</span><b>' + esc(fmtDate(activePkg.timeactivated)) + '</b></div>' +
                '<div class="la-pkgs-dates-row"><span>' + esc(T.expires) + '</span><b>' + (Number(activePkg.expires_at) > 0 ? esc(fmtDate(activePkg.expires_at)) : esc(T.never)) + '</b></div>';
            body.appendChild(datesBox);
        } else {
            var meta = el('div', {class: 'la-pkgs-meta'});
            meta.innerHTML = (Number(p.expiration_days) > 0)
                ? esc(T.valid_for).replace('{n}', '<b>' + esc(p.expiration_days) + '</b>')
                : '<b>' + esc(T.never_expires) + '</b>';
            body.appendChild(meta);
        }

        var foot = el('div', {class: 'la-pkgs-foot'});
        var pkgPriceHtml = p.offer
            ? '<span class="la-pkgs-price-old">' + esc(money(p.offer.original)) + '</span>' + esc(money(p.offer.final)) + ' <small>EGP</small>'
            : esc(money(p.price)) + ' <small>EGP</small>';
        foot.appendChild(el('div', {class: 'la-pkgs-price'}, pkgPriceHtml));
        var btn = el('button', {type: 'button', class: 'la-pkgs-btn'});
        if (!CFG.token) {
            // Guest/visitor — no account to buy with yet, send them to log in first.
            btn.textContent = T.login_to_buy;
            btn.onclick = function() { window.location.href = CFG.loginurl; };
        } else if (isActive) {
            btn.textContent = T.active; btn.disabled = true;
        } else if (hasActive) {
            btn.textContent = T.purchased; btn.disabled = true;
        } else {
            btn.textContent = T.buy_package; btn.onclick = function() { subscribe(p, btn); };
        }
        foot.appendChild(btn);
        body.appendChild(foot);

        card.appendChild(body);
        return card;
    }

    function renderRows(rows, hasActive, activePkg) {
        if (!rows.length) { return; } // nothing to sell — leave the section hidden

        // Only one package can be active at a time — explain why every "Buy package" button is
        // disabled with a single note under the section heading, instead of repeating it per card.
        var headnote = document.getElementById('la-pkgs-headnote');
        if (headnote) {
            if (hasActive) {
                headnote.innerHTML = INFO + '<span>' + esc(T.active_note) + '</span>';
                headnote.style.display = 'flex';
            } else {
                headnote.style.display = 'none';
            }
        }

        grid.innerHTML = '';
        rows.forEach(function(p, i) { grid.appendChild(pkgCard(p, hasActive, i, activePkg)); });
        sec.style.display = 'block';
    }

    if (CFG.token) {
        Promise.all([apiGet('get_available_packages'), apiGet('get_my_packages')]).then(function(res) {
            var rows = res[0] || [], mine = res[1] || [];
            var activePkg = mine.filter(function(p) { return p.status === 'active'; })[0] || null;
            renderRows(rows, !!activePkg, activePkg);
        }).catch(function() { /* keep the section hidden on any error */ });
    } else {
        // Guest/visitor — server already rendered the available packages; no purchase state to merge.
        renderRows(CFG.guestRows || [], false, null);
    }
});
JS;

    $PAGE->requires->js_amd_inline($js);
    return $section;
}

/**
 * Shared CSS for the two front-page program sections, emitted once per request.
 *
 * Both sections use the same la-prg-* card styling (same "PM Lounge" look as the packages and
 * subscriptions sections above them), so whichever renders first carries the <style> tag.
 *
 * @return string a <style> tag the first time it is called, '' afterwards
 */
function local_academy_programs_css() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;

    $css = <<<CSS
.la-prg{--pm:#c9922a;--pm-d:#e8b84b;--pm-bg:#080f1d;--pm-ink:#ffffff;--pm-muted:#8a9ab5;--pm-surface:#0d2149;--pm-border:rgba(201,146,42,.2);--pm-line:rgba(201,146,42,.14);--pm-navy:#0a1628;max-width:1280px;margin:3.5rem auto;padding:2.75rem 1.75rem;background:var(--pm-bg);border:1px solid var(--pm-border);border-radius:14px;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-prg-head{margin-bottom:1.75rem}
.la-prg-title{font-family:'Cairo',sans-serif;font-size:1.65rem;font-weight:800;color:var(--pm-ink);margin:0 0 .35rem}
.la-prg-sub{color:var(--pm-d);margin:0;font-size:1.05rem;font-weight:700}
.la-prg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.75rem;align-items:stretch}
.la-prg-card{display:flex;flex-direction:column;height:100%;background:var(--pm-surface);border:1px solid var(--pm-border);border-radius:10px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.25);transition:transform .18s ease,box-shadow .18s ease}
.la-prg-card:hover{transform:translateY(-6px);border-color:rgba(201,146,42,.45);box-shadow:0 18px 36px rgba(0,0,0,.4)}
.la-prg-card--clickable{cursor:pointer}
.la-prg-card--clickable:focus-visible{outline:3px solid var(--pm-d);outline-offset:2px}
.la-prg-card--clickable .la-prg-name{color:var(--pm-d)}
.la-prg-banner{position:relative;height:160px;flex-shrink:0;display:flex;flex-direction:column;justify-content:space-between;padding:1rem 1.1rem;color:var(--pm-d);overflow:hidden}
.la-prg-banner::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 15%,rgba(201,146,42,.18),transparent 55%)}
.la-prg-banner svg{width:48px;height:48px;opacity:.95;fill:var(--pm-d);position:relative;z-index:1}
.la-prg-badges{align-self:flex-start;position:relative;z-index:1;display:flex;gap:.4rem;flex-wrap:wrap}
.la-prg-badge{background:rgba(201,146,42,.15);border:1px solid var(--pm-border);color:var(--pm-d);font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-prg-badge--free{background:#00a99d;color:#04121f}
.la-prg-badge--owned{background:#1a3a6b;color:#e8b84b;border:1px solid rgba(201,146,42,.35)}
.la-prg-badge--done{background:#00a99d;color:#04121f}
.la-prg-badge--offer{display:inline-flex;align-items:center;gap:.25rem;background:#c0392b;color:#fff;font-weight:800}
.la-prg-body{padding:1.25rem 1.25rem 1.5rem;display:flex;flex-direction:column;flex:1}
.la-prg-name{font-family:'Cairo',sans-serif;font-weight:700;font-size:1.15rem;color:var(--pm-ink);margin:0 0 .5rem;line-height:1.35;min-height:2.7em}
.la-prg-desc{color:var(--pm-muted);font-size:.92rem;margin:0 0 .9rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.la-prg-dates{font-size:.86rem;color:var(--pm-muted);margin-top:auto;margin-bottom:1rem;padding-top:.9rem;border-top:1px solid var(--pm-line)}
.la-prg-dates-row{display:flex;justify-content:space-between;margin-top:.3rem}
.la-prg-dates-row b{color:var(--pm-ink)}
.la-prg-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding-top:.5rem}
.la-prg-price{font-size:1.5rem;font-weight:800;color:var(--pm-d)}
.la-prg-price small{font-size:.8rem;font-weight:600;color:var(--pm-muted)}
.la-prg-price-old{font-size:1rem;font-weight:600;color:#6b7c96;text-decoration:line-through;margin-inline-end:.35rem}
.la-prg-price--free{color:#00a99d}
.la-prg-btn{display:inline-block;background:linear-gradient(135deg,var(--pm),var(--pm-d));border:none;color:var(--pm-navy);font-family:'Cairo',sans-serif;font-weight:700;font-size:.95rem;padding:.7rem 1.5rem;border-radius:4px;cursor:pointer;text-decoration:none;transition:box-shadow .15s ease,transform .1s ease}
.la-prg-btn:hover{box-shadow:0 8px 25px rgba(201,146,42,.45);color:var(--pm-navy);text-decoration:none}
.la-prg-btn:active{transform:scale(.97)}
.la-prg-btn[disabled]{background:rgba(255,255,255,.09);color:var(--pm-muted);cursor:not-allowed}
.la-prg-err{color:#e57368;font-size:.85rem;margin-top:.5rem}
.la-prg-all{display:inline-block;margin-top:1.75rem;color:var(--pm-d);font-weight:700;text-decoration:none}
.la-prg-all:hover{color:var(--pm);text-decoration:underline}
/* Confirmation dialog (same pattern as la-pkgs / la-subs modals). */
.la-prg-modal-bg{position:fixed;inset:0;background:rgba(28,29,36,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:1rem;opacity:0;transition:opacity .18s ease;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-prg-modal-bg.open{display:flex;opacity:1}
.la-prg-modal{background:var(--pm-surface,#0d2149);border:1px solid rgba(201,146,42,.45);border-radius:12px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.6);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .18s ease}
.la-prg-modal-bg.open .la-prg-modal{transform:none}
.la-prg-modal-head{display:flex;align-items:center;gap:.7rem;padding:1.25rem 1.4rem 0}
.la-prg-modal-head svg{width:34px;height:34px;fill:#e8b84b;flex-shrink:0}
.la-prg-modal-head h4{margin:0;font-size:1.2rem;font-weight:800;color:#ffffff}
.la-prg-modal-body{padding:.9rem 1.4rem 0;color:#8a9ab5;font-size:.95rem;line-height:1.5}
.la-prg-modal-plan{margin:.9rem 0;padding:.9rem 1rem;background:rgba(201,146,42,.1);border:1px solid rgba(201,146,42,.2);border-radius:8px}
.la-prg-modal-plan .name{font-weight:700;color:#ffffff;margin-bottom:.35rem}
.la-prg-modal-row{display:flex;justify-content:space-between;font-size:.9rem;color:#8a9ab5;margin-top:.2rem}
.la-prg-modal-row b{color:#ffffff}
.la-prg-modal-secure{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#8a9ab5;margin-top:.2rem}
.la-prg-modal-secure svg{width:14px;height:14px;fill:#00a99d}
.la-prg-modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:1.2rem 1.4rem 1.3rem}
.la-prg-modal-cancel{background:transparent;border:1px solid rgba(201,146,42,.45);color:#8a9ab5;font-weight:600;font-size:.92rem;padding:.6rem 1.1rem;border-radius:4px;cursor:pointer}
.la-prg-modal-cancel:hover{background:rgba(201,146,42,.12);color:#e8b84b}
.la-prg-modal-ok{background:linear-gradient(135deg,#c9922a,#e8b84b);border:none;color:#0a1628;font-weight:700;font-size:.92rem;padding:.6rem 1.3rem;border-radius:4px;cursor:pointer}
.la-prg-modal-ok:hover{box-shadow:0 8px 25px rgba(201,146,42,.45)}
CSS;

    return html_writer::tag('style', $css);
}

/**
 * Makes program cards carrying data-href open that page, emitted once per request.
 *
 * The whole card is the target, so a visitor can tap anywhere on it to read the program details
 * before deciding to buy — not only the footer button. Clicks that land on the footer's own link or
 * button are left alone: those already have their own destination (Buy opens the checkout modal).
 *
 * @return string a <script> tag the first time it is called, '' afterwards
 */
function local_academy_programs_card_js() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;

    $js = <<<JS
(function () {
  function open(card, ev) {
    // The footer button/link handles itself — don't hijack it.
    if (ev.target.closest('a,button')) { return; }
    var href = card.getAttribute('data-href');
    if (href) { window.location.href = href; }
  }
  document.addEventListener('click', function (ev) {
    var card = ev.target.closest('.la-prg-card--clickable');
    if (card) { open(card, ev); }
  });
  // role="link" elements are expected to activate on Enter.
  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') { return; }
    var card = ev.target.closest && ev.target.closest('.la-prg-card--clickable');
    if (card) { ev.preventDefault(); open(card, ev); }
  });
})();
JS;

    return html_writer::tag('script', $js);
}

/**
 * One program card, shared by both front-page program sections.
 *
 * @param array  $badges  ready-made badge HTML pieces
 * @param string $name    program name (unescaped)
 * @param string $desc    short plain-text description
 * @param string $meta    optional block above the footer (dates, etc.)
 * @param string $price   footer price HTML ('' for none)
 * @param string $action  footer button/link HTML
 * @param int    $idx     card index, picks the banner gradient
 * @param string $href    optional details-page URL; makes the whole card clickable
 * @return string
 */
function local_academy_program_card($badges, $name, $desc, $meta, $price, $action, $idx, $href = '') {
    // Six dark, jewel-toned gradients so program cards stay visually distinct without
    // reintroducing the old rainbow palette, which clashed hard against the X-Trade
    // navy/gold identity. Each keeps enough contrast for the white/gold icon on top.
    $grads = array(
        'linear-gradient(135deg,#1a3a6b,#0a1628)',
        'linear-gradient(135deg,#0d2149,#1a3a6b)',
        'linear-gradient(135deg,#004d47,#00201d)',
        'linear-gradient(135deg,#5c1a24,#2c0a10)',
        'linear-gradient(135deg,#3a2a0a,#1a1206)',
        'linear-gradient(135deg,#1c2b45,#0a1628)',
    );
    $icon = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>';

    $cardattrs = $href !== '' ? ' data-href="' . s($href) . '" tabindex="0" role="link"' : '';
    return '<div class="la-prg-card' . ($href !== '' ? ' la-prg-card--clickable' : '') . '"' . $cardattrs . '>' .
        '<div class="la-prg-banner" style="background:' . $grads[$idx % count($grads)] . '">' .
            $icon .
            '<span class="la-prg-badges">' . $badges . '</span>' .
        '</div>' .
        '<div class="la-prg-body">' .
            html_writer::div(s($name), 'la-prg-name') .
            ($desc !== '' ? html_writer::div(s($desc), 'la-prg-desc') : '') .
            $meta .
            '<div class="la-prg-foot">' . $price . $action . '</div>' .
        '</div>' .
    '</div>';
}

/**
 * Front-page "Programs" section: every program this visitor may see in the enrol_programs catalogue,
 * as cards showing whether it is free or paid.
 *
 * Rendered server-side (unlike the packages/subscriptions sections, which fetch over the API) because
 * everything shown is already known at page build time. Only the Buy button needs the API, and it
 * reuses the same create_program_checkout call as the catalogue injection above.
 *
 * Guests see the same cards; their buttons lead to the login page instead of checkout.
 *
 * @return string HTML to echo before the footer, '' when there is nothing to show
 */
function local_academy_available_programs_section() {
    global $CFG, $DB, $USER;

    if (!\local_academy\program_purchase_manager::available()) {
        return '';
    }
    $loggedin = isloggedin() && !isguestuser();
    $rows = \local_academy\program_purchase_manager::get_catalogue_programs($loggedin ? (int)$USER->id : 0);
    if (!$rows) {
        return '';
    }

    // Only needed for the Buy button; without it a paid card falls back to the login link.
    $token = '';
    if ($loggedin) {
        try {
            require_once($CFG->dirroot . '/webservice/lib.php');
            require_once($CFG->libdir . '/externallib.php');
            $service = $DB->get_record('external_services',
                array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', IGNORE_MISSING);
            if ($service) {
                $token = external_generate_token_for_current_user($service)->token;
            }
        } catch (\Throwable $e) {
            $token = '';
        }
    }

    $loginurl = (string) new moodle_url('/login/index.php');
    $tag = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:13px;height:13px;fill:#fff"><path d="M21.41 11.58l-9-9A2 2 0 0011 2H4a2 2 0 00-2 2v7a2 2 0 00.59 1.42l9 9a2 2 0 002.82 0l7-7a2 2 0 000-2.84zM6.5 8A1.5 1.5 0 118 6.5 1.5 1.5 0 016.5 8z"/></svg>';

    $cards = '';
    foreach (array_values($rows) as $i => $p) {
        $programurl = (string) new moodle_url('/enrol/programs/catalogue/program.php', array('id' => $p['id']));

        $badges = html_writer::span(
            s(get_string($p['free'] ? 'hp_prg_free' : 'hp_prg_paid', 'local_academy')),
            'la-prg-badge' . ($p['free'] ? ' la-prg-badge--free' : ''));
        if (!empty($p['offer'])) {
            $badges .= html_writer::span($tag . s($p['offer']['label']), 'la-prg-badge la-prg-badge--offer');
        }
        if ($p['owned']) {
            $badges .= html_writer::span(s(get_string('hp_prg_enrolled', 'local_academy')),
                'la-prg-badge la-prg-badge--owned');
        }

        if ($p['free']) {
            $price = html_writer::div(s(get_string('hp_prg_free', 'local_academy')), 'la-prg-price la-prg-price--free');
        } else if (!empty($p['offer'])) {
            $price = html_writer::div(
                html_writer::span(format_float($p['offer']['original'], 2), 'la-prg-price-old') .
                format_float($p['offer']['final'], 2) . ' <small>' . s($p['currency']) . '</small>',
                'la-prg-price');
        } else {
            $price = html_writer::div(
                format_float($p['price'], 2) . ' <small>' . s($p['currency']) . '</small>', 'la-prg-price');
        }

        if ($p['owned']) {
            // Already allocated — send them to their own program page, not back to checkout.
            $action = html_writer::link(
                (string) new moodle_url('/enrol/programs/my/program.php', array('id' => $p['id'])),
                s(get_string('hp_prg_open', 'local_academy')), array('class' => 'la-prg-btn'));
        } else if ($p['free']) {
            $action = html_writer::link($loggedin ? $programurl : $loginurl,
                s(get_string($p['joinable'] ? 'hp_prg_join' : 'hp_prg_view', 'local_academy')),
                array('class' => 'la-prg-btn'));
        } else if (!$token) {
            $action = html_writer::link($loginurl, s(get_string('hp_login_to_buy', 'local_academy')),
                array('class' => 'la-prg-btn'));
        } else {
            $action = html_writer::tag('button', s(get_string('prg_buy', 'local_academy')),
                array('type' => 'button', 'class' => 'la-prg-btn la-prg-buy',
                    'data-programid' => $p['id'],
                    'data-name'      => $p['name'],
                    'data-price'     => $p['price']));
        }

        // Whole card opens the details page, so a visitor can read up before buying. Owners get
        // their own program page; guests get the login page, same destination as their button.
        if ($p['owned']) {
            $cardhref = (string) new moodle_url('/enrol/programs/my/program.php', array('id' => $p['id']));
        } else {
            $cardhref = $loggedin ? $programurl : $loginurl;
        }

        $cards .= local_academy_program_card($badges, $p['name'], $p['description'], '', $price, $action, $i, $cardhref);
    }

    $out = local_academy_programs_css() . local_academy_programs_card_js() .
        '<section id="la-prg-available" class="la-prg">' .
            '<div class="la-prg-head">' .
                html_writer::tag('h3', s(get_string('hp_prg_heading', 'local_academy')), array('class' => 'la-prg-title')) .
                html_writer::tag('p', s(get_string('hp_prg_desc', 'local_academy')), array('class' => 'la-prg-sub')) .
            '</div>' .
            '<div class="la-prg-grid">' . $cards . '</div>' .
            ($loggedin ? html_writer::link(
                (string) new moodle_url('/enrol/programs/catalogue/index.php'),
                s(get_string('hp_prg_all', 'local_academy')), array('class' => 'la-prg-all')) : '') .
        '</section>';

    if ($token) {
        $str = array(
            'sess_expired'    => get_string('err_sessionexpired', 'local_academy'),
            'req_failed'      => get_string('err_requestfailed', 'local_academy'),
            'confirm_title'   => get_string('hp_prg_confirm_title', 'local_academy'),
            'confirm_body'    => get_string('hp_prg_confirm_body', 'local_academy'),
            'total'           => get_string('hp_total', 'local_academy'),
            'egp'             => get_string('hp_egp', 'local_academy'),
            'coupon'          => get_string('hp_coupon', 'local_academy'),
            'apply'           => get_string('hp_apply', 'local_academy'),
            'discount'        => get_string('hp_discount', 'local_academy'),
            'secure'          => get_string('hp_secure', 'local_academy'),
            'cancel'          => get_string('hp_cancel', 'local_academy'),
            'proceed'         => get_string('hp_proceed', 'local_academy'),
            'redirecting'     => get_string('hp_prg_redirecting', 'local_academy'),
        );
        $cfg = json_encode(array(
            'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
            'token'    => $token,
            'lang'     => current_language(),
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $strjson = json_encode($str, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $out .= html_writer::script(<<<JS
(function () {
  var CFG = {$cfg};
  var T = {$strjson};

  function el(tag, attrs, html) {
    var e = document.createElement(tag);
    for (var k in (attrs || {})) { e.setAttribute(k, attrs[k]); }
    if (html != null) { e.innerHTML = html; }
    return e;
  }
  function esc(v) {
    return (v == null ? '' : String(v)).replace(/[&<>"]/g, function(c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
    });
  }
  function money(n) { return Number(n || 0).toFixed(2); }
  function parse(r) {
    return r.text().then(function(t) {
      var j;
      try { j = JSON.parse(t); } catch (e) { throw new Error(T.sess_expired); }
      if (j.status !== 'success') { throw new Error(j.error || T.req_failed); }
      return j.data;
    });
  }
  function apiGet(fn, params) {
    var base = {'function': fn, token: CFG.token}; if (CFG.lang) { base.alang = CFG.lang; }
    var q = new URLSearchParams(Object.assign(base, params || {}));
    return fetch(CFG.endpoint + '?' + q.toString()).then(parse);
  }
  function apiPost(fn, params) {
    var base = {'function': fn, token: CFG.token}; if (CFG.lang) { base.alang = CFG.lang; }
    var body = new URLSearchParams(Object.assign(base, params || {}));
    return fetch(CFG.endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    }).then(parse);
  }

  function confirmBuyProgram(p) {
    return new Promise(function(resolve) {
      var LOCK = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1a5 5 0 00-5 5v3H6a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2v-9a2 2 0 00-2-2h-1V6a5 5 0 00-5-5zm3 8H9V6a3 3 0 016 0v3z"/></svg>';
      var CAP = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>';
      var bg = el('div', {'class': 'la-prg-modal-bg'});
      bg.innerHTML =
        '<div class="la-prg-modal" role="dialog" aria-modal="true">' +
          '<div class="la-prg-modal-head">' + CAP + '<h4>' + esc(T.confirm_title) + '</h4></div>' +
          '<div class="la-prg-modal-body">' +
            '<p>' + esc(T.confirm_body) + '</p>' +
            '<div class="la-prg-modal-plan">' +
              '<div class="name">' + esc(p.name) + '</div>' +
              '<div class="la-prg-modal-row"><span>' + esc(T.total) + '</span><b class="la-prg-orig">' + esc(money(p.price)) + ' ' + esc(T.egp) + '</b></div>' +
              '<div class="la-prg-modal-row"><span>' + esc(T.coupon) + '</span><span style="display:flex;gap:.3rem"><input type="text" class="la-prg-coupon" style="max-width:110px;border:1px solid #d1d7dc;border-radius:4px;padding:.15rem .4rem"><button type="button" class="la-prg-apply" style="border:1px solid #6c22a6;background:#fff;color:#6c22a6;border-radius:4px;padding:.15rem .6rem;cursor:pointer">' + esc(T.apply) + '</button></span></div>' +
              '<div class="la-prg-coupon-msg" style="color:#c0392b;font-size:.8rem;margin:.2rem 0"></div>' +
              '<div class="la-prg-modal-row"><span>' + esc(T.discount) + '</span><b class="la-prg-disc" style="color:#1f9d55">0.00 ' + esc(T.egp) + '</b></div>' +
              '<div class="la-prg-modal-row" style="border-top:1px solid #e4d3f5;margin-top:.35rem;padding-top:.45rem"><span style="font-weight:700">' + esc(T.total) + '</span><b class="la-prg-final" style="color:#6c22a6;font-size:1.2rem">' + esc(money(p.price)) + ' ' + esc(T.egp) + '</b></div>' +
            '</div>' +
            '<div class="la-prg-modal-secure">' + LOCK + '<span>' + esc(T.secure) + '</span></div>' +
          '</div>' +
          '<div class="la-prg-modal-foot">' +
            '<button type="button" class="la-prg-modal-cancel">' + esc(T.cancel) + '</button>' +
            '<button type="button" class="la-prg-modal-ok">' + esc(T.proceed) + '</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(bg);
      void bg.offsetWidth;
      bg.classList.add('open');

      function close(result) {
        bg.classList.remove('open');
        document.removeEventListener('keydown', onKey);
        setTimeout(function() { if (bg.parentNode) { bg.parentNode.removeChild(bg); } }, 180);
        resolve(result);
      }
      function onKey(e) { if (e.key === 'Escape') { close(null); } }
      document.addEventListener('keydown', onKey);

      var couponInput = bg.querySelector('.la-prg-coupon');
      var discEl = bg.querySelector('.la-prg-disc');
      var finalEl = bg.querySelector('.la-prg-final');
      var origEl = bg.querySelector('.la-prg-orig');
      var cmsg = bg.querySelector('.la-prg-coupon-msg');
      function preview(code) {
        apiGet('preview_discount', {item_type: 'program', item_id: p.id, coupon_code: code || ''})
          .then(function(d) {
            discEl.textContent = (d.discount > 0 ? '-' + money(d.discount) : '0.00') + ' ' + T.egp;
            finalEl.textContent = money(d.final) + ' ' + T.egp;
            origEl.style.textDecoration = d.discount > 0 ? 'line-through' : 'none';
            cmsg.textContent = d.coupon_error ? d.coupon_error : '';
          }).catch(function() {});
      }
      bg.querySelector('.la-prg-apply').onclick = function() { preview(couponInput.value.trim()); };
      couponInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); preview(couponInput.value.trim()); } });
      var prgDeb;
      couponInput.addEventListener('input', function() {
        clearTimeout(prgDeb);
        prgDeb = setTimeout(function() { preview(couponInput.value.trim()); }, 450);
      });
      preview('');

      bg.querySelector('.la-prg-modal-cancel').onclick = function() { close(null); };
      bg.querySelector('.la-prg-modal-ok').onclick = function() { close(couponInput.value.trim()); };
      bg.onclick = function(e) { if (e.target === bg) { close(null); } };
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('#la-prg-available .la-prg-buy'), function (btn) {
    btn.addEventListener('click', function () {
      var p = { id: btn.getAttribute('data-programid'), name: btn.getAttribute('data-name'), price: btn.getAttribute('data-price') };
      confirmBuyProgram(p).then(function(code) {
        if (code === null) { return; }
        var orig = btn.textContent;
        btn.disabled = true; btn.textContent = T.redirecting;
        apiPost('create_program_checkout', {programid: p.id, coupon_code: code})
          .then(function(d) { window.location.href = d.checkout_url; })
          .catch(function(e) {
            btn.disabled = false; btn.textContent = orig;
            var err = btn.parentNode.parentNode.querySelector('.la-prg-err');
            if (!err) { err = document.createElement('div'); err.className = 'la-prg-err'; btn.parentNode.parentNode.appendChild(err); }
            err.textContent = e.message;
          });
      });
    });
  });
})();
JS
        );
    }

    return $out;
}

/**
 * Front-page "My programs" section: the programs the logged-in user is allocated to (bought, was
 * assigned, or signed up for free), with their dates and completion state.
 *
 * Hidden entirely for guests and for users with no programs.
 *
 * @return string HTML to echo before the footer, '' when there is nothing to show
 */
function local_academy_my_programs_section() {
    global $USER;

    if (!isloggedin() || isguestuser() || !\local_academy\program_purchase_manager::available()) {
        return '';
    }
    $rows = \local_academy\program_purchase_manager::get_my_programs((int)$USER->id);
    if (!$rows) {
        return '';
    }

    $dateformat = get_string('strftimedatefullshort');
    $notset = get_string('hp_prg_notset', 'local_academy');

    $cards = '';
    foreach ($rows as $i => $p) {
        $badges = html_writer::span(
            s(get_string($p['completed'] ? 'hp_prg_completed' : 'hp_prg_inprogress', 'local_academy')),
            'la-prg-badge' . ($p['completed'] ? ' la-prg-badge--done' : ''));

        $row = function($label, $ts) use ($dateformat, $notset) {
            return '<div class="la-prg-dates-row"><span>' . s($label) . '</span><b>' .
                s($ts ? userdate($ts, $dateformat) : $notset) . '</b></div>';
        };
        $meta = '<div class="la-prg-dates">' .
            $row(get_string('hp_prg_started', 'local_academy'), $p['timestart']) .
            $row(get_string('hp_prg_due', 'local_academy'), $p['timedue']) .
            ($p['completed']
                ? $row(get_string('hp_prg_completed', 'local_academy'), $p['timecompleted'])
                : $row(get_string('hp_prg_ends', 'local_academy'), $p['timeend'])) .
        '</div>';

        $programurl = (string) new moodle_url('/enrol/programs/my/program.php', array('id' => $p['id']));
        $action = html_writer::link($programurl,
            s(get_string('hp_prg_open', 'local_academy')), array('class' => 'la-prg-btn'));

        $cards .= local_academy_program_card($badges, $p['name'], $p['description'], $meta, '', $action, $i, $programurl);
    }

    return local_academy_programs_css() . local_academy_programs_card_js() .
        '<section id="la-prg-mine" class="la-prg">' .
            '<div class="la-prg-head">' .
                html_writer::tag('h3', s(get_string('hp_myprg_heading', 'local_academy')), array('class' => 'la-prg-title')) .
                html_writer::tag('p', s(get_string('hp_myprg_desc', 'local_academy')), array('class' => 'la-prg-sub')) .
            '</div>' .
            '<div class="la-prg-grid">' . $cards . '</div>' .
            html_writer::link((string) new moodle_url('/enrol/programs/my/index.php'),
                s(get_string('hp_myprg_all', 'local_academy')), array('class' => 'la-prg-all')) .
        '</section>';
}

/**
 * Certificate card injected into the enrol_programs program pages. Shows every ENABLED certificate
 * the program offers and, per rule, what it takes to earn it.
 *
 * Two modes, because the plugin has two program pages and the visitor's relationship to the program
 * differs on each:
 *
 *  - OWNER (/enrol/programs/my/program.php, $preview = false) — the student is allocated. Shows
 *    live eligibility: a status badge, a ✓/✗ per rule with achieved-vs-required, and, once they
 *    qualify, a link to the certificate itself.
 *  - PREVIEW (/enrol/programs/catalogue/program.php, $preview = true) — the student is deciding
 *    whether to buy, or has bought nothing yet. The certificate is part of what they are being
 *    offered, so the requirements must be visible BEFORE purchase. Shows the same requirements as a
 *    plain checklist, with no pass/fail marks (they have not started — marking every line ✗ would be
 *    accurate but reads as rejection) and no link to the certificate.
 *
 * Injected from local_academy so the third-party plugin's files stay untouched. Eligibility itself is
 * still decided here and nothing is issued: when a certificate is linked to a Custom Certificate
 * activity (externalref) and the student qualifies, the card grants them access to that activity's
 * host course and links to it — mod_customcert remains the only thing that generates a certificate.
 * See {@see \local_academy\cert\customcert_link}. An unlinked certificate shows progress only.
 *
 * Preview mode never calls grant_access(): enrolling a non-buyer into the certificate's host course
 * would hand out access the student has not paid for.
 *
 * Rendered server-side (we already have $USER and the program id); a small script relocates it into
 * the page's main region so it sits with the rest of the program content rather than at page end.
 *
 * @param bool $preview true on the catalogue page (requirements only, nothing granted)
 * @return string HTML to echo before the footer, '' when there is nothing to show
 */
function local_academy_program_certificates_section($preview = false) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }
    $programid = optional_param('id', 0, PARAM_INT);
    if ($programid <= 0) {
        return '';
    }

    $reports = \local_academy\cert\eligibility_manager::get_program_certificate_reports((int)$USER->id, $programid);
    // Only advertise enabled certificates — a disabled one is not offered to students.
    $reports = array_values(array_filter($reports, function ($r) {
        return !empty($r['enabled']);
    }));
    if (!$reports) {
        return '';
    }

    $cards = '';
    foreach ($reports as $r) {
        // In preview the student has not started, so eligibility is noise — the card answers "what
        // will this take?", not "how am I doing?".
        $eligible = !$preview && !empty($r['eligible']);
        $statusclass = $eligible ? 'la-cert-status--ok' : 'la-cert-status--pending';
        $statuslabel = $eligible
            ? get_string('cert_student_eligible', 'local_academy')
            : get_string('cert_student_not_eligible', 'local_academy');

        // One row per rule: its label, a passed/failed marker, and achieved-vs-required when the rule
        // reports a measurable value (e.g. progress %). Boolean rules just show the marker.
        $rules = '';
        foreach (($r['results'] ?? array()) as $res) {
            $passed = !empty($res['passed']);
            // Preview: a neutral bullet. Achieved-vs-required is progress reporting, which a student
            // who has not joined yet has none of — "0 / 90 %" on every line is discouraging noise.
            $mark = $preview ? '•' : ($passed ? '✓' : '✗');
            $markclass = $preview ? 'la-cert-mark--todo' : ($passed ? 'la-cert-mark--ok' : 'la-cert-mark--no');
            $unit = (string)($res['unit'] ?? '');
            $required = $res['required'] ?? null;
            $detail = '';
            // Show "achieved / required [unit]" when the rule exposes a meaningful count to compare:
            // a unit (e.g. progress %) or a required total above one (e.g. 2 / 3 courses). A plain
            // yes/no rule (required 1, no unit) needs no number — the marker already says it.
            $showdetail = !$preview && ($unit !== '' || (is_numeric($required) && (float)$required > 1));
            if ($required !== null && $required !== '' && $showdetail) {
                $detail = html_writer::tag('span',
                    s((string)$res['actual']) . ' / ' . s((string)$required) . ($unit !== '' ? ' ' . s($unit) : ''),
                    array('class' => 'la-cert-detail'));
            }
            // Lead with the requirement in plain language ("Complete at least 90% of the program's
            // courses"). The rule-type label ("Program progress ≥ threshold %") is admin wording and
            // tells a student nothing, so it is only the fallback when a rule cannot describe itself.
            $requirement = trim((string)($res['description'] ?? ''));
            if ($requirement === '') {
                $requirement = (string)($res['label'] ?? '');
            }

            $rules .= '<li class="la-cert-rule">' .
                html_writer::tag('span', $mark, array('class' => 'la-cert-mark ' . $markclass)) .
                html_writer::tag('span', s($requirement), array('class' => 'la-cert-rule-label')) .
                $detail .
            '</li>';
        }

        $operatornote = ($r['operator'] ?? 'and') === 'or'
            ? get_string('cert_student_any', 'local_academy')
            : get_string('cert_student_all', 'local_academy');

        // The real certificate lives in a customcert activity inside a host course. An eligible
        // student is enrolled there on the spot (best-effort) so the link below actually opens.
        // Never in preview — $eligible is forced false there, so grant_access() cannot run for a
        // student who has not joined the program.
        $action = '';
        $externalref = (int)($r['externalref'] ?? 0);
        if ($eligible && $externalref > 0) {
            $url = \local_academy\cert\customcert_link::view_url($externalref);
            if ($url && \local_academy\cert\customcert_link::grant_access((int)$USER->id, $externalref)) {
                $action = html_writer::link($url,
                    s(get_string('cert_student_download', 'local_academy')),
                    array('class' => 'la-cert-dl', 'target' => '_blank', 'rel' => 'noopener'));
            }
        }

        // Preview badge says what the certificate IS ("Included with this program"), not how the
        // student is doing — there is nothing to be doing yet.
        $badge = $preview
            ? html_writer::tag('span', s(get_string('cert_student_included', 'local_academy')),
                array('class' => 'la-cert-status la-cert-status--included'))
            : html_writer::tag('span', $statuslabel, array('class' => 'la-cert-status ' . $statusclass));

        if ($preview) {
            $note = get_string('cert_student_preview_note', 'local_academy');
            $noteclass = 'la-cert-note';
        } else if ($eligible) {
            $note = get_string('cert_student_eligible_note', 'local_academy');
            $noteclass = 'la-cert-note la-cert-note--ok';
        } else {
            $note = get_string('cert_student_pending_note', 'local_academy');
            $noteclass = 'la-cert-note';
        }

        $cards .= '<div class="la-cert-card">' .
            '<div class="la-cert-card-head">' .
                html_writer::tag('span', s((string)($r['name'] ?? '')), array('class' => 'la-cert-name')) .
                $badge .
            '</div>' .
            html_writer::tag('p', $operatornote, array('class' => 'la-cert-op')) .
            '<ul class="la-cert-rules">' . $rules . '</ul>' .
            html_writer::tag('p', $note, array('class' => $noteclass)) .
            $action .
        '</div>';
    }

    $css = <<<CSS
.la-cert{--pm:#6c22a6;max-width:1280px;margin:2rem auto;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-cert-title{font-size:1.35rem;font-weight:800;color:#1c1d1f;margin:0 0 1.1rem;display:flex;align-items:center;gap:.5rem}
.la-cert-title svg{width:26px;height:26px;fill:var(--pm)}
.la-cert-intro{color:#6a6f73;font-size:.95rem;margin:-.6rem 0 1.1rem}
.la-cert-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem}
.la-cert-card{background:#fff;border:1px solid #e8e6ef;border-radius:10px;padding:1.1rem 1.2rem;box-shadow:0 4px 14px rgba(108,34,166,.07)}
.la-cert-card-head{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:.4rem}
.la-cert-name{font-weight:700;font-size:1.05rem;color:#1c1d1f}
.la-cert-status{font-size:.78rem;font-weight:800;padding:.25rem .65rem;border-radius:1rem;white-space:nowrap}
.la-cert-status--ok{background:#1f9d55;color:#fff}
.la-cert-status--pending{background:#f1f3f5;color:#6a6f73}
.la-cert-op{font-size:.82rem;color:#6a6f73;margin:0 0 .7rem}
.la-cert-rules{list-style:none;margin:0;padding:0}
.la-cert-rule{display:flex;align-items:center;gap:.55rem;padding:.4rem 0;border-top:1px solid #f4f4f6;font-size:.92rem}
.la-cert-rule:first-child{border-top:none}
.la-cert-mark{width:1.3rem;height:1.3rem;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;font-size:.8rem;font-weight:800;color:#fff}
.la-cert-mark--ok{background:#1f9d55}
.la-cert-mark--no{background:#c0392b}
.la-cert-mark--todo{background:#e8e6ef;color:#6c22a6}
.la-cert-status--included{background:#f5eefc;color:#6c22a6}
.la-cert-rule-label{color:#1c1d1f;flex:1}
.la-cert-detail{color:#6a6f73;font-weight:700;white-space:nowrap}
.la-cert-note{font-size:.85rem;color:#6a6f73;margin:.8rem 0 0}
.la-cert-note--ok{color:#1f7a43;font-weight:700}
.la-cert-dl{display:inline-flex;align-items:center;justify-content:center;width:100%;margin-top:.9rem;padding:.6rem 1rem;border-radius:8px;background:var(--pm);color:#fff;font-weight:700;font-size:.92rem;text-decoration:none}
.la-cert-dl:hover{background:#57187f;color:#fff;text-decoration:none}
CSS;

    $icon = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 15-4-4 1.41-1.41L10 13.17l6.59-6.58L18 8l-8 8z"/></svg>';

    $title = $preview
        ? get_string('cert_student_preview_title', 'local_academy')
        : get_string('cert_student_title', 'local_academy');

    $html = html_writer::tag('style', $css) .
        '<section id="la-cert" class="la-cert">' .
            html_writer::tag('h3', $icon . s($title), array('class' => 'la-cert-title')) .
            ($preview
                ? html_writer::tag('p', s(get_string('cert_student_preview_intro', 'local_academy')),
                    array('class' => 'la-cert-intro'))
                : '') .
            '<div class="la-cert-grid">' . $cards . '</div>' .
        '</section>';

    // Move the section up into the page's main content region (before_footer output otherwise lands
    // at the very bottom of the page, below all program content).
    $html .= <<<'JS'
<script>
(function () {
    var sec = document.getElementById('la-cert');
    if (!sec) { return; }
    var main = document.getElementById('region-main') || document.querySelector('[role="main"]');
    if (main) { main.appendChild(sec); }
})();
</script>
JS;

    return $html;
}

/**
 * Front-page "Comments from our distinguished customers" testimonials block (Figma "PM Lounge"
 * design). Static, localizable marketing content — no purchase logic — rendered after the course
 * cards. Returns HTML to echo before the footer.
 *
 * @return string
 */
function local_academy_home_testimonials_section() {
    $heading = get_string('hp_testi_heading', 'local_academy');
    $courselink = (string) new moodle_url('/', array(), 'la-subs');

    $css = <<<CSS
.la-testi{--pm:#c9922a;--pm-d:#e8b84b;max-width:1280px;margin:3.5rem auto;padding:2.75rem 1.75rem;background:#080f1d;border:1px solid rgba(201,146,42,.2);border-radius:14px;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-testi-title{font-family:'Cairo',sans-serif;font-size:1.55rem;font-weight:800;color:#ffffff;margin:0 0 1.75rem}
.la-testi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem}
.la-testi-card{position:relative;background:#0d2149;border:1px solid rgba(201,146,42,.2);border-radius:10px;padding:2.4rem 1.6rem 1.5rem;display:flex;flex-direction:column}
.la-testi-quote{position:absolute;top:.4rem;left:1.4rem;font-size:3rem;line-height:1;color:#e8b84b;font-weight:800}
.la-testi-text{color:#8a9ab5;font-size:1rem;line-height:1.55;margin:0 0 1.2rem;text-align:justify;flex:1}
.la-testi-user{display:flex;align-items:center;gap:.7rem;margin-bottom:.35rem}
.la-testi-av{width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#0a1628;font-weight:700;font-size:.95rem;background:linear-gradient(135deg,var(--pm),var(--pm-d))}
.la-testi-name{font-weight:700;color:#ffffff;font-size:.95rem}
.la-testi-stars{display:flex;gap:2px;margin:.15rem 0 1rem}
.la-testi-stars svg{width:16px;height:16px;fill:#e8b84b}
.la-testi-course{display:flex;align-items:center;gap:.5rem;padding-top:1rem;border-top:1px solid rgba(201,146,42,.2);color:var(--pm-d);font-weight:700;font-size:.9rem;text-decoration:none;line-height:1.3}
.la-testi-course:hover{color:var(--pm);text-decoration:none}
.la-testi-course svg{width:20px;height:20px;fill:var(--pm-d);flex-shrink:0}
CSS;

    $star = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
    $badge = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2 4 5v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V5l-8-3zm-1 13-3-3 1.4-1.4L11 12.2l4.6-4.6L17 9l-6 6z"/></svg>';

    $cards = '';
    for ($i = 1; $i <= 3; $i++) {
        $quote = get_string('hp_testi' . $i . '_quote', 'local_academy');
        $name  = get_string('hp_testi' . $i . '_name', 'local_academy');
        $course = get_string('hp_testi' . $i . '_course', 'local_academy');
        $initial = core_text::strtoupper(core_text::substr(trim($name), 0, 1));
        $cards .=
            '<div class="la-testi-card">' .
                '<span class="la-testi-quote">&ldquo;</span>' .
                html_writer::tag('p', s($quote), array('class' => 'la-testi-text')) .
                '<div class="la-testi-user">' .
                    html_writer::tag('span', s($initial), array('class' => 'la-testi-av')) .
                    html_writer::tag('span', s($name), array('class' => 'la-testi-name')) .
                '</div>' .
                '<div class="la-testi-stars">' . str_repeat($star, 5) . '</div>' .
                html_writer::link($courselink, $badge . html_writer::tag('span', s($course)),
                    array('class' => 'la-testi-course')) .
            '</div>';
    }

    return html_writer::tag('style', $css) .
        '<section class="la-testi">' .
            html_writer::tag('h3', s($heading), array('class' => 'la-testi-title')) .
            '<div class="la-testi-grid">' . $cards . '</div>' .
        '</section>';
}

/**
 * Front-page "Articles" block (Figma "PM Lounge" design). Static, localizable marketing content
 * linking out to the site blog. Returns HTML to echo before the footer.
 *
 * @return string
 */
function local_academy_home_articles_section() {
    $heading  = get_string('hp_arts_heading', 'local_academy');
    $title    = get_string('hp_arts_title', 'local_academy');
    $body     = get_string('hp_arts_body', 'local_academy');
    $readmore = get_string('hp_arts_readmore', 'local_academy');
    $readall  = get_string('hp_arts_readall', 'local_academy');
    $bloglink = (string) new moodle_url('/blog/index.php');

    $css = <<<CSS
.la-arts{--pm:#c9922a;--pm-d:#e8b84b;background:#080f1d;border-top:1px solid rgba(201,146,42,.2);border-bottom:1px solid rgba(201,146,42,.2);padding:3rem 0;margin:3.5rem 0;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-arts-in{max-width:1280px;margin:0 auto;padding:0 1.75rem}
.la-arts-heading{font-family:'Cairo',sans-serif;font-size:1.55rem;font-weight:800;color:#ffffff;margin:0 0 1.5rem}
.la-arts-card{display:flex;gap:2rem;align-items:stretch;flex-wrap:wrap}
.la-arts-text{flex:1 1 420px;min-width:280px}
.la-arts-title{font-family:'Cairo',sans-serif;font-size:1.2rem;font-weight:700;color:#ffffff;margin:0 0 1rem;line-height:1.35}
.la-arts-body{color:#8a9ab5;font-size:1rem;line-height:1.6;text-align:justify;margin:0 0 1.4rem}
.la-arts-btn{display:inline-block;background:linear-gradient(135deg,var(--pm),var(--pm-d));color:#0a1628;font-weight:700;font-size:.95rem;padding:.65rem 1.6rem;border-radius:4px;text-decoration:none}
.la-arts-btn:hover{box-shadow:0 8px 25px rgba(201,146,42,.45);color:#0a1628;text-decoration:none}
.la-arts-img{flex:0 0 335px;max-width:100%;min-height:300px;border-radius:10px;border:1px solid rgba(201,146,42,.2);background:linear-gradient(135deg,#1a3a6b,#0a1628);display:flex;align-items:center;justify-content:center}
.la-arts-img svg{width:88px;height:88px;fill:rgba(232,184,75,.85)}
.la-arts-more{display:block;text-align:center;margin-top:2rem;color:var(--pm-d);font-weight:700;font-size:1rem;text-decoration:none}
.la-arts-more:hover{color:var(--pm);text-decoration:none}
@media (max-width:768px){.la-arts-img{flex-basis:100%;min-height:200px}}
CSS;

    $doc = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>';

    return html_writer::tag('style', $css) .
        '<section class="la-arts"><div class="la-arts-in">' .
            html_writer::tag('h3', s($heading), array('class' => 'la-arts-heading')) .
            '<div class="la-arts-card">' .
                '<div class="la-arts-text">' .
                    html_writer::tag('h4', s($title), array('class' => 'la-arts-title')) .
                    html_writer::tag('p', s($body), array('class' => 'la-arts-body')) .
                    html_writer::link($bloglink, s($readmore), array('class' => 'la-arts-btn')) .
                '</div>' .
                '<div class="la-arts-img">' . $doc . '</div>' .
            '</div>' .
            html_writer::link($bloglink, s($readall) . '  &rsaquo;', array('class' => 'la-arts-more')) .
        '</div></section>';
}

/**
 * Front-page "PMlounge Business" call-to-action block (Figma "PM Lounge" design). Static,
 * localizable marketing content pointing visitors at the (business) subscription cards. Returns
 * HTML to echo before the footer.
 *
 * @return string
 */
function local_academy_home_business_section() {
    $title = get_string('hp_biz_title', 'local_academy');
    $body  = get_string('hp_biz_body', 'local_academy');
    $join  = get_string('hp_biz_join', 'local_academy');
    $link  = (string) new moodle_url('/', array(), 'la-subs');

    $css = <<<CSS
.la-biz{--pm:#c9922a;--pm-d:#e8b84b;max-width:1280px;margin:3.5rem auto;padding:2.75rem 1.75rem;background:#080f1d;border:1px solid rgba(201,146,42,.2);border-radius:14px;font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif}
.la-biz-in{display:flex;gap:2.5rem;align-items:center;flex-wrap:wrap}
.la-biz-img{flex:0 0 480px;max-width:100%;min-height:340px;border-radius:10px;border:1px solid rgba(201,146,42,.2);background:linear-gradient(135deg,#1a3a6b,#0a1628);display:flex;align-items:center;justify-content:center}
.la-biz-img svg{width:110px;height:110px;fill:rgba(232,184,75,.85)}
.la-biz-text{flex:1 1 380px;min-width:280px}
.la-biz-title{font-family:'Cairo',sans-serif;font-size:1.55rem;font-weight:800;color:#ffffff;margin:0 0 1.1rem}
.la-biz-body{color:#8a9ab5;font-size:1.05rem;line-height:1.6;text-align:justify;margin:0 0 1.6rem}
.la-biz-btn{display:inline-block;background:linear-gradient(135deg,var(--pm),var(--pm-d));color:#0a1628;font-weight:700;font-size:1rem;padding:.75rem 1.9rem;border-radius:4px;text-decoration:none}
.la-biz-btn:hover{box-shadow:0 8px 25px rgba(201,146,42,.45);color:#0a1628;text-decoration:none}
@media (max-width:768px){.la-biz-img{flex-basis:100%;min-height:220px}}
CSS;

    $team = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>';

    return html_writer::tag('style', $css) .
        '<section class="la-biz"><div class="la-biz-in">' .
            '<div class="la-biz-img">' . $team . '</div>' .
            '<div class="la-biz-text">' .
                html_writer::tag('h3', s($title), array('class' => 'la-biz-title')) .
                html_writer::tag('p', s($body), array('class' => 'la-biz-body')) .
                html_writer::link($link, s($join), array('class' => 'la-biz-btn')) .
            '</div>' .
        '</div></section>';
}

/**
 * True if the current user manages the Academy Flex platform (site admin or the 'manager' role
 * that holds the plugin's manage capabilities). The student-facing front-page purchase sections
 * ("Available subscriptions" / "Available packages") are not shown to them.
 *
 * @return bool
 */
function local_academy_is_platform_manager() {
    $context = \context_system::instance();
    return has_capability('local/academy:managepackages', $context)
        || has_capability('local/academy:managesubscriptions', $context)
        || has_capability('local/academy:manageplatform', $context);
}

/**
 * Add an "Available subscriptions" section (Udemy/Coursera-style cards) to the site front page so
 * visitors — logged in or not — can discover course-access subscriptions and Flex packages directly
 * from home. Replaces the old "Book lessons & Flex" banner. Guests can browse the cards but their
 * buy buttons lead to the login page rather than checkout. Each section renders itself only when at
 * least one plan is available and hides silently otherwise.
 */
/**
 * Price + Buy button for paid programs, injected into the enrol_programs catalogue pages.
 *
 * The catalogue markup belongs to a third-party plugin we deliberately do not edit, so this hooks
 * onto the anchor it already renders: <div class="programbox" data-programid="N">. Free programs
 * (no price row) render nothing at all and keep the plugin's own signup button untouched.
 *
 * @return string HTML/JS to append, or '' when this is not a catalogue page
 */
function local_academy_program_catalogue_pricing() {
    global $PAGE, $USER, $CFG, $DB;

    // Catalogue index (list) and the single-program page. Nothing else.
    $pagetypes = array(
        'enrol-programs-catalogue-index',
        'enrol-programs-catalogue-program',
    );
    if (!in_array($PAGE->pagetype, $pagetypes, true)) {
        return '';
    }
    if (!\local_academy\program_purchase_manager::available()) {
        return '';
    }

    // Prices + offer data for every paid program, so the JS can decorate whatever is on the page.
    $prices = array();
    foreach ($DB->get_records('academy_program_prices', array('enabled' => 1)) as $row) {
        if ((float)$row->price > 0) {
            $offer = \local_academy\discount_manager::offer_summary(
                \local_academy\discount_manager::TYPE_PROGRAM, (int)$row->programid, (float)$row->price);
            $prices[(int)$row->programid] = array(
                'price'    => (float)$row->price,
                'currency' => $row->currency,
                'offer'    => $offer,
            );
        }
    }
    if (!$prices) {
        return ''; // No paid programs — nothing to change anywhere.
    }

    // Which of them this user already owns (owned programs show a note, not a Buy button).
    $owned = array();
    $loggedin = isloggedin() && !isguestuser();
    if ($loggedin && $DB->get_manager()->table_exists('enrol_programs_allocations')) {
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($prices), SQL_PARAMS_NAMED);
        $inparams['userid'] = $USER->id;
        $rows = $DB->get_records_select('enrol_programs_allocations',
            "userid = :userid AND programid $insql", $inparams, '', 'id, programid');
        foreach ($rows as $r) {
            $owned[(int)$r->programid] = true;
        }
    }

    // Anonymous visitors get a "log in to buy" link — there is no web-service token for them.
    $token = '';
    if ($loggedin) {
        try {
            require_once($CFG->dirroot . '/webservice/lib.php');
            require_once($CFG->libdir . '/externallib.php');
            $service = $DB->get_record('external_services',
                array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', IGNORE_MISSING);
            if ($service) {
                $token = external_generate_token_for_current_user($service)->token;
            }
        } catch (\Throwable $e) {
            $token = ''; // Fall back to the login prompt rather than breaking the catalogue.
        }
    }

    $str = array(
        'sess_expired'    => get_string('err_sessionexpired', 'local_academy'),
        'req_failed'      => get_string('err_requestfailed', 'local_academy'),
        'confirm_title'   => get_string('hp_prg_confirm_title', 'local_academy'),
        'confirm_body'    => get_string('hp_prg_confirm_body', 'local_academy'),
        'total'           => get_string('hp_total', 'local_academy'),
        'egp'             => get_string('hp_egp', 'local_academy'),
        'coupon'          => get_string('hp_coupon', 'local_academy'),
        'apply'           => get_string('hp_apply', 'local_academy'),
        'discount'        => get_string('hp_discount', 'local_academy'),
        'secure'          => get_string('hp_secure', 'local_academy'),
        'cancel'          => get_string('hp_cancel', 'local_academy'),
        'proceed'         => get_string('hp_proceed', 'local_academy'),
        'redirecting'     => get_string('hp_prg_redirecting', 'local_academy'),
    );
    $cfg = json_encode(array(
        'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
        'token'    => $token,
        'lang'     => current_language(),
        'loginurl' => (new moodle_url('/login/index.php'))->out(false),
        'prices'   => $prices,
        'owned'    => array_keys($owned),
        'str'      => local_academy_string_map(array(
            'prg_buy', 'prg_price_label', 'prg_owned', 'prg_login_to_buy',
            'ui_currency_egp',
        )),
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $strjson = json_encode($str, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $out = '<style>
@import url("https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap");
.academy-prg-price{margin:.75rem 0;padding:.75rem 1rem;border:1px solid #dee2e6;border-radius:.5rem;background:#f8f9fa}
.academy-prg-price .amount{font-size:1.35rem;font-weight:700;color:#0f6cbf}
.academy-prg-price .amount-old{font-size:1rem;font-weight:600;color:#9aa0a6;text-decoration:line-through;margin-inline-end:.35rem}
.academy-prg-price .label{color:#6c757d;font-size:.82rem}
.academy-prg-price .owned{color:#155724;font-weight:600}
.academy-prg-price .offerbadge{display:inline-flex;align-items:center;gap:.25rem;background:#e8153b;color:#fff;font-weight:800;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem;box-shadow:0 2px 6px rgba(232,21,59,.35);margin-bottom:.3rem}
.academy-prg-price .offerbadge svg{width:13px;height:13px;fill:#fff}
.academy-prg-buy{margin-top:.5rem}
.la-cat-modal-bg{position:fixed;inset:0;background:rgba(28,29,36,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:1rem;opacity:0;transition:opacity .18s ease;font-family:"Cairo","Segoe UI",Tahoma,Arial,sans-serif}
.la-cat-modal-bg.open{display:flex;opacity:1}
.la-cat-modal{background:#fff;border-radius:12px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.28);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .18s ease}
.la-cat-modal-bg.open .la-cat-modal{transform:none}
.la-cat-modal-head{display:flex;align-items:center;gap:.7rem;padding:1.25rem 1.4rem 0}
.la-cat-modal-head svg{width:34px;height:34px;fill:#6c22a6;flex-shrink:0}
.la-cat-modal-head h4{margin:0;font-size:1.2rem;font-weight:800;color:#1c1d1f}
.la-cat-modal-body{padding:.9rem 1.4rem 0;color:#3c3c3c;font-size:.95rem;line-height:1.5}
.la-cat-modal-plan{margin:.9rem 0;padding:.9rem 1rem;background:#f5eefc;border:1px solid #e4d3f5;border-radius:8px}
.la-cat-modal-plan .name{font-weight:700;color:#1c1d1f;margin-bottom:.35rem}
.la-cat-modal-row{display:flex;justify-content:space-between;font-size:.9rem;color:#4b4b4b;margin-top:.2rem}
.la-cat-modal-row b{color:#1c1d1f}
.la-cat-modal-secure{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#6a6f73;margin-top:.2rem}
.la-cat-modal-secure svg{width:14px;height:14px;fill:#1f9d55}
.la-cat-modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:1.2rem 1.4rem 1.3rem}
.la-cat-modal-cancel{background:#fff;border:1px solid #d1d7dc;color:#3c3c3c;font-weight:600;font-size:.92rem;padding:.6rem 1.1rem;border-radius:4px;cursor:pointer}
.la-cat-modal-cancel:hover{background:#f6f7f8}
.la-cat-modal-ok{background:#6c22a6;border:none;color:#fff;font-weight:700;font-size:.92rem;padding:.6rem 1.3rem;border-radius:4px;cursor:pointer}
.la-cat-modal-ok:hover{background:#57187f}
</style>';

    $out .= html_writer::script(<<<JS
(function () {
  var CFG = {$cfg};
  var T = {$strjson};
  var STR = CFG.str || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function money(n){return Number(n||0).toFixed(2);}
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function el(tag, attrs, html) { var e = document.createElement(tag); for (var k in (attrs||{})){e.setAttribute(k,attrs[k]);} if(html!=null){e.innerHTML=html;} return e; }
  function parse(r) {
    return r.text().then(function(t) {
      var j; try{j=JSON.parse(t);}catch(e){throw new Error(T.sess_expired);}
      if(j.status!=='success'){throw new Error(j.error||T.req_failed);} return j.data;
    });
  }
  function apiGet(fn,params){var base={'function':fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}var q=new URLSearchParams(Object.assign(base,params||{}));return fetch(CFG.endpoint+'?'+q.toString()).then(parse);}
  function apiPost(fn,params){var base={'function':fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}var body=new URLSearchParams(Object.assign(base,params||{}));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(parse);}

  function confirmBuy(p) {
    return new Promise(function(resolve) {
      var LOCK='<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1a5 5 0 00-5 5v3H6a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2v-9a2 2 0 00-2-2h-1V6a5 5 0 00-5-5zm3 8H9V6a3 3 0 016 0v3z"/></svg>';
      var CAP='<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>';
      var bg=el('div',{'class':'la-cat-modal-bg'});
      bg.innerHTML=
        '<div class="la-cat-modal" role="dialog" aria-modal="true">'+
          '<div class="la-cat-modal-head">'+CAP+'<h4>'+esc(T.confirm_title)+'</h4></div>'+
          '<div class="la-cat-modal-body">'+
            '<p>'+esc(T.confirm_body)+'</p>'+
            '<div class="la-cat-modal-plan">'+
              '<div class="name">'+esc(p.name)+'</div>'+
              '<div class="la-cat-modal-row"><span>'+esc(T.total)+'</span><b class="la-cat-orig">'+esc(money(p.price))+' '+esc(T.egp)+'</b></div>'+
              '<div class="la-cat-modal-row"><span>'+esc(T.coupon)+'</span><span style="display:flex;gap:.3rem"><input type="text" class="la-cat-coupon" style="max-width:110px;border:1px solid #d1d7dc;border-radius:4px;padding:.15rem .4rem"><button type="button" class="la-cat-apply" style="border:1px solid #6c22a6;background:#fff;color:#6c22a6;border-radius:4px;padding:.15rem .6rem;cursor:pointer">'+esc(T.apply)+'</button></span></div>'+
              '<div class="la-cat-coupon-msg" style="color:#c0392b;font-size:.8rem;margin:.2rem 0"></div>'+
              '<div class="la-cat-modal-row"><span>'+esc(T.discount)+'</span><b class="la-cat-disc" style="color:#1f9d55">0.00 '+esc(T.egp)+'</b></div>'+
              '<div class="la-cat-modal-row" style="border-top:1px solid #e4d3f5;margin-top:.35rem;padding-top:.45rem"><span style="font-weight:700">'+esc(T.total)+'</span><b class="la-cat-final" style="color:#6c22a6;font-size:1.2rem">'+esc(money(p.price))+' '+esc(T.egp)+'</b></div>'+
            '</div>'+
            '<div class="la-cat-modal-secure">'+LOCK+'<span>'+esc(T.secure)+'</span></div>'+
          '</div>'+
          '<div class="la-cat-modal-foot">'+
            '<button type="button" class="la-cat-modal-cancel">'+esc(T.cancel)+'</button>'+
            '<button type="button" class="la-cat-modal-ok">'+esc(T.proceed)+'</button>'+
          '</div>'+
        '</div>';
      document.body.appendChild(bg);
      void bg.offsetWidth;
      bg.classList.add('open');

      function close(result){bg.classList.remove('open');document.removeEventListener('keydown',onKey);setTimeout(function(){if(bg.parentNode){bg.parentNode.removeChild(bg);}},180);resolve(result);}
      function onKey(e){if(e.key==='Escape'){close(null);}}
      document.addEventListener('keydown',onKey);

      var couponInput=bg.querySelector('.la-cat-coupon');
      var discEl=bg.querySelector('.la-cat-disc');
      var finalEl=bg.querySelector('.la-cat-final');
      var origEl=bg.querySelector('.la-cat-orig');
      var cmsg=bg.querySelector('.la-cat-coupon-msg');
      function preview(code){
        apiGet('preview_discount',{item_type:'program',item_id:p.id,coupon_code:code||''})
          .then(function(d){
            discEl.textContent=(d.discount>0?'-'+money(d.discount):'0.00')+' '+T.egp;
            finalEl.textContent=money(d.final)+' '+T.egp;
            origEl.style.textDecoration=d.discount>0?'line-through':'none';
            cmsg.textContent=d.coupon_error?d.coupon_error:'';
          }).catch(function(){});
      }
      bg.querySelector('.la-cat-apply').onclick=function(){preview(couponInput.value.trim());};
      couponInput.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();preview(couponInput.value.trim());}});
      var catDeb;
      couponInput.addEventListener('input',function(){clearTimeout(catDeb);catDeb=setTimeout(function(){preview(couponInput.value.trim());},450);});
      preview('');

      bg.querySelector('.la-cat-modal-cancel').onclick=function(){close(null);};
      bg.querySelector('.la-cat-modal-ok').onclick=function(){close(couponInput.value.trim());};
      bg.onclick=function(e){if(e.target===bg){close(null);}};
    });
  }

  var owned = {};
  (CFG.owned || []).forEach(function(id){ owned[id] = true; });

  Array.prototype.forEach.call(document.querySelectorAll('.programbox[data-programid]'), function (box) {
    var id = parseInt(box.getAttribute('data-programid'), 10);
    var info = CFG.prices[id];
    if (!info) { return; }
    if (box.querySelector('.academy-prg-price')) { return; }

    var panel = document.createElement('div');
    panel.className = 'academy-prg-price';
    var TAG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21.41 11.58l-9-9A2 2 0 0011 2H4a2 2 0 00-2 2v7a2 2 0 00.59 1.42l9 9a2 2 0 002.82 0l7-7a2 2 0 000-2.84zM6.5 8A1.5 1.5 0 118 6.5 1.5 1.5 0 016.5 8z"/></svg>';
    if (info.offer) {
      panel.innerHTML = '<div class="offerbadge">' + TAG + esc(info.offer.label) + '</div>' +
        '<div class="label">' + esc(str('prg_price_label')) + '</div>' +
        '<span class="amount-old">' + money(info.offer.original) + '</span>' +
        '<span class="amount">' + money(info.offer.final) + ' ' + esc(info.currency || str('ui_currency_egp')) + '</span>';
    } else {
      panel.innerHTML = '<div class="label">' + esc(str('prg_price_label')) + '</div>' +
        '<div class="amount">' + money(info.price) + ' ' + esc(info.currency || str('ui_currency_egp')) + '</div>';
    }

    if (owned[id]) {
      var note = document.createElement('div');
      note.className = 'owned';
      note.textContent = str('prg_owned');
      panel.appendChild(note);
    } else if (!CFG.token) {
      var login = document.createElement('a');
      login.className = 'btn btn-primary academy-prg-buy';
      login.href = CFG.loginurl;
      login.textContent = str('prg_login_to_buy');
      panel.appendChild(login);
    } else {
      var pname = box.querySelector('.program-name,.programname,h3,h4');
      var btn = document.createElement('button');
      btn.className = 'btn btn-primary academy-prg-buy';
      btn.type = 'button';
      btn.textContent = str('prg_buy');
      btn.onclick = function () {
        confirmBuy({id:id, name: pname ? pname.textContent.trim() : '#'+id, price: info.price}).then(function(code){
          if (code===null) { return; }
          var orig=btn.textContent;
          btn.disabled=true; btn.textContent=T.redirecting;
          apiPost('create_program_checkout',{programid:id,coupon_code:code})
            .then(function(d){window.location.href=d.checkout_url;})
            .catch(function(e){
              btn.disabled=false; btn.textContent=orig;
              var err=panel.querySelector('.text-danger');
              if(!err){err=document.createElement('div');err.className='text-danger mt-2';panel.appendChild(err);}
              err.textContent=e.message;
            });
        });
      };
      panel.appendChild(btn);
    }

    box.appendChild(panel);
  });
})();
JS
    );

    return $out;
}

function local_academy_hero_section() {
    $loginurl = new moodle_url('/login/index.php');
    $signupurl = new moodle_url('/login/signup.php');

    return <<<HTML
<div id="xt-hero" class="xt-hero">
    <div class="xt-hero__badge">منصة تعليم التجارة الدولية الأولى عربياً</div>
    <h1 class="xt-hero__title">
        <span class="xt-hero__gold">156 برنامجًا تدريبيًا</span><br>
        <span>دبلومات وشهادات</span> <span class="xt-hero__teal">احترافية</span>
    </h1>
    <p class="xt-hero__sub">منصة X-Trade تجمع أحدث الدبلومات في سلاسل التوريد، المشتريات، اللوجستيات، المستودعات، الاستيراد والتصدير، الشحن، النقل، والملاحة – مصنفة حسب التخصص والمستوى.</p>
    <div class="xt-hero__stats">
        <div class="xt-hero__stat"><span class="xt-hero__stat-num">156</span><span class="xt-hero__stat-label">دورة ودبلوم</span></div>
        <div class="xt-hero__stat"><span class="xt-hero__stat-num">9</span><span class="xt-hero__stat-label">تخصص رئيسي</span></div>
        <div class="xt-hero__stat"><span class="xt-hero__stat-num">4</span><span class="xt-hero__stat-label">مستويات تعليمية</span></div>
    </div>
    <div class="xt-hero__btns">
        <a href="#la-subs" class="xt-hero__btn-primary">استكشف التخصصات</a>
        <a href="{$signupurl}" class="xt-hero__btn-outline">خطط مرنة</a>
    </div>
</div>
HTML;
}

/**
 * Fetch all brand and section colour variables configured in the edumy theme settings page
 * (Appearance → Edumy settings → Color). Returned with exact Moodle setting names.
 * Empty/unset values are omitted so the frontend falls back to its built-in palette for those keys.
 *
 * @return array<string,string> e.g. ['color_primary' => '#0E2647', 'color_header_style_2_top' => '#000000', ...]
 */
function local_academy_get_theme_tokens() {
    $settings = [
        // Gradients
        'color_gradient_start',
        'color_gradient_end',

        // Main Colors
        'color_primary',
        'color_primary_alternate',
        'color_secondary',
        'color_tertiary',
        'color_accent',
        'color_accent_2',
        'color_accent_3',
        'color_accent_4',
        'color_parallax',

        // Header Styles
        'color_header_style_2_top',
        'color_header_style_2_bottom',
        'color_header_style_3_top',
        'color_header_style_4_top',
        'color_header_style_5',
        'color_header_style_6_top',

        // Footer Styles
        'color_footer_style_1_top',
        'color_footer_style_1_bottom',
        'color_footer_style_2_top',
        'color_footer_style_2_bottom',
        'color_footer_style_3_top',
        'color_footer_style_3_middle',
        'color_footer_style_3_bottom',
        'color_footer_style_5_top',
        'color_footer_style_5_bottom',
        'color_footer_style_6_all',
        'color_footer_style_7_top',
        'color_footer_style_7_bottom',
    ];

    $colors = [];
    foreach ($settings as $setting) {
        $val = get_config('theme_edumy', $setting);
        // Keep only valid #rgb / #rrggbb values; skip unset (false) or blanks.
        if (is_string($val) && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($val))) {
            $colors[$setting] = trim($val);
        }
    }

    return $colors;
}

/**
 * Live "specialties" data for the front-page curriculum block: every visible top-level category
 * that has courses, with its subcategories as levels and the courses inside each. This is the
 * single source of truth shared by {@see local_academy_before_footer()} (which builds the cards
 * client-side via JS on the Moodle front page) and by the headless blocks API
 * ({@see local_academy_get_frontpage_blocks()}, which returns it as structured data for the Next.js
 * frontend to render natively).
 *
 * @return array<int, array{id:int,name:string,url:string,count:int,levels:array}>
 */
function local_academy_get_frontpage_specialties() {
    global $DB;

    $cats = $DB->get_records('course_categories', ['parent' => 0, 'visible' => 1], 'sortorder ASC', 'id, name');
    $specialties = [];
    foreach ($cats as $cat) {
        $levels = [];
        $totalcourses = 0;

        // Courses directly in the top-level category (no sublevel).
        $direct = $DB->get_records_select('course',
            'category = :catid AND visible = 1 AND id != :siteid',
            ['catid' => $cat->id, 'siteid' => SITEID],
            'sortorder ASC', 'id, fullname, summary');
        if ($direct) {
            $list = [];
            foreach ($direct as $c) {
                $list[] = [
                    'id'   => (int) $c->id,
                    'name' => format_string($c->fullname),
                    'desc' => shorten_text(strip_tags(format_text($c->summary)), 120),
                    'url'  => (string) new moodle_url('/course/view.php', ['id' => $c->id]),
                ];
            }
            $levels[] = ['name' => '', 'courses' => $list];
            $totalcourses += count($list);
        }

        // Subcategories as levels.
        $subcats = $DB->get_records('course_categories', ['parent' => $cat->id, 'visible' => 1], 'sortorder ASC', 'id, name');
        foreach ($subcats as $sub) {
            $courses = $DB->get_records_select('course',
                'category = :catid AND visible = 1 AND id != :siteid',
                ['catid' => $sub->id, 'siteid' => SITEID],
                'sortorder ASC', 'id, fullname, summary');
            if (!$courses) {
                continue;
            }
            $list = [];
            foreach ($courses as $c) {
                $list[] = [
                    'id'   => (int) $c->id,
                    'name' => format_string($c->fullname),
                    'desc' => shorten_text(strip_tags(format_text($c->summary)), 120),
                    'url'  => (string) new moodle_url('/course/view.php', ['id' => $c->id]),
                ];
            }
            $levels[] = ['name' => format_string($sub->name), 'courses' => $list];
            $totalcourses += count($list);
        }

        if ($totalcourses) {
            $specialties[] = [
                'id'     => (int) $cat->id,
                'name'   => format_string($cat->name),
                'url'    => (string) new moodle_url('/course/index.php', ['categoryid' => $cat->id]),
                'count'  => $totalcourses,
                'levels' => $levels,
            ];
        }
    }

    return $specialties;
}

/**
 * Live front-page stats and links used to fill placeholders in the front-page custom-HTML blocks.
 *
 * Any element with data-xt-stat="courses|categories|programs" gets its text replaced with the
 * matching count; any element with data-xt-link="course_index|packages" gets its href replaced.
 * This is the single source of truth shared by {@see local_academy_before_footer()} (which applies
 * it client-side via JS on the Moodle front page) and by the headless front-page blocks API
 * ({@see local_academy_get_frontpage_blocks()}, which applies it server-side) so the Next.js
 * frontend renders exactly the same numbers as the Moodle site.
 *
 * @return array{0: array<string,int>, 1: array<string,string>} [$stats, $links]
 */
function local_academy_frontpage_stats_links() {
    global $DB;

    $coursecount   = $DB->count_records_select('course_categories', 'parent != 0 AND visible = 1');
    $topcategories = $DB->count_records_select('course_categories', 'parent = 0 AND visible = 1');
    $programcount  = 0;
    if ($DB->get_manager()->table_exists('enrol_programs_programs')) {
        $programcount = $DB->count_records('enrol_programs_programs', ['archived' => 0]);
    }

    $stats = [
        'courses'    => (int) $coursecount,
        'categories' => (int) $topcategories,
        'programs'   => (int) $programcount,
    ];
    $links = [
        'course_index' => (string) new moodle_url('/course/index.php'),
        'packages'     => '#la-pkgs',
    ];

    return [$stats, $links];
}

/**
 * Return the visible custom-HTML blocks on the site front page (pagetype `site-index`),
 * ordered exactly as Moodle renders them (region, then weight), with:
 *   - multilang/pluginfile filtering applied (same as the block's own get_content()), and
 *   - the data-xt-stat / data-xt-link placeholders substituted server-side.
 *
 * The stored block bodies are self-contained HTML with inline styles, so the returned `html`
 * renders identically in a headless frontend with no Moodle theme CSS required.
 *
 * @param string|null $region Optional region filter (e.g. 'fullwidth-top'); null returns all.
 * @return array<int, array{id:int,title:string,region:string,weight:int,html:string}>
 */
function local_academy_get_frontpage_blocks($region = null) {
    global $DB;

    // Front page (site) course context — this is where front-page blocks are parented.
    $frontcontext = context_course::instance(SITEID);

    $params = [
        'blockname'       => 'cocoon_custom_html',
        'pagetypepattern' => 'site-index',
        'parentcontextid' => $frontcontext->id,
    ];
    $instances = $DB->get_records('block_instances', $params);

    [$stats, $links] = local_academy_frontpage_stats_links();

    $blocks = [];
    foreach ($instances as $bi) {
        // Respect an explicit "hidden" flag from block_positions on the front page, if present.
        $pos = $DB->get_record('block_positions', [
            'blockinstanceid' => $bi->id,
            'contextid'       => $frontcontext->id,
        ]);
        if ($pos && (int) $pos->visible === 0) {
            continue;
        }

        $blockregion = ($pos && $pos->region !== null && $pos->region !== '')
            ? $pos->region : $bi->defaultregion;
        $weight = ($pos && $pos->weight !== null) ? (int) $pos->weight : (int) $bi->defaultweight;

        if ($region !== null && $blockregion !== $region) {
            continue;
        }

        $config = $bi->configdata ? unserialize(base64_decode($bi->configdata)) : null;
        $bodytext = '';
        if ($config && !empty($config->body) && is_array($config->body) && isset($config->body['text'])) {
            $bodytext = $config->body['text'];
        }
        if (trim($bodytext) === '') {
            continue; // Skip empty blocks (e.g. spacer blocks with no real content).
        }

        // Render the body the same way block_cocoon_custom_html::get_content() does: filter
        // multilang, rewrite @@PLUGINFILE@@ against the block's own context, keep raw HTML.
        $blockcontext = context_block::instance($bi->id);
        $html = format_text($bodytext, FORMAT_HTML, [
            'filter'  => true,
            'noclean' => true,
            'context' => $blockcontext,
        ]);
        $html = local_academy_apply_frontpage_dynamics($html, $stats, $links);

        $title = (isset($config->title) && $config->title !== '') ? format_string($config->title) : '';

        $entry = [
            'id'     => (int) $bi->id,
            'title'  => $title,
            'region' => (string) $blockregion,
            'weight' => $weight,
            'kind'   => 'html',
            'html'   => $html,
        ];

        // Curriculum block: its body is a static wrapper + header with an empty
        // <div id="xt-specialties"> placeholder that Moodle fills with course cards client-side.
        // Return the header chrome + live specialties DATA so the frontend renders the cards
        // natively (with working filtering) instead of receiving an empty container.
        if (strpos($html, 'xt-specialties') !== false) {
            $split = local_academy_split_specialties_block($html);
            if ($split !== null) {
                $entry['kind']         = 'specialties';
                $entry['wrapperStyle'] = $split['wrapperstyle'];
                $entry['wrapperDir']   = $split['wrapperdir'];
                $entry['headerHtml']   = $split['headerhtml'];
                $entry['specialties']  = local_academy_get_frontpage_specialties();
                // Drop the (empty-container) html for this kind — the frontend uses the fields above.
                unset($entry['html']);
            }
        }

        $blocks[] = $entry;
    }

    // Order the way Moodle stacks blocks: by region, then ascending weight.
    usort($blocks, function ($a, $b) {
        return [$a['region'], $a['weight']] <=> [$b['region'], $b['weight']];
    });

    return $blocks;
}

/**
 * Replace data-xt-stat text and data-xt-link hrefs in a front-page HTML fragment, mirroring the
 * client-side substitution in {@see local_academy_before_footer()} but done server-side so the
 * headless frontend receives final, filled-in HTML.
 *
 * @param string $html  HTML fragment (may contain UTF-8 / Arabic text).
 * @param array<string,int> $stats
 * @param array<string,string> $links
 * @return string
 */
function local_academy_apply_frontpage_dynamics($html, array $stats, array $links) {
    if (trim($html) === '' || (strpos($html, 'data-xt-') === false)) {
        return $html;
    }

    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    // Force UTF-8 and load as a fragment (no implied <html>/<body>, no doctype).
    $doc->loadHTML(
        '<?xml encoding="UTF-8"?><div data-ea-root="1">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//*[@data-xt-stat]') as $el) {
        $k = $el->getAttribute('data-xt-stat');
        if (array_key_exists($k, $stats)) {
            $el->textContent = (string) $stats[$k];
        }
    }
    foreach ($xpath->query('//*[@data-xt-link]') as $el) {
        $k = $el->getAttribute('data-xt-link');
        if (array_key_exists($k, $links) && $links[$k] !== '') {
            $el->setAttribute('href', $links[$k]);
        }
    }

    // Serialise the children of our wrapper back to an HTML fragment.
    $wrapper = $xpath->query('//div[@data-ea-root="1"]')->item(0);
    if (!$wrapper) {
        return $html;
    }
    $inner = '';
    foreach ($wrapper->childNodes as $child) {
        $inner .= $doc->saveHTML($child);
    }
    return $inner;
}

/**
 * Split a curriculum block body around its <div id="xt-specialties"> placeholder so the frontend
 * can render the admin-authored wrapper + header, then inject natively-rendered course cards where
 * the placeholder sits.
 *
 * @param string $html Rendered block HTML containing an element with id="xt-specialties".
 * @return array{wrapperstyle:string,wrapperdir:string,headerhtml:string}|null
 *         null when the placeholder (or its wrapper) can't be located.
 */
function local_academy_split_specialties_block($html) {
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"?><div data-ea-root="1">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xpath = new DOMXPath($doc);
    $placeholder = $xpath->query('//*[@id="xt-specialties"]')->item(0);
    if (!$placeholder) {
        return null;
    }
    $wrapper = $placeholder->parentNode;
    if (!($wrapper instanceof DOMElement)) {
        return null;
    }

    // Everything inside the wrapper that comes BEFORE the placeholder is the header chrome.
    $headerhtml = '';
    foreach ($wrapper->childNodes as $child) {
        if ($child->isSameNode($placeholder)) {
            break;
        }
        $headerhtml .= $doc->saveHTML($child);
    }

    return [
        'wrapperstyle' => $wrapper->getAttribute('style'),
        'wrapperdir'   => $wrapper->getAttribute('dir'),
        'headerhtml'   => $headerhtml,
    ];
}

function local_academy_before_footer() {
    global $PAGE, $USER, $COURSE, $DB, $CFG;
    $output = '';

    // 0. Dynamic stats for front-page HTML blocks: any element with data-xt-stat="courses|categories|programs"
    // gets its textContent replaced with the live count from the database.
    if (!CLI_SCRIPT && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT) && !(defined('WS_SERVER') && WS_SERVER)) {
        if ($PAGE->pagetype === 'site-index') {
            [$statsarr, $linksarr] = local_academy_frontpage_stats_links();

            $stats = json_encode($statsarr);
            $links = json_encode($linksarr);
            $PAGE->requires->js_amd_inline("
                require([], function() {
                    var S = {$stats};
                    var L = {$links};
                    document.querySelectorAll('[data-xt-stat]').forEach(function(el) {
                        var k = el.getAttribute('data-xt-stat');
                        if (S[k] !== undefined) { el.textContent = S[k]; }
                    });
                    document.querySelectorAll('[data-xt-link]').forEach(function(el) {
                        var k = el.getAttribute('data-xt-link');
                        if (L[k]) { el.setAttribute('href', L[k]); }
                    });
                });
            ");

            // Specialties data for the curriculum block (top-level categories → levels → courses).
            $specialties = local_academy_get_frontpage_specialties();
            $specjson = json_encode($specialties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $PAGE->requires->js_amd_inline("
                require([], function() {
                    var SPECS = {$specjson};
                    var box = document.getElementById('xt-specialties');
                    if (!box || !SPECS.length) return;

                    function esc(v) {
                        return String(v||'').replace(/[&<>\"]/g, function(c) {
                            return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c];
                        });
                    }

                    var FILTER_BASE = 'background-color:rgba(10,22,40,0.7);border:1px solid rgba(201,146,42,0.2);border-radius:40px;padding:8px 18px;font-size:13px;font-weight:600;color:#8A9AB5;cursor:pointer;display:inline-block;transition:all .18s;font-family:inherit';
                    var FILTER_ACTIVE = 'background:linear-gradient(135deg,#C9922A,#E8B84B);color:#0A1628;border-radius:40px;padding:8px 18px;font-size:13px;font-weight:bold;cursor:pointer;display:inline-block;border:none;font-family:inherit';

                    // Filter bar.
                    var h = '<div style=\"display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-items:center;margin-bottom:40px;padding-bottom:15px;border-bottom:1px solid rgba(201,146,42,0.2)\">';
                    h += '<span class=\"xf\" data-cat=\"all\" style=\"' + FILTER_ACTIVE + '\">كل التخصصات</span>';
                    SPECS.forEach(function(s) {
                        h += '<span class=\"xf\" data-cat=\"' + s.id + '\" style=\"' + FILTER_BASE + '\">' + esc(s.name) + '</span>';
                    });
                    h += '</div>';

                    // Specialty sections.
                    SPECS.forEach(function(s) {
                        h += '<div class=\"xs\" data-spec=\"' + s.id + '\" style=\"margin-bottom:40px\">';
                        h += '<h3 style=\"font-size:24px;font-weight:800;color:#E8B84B;margin:0 0 15px;border-right:4px solid #C9922A;padding-right:12px\">' + esc(s.name) + '</h3>';

                        s.levels.forEach(function(lv) {
                            h += '<div style=\"background-color:rgba(255,255,255,0.02);border-radius:16px;padding:20px;border:1px solid rgba(201,146,42,0.1);margin-top:15px\">';
                            if (lv.name) {
                                h += '<div style=\"display:inline-flex;align-items:center;gap:8px;font-weight:bold;font-size:15px;margin-bottom:20px;background-color:rgba(201,146,42,0.15);padding:5px 15px;border-radius:40px;color:#E8B84B\">' + esc(lv.name) + ' (' + lv.courses.length + ' دورات)</div>';
                            }
                            h += '<div style=\"display:flex;flex-wrap:wrap;gap:20px\">';
                            lv.courses.forEach(function(c) {
                                h += '<div style=\"flex:1 1 280px;min-width:260px;background:linear-gradient(145deg,#0D2149,#0A1628);border:1px solid rgba(201,146,42,0.2);border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;transition:border-color .18s,transform .15s\">';
                                h += '<div>';
                                h += '<h4 style=\"font-size:16px;font-weight:bold;margin:0 0 10px;color:#FFFFFF;line-height:1.4\">' + esc(c.name) + '</h4>';
                                if (c.desc) {
                                    h += '<p style=\"font-size:13px;color:#8A9AB5;line-height:1.6;margin:0 0 15px\">' + esc(c.desc) + '</p>';
                                }
                                h += '</div>';
                                h += '<div>';
                                h += '<div style=\"display:flex;gap:10px;font-size:11px;color:#00A99D;margin-bottom:15px;flex-wrap:wrap\"><span>📘 دبلوم مهني</span><span>🎓 شهادة معتمدة</span><span>⏱️ وصول فوري</span></div>';
                                h += '<a href=\"' + esc(c.url) + '\" style=\"display:block;text-align:center;background-color:transparent;border:1px solid #C9922A;color:#E8B84B;padding:8px 15px;border-radius:6px;font-weight:bold;font-size:13px;cursor:pointer;width:100%;text-decoration:none;box-sizing:border-box\">📋 تفاصيل</a>';
                                h += '</div></div>';
                            });
                            h += '</div></div>';
                        });

                        h += '<a href=\"' + esc(s.url) + '\" style=\"display:inline-block;margin-top:15px;color:#E8B84B;font-weight:700;font-size:14px;text-decoration:none\">عرض كل دورات ' + esc(s.name) + ' ←</a>';
                        h += '</div>';
                    });

                    box.innerHTML = h;

                    // Filter logic.
                    var filters = box.querySelectorAll('.xf');
                    filters.forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            filters.forEach(function(b) { b.style.cssText = FILTER_BASE; });
                            btn.style.cssText = FILTER_ACTIVE;
                            var cat = btn.getAttribute('data-cat');
                            box.querySelectorAll('.xs').forEach(function(sec) {
                                sec.style.display = (cat === 'all' || sec.getAttribute('data-spec') === cat) ? '' : 'none';
                            });
                        });
                    });
                });
            ");
        }
    }

    // 1. Front page "Available subscriptions" cards, followed by "Available packages" cards.
    // Shown to everyone (including guests/visitors not logged in) so they can browse what's on
    // offer; the cards themselves send guests to log in instead of straight to checkout. Real
    // admins/managers already manage these from their own dashboards, not from here.
    if (!CLI_SCRIPT && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT) && !(defined('WS_SERVER') && WS_SERVER)) {
        if ($PAGE->pagetype === 'site-index' && !local_academy_is_platform_manager()) {
            // Never let a card section take the front page down (e.g. a pending DB upgrade where the
            // new subscription columns/tables do not exist yet) — render what we can, skip the rest.
            try {
                $output .= local_academy_available_subscriptions_section();
            } catch (\Throwable $e) {
                debugging('academy subscriptions section failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            try {
                $output .= local_academy_available_packages_section();
            } catch (\Throwable $e) {
                debugging('academy packages section failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            // Programs (enrol_programs), directly after the packages cards: what is on offer, then
            // what this user already has. Both no-op when the programs plugin is absent.
            try {
                $output .= local_academy_available_programs_section();
                $output .= local_academy_my_programs_section();
            } catch (\Throwable $e) {
                debugging('academy programs sections failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    // 1b. Paid programs: price + Buy button on the enrol_programs catalogue. Injected from here so
    // the third-party plugin's own files stay untouched and survive its updates.
    if (!CLI_SCRIPT && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT) && !(defined('WS_SERVER') && WS_SERVER)) {
        try {
            $output .= local_academy_program_catalogue_pricing();
        } catch (\Throwable $e) {
            debugging('academy program pricing injection failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // 1c. Program certificates. Eligibility info only — nothing is ever issued here.
    //   - my-program (owner): live progress towards each certificate.
    //   - catalogue-program (shopper): the same requirements as a preview, so a student can see what
    //     the program's certificates demand BEFORE buying it. Without this the certificates are
    //     invisible until after purchase, which is exactly when the information stops being useful.
    if (!CLI_SCRIPT && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT) && !(defined('WS_SERVER') && WS_SERVER)
            && in_array($PAGE->pagetype, array('enrol-programs-my-program', 'enrol-programs-catalogue-program'), true)) {
        try {
            $output .= local_academy_program_certificates_section(
                $PAGE->pagetype === 'enrol-programs-catalogue-program');
        } catch (\Throwable $e) {
            debugging('academy program certificates section failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // 2. Only for real, interactive, logged-in users — skip guests, AJAX/WS/CLI requests.
    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
        return $output;
    }
    if (!isloggedin() || isguestuser()) {
        return $output;
    }
    if (!\local_academy\realtime::enabled()) {
        return $output;
    }

    $cfg = array(
        'url'   => \local_academy\realtime::public_url(),
        'path'  => \local_academy\realtime::socket_path(),
        'token' => \local_academy\realtime::mint_token((int) $USER->id),
    );
    $cfgjson = json_encode($cfg, JSON_UNESCAPED_SLASHES);

    $js = <<<JS
require([], function() {
    var CFG = {$cfgjson};
    if (!CFG.url || !CFG.token) { return; }
    var container = document.getElementById('nav-notification-popover-container');
    if (!container) { return; }
    var userid = parseInt(container.getAttribute('data-userid'), 10) || 0;
    if (!userid) { return; }

    // Short chime via the Web Audio API (no asset file). Browsers block audio until the user has
    // interacted with the page, so the context is lazily created/resumed on first gesture.
    var audioCtx = null;
    function ensureAudio() {
        try {
            if (!audioCtx) {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (AC) { audioCtx = new AC(); }
            }
            if (audioCtx && audioCtx.state === 'suspended') { audioCtx.resume(); }
        } catch (e) { audioCtx = null; }
    }
    ['click', 'keydown', 'touchstart'].forEach(function(ev) {
        document.addEventListener(ev, ensureAudio, {once: true, passive: true});
    });
    function chime() {
        try {
            ensureAudio();
            if (!audioCtx) { return; }
            var o = audioCtx.createOscillator();
            var g = audioCtx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(880, audioCtx.currentTime);
            o.frequency.setValueAtTime(1175, audioCtx.currentTime + 0.12);
            g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.2, audioCtx.currentTime + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.32);
            o.connect(g); g.connect(audioCtx.destination);
            o.start();
            o.stop(audioCtx.currentTime + 0.34);
        } catch (e) { /* ignore */ }
    }

    // On each pushed notification: chime, then refresh (debounced so a burst reloads once).
    var reloadTimer = null;
    function onPush() {
        chime();
        if (reloadTimer) { clearTimeout(reloadTimer); }
        reloadTimer = setTimeout(function() { window.location.reload(); }, 1000);
    }

    // Load the socket.io client. It is a UMD bundle; on a RequireJS page (Moodle) it would register
    // as an anonymous AMD module and never set window.io — so hide AMD across the load, then restore.
    var src = CFG.url.replace(/\\/$/, '') + CFG.path + '/socket.io.js';
    var savedDefine = window.define;
    try { window.define = undefined; } catch (e) {}
    function restore() { try { window.define = savedDefine; } catch (e) {} }

    var s = document.createElement('script');
    s.src = src;
    s.onload = function() {
        restore();
        if (!window.io) {
            if (window.console) { console.warn('[academy-notify] socket.io client did not initialise'); }
            return;
        }
        var socket = window.io(CFG.url, {
            path: CFG.path,
            transports: ['websocket'],
            auth: { token: CFG.token },
            reconnection: true
        });
        socket.on('notification', onPush);
        socket.on('connect_error', function(e) {
            if (window.console) { console.warn('[academy-notify] socket connect_error: ' + (e && e.message)); }
        });
    };
    s.onerror = function() {
        restore();
        if (window.console) { console.warn('[academy-notify] failed to load socket.io client from ' + src); }
    };
    document.head.appendChild(s);
});
JS;

    $PAGE->requires->js_amd_inline($js);
    return $output;
}

/**
 * Build a { key => localised string } map for the given local_academy string keys, for injection
 * into the browser as window.ACADEMY_STR. Placeholder strings (containing {$a...}) are returned
 * verbatim so the JS side can interpolate them (see the strf() helper in the page scripts).
 *
 * @param array $keys list of string identifiers in the local_academy component
 * @return array
 */
function local_academy_string_map(array $keys) {
    $map = array();
    foreach ($keys as $key) {
        $map[$key] = get_string($key, 'local_academy');
    }
    return $map;
}

/**
 * Search real user accounts by name or email, for the admin "pick a user" widgets
 * (assign_package, reports, withdrawals) that replaced raw numeric-id inputs.
 *
 * Read-only. Callers must already have gated on an admin capability (the api.php dispatcher does this
 * via $capmap before invoking search_users). Never returns deleted/guest/site accounts.
 *
 * @param string $query  free-text fragment matched against full name and email (min 2 chars)
 * @param string $role   'teacher' to restrict to accounts holding a teacher role; anything else = any user
 * @param int    $limit  maximum rows to return (clamped to 1..50)
 * @return array<int, array{id:int, fullname:string, email:string}>
 */
function local_academy_search_users($query, $role = 'any', $limit = 20) {
    global $DB, $CFG;

    $query = trim((string)$query);
    if (\core_text::strlen($query) < 2) {
        return array();
    }
    $limit = max(1, min(50, (int)$limit));

    $namefields = implode(', ', array_map(function ($f) { return 'u.' . $f; }, \core_user\fields::get_name_fields()));
    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

    $where  = array('u.deleted = 0', 'u.suspended = 0', 'u.confirmed = 1', 'u.id <> :guestid');
    $params = array('guestid' => (int)$CFG->siteguest);

    // Free-text match on the concatenated full name or the email.
    $like = '%' . $DB->sql_like_escape($query) . '%';
    $where[] = '(' . $DB->sql_like($fullname, ':q1', false) . ' OR ' . $DB->sql_like('u.email', ':q2', false) . ')';
    $params['q1'] = $like;
    $params['q2'] = $like;

    if ($role === 'teacher') {
        $where[] = "EXISTS (SELECT 1 FROM {role_assignments} ra
                              JOIN {role} r ON r.id = ra.roleid
                                           AND r.archetype IN ('teacher', 'editingteacher')
                             WHERE ra.userid = u.id)";
    }

    $sql = "SELECT u.id, u.email, $namefields
              FROM {user} u
             WHERE " . implode(' AND ', $where) . "
          ORDER BY u.firstname, u.lastname";

    $records = $DB->get_records_sql($sql, $params, 0, $limit);
    $out = array();
    foreach ($records as $u) {
        $out[] = array(
            'id'       => (int)$u->id,
            'fullname' => fullname($u),
            'email'    => $u->email,
        );
    }
    return $out;
}
