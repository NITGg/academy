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
 * Reached from, and returning to, the "Why choose us" tab of the Site pages manager
 * (see \local_nit_category\whychoose_ui).
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_nit_category\whychoose;
use local_nit_category\whychoose_ui;

$cardid = optional_param('cardid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$manageurl = whychoose_ui::url();

$card = null;
if ($cardid) {
    $card = whychoose::get_card($cardid);
    if (!$card) {
        redirect($manageurl);
    }
}
$heading = $card ? get_string('whycard_edit', 'local_nit_category') : get_string('whycard_add', 'local_nit_category');

$PAGE->set_url(new moodle_url('/local/nit_category/whycard.php', ['cardid' => $cardid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->navbar->add(get_string('whychoose', 'local_nit_category'), $manageurl);
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
