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
 * Live notification updates via realtime push (Socket.IO) — no polling.
 *
 * Moodle's navbar notification bell only fetches the unread count at page render, so a notification
 * that arrives while the user sits on a page never shows up until they refresh. This connects the
 * browser to the notify-ws Socket.IO relay (authenticated with a short-lived HMAC token) and joins a
 * private per-user room. The instant a notification is sent, Moodle's
 * {@see \local_academy\observer::notification_sent} pushes it and the client plays a short chime and
 * refreshes the page — so e.g. a teacher sees a new lesson request without touching anything.
 *
 * Requires the relay to be reachable from the browser (locally: direct on :3100; in production: a
 * reverse-proxy route such as nginx /notify-ws). If it can't connect, nothing happens until the
 * page is reloaded — there is no polling fallback by design. The chime needs a prior user gesture
 * (browser autoplay policy); the reload happens regardless.
 */
function local_academy_before_footer() {
    global $PAGE, $USER;

    // Only for real, interactive, logged-in users — skip guests, AJAX/WS/CLI requests.
    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
        return;
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
}
