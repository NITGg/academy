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
 * NIT topics-format renderer — branded course-detail landing page.
 *
 * On the multi-section course landing page (not editing) this replaces Moodle's
 * stock course body with the "courses / المقرر" screen of the academy design
 * (Figma D5hrDRS4jNbQ3JrerUI9fp, node 231:210), styled entirely from the
 * theme_nit Brand Colors palette (see scss/components/_coursepage.scss):
 *
 *   1. a hero banner — the course image under a dark scrim, carrying the top
 *      category, title, subject line, a summary excerpt, a row of fact chips
 *      (certificate, duration, level, start date, enrolled) and ONE button.
 *      A course with a promo video (local_nit_media's field under "Course
 *      image") also carries a play button over the picture; pressing it turns
 *      the banner into the player and fades the text out — see acad_hero_promo();
 *   2. a two-column body — the main column (inline-start) holds the "About this
 *      course" card (summary, "What will you learn?" tiles, skills), the
 *      requirements card and the curriculum accordion; the aside holds the
 *      "at a glance" facts and the instructors / "offered by" card.
 *
 * Everything on the page is DRIVEN BY REAL COURSE DATA — there are no static
 * placeholder values. The bands read:
 *   - the course record (name, summary, start date, image),
 *   - the section / activity tree (modules accordion, counts, assessments),
 *   - enrolled teachers (instructors), enrolment count, category chain, tags, and
 *   - the course custom fields under the "Other fields" category, mapped by
 *     shortname:
 *        course_fields ............. hero subject line + "Skills you'll gain" chips
 *        total_number_of_hours ..... "Duration" chip + glance row
 *        language .................. "Language" row in the at-a-glance card
 *        target_audience ........... "Who this course is for" (requirements card)
 *        prerequisites ............. "Prerequisites" (requirements card)
 *        ilos ...................... "What will you learn?" › intended learning outcomes
 *        by_the_end_of_training .... "What will you learn?" › by the end of this program
 *        level (dropdown) .......... "Level" chip + glance row
 *   - and two facts the SYSTEM decides, which used to be hand-ticked checkbox
 *     custom fields ("Free", "Certificate") until 2026-09 and are now read from
 *     the plugin that actually owns each — see {@see acad_gather()}:
 *        free .......... hero "Free" chip — no active price rule in local_payments
 *        certificate ... hero chip + glance row — the course contains a
 *                        certificate activity (local_academy\certificates_api)
 *
 * A field (or whole card) that has no value is simply NOT rendered — nothing is
 * ever shown as an em-dash. Short-text fields authored in the bilingual
 * "{mlang en}…{mlang}{mlang ar}…{mlang}" convention are resolved to the current
 * language via {@see acad_ml()} (the site multilang filter runs first; we fall
 * back to resolving the tags ourselves so the page is correct even where the
 * {mlang} filter is not installed). A field may also hold several items in one
 * value separated by a pipe "|" (or a newline / bullet), which become individual
 * chips — see {@see acad_chips()}.
 *
 * Two things the design carries that this page does NOT: the "like" and "share"
 * buttons beside the hero CTA (no such feature on the site), and the site
 * navbar / footer, which are the theme's and not this renderer's to draw.
 *
 * In editing mode (and on single-section views) we defer to the parent renderer
 * so teachers keep the normal drag/drop management UI.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_nit\output;

use renderable;
use stdClass;
use context_course;
use moodle_url;
use html_writer;
use user_picture;
use core_course_category;
use core_tag_tag;
use core_courseformat\output\local\content;

defined('MOODLE_INTERNAL') || die();

/**
 * Branded course-detail renderer for the topics format.
 */
class format_topics_renderer extends \format_topics\output\renderer {

    /**
     * Canonical delimiter for multi-value short-text custom fields.
     *
     * Single source of truth for the "chips" contract: the course-edit chips
     * editor (see {@see \local_nit_core\hook\output_callbacks::add_course_edit_chips()})
     * joins entries with this token, and {@see acad_chips()} splits on it (a bare
     * pipe is one of the separators the split regex recognises), so a field edited
     * as chips reads back as a chip list here.
     */
    const CHIP_SEP = '|';

    /**
     * Longest summary excerpt the hero shows, in characters.
     *
     * The design's hero carries two or three lines of description under the
     * title; anything longer is shown in full in the "About this course" card.
     */
    const LEAD_CHARS = 220;

    /**
     * Icons the "What will you learn?" tiles cycle through, in order.
     *
     * The design draws four tiles, each with its own pictogram (a pen, a
     * translation card, a hand with a pen, a lit bulb). The tiles here are as
     * many as the course has outcomes, so the glyphs repeat in this order.
     */
    const TILE_ICONS = ['bulb', 'pen', 'book', 'chat'];

    /**
     * Intercept the course content renderable.
     *
     * On the multi-section landing page (not editing, not a single-section view)
     * we replace Moodle's course body with the branded detail layout. Everything
     * else falls through to the stock format renderer.
     *
     * @param renderable $widget instance with renderable interface
     * @return string the widget HTML
     */
    public function render(renderable $widget) {
        if ($widget instanceof content && !$this->page->user_is_editing()) {
            $format = course_get_format($this->page->course);
            // get_sectionid() is null on the "all sections" landing page.
            if (!$format->get_sectionid()) {
                return $this->acad_render_page($format);
            }
        }
        return parent::render($widget);
    }

    /**
     * Build the full branded page for the given course format.
     *
     * Hero first, then the two-column body. Each card returns '' when it has no
     * data, so a column simply shortens instead of showing an empty box.
     *
     * The category's palette is NOT re-applied here: theme_nit_mode_classes()
     * already puts the group's switch class on the <html> element for every page
     * in a styled category, this one included. A second copy on this wrapper
     * would be a copy pinned to the mode the page was rendered in — and the
     * navbar light/dark button, which moves between the category's own two
     * styles, would leave it behind.
     *
     * @param \core_courseformat\base $format the course format
     * @return string HTML
     */
    protected function acad_render_page($format): string {
        $course  = $format->get_course();
        $modinfo = get_fast_modinfo($course);
        $context = context_course::instance($course->id);

        $data = $this->acad_gather($course, $modinfo, $context);

        // Main column (inline-start): about, requirements, curriculum.
        $main  = $this->acad_about($data);
        $main .= $this->acad_requirements($data);
        $main .= $this->acad_modules($course, $modinfo, $context, $data);

        // Aside (inline-end): the facts card, then the people behind the course.
        $aside  = $this->acad_glance($data);
        $aside .= $this->acad_people($course, $data);

        $o  = html_writer::start_div('acad-cr');
        $o .= html_writer::div($this->acad_hero($course, $modinfo, $context, $data), 'acad-cr__wrap');
        // A course with nothing for the aside (no sections, no teacher, no
        // category) gives the main column the whole width rather than a gap.
        $o .= html_writer::div(
            html_writer::div($main, 'acad-cr__main') .
            ($aside !== '' ? html_writer::tag('aside', $aside, ['class' => 'acad-cr__aside']) : ''),
            'acad-cr__wrap acad-cr__grid' . ($aside === '' ? ' acad-cr__grid--single' : ''));
        $o .= html_writer::end_div();

        // Accordion helper. A format renderer runs after <head> is flushed, so
        // $PAGE->requires->js() would be dropped; emit a plain inline <script>.
        $o .= html_writer::script($this->acad_inline_js());

        return $o;
    }

    // =========================================================================
    // Data gathering — one pass, so each band just reads $data.
    // =========================================================================

    /**
     * Collect everything the bands need in a single pass.
     *
     * @param stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @return stdClass
     */
    protected function acad_gather($course, $modinfo, $context) {
        global $DB;

        $d = new stdClass();
        $d->course   = $course;
        $d->context  = $context;
        $d->enrolled = count_enrolled_users($context);

        // Category chain. get_parents() is already top-down — [top, …, immediate
        // parent] — so $catnames[0] is always the TOP-level category, which is
        // what the hero's provider line and the "Offered by" row both mean.
        $d->catnames = [];
        if ($course->category) {
            $cat = core_course_category::get($course->category, IGNORE_MISSING);
            if ($cat) {
                foreach ($cat->get_parents() as $pid) {
                    $p = $DB->get_record('course_categories', ['id' => $pid], 'name');
                    if ($p) {
                        $d->catnames[] = format_string($p->name);
                    }
                }
                $d->catnames[] = format_string($cat->get_formatted_name());
            }
        }

        // Visible sections (become the module accordion rows).
        $numsections   = course_get_format($course)->get_last_section_number();
        $d->modulerows = [];
        $d->modcount   = 0;
        $d->assesscount = 0;
        foreach ($modinfo->get_section_info_all() as $snum => $sec) {
            if ($snum === 0) {
                if (empty($modinfo->sections[0]) || !$sec->uservisible) {
                    continue;
                }
            }
            if ($snum > $numsections) {
                continue;
            }
            $show = $sec->uservisible ||
                ($sec->visible && !$sec->available && !empty($sec->availableinfo)) ||
                (!$sec->visible && !$course->hiddensections);
            if (!$show) {
                continue;
            }
            $d->modulerows[$snum] = $sec;
            $d->modcount++;
        }

        // Count graded assessment activities (real "Assessments" figure).
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (in_array($cm->modname, ['assign', 'quiz', 'workshop', 'lesson'], true)) {
                $d->assesscount++;
            }
        }

        // Teachers (Instructors).
        $d->teachers = $this->acad_teachers($context);

        // Skills fall back to course tags when the course_fields custom field is empty.
        $d->tags = core_tag_tag::get_item_tags('core', 'course', $course->id);

        // Course image.
        $d->image = $this->acad_course_image_url($context);

        // Course promo video — the "Course promo video" field local_nit_media adds
        // under "Course image" on the settings form. null when the course has
        // none, in which case the hero is the picture alone, as before.
        $d->promo = class_exists('\local_nit_media\promo_video')
            ? \local_nit_media\promo_video::for_course((int) $course->id) : null;

        // Start date.
        $d->startlabel = $course->startdate ? userdate($course->startdate, get_string('strftimedatefullshort')) : '';

        // The summary, twice: as formatted HTML for the "About" card, and as a
        // plain-text excerpt for the hero. The excerpt is what the design puts
        // under the title; when it had to be cut, the card shows the whole thing.
        $d->summary = format_text($course->summary, $course->summaryformat, ['context' => $context]);
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($d->summary)));
        if ($plain === '') {
            $d->summary = '';
        }
        $d->lead = ($plain === '') ? '' : shorten_text($plain, self::LEAD_CHARS);
        $d->leadtruncated = ($d->lead !== $plain);

        // Course custom fields under "Other fields", keyed by shortname, respecting
        // visibility. Hidden / teacher-only fields are dropped for public viewers.
        $d->cf = [];
        $canviewhidden = has_capability('moodle/course:update', $context);
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($course->id, true) as $fd) {
                $field = $fd->get_field();
                $vis   = (int) $field->get_configdata_property('visibility');
                // 0 = nobody, 1 = teachers, 2 = everyone.
                if ($vis < 2 && !$canviewhidden) {
                    continue;
                }
                $d->cf[$field->get('shortname')] = (object) [
                    'type'   => $field->get('type'),
                    'raw'    => $fd->get_value(),
                    // Human-readable form: for a select this is the option label,
                    // because get_value() only holds the option's index.
                    'export' => $fd->export_value(),
                ];
            }
        } catch (\Throwable $e) {
            $d->cf = [];
        }

        // Two facts nobody should have to tick. Both used to be checkbox custom
        // fields, and each said whatever the last editor remembered: a course
        // whose prices were removed kept selling, a course that gained a
        // certificate activity advertised none. Now each is read from the plugin
        // that decides it, so this page cannot disagree with the checkout or
        // with the curriculum below it:
        //   - free: no active price rule — the same has_pricing() test enrol.php,
        //     buy.php, the catalogue cards and the app's is_course_free gate on;
        //   - certificate: the course contains a (visible) certificate activity,
        //     asked through local_academy so the catalogue's facet and this page
        //     give one answer.
        // Without the plugin that owns a fact, the fact is simply not claimed.
        $d->isfree = class_exists('\local_payments\price_resolver')
            && !\local_payments\price_resolver::has_pricing((int) $course->id);
        $d->hascertificate = class_exists('\local_academy\certificates_api')
            && \local_academy\certificates_api::course_has_certificate((int) $course->id);

        return $d;
    }

    /**
     * A count rendered with the correct singular / plural language string.
     *
     * @param int $n
     * @param string $onekey singular string key
     * @param string $manykey plural string key
     * @return string
     */
    protected function acad_count($n, $onekey, $manykey): string {
        return get_string(((int) $n === 1) ? $onekey : $manykey, 'theme_nit', $n);
    }

    /**
     * Enrolled teachers for the "Instructors" slots.
     *
     * @param \context_course $context
     * @return array
     */
    protected function acad_teachers($context) {
        $out   = [];
        $roles = get_archetype_roles('editingteacher') + get_archetype_roles('teacher');
        if (empty($roles)) {
            return $out;
        }
        $fields = 'u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt, u.firstnamephonetic,
                   u.lastnamephonetic, u.middlename, u.alternatename';
        $users = get_role_users(array_keys($roles), $context, false, $fields);
        $seen  = [];
        foreach ($users as $u) {
            if (isset($seen[$u->id])) {
                continue;
            }
            $seen[$u->id] = true;
            $out[] = $u;
        }
        return $out;
    }

    // =========================================================================
    // Custom-field helpers
    // =========================================================================

    /**
     * Resolve a bilingual "{mlang}" value to the current language as plain text.
     *
     * The site's multilang filter (if installed) runs first via format_string();
     * if {mlang} tags survive we resolve them ourselves so the page is correct
     * even where the {mlang} filter is not present. Returns '' for empty input.
     *
     * @param string|null $raw
     * @param stdClass $data
     * @return string
     */
    protected function acad_ml($raw, $data): string {
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }
        $out = format_string($raw, true, ['context' => $data->context]);
        if (stripos($out, '{mlang') === false) {
            return trim($out);
        }
        return trim($this->acad_resolve_mlang($out));
    }

    /**
     * Resolve {mlang XX}…{mlang} blocks for the current language.
     *
     * Blocks whose language list contains the current language win; otherwise
     * "other" blocks are used; failing both, the first block is shown so a value
     * is never lost. Mirrors filter_multilang2 selection behaviour.
     *
     * @param string $text
     * @return string
     */
    protected function acad_resolve_mlang($text): string {
        $lang = current_language();
        $pattern = '/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is';
        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return $text;
        }
        $matched = '';
        $other   = '';
        $first   = null;
        foreach ($matches as $block) {
            $langs   = array_map('trim', explode(',', strtolower($block[1])));
            $content = $block[2];
            if ($first === null) {
                $first = $content;
            }
            if (in_array($lang, $langs, true)) {
                $matched .= $content;
            }
            if (in_array('other', $langs, true)) {
                $other .= $content;
            }
        }
        if ($matched !== '') {
            return $matched;
        }
        if ($other !== '') {
            return $other;
        }
        return $first ?? '';
    }

    /**
     * A single short-text custom field, resolved to plain text ('' when absent).
     *
     * @param string $shortname
     * @param stdClass $data
     * @return string
     */
    protected function acad_cf_text($shortname, $data): string {
        if (!isset($data->cf[$shortname])) {
            return '';
        }
        return $this->acad_ml($data->cf[$shortname]->raw, $data);
    }

    /**
     * A dropdown custom field as its option label ('' when absent or unset).
     *
     * A select stores the option's index, so get_value() would give "2" rather
     * than "Level 2" — the exported value carries the label, already run through
     * format_string() by customfield_select. Any {mlang} left in it is resolved
     * here so the label follows the current language.
     *
     * @param string $shortname
     * @param stdClass $data
     * @return string
     */
    protected function acad_cf_select($shortname, $data): string {
        if (!isset($data->cf[$shortname])) {
            return '';
        }
        $label = $data->cf[$shortname]->export ?? null;
        if (!is_string($label) || trim($label) === '') {
            return '';
        }
        if (stripos($label, '{mlang') !== false) {
            $label = $this->acad_resolve_mlang($label);
        }
        return trim($label);
    }

    /**
     * A number custom field as a trimmed display string ('' when absent/zero-empty).
     *
     * @param string $shortname
     * @param stdClass $data
     * @return string
     */
    protected function acad_cf_number($shortname, $data): string {
        if (!isset($data->cf[$shortname])) {
            return '';
        }
        $val = $data->cf[$shortname]->raw;
        if ($val === null || $val === '' || (is_numeric($val) && (float) $val == 0.0)) {
            return '';
        }
        // Whole numbers show without the ".0" the number field stores; keep any
        // genuine decimal part.
        $f = (float) $val;
        return ($f == (int) $f) ? (string) (int) $f : rtrim(rtrim((string) $f, '0'), '.');
    }

    /**
     * Split one short-text value into a clean list of chips.
     *
     * Resolves {mlang} first, then splits on explicit list separators — pipe "|",
     * newline, and the bullet "•". When $listsep is true the value is also split
     * on commas (Arabic "،" included), which suits keyword/tag style fields such
     * as course_fields. Returns [] for empty input.
     *
     * @param string $shortname
     * @param stdClass $data
     * @param bool $listsep also split on commas
     * @return string[]
     */
    protected function acad_chips($shortname, $data, bool $listsep = false): array {
        $text = $this->acad_cf_text($shortname, $data);
        if ($text === '') {
            return [];
        }
        $sep = $listsep ? '/[|\n•،,]+/u' : '/[|\n•]+/u';
        $parts = preg_split($sep, $text);
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, function ($p) {
            return $p !== '';
        }));
    }

    /**
     * The "Duration" figure as a ready-made string ('' when the field is empty).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_hours_label($data): string {
        $hours = $this->acad_cf_number('total_number_of_hours', $data);
        if ($hours === '') {
            return '';
        }
        return get_string(($hours === '1') ? 'acad_nhour' : 'acad_nhours', 'theme_nit', s($hours));
    }

    // =========================================================================
    // Hero
    // =========================================================================

    /**
     * Hero banner: the course image under a dark scrim, with the course identity
     * at the inline-start side (the design's right-hand column on the Arabic
     * screen), a row of fact chips and one call to action.
     *
     * Which button that is depends on who is looking. A reader who can open the
     * course gets "Go to course", which takes them into the first activity they
     * can open (the design's "الذهاب الي الكورس"); a visitor who cannot gets the
     * price and the buy / enrol button — see {@see acad_offer()}.
     *
     * @param stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_hero($course, $modinfo, $context, $data) {
        global $USER;

        // "Has access", not "is enrolled". A teacher, manager or administrator
        // reads the course through moodle/course:view and is never enrolled in
        // it — offering them the price and a "Buy now" button would be asking
        // them to pay for something they can already open. The same capability
        // local_payments' own gate lets through (hook_callbacks), so the button
        // is shown exactly to the people the checkout would accept.
        $hasaccess = is_enrolled($context, $USER->id, '', true)
            || has_capability('moodle/course:view', $context);

        // Provider = top-level category.
        $provider = !empty($data->catnames) ? $data->catnames[0] : format_string($course->shortname);

        $attrs = ['class' => 'acad-cr__hero'];
        if ($data->image) {
            $attrs['class'] .= ' has-image';
            // The image is a custom property so the stylesheet owns how it is
            // drawn (cover, scrim, fallback) and the markup only says which one.
            $attrs['style'] = '--cr-hero-img:url("' . $data->image->out(false) . '")';
        }
        // A promo video turns the hero into a player on demand: the play button
        // sits over the picture, and a click fades the text out and the video in
        // (AcademyUI.crPromoPlay). The player itself is NOT in the markup — an
        // iframe or <video> would start fetching on page load — only its address
        // and kind, which the script reads when the button is pressed.
        if (!empty($data->promo)) {
            $attrs['class'] .= ' has-video';
            $attrs['data-promo-src'] = $data->promo->src;
            $attrs['data-promo-player'] = $data->promo->player;
            if ($data->image) {
                $attrs['data-promo-poster'] = $data->image->out(false);
            }
        }
        $o  = html_writer::start_tag('section', $attrs);
        $o .= html_writer::start_div('acad-cr__hero-body');

        $o .= html_writer::div($provider, 'acad-cr__provider');
        $o .= html_writer::tag('h1', format_string($course->fullname), ['class' => 'acad-cr__title']);

        // Subject line from course_fields (real), else nothing. Already HTML-safe.
        $subject = $this->acad_cf_text('course_fields', $data);
        if ($subject !== '') {
            $o .= html_writer::tag('p', $subject, ['class' => 'acad-cr__subtitle']);
        }

        // Summary excerpt. Tags were stripped from format_text() output, so what
        // is left is text with its entities intact — safe to print as is.
        if ($data->lead !== '') {
            $o .= html_writer::tag('p', $data->lead, ['class' => 'acad-cr__lead']);
        }

        $o .= $this->acad_hero_chips($data);

        $o .= html_writer::start_div('acad-cr__hero-actions');
        if ($hasaccess) {
            // Into the first lesson the reader can open; failing that (a course
            // with no openable activity yet), down to the curriculum.
            $target = $this->acad_first_activity_url($modinfo, $data) ?? '#modules';
            $o .= html_writer::link($target, s(get_string('acad_gotocourse', 'theme_nit')),
                ['class' => 'btn btn-primary acad-cr-btn']);
        } else {
            $o .= $this->acad_offer($course);
        }
        $o .= html_writer::end_div(); // hero-actions.

        $o .= html_writer::end_div(); // hero-body.

        if (!empty($data->promo)) {
            $o .= $this->acad_hero_promo();
        }

        $o .= html_writer::end_tag('section');
        return $o;
    }

    /**
     * The promo video controls of the hero: the play button and the empty player layer.
     *
     * The play button is placed over the photograph side of the banner (the
     * stylesheet positions it; on a narrow screen it moves to the top corner),
     * with a pulsing halo and a label under it. The layer it reveals is empty
     * until the click — the script fills it with a <video> or an <iframe> and a
     * close button — so a visitor who never presses play never loads a player.
     *
     * @return string HTML
     */
    protected function acad_hero_promo(): string {
        $watch = get_string('acad_watchpromo', 'theme_nit');
        $o  = html_writer::start_tag('button', [
            'type' => 'button',
            'class' => 'acad-cr__play',
            'aria-label' => $watch,
            'onclick' => 'AcademyUI.crPromoPlay(this)',
        ]);
        $o .= html_writer::span($this->acad_icon('play'), 'acad-cr__play-disc');
        $o .= html_writer::span(s($watch), 'acad-cr__play-label');
        $o .= html_writer::end_tag('button');
        $o .= html_writer::div('', 'acad-cr__video', [
            'aria-hidden' => 'true',
            'data-close-label' => get_string('acad_closevideo', 'theme_nit'),
        ]);
        return $o;
    }

    /**
     * The row of fact chips under the hero text: only the facts the course has.
     *
     * The design shows a certificate, a duration and a level. Real courses also
     * know when they start and how many people are in, so those ride along with
     * the same icon-plus-text treatment; "Free" is the payment plugin's verdict
     * (no active price rule), not a tick — see {@see acad_gather()}.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_hero_chips($data): string {
        $chips = [];
        if (!empty($data->isfree)) {
            $chips[] = ['check', get_string('acad_free', 'theme_nit'), ' acad-cr__chip--free'];
        }
        if (!empty($data->hascertificate)) {
            $chips[] = ['cert', get_string('acad_hascert', 'theme_nit'), ''];
        }
        $hours = $this->acad_hours_label($data);
        if ($hours !== '') {
            $chips[] = ['clock', $hours, ''];
        }
        $level = $this->acad_cf_select('level', $data);
        if ($level !== '') {
            // Already HTML-safe: customfield_select formats every option.
            $chips[] = ['level', $level, ''];
        }
        if ($data->startlabel !== '') {
            $chips[] = ['calendar', get_string('acad_startson', 'theme_nit', s($data->startlabel)), ''];
        }
        if ($data->enrolled > 0) {
            $chips[] = ['people', get_string('acad_nenrolled', 'theme_nit', number_format($data->enrolled)), ''];
        }
        if (empty($chips)) {
            return '';
        }

        $o = '';
        foreach ($chips as $c) {
            // Each text is a lang string or an already-escaped value.
            $o .= html_writer::tag('span',
                $this->acad_icon($c[0]) . html_writer::tag('span', $c[1]),
                ['class' => 'acad-cr__chip' . $c[2]]);
        }
        return html_writer::div($o, 'acad-cr__meta');
    }

    /**
     * URL of the first activity the reader can open, in curriculum order.
     *
     * The "General" section (0) is looked at last: on most courses it holds the
     * announcements forum, and "Go to course" should open the first lesson, not
     * the notice board. It is still the answer when nothing else is openable.
     *
     * @param \course_modinfo $modinfo
     * @param stdClass $data
     * @return moodle_url|null null when no visible activity has a page of its own
     */
    protected function acad_first_activity_url($modinfo, $data): ?moodle_url {
        $order = array_keys($data->modulerows);
        usort($order, function ($a, $b) {
            return ($a === 0) <=> ($b === 0);
        });
        foreach ($order as $snum) {
            $section = $data->modulerows[$snum];
            if (!$section->uservisible || empty($modinfo->sections[$snum])) {
                continue;
            }
            foreach ($modinfo->sections[$snum] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if ($cm->uservisible && $cm->url) {
                    return $cm->url;
                }
            }
        }
        return null;
    }

    /**
     * What the course costs and the one button that acts on it.
     *
     * The catalogue cards have always said both — "45.00 USD" over a "Buy now"
     * button — and the course page, which is the page a visitor lands on FROM one
     * of those cards, said neither: one "Enroll" button, no amount. A learner who
     * clicked a card priced at 45.00 arrived at a page that looked free, and only
     * found out otherwise at the checkout.
     *
     * The state comes from local_payments\price_resolver::course_state() — the
     * same call the cards make — so the two screens cannot disagree about what a
     * course costs or about which button it should be offering.
     *
     * Every button goes to /enrol/index.php. That is deliberately the one door:
     * local_payments' before_http_headers hook already routes it correctly for
     * every case — a guest to the log-in page with this course remembered as the
     * destination, a signed-in learner to the checkout, a free course to the
     * one-click "register for free" — so the theme picks the LABEL and lets the
     * payment plugin pick the flow. The one exception is the "no country on your
     * profile" state, which is not an offer at all: prices are per country, so
     * that button goes to the profile field that unblocks them.
     *
     * With local_payments absent, or with its resolver throwing, this falls back
     * to exactly the CTA the page has always had: one plain "Enroll". A price we
     * cannot work out must never cost the visitor the way in.
     *
     * @param stdClass $course
     * @return string
     */
    protected function acad_offer($course): string {
        $enrolurl = new moodle_url('/enrol/index.php', ['id' => $course->id]);

        // No payments plugin: the page keeps exactly the CTA it always had.
        if (!class_exists('\local_payments\price_resolver')) {
            return html_writer::div(
                html_writer::link($enrolurl, s(get_string('acad_enrol', 'theme_nit')),
                    ['class' => 'btn btn-primary acad-cr-btn']),
                'acad-cr__cta-row');
        }

        try {
            $state = \local_payments\price_resolver::course_state((int) $course->id);
        } catch (\Throwable $e) {
            // A broken price must never cost the visitor the way in.
            return html_writer::div(
                html_writer::link($enrolurl, s(get_string('acad_enrol', 'theme_nit')),
                    ['class' => 'btn btn-primary acad-cr-btn']),
                'acad-cr__cta-row');
        }

        // Signed in with no profile country. There is no price for this account —
        // not a guessed one, not the default one — so the slot carries the reason
        // and the way to fix it instead of an amount and a button that can only
        // fail at the checkout.
        if (!empty($state['countryrequired']) && class_exists('\local_payments\country_detector')) {
            $notice = \local_payments\country_detector::country_required_notice();
            return html_writer::div(
                html_writer::tag('p', s($notice['message']), ['class' => 'acad-cr__pricenote'])
                . html_writer::div(
                    html_writer::link($notice['url'], s($notice['action']),
                        ['class' => 'btn btn-primary acad-cr-btn']),
                    'acad-cr__cta-row'),
                'acad-cr__offer');
        }

        $o = '';

        // The amount. Printed in every state that has one — including "already
        // purchased" and "in your subscription", where it is what the learner got
        // rather than what they owe.
        $price = $this->acad_price_tags($state);
        if ($price !== '') {
            $o .= html_writer::div($price, 'acad-cr__price-row');
        }

        // The label. "Buy now" only where money actually changes hands: a course
        // covered by a subscription, or already paid for, is an enrolment now.
        $buying = !empty($state['haspricing'])
            && empty($state['purchased'])
            && empty($state['covered'])
            && ($state['price'] > 0 || $state['offerfinal'] > 0);
        $label = $buying
            ? get_string('acad_buynow', 'theme_nit')
            : get_string('acad_enrol', 'theme_nit');

        $o .= html_writer::div(
            html_writer::link($enrolurl, s($label), ['class' => 'btn btn-primary acad-cr-btn']),
            'acad-cr__cta-row');

        return html_writer::div($o, 'acad-cr__offer');
    }

    /**
     * The price tags: a live offer as struck-through original + final + "-40%",
     * otherwise the plain amount, and nothing at all when the course is priced
     * but no rule resolves to one.
     *
     * Nothing beats "Free" here: a course whose price could not be resolved is a
     * course we do not know the price of, not a free one.
     *
     * @param array $state from local_payments\price_resolver::course_state()
     * @return string
     */
    protected function acad_price_tags(array $state): string {
        $money = function (float $amount) use ($state): string {
            $currency = (string) ($state['currency'] ?? '');
            return format_float($amount, 2, false) . ($currency !== '' ? ' ' . $currency : '');
        };

        if (!empty($state['offerlabel']) && !empty($state['offerfinal'])) {
            return html_writer::tag('span', s($money((float) $state['price'])),
                    ['class' => 'acad-cr__price acad-cr__price--was'])
                . html_writer::tag('span', s($money((float) $state['offerfinal'])),
                    ['class' => 'acad-cr__price acad-cr__price--now'])
                . html_writer::tag('span', s($state['offerlabel']),
                    ['class' => 'acad-cr__price-off']);
        }

        if (!empty($state['price']) && $state['price'] > 0) {
            return html_writer::tag('span', s($money((float) $state['price'])),
                ['class' => 'acad-cr__price acad-cr__price--now']);
        }

        return '';
    }

    // =========================================================================
    // Aside — facts and people
    // =========================================================================

    /**
     * One "icon box + label over value" row, the unit both aside cards are built
     * from (the design's glance rows and instructor rows share it).
     *
     * @param string $lead the icon-box contents — an SVG icon or an avatar
     * @param string $label
     * @param string $value
     * @param string $leadclass extra class on the icon box ('' or ' acad-cr__glance-ico--avatar')
     * @return string
     */
    protected function acad_fact_row($lead, $label, $value, $leadclass = ''): string {
        return html_writer::div(
            html_writer::div($lead, 'acad-cr__glance-ico' . $leadclass) .
            html_writer::div(
                html_writer::div($label, 'acad-cr__glance-k') .
                html_writer::div($value, 'acad-cr__glance-v'),
                'acad-cr__glance-txt'
            ),
            'acad-cr__glance-row'
        );
    }

    /**
     * "At a glance" facts card: only rows that have real data are shown.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_glance($data) {
        $rows = [];

        // Modules.
        if ($data->modcount > 0) {
            $rows[] = ['modules',
                get_string('acad_modules', 'theme_nit'),
                $this->acad_count($data->modcount, 'acad_nmodule', 'acad_nmodules')];
        }
        // Level (dropdown).
        $level = $this->acad_cf_select('level', $data);
        if ($level !== '') {
            // Already HTML-safe: customfield_select formats every option.
            $rows[] = ['level', get_string('acad_level', 'theme_nit'), $level];
        }
        // Duration (hours).
        $hours = $this->acad_hours_label($data);
        if ($hours !== '') {
            $rows[] = ['clock', get_string('acad_duration', 'theme_nit'), $hours];
        }
        // Assessments (computed from the activity tree).
        if ($data->assesscount > 0) {
            $rows[] = ['assess',
                get_string('acad_assessments', 'theme_nit'),
                $this->acad_count($data->assesscount, 'acad_nassessment', 'acad_nassessments')];
        }
        // Language of instruction.
        $lang = $this->acad_cf_text('language', $data);
        if ($lang !== '') {
            // $lang is already HTML-safe (resolved via format_string/{mlang}).
            $rows[] = ['lang', get_string('acad_language', 'theme_nit'), $lang];
        }
        // Certificate: the course contains a certificate activity (acad_gather()),
        // which is the same test the catalogue's "Carries a certificate" facet makes.
        if (!empty($data->hascertificate)) {
            $rows[] = ['cert',
                get_string('acad_certificate', 'theme_nit'),
                get_string('acad_certificate_sub', 'theme_nit')];
        }

        if (empty($rows)) {
            return '';
        }

        $body = '';
        foreach ($rows as $r) {
            $body .= $this->acad_fact_row($this->acad_icon($r[0]), $r[1], $r[2]);
        }

        // The design draws this card without a title; keep one for assistive
        // technology so the aside still reads as two named regions.
        return html_writer::div(
            html_writer::tag('h2', get_string('acad_ataglance', 'theme_nit'), ['class' => 'visually-hidden']) . $body,
            'acad-cr__card acad-cr__glance');
    }

    /**
     * The people card: one row per instructor (avatar, then name), then the
     * top-level category as "Offered by".
     *
     * The instructor row keeps the design's shape — the round picture, the
     * "Instructor" label, the name — and grows a compact profile under it, read
     * from the "Instructor Fields" profile group (see {@see INSTRUCTOR_FIELDS}):
     * specialization and years of experience on a meta line, the opening of
     * the biography, the social links as icon pills, and one button that opens
     * the full profile in a dialog ({@see acad_instructor_dialog()}). An
     * instructor who has filled nothing in gets exactly the row they had.
     *
     * @param stdClass $course
     * @param stdClass $data
     * @return string
     */
    protected function acad_people($course, $data) {
        $rows = '';
        $dialogs = '';

        foreach ($data->teachers as $t) {
            $ins = $this->acad_instructor($t, $data);
            $profileurl = new moodle_url('/user/view.php', ['id' => $t->id, 'course' => $course->id]);
            $dialogid = 'acad-ins-' . (int) $t->id;

            // Name, then the meta line (specialization · years of experience).
            $value = html_writer::link($profileurl, s(fullname($t)), ['class' => 'acad-cr__tutor-name']);
            $meta = [];
            if ($ins->specialization !== '') {
                $meta[] = html_writer::span($ins->specialization, 'acad-cr__tutor-spec');
            }
            if ($ins->years !== '') {
                $meta[] = html_writer::span($this->acad_years_label($ins->years), 'acad-cr__tutor-years');
            }
            if ($meta) {
                $value .= html_writer::div(
                    implode(html_writer::span('·', 'acad-cr__tutor-dot', ['aria-hidden' => 'true']), $meta),
                    'acad-cr__tutor-meta');
            }

            $row = $this->acad_fact_row(
                $this->acad_avatar($t, 100),
                get_string('acad_instructorlabel', 'theme_nit'),
                $value,
                ' acad-cr__glance-ico--avatar');

            // Under the row: the opening of the biography, then the social
            // pills beside the "About the instructor" button.
            $extra = '';
            if ($ins->excerpt !== '') {
                $extra .= html_writer::tag('p', s($ins->excerpt), ['class' => 'acad-cr__tutor-bio']);
            }
            $actions = '';
            if ($ins->social) {
                $actions .= $this->acad_social_links($ins->social, 'acad-cr__tutor-social');
            }
            if ($ins->hasprofile) {
                $actions .= html_writer::tag('button',
                    $this->acad_icon('idcard') . html_writer::span(get_string('acad_aboutinstructor', 'theme_nit')),
                    [
                        'type'          => 'button',
                        'class'         => 'btn btn-outline-primary btn-sm acad-cr__tutor-more',
                        'data-dialog'   => $dialogid,
                        'onclick'       => 'AcademyUI.crInstructorOpen(this)',
                    ]);
                $dialogs .= $this->acad_instructor_dialog($t, $ins, $profileurl, $dialogid);
            }
            if ($actions !== '') {
                $extra .= html_writer::div($actions, 'acad-cr__tutor-actions');
            }

            $rows .= html_writer::div($row . $extra, 'acad-cr__tutor' . ($extra !== '' ? ' acad-cr__tutor--rich' : ''));
        }

        // Offered by = top-level category.
        if (!empty($data->catnames)) {
            $rows .= $this->acad_fact_row(
                $this->acad_icon('org'),
                get_string('acad_offeredby', 'theme_nit'),
                $data->catnames[0]);
        }

        if ($rows === '') {
            return '';
        }

        return html_writer::div(
            html_writer::tag('h2', get_string('acad_instructors', 'theme_nit'), ['class' => 'visually-hidden']) . $rows,
            'acad-cr__card acad-cr__people') . $dialogs;
    }

    // -------------------------------------------------------------------------
    // Instructor profile — the "Instructor Fields" group, on the card and in a dialog
    // -------------------------------------------------------------------------

    /**
     * The instructor profile fields this page reads, by the part each plays.
     *
     * Keyed by shortname — the one part of a profile field that is a code and
     * never changes; the labels are the fields' own (bilingual) names. The
     * group was built by hand on the site and local_profilefields knows the same
     * list ({@see \local_profilefields\provision::INSTRUCTOR_FIELDS}); it is
     * repeated here so the theme does not depend on that plugin to draw a card.
     *
     *   cover ..... the banner across the top of the dialog (a file field)
     *   meta ...... one line under the name: specialization, years, languages
     *   section ... a heading and a body in the dialog, in this order
     *   social .... an icon link (card pills and dialog footer)
     *   file ...... the résumé, offered as a download in the dialog footer
     *
     * @var string[] shortname => part
     */
    const INSTRUCTOR_FIELDS = [
        'coverimage'        => 'cover',
        'specialization'    => 'meta',
        'yearsofexperience' => 'meta',
        'languages'         => 'meta',
        'biography'         => 'section',
        'experience'        => 'section',
        'qualifications'    => 'section',
        'certificates'      => 'section',
        'awards'            => 'section',
        'linkedin'          => 'social',
        'website'           => 'social',
        'facebook'          => 'social',
        'instagram'         => 'social',
        'twitter'           => 'social',
        'youtube'           => 'social',
        'resume'            => 'file',
    ];

    /** @var string[] The glyph each dialog section is headed with (shortname => icon key). */
    const INSTRUCTOR_SECTION_ICONS = [
        'biography'      => 'user',
        'experience'     => 'briefcase',
        'qualifications' => 'grad',
        'certificates'   => 'cert',
        'awards'         => 'award',
    ];

    /**
     * Longest biography opening the card shows, in characters.
     *
     * Two lines beside the picture; the dialog carries the whole text.
     */
    const BIO_CHARS = 150;

    /**
     * Everything the card and the dialog need about one instructor, in one pass
     * over their profile fields.
     *
     * Field visibility is honoured through the profile API itself
     * ({@see profile_field_base::show_field_content()}): a field the site keeps
     * private, or for teachers only, is simply not read for the viewer. Text is
     * resolved to the current language (the fields are authored as {mlang}
     * pairs by local_nit_mlang); rich text is formatted by the field's own
     * renderer so embedded files and the multilang filter both apply.
     *
     * @param stdClass $user the instructor (a get_role_users() record)
     * @param stdClass $data page data (for the course context)
     * @return stdClass cover, specialization, years, languages[], excerpt,
     *                  sections[], social[], resume, hasprofile
     */
    protected function acad_instructor($user, $data): stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $p = (object) [
            'cover'          => '',
            'specialization' => '',
            'years'          => '',
            'languages'      => [],
            'excerpt'        => '',
            'sections'       => [],
            'social'         => [],
            'resume'         => null,
            'hasprofile'     => false,
        ];

        $usercontext = \context_user::instance($user->id, IGNORE_MISSING);
        if (!$usercontext) {
            return $p;
        }

        foreach (profile_get_user_fields_with_data((int) $user->id) as $f) {
            $short = (string) ($f->field->shortname ?? '');
            $part  = self::INSTRUCTOR_FIELDS[$short] ?? null;
            if ($part === null || !$f->is_visible()) {
                continue;
            }

            // The two file fields: the file is the value, whatever the data row says.
            if ($part === 'cover' || $part === 'file') {
                $file = $this->acad_profile_file($usercontext, (int) $f->field->id);
                if (!$file) {
                    continue;
                }
                $url = moodle_url::make_pluginfile_url($file->get_contextid(), 'profilefield_file', 'files',
                    $file->get_itemid(), $file->get_filepath(), $file->get_filename());
                if ($part === 'cover') {
                    if ($file->is_valid_image()) {
                        $p->cover = $url->out(false);
                    }
                } else {
                    $p->resume = (object) ['url' => $url, 'name' => $file->get_filename(), 'label' => $f->display_name()];
                }
                continue;
            }

            if ($f->is_empty()) {
                continue;
            }

            switch ($part) {
                case 'meta':
                    $text = $this->acad_ml((string) $f->data, $data);
                    if ($text === '') {
                        break;
                    }
                    if ($short === 'specialization') {
                        $p->specialization = $text;
                    } else if ($short === 'yearsofexperience') {
                        $p->years = $text;
                    } else {
                        // "Arabic, English" / "العربية، الإنجليزية" → one chip each.
                        $p->languages = preg_split('/\s*[,،|\/\n]+\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);
                    }
                    break;

                case 'section':
                    $html  = $f->display_data();
                    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
                    if ($plain === '') {
                        break;
                    }
                    $p->sections[] = (object) ['key' => $short, 'label' => $f->display_name(), 'html' => $html];
                    if ($short === 'biography') {
                        $p->excerpt = shorten_text($plain, self::BIO_CHARS);
                    }
                    break;

                case 'social':
                    $url = $this->acad_social_url($short, (string) $f->data);
                    if ($url === '') {
                        break;
                    }
                    $p->social[] = (object) ['key' => $short, 'label' => $f->display_name(), 'url' => $url];
                    break;
            }
        }

        // Sections in this page's order (biography first, awards last), not the
        // order the admin happened to sort the fields in.
        $order = array_flip(array_keys(self::INSTRUCTOR_FIELDS));
        usort($p->sections, function ($a, $b) use ($order) {
            return $order[$a->key] <=> $order[$b->key];
        });

        // The dialog is worth opening when it would show something the row does
        // not already: a section, a résumé or a cover photograph.
        $p->hasprofile = !empty($p->sections) || $p->resume !== null || $p->cover !== '' || !empty($p->languages);

        return $p;
    }

    /**
     * The one file stored in a profilefield_file field for a user.
     *
     * Mirrors where profilefield_file keeps it: the user's context, component
     * `profilefield_file`, area `files`, itemid = the field id.
     *
     * @param \context_user $usercontext
     * @param int $fieldid
     * @return \stored_file|null
     */
    protected function acad_profile_file($usercontext, int $fieldid) {
        $files = get_file_storage()->get_area_files($usercontext->id, 'profilefield_file', 'files',
            $fieldid, 'itemid, filepath, filename', false);
        return $files ? reset($files) : null;
    }

    /**
     * A social field's value as an absolute https URL, or '' when it is not one.
     *
     * The fields are free text: a full address, a bare domain ("example.com") or
     * a handle ("@name") all happen. A domain gets its scheme; a handle goes to
     * the network the field is for; anything that still is not a URL is dropped
     * rather than printed as a broken link.
     *
     * @param string $short field shortname (which network)
     * @param string $raw stored value, possibly {mlang} markup
     * @return string
     */
    protected function acad_social_url(string $short, string $raw): string {
        $raw = trim(stripos($raw, '{mlang') !== false ? $this->acad_resolve_mlang($raw) : $raw);
        if ($raw === '') {
            return '';
        }
        $hosts = ['twitter' => 'x.com/', 'instagram' => 'instagram.com/', 'youtube' => 'youtube.com/@',
            'facebook' => 'facebook.com/', 'linkedin' => 'linkedin.com/in/'];
        if ($raw[0] === '@' && isset($hosts[$short])) {
            $raw = 'https://' . $hosts[$short] . ltrim($raw, '@');
        } else if (!preg_match('~^https?://~i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        $clean = clean_param($raw, PARAM_URL);
        return preg_match('~^https?://[^/\s]+~i', $clean) ? $clean : '';
    }

    /**
     * "12 years of experience" for a number; anything else is printed as typed.
     *
     * @param string $years
     * @return string HTML-safe
     */
    protected function acad_years_label(string $years): string {
        if (preg_match('/^\s*(\d{1,2})\s*\+?\s*$/', $years, $m)) {
            return get_string(((int) $m[1] === 1) ? 'acad_1yearexp' : 'acad_nyearsexp', 'theme_nit', (int) $m[1]);
        }
        return $years;
    }

    /**
     * The instructor's picture at the requested size, never a link.
     *
     * @param stdClass $user
     * @param int $size
     * @return string HTML
     */
    protected function acad_avatar($user, int $size): string {
        $userpic = new user_picture($user);
        $userpic->size = $size;
        $userpic->link = false;
        return $this->output->render($userpic);
    }

    /**
     * The social links as a row of round icon buttons.
     *
     * @param stdClass[] $links from {@see acad_instructor()}
     * @param string $class wrapper class
     * @return string HTML
     */
    protected function acad_social_links(array $links, string $class): string {
        $o = '';
        foreach ($links as $l) {
            $o .= html_writer::link($l->url, $this->acad_icon($l->key), [
                'class'      => 'acad-cr__social acad-cr__social--' . $l->key,
                'title'      => $l->label,
                'aria-label' => $l->label,
                'target'     => '_blank',
                'rel'        => 'noopener',
            ]);
        }
        return html_writer::div($o, $class);
    }

    /**
     * The full instructor profile as a native <dialog>.
     *
     * A banner (the cover photograph under the page's own scrim, or a brand
     * gradient when there is none) carries the picture, the name and the meta
     * line; a strip of facts (years, languages, specialization) sits under it;
     * then one section per filled rich-text field; and a footer with the social
     * links, the résumé and the link to the full Moodle profile. The element is
     * in the top layer, so it renders above everything wherever it is emitted,
     * and the browser owns focus, Escape and the backdrop.
     *
     * @param stdClass $user
     * @param stdClass $ins from {@see acad_instructor()}
     * @param moodle_url $profileurl
     * @param string $id element id
     * @return string HTML
     */
    protected function acad_instructor_dialog($user, $ins, $profileurl, string $id): string {
        $name = s(fullname($user));

        // Banner.
        $meta = [];
        if ($ins->specialization !== '') {
            $meta[] = html_writer::span($ins->specialization, 'acad-ins__spec');
        }
        if ($ins->years !== '') {
            $meta[] = html_writer::span($this->acad_years_label($ins->years), 'acad-ins__years');
        }
        $head  = html_writer::div($this->acad_avatar($user, 200), 'acad-ins__avatar');
        $head .= html_writer::div(
            html_writer::div(get_string('acad_instructorlabel', 'theme_nit'), 'acad-ins__eyebrow') .
            html_writer::tag('h2', $name, ['class' => 'acad-ins__name', 'id' => $id . '-title']) .
            ($meta ? html_writer::div(implode(html_writer::span('·', 'acad-ins__dot', ['aria-hidden' => 'true']), $meta),
                'acad-ins__meta') : ''),
            'acad-ins__who');
        $hero = html_writer::div(html_writer::div($head, 'acad-ins__head'),
            'acad-ins__hero' . ($ins->cover !== '' ? ' has-cover' : ''),
            // The photograph rides in as a custom property so the stylesheet
            // can layer the scrim over it without a second element.
            $ins->cover !== '' ? ['style' => '--ins-cover: url("' . s($ins->cover) . '")'] : []);

        // Facts strip.
        $facts = [];
        if ($ins->years !== '') {
            $facts[] = ['clock', get_string('acad_yearsexp', 'theme_nit'), s($ins->years)];
        }
        if ($ins->languages) {
            $chips = '';
            foreach ($ins->languages as $lang) {
                $chips .= html_writer::span($lang, 'acad-ins__chip');
            }
            $facts[] = ['lang', get_string('acad_speaks', 'theme_nit'), html_writer::div($chips, 'acad-ins__chips')];
        }
        if ($ins->specialization !== '') {
            $facts[] = ['bulb', get_string('acad_specialization', 'theme_nit'), $ins->specialization];
        }
        $strip = '';
        foreach ($facts as $f) {
            $strip .= html_writer::div(
                html_writer::div($this->acad_icon($f[0]), 'acad-ins__fact-ico') .
                html_writer::div(html_writer::div($f[1], 'acad-ins__fact-k') . html_writer::div($f[2], 'acad-ins__fact-v'),
                    'acad-ins__fact-txt'),
                'acad-ins__fact');
        }
        if ($strip !== '') {
            $strip = html_writer::div($strip, 'acad-ins__facts');
        }

        // Sections.
        $body = '';
        foreach ($ins->sections as $s) {
            $icon = self::INSTRUCTOR_SECTION_ICONS[$s->key] ?? 'list';
            $body .= html_writer::tag('section',
                html_writer::tag('h3',
                    html_writer::span($this->acad_icon($icon), 'acad-ins__sec-ico') . html_writer::span($s->label),
                    ['class' => 'acad-ins__sec-h']) .
                html_writer::div($s->html, 'acad-ins__sec-body'),
                ['class' => 'acad-ins__sec acad-ins__sec--' . $s->key]);
        }
        if ($body !== '') {
            $body = html_writer::div($body, 'acad-ins__body');
        }

        // Footer: social pills, then the résumé and the profile link.
        $foot = '';
        if ($ins->social) {
            $foot .= $this->acad_social_links($ins->social, 'acad-ins__social');
        }
        $links = '';
        if ($ins->resume) {
            $links .= html_writer::link($ins->resume->url,
                $this->acad_icon('download') . html_writer::span(get_string('acad_downloadresume', 'theme_nit')),
                ['class' => 'btn btn-outline-primary acad-ins__btn', 'download' => $ins->resume->name]);
        }
        $links .= html_writer::link($profileurl,
            html_writer::span(get_string('acad_viewfullprofile', 'theme_nit')) . $this->acad_icon('arrow'),
            ['class' => 'btn btn-primary acad-ins__btn']);
        $foot .= html_writer::div($links, 'acad-ins__links');
        $foot = html_writer::div($foot, 'acad-ins__foot');

        $close = html_writer::tag('button', $this->acad_icon('close'), [
            'type'       => 'button',
            'class'      => 'acad-ins__close',
            'aria-label' => get_string('closebuttontitle'),
            'onclick'    => 'AcademyUI.crInstructorClose(this)',
        ]);

        // The close button is a sibling of the frame, not inside it, so it stays
        // in the corner while the profile scrolls under it.
        return html_writer::tag('dialog',
            $close . html_writer::div($hero . $strip . $body . $foot, 'acad-ins__frame'),
            ['class' => 'acad-ins', 'id' => $id, 'aria-labelledby' => $id . '-title']);
    }

    // =========================================================================
    // Main column — about, requirements, curriculum
    // =========================================================================

    /**
     * "About this course" card.
     *
     * Opens with the eyebrow, then — when the hero could only show an excerpt of
     * the summary — the whole summary; then "What will you learn in this
     * course?" over one tile per learning outcome (ILOs and end-of-training
     * statements, labelled separately only when both exist); then the skills
     * chips (course_fields, else course tags). Nothing to say ⇒ '' (card omitted).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_about($data) {
        $groups = [
            ['acad_ilos', $this->acad_chips('ilos', $data)],
            ['acad_bytheend', $this->acad_chips('by_the_end_of_training', $data)],
        ];
        $groups = array_values(array_filter($groups, function ($g) {
            return !empty($g[1]);
        }));

        $skills = $this->acad_skills($data);

        // The summary appears here only when the hero had to cut it — a short
        // summary is already on the page in full, three hundred pixels up.
        $showsummary = ($data->summary !== '' && $data->leadtruncated);

        if (empty($groups) && !$showsummary && $skills === '') {
            return '';
        }

        $question = html_writer::tag('h2', get_string('acad_whatlearn_q', 'theme_nit'),
            ['class' => 'acad-cr__h2']);

        $head = html_writer::div(get_string('acad_about_h', 'theme_nit'), 'acad-cr__eyebrow');
        $body = '';
        if ($showsummary) {
            $head .= html_writer::div($data->summary, 'acad-cr__summary');
            if (!empty($groups)) {
                $body .= $question;
            }
        } else if (!empty($groups)) {
            // The design's head: eyebrow and question together over the rule.
            $head .= $question;
        }

        // With a single group the sub-heading is noise — the question already
        // says it. Label the groups only when both are present.
        $labelled = count($groups) > 1;
        $i = 0;
        foreach ($groups as $g) {
            $tiles = '';
            foreach ($g[1] as $item) {
                // $item is already HTML-safe (resolved via format_string/{mlang}).
                $icon = self::TILE_ICONS[$i % count(self::TILE_ICONS)];
                $i++;
                $tiles .= html_writer::div(
                    $this->acad_icon($icon) . html_writer::tag('span', $item),
                    'acad-cr__tile');
            }
            $label = $labelled
                ? html_writer::tag('h3', get_string($g[0], 'theme_nit'), ['class' => 'acad-cr__learn-grouph'])
                : '';
            $body .= html_writer::div($label . html_writer::div($tiles, 'acad-cr__tiles'), 'acad-cr__learn-group');
        }

        $body .= $skills;

        return html_writer::div(
            html_writer::div($head, 'acad-cr__card-head') . $body,
            'acad-cr__card acad-cr__about',
            ['id' => 'about']);
    }

    /**
     * "Skills you'll gain" block — course_fields chips, else course tags.
     * Empty ⇒ '' (block omitted).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_skills($data) {
        $items = $this->acad_chips('course_fields', $data, true);
        $pills = '';
        if (!empty($items)) {
            foreach ($items as $item) {
                // $item is already HTML-safe (resolved via format_string/{mlang}).
                $pills .= html_writer::tag('span', $item, ['class' => 'acad-cr__pill']);
            }
        } else if (!empty($data->tags)) {
            foreach ($data->tags as $tag) {
                $pills .= html_writer::tag('span', format_string($tag->get_display_name()), ['class' => 'acad-cr__pill']);
            }
        } else {
            return '';
        }

        return html_writer::div(
            html_writer::tag('h3', get_string('acad_skills', 'theme_nit'), ['class' => 'acad-cr__learn-grouph'])
            . html_writer::div($pills, 'acad-cr__skills'),
            'acad-cr__learn-group acad-cr__skills-group');
    }

    /**
     * "Requirements & audience" card — prerequisites + target audience.
     * Empty ⇒ '' (card omitted).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_requirements($data) {
        $blocks = '';

        $audience = $this->acad_chips('target_audience', $data);
        if (!empty($audience)) {
            $blocks .= $this->acad_req_block(
                $this->acad_icon('people'),
                get_string('acad_audience', 'theme_nit'),
                $audience);
        }

        $prereq = $this->acad_chips('prerequisites', $data);
        if (!empty($prereq)) {
            $blocks .= $this->acad_req_block(
                $this->acad_icon('list'),
                get_string('acad_prerequisites', 'theme_nit'),
                $prereq);
        }

        if ($blocks === '') {
            return '';
        }

        return html_writer::div(
            html_writer::div(
                html_writer::tag('h2', get_string('acad_requirements', 'theme_nit'), ['class' => 'acad-cr__h2']),
                'acad-cr__card-head')
            . html_writer::div($blocks, 'acad-cr__req-grid'),
            'acad-cr__card acad-cr__req',
            ['id' => 'requirements']);
    }

    /**
     * One requirements block (icon + heading + list of points).
     *
     * @param string $icon
     * @param string $heading
     * @param string[] $points
     * @return string
     */
    protected function acad_req_block($icon, $heading, array $points) {
        $list = '';
        foreach ($points as $p) {
            // $p is already HTML-safe (resolved via format_string/{mlang}).
            $list .= html_writer::tag('li', $p);
        }
        return html_writer::div(
            html_writer::div($icon . html_writer::tag('span', $heading), 'acad-cr__req-h') .
            html_writer::tag('ul', $list, ['class' => 'acad-cr__req-list']),
            'acad-cr__req-card'
        );
    }

    /**
     * Curriculum card: one accordion row per section, first one open.
     *
     * The design draws the card without a title; a hidden one keeps the module
     * count available to assistive technology (the glance card shows it too).
     *
     * @param stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_modules($course, $modinfo, $context, $data) {
        $acc = '';
        $idx = 0;
        foreach ($data->modulerows as $snum => $section) {
            $idx++;
            $acc .= $this->acad_module_row($course, $section, $modinfo, $snum, $idx, ($idx === 1), $context);
        }

        return html_writer::div(
            html_writer::tag('h2',
                $this->acad_count($data->modcount, 'acad_1modulein', 'acad_nmodulesin'),
                ['class' => 'visually-hidden'])
            . $acc,
            'acad-cr__card acad-cr__acc',
            ['id' => 'modules']);
    }

    /**
     * One accordion row (section header + collapsible body).
     *
     * @param stdClass $course
     * @param \section_info $section
     * @param \course_modinfo $modinfo
     * @param int $snum
     * @param int $idx
     * @param bool $open
     * @param \context_course $context
     * @return string
     */
    protected function acad_module_row($course, $section, $modinfo, $snum, $idx, $open, $context) {
        $title  = get_section_name($course, $section);
        $bodyid = 'acad-cr-mod-' . $snum;
        $cmlist = !empty($modinfo->sections[$snum]) ? $modinfo->sections[$snum] : [];

        // Count visible activities + a "What's included" tally by module type.
        $typecounts = [];
        $visitems   = 0;
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }
            $visitems++;
            $plural = (string) $cm->modplural;
            $typecounts[$plural] = ($typecounts[$plural] ?? 0) + 1;
        }
        $included = [];
        foreach ($typecounts as $plural => $count) {
            $included[] = html_writer::tag('b', $count) . ' ' . s($plural);
        }

        $o  = html_writer::start_div('acad-cr__mod' . ($open ? ' is-open' : ''), ['id' => 'acad-cr-modwrap-' . $snum]);

        // Header: title and "Module details" on one line, the item count under.
        $o .= html_writer::start_tag('button', [
            'class'         => 'acad-cr__mod-head',
            'type'          => 'button',
            'onclick'       => 'AcademyUI.crModule(this)',
            'aria-expanded' => $open ? 'true' : 'false',
            'aria-controls' => $bodyid,
        ]);
        $meta = get_string('acad_modulen', 'theme_nit', $idx);
        if ($visitems > 0) {
            $meta .= ' : ' . $this->acad_count($visitems, 'acad_nitem', 'acad_nitems');
        }
        // Spans, not divs: a <button> may only hold phrasing content. The grid
        // on the button lays the three out as blocks regardless.
        $o .= html_writer::tag('span', format_string($title), ['class' => 'acad-cr__mod-title']);
        $o .= html_writer::tag('span',
            get_string('acad_moduledetails', 'theme_nit') . $this->acad_icon('chevron'),
            ['class' => 'acad-cr__mod-toggle']);
        $o .= html_writer::tag('span', $meta, ['class' => 'acad-cr__mod-meta']);
        $o .= html_writer::end_tag('button');

        // Body.
        $o .= html_writer::start_div('acad-cr__mod-body', ['id' => $bodyid, 'role' => 'region']);

        if ($section->uservisible && !empty($section->summary)) {
            $desc = format_text($section->summary, $section->summaryformat, ['context' => $context]);
            if (trim(strip_tags($desc)) !== '') {
                $o .= html_writer::div($desc, 'acad-cr__mod-desc');
            }
        }

        if (!empty($included)) {
            $o .= html_writer::tag('div', get_string('acad_included', 'theme_nit'), ['class' => 'acad-cr__included-h']);
            $o .= html_writer::div(implode(' · ', $included), 'acad-cr__included-sum');
        }

        if (!$section->uservisible) {
            if (!empty($section->availableinfo)) {
                $locked = \core_availability\info::format_info($section->availableinfo, $course);
                $o .= html_writer::div($locked, 'acad-cr__act-locked');
            }
        } else {
            $o .= $this->acad_activities($modinfo, $cmlist);
        }

        $o .= html_writer::end_div(); // body.
        $o .= html_writer::end_div(); // mod.
        return $o;
    }

    /**
     * Activity rows inside one module body.
     *
     * @param \course_modinfo $modinfo
     * @param array $cmlist
     * @return string
     */
    protected function acad_activities($modinfo, $cmlist) {
        $courseid = (int) $modinfo->get_course_id();

        // Is the reader looking at a course they have no access to, and if so which lessons
        // did the teacher publish as free previews (AC-4.9.5)? Both answers come from
        // local_payments; guarded by class_exists so the theme still renders without it,
        // in which case every row is drawn as it always was.
        $locked = false;
        $free = [];
        if (class_exists('\local_payments\course_preview')) {
            $locked = \local_payments\course_preview::is_locked($courseid);
            if ($locked) {
                $free = \local_payments\free_preview::for_course($courseid);
            }
        }

        $o = '';
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }

            $isfree = $locked && !empty($free[(int) $cm->id]);
            $islocked = $locked && !$isfree;

            $ico = $this->acad_act_icon($cm);

            // A locked lesson is not a link: clicking it only bounces the visitor to the
            // checkout, so the row says what to do instead of pretending to be playable.
            $name = ($cm->url && !$islocked)
                ? html_writer::link($cm->url, format_string($cm->name))
                : format_string($cm->name);
            if ($isfree) {
                $name .= html_writer::tag('span', get_string('freepreview_badge', 'local_payments'),
                    ['class' => 'acad-cr__act-free']);
            }
            if ($islocked) {
                $name .= html_writer::tag('span', $this->acad_icon('lock'), [
                    'class'       => 'acad-cr__act-lock',
                    'title'       => get_string('freepreview_lockedlesson', 'local_payments'),
                    'aria-hidden' => 'true',
                ]);
            }

            $rowclass = 'acad-cr__act';
            $rowclass .= $isfree ? ' is-free' : ($islocked ? ' is-locked' : '');

            $body = $ico .
                html_writer::div($name, 'acad-cr__act-name') .
                $this->acad_act_duration($cm) .
                html_writer::tag('span', s((string) $cm->modfullname), ['class' => 'acad-cr__act-type']);

            if ($islocked) {
                $body .= html_writer::div(get_string('freepreview_lockedlesson', 'local_payments'),
                    'acad-cr__act-lockmsg');
            }

            $o .= html_writer::div($body, $rowclass);
        }
        return $o;
    }

    /**
     * The activity's icon, coloured by its module purpose.
     *
     * Core's monochrome activity icons are black SVGs that core recolours with a
     * per-purpose filter — but only inside its own `.activityiconcontainer`
     * markup. Printed as a bare <img> (as this page once did) the forum, page
     * and quiz icons stayed black, invisible on the dark brand. So the icon is
     * rendered through core's own activity_icon output class, which prints that
     * container, and the purpose colours (plus theme_nit's own "other" purpose
     * rule in _activityicons.scss) apply here as everywhere else.
     *
     * @param \cm_info $cm
     * @return string
     */
    protected function acad_act_icon($cm): string {
        if (class_exists('\core_course\output\activity_icon')) {
            $icon = \core_course\output\activity_icon::from_cm_info($cm)
                ->set_extra_classes('acad-cr__act-icobox');
            return $this->output->render($icon);
        }
        return html_writer::empty_tag('img', [
            'src' => $cm->get_icon_url(), 'alt' => '', 'class' => 'acad-cr__act-ico', 'aria-hidden' => 'true',
        ]);
    }

    /**
     * Playing time badge for an activity row, when the activity plays a video.
     *
     * The length a learner cares about is already in the file - it just never
     * reaches the course page, because Moodle only learns it when the browser
     * loads the video on mod/resource/view.php. local_nit_media reads it out of
     * the container header instead, so the row can say "0:07" up front.
     *
     * Guarded by class_exists so the theme still renders on a site where that
     * plugin is absent: the row then looks exactly as it did before.
     *
     * @param \cm_info $cm
     * @return string HTML, or '' when there is no video or no readable length
     */
    protected function acad_act_duration($cm): string {
        if (!class_exists('\local_nit_media\duration')) {
            return '';
        }

        $seconds = \local_nit_media\duration::for_cm($cm);
        if ($seconds === null) {
            return '';
        }

        return html_writer::tag('span', \local_nit_media\duration::format($seconds), [
            'class' => 'acad-cr__act-dur',
            'title' => get_string('acad_videolength', 'theme_nit'),
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * URL of the course overview image, or null when none is set.
     *
     * @param \context_course $context
     * @return moodle_url|null
     */
    protected function acad_course_image_url($context) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder DESC, id DESC', false);
        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $context->id, 'course', 'overviewfiles', null, $file->get_filepath(), $file->get_filename());
        }
        return null;
    }

    /**
     * Small inline SVG icon (stroke = currentColor) by key.
     *
     * Drawn to echo the design's pictograms (a starred document for modules, a
     * diploma for the certificate, a bulb / pen / book / speech bubble for the
     * learning-outcome tiles) but as strokes in currentColor, so they take the
     * brand's Accent role and follow a category palette or a light/dark switch
     * the way a fixed-colour bitmap could not.
     *
     * @param string $key
     * @return string
     */
    protected function acad_icon($key): string {
        $paths = [
            'check'    => '<path d="M4 12l5 5 11-12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'chevron'  => '<polyline points="6,9 12,15 18,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'modules'  => '<path d="M6 3h8l4 4v14H6z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 10.5l1.3 2.6 2.9.4-2.1 2 .5 2.9-2.6-1.4-2.6 1.4.5-2.9-2.1-2 2.9-.4z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
            'clock'    => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
            'assess'   => '<path d="M8 6h12M8 12h12M8 18h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M3.5 6l1 1 2-2M3.5 12l1 1 2-2M3.5 18l1 1 2-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
            'lang'     => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" stroke="currentColor" stroke-width="1.7"/>',
            'cert'     => '<path d="M4 5h16v11H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 9h8M8 12.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="16" cy="15.5" r="2.3" stroke="currentColor" stroke-width="1.5"/><path d="M14.5 17.5l-.8 4 2.3-1.3 2.3 1.3-.8-4" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
            'people'   => '<circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M16 5.5a3.2 3.2 0 0 1 0 5M17.5 20a5.5 5.5 0 0 0-2.5-4.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'list'     => '<path d="M8 6h12M8 12h12M8 18h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="4" cy="6" r="1.3" fill="currentColor"/><circle cx="4" cy="12" r="1.3" fill="currentColor"/><circle cx="4" cy="18" r="1.3" fill="currentColor"/>',
            'lock'     => '<rect x="4.5" y="10" width="15" height="10" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'level'    => '<rect x="3" y="14" width="4" height="7" rx="1" stroke="currentColor" stroke-width="1.7"/><rect x="10" y="9" width="4" height="12" rx="1" stroke="currentColor" stroke-width="1.7"/><rect x="17" y="4" width="4" height="17" rx="1" stroke="currentColor" stroke-width="1.7"/>',
            'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M8 3v4M16 3v4M3.5 10.5h17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'org'      => '<rect x="3" y="7" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12.5h18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'bulb'     => '<path d="M12 3a6 6 0 0 0-3.6 10.8c.7.5 1.1 1.3 1.1 2.2h5c0-.9.4-1.7 1.1-2.2A6 6 0 0 0 12 3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9.5 19h5M10.5 21.5h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'pen'      => '<path d="M4 20l4.2-1 10.3-10.3-3.2-3.2L5 15.8z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M13 7.7l3.2 3.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'book'     => '<path d="M4 4.5h6a2.5 2.5 0 0 1 2.5 2.5v13A2.5 2.5 0 0 0 10 17.5H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M20 4.5h-6A2.5 2.5 0 0 0 11.5 7v13a2.5 2.5 0 0 1 2.5-2.5h6z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
            'chat'     => '<path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9.5L4 21z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 9h8M8 12.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            // The two promo-video glyphs. A play triangle points the same way in
            // both languages (it is "play", not "next"), and nothing flips it.
            'play'     => '<path d="M9 6.5v11l9-5.5z" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
            'close'    => '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
            // The instructor profile: section heads, the card button, the
            // footer actions, and one brand mark per social network. The marks
            // are drawn as outlines like every other glyph, so they take the
            // accent colour rather than each network's own.
            'user'      => '<circle cx="12" cy="8" r="3.6" stroke="currentColor" stroke-width="1.7"/><path d="M5 20a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'briefcase' => '<rect x="3" y="7.5" width="18" height="12.5" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 7.5V5.5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2M3 13h18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M10.5 13v2h3v-2" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
            'grad'      => '<path d="M2.5 9.5L12 5l9.5 4.5L12 14z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M6.5 11.8v4.2c0 1.4 2.5 2.8 5.5 2.8s5.5-1.4 5.5-2.8v-4.2M21.5 9.5v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'award'     => '<circle cx="12" cy="9" r="5.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 13.5L7 21l5-2.5 5 2.5-1.5-7.5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 6.5l.9 1.8 2 .3-1.45 1.4.35 2-1.8-.95-1.8.95.35-2L9.1 8.6l2-.3z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>',
            'idcard'    => '<rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="8.5" cy="11" r="2" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 16a3 3 0 0 1 6 0M13.5 9.5H18M13.5 12.5H18M13.5 15.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
            'download'  => '<path d="M12 4v11M7.5 10.5L12 15l4.5-4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.5 16.5v2a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
            // A "go there" arrow: points forward. The RTL build does not touch
            // inline SVG, so _coursepeople.scss flips it under body.dir-rtl.
            'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            'linkedin'  => '<rect x="3.5" y="3.5" width="17" height="17" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M8 10.5V17M8 7.5v.01M12 17v-3.7a2.2 2.2 0 0 1 4.4 0V17M12 10.5V17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
            'website'   => '<circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 12h17M12 3.5c2.8 2.8 2.8 14.2 0 17M12 3.5c-2.8 2.8-2.8 14.2 0 17" stroke="currentColor" stroke-width="1.6"/>',
            'facebook'  => '<path d="M14.5 21v-7h2.4l.4-3h-2.8V9.2c0-.9.3-1.5 1.5-1.5h1.5V5.1c-.3 0-1.2-.1-2.2-.1-2.2 0-3.8 1.3-3.8 3.8V11H9v3h2.5v7" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
            'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3.8" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="7" r="1" fill="currentColor"/>',
            'twitter'   => '<path d="M4 4l16 16M20 4L4 20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
            'youtube'   => '<rect x="2.5" y="5.5" width="19" height="13" rx="4" stroke="currentColor" stroke-width="1.6"/><path d="M10 9v6l5-3z" fill="currentColor" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>',
        ];
        $p = $paths[$key] ?? '';
        return '<svg class="acad-cr__ico acad-cr__ico--' . $key . '" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
            . $p . '</svg>';
    }

    /**
     * Inline accordion helper. Emitted in the body because a format renderer
     * runs after <head> is flushed.
     *
     * @return string JavaScript
     */
    protected function acad_inline_js() {
        return <<<'JS'
(function (w) {
    'use strict';
    w.AcademyUI = w.AcademyUI || {};

    // Accordion row: header button toggles .is-open on its .acad-cr__mod wrapper.
    w.AcademyUI.crModule = function (btn) {
        var row = btn.closest('.acad-cr__mod');
        if (!row) { return; }
        var open = row.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    // ---- Promo video -------------------------------------------------------
    // The hero becomes the player: its text fades out, the scrim clears, the
    // banner eases to 16:9 and the video fades in over the picture. Every
    // visual step is a CSS transition keyed off .is-playing; the script only
    // fixes the two heights (a height can be animated, an aspect ratio cannot),
    // builds the player on demand and takes it down again on close / Escape.
    var promoHeight = function (hero) {
        return Math.max(Math.round(hero.clientWidth * 9 / 16), 220);
    };
    var promoIcon = function (d) {
        return '<svg class="acad-cr__ico" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="' + d +
            '" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    };

    w.AcademyUI.crPromoPlay = function (btn) {
        var hero = btn.closest('.acad-cr__hero');
        var layer = hero && hero.querySelector('.acad-cr__video');
        if (!hero || !layer || hero.classList.contains('is-playing')) { return; }

        var src = hero.getAttribute('data-promo-src');
        var kind = hero.getAttribute('data-promo-player');
        if (!src) { return; }

        // Pin the resting height so the change to the player's height is a
        // transition rather than a jump, and remember it for the way back.
        var rest = hero.getBoundingClientRect().height;
        hero.style.height = rest + 'px';
        void hero.offsetHeight; // Commit the pinned height before changing it.
        hero.dataset.promoRest = String(rest);

        // The player. Created here, not in the markup, so nothing is fetched
        // until the visitor asks for it; the click is the user gesture that
        // lets autoplay through.
        var player;
        if (kind === 'video') {
            player = document.createElement('video');
            player.src = src;
            player.controls = true;
            player.autoplay = true;
            player.playsInline = true;
            player.preload = 'auto';
            var poster = hero.getAttribute('data-promo-poster');
            if (poster) { player.poster = poster; }
        } else {
            player = document.createElement('iframe');
            player.src = src;
            player.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
            player.setAttribute('allowfullscreen', '');
            player.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            player.setAttribute('title', btn.getAttribute('aria-label') || '');
        }
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'acad-cr__video-close';
        close.setAttribute('aria-label', layer.getAttribute('data-close-label') || '');
        close.innerHTML = promoIcon('M6 6l12 12M18 6L6 18');
        close.addEventListener('click', function () { w.AcademyUI.crPromoClose(hero); });
        layer.replaceChildren(player, close);
        layer.setAttribute('aria-hidden', 'false');

        hero.classList.add('is-playing');
        hero.style.height = promoHeight(hero) + 'px';

        if (kind === 'video') {
            var p = player.play();
            if (p && p.catch) { p.catch(function () { /* the controls are there */ }); }
        }
        hero._promoKey = function (e) {
            if (e.key === 'Escape') { w.AcademyUI.crPromoClose(hero); }
        };
        hero._promoResize = function () {
            hero.style.height = promoHeight(hero) + 'px';
        };
        document.addEventListener('keydown', hero._promoKey);
        w.addEventListener('resize', hero._promoResize);
        close.focus({preventScroll: true});
    };

    w.AcademyUI.crPromoClose = function (hero) {
        var layer = hero.querySelector('.acad-cr__video');
        if (!hero.classList.contains('is-playing')) { return; }

        var video = layer.querySelector('video');
        if (video) { video.pause(); }
        document.removeEventListener('keydown', hero._promoKey);
        w.removeEventListener('resize', hero._promoResize);

        hero.classList.remove('is-playing');
        layer.setAttribute('aria-hidden', 'true');
        hero.style.height = (hero.dataset.promoRest || '') + 'px';

        // Take the player down once the banner is back at rest (and no later
        // than the transition would take, for a reduced-motion visitor whose
        // transitions never end because they never start).
        var done = function (e) {
            if (e && (e.target !== hero || e.propertyName !== 'height')) { return; }
            hero.removeEventListener('transitionend', done);
            clearTimeout(timer);
            hero.style.height = '';
            layer.replaceChildren();
        };
        var timer = setTimeout(done, 900);
        hero.addEventListener('transitionend', done);

        var play = hero.querySelector('.acad-cr__play');
        if (play) { play.focus({preventScroll: true}); }
    };

    // ---- Instructor profile dialog ----------------------------------------
    // A native <dialog>: the browser owns the top layer, the focus trap, Escape
    // and the backdrop; the stylesheet owns the entrance. The script only opens
    // it, closes it, and treats a click on the backdrop (the dialog element
    // itself, outside its frame) as a close. On the rare browser without
    // showModal() the button falls back to the instructor's profile page.
    w.AcademyUI.crInstructorOpen = function (btn) {
        var dlg = document.getElementById(btn.getAttribute('data-dialog') || '');
        if (!dlg || typeof dlg.showModal !== 'function') {
            var link = btn.closest('.acad-cr__tutor');
            link = link && link.querySelector('.acad-cr__tutor-name');
            if (link) { w.location.href = link.href; }
            return;
        }
        dlg._opener = btn;
        dlg.showModal();
        // Start at the top whichever section was read last time.
        var frame = dlg.querySelector('.acad-ins__frame');
        if (frame) { frame.scrollTop = 0; }
    };
    w.AcademyUI.crInstructorClose = function (el) {
        var dlg = el.closest('dialog');
        if (dlg && dlg.open) { dlg.close(); }
    };
    document.addEventListener('click', function (e) {
        var dlg = e.target.closest && e.target.closest('dialog.acad-ins');
        if (dlg && e.target === dlg) { dlg.close(); }
    });
    document.addEventListener('close', function (e) {
        var dlg = e.target;
        if (dlg && dlg.classList && dlg.classList.contains('acad-ins') && dlg._opener) {
            dlg._opener.focus({preventScroll: true});
        }
    }, true);
})(window);
JS;
    }
}
