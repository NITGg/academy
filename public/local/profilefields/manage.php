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
 * Site pages manager - the register, login, profile, password-reset and footer tabs.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_profilefields\page;

admin_externalpage_setup('local_profilefields_manage');

$tab = optional_param('tab', page::TAB_REGISTER, PARAM_ALPHA);
if (!in_array($tab, page::tabs(), true)) {
    $tab = page::TAB_REGISTER;
}

$PAGE->set_url(new moodle_url('/local/profilefields/manage.php', ['tab' => $tab]));

// Runs before output so a save or reorder can redirect with a notice.
page::process($tab);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managefields', 'local_profilefields'));

page::render($tab);

echo $OUTPUT->footer();
