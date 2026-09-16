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

use local_profilefields\about;
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

        $isabout = $slug === about::SLUG;
        if ($isabout) {
            $this->add_about_hero_section();
        }

        foreach (staticpages::langs() as $lang) {
            $langname = get_string('lang' . $lang, 'local_profilefields');
            $dir = $lang === 'ar' ? 'rtl' : 'ltr';

            $mform->addElement('header', 'lang' . $lang . 'header',
                get_string('staticpagelangsection', 'local_profilefields', $langname));
            $mform->setExpanded('lang' . $lang . 'header', true);

            $mform->addElement('text', 'title_' . $lang,
                get_string('staticpagetitle', 'local_profilefields', $langname),
                ['size' => 60, 'dir' => $dir]);
            $mform->setType('title_' . $lang, PARAM_TEXT);
            $mform->addRule('title_' . $lang, get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $mform->addElement('static', 'titlehint_' . $lang, '',
                get_string('staticpagetitle_desc', 'local_profilefields',
                    staticpages::default_title($slug, $lang)));

            if ($isabout) {
                // The line above the title and the paragraph under it. Short on
                // purpose: the tagline is one breath, the lede is what a visitor
                // reads before deciding whether to read the rest.
                $mform->addElement('text', 'tagline_' . $lang,
                    get_string('abouttagline', 'local_profilefields', $langname),
                    ['size' => 60, 'dir' => $dir, 'maxlength' => 80]);
                $mform->setType('tagline_' . $lang, PARAM_TEXT);
                $mform->addRule('tagline_' . $lang, get_string('maximumchars', '', 80), 'maxlength', 80, 'client');
                $mform->addHelpButton('tagline_' . $lang, 'abouttagline', 'local_profilefields');

                $mform->addElement('textarea', 'lede_' . $lang,
                    get_string('aboutlede', 'local_profilefields', $langname),
                    ['rows' => 3, 'cols' => 60, 'dir' => $dir, 'maxlength' => 400]);
                $mform->setType('lede_' . $lang, PARAM_TEXT);
                $mform->addRule('lede_' . $lang, get_string('maximumchars', '', 400), 'maxlength', 400, 'client');
                $mform->addHelpButton('lede_' . $lang, 'aboutlede', 'local_profilefields');
            }

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

        if ($isabout) {
            $this->add_about_lists();
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * The About page's picture and film.
     *
     * One of each, shared by both languages. When both are set the page shows the
     * picture with a play button over it and only loads the player when it is
     * pressed - a film that autoplays on an "about us" page is the thing people
     * close the tab over.
     *
     * @return void
     */
    protected function add_about_hero_section(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'aboutheroheader', get_string('abouthero', 'local_profilefields'));
        $mform->setExpanded('aboutheroheader', true);
        $mform->addElement('static', 'aboutherointro', '', get_string('abouthero_desc', 'local_profilefields'));

        $mform->addElement('filemanager', 'heroimage', get_string('aboutheroimage', 'local_profilefields'),
            null, about::hero_file_options());
        $mform->addHelpButton('heroimage', 'aboutheroimage', 'local_profilefields');

        $mform->addElement('text', 'herovideo', get_string('aboutherovideo', 'local_profilefields'),
            ['size' => 80, 'dir' => 'ltr', 'placeholder' => 'https://www.youtube.com/watch?v=...']);
        $mform->setType('herovideo', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('herovideo', 'aboutherovideo', 'local_profilefields');
    }

    /**
     * The About page's three lists - facts, pillars, milestones - as repeating rows.
     *
     * Each row carries both languages, so a fact and its translation cannot be
     * added, removed or reordered apart. The row counts come from what is stored,
     * plus one blank row on an empty list so there is something to type into.
     *
     * @return void
     */
    protected function add_about_lists(): void {
        $mform = $this->_form;
        $stored = about::load();
        $langs = staticpages::langs();

        // ---- Facts: the strip under the picture. Value shared, label per language.
        $mform->addElement('header', 'aboutfactsheader', get_string('aboutfacts', 'local_profilefields'));
        $mform->setExpanded('aboutfactsheader', !empty($stored['facts']));
        $mform->addElement('static', 'aboutfactsintro', '',
            get_string('aboutfacts_desc', 'local_profilefields', about::MAX_FACTS));

        $elements = [
            $mform->createElement('static', 'fact_no', get_string('aboutfactno', 'local_profilefields'), ''),
            $mform->createElement('text', 'fact_value', get_string('aboutfactvalue', 'local_profilefields'),
                ['size' => 16, 'maxlength' => 20]),
        ];
        $options = ['fact_value' => ['type' => PARAM_TEXT]];
        foreach ($langs as $lang) {
            $langname = get_string('lang' . $lang, 'local_profilefields');
            $elements[] = $mform->createElement('text', 'fact_label_' . $lang,
                get_string('aboutfactlabel', 'local_profilefields', $langname),
                ['size' => 40, 'maxlength' => 60, 'dir' => $lang === 'ar' ? 'rtl' : 'ltr']);
            $options['fact_label_' . $lang] = ['type' => PARAM_TEXT];
        }
        $elements[] = $mform->createElement('submit', 'fact_delete', get_string('aboutremoverow', 'local_profilefields'),
            [], false);
        $this->repeat_elements($elements, max(1, count($stored['facts'])), $options, 'fact_repeats', 'fact_add', 1,
            get_string('aboutaddfact', 'local_profilefields'), true, 'fact_delete');

        // ---- Pillars: what the academy stands for. Icon shared, title and text per language.
        $mform->addElement('header', 'aboutpillarsheader', get_string('aboutpillars', 'local_profilefields'));
        $mform->setExpanded('aboutpillarsheader', !empty($stored['pillars']));
        $mform->addElement('static', 'aboutpillarsintro', '',
            get_string('aboutpillars_desc', 'local_profilefields', about::MAX_PILLARS));

        $elements = [
            $mform->createElement('static', 'pillar_no', get_string('aboutpillarno', 'local_profilefields'), ''),
            $mform->createElement('text', 'pillar_icon', get_string('aboutpillaricon', 'local_profilefields'),
                ['size' => 30, 'dir' => 'ltr', 'placeholder' => 'fa-solid fa-gears']),
        ];
        $options = [
            'pillar_icon' => ['type' => PARAM_TEXT, 'helpbutton' => ['aboutpillaricon', 'local_profilefields']],
        ];
        foreach ($langs as $lang) {
            $langname = get_string('lang' . $lang, 'local_profilefields');
            $dir = $lang === 'ar' ? 'rtl' : 'ltr';
            $elements[] = $mform->createElement('text', 'pillar_title_' . $lang,
                get_string('aboutpillartitle', 'local_profilefields', $langname),
                ['size' => 50, 'maxlength' => 80, 'dir' => $dir]);
            $elements[] = $mform->createElement('textarea', 'pillar_text_' . $lang,
                get_string('aboutpillartext', 'local_profilefields', $langname),
                ['rows' => 3, 'cols' => 60, 'maxlength' => 300, 'dir' => $dir]);
            $options['pillar_title_' . $lang] = ['type' => PARAM_TEXT];
            $options['pillar_text_' . $lang] = ['type' => PARAM_TEXT];
        }
        $elements[] = $mform->createElement('submit', 'pillar_delete', get_string('aboutremoverow', 'local_profilefields'),
            [], false);
        $this->repeat_elements($elements, max(1, count($stored['pillars'])), $options, 'pillar_repeats', 'pillar_add', 1,
            get_string('aboutaddpillar', 'local_profilefields'), true, 'pillar_delete');

        // ---- Milestones: the academy's history, one row per year.
        $mform->addElement('header', 'aboutmilestonesheader', get_string('aboutmilestones', 'local_profilefields'));
        $mform->setExpanded('aboutmilestonesheader', !empty($stored['milestones']));
        $mform->addElement('static', 'aboutmilestonesintro', '',
            get_string('aboutmilestones_desc', 'local_profilefields', about::MAX_MILESTONES));

        $elements = [
            $mform->createElement('static', 'milestone_no', get_string('aboutmilestoneno', 'local_profilefields'), ''),
            $mform->createElement('text', 'milestone_year', get_string('aboutmilestoneyear', 'local_profilefields'),
                ['size' => 10, 'maxlength' => 12, 'dir' => 'ltr']),
        ];
        $options = ['milestone_year' => ['type' => PARAM_TEXT]];
        foreach ($langs as $lang) {
            $langname = get_string('lang' . $lang, 'local_profilefields');
            $dir = $lang === 'ar' ? 'rtl' : 'ltr';
            $elements[] = $mform->createElement('text', 'milestone_title_' . $lang,
                get_string('aboutmilestonetitle', 'local_profilefields', $langname),
                ['size' => 50, 'maxlength' => 100, 'dir' => $dir]);
            $elements[] = $mform->createElement('textarea', 'milestone_text_' . $lang,
                get_string('aboutmilestonetext', 'local_profilefields', $langname),
                ['rows' => 2, 'cols' => 60, 'maxlength' => 300, 'dir' => $dir]);
            $options['milestone_title_' . $lang] = ['type' => PARAM_TEXT];
            $options['milestone_text_' . $lang] = ['type' => PARAM_TEXT];
        }
        $elements[] = $mform->createElement('submit', 'milestone_delete',
            get_string('aboutremoverow', 'local_profilefields'), [], false);
        $this->repeat_elements($elements, max(1, count($stored['milestones'])), $options, 'milestone_repeats',
            'milestone_add', 1, get_string('aboutaddmilestone', 'local_profilefields'), true, 'milestone_delete');
    }

    /**
     * Read one of the About lists back out of a submission.
     *
     * A row the administrator pressed "Remove" on is simply absent from the posted
     * arrays (repeat_elements leaves only its hidden marker), so the rows are
     * walked by the indexes that were actually posted.
     *
     * @param \stdClass $data from get_data()
     * @param string $prefix fact|pillar|milestone
     * @param string $shared the field that is the same in every language
     * @param string[] $translated the fields that exist once per language
     * @return array rows in the shape {@see about::defaults()} lists use
     */
    protected static function about_rows(\stdClass $data, string $prefix, string $shared, array $translated): array {
        $values = (array) ($data->{$prefix . '_' . $shared} ?? []);
        $indexes = array_keys($values);
        foreach ($translated as $field) {
            foreach (staticpages::langs() as $lang) {
                $indexes = array_merge($indexes, array_keys((array) ($data->{$prefix . '_' . $field . '_' . $lang} ?? [])));
            }
        }
        $indexes = array_unique($indexes);
        sort($indexes);

        $rows = [];
        foreach ($indexes as $i) {
            $row = [$shared => (string) ($values[$i] ?? '')];
            foreach ($translated as $field) {
                $row[$field] = [];
                foreach (staticpages::langs() as $lang) {
                    $posted = (array) ($data->{$prefix . '_' . $field . '_' . $lang} ?? []);
                    $row[$field][$lang] = (string) ($posted[$i] ?? '');
                }
            }
            $rows[] = $row;
        }

        return $rows;
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

        // The film address is dropped into a player at render time, so it is
        // checked here: an address the page cannot play should be refused on the
        // form, not discovered as an empty frame on the page.
        if (!empty($data['herovideo'])) {
            $video = about::video((string) $data['herovideo']);
            if ($video['provider'] === '') {
                $errors['herovideo'] = get_string('aboutherovideoinvalid', 'local_profilefields');
            }
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

        if ($slug === about::SLUG) {
            $this->load_about($data);
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

        if ($slug === about::SLUG) {
            self::save_about($data);
        }
    }

    /**
     * Put the About page's extras into the form's defaults.
     *
     * @param \stdClass $data the defaults being assembled by {@see load()}
     * @return void
     */
    protected function load_about(\stdClass $data): void {
        $stored = about::load();

        foreach (staticpages::langs() as $lang) {
            $data->{'tagline_' . $lang} = $stored['tagline'][$lang];
            $data->{'lede_' . $lang} = $stored['lede'][$lang];
        }
        $data->herovideo = $stored['video'];

        $draftid = 0;
        file_prepare_draft_area($draftid, \context_system::instance()->id, staticpages::COMPONENT,
            about::HERO_FILEAREA, 0, about::hero_file_options());
        $data->heroimage = $draftid;

        foreach ($stored['facts'] as $i => $fact) {
            $data->fact_value[$i] = $fact['value'];
            foreach (staticpages::langs() as $lang) {
                $data->{'fact_label_' . $lang}[$i] = $fact['label'][$lang];
            }
        }
        foreach ($stored['pillars'] as $i => $pillar) {
            $data->pillar_icon[$i] = $pillar['icon'];
            foreach (staticpages::langs() as $lang) {
                $data->{'pillar_title_' . $lang}[$i] = $pillar['title'][$lang];
                $data->{'pillar_text_' . $lang}[$i] = $pillar['text'][$lang];
            }
        }
        foreach ($stored['milestones'] as $i => $milestone) {
            $data->milestone_year[$i] = $milestone['year'];
            foreach (staticpages::langs() as $lang) {
                $data->{'milestone_title_' . $lang}[$i] = $milestone['title'][$lang];
                $data->{'milestone_text_' . $lang}[$i] = $milestone['text'][$lang];
            }
        }
    }

    /**
     * Write the About page's extras.
     *
     * @param \stdClass $data from get_data()
     * @return void
     */
    protected static function save_about(\stdClass $data): void {
        $sections = about::defaults();

        foreach (staticpages::langs() as $lang) {
            $sections['tagline'][$lang] = (string) ($data->{'tagline_' . $lang} ?? '');
            $sections['lede'][$lang] = (string) ($data->{'lede_' . $lang} ?? '');
        }
        $sections['video'] = clean_param(trim((string) ($data->herovideo ?? '')), PARAM_URL);
        $sections['facts'] = self::about_rows($data, 'fact', 'value', ['label']);
        $sections['pillars'] = self::about_rows($data, 'pillar', 'icon', ['title', 'text']);
        $sections['milestones'] = self::about_rows($data, 'milestone', 'year', ['title', 'text']);

        about::save($sections);

        if (isset($data->heroimage)) {
            file_save_draft_area_files((int) $data->heroimage, \context_system::instance()->id,
                staticpages::COMPONENT, about::HERO_FILEAREA, 0, about::hero_file_options());
        }
    }
}
