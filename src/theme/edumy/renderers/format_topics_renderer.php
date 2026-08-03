<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/topics/renderer.php');

/**
 * Course page renderer — pixel replica of the Coursera course-detail page.
 *
 * Active on course/view.php when NOT in editing mode. In editing mode the page
 * falls back to the standard format_topics_renderer so teachers keep the normal
 * drag/drop management UI.
 *
 * The visible labels are Coursera's own wording ("What you'll learn", "Skills
 * you'll gain", "What's included", …) and are intentionally hard-coded English
 * literals — they are brand copy, not Moodle language strings, and calling
 * get_string() for keys Moodle doesn't ship would emit "[[key]]" + debugging.
 *
 * Data that Moodle does not natively hold (star rating, per-lesson duration,
 * learning outcomes, marketing bullets, …) is rendered as an em-dash "—" in the
 * exact slot where Coursera shows the real value, so the layout matches the
 * reference screenshots slot-for-slot. Every such placeholder is DASH; swapping
 * one for a real source later is a local change in the band that prints it.
 */
class theme_edumy_format_topics_renderer extends format_topics_renderer {

    /** Placeholder for data Moodle does not have. */
    const DASH = '—';

    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        if ($this->page->user_is_editing()) {
            parent::print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
            return;
        }

        $modinfo = get_fast_modinfo($course);
        $course  = course_get_format($course)->get_course();
        $context = context_course::instance($course->id);

        $data = $this->acad_gather($course, $modinfo, $context);

        echo html_writer::start_div('acad-cr');
        echo $this->acad_breadcrumb($data);
        echo $this->acad_hero($course, $context, $data);
        echo $this->acad_stats($data);
        echo $this->acad_tabs();
        echo $this->acad_learn();
        echo $this->acad_skills($data);
        echo $this->acad_details($data);
        echo $this->acad_expertise($course, $context, $data);
        echo $this->acad_modules($course, $modinfo, $context, $data);
        echo html_writer::end_div();

        // Load the accordion/tab helpers. $PAGE->requires->js() would target the
        // <head>, which is already flushed by the time a format renderer runs, so
        // the request is silently dropped. Emit a plain <script> in the body
        // instead (ui.js is a non-AMD IIFE); a filemtime query busts the cache.
        global $CFG;
        $ver = @filemtime($CFG->dirroot . '/local/academy/ui.js') ?: 1;
        echo html_writer::script('', new moodle_url('/local/academy/ui.js', ['v' => $ver]));
    }

    // =========================================================================
    // Data gathering — one pass, so each band just reads $data.
    // =========================================================================

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

        return $d;
    }

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

        // Instructor line.
        if (!empty($data->teachers)) {
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
        $o .= html_writer::div('Included with ' . self::DASH . ' · ' .
            html_writer::tag('a', 'Learn more', ['href' => '#']), 'acad-cr__plus');
        $o .= html_writer::end_div(); // hero-main

        // ---- Right aside (progress-ring stand-in) ----
        $o .= html_writer::start_div('acad-cr__hero-aside');
        $o .= $this->acad_ring();
        $o .= html_writer::div(self::DASH, 'acad-cr__aside-note');
        $o .= html_writer::end_div();

        $o .= html_writer::end_div(); // hero
        return html_writer::div($o, 'acad-cr__wrap');
    }

    /** Decorative circular ring like the Coursera hero. */
    protected function acad_ring() {
        return '<svg class="acad-cr__ring" viewBox="0 0 120 120" aria-hidden="true">'
            . '<circle cx="60" cy="60" r="52" fill="none" stroke="#e5e5e5" stroke-width="10"/>'
            . '<circle cx="60" cy="60" r="52" fill="none" stroke="#0056d2" stroke-width="10"'
            . ' stroke-linecap="round" stroke-dasharray="245 327" transform="rotate(-90 60 60)"/>'
            . '</svg>';
    }

    protected function acad_stats($data) {
        $stars = html_writer::tag('span', '★★★★★', ['class' => 'acad-cr__stars']);
        $stats = [
            ['top' => $data->modcount . ' modules',
             'sub' => 'Gain insight into a topic and learn the fundamentals'],
            ['top' => $stars . ' ' . self::DASH,
             'sub' => self::DASH . ' reviews'],
            ['top' => self::DASH . ' level',
             'sub' => 'No prior experience required'],
            ['top' => 'Flexible schedule',
             'sub' => self::DASH . ' · Learn at your own pace'],
            ['top' => self::DASH . '%',
             'sub' => 'Most learners liked this course'],
        ];

        $o = html_writer::start_div('acad-cr__stats');
        foreach ($stats as $st) {
            $o .= html_writer::div(
                html_writer::div($st['top'], 'acad-cr__stat-top') .
                html_writer::div($st['sub'], 'acad-cr__stat-sub'),
                'acad-cr__stat'
            );
        }
        $o .= html_writer::end_div();
        return html_writer::div($o, 'acad-cr__wrap');
    }

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

    protected function acad_learn() {
        // Moodle has no structured "learning outcomes"; show four dashed slots so
        // the checklist matches the reference. Real copy can replace DASH later.
        $check = '<svg class="acad-cr__check" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
               . '<path d="M4 10l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        $grid = '';
        for ($i = 0; $i < 4; $i++) {
            $grid .= html_writer::div($check . html_writer::tag('span', self::DASH), 'acad-cr__learn-item');
        }

        $inner = html_writer::tag('h2', "What you'll learn", ['class' => 'acad-cr__h2', 'id' => 'about'])
               . html_writer::div(html_writer::div($grid, 'acad-cr__learn-grid'), 'acad-cr__learn-card');

        return html_writer::div(html_writer::div($inner, 'acad-cr__wrap'), 'acad-cr__band');
    }

    protected function acad_skills($data) {
        $pills = '';
        if (!empty($data->tags)) {
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

    protected function acad_module_row($course, $section, $modinfo, $snum, $idx, $open, $context) {
        $title  = get_section_name($course, $section);
        $bodyid = 'acad-cr-mod-' . $snum;
        $cmlist = !empty($modinfo->sections[$snum]) ? $modinfo->sections[$snum] : [];

        // "What's included" tally by module type.
        $typecounts = [];
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) { continue; }
            $typecounts[$cm->modplural] = ($typecounts[$cm->modplural] ?? 0) + 1;
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
            $o .= html_writer::div($this->section_availability($section), 'acad-cr__act-locked');
        } else {
            $o .= $this->acad_activities($modinfo, $cmlist);
        }

        $o .= html_writer::end_div(); // body
        $o .= html_writer::end_div(); // mod
        return $o;
    }

    protected function acad_activities($modinfo, $cmlist) {
        if (empty($cmlist)) {
            return html_writer::div(self::DASH, 'acad-cr__act-locked');
        }
        $o = '';
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) { continue; }

            $ico = html_writer::empty_tag('img', [
                'src' => $cm->get_icon_url(), 'alt' => $cm->modfullname, 'class' => 'acad-cr__act-ico',
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
            html_writer::div(
                'Instructor ratings ' . html_writer::tag('b', self::DASH) . ' (' . self::DASH . ' ratings)',
                'acad-cr__rail-rating'
            ) .
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
}
