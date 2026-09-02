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

use local_profilefields\staticpages;
use moodle_url;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Editing one static page: its name and its body, once per language.
 *
 * A moodleform rather than the hand-built tables the other tabs use, because this is
 * the one tab whose fields are rich text. An editor element brings the toolbar, the
 * draft file area and the image upload with it; reproducing that against a plain
 * textarea means re-implementing the file-picker wiring by hand, and getting it
 * subtly wrong is how embedded images end up pointing at a draft area that has been
 * cleaned away.
 *
 * The page kinds differ only at the edges, so they share this form rather than each
 * having their own:
 *
 * - a policy page adds the document chooser, one per language, and its body becomes
 *   a fallback for while the documents are still being written;
 * - the Contact page adds the map, and says out loud that the address, phone and
 *   social links are the footer's;
 * - the FAQ page's body is the paragraph above the questions - the questions
 *   themselves are their own form ({@see faq_form}).
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class staticpage_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $slug = (string) $this->_customdata['slug'];
        $kind = staticpages::kind($slug);

        $mform->addElement('hidden', 'tab', 'page' . $slug);
        $mform->setType('tab', PARAM_ALPHA);
        $mform->addElement('hidden', 'slug', $slug);
        $mform->setType('slug', PARAM_ALPHA);

        $mform->addElement('advcheckbox', 'enabled', get_string('staticpageenabled', 'local_profilefields'),
            get_string('staticpageenabled_label', 'local_profilefields'));
        $mform->addHelpButton('enabled', 'staticpageenabled', 'local_profilefields');

        if ($kind === staticpages::KIND_POLICY) {
            $this->add_policy_choosers($slug);
        }

        foreach (staticpages::langs() as $lang) {
            $langname = get_string('lang' . $lang, 'local_profilefields');

            $mform->addElement('header', 'lang' . $lang . 'header',
                get_string('staticpagelangsection', 'local_profilefields', $langname));
            $mform->setExpanded('lang' . $lang . 'header', true);

            $mform->addElement('text', 'title_' . $lang,
                get_string('staticpagetitle', 'local_profilefields', $langname),
                ['size' => 60, 'dir' => $lang === 'ar' ? 'rtl' : 'ltr']);
            $mform->setType('title_' . $lang, PARAM_TEXT);
            $mform->addRule('title_' . $lang, get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $mform->addElement('static', 'titlehint_' . $lang, '',
                get_string('staticpagetitle_desc', 'local_profilefields',
                    staticpages::default_title($slug, $lang)));

            $label = $kind === staticpages::KIND_POLICY
                ? get_string('staticpagefallback', 'local_profilefields', $langname)
                : get_string('staticpagecontent', 'local_profilefields', $langname);

            $mform->addElement('editor', 'content_' . $lang, $label, ['rows' => 18],
                $this->editor_options());
            $mform->setType('content_' . $lang, PARAM_RAW);
        }

        if ($kind === staticpages::KIND_CONTACT) {
            $this->add_contact_section();
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * The document chooser for a legal page, one per language.
     *
     * @param string $slug
     * @return void
     */
    protected function add_policy_choosers(string $slug): void {
        $mform = $this->_form;
        $choices = staticpages::policy_choices();

        $mform->addElement('header', 'policyheader', get_string('staticpagepolicysection', 'local_profilefields'));
        $mform->setExpanded('policyheader', true);

        $manageurl = new moodle_url('/admin/tool/policy/managedocs.php');
        $mform->addElement('static', 'policyintro', '',
            get_string('staticpagepolicysection_desc', 'local_profilefields', $manageurl->out(false)));

        if (empty($choices)) {
            $mform->addElement('static', 'policynone', '',
                \html_writer::div(get_string('staticpagepolicyempty', 'local_profilefields'), 'alert alert-warning'));
            return;
        }

        $options = [0 => get_string('staticpagepolicynone', 'local_profilefields')] + $choices;

        foreach (staticpages::langs() as $lang) {
            $mform->addElement('select', 'policy_' . $lang,
                get_string('staticpagepolicy', 'local_profilefields',
                    get_string('lang' . $lang, 'local_profilefields')),
                $options);
            $mform->setType('policy_' . $lang, PARAM_INT);
        }
    }

    /**
     * The Contact page's map, and where its other details actually come from.
     *
     * @return void
     */
    protected function add_contact_section(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'contactheader', get_string('staticpagecontactsection', 'local_profilefields'));
        $mform->setExpanded('contactheader', true);

        $footerurl = new moodle_url('/local/profilefields/manage.php', ['tab' => 'footer']);
        $mform->addElement('static', 'contactnote', '',
            get_string('staticpagecontactnote', 'local_profilefields', $footerurl->out(false)));

        $mform->addElement('text', 'mapembed', get_string('staticpagemapembed', 'local_profilefields'),
            ['size' => 80, 'dir' => 'ltr', 'placeholder' => 'https://www.google.com/maps/embed?pb=...']);
        $mform->setType('mapembed', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('mapembed', 'staticpagemapembed', 'local_profilefields');

        $mform->addElement('text', 'maplink', get_string('staticpagemaplink', 'local_profilefields'),
            ['size' => 80, 'dir' => 'ltr', 'placeholder' => 'https://maps.app.goo.gl/...']);
        $mform->setType('maplink', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('maplink', 'staticpagemaplink', 'local_profilefields');
    }

    /**
     * Reject a map embed that is not an embeddable map, and a map link that is not a URL.
     *
     * The embed URL is dropped straight into an iframe src, so it is checked here
     * rather than at render time: an administrator who pasted the whole `<iframe>`
     * tag, or a page URL that Google refuses to frame, should be told on the form
     * instead of finding a blank rectangle on the page.
     *
     * @param array $data
     * @param array $files
     * @return array errors keyed by element name
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['mapembed'])) {
            $embed = trim((string) $data['mapembed']);
            if (stripos($embed, '<iframe') !== false) {
                $errors['mapembed'] = get_string('staticpagemapembediframe', 'local_profilefields');
            } else if (!preg_match('~^https://~i', $embed) || clean_param($embed, PARAM_URL) === '') {
                $errors['mapembed'] = get_string('staticpagemapembedinvalid', 'local_profilefields');
            }
        }

        if (!empty($data['maplink']) && clean_param(trim((string) $data['maplink']), PARAM_URL) === '') {
            $errors['maplink'] = get_string('staticpagemaplinkinvalid', 'local_profilefields');
        }

        return $errors;
    }

    /**
     * The editor's file options - shared by the form and by whoever saves it.
     *
     * @return array
     */
    public static function editor_options(): array {
        global $CFG;

        // No `return_types`: the constants behind it (FILE_INTERNAL and friends)
        // live in repository/lib.php, which is not loaded on every request, and
        // these options are also read from the public page and from a web service.
        // The editor element's own default already allows an uploaded file and a
        // linked one.
        return [
            'subdirs'   => 0,
            'maxbytes'  => $CFG->maxbytes,
            'maxfiles'  => EDITOR_UNLIMITED_FILES,
            'context'   => \context_system::instance(),
            'trusttext' => false,
            'noclean'   => true,
        ];
    }

    /**
     * Load the stored page into the form.
     *
     * Each language's body is prepared into its own draft area, keyed on the row id
     * of that language - which is why {@see staticpages::ensure_row()} makes the row
     * before anybody has typed anything.
     *
     * @param string $slug
     * @return void
     */
    public function load(string $slug): void {
        $context = \context_system::instance();
        $data = (object) [
            'enabled' => staticpages::enabled($slug) ? 1 : 0,
        ];

        foreach (staticpages::langs() as $lang) {
            $row = staticpages::ensure_row($slug, $lang);

            $data->{'title_' . $lang} = $row->title;

            $draftid = 0;
            $text = file_prepare_draft_area($draftid, $context->id, staticpages::COMPONENT,
                staticpages::FILEAREA, (int) $row->id, self::editor_options(), (string) $row->content);

            $data->{'content_' . $lang} = [
                'text'   => $text,
                'format' => (int) $row->contentformat,
                'itemid' => $draftid,
            ];

            if (staticpages::kind($slug) === staticpages::KIND_POLICY) {
                $data->{'policy_' . $lang} = staticpages::policy_id($slug, $lang);
            }
        }

        if (staticpages::kind($slug) === staticpages::KIND_CONTACT) {
            $data->mapembed = staticpages::contact_setting('mapembed');
            $data->maplink = staticpages::contact_setting('maplink');
        }

        $this->set_data($data);
    }

    /**
     * Write what the form submitted.
     *
     * @param \stdClass $data from get_data()
     * @return void
     */
    public static function save(\stdClass $data): void {
        $slug = (string) $data->slug;
        if (!staticpages::exists($slug)) {
            return;
        }

        $context = \context_system::instance();
        staticpages::set_enabled($slug, !empty($data->enabled));

        foreach (staticpages::langs() as $lang) {
            $row = staticpages::ensure_row($slug, $lang);
            $editor = (array) ($data->{'content_' . $lang} ?? []);

            $text = file_save_draft_area_files((int) ($editor['itemid'] ?? 0), $context->id,
                staticpages::COMPONENT, staticpages::FILEAREA, (int) $row->id,
                self::editor_options(), (string) ($editor['text'] ?? ''));

            staticpages::save_row($slug, $lang,
                trim((string) ($data->{'title_' . $lang} ?? '')),
                $text,
                (int) ($editor['format'] ?? FORMAT_HTML));

            // Only when the chooser was actually on the form. It is left off when
            // the site has no policy document yet, and a save then must not read
            // the missing field as "none" and quietly unmap what was there.
            if (isset($data->{'policy_' . $lang})) {
                staticpages::set_policy_id($slug, $lang, (int) $data->{'policy_' . $lang});
            }
        }

        if (staticpages::kind($slug) === staticpages::KIND_CONTACT) {
            staticpages::set_contact_setting('mapembed', clean_param(trim((string) ($data->mapembed ?? '')), PARAM_URL));
            staticpages::set_contact_setting('maplink', clean_param(trim((string) ($data->maplink ?? '')), PARAM_URL));
        }
    }
}
