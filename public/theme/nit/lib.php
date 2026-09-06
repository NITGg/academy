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
        // Aligned to Brand-Colors Group 1 (Slate blue). No red anywhere; the old
        // gold marketing accent is now a soft blue so the whole palette is one
        // calm, cohesive cool family. (Most of these are aliased onto the brand
        // layer in _root.scss; the defaults here keep the legacy vars, the login
        // gradient companion, and the mobile export consistent with the brand.)
        'primary'          => ['group' => 'Brand', 'label' => 'Primary', 'default' => '#5488c4'],
        'secondary'        => ['group' => 'Brand', 'label' => 'Secondary', 'default' => '#33475e'],
        'accentgold'       => ['group' => 'Brand', 'label' => 'Accent (link / underline)', 'default' => '#7fabdb'],
        'accentgolddark'   => ['group' => 'Brand', 'label' => 'Accent (dark / gradient)', 'default' => '#5488c4'],
        'accentteal'       => ['group' => 'Brand', 'label' => 'Accent teal', 'default' => '#2f9e8f'],

        // --- Navbar : the slate top bar ----------------------------------------
        'navbarbg'         => ['group' => 'Navbar', 'label' => 'Navbar background', 'default' => '#0c141f'],
        'navbarsurface'    => ['group' => 'Navbar', 'label' => 'Navbar surface (buttons)', 'default' => '#121e2d'],
        'navbarborder'     => ['group' => 'Navbar', 'label' => 'Navbar border', 'default' => '#223244'],
        'navbaraccent'     => ['group' => 'Navbar', 'label' => 'Navbar accent', 'default' => '#7fabdb'],
        'navbaraccenthover' => ['group' => 'Navbar', 'label' => 'Navbar accent hover', 'default' => '#a9c8e6'],
        'navbartext'       => ['group' => 'Navbar', 'label' => 'Navbar text', 'default' => '#eef3f9'],
        'navbarpanel'      => ['group' => 'Navbar', 'label' => 'Dropdown panel background', 'default' => '#121e2d'],
        'navbarpaneltext'  => ['group' => 'Navbar', 'label' => 'Dropdown item text', 'default' => '#94a3b8'],
        'navbarpanelborder' => ['group' => 'Navbar', 'label' => 'Dropdown divider', 'default' => '#223244'],

        // --- Neutrals : surfaces, text, borders (the dark slate ground) --------
        'background'       => ['group' => 'Neutrals', 'label' => 'Background', 'default' => '#0c141f'],
        'surface'          => ['group' => 'Neutrals', 'label' => 'Surface (subtle fill)', 'default' => '#121e2d'],
        'textprimary'      => ['group' => 'Neutrals', 'label' => 'Text primary', 'default' => '#eef3f9'],
        'textsecondary'    => ['group' => 'Neutrals', 'label' => 'Text secondary', 'default' => '#94a3b8'],
        'border'           => ['group' => 'Neutrals', 'label' => 'Border', 'default' => '#223244'],

        // --- Semantic : status colours (red-free — danger is a warm orange) -----
        'success'          => ['group' => 'Semantic', 'label' => 'Success', 'default' => '#3fa877'],
        'warning'          => ['group' => 'Semantic', 'label' => 'Warning', 'default' => '#d8c24e'],
        'error'            => ['group' => 'Semantic', 'label' => 'Error / danger', 'default' => '#d07f43'],
        'info'             => ['group' => 'Semantic', 'label' => 'Info', 'default' => '#5fb0c9'],

        // --- Dark : the always-dark marketing bands (aligned to Group 1 slate) --
        'darkprimary'         => ['group' => 'Dark', 'label' => 'Dark primary', 'default' => '#5488c4'],
        'darkbackground'      => ['group' => 'Dark', 'label' => 'Dark background', 'default' => '#0c141f'],
        'darksurface'         => ['group' => 'Dark', 'label' => 'Dark surface (card)', 'default' => '#121e2d'],
        'darksurfacevariant'  => ['group' => 'Dark', 'label' => 'Dark surface (raised)', 'default' => '#1c2a3a'],
        'darktextprimary'     => ['group' => 'Dark', 'label' => 'Dark text primary', 'default' => '#eef3f9'],
        'darktextsecondary'   => ['group' => 'Dark', 'label' => 'Dark text secondary', 'default' => '#94a3b8'],
        'darkborder'          => ['group' => 'Dark', 'label' => 'Dark border', 'default' => '#223244'],

        // --- Categories : three interchangeable colour styles for the category
        // details page (local_nit_category). Aligned 1:1 with the three Brand
        // Colors groups so a category page matches its brand group:
        //   Style 1 = Group 1 (Slate blue) · Style 2 = Group 2 (Teal)
        //   Style 3 = Group 3 (Indigo). Eight tokens each: 4 text + 4 background.
        'cat_style1_text1' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 1', 'default' => '#eef3f9'],
        'cat_style1_text2' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 2', 'default' => '#94a3b8'],
        'cat_style1_text3' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 3', 'default' => '#7fabdb'],
        'cat_style1_text4' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 4', 'default' => '#0c141f'],
        'cat_style1_bg1'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 1', 'default' => '#0c141f'],
        'cat_style1_bg2'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 2', 'default' => '#121e2d'],
        'cat_style1_bg3'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 3', 'default' => '#1c2a3a'],
        'cat_style1_bg4'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 4', 'default' => '#5488c4'],

        'cat_style2_text1' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 1', 'default' => '#eef5f4'],
        'cat_style2_text2' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 2', 'default' => '#8aa5a2'],
        'cat_style2_text3' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 3', 'default' => '#58bdad'],
        'cat_style2_text4' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 4', 'default' => '#06201d'],
        'cat_style2_bg1'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 1', 'default' => '#0a1a1a'],
        'cat_style2_bg2'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 2', 'default' => '#102727'],
        'cat_style2_bg3'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 3', 'default' => '#143231'],
        'cat_style2_bg4'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 4', 'default' => '#2f9e8f'],

        'cat_style3_text1' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 1', 'default' => '#efedf7'],
        'cat_style3_text2' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 2', 'default' => '#9691b3'],
        'cat_style3_text3' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 3', 'default' => '#a99ee2'],
        'cat_style3_text4' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 4', 'default' => '#11101c'],
        'cat_style3_bg1'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 1', 'default' => '#11101c'],
        'cat_style3_bg2'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 2', 'default' => '#1a182d'],
        'cat_style3_bg3'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 3', 'default' => '#201e34'],
        'cat_style3_bg4'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 4', 'default' => '#8478cf'],
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
 * The 23 semantic roles every Brand-Colors group is built from.
 *
 * This is the clean, small semantic layer that replaces the sprawling
 * theme_nit_colour_palette(): a component references a role by name (Primary,
 * Surface, Text primary, …) and never a raw colour. `label` is the display name
 * and `usage` is a list of the concrete UI things that should use the colour —
 * rendered as chips on the gallery's Brand Colors tab. `default` here is only a
 * red-free FALLBACK (the Group 1 / Slate-blue values): every group overrides all
 * 23 roles in theme_nit_brand_group_defaults(), so a role default is used only if
 * a group ever omits a role. The Hover Background / Hover Text roles carry the
 * explicit hover colours (other opacity variants are still derived in SCSS, see
 * scss/foundation/_brand.scss).
 *
 * @return array<string, array{label:string, usage:string[], default:string}>
 */
function theme_nit_brand_roles(): array {
    return [
        'primary'           => ['label' => 'Primary', 'usage' => ['background main button', 'checked toggles', 'progress fill', 'notification dots'], 'default' => '#5488c4'],
        'secondary'         => ['label' => 'Secondary', 'usage' => ['background secondary button'], 'default' => '#1c2a3a'],
        // Text drawn ON a filled button, one role per button colour. These are
        // roles rather than "whatever the body ink happens to be" because the
        // right answer depends on the fill, not on the page: a light group fills
        // its main button with a dark blue and needs white on it, a dark group
        // fills it with a light blue and needs near-black. Getting that from
        // "Text primary" was wrong by construction in half the groups.
        'onprimary'         => ['label' => 'Text on main button', 'usage' => ['label inside a filled main button', 'text on any primary fill'], 'default' => '#eef3f9'],
        'onsecondary'       => ['label' => 'Text on secondary button', 'usage' => ['label inside a secondary button', 'label inside an outline-secondary button'], 'default' => '#eef3f9'],
        'accent'            => ['label' => 'Accent', 'usage' => ['none text'], 'default' => '#5488c4'],
        'accenttext'        => ['label' => 'Accent Text', 'usage' => ['text of links', 'important words', 'underlines'], 'default' => '#7fabdb'],
        'background'        => ['label' => 'Background', 'usage' => ['page background'], 'default' => '#0c141f'],
        'background2'       => ['label' => 'Second background', 'usage' => ['alternate page sections', 'bands lifted off the page ground'], 'default' => '#101a27'],
        'navbarbackground1' => ['label' => 'Navbar background 1', 'usage' => ['navbar background'], 'default' => '#0c141f'],
        'navbarbackground2' => ['label' => 'Navbar background 2', 'usage' => ['navbar background — second colour (reserved, not consumed yet)'], 'default' => '#121e2d'],
        'footerbackground1' => ['label' => 'Footer background 1', 'usage' => ['footer background'], 'default' => '#0c141f'],
        'footerbackground2' => ['label' => 'Footer background 2', 'usage' => ['footer background — second colour (reserved, not consumed yet)'], 'default' => '#121e2d'],
        'navbariconcolor'   => ['label' => 'Navbar icon color', 'usage' => ['navbar icons — search, language, messages, notifications, gear', 'notification panel action icons'], 'default' => '#eef3f9'],
        'navbariconbg'      => ['label' => 'Navbar icon background', 'usage' => ['navbar icon hover pad — the icons have no background at rest'], 'default' => '#121e2d'],
        'surface'           => ['label' => 'Surface', 'usage' => ['Cards background', 'dropdowns background', 'side menu background', 'inputs background', 'tooltips background', 'table background', 'page sections background'], 'default' => '#121e2d'],
        'textprimary'       => ['label' => 'Text primary', 'usage' => ['main normal text', 'text in buttons', 'text in inputs', 'navbar text', 'navbar underline'], 'default' => '#eef3f9'],
        'textsecondary'     => ['label' => 'Text secondary', 'usage' => ['secondary normal text', 'placeholders'], 'default' => '#94a3b8'],
        'borderprimary'     => ['label' => 'Border primary', 'usage' => ['main border color'], 'default' => '#223244'],
        'bordersecondary'   => ['label' => 'Border secondary', 'usage' => ['secondary border color'], 'default' => '#33475e'],
        'hoverbackground'   => ['label' => 'Hover Background', 'usage' => ['hover background'], 'default' => '#16222f'],
        'hovertext'         => ['label' => 'Hover Text', 'usage' => ['hover text'], 'default' => '#7fabdb'],
        'error'             => ['label' => 'Error', 'usage' => ['Errors', 'danger / destructive actions', 'invalid fields'], 'default' => '#d07f43'],
        'success'           => ['label' => 'Success', 'usage' => ['Success', 'enrolled / active / paid', 'positive states'], 'default' => '#3fa877'],
        'warning'           => ['label' => 'Warning', 'usage' => ['Warnings', 'caution', 'pending / expiring'], 'default' => '#d8c24e'],
        'info'              => ['label' => 'Info', 'usage' => ['Neutral notices', 'tips', 'hints'], 'default' => '#5fb0c9'],
    ];
}

