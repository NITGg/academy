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
 * Add or edit one card of the "Why thousands choose us" section.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_category\whychoose;

$cardid = optional_param('cardid', 0, PARAM_INT);

admin_externalpage_setup('local_nit_category_whychoose');
$PAGE->set_url(new moodle_url('/local/nit_category/whycard.php', ['cardid' => $cardid]));

$manageurl = new moodle_url('/local/nit_category/whychoose.php');

$card = null;
if ($cardid) {
    $card = whychoose::get_card($cardid);
    if (!$card) {
        redirect($manageurl);
    }
}
$heading = $card ? get_string('whycard_edit', 'local_nit_category') : get_string('whycard_add', 'local_nit_category');
$PAGE->set_title($heading);
$PAGE->navbar->add($heading);

$form = new \local_nit_category\form\whycard_form($PAGE->url->out(false));
$form->set_card($card);

if ($form->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $form->get_data()) {
    whychoose::save_card($data);
    redirect($manageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
$form->display();
echo $OUTPUT->footer();
