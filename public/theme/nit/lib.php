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
 * The NIT colour palette — the single source of truth for the site's colours.
 *
 * This is the "AppColors" of the theme: a flat set of semantically-named colour
 * tokens the site is built from. It powers three things at once:
 *   1. The colour editor on the gallery page (theme/nit/gallery.php) renders one
 *      picker per token, grouped by the `group` label.
 *   2. theme_nit_get_pre_scss() emits each token as a `$nit-c-<key>` SCSS
 *      variable (config value, else the default here) before Bootstrap compiles.
 *   3. scss/foundation/_root.scss republishes each as a `--nit-<key>` CSS custom
 *      property, so any component — the navbar included — reads its colour from
 *      the palette via `var(--nit-<key>)`.
 *
 * Defaults are the colours the site already uses today (navbar + home), so an
 * untouched install looks identical to before the editor existed. `key` becomes
 * the config name `colour_<key>`, the SCSS var `$nit-c-<key>` and the custom
 * property `--nit-<key>`.
 *
 * @return array<string, array{group:string, label:string, default:string}>
 *         ordered map keyed by token key
 */
function theme_nit_colour_palette(): array {
    return [
        // --- Brand (site) : Bootstrap $primary/$secondary + marketing accents --
        'primary'          => ['group' => 'Brand', 'label' => 'Primary', 'default' => '#2a50c8'],
        'secondary'        => ['group' => 'Brand', 'label' => 'Secondary', 'default' => '#626c7a'],
        'accentgold'       => ['group' => 'Brand', 'label' => 'Accent gold', 'default' => '#e8b84b'],
        'accentgolddark'   => ['group' => 'Brand', 'label' => 'Accent gold (dark / gradient)', 'default' => '#c9922a'],
        'accentteal'       => ['group' => 'Brand', 'label' => 'Accent teal', 'default' => '#00a99d'],

        // --- Navbar : the dark navy + gold top bar -----------------------------
        'navbarbg'         => ['group' => 'Navbar', 'label' => 'Navbar background', 'default' => '#0a1628'],
        'navbarsurface'    => ['group' => 'Navbar', 'label' => 'Navbar surface (buttons)', 'default' => '#10203a'],
        'navbarborder'     => ['group' => 'Navbar', 'label' => 'Navbar border', 'default' => '#1b2c48'],
        'navbaraccent'     => ['group' => 'Navbar', 'label' => 'Navbar accent (gold)', 'default' => '#e8b84b'],
        'navbaraccenthover' => ['group' => 'Navbar', 'label' => 'Navbar accent hover', 'default' => '#f0c86a'],
        'navbartext'       => ['group' => 'Navbar', 'label' => 'Navbar text', 'default' => '#cdd5e0'],
        'navbarpanel'      => ['group' => 'Navbar', 'label' => 'Dropdown panel background', 'default' => '#0d2149'],
        'navbarpaneltext'  => ['group' => 'Navbar', 'label' => 'Dropdown item text', 'default' => '#8a9ab5'],
        'navbarpanelborder' => ['group' => 'Navbar', 'label' => 'Dropdown divider', 'default' => '#dedede'],

        // --- Neutrals : surfaces, text, borders --------------------------------
        'background'       => ['group' => 'Neutrals', 'label' => 'Background', 'default' => '#ffffff'],
        'surface'          => ['group' => 'Neutrals', 'label' => 'Surface (subtle fill)', 'default' => '#f7f8fa'],
        'textprimary'      => ['group' => 'Neutrals', 'label' => 'Text primary', 'default' => '#171b22'],
        'textsecondary'    => ['group' => 'Neutrals', 'label' => 'Text secondary', 'default' => '#626c7a'],
        'border'           => ['group' => 'Neutrals', 'label' => 'Border', 'default' => '#dce1e8'],

        // --- Semantic : status colours -----------------------------------------
        'success'          => ['group' => 'Semantic', 'label' => 'Success', 'default' => '#1e7a54'],
        'warning'          => ['group' => 'Semantic', 'label' => 'Warning', 'default' => '#9a6410'],
        'error'            => ['group' => 'Semantic', 'label' => 'Error / danger', 'default' => '#b23a2e'],
        'info'             => ['group' => 'Semantic', 'label' => 'Info', 'default' => '#0e7c86'],

        // --- Dark : the dark-mode palette (also seeds the dark marketing bands) -
        // Defaults are the navy tones the site's dark surfaces already use, so the
        // hero/section blocks render identically once they read these tokens.
        'darkprimary'         => ['group' => 'Dark', 'label' => 'Dark primary', 'default' => '#6c9bd6'],
        'darkbackground'      => ['group' => 'Dark', 'label' => 'Dark background', 'default' => '#0a1628'],
        'darksurface'         => ['group' => 'Dark', 'label' => 'Dark surface (card)', 'default' => '#0f1e33'],
        'darksurfacevariant'  => ['group' => 'Dark', 'label' => 'Dark surface (raised)', 'default' => '#13293f'],
        'darktextprimary'     => ['group' => 'Dark', 'label' => 'Dark text primary', 'default' => '#ffffff'],
        'darktextsecondary'   => ['group' => 'Dark', 'label' => 'Dark text secondary', 'default' => '#8a9ab5'],
        'darkborder'          => ['group' => 'Dark', 'label' => 'Dark border', 'default' => '#244766'],
    ];
}