/**
 * The ordered Brand-Colors groups.
 *
 * A "group" is a complete named set of all 23 roles — a swappable palette.
 * Group 1 is the site-wide default; a component can opt into another group via
 * the matching wrapper class (`.nit-brand-2`, `.nit-brand-3`), keeping the same
 * variable names but resolving them from that group's values. Groups 2 and 3
 * seed equal to Group 1 and are tuned later on the gallery page.
 *
 * @return array<string, string> group key (g1/g2/g3) => display label
 */
function theme_nit_brand_groups(): array {
    return [
        'g1' => 'Group 1',
        'g2' => 'Group 2',
        'g3' => 'Group 3',
        // 4 and 5 are a matched pair, built for the navbar light/dark switch:
        // one palette at two light levels, so toggling changes how bright the
        // site is and not which site it is. Their labels say so, because the two
        // selects on the "Change style" tab are where that choice gets made.
        'g4' => 'Group 4 (Daylight — light)',
        'g5' => 'Group 5 (Graphite — dark)',
    ];
}

/**
 * The Brand-Colors group assigned to a category (for the category details page).
 *
 * Admins map only the MAIN (top-level) categories to groups on the gallery
 * "Category styles" tab; the map is stored as the theme_nit config
 * `nit_category_groups` (JSON `{topcatid: "g2", …}`). A category page resolves to
 * the group of its top-level ancestor, so every subcategory / filtered view under
 * a main category inherits that main category's group. Unassigned → Group 1.
 *
 * @param int $categoryid the category whose page is being rendered
 * @return string one of the group keys from theme_nit_brand_groups() (g1/g2/g3)
 */
function theme_nit_category_brand_group(int $categoryid): string {
    static $map = null;
    if ($map === null) {
        $raw = get_config('theme_nit', 'nit_category_groups');
        $map = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];
    }
    if (empty($map)) {
        return 'g1';
    }
    // Styles are assigned per main category → resolve to the top-level ancestor.
    $topid = $categoryid;
    try {
        $cat = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if ($cat) {
            $parents = $cat->get_parents();      // Ancestor ids, top-most first, excludes self.
            $topid = !empty($parents) ? (int) $parents[0] : (int) $categoryid;
        }
    } catch (\Throwable $e) {
        $topid = $categoryid;
    }
    $group = $map[$topid] ?? 'g1';
    return array_key_exists($group, theme_nit_brand_groups()) ? $group : 'g1';
}

/**
 * The CSS body/wrapper class that switches an element to a brand group.
 *
 * Group 1 is the default layer (no class); groups 2/3 map to the switch classes
 * declared in scss/foundation/_brand.scss.
 *
 * @param string $group a group key (g1/g2/g3)
 * @return string '' | 'nit-brand-2' | 'nit-brand-3'
 */
function theme_nit_brand_group_class(string $group): string {
    $classes = [
        'g1' => '',
        'g2' => 'nit-brand-2',
        'g3' => 'nit-brand-3',
        'g4' => 'nit-brand-4',
        'g5' => 'nit-brand-5',
    ];
    return $classes[$group] ?? '';
}

/**
 * The cookie the light/dark switch remembers a visitor's choice in.
 *
 * A cookie rather than a user preference because the switch has to work for the
 * logged-out catalogue too, and because the button re-skins the page in the
 * browser (it only swaps a class on <html>) — a preference would need an AJAX
 * round trip to store what the next request has to read back anyway.
 */
if (!defined('THEME_NIT_MODE_COOKIE')) {
    define('THEME_NIT_MODE_COOKIE', 'nit_mode');
}

/**
 * The two display modes of the light/dark switch, in switch order.
 *
 * @return array<string, string> mode key => the mode it toggles to
 */
function theme_nit_modes(): array {
    return ['light' => 'dark', 'dark' => 'light'];
}

/**
 * Which Brand-Colors group each display mode renders in.
 *
 * The light/dark button does not carry a palette of its own: it selects one of
 * the three Brand-Colors groups, exactly like the category styles do. An admin
 * maps mode → group on the gallery "Change style" tab ("Site styles" section);
 * the map is stored as the theme_nit config `nit_mode_groups`
 * (JSON `{"light":"g1","dark":"g2"}`).
 *
 * Defaults: light → Group 1 (the site's normal look), dark → Group 2.
 *
 * @return array<string, string> mode key (light/dark) => group key (g1/g2/g3)
 */
function theme_nit_mode_groups(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $defaults = ['light' => 'g1', 'dark' => 'g2'];
    $raw = get_config('theme_nit', 'nit_mode_groups');
    $saved = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];

    $groups = theme_nit_brand_groups();
    $map = [];
    foreach ($defaults as $mode => $default) {
        $group = $saved[$mode] ?? $default;
        $map[$mode] = array_key_exists($group, $groups) ? $group : $default;
    }
    return $map;
}

/**
 * The display mode this request should render in.
 *
 * Read from the visitor's cookie; anything we do not recognise (and the very
 * first visit) is "light", so the site looks the way it always has until
 * somebody presses the button.
 *
 * @return string 'light' | 'dark'
 */
function theme_nit_current_mode(): string {
    $mode = isset($_COOKIE[THEME_NIT_MODE_COOKIE]) ? (string) $_COOKIE[THEME_NIT_MODE_COOKIE] : '';
    return array_key_exists($mode, theme_nit_modes()) ? $mode : 'light';
}

/**
 * The classes the current display mode puts on the <html> element.
 *
 * Two things: `nit-mode-light` / `nit-mode-dark` (a hook for anything that has
 * to know which mode it is in), and the Brand-Colors group switch class for the
 * group that mode maps to.
 *
 * The classes go on <html>, not <body>, deliberately. The group switch works by
 * re-pointing the `--nit-brand-*` custom properties, and the legacy `--nit-*`
 * aliases are declared on `:root` — i.e. on <html> itself. A custom property
 * holding a var() is substituted on the element that declares it, so an alias on
 * <html> would freeze to Group 1 if the switch class sat any lower in the tree
 * (the site would recolour only half-way). Declaring the switch on the same
 * element the aliases live on makes them resolve from the active group.
 *
 * @return string space-separated class list (never empty)
 */
function theme_nit_mode_classes(): string {
    return theme_nit_mode_classes_for(theme_nit_current_mode());
}

/**
 * The <html> classes a GIVEN mode owns.
 *
 * Split out from theme_nit_mode_classes() so the navbar switch can ask for the
 * other mode's set and swap the two in the browser. One function builds both,
 * because the server-rendered class list and the one the button applies must
 * never be able to disagree.
 *
 * @param string $mode 'light' | 'dark'
 * @return string space-separated class list
 */
function theme_nit_mode_classes_for(string $mode): string {
    $group = theme_nit_mode_groups()[$mode] ?? 'g1';

    // `nit-chrome-light` / `nit-chrome-dark` says whether the BAR is light, which
    // is not the same question as which mode the visitor picked: a group is free
    // to run a dark bar over a light page. Measured from the group's own navbar
    // colour (theme_nit_group_is_light), so it stays true when an admin retunes
    // the palette. CSS that has to contrast with the bar — anything drawn ON it
    // whose own colour we do not control, a user's profile picture above all —
    // keys off this rather than naming a group number.
    $chrome = theme_nit_group_is_light($group) ? 'nit-chrome-light' : 'nit-chrome-dark';

    return trim('nit-mode-' . $mode . ' ' . $chrome . ' ' . theme_nit_brand_group_class($group));
}

/**
 * Per-group default overrides for the Brand-Colors palette.
 *
 * Groups 1-3 are each a complete, self-contained theme with its own
 * distinct — but deliberately calm and low-strain — mood, so an admin can skin a
 * category with a genuinely different look by switching groups. All three are
 * dark palettes tuned for eye comfort: desaturated accents (no harsh, fully
 * saturated hues), gentle contrast, and semantic colours (error/success/…) kept
 * softened but still readable.
 *
 * No red anywhere — not as a brand accent and not for the semantic "error"
 * role: danger is signalled with a warm orange and caution with yellow, so the
 * two stay distinguishable while keeping the palette entirely red-free.
 *
 *   - Group 1 — Slate blue : calm, cool blue accent on a deep slate ground.
 *   - Group 2 — Teal / Deep sea : cool, restful green-teal on near-black teal.
 *   - Group 3 — Indigo / Lavender : soft violet on a deep indigo ground.
 *
 * Groups 4 and 5 are the exception to "all three are dark", and to "each group is
 * its own mood": they are ONE palette at two light levels, built for the navbar
 * light/dark switch. A switch between modes should change how bright the site is,
 * not which site it is, so they share an accent and differ only in ground.
 *
 *   - Group 4 — Daylight (LIGHT) : the only light group. Near-white page, white
 *     cards, deep slate ink, azure accent — under a DARK navigation bar and
 *     footer (the site logo is white-on-transparent and vanishes on a light bar).
 *   - Group 5 — Graphite (DARK) : the same palette turned down. Neutral graphite
 *     ground — which is also what keeps it apart from groups 1-3, all of which
 *     are coloured darks.
 *
 * Being light, Group 4 is the one group whose text-on-primary cannot be the body
 * ink — see the `--nit-brand-on-primary` note on `.nit-brand-4` in
 * scss/foundation/_brand.scss. It is also why `--nit-navbartext` reads the navbar
 * icon role rather than the body ink (scss/foundation/_root.scss).
 *
 * A role missing from a group falls back to theme_nit_brand_roles()['default'].
 *
 * @return array<string, array<string, string>> group key (g1..g5) => role => #hex
 */
