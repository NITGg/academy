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
 * NIT theme SCSS callbacks.
 *
 * Composition (one combined stream): pre_scss -> main -> extra.
 *   pre   : primitives -> mixins -> semantic (Bootstrap var overrides) -> pre.
 *   main  : Boost preset (Bootstrap compiles with NIT values) -> NIT components.
 *   extra : component-tier CSS custom properties -> fonts.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Concatenate the contents of every .scss file in a directory (sorted).
 *
 * @param string $dir absolute directory path
 * @return string combined SCSS
 */
function theme_nit_concat_scss(string $dir): string {
    $files = glob($dir . '/*.scss') ?: [];
    sort($files);
    $scss = '';
    foreach ($files as $file) {
        $scss .= file_get_contents($file) . "\n";
    }
    return $scss;
}

/**
 * Live site counters for the front-page marketing sections.
 *
 * Exposed to JavaScript as `window.NIT_STATS` by the frontpage layout, so
 * author-written NIT Section blocks can render real numbers dynamically
 * (works for guests — no web service or token needed).
 *
 * @return array{courses:int,categories:int,topcategories:int,subcategories:int,students:int}
 */
function theme_nit_get_site_stats(): array {
    global $DB;

    $categories = (int) $DB->count_records('course_categories', ['visible' => 1]);
    $topcategories = (int) $DB->count_records('course_categories', ['visible' => 1, 'parent' => 0]);

    return [
        // Real courses (exclude the site "course" id 1) that are visible.
        'courses' => (int) $DB->count_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID]),
        'categories' => $categories,
        'topcategories' => $topcategories,
        'subcategories' => max(0, $categories - $topcategories),
        // Distinct users with at least one enrolment.
        'students' => (int) $DB->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {user_enrolments}'),
    ];
}

/**
 * The fee-enrolment price of a course, or '' when the course is free.
 *
 * @param int $courseid
 * @return string e.g. "250.00 EGP" or '' (free)
 */
function theme_nit_course_price(int $courseid): string {
    global $DB;

    $recs = $DB->get_records_select(
        'enrol',
        "courseid = :cid AND status = 0 AND enrol IN ('fee', 'paypal')",
        ['cid' => $courseid],
        'sortorder ASC',
        'id, cost, currency'
    );
    foreach ($recs as $r) {
        if ((float) $r->cost > 0) {
            return format_float($r->cost, 2, false) . ' ' . $r->currency;
        }
    }
    return '';
}

/**
 * The name of a course's (editing) teacher, or '' if none.
 *
 * @param int $courseid
 * @return string
 */
function theme_nit_course_teacher(int $courseid): string {
    global $DB;

    $roleids = $DB->get_fieldset_select('role', 'id', "archetype IN ('editingteacher', 'teacher')");
    if (empty($roleids)) {
        return '';
    }
    [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
    $params['ctx'] = context_course::instance($courseid)->id;

    $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename
              FROM {role_assignments} ra
              JOIN {user} u ON u.id = ra.userid
             WHERE ra.contextid = :ctx AND ra.roleid $insql AND u.deleted = 0
          ORDER BY ra.timemodified ASC";
    $teacher = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

    return $teacher ? fullname($teacher) : '';
}

/**
 * Visible courses as view-models for the front-page "courses" section.
 *
 * Exposed to JavaScript as `window.NIT_COURSES`; author-written NIT Section
 * blocks render them via a <template> (see the frontpage renderer).
 *
 * @param int $limit maximum number of courses
 * @return array<int, array{id:int,fullname:string,summary:string,url:string,image:string,price:string}>
 */
function theme_nit_get_courses(int $limit = 12): array {
    global $DB, $CFG, $OUTPUT;
    require_once($CFG->libdir . '/filelib.php');

    $records = $DB->get_records_select(
        'course',
        'id <> :site AND visible = 1',
        ['site' => SITEID],
        'sortorder ASC',
        '*',
        0,
        $limit
    );

    $fs = get_file_storage();
    $courses = [];
    foreach ($records as $c) {
        $context = context_course::instance($c->id);

        // Course image: overview file, else a generated pattern.
        $image = '';
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $image = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                break;
            }
        }
        if ($image === '') {
            $image = $OUTPUT->get_generated_image_for_id($c->id);
        }

        // Short plain-text summary.
        $summary = '';
        if (!empty($c->summary)) {
            $plain = html_to_text(
                format_text($c->summary, $c->summaryformat, ['context' => $context, 'noclean' => true]),
                0,
                false
            );
            $summary = shorten_text(trim($plain), 120);
        }

        $courses[] = [
            'id' => (int) $c->id,
            'fullname' => format_string($c->fullname, true, ['context' => $context]),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            'image' => $image,
            'price' => theme_nit_course_price((int) $c->id),
            'teacher' => theme_nit_course_teacher((int) $c->id),
        ];
    }
    return $courses;
}

/**
 * Main SCSS: Boost's preset, then the NIT component layer.
 *
 * @param theme_config $theme the theme config object
 * @return string
 */
function theme_nit_get_main_scss_content($theme) {
    global $CFG;

    // Inherit Boost's compiled preset. Bootstrap compiles using the NIT
    // variable values set in pre_scss, so components adopt the NIT look.
    require_once($CFG->dirroot . '/theme/boost/lib.php');
    $scss = theme_boost_get_main_scss_content($theme);

    // NIT component refinements (token-driven).
    $scss .= theme_nit_concat_scss(__DIR__ . '/scss/components');

    // Any global post-Bootstrap styles.
    $scss .= file_get_contents(__DIR__ . '/scss/post.scss');

    return $scss;
}

/**
 * Pre-SCSS: primitives, mixins, then the semantic tier that overrides Bootstrap.
 *
 * @param theme_config $theme the theme config object
 * @return string
 */
function theme_nit_get_pre_scss($theme) {
    $scss = '';

    // Tier 1: primitive tokens (raw palette + scales).
    $scss .= file_get_contents(__DIR__ . '/scss/tokens/_primitives.scss');
    // Shared functions / mixins.
    $scss .= file_get_contents(__DIR__ . '/scss/_mixins.scss');
    // Tier 2: semantic tokens mapped onto Bootstrap variables (before Bootstrap).
    $scss .= file_get_contents(__DIR__ . '/scss/tokens/_semantic.scss');

    // Brand overrides (M5): the SDK resolver returns the active brand's semantic
    // tokens; the theme maps them onto SCSS variables here, after the M3 defaults
    // and before Bootstrap, so the whole UI recompiles on brand. Guarded so the
    // theme still renders if the SDK is absent (graceful degradation).
    if (class_exists('\local_nit_core\api\branding')) {
        $brand = \local_nit_core\api\branding::tokens();
        if (!empty($brand['primary'])) {
            $scss .= '$primary: ' . $brand['primary'] . ";\n";
            $scss .= '$nit-on-primary: ' . $brand['onprimary'] . ";\n";
        }
        if (!empty($brand['font'])) {
            $scss .= '$font-family-sans-serif: ' . $brand['font'] . ";\n";
        }
    }

    // Reserved pre-Boost overrides.
    $scss .= file_get_contents(__DIR__ . '/scss/pre.scss');

    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\$behatsite: true;\n";
    }
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Extra SCSS: component-tier CSS custom properties (light + dark) and fonts.
 *
 * @param theme_config $theme the theme config object
 * @return string
 */
function theme_nit_get_extra_scss($theme) {
    $scss = '';

    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_root.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_fonts.scss');

    if (!empty($theme->settings->scss)) {
        $scss .= $theme->settings->scss;
    }

    return $scss;
}
