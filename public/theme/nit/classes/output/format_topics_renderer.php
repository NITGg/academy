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
 * NIT topics-format renderer — pixel replica of the Coursera course-detail page.
 *
 * Ported from theme_edumy (legacy print_multiple_section_page override) to the
 * Moodle 5.x course-format output pipeline. In 5.x the whole course body is one
 * `\core_courseformat\output\local\content` renderable rendered by the format
 * renderer; we intercept it in {@see render()} and, on the multi-section landing
 * page while NOT editing, emit the Coursera bands instead of the stock template.
 *
 * In editing mode (and on single-section pages) we defer to the parent so
 * teachers keep the normal drag/drop management UI.
 *
 * The visible labels are Coursera's own wording ("What you'll learn", "Skills
 * you'll gain", "What's included", …) and are intentionally hard-coded English
 * literals — they are brand copy, not Moodle language strings.
 *
 * Data that Moodle does not natively hold (star rating, per-lesson duration,
 * learning outcomes, marketing bullets, …) is rendered as an em-dash "—" in the
 * exact slot where Coursera shows the real value, so the layout matches the
 * reference screenshots slot-for-slot.
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
 * Coursera-style course page renderer for the topics format.
 */
class format_topics_renderer extends \format_topics\output\renderer {

    /** @var string Placeholder for data Moodle does not have. */
    const DASH = '—';

    /**
     * Separator that joins the individual "chip" sentences inside a single
     * short-text course custom field (co1 = "What you'll learn", co2 = "Skills
     * you'll gain"). Deliberately obscure so a learner never types it as part of
     * a real sentence.
     */
    const CHIP_SEP = '@@|@@';

