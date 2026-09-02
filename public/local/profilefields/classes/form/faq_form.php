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

namespace local_profilefields\form;

use local_profilefields\faq;
use local_profilefields\staticpages;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The question and answer list of the FAQ page.
 *
 * One form for the whole list, built with repeat_elements: pressing "Add three more
 * questions" grows it, ticking Delete removes a row, and one Save writes the lot.
 * Editing twelve questions is one page and one submission rather than twelve trips
 * through an add/edit/delete screen.
 *
 * Order is a number the administrator types, not a pair of arrows. Arrows need a
 * round trip per move, which on an unsaved form means either losing what is typed
 * or saving it behind the administrator's back; a number moves a question from
 * eleventh to second in one edit. The numbers are handed out in steps of
 * {@see faq::SORTSTEP} so there is always room to put a new question between two
 * old ones.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faq_form extends moodleform {

    /** @var int How many blank rows to offer when the list is empty. */
    const INITIAL = 3;

    /** @var int How many rows the "add more" button adds. */
    const ADDMORE = 3;

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $items = (array) ($this->_customdata['items'] ?? []);

        $mform->addElement('hidden', 'tab', 'pagefaq');
        $mform->setType('tab', PARAM_ALPHA);
        $mform->addElement('hidden', 'faqsubmit', 1);
        $mform->setType('faqsubmit', PARAM_INT);

        $repeated = [];
        $repeatedoptions = [];

        $repeated[] = $mform->createElement('header', 'faqitemheader',
            get_string('faqitem', 'local_profilefields', '{no}'));

        $repeated[] = $mform->createElement('hidden', 'faqid', 0);
        $repeatedoptions['faqid']['type'] = PARAM_INT;

        foreach (staticpages::langs() as $lang) {
            $langname = get_string('lang' . $lang, 'local_profilefields');
            $dir = $lang === 'ar' ? 'rtl' : 'ltr';

            $repeated[] = $mform->createElement('text', 'faqquestion' . $lang,
                get_string('faqquestion', 'local_profilefields', $langname),
                ['size' => 70, 'dir' => $dir]);
            $repeatedoptions['faqquestion' . $lang]['type'] = PARAM_TEXT;

            $repeated[] = $mform->createElement('editor', 'faqanswer' . $lang,
                get_string('faqanswer', 'local_profilefields', $langname),
                ['rows' => 6], self::answer_options());
            $repeatedoptions['faqanswer' . $lang]['type'] = PARAM_RAW;
        }

        $repeated[] = $mform->createElement('text', 'faqsortorder',
            get_string('faqsortorder', 'local_profilefields'), ['size' => 6]);
        $repeatedoptions['faqsortorder']['type'] = PARAM_INT;
        $repeatedoptions['faqsortorder']['helpbutton'] = ['faqsortorder', 'local_profilefields'];

        $repeated[] = $mform->createElement('advcheckbox', 'faqvisible',
            get_string('faqvisible', 'local_profilefields'), get_string('faqvisible_label', 'local_profilefields'));
        $repeatedoptions['faqvisible']['default'] = 1;

        $repeated[] = $mform->createElement('advcheckbox', 'faqdelete',
            get_string('faqdelete', 'local_profilefields'), get_string('faqdelete_label', 'local_profilefields'));

        $count = max(count($items) + 1, self::INITIAL);

        $this->repeat_elements($repeated, $count, $repeatedoptions, 'faqcount', 'faqaddmore',
            self::ADDMORE, get_string('faqaddmore', 'local_profilefields'), false);

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * A question with an answer but nothing asked is a row that would draw blank.
     *
     * @param array $data
     * @param array $files
     * @return array errors keyed by element name
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $count = (int) ($data['faqcount'] ?? 0);
        for ($i = 0; $i < $count; $i++) {
            if (!empty($data['faqdelete'][$i])) {
                continue;
            }

            $asked = false;
            $answered = false;
            foreach (staticpages::langs() as $lang) {
                $asked = $asked || trim((string) ($data['faqquestion' . $lang][$i] ?? '')) !== '';
                $answered = $answered
                    || staticpages::has_text((string) ($data['faqanswer' . $lang][$i]['text'] ?? ''));
            }

            if ($answered && !$asked) {
                $first = staticpages::langs()[0];
                $errors['faqquestion' . $first . '[' . $i . ']'] =
                    get_string('faqnoquestion', 'local_profilefields');
            }
        }

        return $errors;
    }

    /**
     * The answer editors' options.
     *
     * `maxfiles => 0` on purpose: an answer is a paragraph and a link, and a file
     * area per question would need an itemid, a pluginfile route and a cleanup path
     * for a picture nobody has asked to put in one. Links to images already on the
     * site still work.
     *
     * @return array
     */
    public static function answer_options(): array {
        return [
            'maxfiles'  => 0,
            'noclean'   => true,
            'context'   => \context_system::instance(),
            'trusttext' => false,
        ];
    }

    /**
     * Load the stored questions into the form.
     *
     * @param array $items rows of {local_profilefields_faq}, in display order
     * @return void
     */
    public function load(array $items): void {
        $data = [];
        $index = 0;

        foreach ($items as $item) {
            $data['faqid[' . $index . ']'] = (int) $item->id;
            $data['faqsortorder[' . $index . ']'] = (int) $item->sortorder;
            $data['faqvisible[' . $index . ']'] = (int) $item->visible;

            foreach (staticpages::langs() as $lang) {
                $data['faqquestion' . $lang . '[' . $index . ']'] = (string) $item->{'question' . $lang};
                $data['faqanswer' . $lang . '[' . $index . ']'] = [
                    'text'   => (string) $item->{'answer' . $lang},
                    'format' => (int) $item->answerformat,
                ];
            }

            $index++;
        }

        // The spare row at the bottom gets the next number in the sequence, so a
        // question added without touching the field lands at the end rather than
        // jumping to the top on a sortorder of 0.
        $data['faqsortorder[' . $index . ']'] = ($index + 1) * faq::SORTSTEP;

        $this->set_data($data);
    }

    /**
     * Turn what the form submitted into the list to store.
     *
     * A row that is empty in every language is not a question - it is one of the
     * spare rows at the bottom of the form - so it is dropped rather than saved as
     * a blank entry that would then need deleting.
     *
     * @param \stdClass $data from get_data()
     * @return array the items, in the shape faq::save_all() takes
     */
    public static function extract(\stdClass $data): array {
        $items = [];
        $count = (int) ($data->faqcount ?? 0);
        $langs = staticpages::langs();

        for ($i = 0; $i < $count; $i++) {
            if (!empty($data->faqdelete[$i])) {
                continue;
            }

            $item = [
                'id'           => (int) ($data->faqid[$i] ?? 0),
                'sortorder'    => (int) ($data->faqsortorder[$i] ?? 0),
                'visible'      => empty($data->faqvisible[$i]) ? 0 : 1,
                'answerformat' => FORMAT_HTML,
            ];

            $empty = true;
            foreach ($langs as $lang) {
                $question = trim((string) ($data->{'faqquestion' . $lang}[$i] ?? ''));
                $answer = (string) ($data->{'faqanswer' . $lang}[$i]['text'] ?? '');

                $item['question' . $lang] = $question;
                $item['answer' . $lang] = staticpages::has_text($answer) ? $answer : '';

                $empty = $empty && $question === '' && $item['answer' . $lang] === '';
            }

            if ($empty) {
                continue;
            }

            $item['answerformat'] = (int) ($data->{'faqanswer' . $langs[0]}[$i]['format'] ?? FORMAT_HTML);
            $items[] = $item;
        }

        // Renumber in the order the questions will be shown, so the gaps stay even
        // however the administrator typed the numbers.
        usort($items, static function (array $a, array $b): int {
            return $a['sortorder'] <=> $b['sortorder'];
        });
        foreach ($items as $index => $unused) {
            $items[$index]['sortorder'] = ($index + 1) * faq::SORTSTEP;
        }

        return $items;
    }
}
