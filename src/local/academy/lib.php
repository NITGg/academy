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
    $css = <<<CSS
.la-subs{max-width:1280px;margin:3rem auto;padding:0 1rem}
.la-subs-head{margin-bottom:1.5rem}
.la-subs-title{font-size:1.7rem;font-weight:800;color:#1c1d1f;margin:0 0 .3rem}
.la-subs-sub{color:#6a6f73;margin:0;font-size:1.02rem}
.la-subs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;align-items:stretch}
.la-subs-card{display:flex;flex-direction:column;height:100%;background:#fff;border:1px solid #e5e7eb;border-radius:1rem;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:transform .18s ease,box-shadow .18s ease}
.la-subs-card:hover{transform:translateY(-6px);box-shadow:0 16px 32px rgba(0,0,0,.16)}
.la-subs-banner{position:relative;height:190px;flex-shrink:0;display:flex;flex-direction:column;justify-content:space-between;padding:1rem 1.1rem;color:#fff;overflow:hidden}
.la-subs-banner::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 15%,rgba(255,255,255,.22),transparent 55%)}
.la-subs-banner svg{width:52px;height:52px;opacity:.95;fill:#fff;position:relative;z-index:1}
.la-subs-badges{align-self:flex-start;position:relative;z-index:1;display:flex;gap:.4rem;flex-wrap:wrap}
.la-subs-daysbadge{background:rgba(255,255,255,.95);color:#1c1d1f;font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-subs-body{padding:1.25rem 1.25rem 1.5rem;display:flex;flex-direction:column;flex:1}
.la-subs-name{font-weight:700;font-size:1.2rem;color:#1c1d1f;margin:0 0 .5rem;line-height:1.35;min-height:2.7em}
.la-subs-desc{color:#6a6f73;font-size:.92rem;margin:0 0 .9rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:4.1em}
.la-subs-card--active{border-color:#a435f0;box-shadow:0 0 0 2px rgba(164,53,240,.28)}
.la-subs-activebadge{background:#1f9d55;color:#fff;font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-subs-dates{font-size:.86rem;color:#3c3c3c;margin-bottom:1rem;padding-top:.9rem;border-top:1px solid #f1f1f1}
.la-subs-dates-row{display:flex;justify-content:space-between;margin-top:.3rem}
.la-subs-dates-row b{color:#1c1d1f}
.la-subs-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding-top:.5rem}
.la-subs-price{font-size:1.5rem;font-weight:800;color:#1c1d1f}
.la-subs-price small{font-size:.8rem;font-weight:600;color:#6a6f73}
.la-subs-btn{background:#a435f0;border:none;color:#fff;font-weight:700;font-size:.95rem;padding:.7rem 1.4rem;border-radius:.5rem;cursor:pointer;transition:background .15s ease,transform .1s ease}
.la-subs-btn:hover{background:#8710d8}
.la-subs-btn:active{transform:scale(.97)}
.la-subs-btn[disabled]{background:#d1d7dc;color:#6a6f73;cursor:not-allowed}
.la-subs-headnote{display:none;align-items:center;gap:.4rem;margin-top:.6rem;color:#8a5a00;font-size:.85rem;line-height:1.4}
.la-subs-headnote svg{width:15px;height:15px;flex-shrink:0;fill:#c07f00}
/* Confirmation dialog (replaces the native window.confirm). */
.la-subs-modal-bg{position:fixed;inset:0;background:rgba(28,29,36,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:1rem;opacity:0;transition:opacity .18s ease}
.la-subs-modal-bg.open{display:flex;opacity:1}
.la-subs-modal{background:#fff;border-radius:1rem;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.28);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .18s ease}
.la-subs-modal-bg.open .la-subs-modal{transform:none}
.la-subs-modal-head{display:flex;align-items:center;gap:.7rem;padding:1.25rem 1.4rem 0}
.la-subs-modal-head svg{width:34px;height:34px;fill:#a435f0;flex-shrink:0}
.la-subs-modal-head h4{margin:0;font-size:1.2rem;font-weight:800;color:#1c1d1f}
.la-subs-modal-body{padding:.9rem 1.4rem 0;color:#3c3c3c;font-size:.95rem;line-height:1.5}
.la-subs-modal-plan{margin:.9rem 0;padding:.9rem 1rem;background:#faf5ff;border:1px solid #ecdcfb;border-radius:.6rem}
.la-subs-modal-plan .name{font-weight:700;color:#1c1d1f;margin-bottom:.35rem}
.la-subs-modal-row{display:flex;justify-content:space-between;font-size:.9rem;color:#4b4b4b;margin-top:.2rem}
.la-subs-modal-row b{color:#1c1d1f}
.la-subs-modal-secure{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#6a6f73;margin-top:.2rem}
.la-subs-modal-secure svg{width:14px;height:14px;fill:#1f9d55}
.la-subs-modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:1.2rem 1.4rem 1.3rem}
.la-subs-modal-cancel{background:#fff;border:1px solid #d1d7dc;color:#3c3c3c;font-weight:600;font-size:.92rem;padding:.6rem 1.1rem;border-radius:.5rem;cursor:pointer}
.la-subs-modal-cancel:hover{background:#f6f7f8}
.la-subs-modal-ok{background:#a435f0;border:none;color:#fff;font-weight:700;font-size:.92rem;padding:.6rem 1.3rem;border-radius:.5rem;cursor:pointer}
.la-subs-modal-ok:hover{background:#8710d8}
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
    );
    $strjson = json_encode($str, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $js = <<<JS
require([], function() {
    var CFG = {$cfgjson};
    var T = {$strjson};
    var sec = document.getElementById('la-subs');
    if (!sec || (!CFG.token && !(CFG.guestRows && CFG.guestRows.length))) { return; }
    var grid = document.getElementById('la-subs-grid');

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

    var GRADS = [
        'linear-gradient(135deg,#6a11cb,#2575fc)',
        'linear-gradient(135deg,#ff512f,#dd2476)',
        'linear-gradient(135deg,#11998e,#38ef7d)',
        'linear-gradient(135deg,#f7971e,#ffd200)',
        'linear-gradient(135deg,#8e2de2,#4a00e0)',
        'linear-gradient(135deg,#1a2980,#26d0ce)'
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
                            '<div class="la-subs-modal-row"><span>' + esc(T.total) + '</span><b>' + esc(money(s.price)) + ' ' + esc(T.egp) + '</b></div>' +
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
            function onKey(e) { if (e.key === 'Escape') { close(false); } }
            document.addEventListener('keydown', onKey);
            bg.querySelector('.la-subs-modal-cancel').onclick = function() { close(false); };
            bg.querySelector('.la-subs-modal-ok').onclick = function() { close(true); };
            bg.onclick = function(e) { if (e.target === bg) { close(false); } };
        });
    }

    function subscribe(s, btn) {
        confirmSubscribe(s).then(function(ok) {
            if (!ok) { return; }
            var orig = btn.textContent;
            btn.disabled = true; btn.textContent = T.redirecting;
            apiPost('create_subscription_checkout', {subscriptionid: s.id})
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

    function subCard(s, hasActive, idx, activeSub) {
        var isActive = !!(activeSub && Number(activeSub.subscriptionid) === Number(s.id));
        var card = el('div', {class: 'la-subs-card' + (isActive ? ' la-subs-card--active' : '')});
        var banner = el('div', {class: 'la-subs-banner', style: 'background:' + GRADS[idx % GRADS.length]});
        banner.innerHTML = CAP +
            '<span class="la-subs-badges">' +
                '<span class="la-subs-daysbadge">' + esc(num(T.days, s.duration_days)) + '</span>' +
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
        foot.appendChild(el('div', {class: 'la-subs-price'}, esc(money(s.price)) + ' <small>EGP</small>'));
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
            var b2bBtn = el('button', {type: 'button', class: 'la-subs-btn', style: 'width:100%;margin-top:.6rem;background:#1c1d1f'});
            b2bBtn.textContent = T.b2b_business;
            if (!CFG.token) {
                b2bBtn.onclick = function() { window.location.href = CFG.loginurl; };
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
    $css = <<<CSS
.la-pkgs{max-width:1280px;margin:3rem auto;padding:0 1rem}
.la-pkgs-head{margin-bottom:1.5rem}
.la-pkgs-title{font-size:1.7rem;font-weight:800;color:#1c1d1f;margin:0 0 .3rem}
.la-pkgs-sub{color:#6a6f73;margin:0;font-size:1.02rem}
.la-pkgs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;align-items:stretch}
.la-pkgs-card{display:flex;flex-direction:column;height:100%;background:#fff;border:1px solid #e5e7eb;border-radius:1rem;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:transform .18s ease,box-shadow .18s ease}
.la-pkgs-card:hover{transform:translateY(-6px);box-shadow:0 16px 32px rgba(0,0,0,.16)}
.la-pkgs-banner{position:relative;height:190px;flex-shrink:0;display:flex;flex-direction:column;justify-content:space-between;padding:1rem 1.1rem;color:#fff;overflow:hidden}
.la-pkgs-banner::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 15%,rgba(255,255,255,.22),transparent 55%)}
.la-pkgs-banner svg{width:52px;height:52px;opacity:.95;fill:#fff;position:relative;z-index:1}
.la-pkgs-badges{align-self:flex-start;position:relative;z-index:1;display:flex;gap:.4rem;flex-wrap:wrap}
.la-pkgs-flexbadge{background:rgba(255,255,255,.95);color:#1c1d1f;font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-pkgs-body{padding:1.25rem 1.25rem 1.5rem;display:flex;flex-direction:column;flex:1}
.la-pkgs-name{font-weight:700;font-size:1.2rem;color:#1c1d1f;margin:0 0 .5rem;line-height:1.35;min-height:2.7em}
.la-pkgs-desc{color:#6a6f73;font-size:.92rem;margin:0 0 .9rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:4.1em}
.la-pkgs-card--active{border-color:#0d6efd;box-shadow:0 0 0 2px rgba(13,110,253,.28)}
.la-pkgs-activebadge{background:#1f9d55;color:#fff;font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem}
.la-pkgs-meta{font-size:.86rem;color:#3c3c3c;margin-top:auto;margin-bottom:1rem;padding-top:.9rem;border-top:1px solid #f1f1f1}
.la-pkgs-dates{font-size:.86rem;color:#3c3c3c;margin-top:auto;margin-bottom:1rem;padding-top:.9rem;border-top:1px solid #f1f1f1}
.la-pkgs-dates-row{display:flex;justify-content:space-between;margin-top:.3rem}
.la-pkgs-dates-row b{color:#1c1d1f}
.la-pkgs-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding-top:.5rem}
.la-pkgs-price{font-size:1.5rem;font-weight:800;color:#1c1d1f}
.la-pkgs-price small{font-size:.8rem;font-weight:600;color:#6a6f73}
.la-pkgs-btn{background:#0d6efd;border:none;color:#fff;font-weight:700;font-size:.95rem;padding:.7rem 1.4rem;border-radius:.5rem;cursor:pointer;transition:background .15s ease,transform .1s ease}
.la-pkgs-btn:hover{background:#0b5ed7}
.la-pkgs-btn:active{transform:scale(.97)}
.la-pkgs-btn[disabled]{background:#d1d7dc;color:#6a6f73;cursor:not-allowed}
.la-pkgs-headnote{display:none;align-items:center;gap:.4rem;margin-top:.6rem;color:#8a5a00;font-size:.85rem;line-height:1.4}
.la-pkgs-headnote svg{width:15px;height:15px;flex-shrink:0;fill:#c07f00}
/* Confirmation dialog (replaces the native window.confirm). */
.la-pkgs-modal-bg{position:fixed;inset:0;background:rgba(28,29,36,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:1rem;opacity:0;transition:opacity .18s ease}
.la-pkgs-modal-bg.open{display:flex;opacity:1}
.la-pkgs-modal{background:#fff;border-radius:1rem;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.28);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .18s ease}
.la-pkgs-modal-bg.open .la-pkgs-modal{transform:none}
.la-pkgs-modal-head{display:flex;align-items:center;gap:.7rem;padding:1.25rem 1.4rem 0}
.la-pkgs-modal-head svg{width:34px;height:34px;fill:#0d6efd;flex-shrink:0}
.la-pkgs-modal-head h4{margin:0;font-size:1.2rem;font-weight:800;color:#1c1d1f}
.la-pkgs-modal-body{padding:.9rem 1.4rem 0;color:#3c3c3c;font-size:.95rem;line-height:1.5}
.la-pkgs-modal-plan{margin:.9rem 0;padding:.9rem 1rem;background:#f0f7ff;border:1px solid #cce5ff;border-radius:.6rem}
.la-pkgs-modal-plan .name{font-weight:700;color:#1c1d1f;margin-bottom:.35rem}
.la-pkgs-modal-row{display:flex;justify-content:space-between;font-size:.9rem;color:#4b4b4b;margin-top:.2rem}
.la-pkgs-modal-row b{color:#1c1d1f}
.la-pkgs-modal-secure{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#6a6f73;margin-top:.2rem}
.la-pkgs-modal-secure svg{width:14px;height:14px;fill:#1f9d55}
.la-pkgs-modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:1.2rem 1.4rem 1.3rem}
.la-pkgs-modal-cancel{background:#fff;border:1px solid #d1d7dc;color:#3c3c3c;font-weight:600;font-size:.92rem;padding:.6rem 1.1rem;border-radius:.5rem;cursor:pointer}
.la-pkgs-modal-cancel:hover{background:#f6f7f8}
.la-pkgs-modal-ok{background:#0d6efd;border:none;color:#fff;font-weight:700;font-size:.92rem;padding:.6rem 1.3rem;border-radius:.5rem;cursor:pointer}
.la-pkgs-modal-ok:hover{background:#0b5ed7}
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

    var GRADS = [
        'linear-gradient(135deg,#0d6efd,#00d4ff)',
        'linear-gradient(135deg,#fc4a1a,#f7b733)',
        'linear-gradient(135deg,#0f2027,#2c5364)',
        'linear-gradient(135deg,#f857a6,#ff5858)',
        'linear-gradient(135deg,#00b09b,#96c93d)',
        'linear-gradient(135deg,#7f00ff,#e100ff)'
    ];
    var BOLT = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 21h-1l1-7H7.5c-.58 0-.57-.32-.38-.66.19-.34.05-.08.07-.12C8.72 10.6 10.85 7.08 13 3.5h1l-1 7h4.5c.5 0 .5.33.36.61-.13.28-.08.19-.11.24C15.09 15.34 13 18.85 11 21z"/></svg>';
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
                            '<div class="la-pkgs-modal-row"><span>' + esc(T.total) + '</span><b>' + esc(money(p.price)) + ' ' + esc(T.egp) + '</b></div>' +
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
            function onKey(e) { if (e.key === 'Escape') { close(false); } }
            document.addEventListener('keydown', onKey);
            bg.querySelector('.la-pkgs-modal-cancel').onclick = function() { close(false); };
            bg.querySelector('.la-pkgs-modal-ok').onclick = function() { close(true); };
            bg.onclick = function(e) { if (e.target === bg) { close(false); } };
        });
    }

    function subscribe(p, btn) {
        confirmBuyPackage(p).then(function(ok) {
            if (!ok) { return; }
            var orig = btn.textContent;
            btn.disabled = true; btn.textContent = T.redirecting;
            apiPost('create_package_checkout', {packageid: p.id})
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
        foot.appendChild(el('div', {class: 'la-pkgs-price'}, esc(money(p.price)) + ' <small>EGP</small>'));
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
function local_academy_before_footer() {
    global $PAGE, $USER, $COURSE, $DB, $CFG;
    $output = '';

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