function theme_nit_brand_group_defaults(): array {
    return [
        // --- Group 1 : Slate blue (calm, cool). -------------------------------
        'g1' => [
            'primary'           => '#5488c4',
            'secondary'         => '#1c2a3a',
            'onprimary'         => '#eef3f9',
            'onsecondary'       => '#eef3f9',
            'accent'            => '#5488c4',
            'accenttext'        => '#7fabdb',
            'background'        => '#0c141f',
            'background2'       => '#101a27',
            'navbarbackground1' => '#0c141f',
            'navbarbackground2' => '#121e2d',
            'footerbackground1' => '#0c141f',
            'footerbackground2' => '#121e2d',
            'surface'           => '#121e2d',
            'textprimary'       => '#eef3f9',
            'navbariconcolor'   => '#eef3f9',
            'navbariconbg'      => '#121e2d',
            'textsecondary'     => '#94a3b8',
            'borderprimary'     => '#223244',
            'bordersecondary'   => '#33475e',
            'hoverbackground'   => '#16222f',
            'hovertext'         => '#7fabdb',
            'error'             => '#d07f43',
            'success'           => '#3fa877',
            'warning'           => '#d8c24e',
            'info'              => '#5fb0c9',
        ],
        // --- Group 2 : Teal / Deep sea (cool, restful). -----------------------
        'g2' => [
            'primary'           => '#2f9e8f',
            'secondary'         => '#12302e',
            'onprimary'         => '#eef5f4',
            'onsecondary'       => '#eef5f4',
            'accent'            => '#2f9e8f',
            'accenttext'        => '#58bdad',
            'background'        => '#0a1a1a',
            'background2'       => '#0d2020',
            'navbarbackground1' => '#0a1a1a',
            'navbarbackground2' => '#102727',
            'footerbackground1' => '#0a1a1a',
            'footerbackground2' => '#102727',
            'surface'           => '#102727',
            'textprimary'       => '#eef5f4',
            'navbariconcolor'   => '#eef5f4',
            'navbariconbg'      => '#102727',
            'textsecondary'     => '#8aa5a2',
            'borderprimary'     => '#1f3f3d',
            'bordersecondary'   => '#2f5a56',
            'hoverbackground'   => '#143231',
            'hovertext'         => '#6ccabb',
            'error'             => '#d07f43',
            'success'           => '#46b085',
            'warning'           => '#d8c24e',
            'info'              => '#6aa6c9',
        ],
        // --- Group 3 : Indigo / Lavender (soft, cool violet). -----------------
        'g3' => [
            'primary'           => '#8478cf',
            'secondary'         => '#26243d',
            'onprimary'         => '#efedf7',
            'onsecondary'       => '#efedf7',
            'accent'            => '#8478cf',
            'accenttext'        => '#a99ee2',
            'background'        => '#11101c',
            'background2'       => '#151425',
            'navbarbackground1' => '#11101c',
            'navbarbackground2' => '#1a182d',
            'footerbackground1' => '#11101c',
            'footerbackground2' => '#1a182d',
            'surface'           => '#1a182d',
            'textprimary'       => '#efedf7',
            'navbariconcolor'   => '#efedf7',
            'navbariconbg'      => '#1a182d',
            'textsecondary'     => '#9691b3',
            'borderprimary'     => '#2d2a45',
            'bordersecondary'   => '#433d64',
            'hoverbackground'   => '#201e34',
            'hovertext'         => '#b4a9ee',
            'error'             => '#d07f43',
            'success'           => '#57b39a',
            'warning'           => '#d8c24e',
            'info'              => '#7fa6d6',
        ],
        // --- Groups 4 and 5 : Daylight / Graphite. ----------------------------
        // Unlike groups 1-3 these were not picked by eye. They are two readings
        // of ONE system, built in OKLCH so the steps are perceptually even, then
        // checked pair by pair for contrast. The generator is checked in beside
        // them — `node theme/nit/docs/palette-check.js` reprints these hexes and
        // the whole contrast table, so a change here can be re-verified rather
        // than argued about.
        //
        // Two ramps, sampled at fixed OKLCH lightnesses:
        //   neutral  hue 258, chroma 0.005-0.014  (barely cool, never tinted)
        //     N0  #fbfdff   N50 #f6f8fb   N100 #f1f3f6  N200 #e6e8eb  N300 #d5d9df
        //     N350 #c7cbd0  N400 #a7abb1  N500 #7b8189  N600 #5e646b  N700 #43484f
        //     N800 #2a2e35  N850 #1f232a  N900 #14191f  N950 #0d1117
        //   accent   hue 256 (azure)
        //     A200 #c0dafc  A300 #98c0f7  A400 #71a7ef  A500 #4687db
        //     A600 #2368bd  A700 #0e509d  A800 #073b78
        //
        // Light reads the ramps from one end, dark from the other, so switching
        // mode changes how bright the site is and not which site it is. The
        // neutrals carry almost no chroma on purpose: at these lightnesses a
        // tinted ground reads as a colour cast, which is what made the warm
        // palette these replaced look muddy.
        //
        // Every text/background pair in both groups is WCAG AA or better; the
        // numbers are in the block comment above each group.

        // --- Group 4 : Daylight (LIGHT). --------------------------------------
        // Light CONTENT under DARK CHROME. The dark navigation bar and footer are
        // not a leftover: the site logo is a white-on-transparent PNG (an admin
        // setting, not a theme asset), so a light bar erases the wordmark. A dark
        // header band is how most light interfaces are built anyway.
        //
        // Contrast: ink on page 16.6 · ink on card 17.7 · muted on page 5.6 ·
        // link on card 7.9 · white on the primary fill 5.6 · primary fill on the
        // page 5.2 · navbar text on the bar 17.8. All AA or AAA.
        'g4' => [
            'primary'           => '#2368bd',   // A600
            'secondary'         => '#e6e8eb',   // N200
            // Buttons: white on the dark-blue fill (5.6:1), and the body ink on
            // the pale grey secondary fill (16.6:1).
            'onprimary'         => '#ffffff',
            'onsecondary'       => '#14191f',   // N900
            'accent'            => '#2368bd',
            // A step darker than primary: on a light ground a link has to beat
            // the paper, not the ink.
            'accenttext'        => '#0e509d',   // A700
            'background'        => '#f6f8fb',   // N50
            'background2'       => '#f1f3f6',   // N100
            // Light chrome. This group is light THROUGHOUT — bar, page and band.
            // The bar is one step whiter than the page so it still reads as a
            // bar; the footer is one step greyer, which is what lets the curve
            // across its top be seen at all.
            'navbarbackground1' => '#ffffff',
            'navbarbackground2' => '#f6f8fb',   // N50
            'footerbackground1' => '#f1f3f6',   // N100
            'footerbackground2' => '#e6e8eb',   // N200
            'surface'           => '#ffffff',
            'textprimary'       => '#14191f',   // N900
            // The bar is light here, so its glyphs and its text (--nit-navbartext
            // follows this role) are the body ink, not near-white.
            'navbariconcolor'   => '#14191f',   // N900
            'navbariconbg'      => '#e6e8eb',   // N200 — the hover pad
            'textsecondary'     => '#5e646b',   // N600
            'borderprimary'     => '#d5d9df',   // N300
            'bordersecondary'   => '#a7abb1',   // N400
            'hoverbackground'   => '#f1f3f6',   // N100
            'hovertext'         => '#073b78',   // A800
            // Semantics sampled at OKLCH L48 — dark enough to read as text on
            // white. Still no red: danger is a deep burnt orange.
            'error'             => '#9a3c16',
            'success'           => '#00703e',
            'warning'           => '#775800',
            'info'              => '#006789',
        ],
        // --- Group 5 : Graphite (DARK). ---------------------------------------
        // Group 4 turned down — the same two ramps read from the dark end. The
        // ground is neutral graphite rather than a coloured dark, which is also
        // what keeps it apart from groups 1-3 (navy, teal, indigo).
        //
        // The primary is LIGHT here and its label is DARK, the way dark themes
        // are built: a fill dark enough to hold white text would be too dim to
        // see against the page. White on the mid azure was 3.65 — short of AA;
        // the page ground on the light azure is 7.6. See the
        // `--nit-brand-on-primary` line on `.nit-brand-5` in _brand.scss.
        //
        // Contrast: ink on page 17.8 · ink on card 14.8 · muted on card 6.8 ·
        // link on card 8.4 · dark label on the primary fill 7.6. All AA or AAA.
        'g5' => [
            'primary'           => '#71a7ef',   // A400
            'secondary'         => '#2a2e35',   // N800
            // Buttons: the page ground on the light-azure fill (7.6:1), and the
            // body ink on the dark grey secondary fill.
            'onprimary'         => '#0d1117',
            'onsecondary'       => '#f6f8fb',
            'accent'            => '#71a7ef',
            'accenttext'        => '#98c0f7',   // A300
            'background'        => '#0d1117',   // N950
            'background2'       => '#14191f',   // N900
            'navbarbackground1' => '#0d1117',
            'navbarbackground2' => '#14191f',
            'footerbackground1' => '#0d1117',
            'footerbackground2' => '#14191f',
            'surface'           => '#1f232a',   // N850
            'textprimary'       => '#f6f8fb',   // N50
            'navbariconcolor'   => '#f6f8fb',
            'navbariconbg'      => '#1f232a',
            'textsecondary'     => '#a7abb1',   // N400
            'borderprimary'     => '#2a2e35',   // N800
            'bordersecondary'   => '#43484f',   // N700
            'hoverbackground'   => '#14191f',   // N900
            'hovertext'         => '#c0dafc',   // A200
            // The same four hues as Group 4, sampled at L72 instead of L48.
            'error'             => '#e68867',
            'success'           => '#5cbc82',
            'warning'           => '#c89e3a',
            'info'              => '#3bb2e3',
        ],
    ];
}

/**
 * The full Brand-Colors palette: every group × every role, flattened.
 *
 * Keyed `g<N>_<role>` (e.g. `g1_primary`); the key becomes the config name
 * `brandcolour_<key>`, the SCSS var `$nit-b-<gkey>-<role>` and the per-group
 * custom property `--nit-brand-<gkey>-<role>`. Powers the Brand Colors editor,
 * the pre-SCSS emission and the _brand.scss custom-property layer.
 *
 * Each group's per-role default comes from theme_nit_brand_group_defaults(),
 * falling back to the shared role default (theme_nit_brand_roles()) when a group
 * does not override a role — so every group ships as a distinct palette.
 *
 * @return array<string, array{group:string, groupkey:string, role:string,
 *         label:string, usage:string, default:string}> ordered map keyed by token key
 */
