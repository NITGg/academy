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
 * Live notification updates: realtime push (Socket.IO) → chime + auto page refresh.
 *
 * Moodle's navbar notification bell only fetches the unread count at page render and the list when
 * the user opens the popover — so a notification that arrives while the user sits on a page never
 * shows up until they refresh.
 *
 * Primary path: the browser connects to the notify-ws Socket.IO server (authenticated with a
 * short-lived HMAC token), joins a private per-user room, and gets a "notification" event the
 * instant one is sent (Moodle's {@see \local_academy\observer::notification_sent} pushes it). On
 * each event we play a short chime (one per notification) and refresh the page.
 *
 * Fallback path: if the socket can't connect (relay down / proxy misconfigured / realtime
 * disabled), the client degrades to polling message_popup_get_unread_popup_notification_count every
 * 30s, so notifications are never silently lost. The chime needs a prior user gesture (browser
 * autoplay policy); the reload happens regardless.
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

    $enabled = \local_academy\realtime::enabled();
    $cfg = array(
        // Empty url => realtime disabled => client uses the polling fallback only.
        'url'      => $enabled ? \local_academy\realtime::public_url() : '',
        'path'     => \local_academy\realtime::socket_path(),
        'token'    => \local_academy\realtime::mint_token((int) $USER->id),
        'interval' => 30000,
    );
    $cfgjson = json_encode($cfg, JSON_UNESCAPED_SLASHES);

    $js = <<<JS
require(['core/ajax'], function(Ajax) {
    var CFG = {$cfgjson};
    var container = document.getElementById('nav-notification-popover-container');
    if (!container) { return; }
    var userid = parseInt(container.getAttribute('data-userid'), 10) || 0;
    if (!userid) { return; }
    var badge = container.querySelector('[data-region="count-container"]');

    var lastCount = (function() {
        var n = badge ? parseInt((badge.textContent || '').trim(), 10) : 0;
        return isNaN(n) ? 0 : n;
    })();

    // Short chime via the Web Audio API (no asset file needed). Browsers block audio until the
    // user has interacted with the page, so the context is lazily created/resumed on first gesture.
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

    var CHIME_GAP = 450;
    var MAX_CHIMES = 5;

    // Reload shortly after the last notification, so several arriving together still each chime
    // but the page only reloads once.
    var reloadTimer = null;
    function scheduleReload(delay) {
        if (reloadTimer) { clearTimeout(reloadTimer); }
        reloadTimer = setTimeout(function() { window.location.reload(); }, delay);
    }

    // ── Realtime path ──────────────────────────────────────────────────────────────────────────
    var socketConnected = false;
    function onPush() {
        chime();              // one chime per pushed notification
        scheduleReload(1000); // debounced single reload
    }
    function connectSocket() {
        if (!CFG.url || !CFG.token) { startPolling(); return; }
        var s = document.createElement('script');
        s.src = CFG.url.replace(/\\/$/, '') + CFG.path + '/socket.io.js';
        s.onload = function() {
            try {
                if (!window.io) { startPolling(); return; }
                var socket = window.io(CFG.url, {
                    path: CFG.path,
                    transports: ['websocket', 'polling'],
                    auth: { token: CFG.token },
                    reconnection: true,
                    timeout: 5000
                });
                socket.on('connect', function() { socketConnected = true; stopPolling(); });
                socket.on('notification', onPush);
                socket.on('connect_error', function() { socketConnected = false; startPolling(); });
                socket.on('disconnect', function() { socketConnected = false; startPolling(); });
            } catch (e) { startPolling(); }
        };
        s.onerror = function() { startPolling(); };
        document.head.appendChild(s);
        // Safety net: if the socket hasn't connected shortly, poll in the meantime.
        setTimeout(function() { if (!socketConnected) { startPolling(); } }, 6000);
    }

    // ── Polling fallback ───────────────────────────────────────────────────────────────────────
    function chimeFor(newcount) {
        var times = Math.min(newcount, MAX_CHIMES);
        for (var i = 0; i < times; i++) { setTimeout(chime, i * CHIME_GAP); }
        return times;
    }
    var pollTimer = null;
    function poll() {
        if (socketConnected) { return; }
        Ajax.call([{
            methodname: 'message_popup_get_unread_popup_notification_count',
            args: {useridto: userid}
        }])[0].then(function(count) {
            count = parseInt(count, 10) || 0;
            if (count > lastCount) {
                var played = chimeFor(count - lastCount);
                scheduleReload(400 + played * CHIME_GAP);
                return null;
            }
            if (count !== lastCount && badge) {
                badge.textContent = count;
                if (count > 0) { badge.classList.remove('hidden'); }
                else { badge.classList.add('hidden'); }
            }
            lastCount = count;
            return null;
        }).catch(function() { /* transient — retry next tick */ });
    }
    function startPolling() {
        if (pollTimer || socketConnected) { return; }
        poll();
        pollTimer = setInterval(poll, CFG.interval);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopPolling();
        } else if (!socketConnected) {
            startPolling();
        }
    });

    connectSocket();
});
JS;

    $PAGE->requires->js_amd_inline($js);
}
