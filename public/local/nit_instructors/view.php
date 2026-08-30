<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The public instructor profile (AC-4.5.16).
 *
 * "The public instructor profile shown to learners displays the profile picture,
 * the name, and the Academic and Professional Background group. It never displays
 * the email address, the telephone number, the country of record, the nationality
 * or any account setting."
 *
 * That sentence is why this page exists at all rather than pointing learners at
 * /user/profile.php. Moodle's profile page decides what to show from capabilities
 * and site settings, and on a site configured slightly differently it will happily
 * show an email address. This page cannot: it is built from a fixed list of three
 * things, so there is no configuration under which it leaks a fourth.
 *
 * It shows the approved version only. A change waiting for review is invisible
 * here, which is exactly what AC-4.5.14 asks for.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_nit_instructors\output;
use local_nit_instructors\profile;

$id = required_param('id', PARAM_INT);

// A public page by intent - AC-4.5.6 has a learner reading this before they buy,
// and a guest deciding whether to register is exactly the audience. It still
// respects a site that requires login to see anything at all.
if (!empty($CFG->forcelogin)) {
    require_login(null, false);
}

$url = new moodle_url('/local/nit_instructors/view.php', ['id' => $id]);

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$user = $DB->get_record('user', ['id' => $id, 'deleted' => 0], 'id, firstname, lastname, picture, imagealt, suspended');

// A suspended or missing account is one message, not two: whether an id belongs to
// a suspended instructor or to nobody is not a learner's business.
if (!$user || !empty($user->suspended) || !profile::is_instructor((int) $user->id)) {
    throw new moodle_exception('nosuchinstructor', 'local_nit_instructors');
}

$name = fullname($user);

$PAGE->set_title($name);
$PAGE->set_heading($name);

$version = profile::approved((int) $user->id);

echo $OUTPUT->header();

echo html_writer::start_div('nit-instructor-profile');

// The three things this page is allowed to show, and nothing else.
echo html_writer::div(
    $OUTPUT->user_picture($user, ['size' => 100, 'link' => false]),
    'mb-3'
);
echo $OUTPUT->heading(s($name), 2);

$group = output::group($version, (int) $user->id);

if ($group !== '') {
    echo html_writer::tag('h3', get_string('background', 'local_nit_instructors'),
        ['class' => 'h5 mt-4']);
    echo $group;
} else {
    // AC-4.5.11 allows an instructor with none of this completed. The page still
    // has to be a page - a name and a picture are a perfectly good profile.
    echo html_writer::div(get_string('nobackground', 'local_nit_instructors'), 'text-muted');
}

echo html_writer::end_div();

echo $OUTPUT->footer();