/**
 * The resolved value of one palette token: the saved config, else its default.
 *
 * @param string $key palette key (see theme_nit_colour_palette())
 * @return string a `#rrggbb` colour
 */
function theme_nit_colour(string $key): string {
    $palette = theme_nit_colour_palette();
    $default = $palette[$key]['default'] ?? '#000000';
    $value = get_config('theme_nit', 'colour_' . $key);
    return (is_string($value) && $value !== '') ? $value : $default;
}

/**
 * The whole resolved colour palette, for API / export consumption.
 *
 * Each entry carries the token's group, label, the live resolved value (saved
 * config, else default) and its default — so a client (e.g. the mobile app) can
 * both apply the colours and show which were customised. Backs colours.php.
 *
 * @return array<int, array{key:string, group:string, label:string, value:string, default:string, iscustom:bool}>
 */
function theme_nit_colours_all(): array {
    $out = [];
    foreach (theme_nit_colour_palette() as $key => $meta) {
        $value = theme_nit_colour($key);
        $out[] = [
            'key' => $key,
            'group' => $meta['group'],
            'label' => $meta['label'],
            'value' => $value,
            'default' => $meta['default'],
            'iscustom' => (strtolower($value) !== strtolower($meta['default'])),
        ];
    }
    return $out;
}

/**
 * The per-language custom-font slots.
 *
 * The theme hosts one uploadable font file per site language: the English font
 * is applied when the site runs in English (`html[lang="en"]`) and the Arabic
 * font when it runs in Arabic (`html[lang="ar"]`). Each slot is stored exactly
 * like a Boost stored-file setting — the file lives in its own file area
 * (itemid 0, system context) and the config `theme_nit/<setting>` holds the
 * filename — so the standard theme plumbing (setting_file_url / setting_file_serve)
 * serves it (see theme_nit_pluginfile()).
 *
 * `input` is the multipart field name on the gallery font form; `family` is the
 * CSS font-family the compiled stylesheet exposes; `selector` scopes it to the
 * matching site language; `fallback` is the system-font stack used until (and
 * behind) the uploaded file.
 *
 * @return array<string, array{setting:string, filearea:string, input:string,
 *         basename:string, family:string, selector:string, fallback:string,
 *         strkey:string, samplekey:string, rtl:bool}>
 */
function theme_nit_font_slots(): array {
    return [
        'en' => [
            'setting'   => 'fontfileen',
            'filearea'  => 'fontfileen',
            'input'     => 'fontfile_en',
            'basename'  => 'font-en',
            'family'    => 'NIT Site Font EN',
            'selector'  => 'html[lang="en"] body',
            'fallback'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
            'strkey'    => 'fonten',
            'samplekey' => 'fontsampleen',
            'rtl'       => false,
        ],
        'ar' => [
            'setting'   => 'fontfilear',
            'filearea'  => 'fontfilear',
            'input'     => 'fontfile_ar',
            'basename'  => 'font-ar',
            'family'    => 'NIT Site Font AR',
            'selector'  => 'html[lang="ar"] body',
            'fallback'  => '"Segoe UI", Tahoma, "Traditional Arabic", "Noto Naskh Arabic", Arial, sans-serif',
            'strkey'    => 'fontar',
            'samplekey' => 'fontsamplear',
            'rtl'       => true,
        ],
    ];
}

/**
 * The @font-face + language-scoped font-family rules for the uploaded fonts.
 *
 * Emitted into the (cached) extra SCSS stream. Only slots that actually have a
 * file uploaded produce output, so an untouched install keeps the default
 * system font. The font URL is a self-hosted pluginfile URL (never external);
 * because it is wrapped in a quoted url("…") the protocol-relative `//` is a
 * string, not a SCSS line comment.
 *
 * @param theme_config $theme the theme config object (carries the settings)
 * @return string CSS (valid SCSS)
 */
