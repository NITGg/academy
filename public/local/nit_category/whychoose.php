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
 * Manage the "Why thousands choose us" section of the category pages.
 *
 * Site administration → Plugins → Local plugins → "Why choose us" section. The three
 * texts are edited here in place; the cards are listed here and edited on whycard.php.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_category\text_util;
use local_nit_category\whychoose;

admin_externalpage_setup('local_nit_category_whychoose');

$do     = optional_param('do', '', PARAM_ALPHA);
$cardid = optional_param('cardid', 0, PARAM_INT);

$url = new moodle_url('/local/nit_category/whychoose.php');

// ── Card actions (links from the table) ─────────────────────────────────────────────────
if ($do !== '' && $cardid > 0) {
    require_sesskey();
    $card = whychoose::get_card($cardid);
    if (!$card) {
        redirect($url);
    }
    if ($do === 'moveup' || $do === 'movedown') {
        whychoose::move_card($cardid, $do === 'moveup' ? -1 : 1);
        redirect($url);
    }
    if ($do === 'delete') {
        $confirm = optional_param('confirm', 0, PARAM_BOOL);
        if ($confirm) {
            whychoose::delete_card($cardid);
            redirect($url, get_string('whycard_deleted', 'local_nit_category'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('whycard_confirmdelete', 'local_nit_category', s(text_util::ml($card->title))),
            new moodle_url($url, ['do' => 'delete', 'cardid' => $cardid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $url
        );
        echo $OUTPUT->footer();
        exit;
    }
}

// ── Section texts ───────────────────────────────────────────────────────────────────────
$textsform = new \local_nit_category\form\whychoose_texts_form($url->out(false));
if ($data = $textsform->get_data()) {
    whychoose::save_texts((array) $data);
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$textsform->set_texts();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('whychoose', 'local_nit_category'));
echo html_writer::div(get_string('whychoose_intro', 'local_nit_category'), 'alert alert-info');

// ── Cards table ─────────────────────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('whycards', 'local_nit_category'), 3);

$cards = array_values(whychoose::get_cards());
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    '#',
    get_string('whycard_image', 'local_nit_category'),
    get_string('whycard_title', 'local_nit_category'),
    get_string('whycard_body', 'local_nit_category'),
    get_string('actions'),
];
$last = count($cards) - 1;
foreach ($cards as $i => $card) {
    $title = whychoose::split($card->title);
    $body  = whychoose::split($card->body);
    $two = static function (array $parts): string {
        $out = [];
        if ($parts['en'] !== '') {
            $out[] = html_writer::div(s($parts['en']), '', ['dir' => 'ltr']);
        }
        if ($parts['ar'] !== '') {
            $out[] = html_writer::div(s($parts['ar']), '', ['dir' => 'rtl']);
        }
        return implode('', $out);
    };

    $imgurl = whychoose::get_image_url((int) $card->id);
    $img = $imgurl !== ''
        ? html_writer::empty_tag('img', ['src' => $imgurl, 'alt' => '', 'style' => 'max-width:64px;max-height:64px;object-fit:contain;'])
        : html_writer::span(get_string('whycard_noimage', 'local_nit_category'), 'text-muted small fst-italic');

    $edit = new moodle_url('/local/nit_category/whycard.php', ['cardid' => $card->id]);
    $tools = $OUTPUT->action_icon($edit, new pix_icon('t/edit', get_string('edit')));
    if ($i > 0) {
        $tools .= $OUTPUT->action_icon(new moodle_url($url, ['do' => 'moveup', 'cardid' => $card->id, 'sesskey' => sesskey()]),
            new pix_icon('t/up', get_string('moveup')));
    }
    if ($i < $last) {
        $tools .= $OUTPUT->action_icon(new moodle_url($url, ['do' => 'movedown', 'cardid' => $card->id, 'sesskey' => sesskey()]),
            new pix_icon('t/down', get_string('movedown')));
    }
    $tools .= $OUTPUT->action_icon(new moodle_url($url, ['do' => 'delete', 'cardid' => $card->id, 'sesskey' => sesskey()]),
        new pix_icon('t/delete', get_string('delete')));

    $table->data[] = [
        str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
        $img,
        $two($title),
        $two($body),
        $tools,
    ];
}
if (!$cards) {
    $cell = new html_table_cell(html_writer::span(get_string('whycard_none', 'local_nit_category'), 'text-muted'));
    $cell->colspan = count($table->head);
    $table->data[] = new html_table_row([$cell]);
}
echo html_writer::table($table);
echo html_writer::div(
    $OUTPUT->single_button(new moodle_url('/local/nit_category/whycard.php'),
        get_string('whycard_add', 'local_nit_category'), 'get'),
    'mb-4'
);

// ── Section texts form ──────────────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('whychoose_texts', 'local_nit_category'), 3);
$textsform->display();

echo $OUTPUT->footer();
