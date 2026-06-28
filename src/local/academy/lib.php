<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add a "Teacher profile" section to the user's Moodle profile page (standard themes).
 */
function local_academy_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!$iscurrentuser) {
        return true;
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
    $node = navigation_node::create(
        get_string('editmyteacherprofile', 'local_academy'),
        new moodle_url('/local/academy/teacher_profile.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_academy_teacherprofile',
        new pix_icon('i/edit', '')
    );
    // Place it inside the "User account" group (with Edit profile, Change password, ...).
    $useraccount = $navigation->find('useraccount', navigation_node::TYPE_CONTAINER);
    if ($useraccount) {
        $useraccount->add_node($node);
    } else {
        $navigation->add_node($node);
    }
}
