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
 * JS (driving /local/academy/api.php with a mobile WS token, exactly like student.php) that fetches
 * the available subscriptions and renders Udemy/Coursera-style cards. Returns '' when a WS token
 * cannot be minted; the section then simply does not appear.
 *
 * @return string HTML to echo before the footer
 */
function local_academy_available_subscriptions_section() {
    global $DB, $CFG, $PAGE;

    // Mint the same mobile web-service token student.php uses so the front-page cards and the student
    // hub exercise identical endpoints. If the mobile service is unavailable, skip the section.
    require_once($CFG->dirroot . '/webservice/lib.php');
    try {
        $service = $DB->get_record('external_services',
            array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
        $token = external_generate_token_for_current_user($service)->token;
    } catch (\Exception $e) {
        return '';
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
.la-subs-daysbadge{align-self:flex-start;background:rgba(255,255,255,.95);color:#1c1d1f;font-weight:700;font-size:.8rem;padding:.3rem .7rem;border-radius:1rem;position:relative;z-index:1}
.la-subs-body{padding:1.25rem 1.25rem 1.5rem;display:flex;flex-direction:column;flex:1}
.la-subs-name{font-weight:700;font-size:1.2rem;color:#1c1d1f;margin:0 0 .5rem;line-height:1.35;min-height:2.7em}
.la-subs-desc{color:#6a6f73;font-size:.92rem;margin:0 0 .9rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:4.1em}
.la-subs-courses{font-size:.86rem;color:#3c3c3c;margin-bottom:1rem;padding-top:.9rem;border-top:1px solid #f1f1f1}
.la-subs-courses b{color:#1c1d1f}
.la-subs-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding-top:.5rem}
.la-subs-price{font-size:1.5rem;font-weight:800;color:#1c1d1f}
.la-subs-price small{font-size:.8rem;font-weight:600;color:#6a6f73}
.la-subs-btn{background:#a435f0;border:none;color:#fff;font-weight:700;font-size:.95rem;padding:.7rem 1.4rem;border-radius:.5rem;cursor:pointer;transition:background .15s ease,transform .1s ease}
.la-subs-btn:hover{background:#8710d8}
.la-subs-btn:active{transform:scale(.97)}
.la-subs-btn[disabled]{background:#d1d7dc;color:#6a6f73;cursor:not-allowed}
CSS;

    $section = html_writer::tag('style', $css) .
        '<section id="la-subs" class="la-subs" style="display:none">' .
            '<div class="la-subs-head">' .
                html_writer::tag('h3', s($heading), array('class' => 'la-subs-title')) .
                html_writer::tag('p', s($desc), array('class' => 'la-subs-sub')) .
            '</div>' .
            '<div id="la-subs-msg" class="alert" style="display:none"></div>' .
            '<div id="la-subs-grid" class="la-subs-grid"></div>' .
        '</section>';

    $cfg = array(
        'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
        'token'    => $token,
    );
    $cfgjson = json_encode($cfg, JSON_UNESCAPED_SLASHES);

    $js = <<<JS
require([], function() {
    var CFG = {$cfgjson};
    var sec = document.getElementById('la-subs');
    if (!sec || !CFG.token) { return; }
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
    function showMsg(t, k) {
        var m = document.getElementById('la-subs-msg');
        m.textContent = t; m.className = 'alert alert-' + (k || 'info'); m.style.display = 'block';
    }
    function parse(r) {
        return r.text().then(function(t) {
            var j;
            try { j = JSON.parse(t); } catch (e) { throw new Error('Session expired — reload the page.'); }
            if (j.status !== 'success') { throw new Error(j.error || 'Request failed'); }
            return j.data;
        });
    }
    function apiGet(fn, params) {
        var q = new URLSearchParams(Object.assign({function: fn, token: CFG.token}, params || {}));
        return fetch(CFG.endpoint + '?' + q.toString()).then(parse);
    }
    function apiPost(fn, params) {
        var body = new URLSearchParams(Object.assign({function: fn, token: CFG.token}, params || {}));
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

    function subscribe(s, btn) {
        if (!window.confirm('Subscribe to "' + s.name + '" for ' + money(s.price) + ' EGP (' + s.duration_days + ' days)?')) { return; }
        var orig = btn.textContent;
        btn.disabled = true; btn.textContent = 'Redirecting…';
        apiPost('create_subscription_checkout', {subscriptionid: s.id})
            .then(function(d) { window.location.href = d.checkout_url; })
            .catch(function(e) { showMsg(e.message, 'danger'); btn.disabled = false; btn.textContent = orig; });
    }

    function subCard(s, hasActive, idx) {
        var card = el('div', {class: 'la-subs-card'});
        var banner = el('div', {class: 'la-subs-banner', style: 'background:' + GRADS[idx % GRADS.length]});
        banner.innerHTML = CAP + '<span class="la-subs-daysbadge">' + esc(s.duration_days) + ' days</span>';
        card.appendChild(banner);

        var body = el('div', {class: 'la-subs-body'});
        body.appendChild(el('div', {class: 'la-subs-name'}, esc(s.name)));
        if (s.description) { body.appendChild(el('div', {class: 'la-subs-desc'}, esc(s.description))); }
        var n = (s.courses || []).length;
        body.appendChild(el('div', {class: 'la-subs-courses'},
            n ? ('<b>' + n + '</b> course' + (n === 1 ? '' : 's') + ' included') : 'Full course access'));

        var foot = el('div', {class: 'la-subs-foot'});
        foot.appendChild(el('div', {class: 'la-subs-price'}, esc(money(s.price)) + ' <small>EGP</small>'));
        var btn = el('button', {type: 'button', class: 'la-subs-btn'}, hasActive ? 'Subscribed' : 'Subscribe');
        if (hasActive) { btn.disabled = true; } else { btn.onclick = function() { subscribe(s, btn); }; }
        foot.appendChild(btn);
        body.appendChild(foot);
        card.appendChild(body);
        return card;
    }

    Promise.all([apiGet('get_available_subscriptions'), apiGet('get_my_subscriptions')]).then(function(res) {
        var rows = res[0] || [], mine = res[1] || [];
        if (!rows.length) { return; } // nothing to sell — leave the section hidden
        var hasActive = mine.some(function(s) { return s.status === 'active'; });
        grid.innerHTML = '';
        rows.forEach(function(s, i) { grid.appendChild(subCard(s, hasActive, i)); });
        sec.style.display = 'block';
    }).catch(function() { /* keep the section hidden on any error */ });
});
JS;

    $PAGE->requires->js_amd_inline($js);
    return $section;
}

/**
 * Add an "Available subscriptions" section (Udemy/Coursera-style cards) to the site front page so
 * students can discover and buy course-access subscriptions directly from home. Replaces the old
 * "Book lessons & Flex" banner. The section renders itself only when at least one subscription is
 * available and hides silently otherwise.
 */
function local_academy_before_footer() {
    global $PAGE, $USER, $COURSE, $DB, $CFG;
    $output = '';

    // 1. Front page "Available subscriptions" cards
    if (!CLI_SCRIPT && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT) && !(defined('WS_SERVER') && WS_SERVER)) {
        if (isloggedin() && !isguestuser() && $PAGE->pagetype === 'site-index') {
            $output .= local_academy_available_subscriptions_section();
        }
    }

    // 2. Only for real, interactive, logged-in users — skip guests, AJAX/WS/CLI requests.
    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
        return $output;
    }
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!\local_academy\realtime::enabled()) {
        return;
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