function theme_nit_brand_palette(): array {
    $out = [];
    $roles = theme_nit_brand_roles();
    $groupdefaults = theme_nit_brand_group_defaults();
    foreach (theme_nit_brand_groups() as $gkey => $glabel) {
        foreach ($roles as $role => $meta) {
            $out[$gkey . '_' . $role] = [
                'group'    => $glabel,
                'groupkey' => $gkey,
                'role'     => $role,
                'label'    => $meta['label'],
                'usage'    => $meta['usage'],
                'default'  => $groupdefaults[$gkey][$role] ?? $meta['default'],
            ];
        }
    }
    return $out;
}

/**
 * The resolved value of one Brand-Colors token: the saved config, else default.
 *
 * @param string $key brand palette key (see theme_nit_brand_palette())
 * @return string a `#rrggbb` colour
 */
function theme_nit_brandcolour(string $key): string {
    $palette = theme_nit_brand_palette();
    $default = $palette[$key]['default'] ?? '#000000';
    $value = get_config('theme_nit', 'brandcolour_' . $key);
    return (is_string($value) && $value !== '') ? $value : $default;
}

/**
 * The whole resolved Brand-Colors palette, for the editor / export.
 *
 * @return array<int, array{key:string, group:string, groupkey:string, role:string,
 *         label:string, usage:string, value:string, default:string, iscustom:bool}>
 */
function theme_nit_brand_all(): array {
    $out = [];
    foreach (theme_nit_brand_palette() as $key => $meta) {
        $value = theme_nit_brandcolour($key);
        $out[] = [
            'key'      => $key,
            'group'    => $meta['group'],
            'groupkey' => $meta['groupkey'],
            'role'     => $meta['role'],
            'label'    => $meta['label'],
            'usage'    => $meta['usage'],
            'value'    => $value,
            'default'  => $meta['default'],
            'iscustom' => (strtolower($value) !== strtolower($meta['default'])),
        ];
    }
    return $out;
}

// -----------------------------------------------------------------------------
// Design-system export helpers.
//
// These back the public JSON API (design_system.php) that mirrors the four tabs
// of the gallery page for external clients (the Flutter / mobile app):
//   1. Brand Colors    → theme_nit_brand_export()
//   2. Category styles → theme_nit_category_styles_export()
//   3. Fonts           → theme_nit_fonts_export()
//   4. Components      → theme_nit_components_export()
// theme_nit_design_system_export() wraps all four into one payload. Everything
// returned is public branding metadata (already visible in the site CSS / DOM);
// nothing sensitive is exposed.
// -----------------------------------------------------------------------------

/**
 * Brand Colors tab, as data: every group with its resolved roles.
 *
 * One entry per group (Group 1/2/3). Group 1 is the site-wide default (applied
 * with no wrapper class); groups 2/3 are activated by wrapping an element in the
 * matching `class` (`nit-brand-2` / `nit-brand-3`), which re-resolves the same
 * `--nit-brand-<role>` custom properties to that group's values. `roles` lists
 * the site-wide role keys shared by every group, in order.
 *
 * @return array{roles: string[], groups: array<int, array{key:string, name:string,
 *         isdefault:bool, class:string, roles: array}>}
 */
function theme_nit_brand_export(): array {
    $groups = [];
    $gidx = [];
    foreach (theme_nit_brand_all() as $token) {
        $gkey = $token['groupkey'];
        if (!array_key_exists($gkey, $gidx)) {
            $gidx[$gkey] = count($groups);
            $groups[] = [
                'key'       => $gkey,
                'name'      => $token['group'],
                'isdefault' => ($gkey === 'g1'),
                'class'     => theme_nit_brand_group_class($gkey),
                'roles'     => [],
            ];
        }
        $groups[$gidx[$gkey]]['roles'][] = [
            'key'      => $token['key'],
            'role'     => $token['role'],
            'label'    => $token['label'],
            // The semantic custom property a component consumes. Its value is the
            // active group's value: inside a .nit-brand-2/3 wrapper it resolves to
            // that group; at the top level it resolves to Group 1.
            'cssvar'   => '--nit-brand-' . $token['role'],
            'value'    => $token['value'],
            'default'  => $token['default'],
            'iscustom' => $token['iscustom'],
            'usage'    => array_values($token['usage']),
        ];
    }

    // The ordered list of role keys (identical across groups).
    $roles = [];
    foreach (theme_nit_brand_roles() as $role => $unused) {
        $roles[] = $role;
    }

    return ['roles' => $roles, 'groups' => $groups];
}

/**
 * Category styles tab, as data: which brand group each main category uses.
 *
 * Only top-level (main) categories are assignable; subcategories inherit their
 * top ancestor's group. Visibility-aware: an anonymous request sees only the
 * categories a guest may see. `group` is the assigned group key (default `g1`);
 * `class` is the wrapper class that skins the category page from that group.
 *
 * @return array{groups: array<int, array{key:string, name:string}>,
 *         categories: array<int, array{id:int, name:string, group:string,
 *         groupname:string, class:string, isdefault:bool}>}
 */
function theme_nit_category_styles_export(): array {
    $grouplabels = theme_nit_brand_groups();

    $groups = [];
    foreach ($grouplabels as $gkey => $glabel) {
        $groups[] = ['key' => $gkey, 'name' => $glabel];
    }

    $raw = get_config('theme_nit', 'nit_category_groups');
    $map = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];

    $categories = [];
    foreach (core_course_category::top()->get_children() as $cat) {
        $gkey = $map[$cat->id] ?? 'g1';
        if (!array_key_exists($gkey, $grouplabels)) {
            $gkey = 'g1';
        }
        $categories[] = [
            'id'        => (int) $cat->id,
            'name'      => $cat->get_formatted_name(),
            'group'     => $gkey,
            'groupname' => $grouplabels[$gkey],
            'class'     => theme_nit_brand_group_class($gkey),
            'isdefault' => ($gkey === 'g1'),
        ];
    }

    return ['groups' => $groups, 'categories' => $categories];
}

/**
 * Fonts tab, as data: the per-language font family + downloadable file URL.
 *
 * One entry per language slot (en / ar). `family` is the CSS font-family the
 * compiled stylesheet exposes; `url` is the self-hosted font file (empty when no
 * font has been uploaded, in which case the site falls back to `fallback`).
 *
 * @return array<int, array{lang:string, label:string, family:string, rtl:bool,
 *         fallback:string, hasfont:bool, filename:string, url:string}>
 */
function theme_nit_fonts_export(): array {
    $theme = theme_config::load('nit');
    $out = [];
    foreach (theme_nit_font_slots() as $lang => $slot) {
        $filename = get_config('theme_nit', $slot['setting']);
        $hasfont = is_string($filename) && $filename !== '';
        // setting_file_url() already returns a full, self-hosted pluginfile URL
        // string (the same one theme_nit_font_scss() emits into the @font-face).
        $url = $hasfont ? (string) $theme->setting_file_url($slot['setting'], $slot['filearea']) : '';
        $out[] = [
            'lang'     => $lang,
            'label'    => get_string($slot['strkey'], 'theme_nit'),
            'family'   => $slot['family'],
            'rtl'      => (bool) $slot['rtl'],
            'fallback' => $slot['fallback'],
            'hasfont'  => $hasfont,
            'filename' => $hasfont ? ltrim($filename, '/') : '',
            'url'      => $url,
        ];
    }
    return $out;
}

/**
 * Components tab, as data: the global components showcased on the gallery page.
 *
 * A static inventory mirroring the gallery's Components tab — each component with
 * its named variants and the CSS classes that render them — so the mobile app
 * knows which shared UI elements exist and how they map to markup.
 *
 * @return array<int, array{name:string, variants: array<int, array{label:string, class:string}>}>
 */
function theme_nit_components_export(): array {
    return [
        [
            'name'     => 'Buttons',
            'variants' => [
                ['label' => 'Primary',   'class' => 'btn btn-primary'],
                ['label' => 'Secondary', 'class' => 'btn btn-secondary'],
                ['label' => 'Success',   'class' => 'btn btn-success'],
                ['label' => 'Warning',   'class' => 'btn btn-warning'],
                ['label' => 'Danger',    'class' => 'btn btn-danger'],
                ['label' => 'Outline',   'class' => 'btn btn-outline-primary'],
                ['label' => 'Disabled',  'class' => 'btn btn-primary', 'disabled' => true],
            ],
        ],
        [
            'name'     => 'Alerts',
            'variants' => [
                ['label' => 'Primary', 'class' => 'alert alert-primary'],
                ['label' => 'Success', 'class' => 'alert alert-success'],
                ['label' => 'Warning', 'class' => 'alert alert-warning'],
                ['label' => 'Danger',  'class' => 'alert alert-danger'],
            ],
        ],
    ];
}

/**
 * The whole design system as one payload — the four gallery tabs, as data.
 *
 * Backs design_system.php (the public mobile-facing JSON API).
 *
 * @return array{generated:int, site: array{name:string, url:string},
 *         brandcolors: array, categorystyles: array, fonts: array, components: array}
 */
function theme_nit_design_system_export(): array {
    global $SITE, $CFG;
    return [
        'generated'      => time(),
        'site'           => [
            'name' => format_string($SITE->fullname ?? ''),
            'url'  => $CFG->wwwroot,
        ],
        'brandcolors'    => theme_nit_brand_export(),
        'categorystyles' => theme_nit_category_styles_export(),
        'fonts'          => theme_nit_fonts_export(),
        'components'     => theme_nit_components_export(),
    ];
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

        // Declare the uploaded file as the *normal* weight only (not 100 900). Most
        // uploads are a single regular-weight file; claiming it covers 100-900 makes
        // the browser use that one file verbatim for bold too (headings look thin).
        // Declaring just `normal` lets the browser synthesize bold for heavier weights
        // (font-weight: 600/700/800 on headings), so a single-weight upload still bolds.
        $css .= '@font-face {'
            . 'font-family: "' . $family . '";'
            . 'src: url("' . $url . '") format("' . $format . '");'
            . 'font-weight: normal;'
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
 * How long (seconds) the front-page data helpers cache their result.
 *
 * Read from the theme setting `frontpagecachettl` (edited under Site admin →
 * Appearance → NIT settings). When the setting has never been saved, fall back
 * to 5 minutes; an explicit 0 disables caching (recompute every request).
 *
 * @return int seconds, or 0 to disable caching
 */
function theme_nit_frontpage_cache_ttl(): int {
    $raw = get_config('theme_nit', 'frontpagecachettl');
    if ($raw === false || $raw === null || $raw === '') {
        return 300;
    }
    return max(0, (int) $raw);
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

    // Short-lived cache: these whole-table counts change slowly but run on the
    // busiest page (Site home), so serve a cached copy. Lifetime is the
    // admin-configurable theme setting (0 = disabled).
    $ttl = theme_nit_frontpage_cache_ttl();
    $cache = \cache::make('theme_nit', 'frontpage');
    if ($ttl > 0) {
        $cached = $cache->get('sitestats');
        if (is_array($cached) && ($cached['expires'] ?? 0) > time()) {
            return $cached['data'];
        }
    }

    $categories = (int) $DB->count_records('course_categories', ['visible' => 1]);
    $topcategories = (int) $DB->count_records('course_categories', ['visible' => 1, 'parent' => 0]);

    $stats = [
        // Real courses (exclude the site "course" id 1) that are visible.
        'courses' => (int) $DB->count_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID]),
        'categories' => $categories,
        'topcategories' => $topcategories,
        'subcategories' => max(0, $categories - $topcategories),
        // Distinct users with at least one enrolment.
        'students' => (int) $DB->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {user_enrolments}'),
    ];

    if ($ttl > 0) {
        $cache->set('sitestats', ['expires' => time() + $ttl, 'data' => $stats]);
    }
    return $stats;
}