function theme_nit_font_scss($theme): string {
    $css = '';
    foreach (theme_nit_font_slots() as $slot) {
        $url = $theme->setting_file_url($slot['setting'], $slot['filearea']);
        if (empty($url)) {
            continue;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $format = ($ext === 'otf') ? 'opentype' : 'truetype';
        $family = $slot['family'];

        $css .= '@font-face {'
            . 'font-family: "' . $family . '";'
            . 'src: url("' . $url . '") format("' . $format . '");'
            . 'font-weight: 100 900;'
            . 'font-style: normal;'
            . 'font-display: swap;'
            . "}\n";
        $css .= $slot['selector'] . ' {'
            . 'font-family: "' . $family . '", ' . $slot['fallback'] . ';'
            . "}\n";
    }
    return $css;
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

    // User-editable colour palette (edited on the gallery page). Always emit
    // every token as a `$nit-c-<key>` SCSS variable — the saved colour, else the
    // palette default — so it is defined for the navbar and for the --nit-*
    // custom properties in _root.scss (extra_scss, same combined stream).
    foreach (theme_nit_colour_palette() as $key => $meta) {
        $scss .= '$nit-c-' . $key . ': ' . theme_nit_colour($key) . ";\n";
    }

    // Map palette tokens onto the Bootstrap/semantic layer, but ONLY for tokens
    // the admin has actually saved — so an untouched install (and any live M5
    // SDK brand set just above) keeps its existing values. `$nit-c-*` above
    // still carries the defaults for the custom-property layer regardless.
    // Config key => the SCSS variables it drives.
    $semanticmap = [
        'primary'     => ['primary', 'link-color'],
        'secondary'   => ['secondary'],
        'success'     => ['success'],
        'warning'     => ['warning'],
        'error'       => ['danger'],
        'info'        => ['info'],
        'background'  => ['body-bg', 'nit-surface'],
        'textprimary' => ['body-color', 'nit-ink'],
        'border'      => ['border-color', 'card-border-color', 'nit-line'],
    ];
    foreach ($semanticmap as $key => $targets) {
        $saved = get_config('theme_nit', 'colour_' . $key);
        if (!is_string($saved) || $saved === '') {
            continue;
        }
        foreach ($targets as $target) {
            $scss .= '$' . $target . ': $nit-c-' . $key . ";\n";
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

    // Admin-uploaded, per-language custom fonts (edited on the gallery page).
    $scss .= theme_nit_font_scss($theme);

    if (!empty($theme->settings->scss)) {
        $scss .= $theme->settings->scss;
    }

    return $scss;
}

/**
 * Serve the theme's admin-uploaded font files via pluginfile.php.
 *
 * Mirrors theme_boost_pluginfile(): the uploaded fonts live in a system-context
 * file area per language slot (see theme_nit_font_slots()), and the theme
 * revision — not the itemid — busts the cache. The gallery page (site:config
 * only) is the sole writer; this endpoint is a public, cache-able read of the
 * self-hosted font, exactly like the site logo.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_nit_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $fontareas = array_map(static fn($slot) => $slot['filearea'], theme_nit_font_slots());

    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $fontareas, true)) {
        $theme = theme_config::load('nit');
        // Theme files must be cache-able by both browsers and proxies by default.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }

    send_file_not_found();
}

/**
 * Visible categories as view-models for the front-page "categories" section.
 *
 * Exposed to JavaScript as `window.NIT_CATEGORIES`.
 *
 * @param int $limit maximum number of categories
 * @return array<int, array{id:int,name:string,coursecount:int,icon:string}>
 */
function theme_nit_get_categories(int $limit = 4): array {
    global $OUTPUT;
    $icons = ['💻', '📊', '🎨', '🗣️', '🔬', '💡', '📚', '🎯'];

    // Moodle categories have no image field of their own, so use the site logo as
    // the fallback image ("if the category has no image, show the site logo").
    $logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
    $logourl = $logo ? $logo->out(false) : '';

    // Only main (top-level) categories, in display order, visible to this user.
    // core_course_category::top()->get_children() is permission- and visibility-aware.
    $toplevel = core_course_category::top()->get_children(['limit' => $limit]);

    $categories = [];
    $i = 0;
    foreach ($toplevel as $cat) {
        $categories[] = [
            'id' => (int) $cat->id,
            'name' => $cat->get_formatted_name(),
            // Count courses in this category AND all its subcategories, so a main
            // category whose courses live only in subcategories still shows a real total.
            'coursecount' => $cat->get_courses_count(['recursive' => true]),
            'icon' => $icons[$i % count($icons)],
            'image' => $logourl,
            // Build the details-page URL here so the frontend never has to guess wwwroot.
            'url' => (new moodle_url('/local/nit_category/index.php', ['id' => $cat->id]))->out(false),
        ];
        $i++;
    }

    return $categories;
}
