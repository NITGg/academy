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
 * Live notification updates without a page reload.
 *
 * Moodle's navbar notification bell only fetches the unread count at page render and the list when
 * the user opens the popover — so a notification that arrives while the user sits on a page never
 * shows up until they refresh. This injects a tiny poller (built on the existing core/ajax +
 * core/toast modules, so no grunt build is needed) that, every 30s, asks the
 * message_popup_get_unread_popup_notification_count web service for the current count. When the
 * count rises it updates the bell badge live and pops a toast with the newest notification's
 * subject. Polling pauses while the tab is hidden and runs once immediately when it becomes
 * visible again.
 */
function local_academy_before_footer() {
    global $PAGE;

    // Only for real, interactive, logged-in users — skip guests, AJAX/WS/CLI requests.
    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
        return;
    }
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $intervalms = 30000; // Poll cadence.
    $newnotif = get_string('newnotification', 'local_academy');

    $js = <<<JS
require(['core/ajax', 'core/toast'], function(Ajax, Toast) {
    var container = document.getElementById('nav-notification-popover-container');
    if (!container) { return; }
    var userid = parseInt(container.getAttribute('data-userid'), 10) || 0;
    if (!userid) { return; }
    var badge = container.querySelector('[data-region="count-container"]');

    var lastCount = (function() {
        var n = badge ? parseInt((badge.textContent || '').trim(), 10) : 0;
        return isNaN(n) ? 0 : n;
    })();

    function setBadge(count) {
        if (!badge) { return; }
        badge.textContent = count;
        if (count > 0) { badge.classList.remove('hidden'); }
        else { badge.classList.add('hidden'); }
    }

    function toast(subject) {
        Toast.add(subject || '{$newnotif}', {type: 'info'});
    }

    function announce() {
        // Pull the newest notification so the toast can show its subject; fall back to a generic msg.
        Ajax.call([{
            methodname: 'message_popup_get_popup_notifications',
            args: {useridto: userid, newestfirst: true, limit: 1, offset: 0}
        }])[0].then(function(result) {
            var n = (result && result.notifications && result.notifications.length) ? result.notifications[0] : null;
            toast(n ? (n.shortenedsubject || n.subject) : null);
            return null;
        }).catch(function() { toast(null); });
    }

    function poll() {
        Ajax.call([{
            methodname: 'message_popup_get_unread_popup_notification_count',
            args: {useridto: userid}
        }])[0].then(function(count) {
            count = parseInt(count, 10) || 0;
            if (count > lastCount) {
                setBadge(count);
                announce();
            } else if (count !== lastCount) {
                setBadge(count); // Read/cleared elsewhere — keep the badge in sync.
            }
            lastCount = count;
            return null;
        }).catch(function() { /* transient — retry next tick */ });
    }

    var timer = setInterval(poll, {$intervalms});
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (timer) { clearInterval(timer); timer = null; }
        } else if (!timer) {
            poll();
            timer = setInterval(poll, {$intervalms});
        }
    });
});
JS;

    $PAGE->requires->js_amd_inline($js);
}