/**
 * The fee-enrolment price of a course, or '' when the course is free.
 *
 * A signed-in account with no profile country is quoted nothing at all — every price here is a
 * country price and that account has no country — so this returns the short "set your country"
 * label instead of an amount. It is deliberately NOT '': '' means free, and a paid course must
 * never be advertised as free. Callers that need to tell the two apart read the
 * `country_required` flag on the view-models below.
 *
 * @param int $courseid
 * @return string e.g. "250.00 EGP", the country-required label, or '' (free)
 */
function theme_nit_course_price(int $courseid): string {
    global $DB, $USER;

    if (class_exists('\local_payments\country_detector')
        && \local_payments\country_detector::pricing_blocked()
        && class_exists('\local_payments\price_resolver')
        && \local_payments\price_resolver::has_pricing($courseid)) {
        return get_string('countryrequired', 'local_payments');
    }

    // Prefer the local_payments plugin: it stores per-country course prices in
    // its own table (local_payments_course_prices), independent of Moodle's core
    // enrol methods. Resolve for the current user's country so an Egyptian user
    // sees the EGP price, etc. (the front-page grid cache is keyed by user +
    // country — see theme_nit_get_courses — so this stays cache-safe).
    if (class_exists('\local_payments\price_resolver')
        && \local_payments\price_resolver::has_pricing($courseid)) {
        try {
            $pricing = \local_payments\price_resolver::resolve(
                $courseid,
                !empty($USER->id) ? (int) $USER->id : null
            );
            if ((float) $pricing->price > 0) {
                return format_float($pricing->price, 2, false) . ' ' . $pricing->currency;
            }
            // Active rule with a zero price — treat as explicitly free.
            return '';
        } catch (\moodle_exception $e) {
            // resolve() throws when nothing matches the viewer's country AND the course has
            // no default rule. The course still HAS pricing (has_pricing() said so), so it is
            // not free — fall back to any active rule rather than advertising it as free.
            $fallback = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_active = 1',
                ['courseid' => $courseid],
                'price, currency',
                IGNORE_MULTIPLE
            );
            if ($fallback && (float) $fallback->price > 0) {
                return format_float($fallback->price, 2, false) . ' ' . $fallback->currency;
            }
            return '';
        }
    }

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
 * The course's lead teacher as a link to their public instructor profile.
 *
 * AC-4.5.17: "The instructor's name and photograph on the course details page
 * link to the public instructor profile." The public page is the one that shows
 * their background and nothing private (AC-4.5.16); Moodle's own /user/view.php
 * decides what to reveal from capabilities and site settings, which is exactly the
 * decision the specification does not want made per-site.
 *
 * Falls back to the plain name whenever there is no profile to link to - the
 * plugin absent, or the teacher not recognised as an instructor - so a caller can
 * use this everywhere the name appears without checking first.
 *
 * @param int $courseid
 * @return string HTML: a link, an escaped name, or ''
 */
function theme_nit_course_teacher_link(int $courseid): string {
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

    if (!$teacher) {
        return '';
    }

    $name = fullname($teacher);

    if (!class_exists('\local_nit_instructors\profile')
            || !\local_nit_instructors\profile::is_instructor((int) $teacher->id)) {
        return s($name);
    }

    return html_writer::link(
        new moodle_url('/local/nit_instructors/view.php', ['id' => $teacher->id]),
        s($name),
        ['class' => 'nit-instructor-link']
    );
}

/**
 * Visible courses as view-models for the front-page "courses" section.
 *
 * Exposed to JavaScript as `window.NIT_COURSES`; author-written NIT Section
 * blocks render them via a <template> (see the frontpage renderer).
 *
 * @param int $limit maximum number of courses
 * @return array<int, array{id:int,fullname:string,summary:string,url:string,image:string,price:string,is_free:bool,country_required:bool}>
 */
function theme_nit_get_courses(int $limit = 12): array {
    global $DB, $CFG, $OUTPUT;
    require_once($CFG->libdir . '/filelib.php');

    // Short-lived cache: assembling each card costs several per-course queries
    // (context, overview image, price, teacher). On the Site home that is an
    // N+1 pattern on the busiest page, so cache the assembled list (keyed by
    // limit). Lifetime is the admin-configurable theme setting (0 = disabled).
    // Purge theme caches to refresh sooner.
    global $USER;

    // Prices are resolved per country and the "enrolled" state is per user, so
    // the cached view-model must be keyed by user + country — otherwise the first
    // visitor's prices/enrolment would be served to everyone.
    $userid = (int) ($USER->id ?? 0);
    $country = '';
    if (class_exists('\local_payments\country_detector')) {
        $country = \local_payments\country_detector::detect($userid > 0 ? $userid : null);
    }

    // A card can now carry a translated string in its price slot (the "set your country"
    // label), so the language joins the key — otherwise the first visitor's language would be
    // served to the next one.
    $ttl = theme_nit_frontpage_cache_ttl();
    $cache = \cache::make('theme_nit', 'frontpage');
    $cachekey = 'courses_' . $limit . '_' . $userid . '_' . $country . '_' . current_language();
    if ($ttl > 0) {
        $cached = $cache->get($cachekey);
        if (is_array($cached) && ($cached['expires'] ?? 0) > time()) {
            return $cached['data'];
        }
    }

    $records = $DB->get_records_select(
        'course',
        'id <> :site AND visible = 1',
        ['site' => SITEID],
        'sortorder ASC',
        '*',
        0,
        $limit
    );

    // Asked once, not per card: is this viewer barred from seeing prices (signed in, no profile
    // country)? See local_payments\country_detector::pricing_blocked().
    $pricingblocked = class_exists('\local_payments\country_detector')
        && \local_payments\country_detector::pricing_blocked();

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

        $price = theme_nit_course_price((int) $c->id);
        $isenrolled = false;
        if ($userid > 0 && class_exists('\local_payments\enrollment_handler')) {
            $isenrolled = \local_payments\enrollment_handler::is_enrolled($userid, (int) $c->id);
        }
        // When this is on, `price` holds the "set your country" label rather than an amount,
        // and the course is paid — a block that wants to style the two differently branches
        // on this instead of parsing the string.
        $countryrequired = $pricingblocked
            && class_exists('\local_payments\price_resolver')
            && \local_payments\price_resolver::has_pricing((int) $c->id);
        $courses[] = [
            'id' => (int) $c->id,
            'fullname' => format_string($c->fullname, true, ['context' => $context]),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            'image' => $image,
            'price' => $price,
            'is_free' => ($price === ''),
            'country_required' => $countryrequired,
            'is_enrolled' => $isenrolled,
            'teacher' => theme_nit_course_teacher((int) $c->id),
        ];
    }

    if ($ttl > 0) {
        $cache->set($cachekey, ['expires' => time() + $ttl, 'data' => $courses]);
    }
    return $courses;
}

/**
 * The courses the current user is enrolled in, as view-models for the front-page "My courses" section.
 *
 * Exposed to JavaScript as `window.NIT_MY_COURSES`; a NIT Section block renders them via a
 * <template> keyed on `data-nit-my-courses` / `data-nit-my-course-card`. Per-user, so not cached.
 *
 * @param int $limit maximum number of courses
 * @return array<int, array{id:int,fullname:string,summary:string,url:string,image:string,price:string,is_free:bool,teacher:string}>
 */
