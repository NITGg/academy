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

namespace local_nit_category;

use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use moodle_url;
use pix_icon;

/**
 * The admin screen of the "Why thousands choose us" section.
 *
 * Shown as the "Why choose us" tab of the Site pages manager
 * (/local/profilefields/manage.php?tab=whychoose). That page owns the tab bar and calls
 * process() before output and render() after its header; everything the tab shows and
 * saves lives here, so the manager needs to know nothing about cards or file areas.
 * The card add/edit form is a page of its own (whycard.php) and comes back to the tab.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class whychoose_ui {

    /** @var string the tab id on the Site pages manager */
    const TAB = 'whychoose';

    /**
     * The tab's own URL on the Site pages manager.
     *
     * @param array $extra extra query params
     * @return moodle_url
     */
    public static function url(array $extra = []): moodle_url {
        return new moodle_url('/local/profilefields/manage.php', ['tab' => self::TAB] + $extra);
    }

    /**
     * Handle a card action link or the texts form, then redirect. Runs before output.
     *
     * A delete link first lands on render()'s confirm dialog; only the confirmed link
     * (confirm=1) deletes here.
     *
     * @return void
     */
    public static function process(): void {
        $do     = optional_param('do', '', PARAM_ALPHA);
        $cardid = optional_param('cardid', 0, PARAM_INT);

        if ($do !== '' && $cardid > 0) {
            require_sesskey();
            if (!whychoose::get_card($cardid)) {
                redirect(self::url());
            }
            if ($do === 'moveup' || $do === 'movedown') {
                whychoose::move_card($cardid, $do === 'moveup' ? -1 : 1);
                redirect(self::url());
            }
            if ($do === 'delete' && optional_param('confirm', 0, PARAM_BOOL)) {
                whychoose::delete_card($cardid);
                redirect(self::url(), get_string('whycard_deleted', 'local_nit_category'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
            }
        }

        $form = self::texts_form();
        if ($data = $form->get_data()) {
            whychoose::save_texts((array) $data);
            redirect(self::url(), get_string('changessaved'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    /**
     * Print the tab: intro, the cards table with its actions, and the texts form.
     *
     * @return void
     */
    public static function render(): void {
        global $OUTPUT;

        // An unconfirmed delete shows the question instead of the tab.
        $do     = optional_param('do', '', PARAM_ALPHA);
        $cardid = optional_param('cardid', 0, PARAM_INT);
        if ($do === 'delete' && $cardid > 0 && ($card = whychoose::get_card($cardid))) {
            echo $OUTPUT->confirm(
                get_string('whycard_confirmdelete', 'local_nit_category', s(text_util::ml($card->title))),
                self::url(['do' => 'delete', 'cardid' => $cardid, 'confirm' => 1, 'sesskey' => sesskey()]),
                self::url()
            );
            return;
        }

        echo html_writer::tag('p', get_string('whychoose_intro', 'local_nit_category'), ['class' => 'text-muted']);

        echo $OUTPUT->heading(get_string('whycards', 'local_nit_category'), 3, 'h4');
        echo self::cards_table();
        echo html_writer::div(
            $OUTPUT->single_button(new moodle_url('/local/nit_category/whycard.php'),
                get_string('whycard_add', 'local_nit_category'), 'get'),
            'mb-4'
        );

        echo $OUTPUT->heading(get_string('whychoose_texts', 'local_nit_category'), 3, 'mt-5 h4');
        $form = self::texts_form();
        $form->set_texts();
        $form->display();
    }

    /**
     * The section-texts form, posting back to this tab.
     *
     * @return form\whychoose_texts_form
     */
    protected static function texts_form(): form\whychoose_texts_form {
        return new form\whychoose_texts_form(self::url()->out(false));
    }

    /**
     * The table of cards: number, picture, both languages of heading and text, actions.
     *
     * @return string HTML
     */
    protected static function cards_table(): string {
        global $OUTPUT;

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

        // Both languages of a value, each in its own direction.
        $both = static function (string $raw): string {
            $out = [];
            foreach (whychoose::split($raw) as $lang => $val) {
                if ($val !== '') {
                    $out[] = html_writer::div(s($val), '', ['dir' => $lang === 'ar' ? 'rtl' : 'ltr']);
                }
            }
            return implode('', $out);
        };

        $last = count($cards) - 1;
        foreach ($cards as $i => $card) {
            $imgurl = whychoose::get_image_url((int) $card->id);
            $img = $imgurl !== ''
                ? html_writer::empty_tag('img', ['src' => $imgurl, 'alt' => '',
                    'style' => 'max-width:64px;max-height:64px;object-fit:contain;'])
                : html_writer::span(get_string('whycard_noimage', 'local_nit_category'), 'text-muted small fst-italic');

            $tools = $OUTPUT->action_icon(new moodle_url('/local/nit_category/whycard.php', ['cardid' => $card->id]),
                new pix_icon('t/edit', get_string('edit')));
            if ($i > 0) {
                $tools .= $OUTPUT->action_icon(self::url(['do' => 'moveup', 'cardid' => $card->id, 'sesskey' => sesskey()]),
                    new pix_icon('t/up', get_string('moveup')));
            }
            if ($i < $last) {
                $tools .= $OUTPUT->action_icon(self::url(['do' => 'movedown', 'cardid' => $card->id, 'sesskey' => sesskey()]),
                    new pix_icon('t/down', get_string('movedown')));
            }
            $tools .= $OUTPUT->action_icon(self::url(['do' => 'delete', 'cardid' => $card->id, 'sesskey' => sesskey()]),
                new pix_icon('t/delete', get_string('delete')));

            $table->data[] = [
                str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                $img,
                $both($card->title),
                $both($card->body),
                $tools,
            ];
        }
        if (!$cards) {
            $cell = new html_table_cell(html_writer::span(get_string('whycard_none', 'local_nit_category'), 'text-muted'));
            $cell->colspan = count($table->head);
            $table->data[] = new html_table_row([$cell]);
        }
        return html_writer::table($table);
    }
}
