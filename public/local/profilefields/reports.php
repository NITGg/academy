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
 * Register reports - refused sign-up attempts, and the IP deny list behind them.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_profilefields\reports;

admin_externalpage_setup('local_profilefields_reports');

$tab = optional_param('tab', reports::TAB_ATTEMPTS, PARAM_ALPHA);
if (!in_array($tab, reports::tabs(), true)) {
    $tab = reports::TAB_ATTEMPTS;
}

$PAGE->set_url(reports::url($tab));

// Runs before output so an action can redirect with a notice.
reports::process($tab);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportstitle', 'local_profilefields'));

reports::render($tab);

echo $OUTPUT->footer();