function theme_nit_get_enrolled_courses(int $limit = 12): array {
    global $CFG, $OUTPUT, $USER;
    require_once($CFG->libdir . '/filelib.php');
    require_once($CFG->libdir . '/enrollib.php');

    if (empty($USER->id) || isguestuser()) {
        return [];
    }

    // The user's enrolled, visible courses (most recently accessed first).
    $records = enrol_get_my_courses('*', 'visible DESC, sortorder ASC');
    $fs = get_file_storage();
    $courses = [];
    foreach ($records as $c) {
        if ((int) $c->id === (int) SITEID || empty($c->visible)) {
            continue;
        }
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
                format_text($c->summary, $c->summaryformat ?? FORMAT_HTML, ['context' => $context, 'noclean' => true]),
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
            'price' => '',
            'is_free' => true,
            'teacher' => theme_nit_course_teacher((int) $c->id),
        ];
        if (count($courses) >= $limit) {
            break;
        }
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

    // The top bar has to be at least as tall as the logo it carries. The navbar
    // logo height is an admin setting now (Appearance → Logos), and
    // `$navbar-height` is what both the bar and the content offset beneath it are
    // measured from, so it is recomputed here — after _semantic.scss set it, and
    // before Bootstrap and Boost read it. Grow-only, so the default bar is
    // untouched until the logo actually outgrows it. See theme_nit_logo_slots().
    $scss .= '$navbar-height: ' . theme_nit_navbar_height() . "px;\n";

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

    // -------------------------------------------------------------------------
    // Brand Colors palette (the new semantic layer — gallery "Brand Colors" tab).
    // Emit every group's tokens as `$nit-b-<gkey>-<role>` SCSS variables (saved
    // value, else the seed default), so _brand.scss can publish them as
    // `--nit-brand-*` custom properties in the same combined stream.
    foreach (theme_nit_brand_palette() as $key => $meta) {
        $scss .= '$nit-b-' . str_replace('_', '-', $key) . ': ' . theme_nit_brandcolour($key) . ";\n";
    }

    // Drive the Bootstrap / semantic SCSS layer from Group 1 — the site-wide
    // default group. Unlike the legacy colour map above (applied only when the
    // admin saved a value), the brand always sets these, so buttons, cards,
    // alerts, links and the page body follow the brand out of the box. This is
    // the primary "rewire the whole site" lever; components that read these
    // Bootstrap vars need no edits. Group key => the SCSS variables it drives.
    $brandmap = [
        'g1_primary'         => ['primary'],
        'g1_secondary'       => ['secondary'],
        'g1_accenttext'      => ['link-color'],
        'g1_background'      => ['body-bg'],
        // Surface also drives form controls: Bootstrap's $input-bg is a fixed
        // light gray, so on the dark brand it would leave white text on a light
        // field (invisible). Point it at the brand surface instead.
        'g1_surface'         => ['card-bg', 'dropdown-bg', 'input-bg', 'nit-surface'],
        'g1_textprimary'     => ['body-color', 'dropdown-link-color', 'input-color', 'nit-ink'],
        'g1_textsecondary'   => ['text-muted'],
        'g1_borderprimary'   => ['border-color', 'card-border-color', 'dropdown-border-color', 'input-border-color', 'nit-line'],
        'g1_success'         => ['success'],
        'g1_warning'         => ['warning'],
        'g1_error'           => ['danger'],
        'g1_info'            => ['info'],
    ];
    foreach ($brandmap as $key => $targets) {
        foreach ($targets as $target) {
            $scss .= '$' . $target . ': $nit-b-' . str_replace('_', '-', $key) . ";\n";
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

    // _brand.scss must come before _root.scss: it declares the --nit-brand-*
    // custom properties (active layer + per-group + switch classes) that
    // _root.scss then aliases the legacy --nit-* properties onto.
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_brand.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_root.scss');
    // _corebridge.scss re-points the colours CORE baked from Group 1 (page
    // grounds, form fields, dimmed text, borders, the secondary button) at the
    // ACTIVE brand roles, so a group switch reaches core's own CSS and not just
    // ours. It has to come after _brand.scss for the roles, and it is emitted
    // late so its rules out-order core's at equal specificity. Under Group 1 it
    // resolves to the values core already baked, so nothing changes there.
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_corebridge.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_fonts.scss');

    // Admin-uploaded, per-language custom fonts (edited on the gallery page).
    $scss .= theme_nit_font_scss($theme);

    // Admin-uploaded pictures beside the log-in / sign-up cards.
    $scss .= theme_nit_auth_background_scss($theme);

    // How large to draw the site logo, per place (Appearance → Logos).
    $scss .= theme_nit_logo_scss();

    if (!empty($theme->settings->scss)) {
        $scss .= $theme->settings->scss;
    }

    return $scss;
}

/**
 * How big the site logo is drawn, in each place the site draws it.
 *
 * The logo file itself is uploaded on the core Logos page (Site administration →
 * Appearance → Logos). Nothing on that page ever said how *large* to draw it, so
 * the size lived in five hard-coded CSS rules and only a developer could change
 * it. Each slot below now publishes its height as a CSS custom property that the
 * matching rule reads via `var()`, and each has an admin setting sitting beside
 * the upload it belongs to (added by theme/nit/settings.php).
 *
 * `default` is the height the stylesheet already used, so a site that never
 * touches the new settings renders exactly as it did before they existed.
 *
 * On top of every slot sits one master multiplier — `theme_nit/logoscale`, a
 * percentage — because "make the logo bigger" is the whole request most of the
 * time and nobody should have to edit five numbers to answer it.
 *
 * @return array<string, array{setting:string, property:string, default:int}>
 */
function theme_nit_logo_slots(): array {
    return [
        // The corner of every page — theme/nit/templates/theme_boost/navbar.mustache,
        // sized by `.nit-navbar-logo` in scss/components/_navbar.scss. This is the
        // one an administrator means when they say "the logo".
        'navbar' => [
            'setting'  => 'logoheightnavbar',
            'property' => '--nit-logo-navbar',
            'default'  => 66,
        ],
        // The header of the phone-width primary drawer (Boost's
        // primary-drawer-mobile template): the same mark, in the menu that
        // replaces the navbar links on a small screen.
        'drawer' => [
            'setting'  => 'logoheightdrawer',
            'property' => '--nit-logo-drawer',
            'default'  => 100,
        ],
        // The brand column of the site footer — theme_nit/site_footer.
        'footer' => [
            'setting'  => 'logoheightfooter',
            'property' => '--nit-logo-footer',
            'default'  => 100,
        ],
        // Over the picture beside the account screens — core/login_panel.
        'authpanel' => [
            'setting'  => 'logoheightauthpanel',
            'property' => '--nit-logo-authpanel',
            'default'  => 48,
        ],
        // Inside the log-in / sign-up card itself — `.login-logo` in core/loginform.
        'authcard' => [
            'setting'  => 'logoheightauthcard',
            'property' => '--nit-logo-authcard',
            'default'  => 56,
        ],
    ];
}

/**
 * The alternative logo slots — one per core logo, for the other display mode.
 *
 * Core ships three logo settings on Appearance → Logos (`logo`, `logocompact`,
 * `favicon`) and exactly one of each. That is enough for a site with one look,
 * and not enough for a site with a light mode and a dark one: a mark drawn in
 * white for a navy bar disappears the moment the bar turns white, and no amount
 * of CSS can fix that honestly — filtering it would rewrite a picture the site
 * owner uploaded.
 *
 * So the admin says which mode the CORE logos were drawn for
 * (`theme_nit/logosfor`), and uploads the other set here. Pages rendering in the
 * mode the core logos suit use core's; pages in the other mode use these, and
 * fall back to core's when a slot is empty — an empty slot must never mean "no
 * logo", only "no separate version".
 *
 * Stored exactly like the fonts and the auth pictures: system context, itemid 0,
 * config `theme_nit/<setting>` = the filename, served by theme_nit_pluginfile().
 *
 * @return array<string, array{setting:string, filearea:string, basename:string,
 *         strkey:string, deskey:string, core:string}> keyed by slot
 */
function theme_nit_logo_variants(): array {
    return [
        'logo' => [
            'setting'  => 'altlogo',
            'filearea' => 'altlogo',
            'basename' => 'alt-logo',
            'strkey'   => 'altlogo',
            'deskey'   => 'altlogo_desc',
            'core'     => 'logo',
        ],
        'logocompact' => [
            'setting'  => 'altlogocompact',
            'filearea' => 'altlogocompact',
            'basename' => 'alt-logo-compact',
            'strkey'   => 'altlogocompact',
            'deskey'   => 'altlogocompact_desc',
            'core'     => 'logocompact',
        ],
        'favicon' => [
            'setting'  => 'altfavicon',
            'filearea' => 'altfavicon',
            'basename' => 'alt-favicon',
            'strkey'   => 'altfavicon',
            'deskey'   => 'altfavicon_desc',
            'core'     => 'favicon',
        ],
    ];
}

/**
 * Whether a brand group renders its CHROME light.
 *
 * Measured, not declared: the relative luminance of the group's "Navbar
 * background 1" decides it. That is the surface the logo is actually drawn on,
 * and it means a group an admin retunes on the Brand Colors tab starts or stops
 * counting as light on its own — nobody has to remember to flip a second switch.
 *
 * @param string $group group key (g1..g5)
 * @return bool true when the bar is light enough to need a dark mark
 */
function theme_nit_group_is_light(string $group): bool {
    $hex = theme_nit_brandcolour($group . '_navbarbackground1');
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return false;
    }
    // WCAG relative luminance: linearise each channel, then weight it.
    $lum = 0;
    foreach ([[0, 0.2126], [2, 0.7152], [4, 0.0722]] as [$offset, $weight]) {
        $c = hexdec(substr($hex, $offset, 2)) / 255;
        $c = $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        $lum += $c * $weight;
    }
    return $lum > 0.5;
}

/**
 * The brand group this request's page CHROME renders in.
 *
 * The navbar and the footer sit outside any category wrapper, so their group is
 * always the one the light/dark switch selected — not whatever a category page
 * re-skins its own content with.
 *
 * @return string group key (g1..g5)
 */
function theme_nit_active_chrome_group(): string {
    return theme_nit_mode_groups()[theme_nit_current_mode()] ?? 'g1';
}

/**
 * The URL of a logo, for the mode this page is rendering in.
 *
 * Returns the alternative upload when the page's chrome is light and the core
 * logos were drawn for dark (or the other way round) AND that alternative was
 * actually uploaded. Otherwise core's own logo, unchanged — including when the
 * admin has not said which mode the core logos are for, because guessing at
 * somebody's artwork is worse than leaving it alone.
 *
 * @param string $slot 'logo' | 'logocompact' | 'favicon'
 * @param int $maxwidth passed through to core's sizing for 'logo'/'logocompact'
 * @param int $maxheight
 * @return moodle_url|false the URL, or false exactly as core returns for "none"
 */
function theme_nit_logo_url(string $slot, int $maxwidth = 300, int $maxheight = 300, ?string $mode = null) {
    $variants = theme_nit_logo_variants();

    // Core's own URL for this slot, built the way renderer_base builds it — the
    // size is hidden in the file path and `theme_get_revision()` is the cache
    // buster. Rebuilt here rather than called on the renderer because this
    // function is also used outside a rendering context (settings, CLI checks).
    $corelogo = function () use ($slot, $maxwidth, $maxheight) {
        $filename = get_config('core_admin', $slot);
        if (empty($filename)) {
            return false;
        }
        $filepath = $slot === 'favicon' ? '64x64/' : ((int) $maxwidth . 'x' . (int) $maxheight) . '/';
        return \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'core_admin',
            $slot,
            $filepath,
            theme_get_revision(),
            $filename
        );
    };

    if (!isset($variants[$slot])) {
        return $corelogo();
    }

    // Which mode the core uploads were drawn for. Unset = "not answered", and
    // then nothing is swapped.
    $corelogosfor = get_config('theme_nit', 'logosfor');
    if ($corelogosfor !== 'dark' && $corelogosfor !== 'light') {
        return $corelogo();
    }

    // `$mode` lets a caller ask "what would this be in the OTHER mode?" — which is
    // what the navbar switch needs, so it can swap the picture in the browser
    // instead of making the visitor reload the page to see it change.
    $group = $mode !== null
        ? (theme_nit_mode_groups()[$mode] ?? theme_nit_active_chrome_group())
        : theme_nit_active_chrome_group();
    $pagemode = theme_nit_group_is_light($group) ? 'light' : 'dark';
    if ($pagemode === $corelogosfor) {
        return $corelogo();
    }

    // The other mode: use the alternative, if there is one.
    $variant = $variants[$slot];
    $filename = get_config('theme_nit', $variant['setting']);
    if (!is_string($filename) || $filename === '') {
        return $corelogo();
    }

    // The itemid of a theme stored file is the THEME REVISION, not 0 — that is
    // what makes the browser drop the old picture when a new one is uploaded,
    // and theme_config::setting_file_serve() will not resolve a path without it.
    // Built here rather than through setting_file_url() only because that returns
    // a protocol-relative string and every caller of this function wants the
    // moodle_url core's own accessors return.
    return \moodle_url::make_pluginfile_url(
        \context_system::instance()->id,
        'theme_nit',
        $variant['filearea'],
        theme_get_revision(),
        '/',
        ltrim($filename, '/')
    );
}

