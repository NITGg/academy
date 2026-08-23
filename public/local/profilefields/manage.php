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
 * Sign-up and profile field layout.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

admin_externalpage_setup('local_profilefields_manage');

$form = new \local_profilefields\form\manage_form($PAGE->url);

if ($form->is_cancelled()) {
    redirect($PAGE->url);
} else if ($data = $form->get_data()) {
    \local_profilefields\form\manage_form::save($data);
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form->load_current_values();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managefields', 'local_profilefields'));

echo $OUTPUT->notification(
    get_string('manageintro', 'local_profilefields', (new moodle_url('/login/signup.php'))->out()),
    \core\output\notification::NOTIFY_INFO,
    false
);

$form->display();

echo $OUTPUT->footer();