    /**
     * Intercept the course content renderable.
     *
     * On the multi-section landing page (not editing, not a single-section view)
     * we replace Moodle's course body with the Coursera replica. Everything else
     * falls through to the stock format renderer.
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
     * Build the full Coursera-style page for the given course format.
     *
     * @param \core_courseformat\base $format the course format
     * @return string HTML
     */
    protected function acad_render_page($format): string {
        global $CFG;

        $course  = $format->get_course();
        $modinfo = get_fast_modinfo($course);
        $context = context_course::instance($course->id);

        $data = $this->acad_gather($course, $modinfo, $context);

        $o  = html_writer::start_div('acad-cr');
        $o .= $this->acad_breadcrumb($data);
        $o .= $this->acad_hero($course, $context, $data);
        $o .= $this->acad_stats($data);
        $o .= $this->acad_tabs();
        $o .= $this->acad_learn($data);
        $o .= $this->acad_skills($data);
        $o .= $this->acad_details($data);
        $o .= $this->acad_expertise($course, $context, $data);
        $o .= $this->acad_modules($course, $modinfo, $context, $data);
        $o .= html_writer::end_div();

        // Accordion / tab helpers. A format renderer runs after <head> is flushed,
        // so $PAGE->requires->js() would be dropped; emit a plain inline <script>.
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
        $d->enrolled = count_enrolled_users($context);

        // Category chain for the breadcrumb.
        $d->catnames = [];
        if ($course->category) {
            $cat = core_course_category::get($course->category, IGNORE_MISSING);
            if ($cat) {
                foreach (array_reverse($cat->get_parents()) as $pid) {
                    $p = $DB->get_record('course_categories', ['id' => $pid], 'name');
                    if ($p) { $d->catnames[] = format_string($p->name); }
                }
                $d->catnames[] = format_string($cat->get_formatted_name());
            }
        }

        // Visible sections (become the module accordion rows).
        $numsections   = course_get_format($course)->get_last_section_number();
        $d->modulerows = [];
        $d->modcount   = 0;
        foreach ($modinfo->get_section_info_all() as $snum => $sec) {
            if ($snum === 0) {
                if (empty($modinfo->sections[0]) || !$sec->uservisible) { continue; }
            }
            if ($snum > $numsections) { continue; }
            $show = $sec->uservisible ||
                ($sec->visible && !$sec->available && !empty($sec->availableinfo)) ||
                (!$sec->visible && !$course->hiddensections);
            if (!$show) { continue; }
            $d->modulerows[$snum] = $sec;
            $d->modcount++;
        }

        // Teachers (Coursera "Instructors").
        $d->teachers = $this->acad_teachers($context);

        // Skills = course tags.
        $d->tags = core_tag_tag::get_item_tags('core', 'course', $course->id);

        // Course image.
        $d->image = $this->acad_course_image_url($context);

        // Language name.
        if ($course->lang) {
            $langs = get_string_manager()->get_list_of_languages();
            $d->lang = $langs[$course->lang] ?? $course->lang;
        } else {
            $d->lang = self::DASH;
        }

        // Start date ("Starts Aug 3").
        $d->startlabel = $course->startdate ? 'Starts ' . userdate($course->startdate, '%b %e') : self::DASH;

        // Course custom fields, keyed by shortname. co1 = What you'll learn (chips),
        // co2 = Skills you'll gain (chips), co3 = Instructor, co4 = level. Missing or
        // hidden fields simply won't appear in the map, so each band falls back safely.
        $d->cf = [];
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($course->id, true) as $fielddata) {
                $sn  = $fielddata->get_field()->get('shortname');
                $val = $fielddata->get_value();
                $d->cf[$sn] = is_string($val) ? trim($val) : $val;
            }
        } catch (\Throwable $e) {
            // Custom fields component unavailable — leave $d->cf empty, bands fall back to DASH.
            $d->cf = [];
        }

        return $d;
    }

    /**
     * Split a chip-style custom field value ("a@@|@@b@@|@@c") into a clean list
     * of non-empty, trimmed sentences. Returns [] when the field is empty/absent.
     *
     * @param string $shortname
     * @param stdClass $data
     * @return string[]
     */
    protected function acad_cf_list($shortname, $data) {
        $raw = $data->cf[$shortname] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $parts = array_map('trim', explode(self::CHIP_SEP, $raw));
        return array_values(array_filter($parts, function($p) {
            return $p !== '';
        }));
    }

    /**
     * Plain (non-chip) short-text custom field value, or '' when empty/absent.
     *
     * @param string $shortname
     * @param stdClass $data
     * @return string
     */
    protected function acad_cf_text($shortname, $data) {
        $raw = $data->cf[$shortname] ?? '';
        return is_string($raw) ? trim($raw) : '';
    }

    /**
     * Enrolled teachers for the Coursera "Instructors" slots.
     *
     * @param \context_course $context
     * @return array
     */
    protected function acad_teachers($context) {
        $out   = [];
        $roles = get_archetype_roles('editingteacher') + get_archetype_roles('teacher');
        if (empty($roles)) { return $out; }
        $fields = 'u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt, u.firstnamephonetic,
                   u.lastnamephonetic, u.middlename, u.alternatename';
        $users = get_role_users(array_keys($roles), $context, false, $fields);
        $seen  = [];
        foreach ($users as $u) {
            if (isset($seen[$u->id])) { continue; }
            $seen[$u->id] = true;
            $out[] = $u;
        }
        return $out;
    }

    // =========================================================================
    // Bands
    // =========================================================================

    /**
     * Breadcrumb band.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_breadcrumb($data) {
        $parts = array_merge(['Browse'], $data->catnames);
        $html  = '';
        $last  = count($parts) - 1;
        foreach ($parts as $i => $p) {
            $html .= html_writer::tag('span', s($p));
            if ($i < $last) { $html .= html_writer::tag('span', '›'); }
        }
        return html_writer::div(html_writer::div($html, 'acad-cr__crumbs'), 'acad-cr__wrap');
    }

    /**
     * Hero band (provider, title, instructor line, CTA, enrolled count).
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_hero($course, $context, $data) {
        global $USER;

        $isenrolled = is_enrolled($context, $USER->id, '', true);
        if ($isenrolled) {
            $url   = new moodle_url('/course/view.php', ['id' => $course->id]);
            $label = 'Go to Course';
        } else {
            $url   = new moodle_url('/enrol/index.php', ['id' => $course->id]);
            $label = 'Enroll for Free';
        }

        // Provider = top-level category (stands in for Coursera's "IBM" logo slot).
        $provider = !empty($data->catnames) ? $data->catnames[0] : format_string($course->shortname);

        // Instructor line. co3 = "Instructor" custom field wins when set; otherwise
        // fall back to the enrolled teacher(s), then to a dash.
        $cfinstructor = $this->acad_cf_text('co3', $data);
        if ($cfinstructor !== '') {
            $instr = html_writer::tag('strong', 'Instructor: ') . s($cfinstructor);
        } else if (!empty($data->teachers)) {
            $first = fullname($data->teachers[0]);
            $more  = count($data->teachers) - 1;
            $instr = html_writer::tag('strong', 'Instructor: ')
                   . html_writer::tag('a', s($first), ['href' => '#'])
                   . ($more > 0 ? ' +' . $more . ' more' : '');
        } else {
            $instr = html_writer::tag('strong', 'Instructor: ') . self::DASH;
        }

        $o  = html_writer::start_div('acad-cr__hero');

        // ---- Left main ----
        $o .= html_writer::start_div('acad-cr__hero-main');
        $o .= html_writer::div(s($provider), 'acad-cr__provider');
        $o .= html_writer::tag('h1', format_string($course->fullname), ['class' => 'acad-cr__title']);
        $o .= html_writer::tag('p',
            'This course is part of multiple programs. ' .
            html_writer::tag('a', 'Learn more', ['href' => '#']),
            ['class' => 'acad-cr__partof']);
        $o .= html_writer::div($instr, 'acad-cr__instructor');

        $o .= html_writer::start_div('acad-cr__cta-row');
        $o .= html_writer::link($url, $label, ['class' => 'acad-cr-btn']);
        $o .= html_writer::tag('span', s($data->startlabel), ['class' => 'acad-cr__starts']);
        $o .= html_writer::end_div();

        $o .= html_writer::div(
            html_writer::tag('b', number_format($data->enrolled)) . ' already enrolled',
            'acad-cr__enrolled');
        $o .= html_writer::end_div(); // hero-main

        // ---- Right aside (progress-ring card, decorative) ----
        $o .= html_writer::div(
            $this->acad_ring() .
            html_writer::div('Included with course materials', 'acad-cr__aside-note'),
            'acad-cr__hero-aside');

        $o .= html_writer::end_div(); // hero
        return html_writer::div($o, 'acad-cr__wrap');
    }

    /**
     * Decorative circular ring like the Coursera hero.
     *
     * @return string
     */
    protected function acad_ring() {
        return '<svg class="acad-cr__ring" viewBox="0 0 120 120" aria-hidden="true">'
            . '<circle cx="60" cy="60" r="52" fill="none" stroke="#e5e5e5" stroke-width="10"/>'
            . '<circle cx="60" cy="60" r="52" fill="none" stroke="#0056d2" stroke-width="10"'
            . ' stroke-linecap="round" stroke-dasharray="245 327" transform="rotate(-90 60 60)"/>'
            . '</svg>';
    }

    /**
     * Stats strip (module count + level).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_stats($data) {
        // co4 = "level" custom field; fall back to a dash when unset.
        $level = $this->acad_cf_text('co4', $data);
        $leveltop = ($level !== '' ? s($level) : self::DASH) . ' level';
        // Rating, "Flexible schedule" and the "—%" liked-this-course cells are
        // intentionally dropped — only real data (modules count + level) is shown.
        $stats = [
            ['top' => $data->modcount . ' modules'],
            ['top' => $leveltop],
        ];

        $o = html_writer::start_div('acad-cr__stats');
        foreach ($stats as $st) {
            $o .= html_writer::div(
                html_writer::div($st['top'], 'acad-cr__stat-top'),
                'acad-cr__stat'
            );
        }
        $o .= html_writer::end_div();
        return html_writer::div($o, 'acad-cr__wrap');
    }

    /**
     * Sticky tab bar.
     *
     * @return string
     */
    protected function acad_tabs() {
        $tabs = [
            ['id' => 'about',           'label' => 'About',           'active' => true],
            ['id' => 'outcomes',        'label' => 'Outcomes',        'active' => false],
            ['id' => 'modules',         'label' => 'Modules',         'active' => false],
            ['id' => 'recommendations', 'label' => 'Recommendations', 'active' => false],
            ['id' => 'testimonials',    'label' => 'Testimonials',    'active' => false],
            ['id' => 'reviews',         'label' => 'Reviews',         'active' => false],
        ];
        $o = html_writer::start_div('acad-cr__tabs');
        foreach ($tabs as $t) {
            $cls = 'acad-cr__tab' . (!empty($t['active']) ? ' is-active' : '');
            $o .= html_writer::tag('button', s($t['label']), [
                'class'      => $cls,
                'type'       => 'button',
                'data-crtab' => $t['id'],
                'onclick'    => 'AcademyUI.crTab(this)',
            ]);
        }
        $o .= html_writer::end_div();
        return html_writer::div($o, 'acad-cr__wrap');
    }

    /**
     * "What you'll learn" band.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_learn($data) {
        $check = '<svg class="acad-cr__check" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
               . '<path d="M4 10l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        // co1 = "What you'll learn" chips. Fall back to four dashed slots so the
        // checklist still matches the reference layout when the field is empty.
        $items = $this->acad_cf_list('co1', $data);
        $grid = '';
        if ($items) {
            foreach ($items as $item) {
                $grid .= html_writer::div($check . html_writer::tag('span', s($item)), 'acad-cr__learn-item');
            }
        } else {
            for ($i = 0; $i < 4; $i++) {
                $grid .= html_writer::div($check . html_writer::tag('span', self::DASH), 'acad-cr__learn-item');
            }
        }

        $inner = html_writer::tag('h2', "What you'll learn", ['class' => 'acad-cr__h2', 'id' => 'about'])
               . html_writer::div(html_writer::div($grid, 'acad-cr__learn-grid'), 'acad-cr__learn-card');

        return html_writer::div(html_writer::div($inner, 'acad-cr__wrap'), 'acad-cr__band');
    }

    /**
     * "Skills you'll gain" band.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_skills($data) {
        // co2 = "Skills you'll gain" chips. Fall back to course tags, then to six
        // dashed pills so the band always fills the reference layout.
        $items = $this->acad_cf_list('co2', $data);
        $pills = '';
        if ($items) {
            foreach ($items as $item) {
                $pills .= html_writer::tag('span', s($item), ['class' => 'acad-cr__pill']);
            }
        } else if (!empty($data->tags)) {
            foreach ($data->tags as $tag) {
                $pills .= html_writer::tag('span', format_string($tag->get_display_name()), ['class' => 'acad-cr__pill']);
            }
            $pills .= html_writer::tag('a', 'View all', ['class' => 'acad-cr__viewall', 'href' => '#']);
        } else {
            for ($i = 0; $i < 6; $i++) {
                $pills .= html_writer::tag('span', self::DASH, ['class' => 'acad-cr__pill']);
            }
        }

        $inner = html_writer::tag('h2', "Skills you'll gain", ['class' => 'acad-cr__h2', 'id' => 'outcomes'])
               . html_writer::div($pills, 'acad-cr__skills');

        return html_writer::div(html_writer::div($inner, 'acad-cr__wrap'), 'acad-cr__band');
    }

    /**
     * "Details to know" band.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_details($data) {
        $cell = function($icon, $head, $sub) {
            return html_writer::div(
                html_writer::div($icon . html_writer::tag('span', $head), 'acad-cr__detail-h') .
                html_writer::div($sub, 'acad-cr__detail-sub'),
                'acad-cr__detail'
            );
        };

        $iconcert = '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="9" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M8 14l-1 7 5-3 5 3-1-7" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';
        $iconassess = '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
        $iconlang = '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" stroke="currentColor" stroke-width="1.6"/></svg>';

        $o  = html_writer::start_div('acad-cr__details');
        $o .= $cell($iconcert,   'Shareable certificate', 'Add to your LinkedIn profile');
        $o .= $cell($iconassess, 'Assessments',           self::DASH . ' assignments');
        $o .= $cell($iconlang,   'Taught in ' . s($data->lang), self::DASH . ' languages available');
        $o .= html_writer::end_div();

        $inner = html_writer::tag('h2', 'Details to know', ['class' => 'acad-cr__h2'])
               . $o;

        return html_writer::div(html_writer::div($inner, 'acad-cr__wrap'), 'acad-cr__band');
    }

    /**
     * "Build your subject-matter expertise" band (grey).
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_expertise($course, $context, $data) {
        $summary = format_text($course->summary, $course->summaryformat, ['context' => $context]);
        $summary = trim(strip_tags($summary)) !== '' ? $summary : html_writer::tag('p', self::DASH);

        $tick = '<svg viewBox="0 0 20 20" fill="none"><path d="M4 10l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $bullets = '';
        for ($i = 0; $i < 4; $i++) {
            $bullets .= html_writer::tag('li', $tick . html_writer::tag('span', self::DASH));
        }

        $left = html_writer::tag('h2', 'Build your subject-matter expertise', ['class' => 'acad-cr__h2'])
              . html_writer::tag('p',
                    'This course is available as part of multiple programs. When you enroll in this ' .
                    'course, you\'ll also be asked to select a specific program.',
                    ['class' => 'acad-cr__modules-intro'])
              . html_writer::tag('ul', $bullets, ['class' => 'acad-cr__bullets']);

        $right = html_writer::tag('h2',
                    'There are ' . $data->modcount . ' modules in this course',
                    ['class' => 'acad-cr__h2', 'id' => 'modules'])
               . html_writer::div($summary, 'acad-cr__modules-intro');

        $grid = html_writer::div($left . $right, 'acad-cr__expertise-grid');

        return html_writer::div(
            html_writer::div(html_writer::div($grid, 'acad-cr__wrap'), 'acad-cr__band'),
            'acad-cr__soft'
        );
    }

    // =========================================================================
    // Modules band (accordion + instructor rail)
    // =========================================================================

    /**
     * Modules band: accordion (left) + instructor rail (right).
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

        $left = html_writer::div($acc, 'acad-cr__acc');
        $rail = $this->acad_rail($course, $context, $data);

        $grid = html_writer::div(
            html_writer::div($left, 'acad-cr__modules-main') .
            html_writer::div($rail, 'acad-cr__modules-rail'),
            'acad-cr__modules-grid'
        );

        return html_writer::div(html_writer::div($grid, 'acad-cr__wrap'), 'acad-cr__band');
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

        // "What's included" tally by module type.
        $typecounts = [];
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) { continue; }
            // modplural is a lang_string; cast before using it as an array key.
            $plural = (string) $cm->modplural;
            $typecounts[$plural] = ($typecounts[$plural] ?? 0) + 1;
        }
        $included = [];
        foreach ($typecounts as $plural => $count) {
            $included[] = '<b>' . $count . '</b> ' . s($plural);
        }
        $includedstr = $included ? implode(' · ', $included) : self::DASH;

        $o  = html_writer::start_div('acad-cr__mod' . ($open ? ' is-open' : ''), ['id' => 'acad-cr-modwrap-' . $snum]);

        // Header.
        $o .= html_writer::start_tag('button', [
            'class'         => 'acad-cr__mod-head',
            'type'          => 'button',
            'onclick'       => 'AcademyUI.crModule(this)',
            'aria-expanded' => $open ? 'true' : 'false',
            'aria-controls' => $bodyid,
        ]);
        $o .= html_writer::div(
            html_writer::tag('div', format_string($title), ['class' => 'acad-cr__mod-title']) .
            html_writer::tag('div', 'Module ' . $idx . ' · ' . self::DASH . ' to complete', ['class' => 'acad-cr__mod-meta'])
        );
        $o .= html_writer::tag('span',
            'Module details' .
            '<svg viewBox="0 0 20 20" fill="none"><polyline points="5,8 10,13 15,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            ['class' => 'acad-cr__mod-toggle']);
        $o .= html_writer::end_tag('button');

        // Body.
        $o .= html_writer::start_div('acad-cr__mod-body', ['id' => $bodyid, 'role' => 'region']);

        if ($section->uservisible && !empty($section->summary)) {
            $desc = format_text($section->summary, $section->summaryformat, ['context' => $context]);
            $o .= html_writer::div($desc, 'acad-cr__mod-desc');
        } else {
            $o .= html_writer::div(self::DASH, 'acad-cr__mod-desc');
        }

        $o .= html_writer::tag('div', "What's included", ['class' => 'acad-cr__included-h']);
        $o .= html_writer::div($includedstr, 'acad-cr__included-sum');

        if (!$section->uservisible) {
            // Section is restricted for this viewer: show the availability note
            // (core_courseformat has no public section_availability() helper in 5.x).
            $locked = !empty($section->availableinfo)
                ? \core_availability\info::format_info($section->availableinfo, $course)
                : self::DASH;
            $o .= html_writer::div($locked, 'acad-cr__act-locked');
        } else {
            $o .= $this->acad_activities($modinfo, $cmlist);
        }

        $o .= html_writer::end_div(); // body
        $o .= html_writer::end_div(); // mod
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
        if (empty($cmlist)) {
            return html_writer::div(self::DASH, 'acad-cr__act-locked');
        }
        $o = '';
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) { continue; }

            $ico = html_writer::empty_tag('img', [
                'src' => $cm->get_icon_url(), 'alt' => (string) $cm->modfullname, 'class' => 'acad-cr__act-ico',
            ]);
            $name = $cm->url
                ? html_writer::link($cm->url, format_string($cm->name))
                : format_string($cm->name);

            $o .= html_writer::div(
                $ico .
                html_writer::div($name, 'acad-cr__act-name') .
                html_writer::tag('span', self::DASH, ['class' => 'acad-cr__act-dur']),
                'acad-cr__act'
            );
        }
        return $o;
    }

    /**
     * Right-hand instructor + "offered by" rail.
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_rail($course, $context, $data) {
        // Instructors card.
        $tutors = '';
        if (!empty($data->teachers)) {
            foreach ($data->teachers as $t) {
                $userpic = new user_picture($t);
                $userpic->size = 100;
                $avatar = $this->output->render($userpic);
                $tutors .= html_writer::div(
                    $avatar .
                    html_writer::div(
                        html_writer::div(s(fullname($t)), 'acad-cr__tutor-name') .
                        html_writer::div(self::DASH, 'acad-cr__tutor-org') .
                        html_writer::div(self::DASH . ' Courses · ' . self::DASH . ' learners', 'acad-cr__tutor-stat')
                    ),
                    'acad-cr__tutor'
                );
            }
        } else {
            $tutors = html_writer::div(self::DASH, 'acad-cr__tutor');
        }

        $instructorscard = html_writer::div(
            html_writer::tag('div', 'Instructors', ['class' => 'acad-cr__rail-h']) .
            $tutors,
            'acad-cr__rail-card'
        );

        // Offered by.
        $provider = !empty($data->catnames) ? $data->catnames[0] : format_string($course->shortname);
        $offeredcard = html_writer::div(
            html_writer::tag('div', 'Offered by', ['class' => 'acad-cr__offered-h']) .
            html_writer::div(s($provider), 'acad-cr__offered-logo') .
            html_writer::tag('a', 'Learn more', ['href' => '#']),
            'acad-cr__rail-card'
        );

        return $instructorscard . $offeredcard;
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
     * Inline accordion / tab helpers (ported from local/academy/ui.js). Emitted
     * in the body because a format renderer runs after <head> is flushed.
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

    // Sticky tab bar: activate the clicked tab and smooth-scroll to its anchor.
    w.AcademyUI.crTab = function (btn) {
        var bar = btn.closest('.acad-cr__tabs');
        if (bar) {
            bar.querySelectorAll('.acad-cr__tab').forEach(function (t) {
                t.classList.remove('is-active');
            });
        }
        btn.classList.add('is-active');
        var id = btn.getAttribute('data-crtab');
        var target = id && document.getElementById(id);
        if (target) {
            var top = target.getBoundingClientRect().top + window.pageYOffset - 70;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    };
})(window);
JS;
    }
}