/**
 * The master logo multiplier, as a percentage.
 *
 * Clamped rather than validated away: PARAM_INT on the setting will happily
 * accept 0 or 9999, and neither the navbar nor the footer survives that. The
 * range is wide enough to be useful and narrow enough that no value can make
 * the logo vanish or push the page apart.
 *
 * @return int percentage, 25..400
 */
function theme_nit_logo_scale(): int {
    $scale = (int) get_config('theme_nit', 'logoscale');
    if ($scale <= 0) {
        $scale = 100;
    }
    return (int) min(400, max(25, $scale));
}

/**
 * The height one logo slot is drawn at, in pixels, with the master scale applied.
 *
 * `get_config()` returns false until admin_apply_default_settings() has run for
 * a newly added setting, so the slot's own default is the fallback — that is
 * what keeps the first page load after an upgrade looking like the last one
 * before it.
 *
 * @param string $slot a key of theme_nit_logo_slots()
 * @return int height in pixels, 8..400
 */
function theme_nit_logo_height(string $slot): int {
    $slots = theme_nit_logo_slots();
    if (!isset($slots[$slot])) {
        return 0;
    }

    $height = (int) get_config('theme_nit', $slots[$slot]['setting']);
    if ($height <= 0) {
        $height = $slots[$slot]['default'];
    }

    $height = (int) round($height * theme_nit_logo_scale() / 100);

    return (int) min(400, max(8, $height));
}

/**
 * How tall the top bar has to be to carry the logo it has been given.
 *
 * `$navbar-height` drives both the bar itself and the content offset underneath
 * it in every Boost layout (drawer.scss, layout.scss, navbar.scss), so a logo
 * taller than the bar would spill out of it rather than push it open. Grow-only:
 * the bar keeps its designed 100px until the logo plus its breathing room needs
 * more, so shrinking the logo never shrinks the header.
 *
 * @return int height in pixels
 */
function theme_nit_navbar_height(): int {
    // 12px of clearance above and below the mark, matching the space the 66px
    // default leaves in the 100px bar.
    return (int) max(100, theme_nit_logo_height('navbar') + 24);
}

/**
 * Publish the resolved logo heights as CSS custom properties.
 *
 * One `:root` block, one property per slot. The rules that consume them live in
 * the component stylesheets (scss/components/_navbar.scss, _sitefooter.scss,
 * _login.scss, _authpages.scss, _logo.scss), each keeping the original height as
 * the `var()` fallback so a stale or half-built stylesheet still draws a logo.
 *
 * @return string SCSS
 */
function theme_nit_logo_scss(): string {
    $scss = "\n:root {\n";
    foreach (theme_nit_logo_slots() as $key => $slot) {
        $scss .= '    ' . $slot['property'] . ': ' . theme_nit_logo_height($key) . "px;\n";
    }
    $scss .= "}\n";

    return $scss;
}

/**
 * The two pictures beside the account screens: one for log-in, one for sign-up.
 *
 * Both live in a system-context file area of their own and are uploaded on the
 * gallery page's "Log-in & sign-up" tab (theme/nit/gallery.php), stored exactly
 * the way the per-language fonts are — fixed basename, config `theme_nit/<setting>`
 * holding the filename — so theme_config::setting_file_url() and
 * theme_nit_pluginfile() serve them with no extra plumbing.
 *
 * `selector` is what separates the two. Every screen in this journey renders on
 * the same `pagelayout-login` layout, so the log-in picture is written against
 * the layout (which covers forgotten-password and the rest of the flow) and the
 * sign-up one against that page's body id. An id outranks the class, so sign-up
 * overrides log-in wherever both are set, and inherits it wherever it is not —
 * which is the behaviour there was before the pictures were split in two.
 *
 * The `login` slot deliberately keeps the `loginbackgroundimage` name Boost uses:
 * the setting started life as a Boost-shaped stored file and a site that already
 * has one uploaded keeps it.
 *
 * @return array<string, array{setting:string, filearea:string, input:string,
 *     basename:string, strkey:string, deskey:string, selector:string}>
 */
function theme_nit_auth_image_slots(): array {
    return [
        'login' => [
            'setting'  => 'loginbackgroundimage',
            'filearea' => 'loginbackgroundimage',
            'input'    => 'authimage_login',
            'basename' => 'login-background',
            'strkey'   => 'authimagelogin',
            'deskey'   => 'authimagelogin_desc',
            'selector' => 'body.pagelayout-login #page .login-layout-left',
        ],
        'signup' => [
            'setting'  => 'signupbackgroundimage',
            'filearea' => 'signupbackgroundimage',
            'input'    => 'authimage_signup',
            'basename' => 'signup-background',
            'strkey'   => 'authimagesignup',
            'deskey'   => 'authimagesignup_desc',
            'selector' => 'body#page-login-signup.pagelayout-login #page .login-layout-left',
        ],
    ];
}

/**
 * The languages the account-screen quote is written in.
 *
 * The same two the site's fonts are chosen per (theme_nit_font_slots()), and for
 * the same reason: a learner who switched the interface to Arabic should not be
 * read to in English by the one piece of copy on the screen that an administrator
 * wrote rather than translated.
 *
 * @return array<string, array{strkey:string, rtl:bool}>
 */
function theme_nit_auth_text_langs(): array {
    return [
        'en' => ['strkey' => 'fonten', 'rtl' => false],
        'ar' => ['strkey' => 'fontar', 'rtl' => true],
    ];
}

/**
 * The quote and its attribution for one language, as stored.
 *
 * Raw values — no formatting, no escaping. For display use
 * theme_nit_auth_panel_content(), which resolves the language and formats.
 *
 * @param string $lang language key from theme_nit_auth_text_langs()
 * @return array{quote:string, author:string}
 */
function theme_nit_auth_text(string $lang): array {
    return [
        'quote'  => (string) get_config('theme_nit', 'authpanelquote_' . $lang),
        'author' => (string) get_config('theme_nit', 'authpanelauthor_' . $lang),
    ];
}

/**
 * What the left panel of the account screens shows over the picture.
 *
 * Two things, both of which used to be baked into the photograph itself — which
 * meant re-exporting an image to correct a typo, and a logo that went stale the
 * moment the site's did.
 *
 * The logo is the navbar's, read through the same two renderer methods the navbar
 * template uses, so there is one logo on the site and not two. `get_logo_url()`
 * is the fallback: `should_display_navbar_logo()` is false on a site that has set
 * only the full logo and no compact one, and a blank corner is a worse answer
 * than the logo that is actually configured.
 *
 * The quote is resolved to the interface language, falling back to English and
 * then to whichever language has been filled in — an administrator who wrote only
 * one of the two gets that one everywhere rather than an empty card.
 *
 * @param renderer_base $output the renderer, for the logo URLs
 * @return array template context for theme_nit/core/login_panel
 */
function theme_nit_auth_panel_content($output): array {
    global $SITE;

    $logourl = $output->get_compact_logo_url(null, 120);
    if (empty($logourl)) {
        $logourl = $output->get_logo_url(null, 120);
    }

    $langs = theme_nit_auth_text_langs();
    $current = current_language();

    // Preference order: the interface language, then English, then anything that
    // has been written at all.
    $order = array_unique(array_merge(
        array_key_exists($current, $langs) ? [$current] : [],
        ['en'],
        array_keys($langs)
    ));

    $quote = '';
    $author = '';
    foreach ($order as $lang) {
        if (!array_key_exists($lang, $langs)) {
            continue;
        }
        $text = theme_nit_auth_text($lang);
        if (trim($text['quote']) !== '') {
            $quote = $text['quote'];
            $author = $text['author'];
            break;
        }
    }

    $context = context_system::instance();

    return [
        'haslogo'  => !empty($logourl),
        'logourl'  => !empty($logourl) ? $logourl->out(false) : '',
        'sitename' => format_string($SITE->fullname, true, ['context' => $context, 'escape' => false]),
        // format_string() escapes and runs the multilang filter, so a bilingual
        // site can also write one field with {mlang} markup instead of two.
        'hasquote'  => (trim($quote) !== ''),
        'quote'     => format_string($quote, true, ['context' => $context]),
        'hasauthor' => (trim($author) !== ''),
        'author'    => format_string($author, true, ['context' => $context]),
    ];
}

/**
 * CSS for the account-screen pictures.
 *
 * Boost writes a `.login-layout-left` rule of its own from theme_boost_get_extra_scss(),
 * which still runs here — theme_config::get_extra_scss_code() calls the parent's
 * callback and then the child's. What it cannot do is find a picture on its own:
 * theme_config::setting_file_url() builds its URL from the ACTIVE theme's name,
 * so with NIT running it looks up `theme_nit/loginbackgroundimage` and, until
 * that setting existed, found nothing and fell back to Boost's bundled
 * AI-generated photo. That fallback is what the log-in page had been showing,
 * watermark and all — and it is why the pictures belong to THIS theme rather than
 * to an upload against Boost's setting, which nothing would ever read.
 *
 * With a picture uploaded, Boost's own rule picks the log-in one up. Three things
 * it does not do are left here:
 *
 *  - the sign-up picture. Boost has no concept of a second one.
 *  - centre the crop. Boost sets `background-position: center` only for its
 *    default photo; an uploaded one gets the CSS initial value, which pins a
 *    cover-sized image to its top-left corner and cuts off everything else.
 *  - `content: none` on the watermark, per slot. Boost drops the "AI-generated
 *    image" caption once the log-in picture is uploaded, but it knows nothing
 *    about the sign-up one — so a site that sets only sign-up would otherwise
 *    caption its own photograph.
 *
 * Boost's rule and the log-in rule here are the same selector at the same
 * specificity, and this one is emitted second, so it wins on cascade order.
 * Nothing in Boost is edited or disabled; its rule is simply the one underneath.
 *
 * A slot with nothing uploaded emits nothing, so log-in falls back to Boost's
 * default (watermark included, because the caption is true of that image) and
 * sign-up falls back to log-in.
 *
 * @param theme_config $theme the theme config object (carries the settings)
 * @return string SCSS, possibly empty
 */
function theme_nit_auth_background_scss($theme): string {
    $scss = '';

    foreach (theme_nit_auth_image_slots() as $slot) {
        $url = $theme->setting_file_url($slot['setting'], $slot['filearea']);
        if (empty($url)) {
            continue;
        }

        // setting_file_url() returns a protocol-relative pluginfile URL string.
        // It is built from the file name the admin uploaded, so it can carry
        // quotes and parentheses that would end the CSS url() early.
        $safe = addcslashes((string) $url, "'\\");
        $sel = $slot['selector'];

        $scss .= "\n{$sel} {"
            . " background-image: url('{$safe}');"
            . " background-size: cover;"
            . " background-position: center;"
            . " }\n"
            // The caption belongs to Boost's default photo, and that photo is gone.
            . "{$sel}::after { content: none; }\n";
    }

    return $scss;
}

/**
 * Serve the theme's admin-uploaded fonts and account-screen pictures via pluginfile.php.
 *
 * Mirrors theme_boost_pluginfile(): each upload lives in a system-context file
 * area of its own — one per language for the fonts (theme_nit_font_slots()), one
 * per screen for the pictures (theme_nit_auth_image_slots()) — and the theme
 * revision, not the itemid, busts the cache. The gallery page (site:config only)
 * is the sole writer; this endpoint is a public, cache-able read of a self-hosted
 * file, exactly like the site logo. Public matters for the pictures in
 * particular: whoever is looking at the log-in screen is by definition not
 * logged in yet.
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
    $areas = array_merge(
        array_map(static fn($slot) => $slot['filearea'], theme_nit_font_slots()),
        array_map(static fn($slot) => $slot['filearea'], theme_nit_auth_image_slots()),
        // The per-mode logo uploads (Appearance → Logos). Without this the file
        // saves fine and then 404s when the page asks for it.
        array_map(static fn($v) => $v['filearea'], theme_nit_logo_variants())
    );

    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $areas, true)) {
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
 * @return array<int, array{id:int,name:string,names:array<string,string>,coursecount:int,icon:string,iconurl:string,image:string,url:string}>
 */
function theme_nit_get_categories(int $limit = 4): array {
    global $CFG, $OUTPUT;
    $icons = ['💻', '📊', '🎨', '🗣️', '🔬', '💡', '📚', '🎯'];

    // Moodle categories have no image field of their own, so local_nit_category adds
    // one (uploaded on the category's "Category image" tab, or taken from the first
    // image in its description). Guarded with function_exists so the front page still
    // renders if that plugin is ever absent.
    if (file_exists($CFG->dirroot . '/local/nit_category/lib.php')) {
        require_once($CFG->dirroot . '/local/nit_category/lib.php');
    }
    $hascategoryplugin = function_exists('local_nit_category_get_image_url');

    // Last resort, unchanged: "if the category has no image, show the site logo".
    $logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
    $logourl = $logo ? $logo->out(false) : '';

    // Only main (top-level) categories, in display order, visible to this user.
    // core_course_category::top()->get_children() is permission- and visibility-aware.
    $toplevel = core_course_category::top()->get_children(['limit' => $limit]);

    $categories = [];
    $i = 0;
    foreach ($toplevel as $cat) {
        // These are top-level categories, so there is no ancestor to inherit from; the
        // resolver still covers "uploaded file -> first image inside the description".
        $catimage = $hascategoryplugin ? local_nit_category_get_image_url((int) $cat->id) : '';

        // The category's own icon, when local_nit_category has one: `iconurl` for an
        // uploaded icon file, `icon` for an emoji. The rotating emoji below is only a
        // placeholder for categories that have set neither.
        $caticonurl = $hascategoryplugin ? local_nit_category_get_icon_url((int) $cat->id) : '';
        $caticonemoji = $hascategoryplugin ? local_nit_category_get_icon_emoji((int) $cat->id) : '';

        $categories[] = [
            'id' => (int) $cat->id,
            'name' => $cat->get_formatted_name(),
            // Every translation the name carries, not just the one this page is
            // being read in: the logo/categories block prints the Arabic and the
            // English name on the same card, so one filtered string is not enough.
            'names' => theme_nit_multilang_variants((string) $cat->name),
            // Count courses in this category AND all its subcategories, so a main
            // category whose courses live only in subcategories still shows a real total.
            'coursecount' => $cat->get_courses_count(['recursive' => true]),
            'icon' => $caticonemoji !== '' ? $caticonemoji : $icons[$i % count($icons)],
            'iconurl' => $caticonurl,
            'image' => $catimage !== '' ? $catimage : $logourl,
            // Build the details-page URL here so the frontend never has to guess wwwroot.
            'url' => (new moodle_url('/local/nit_category/index.php', ['id' => $cat->id]))->out(false),
        ];
        $i++;
    }

    return $categories;
}

/**
 * Split a multi-language string into one plain-text value per language.
 *
 * `format_string()` — and therefore `get_formatted_name()` — answers with the
 * ONE language the page is currently being read in, which is right for almost
 * every caller and wrong for a card that has to print the Arabic name and the
 * English name side by side. This reads the raw, unfiltered value instead and
 * hands back every translation it carries, so the caller can show two at once.
 *
 * Both markups the site can hold are understood: the "Multi-language content
 * (v2)" filter's `{mlang xx}…{mlang}` — what the front-page blocks and our
 * category names use — and core's older multilang spans. A value in neither
 * form is not a translation set, so it yields an empty array and the caller
 * falls back to the formatted name.
 *
 * The pieces come back as plain text (tags dropped, entities decoded) because
 * they are written into the page with `textContent`, which escapes on output;
 * leaving them escaped here would print a literal `&amp;` on the card.
 *
 * @param string $text the raw field value, before any filter has run
 * @return array<string, string> language code (lower case, e.g. 'en', 'ar') => value
 */
function theme_nit_multilang_variants(string $text): array {
    $plain = static function (string $value): string {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
    };

    $variants = [];

    // v2: {mlang en}Law{mlang}{mlang ar}القانون{mlang}. One tag may list several
    // codes ({mlang en,fr}), and "other" is a code like any other here — it is the
    // caller's business whether a fallback means anything to it.
    if (preg_match_all('/\{\s*mlang\s+([^}]+)\}(.*?)\{\s*mlang\s*\}/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            foreach (preg_split('/\s*,\s*/', trim($match[1])) as $lang) {
                $lang = strtolower(trim($lang));
                // First one wins: a name repeating a language is a typo, not an override.
                if ($lang !== '' && !isset($variants[$lang])) {
                    $variants[$lang] = $plain($match[2]);
                }
            }
        }
    }

    // v1: a span carrying class="multilang". The two attributes appear in either
    // order in the wild, so the span is matched on the class and the code is read
    // out of it afterwards rather than pinning both into one pattern.
    if (preg_match_all('/<span\b([^>]*\bmultilang\b[^>]*)>(.*?)<\/span>/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if (preg_match('/\blang\s*=\s*["\']?([a-zA-Z0-9_-]+)/', $match[1], $attr)) {
                $lang = strtolower($attr[1]);
                if (!isset($variants[$lang])) {
                    $variants[$lang] = $plain($match[2]);
                }
            }
        }
    }

    return array_filter($variants, static function (string $value): bool {
        return $value !== '';
    });
}

/**
 * The site footer (AC-4.7.13), as template context.
 *
 * The footer appears on every page, so this runs on every page: it is a handful
 * of get_config() reads, all of which Moodle's config cache already holds in
 * memory, so there is nothing here worth a cache of its own.
 *
 * The content comes from local_profilefields (the Footer tab of the Site pages
 * manager), guarded on the plugin being installed at all - theme_nit has to keep
 * rendering a page on a site that does not run it. Everything an administrator
 * typed goes through format_string(), which escapes it AND applies the multilang
 * filter, so one field can carry both languages via {mlang} markup.
 *
 * @param renderer_base $output the renderer, for the logo URLs
 * @return array|null template context for theme_nit/site_footer, or null when the
 *                    footer is switched off or the content plugin is absent
 */
function theme_nit_get_site_footer_context($output): ?array {
    global $SITE;

    if (!class_exists('\local_profilefields\footer')) {
        return null;
    }

    $data = \local_profilefields\footer::config();
    if (empty($data['enabled'])) {
        return null;
    }

    $context = context_system::instance();
    $opts = ['context' => $context];

    $rows = [];
    foreach ($data['contact']['rows'] as $row) {
        $rows[] = [
            'icon'    => $row['icon'],
            'text'    => format_string($row['text'], true, $opts),
            'url'     => $row['url'],
            'haslink' => $row['url'] !== '',
        ];
    }

    $columns = [];
    foreach ($data['columns'] as $column) {
        $links = [];
        foreach ($column['links'] as $link) {
            $links[] = [
                'label' => format_string($link['label'], true, $opts),
                // Already through clean_param(PARAM_URL) on save; out() here so a
                // site path such as /course/ becomes a real link under any wwwroot.
                'url'   => (new moodle_url($link['url']))->out(false),
            ];
        }
        $columns[] = [
            'heading'    => format_string($column['heading'], true, $opts),
            'hasheading' => trim($column['heading']) !== '',
            'links'      => $links,
        ];
    }

    $social = [];
    foreach ($data['social'] as $item) {
        $social[] = [
            'icon'    => $item['icon'],
            'url'     => $item['url'],
            // The aria-label on an icon-only link: the network's own name, which
            // is a brand and the same in both languages. It comes with the row so
            // "LinkedIn" and "X" read as their brands rather than as ucfirst() of
            // a config key.
            'network' => $item['name'],
        ];
    }

    // The site logo, read exactly the way the navbar reads it - the compact one
    // first, then the full one - so the footer never shows a different logo from
    // the corner of the same page, and an administrator who replaces the site
    // logo has replaced this one too. `get_logo_url()` is the fallback because
    // `should_display_navbar_logo()` is false on a site that set only the full
    // logo. The packaged image is the last resort, for a site that has set
    // neither: a blank column is worse than the brand's own mark.
    $logourl = $output->get_compact_logo_url(null, 200);
    if (empty($logourl)) {
        $logourl = $output->get_logo_url(null, 200);
    }
    $logourl = !empty($logourl)
        ? $logourl->out(false)
        : (new moodle_url('/theme/nit/pix/footer-logo.png'))->out(false);

    return [
        'hascontact'     => trim($data['contact']['heading']) !== '' || !empty($rows),
        'contactheading' => format_string($data['contact']['heading'], true, $opts),
        'contactrows'    => $rows,
        'columns'        => $columns,
        'haslogo'        => $logourl !== '',
        'logourl'        => $logourl,
        'sitename'       => format_string($SITE->fullname, true, $opts + ['escape' => false]),
        'hassocial'      => !empty($social),
        'social'         => $social,
        'copyright'      => format_string($data['copyright'], true, $opts),
    ];
}
