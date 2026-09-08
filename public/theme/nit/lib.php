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
 * The named sections the roles are grouped into, in display order.
 *
 * A group is 37 roles now, which is more than anybody can scan as one flat
 * grid. The section is purely an editing aid — it changes no CSS and no export
 * shape — but it is declared here rather than in the template because the ORDER
 * of theme_nit_brand_roles() is what the gallery renders, and the two have to
 * agree. `key` is also the anchor the gallery's in-group jump links use.
 *
 * @return array<string, string> section key => display label
 */
function theme_nit_brand_role_sections(): array {
    return [
        'brand'   => 'Brand',
        'navbar'  => 'Navbar',
        'footer'  => 'Footer',
        'surface' => 'Surfaces & text',
        'status'  => 'Status',
    ];
}

/**
 * The 37 semantic roles every Brand-Colors group is built from.
 *
 * This is the clean, small semantic layer that replaces the sprawling
 * theme_nit_colour_palette(): a component references a role by name (Primary,
 * Surface, Text primary, …) and never a raw colour. `label` is the display name,
 * `section` is which block of the editor it belongs to (see
 * theme_nit_brand_role_sections()) and `usage` is a list of the concrete UI
 * things that should use the colour — rendered as chips on the gallery's Brand
 * Colors tab. `default` here is only a red-free FALLBACK (the Group 1 /
 * Slate-blue values): every group overrides all of them in
 * theme_nit_brand_group_defaults(), so a role default is used only if a group
 * ever omits a role. The Hover Background / Hover Text roles carry the explicit
 * hover colours (other opacity variants are still derived in SCSS, see
 * scss/foundation/_brand.scss).
 *
 * @return array<string, array{section:string, label:string, usage:string[], default:string}>
 */
function theme_nit_brand_roles(): array {
    return [
        // --- Brand -----------------------------------------------------------
        'primary'           => ['section' => 'brand', 'label' => 'Primary', 'usage' => ['background main button', 'checked toggles', 'progress fill', 'notification dots'], 'default' => '#5488c4'],
        'secondary'         => ['section' => 'brand', 'label' => 'Secondary', 'usage' => ['background secondary button'], 'default' => '#1c2a3a'],
        // Text drawn ON a filled button, one role per button colour. These are
        // roles rather than "whatever the body ink happens to be" because the
        // right answer depends on the fill, not on the page: a light group fills
        // its main button with a dark blue and needs white on it, a dark group
        // fills it with a light blue and needs near-black. Getting that from
        // "Text primary" was wrong by construction in half the groups.
        'onprimary'         => ['section' => 'brand', 'label' => 'Text on main button', 'usage' => ['label inside a filled main button', 'text on any primary fill'], 'default' => '#eef3f9'],
        'onsecondary'       => ['section' => 'brand', 'label' => 'Text on secondary button', 'usage' => ['label inside a secondary button', 'label inside an outline-secondary button'], 'default' => '#eef3f9'],
        'accent'            => ['section' => 'brand', 'label' => 'Accent', 'usage' => ['none text'], 'default' => '#5488c4'],
        'accenttext'        => ['section' => 'brand', 'label' => 'Accent Text', 'usage' => ['text of links', 'important words', 'underlines'], 'default' => '#7fabdb'],

        // --- Navbar ----------------------------------------------------------
        // The bar owns its whole palette rather than borrowing the page's. Every
        // thing drawn on it — the site titles, the icon cluster, the log-in link
        // — has its own rest / hover / active colour, because the bar is the one
        // surface where "the same blue as a body link" is almost never the right
        // answer: it sits on its own background, at its own size, over content
        // that scrolls under it.
        'navbarbackground1' => ['section' => 'navbar', 'label' => 'Navbar background 1', 'usage' => ['navbar background'], 'default' => '#0c141f'],
        'navbarbackground2' => ['section' => 'navbar', 'label' => 'Navbar background 2', 'usage' => ['navbar glass — the translucent pane the bar is painted with'], 'default' => '#121e2d'],
        'navbartitlecolor'  => ['section' => 'navbar', 'label' => 'Navbar title color', 'usage' => ['the site links across the bar (Home, Courses, …)'], 'default' => '#eef3f9'],
        'navbartitlehovercolor' => ['section' => 'navbar', 'label' => 'Navbar title hover color', 'usage' => ['a site link under the cursor'], 'default' => '#7fabdb'],
        'navbartitleactivecolor' => ['section' => 'navbar', 'label' => 'Navbar title active color', 'usage' => ['the site link for the page being viewed'], 'default' => '#7fabdb'],
        // The two SHAPE colours. Which shape they draw (underline / bold /
        // square background / all three) is not a colour and so is not a role:
        // it is a per-group choice stored beside them, see
        // theme_nit_navbar_title_shapes().
        'navbartitlehoverstylecolor' => ['section' => 'navbar', 'label' => 'Navbar title hover style color', 'usage' => ['the hover shape — its underline, or its square background'], 'default' => '#16222f'],
        'navbartitleactivestylecolor' => ['section' => 'navbar', 'label' => 'Navbar title active style color', 'usage' => ['the active shape — its underline, or its square background'], 'default' => '#7fabdb'],
        'navbariconcolor'   => ['section' => 'navbar', 'label' => 'Navbar icon color', 'usage' => ['navbar icons — search, language, messages, notifications, gear', 'notification panel action icons'], 'default' => '#eef3f9'],
        'navbariconhovercolor' => ['section' => 'navbar', 'label' => 'Navbar icon hover color', 'usage' => ['a navbar icon under the cursor', 'the soft pad drawn behind it'], 'default' => '#7fabdb'],
        'navbariconactivecolor' => ['section' => 'navbar', 'label' => 'Navbar icon active color', 'usage' => ['a navbar icon whose panel is open, or being pressed'], 'default' => '#7fabdb'],
        'navbarlogincolor'  => ['section' => 'navbar', 'label' => 'Navbar login color', 'usage' => ['the "Log in" link on the bar (signed-out visitors)'], 'default' => '#eef3f9'],
        'navbarloginhovercolor' => ['section' => 'navbar', 'label' => 'Navbar login hover color', 'usage' => ['the "Log in" link under the cursor'], 'default' => '#7fabdb'],
        'navbarloginactivecolor' => ['section' => 'navbar', 'label' => 'Navbar login active color', 'usage' => ['the "Log in" link being pressed, or on the log-in page itself'], 'default' => '#7fabdb'],

        // --- Footer ----------------------------------------------------------
        'footerbackground1' => ['section' => 'footer', 'label' => 'Footer background 1', 'usage' => ['footer background'], 'default' => '#0c141f'],
        'footerbackground2' => ['section' => 'footer', 'label' => 'Footer background 2', 'usage' => ['footer background — second colour (reserved, not consumed yet)'], 'default' => '#121e2d'],
        // Footer-only roles. The band used to borrow Accent Text / Primary from
        // the page, which meant an admin could not recolour a footer heading
        // without moving every link on the site. Three roles, one per thing the
        // footer actually draws, so the band is tunable on its own.
        'footerheading'     => ['section' => 'footer', 'label' => 'Footer heading', 'usage' => ['footer column headings'], 'default' => '#7fabdb'],
        'footerlink'        => ['section' => 'footer', 'label' => 'Footer link', 'usage' => ['footer column links'], 'default' => '#5488c4'],
        'footericon'        => ['section' => 'footer', 'label' => 'Footer icon', 'usage' => ['footer social icons and their ring'], 'default' => '#5488c4'],

        // --- Surfaces & text --------------------------------------------------
        'background'        => ['section' => 'surface', 'label' => 'Background', 'usage' => ['page background'], 'default' => '#0c141f'],
        'background2'       => ['section' => 'surface', 'label' => 'Second background', 'usage' => ['alternate page sections', 'bands lifted off the page ground'], 'default' => '#101a27'],
        'surface'           => ['section' => 'surface', 'label' => 'Surface', 'usage' => ['Cards background', 'dropdowns background', 'side menu background', 'inputs background', 'tooltips background', 'table background', 'page sections background'], 'default' => '#121e2d'],
        'textprimary'       => ['section' => 'surface', 'label' => 'Text primary', 'usage' => ['main normal text', 'text in buttons', 'text in inputs'], 'default' => '#eef3f9'],
        'textsecondary'     => ['section' => 'surface', 'label' => 'Text secondary', 'usage' => ['secondary normal text', 'placeholders'], 'default' => '#94a3b8'],
        'borderprimary'     => ['section' => 'surface', 'label' => 'Border primary', 'usage' => ['main border color'], 'default' => '#223244'],
        'bordersecondary'   => ['section' => 'surface', 'label' => 'Border secondary', 'usage' => ['secondary border color'], 'default' => '#33475e'],
        'hoverbackground'   => ['section' => 'surface', 'label' => 'Hover Background', 'usage' => ['hover background'], 'default' => '#16222f'],
        'hovertext'         => ['section' => 'surface', 'label' => 'Hover Text', 'usage' => ['hover text'], 'default' => '#7fabdb'],

        // --- Status -----------------------------------------------------------
        'error'             => ['section' => 'status', 'label' => 'Error', 'usage' => ['Errors', 'danger / destructive actions', 'invalid fields'], 'default' => '#d07f43'],
        'success'           => ['section' => 'status', 'label' => 'Success', 'usage' => ['Success', 'enrolled / active / paid', 'positive states'], 'default' => '#3fa877'],
        'warning'           => ['section' => 'status', 'label' => 'Warning', 'usage' => ['Warnings', 'caution', 'pending / expiring'], 'default' => '#d8c24e'],
        'info'              => ['section' => 'status', 'label' => 'Info', 'usage' => ['Neutral notices', 'tips', 'hints'], 'default' => '#5fb0c9'],
    ];
}

/**
 * The navbar-title SHAPE choices — the one navbar decision that is not a colour.
 *
 * A title can answer the cursor (and mark the page you are on) with an
 * underline, extra weight, a filled square behind it, or all three at once. That
 * is a style choice an administrator makes per Brand-Colors group, so it is
 * stored per group as `theme_nit/navbarshape_<gkey>_<state>` and consumed as
 * three CSS custom properties per state (see theme_nit_navbar_style_scss()).
 *
 * Each entry says which of the three treatments the shape switches on. Nothing
 * else in the theme has to know the shape names.
 *
 * @return array<string, array{label:string, underline:bool, bold:bool, square:bool}>
 */
function theme_nit_navbar_title_shapes(): array {
    return [
        'underline' => ['label' => 'Under line', 'underline' => true,  'bold' => false, 'square' => false],
        'bold'      => ['label' => 'Bold',       'underline' => false, 'bold' => true,  'square' => false],
        'square'    => ['label' => 'Square background', 'underline' => false, 'bold' => false, 'square' => true],
        'all'       => ['label' => 'All',        'underline' => true,  'bold' => true,  'square' => true],
    ];
}

/**
 * The two navbar-title states that carry a shape, and each one's default.
 *
 * The defaults are the look the bar already had: hover paints a soft pad behind
 * the title (a "square background" whose colour seeds to the group's Hover
 * Background), and the current page is marked with an underline in the group's
 * accent — which is the one thing the bar could not say before, because "active"
 * was a colour change alone and the two accents in most groups are the same hue.
 *
 * @return array<string, array{label:string, default:string}> state key => meta
 */
function theme_nit_navbar_title_states(): array {
    return [
        'titlehover'  => ['label' => 'Title hover style shape', 'default' => 'square'],
        'titleactive' => ['label' => 'Title active style shape', 'default' => 'underline'],
    ];
}

/**
 * The shape an administrator chose for one group / state.
 *
 * @param string $group group key (g1..g5)
 * @param string $state state key (see theme_nit_navbar_title_states())
 * @return string a key of theme_nit_navbar_title_shapes()
 */
function theme_nit_navbar_title_shape(string $group, string $state): string {
    $states = theme_nit_navbar_title_states();
    $default = $states[$state]['default'] ?? 'underline';
    $value = get_config('theme_nit', 'navbarshape_' . $group . '_' . $state);
    return (is_string($value) && array_key_exists($value, theme_nit_navbar_title_shapes()))
        ? $value : $default;
}

/**
 * The three things drawn on the bar, and how big / how heavy each is set.
 *
 * The same three subjects the navbar COLOUR roles are built around (titles,
 * icons, the log-in link) each get a size and a weight, per Brand-Colors group,
 * stored as `theme_nit/navbarsize_<gkey>_<subject>` and
 * `theme_nit/navbarweight_<gkey>_<subject>`. Per group and not site-wide because
 * that is what a group IS here — a complete description of how the site looks in
 * that palette — and because the shapes above already work that way; one of the
 * two being global would be a trap, since an admin editing it under "Group 3"
 * would silently move Group 1 as well.
 *
 * `size` and `weight` are the values the stylesheet already used, so an untouched
 * site renders exactly as it did: 16px/600 titles, 22px glyphs, a 16px/600 log-in
 * link. `min`/`max` are clamps, not suggestions — a 60px title would break the
 * bar out of its own height.
 *
 * Two notes on what "icon size" reaches. It is the GLYPH, not the button: the
 * 42px pointer target grows only if the glyph outgrows it
 * (theme_nit_navbar_icon_box()), so making icons smaller never makes them harder
 * to hit. And "icon weight" is only visible on the parts of the cluster that are
 * type rather than a glyph — the language code (EN / AR) — because Font Awesome
 * ships one weight per face.
 *
 * @return array<string, array{label:string, usage:string[], size:int, weight:int, min:int, max:int}>
 */
function theme_nit_navbar_type_subjects(): array {
    return [
        'title' => [
            'label'  => 'Navbar title text',
            'usage'  => ['size and weight of the site links across the bar'],
            'size'   => 16, 'weight' => 600, 'min' => 12, 'max' => 28,
        ],
        'icon'  => [
            'label'  => 'Navbar icon size',
            'usage'  => ['size of the navbar glyphs', 'the button grows only if the glyph outgrows it', 'weight reaches the language code (EN / AR), not the glyphs'],
            'size'   => 22, 'weight' => 500, 'min' => 14, 'max' => 36,
        ],
        'login' => [
            'label'  => 'Navbar login text',
            'usage'  => ['size and weight of the "Log in" link'],
            'size'   => 16, 'weight' => 600, 'min' => 12, 'max' => 28,
        ],
    ];
}

/**
 * The font weights offered, keyed by the CSS number.
 *
 * A fixed ladder rather than a free number: these are the weights a variable
 * face actually has stops for, and the names are what an administrator thinks in.
 *
 * @return array<int, string> weight => label
 */
function theme_nit_navbar_weights(): array {
    return [
        300 => 'Light (300)',
        400 => 'Regular (400)',
        500 => 'Medium (500)',
        600 => 'Semi-bold (600)',
        700 => 'Bold (700)',
        800 => 'Extra bold (800)',
    ];
}

/**
 * The size an administrator set for one group / subject, in px.
 *
 * Clamped to the subject's own range, so a saved value that predates a narrower
 * range (or a hand-edited config row) still renders something sane.
 *
 * @param string $group group key (g1..g5)
 * @param string $subject subject key (see theme_nit_navbar_type_subjects())
 * @return int pixels
 */
function theme_nit_navbar_type_size(string $group, string $subject): int {
    $meta = theme_nit_navbar_type_subjects()[$subject] ?? null;
    if ($meta === null) {
        return 16;
    }
    $value = get_config('theme_nit', 'navbarsize_' . $group . '_' . $subject);
    if (!is_numeric($value)) {
        return $meta['size'];
    }
    return min($meta['max'], max($meta['min'], (int) $value));
}

/**
 * The font weight an administrator set for one group / subject.
 *
 * @param string $group group key (g1..g5)
 * @param string $subject subject key (see theme_nit_navbar_type_subjects())
 * @return int a key of theme_nit_navbar_weights()
 */
function theme_nit_navbar_type_weight(string $group, string $subject): int {
    $meta = theme_nit_navbar_type_subjects()[$subject] ?? null;
    if ($meta === null) {
        return 400;
    }
    $value = get_config('theme_nit', 'navbarweight_' . $group . '_' . $subject);
    return array_key_exists((int) $value, theme_nit_navbar_weights()) ? (int) $value : $meta['weight'];
}

/**
 * How wide the square icon button is, for a given glyph size.
 *
 * The button is 42px and stays 42px until the glyph would not fit in it — a
 * pointer target that shrinks with the glyph would put the smallest setting well
 * under what a finger can hit, which is the one thing an admin choosing "small
 * icons" is not asking for.
 *
 * @param int $glyph glyph size in px
 * @return int the button's side, in px
 */
function theme_nit_navbar_icon_box(int $glyph): int {
    return max(42, $glyph + 20);
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
 * The theme_nit config row holding the category → group map for one mode.
 *
 * Light keeps the original key so every map an admin saved before the site had
 * two styles per category is still the light one — the look those pages already
 * had. Dark is a second row rather than a second field inside the first, so the
 * two maps stay independently readable and a half-written dark map can never
 * cost a category the style it is showing today.
 *
 * @param string $mode 'light' | 'dark'
 * @return string the config name
 */
function theme_nit_category_groups_config(string $mode): string {
    return $mode === 'dark' ? 'nit_category_groups_dark' : 'nit_category_groups';
}

/**
 * The whole category → group map for one display mode.
 *
 * @param string $mode 'light' | 'dark'
 * @return array<int|string, string> main category id => group key
 */
function theme_nit_category_group_map(string $mode): array {
    static $maps = [];
    $mode = ($mode === 'dark') ? 'dark' : 'light';
    if (!array_key_exists($mode, $maps)) {
        $raw = get_config('theme_nit', theme_nit_category_groups_config($mode));
        $maps[$mode] = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];
    }
    return $maps[$mode];
}

/**
 * The Brand-Colors group assigned to a category (for the category details page).
 *
 * Admins map only the MAIN (top-level) categories to groups on the gallery
 * "Category styles" tab, once per display mode; the maps are stored as the
 * theme_nit configs `nit_category_groups` (light) and `nit_category_groups_dark`
 * (dark), each JSON `{topcatid: "g2", …}`. A category page resolves to the group
 * of its top-level ancestor, so every subcategory / filtered view under a main
 * category inherits that main category's group. Unassigned → Group 1.
 *
 * @param int $categoryid the category whose page is being rendered
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return string one of the group keys from theme_nit_brand_groups() (g1..g5)
 */
function theme_nit_category_brand_group(int $categoryid, ?string $mode = null): string {
    return theme_nit_category_brand_group_assigned($categoryid, $mode) ?? 'g1';
}

/**
 * The group an admin EXPLICITLY assigned to a category, or null when there is none.
 *
 * Same resolution as theme_nit_category_brand_group() — the map is keyed by main
 * category, so a subcategory answers with its top-level ancestor's group — but it
 * tells "assigned to Group 1" apart from "never assigned", which the plain
 * function cannot: both come back as `g1` there.
 *
 * That distinction is what lets a course page adopt its category's palette
 * without stealing the light/dark switch from every OTHER course: an unassigned
 * category returns null and the page stays on the mode's group (see
 * theme_nit_page_brand_group()).
 *
 * A category holds one assignment PER MODE, so this is asked per mode too. A
 * category styled for light and left alone for dark answers null in dark, and
 * those pages fall back to the site's dark group — "not answered" must never
 * mean "wear the light palette in the dark", which is the one outcome nobody
 * would have chosen deliberately.
 *
 * @param int $categoryid the category being rendered, or a course's category
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return string|null a group key (g1..g5), or null when nothing is assigned
 */
function theme_nit_category_brand_group_assigned(int $categoryid, ?string $mode = null): ?string {
    static $resolved = [];

    $mode = ($mode === 'dark' || $mode === 'light') ? $mode : theme_nit_current_mode();
    $map = theme_nit_category_group_map($mode);

    if (empty($map) || $categoryid <= 0) {
        return null;
    }
    $cachekey = $mode . ':' . $categoryid;
    if (array_key_exists($cachekey, $resolved)) {
        return $resolved[$cachekey];
    }

    $topid = theme_nit_category_top_ancestor($categoryid);

    $group = $map[$topid] ?? null;
    if ($group === null || !array_key_exists($group, theme_nit_brand_groups())) {
        $group = null;
    }
    $resolved[$cachekey] = $group;
    return $group;
}

/**
 * The MAIN (top-level) category a category belongs to — itself, when it is one.
 *
 * Both halves of a category's branding are assigned per main category (the
 * palette above, the navbar logo below), so both resolve through this: one
 * lookup, one cache, and no way for the two to disagree about which category a
 * course page is really in.
 *
 * @param int $categoryid any category id
 * @return int the top-level ancestor's id (the input id if it cannot be resolved)
 */
function theme_nit_category_top_ancestor(int $categoryid): int {
    static $tops = [];
    if ($categoryid <= 0) {
        return 0;
    }
    if (array_key_exists($categoryid, $tops)) {
        return $tops[$categoryid];
    }

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

    $tops[$categoryid] = $topid;
    return $topid;
}

/**
 * The category THIS request's page belongs to, or 0.
 *
 * A category's branding is a property of the category, not of one page in it: a
 * visitor who opens a course from a Group 2 category should stay in Group 2 for
 * the whole visit — the course page, its settings form, the participants list,
 * the grader report, the activity overview, every report under "More". So the
 * category is resolved from the page's own context rather than being printed by
 * each screen, which is why only course/view.php used to carry it (the course
 * format renderer was the single place that asked).
 *
 * Four ways a page can name a category, in order:
 *   1. `$PAGE->course->category` — anything inside a course. Off the site course
 *      this is 0, which is how non-course pages fall through.
 *   2. `$PAGE->category` — the category pages proper (course/index.php).
 *   3. A CONTEXT_COURSECAT page context — the category management screens, which
 *      do not always set (2).
 *   4. Any other context that lives inside a course — course, activity, block.
 *      Setting a course CONTEXT without calling set_course() is common in plugin
 *      pages (local/payments/buy.php does exactly that), and those pages have to
 *      match the course they are about.
 *
 * Split out from theme_nit_page_brand_group() when a category gained a second
 * piece of branding (its navbar logo): both answers are about the same category,
 * and asking twice through two copies of this walk is how they would eventually
 * come back about two different ones.
 *
 * @return int the category id, or 0 when this page is not in one
 */
function theme_nit_page_categoryid(): int {
    global $PAGE, $DB;

    // -1 = not computed yet; 0 is a real answer ("this page is not in a category").
    static $catid = -1;
    if ($catid !== -1) {
        return $catid;
    }
    $catid = 0;

    if (!isset($PAGE)) {
        return 0;
    }

    if (!empty($PAGE->course) && !empty($PAGE->course->category)) {
        $catid = (int) $PAGE->course->category;
    }
    if (!$catid) {
        try {
            $cat = $PAGE->category;
            if (!empty($cat->id)) {
                $catid = (int) $cat->id;
            }
        } catch (\Throwable $e) {
            $catid = 0;
        }
    }
    if (!$catid) {
        // Guarded: reading $PAGE->context before anything set one emits a
        // debugging notice (and throws outright in an AJAX script under
        // developer mode). Nothing here is worth a warning on somebody else's
        // page, so a page that cannot answer simply does not get a category.
        try {
            $context = $PAGE->context;
            if ($context) {
                if ((int) $context->contextlevel === CONTEXT_COURSECAT) {
                    $catid = (int) $context->instanceid;
                } else {
                    // A course (or activity, or block) context with no
                    // $PAGE->course behind it. That is most plugin pages:
                    // local/payments/buy.php sets the course CONTEXT and never
                    // calls set_course(), so the check above still saw the site
                    // course and the buy screen came out in the site palette
                    // while the course around it was in its category's. Walk the
                    // context up to its course and read the category from there.
                    $coursectx = $context->get_course_context(false);
                    if ($coursectx && (int) $coursectx->instanceid !== (int) SITEID) {
                        $catid = (int) $DB->get_field('course', 'category',
                            ['id' => $coursectx->instanceid]);
                    }
                }
            }
        } catch (\Throwable $e) {
            $catid = 0;
        }
    }

    return $catid;
}

/**
 * The category-assigned brand group THIS request's page belongs to, if any.
 *
 * Returns null unless the page's category (or its main ancestor) has a group
 * assigned for the mode being asked about on the gallery "Category styles" tab:
 * an unassigned category must leave the page on the light/dark switch's group,
 * not pin it to Group 1.
 *
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return string|null group key (g1..g5), or null when the page is not in a styled category
 */
function theme_nit_page_brand_group(?string $mode = null): ?string {
    $catid = theme_nit_page_categoryid();
    if (!$catid) {
        return null;
    }
    return theme_nit_category_brand_group_assigned($catid, $mode);
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
 * The <html> classes a GIVEN mode owns, on THIS page.
 *
 * Split out from theme_nit_mode_classes() so the navbar switch can ask for the
 * other mode's set and swap the two in the browser. One function builds both,
 * because the server-rendered class list and the one the button applies must
 * never be able to disagree.
 *
 * "On this page" is the whole of it: a category carries a style per mode, and
 * inside such a category that style outranks the site's for that mode — the
 * course page, its settings, participants, grades, reports. So the group is
 * looked up per mode and per page, which is exactly what lets the switch keep
 * working inside a styled category (it now moves between the category's own two
 * looks) instead of having to be hidden there.
 *
 * @param string $mode 'light' | 'dark'
 * @return string space-separated class list
 */
function theme_nit_mode_classes_for(string $mode): string {
    return theme_nit_html_classes_for($mode, theme_nit_active_chrome_group($mode));
}

/**
 * The <html> classes for a given mode rendered in a given group.
 *
 * The two are normally tied together (a mode selects a group), but a styled
 * category breaks the tie: the page renders in the CATEGORY's group while the
 * visitor's chosen mode is still what it was. One builder for both cases, so the
 * chrome class can never be derived from a different group than the one actually
 * painting the page.
 *
 * @param string $mode 'light' | 'dark' — the visitor's choice
 * @param string $group the group key this page actually renders in
 * @return string space-separated class list
 */
function theme_nit_html_classes_for(string $mode, string $group): string {
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
            'navbartitlecolor'  => '#eef3f9',
            'navbartitlehovercolor' => '#7fabdb',
            'navbartitleactivecolor' => '#7fabdb',
            'navbartitlehoverstylecolor' => '#16222f',
            'navbartitleactivestylecolor' => '#7fabdb',
            'navbariconcolor'   => '#eef3f9',
            'navbariconhovercolor' => '#7fabdb',
            'navbariconactivecolor' => '#7fabdb',
            'navbarlogincolor'  => '#eef3f9',
            'navbarloginhovercolor' => '#7fabdb',
            'navbarloginactivecolor' => '#7fabdb',
            'footerbackground1' => '#0c141f',
            'footerbackground2' => '#121e2d',
            'footerheading'     => '#7fabdb',
            'footerlink'        => '#5488c4',
            'footericon'        => '#5488c4',
            'surface'           => '#121e2d',
            'textprimary'       => '#eef3f9',
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
            'navbartitlecolor'  => '#eef5f4',
            'navbartitlehovercolor' => '#6ccabb',
            'navbartitleactivecolor' => '#58bdad',
            'navbartitlehoverstylecolor' => '#143231',
            'navbartitleactivestylecolor' => '#58bdad',
            'navbariconcolor'   => '#eef5f4',
            'navbariconhovercolor' => '#6ccabb',
            'navbariconactivecolor' => '#58bdad',
            'navbarlogincolor'  => '#eef5f4',
            'navbarloginhovercolor' => '#58bdad',
            'navbarloginactivecolor' => '#6ccabb',
            'footerbackground1' => '#0a1a1a',
            'footerbackground2' => '#102727',
            'footerheading'     => '#58bdad',
            'footerlink'        => '#2f9e8f',
            'footericon'        => '#2f9e8f',
            'surface'           => '#102727',
            'textprimary'       => '#eef5f4',
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
            'navbartitlecolor'  => '#efedf7',
            'navbartitlehovercolor' => '#b4a9ee',
            'navbartitleactivecolor' => '#a99ee2',
            'navbartitlehoverstylecolor' => '#201e34',
            'navbartitleactivestylecolor' => '#a99ee2',
            'navbariconcolor'   => '#efedf7',
            'navbariconhovercolor' => '#b4a9ee',
            'navbariconactivecolor' => '#a99ee2',
            'navbarlogincolor'  => '#efedf7',
            'navbarloginhovercolor' => '#a99ee2',
            'navbarloginactivecolor' => '#b4a9ee',
            'footerbackground1' => '#11101c',
            'footerbackground2' => '#1a182d',
            'footerheading'     => '#a99ee2',
            'footerlink'        => '#8478cf',
            'footericon'        => '#8478cf',
            'surface'           => '#1a182d',
            'textprimary'       => '#efedf7',
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
            // The bar is light here, so everything drawn on it — the titles, the
            // glyphs, the log-in link — is the body ink, not near-white. Their
            // hover / active states go DARKER (A700 / A800), because on a light
            // ground a state has to beat the paper, not the ink.
            'navbartitlecolor'  => '#14191f',   // N900
            'navbartitlehovercolor' => '#073b78',   // A800
            'navbartitleactivecolor' => '#0e509d',  // A700
            'navbartitlehoverstylecolor' => '#f1f3f6',  // N100 — the hover pad
            'navbartitleactivestylecolor' => '#0e509d', // A700 — the underline
            'navbariconcolor'   => '#14191f',   // N900
            'navbariconhovercolor' => '#073b78',    // A800
            'navbariconactivecolor' => '#0e509d',   // A700
            'navbarlogincolor'  => '#14191f',   // N900
            'navbarloginhovercolor' => '#0e509d',   // A700
            'navbarloginactivecolor' => '#073b78',  // A800
            'footerbackground1' => '#f1f3f6',   // N100
            'footerbackground2' => '#e6e8eb',   // N200
            'footerheading'     => '#0e509d',
            'footerlink'        => '#2368bd',
            'footericon'        => '#2368bd',
            'surface'           => '#ffffff',
            'textprimary'       => '#14191f',   // N900
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
            'navbartitlecolor'  => '#f6f8fb',
            'navbartitlehovercolor' => '#c0dafc',   // A200
            'navbartitleactivecolor' => '#98c0f7',  // A300
            'navbartitlehoverstylecolor' => '#14191f',  // N900 — the hover pad
            'navbartitleactivestylecolor' => '#98c0f7', // A300 — the underline
            'navbariconcolor'   => '#f6f8fb',
            'navbariconhovercolor' => '#c0dafc',    // A200
            'navbariconactivecolor' => '#98c0f7',   // A300
            'navbarlogincolor'  => '#f6f8fb',
            'navbarloginhovercolor' => '#98c0f7',   // A300
            'navbarloginactivecolor' => '#c0dafc',  // A200
            'footerbackground1' => '#0d1117',
            'footerbackground2' => '#14191f',
            'footerheading'     => '#98c0f7',
            'footerlink'        => '#71a7ef',
            'footericon'        => '#71a7ef',
            'surface'           => '#1f232a',   // N850
            'textprimary'       => '#f6f8fb',   // N50
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
                'section'  => $meta['section'],
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
            'section'  => $meta['section'],
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
            'section'  => $token['section'],
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
 * Category styles tab, as data: how each main category is branded, per mode.
 *
 * Only top-level (main) categories are assignable; subcategories inherit their
 * top ancestor's branding. Visibility-aware: an anonymous request sees only the
 * categories a guest may see.
 *
 * Each category carries a `modes` map — `light` and `dark`, each with the group
 * it renders in, the wrapper class that skins it, and the URL of the logo drawn
 * for that mode (empty string = "use the site logo"). The flat `group` /
 * `groupname` / `class` / `isdefault` keys are the LIGHT values and are kept
 * because they are what the app already reads; a category that was branded
 * before the site had two styles per category still answers there exactly as it
 * did.
 *
 * @return array{groups: array<int, array{key:string, name:string}>,
 *         categories: array<int, array{id:int, name:string, group:string,
 *         groupname:string, class:string, isdefault:bool, modes:array}>}
 */
function theme_nit_category_styles_export(): array {
    $grouplabels = theme_nit_brand_groups();

    $groups = [];
    foreach ($grouplabels as $gkey => $glabel) {
        $groups[] = ['key' => $gkey, 'name' => $glabel];
    }

    $maps = [];
    foreach (array_keys(theme_nit_modes()) as $mode) {
        $maps[$mode] = theme_nit_category_group_map($mode);
    }

    $categories = [];
    foreach (core_course_category::top()->get_children() as $cat) {
        $modes = [];
        foreach ($maps as $mode => $map) {
            $gkey = $map[$cat->id] ?? 'g1';
            if (!array_key_exists($gkey, $grouplabels)) {
                $gkey = 'g1';
            }
            $logo = theme_nit_category_logo_url((int) $cat->id, $mode);
            $modes[$mode] = [
                'group'     => $gkey,
                'groupname' => $grouplabels[$gkey],
                'class'     => theme_nit_brand_group_class($gkey),
                'isdefault' => ($gkey === 'g1'),
                'logo'      => $logo ? $logo->out(false) : '',
            ];
        }

        $light = $modes['light'];
        $categories[] = [
            'id'        => (int) $cat->id,
            'name'      => $cat->get_formatted_name(),
            'group'     => $light['group'],
            'groupname' => $light['groupname'],
            'class'     => $light['class'],
            'isdefault' => $light['isdefault'],
            'modes'     => $modes,
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

    // How big and how heavy the bar sets its titles / icons / log-in link, and
    // which shape a title takes on hover and on the current page — per brand
    // group (gallery → Brand Colors → Navbar).
    $scss .= theme_nit_navbar_style_scss();

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
 * Normally the group the light/dark switch selected. Inside a category that has
 * a style assigned it is that category's group instead: the switch class lands on
 * <html>, so the navbar and the footer are inside it like everything else, and
 * the things that have to know how bright the bar is — `nit-chrome-*`, and above
 * all which logo file to serve — must read the group that is actually painting
 * it. Asking the switch here instead would hand a dark navbar the logo drawn for
 * a light one, and the mark would disappear.
 *
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return string group key (g1..g5)
 */
function theme_nit_active_chrome_group(?string $mode = null): string {
    $mode = ($mode === 'dark' || $mode === 'light') ? $mode : theme_nit_current_mode();
    return theme_nit_page_brand_group($mode)
        ?? (theme_nit_mode_groups()[$mode] ?? 'g1');
}

/**
 * The per-category logo slots — one per display mode.
 *
 * A category is a brand of its own on this site: it has its own palette (a brand
 * group per mode, above) and its own mark. The mark needs the same two versions
 * for the same reason the site's does — a logo drawn in white for a navy bar
 * disappears the moment the bar turns white — so there is one slot per mode, and
 * an empty slot means "use the site logo", never "no logo".
 *
 * Stored the way local_nit_category stores a category's picture: in the
 * CATEGORY's own context, itemid 0, one file per area. That keeps a category's
 * files with the category — delete the category and they go with it — and it
 * gives the file a context whose visibility we can honour when serving it (see
 * theme_nit_pluginfile()).
 *
 * `input` is the multipart field-name PREFIX on the gallery form; the category
 * id is appended, because one form posts a row per category.
 *
 * @return array<string, array{filearea:string, input:string, basename:string,
 *         strkey:string, remove:string}> keyed by mode
 */
function theme_nit_category_logo_slots(): array {
    return [
        'light' => [
            'filearea' => 'categorylogolight',
            'input'    => 'catlogolight',
            'basename' => 'category-logo-light',
            'strkey'   => 'categorystyles_col_logolight',
            'remove'   => 'removecatlogolight',
        ],
        'dark' => [
            'filearea' => 'categorylogodark',
            'input'    => 'catlogodark',
            'basename' => 'category-logo-dark',
            'strkey'   => 'categorystyles_col_logodark',
            'remove'   => 'removecatlogodark',
        ],
    ];
}

/**
 * The logo file uploaded for a category in one display mode, if there is one.
 *
 * Asked about the MAIN category, the same way the palette is: the gallery table
 * lists top-level categories only, so a course three levels down answers with
 * its main category's mark.
 *
 * @param int $categoryid any category id (resolved to its top-level ancestor)
 * @param string $mode 'light' | 'dark'
 * @return stored_file|null the file, or null when that slot is empty
 */
function theme_nit_category_logo_file(int $categoryid, string $mode): ?stored_file {
    static $cache = [];

    $slots = theme_nit_category_logo_slots();
    if (!isset($slots[$mode]) || $categoryid <= 0) {
        return null;
    }

    $topid = theme_nit_category_top_ancestor($categoryid);
    $cachekey = $mode . ':' . $topid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    $cache[$cachekey] = null;

    $context = \context_coursecat::instance($topid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $files = get_file_storage()->get_area_files(
        $context->id,
        'theme_nit',
        $slots[$mode]['filearea'],
        0,
        'filename',
        false
    );
    $cache[$cachekey] = $files ? reset($files) : null;
    return $cache[$cachekey];
}

/**
 * The URL of a category's own logo for one display mode.
 *
 * @param int $categoryid any category id (resolved to its top-level ancestor)
 * @param string $mode 'light' | 'dark'
 * @return moodle_url|false the URL, or false when that slot is empty
 */
function theme_nit_category_logo_url(int $categoryid, string $mode) {
    $file = theme_nit_category_logo_file($categoryid, $mode);
    if (!$file) {
        return false;
    }
    // `theme_get_revision()` as the itemid segment is the cache buster, exactly
    // as the site logos use it — the stored file's real itemid is 0, and
    // theme_nit_pluginfile() drops this segment before looking the file up.
    return \moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        'theme_nit',
        $file->get_filearea(),
        theme_get_revision(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * The category logo THIS request's page should draw, if its category has one.
 *
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return moodle_url|false
 */
function theme_nit_page_category_logo_url(?string $mode = null) {
    $catid = theme_nit_page_categoryid();
    if (!$catid) {
        return false;
    }
    $mode = ($mode === 'dark' || $mode === 'light') ? $mode : theme_nit_current_mode();
    return theme_nit_category_logo_url($catid, $mode);
}

/**
 * The URL of a logo, for the mode this page is rendering in.
 *
 * Three answers, in order:
 *
 *   1. The page's CATEGORY logo, when it has one for this mode. A category is a
 *      brand of its own here — it already carries its own palette — so on its
 *      pages its own mark replaces the site's, in the navbar and everywhere else
 *      the compact logo is drawn. The browser-tab icon is deliberately excluded:
 *      the favicon says which SITE a tab belongs to, and a per-category one only
 *      makes a row of tabs harder to read.
 *   2. The alternative site upload, when the page's chrome is light and the core
 *      logos were drawn for dark (or the other way round) AND that alternative
 *      was actually uploaded.
 *   3. Core's own logo, unchanged — including when the admin has not said which
 *      mode the core logos are for, because guessing at somebody's artwork is
 *      worse than leaving it alone.
 *
 * Resolving all of this in ONE function is the point: every caller — the three
 * overridden renderer accessors, the switch's swap payload, the site footer —
 * gets the same answer, and no screen has to know that categories can have logos.
 *
 * @param string $slot 'logo' | 'logocompact' | 'favicon'
 * @param int $maxwidth passed through to core's sizing for 'logo'/'logocompact'
 * @param int $maxheight
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return moodle_url|false the URL, or false exactly as core returns for "none"
 */
function theme_nit_logo_url(string $slot, int $maxwidth = 300, int $maxheight = 300, ?string $mode = null) {
    $variants = theme_nit_logo_variants();

    // (1) The category's own mark, when this page is inside one that has it.
    if ($slot !== 'favicon') {
        $catlogo = theme_nit_page_category_logo_url($mode);
        if ($catlogo) {
            return $catlogo;
        }
    }

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
    // instead of making the visitor reload the page to see it change. Asked
    // through the chrome-group resolver, so inside a styled category it answers
    // with the group that category renders in for that mode — not the site's.
    $group = theme_nit_active_chrome_group($mode);
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
 * The bar's non-colour style layer, as CSS — one block per brand group.
 *
 * Two things live here, and both are per group for the same reason: how big and
 * how heavy each of the three subjects is set (titles / icons / log-in), and
 * which SHAPE a title takes on hover and on the current page.
 *
 * The shapes are here rather than in the stylesheet because CSS has no way to
 * switch which RULES apply from a custom property. So `_navbar.scss` writes the
 * rule once, reading three properties per state, and this decides what those
 * properties hold: the shape's own colour where the treatment is on, and an
 * inert value (`transparent`, the resting weight) where it is off. That file
 * therefore needs no knowledge of the shape names at all.
 *
 * Emitted for every group in the same order _brand.scss uses — `:root` first,
 * then the four switch classes — because a brand group class lands on <html>,
 * the very element `:root` matches: the two selectors tie on specificity and
 * source order is what decides. Same reason _brand.scss lists its roles that
 * way.
 *
 * "Bold" is one step up from whatever the group's resting title weight is, not a
 * fixed 800 — a group set to Regular titles should get a visible step, not a
 * jump past every weight in between. It never reflows the bar either: the link
 * reserves the heaviest width up front through a hidden twin sized at `max()` of
 * the three weights (see `.nit-navbar-link` in scss/components/_navbar.scss), so
 * a group that uses no bold reserves nothing extra.
 *
 * @return string CSS
 */
function theme_nit_navbar_style_scss(): string {
    $shapes = theme_nit_navbar_title_shapes();
    $states = theme_nit_navbar_title_states();
    $subjects = theme_nit_navbar_type_subjects();

    $css = "\n";
    foreach (array_keys(theme_nit_brand_groups()) as $gkey) {
        $class = theme_nit_brand_group_class($gkey);
        // Bare class, not `:root.<class>` — a group wrapper anywhere in the page
        // then carries these too, exactly as it carries its colours.
        $selector = ($class === '') ? ':root' : '.' . $class;
        $css .= $selector . " {\n";

        // Size and weight, one pair per subject.
        foreach (array_keys($subjects) as $subject) {
            $size = theme_nit_navbar_type_size($gkey, $subject);
            $weight = theme_nit_navbar_type_weight($gkey, $subject);
            $prefix = '--nit-nav' . $subject . '-';
            $css .= '    ' . $prefix . 'size: ' . $size . "px;\n";
            $css .= '    ' . $prefix . 'weight: ' . $weight . ";\n";
            if ($subject === 'icon') {
                // The pointer target, which follows the glyph up but never down.
                $css .= '    ' . $prefix . 'box: ' . theme_nit_navbar_icon_box($size) . "px;\n";
            }
        }

        // The two title shapes. "Bold" steps up from this group's own resting
        // title weight, clamped to the heaviest weight CSS has.
        $restweight = theme_nit_navbar_type_weight($gkey, 'title');
        $boldweight = min(900, $restweight + 200);
        foreach ($states as $state => $unused) {
            $shape = $shapes[theme_nit_navbar_title_shape($gkey, $state)];
            // Both treatments that paint take the state's own "style color"
            // role, so an admin picks the shape and its colour side by side.
            $colour = 'var(--nit-brand-navbar' . $state . 'stylecolor)';
            $prefix = '--nit-navtitle-' . ($state === 'titlehover' ? 'hover' : 'active');
            $css .= '    ' . $prefix . '-underline: ' . ($shape['underline'] ? $colour : 'transparent') . ";\n";
            $css .= '    ' . $prefix . '-bg: ' . ($shape['square'] ? $colour : 'transparent') . ";\n";
            $css .= '    ' . $prefix . '-weight: ' . ($shape['bold'] ? $boldweight : $restweight) . ";\n";
        }
        $css .= "}\n";
    }

    return $css;
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
        // The logo on the account screens is a link home, the same as the navbar
        // brand is on every other page. The panel is rendered as a Mustache
        // PARTIAL, so it cannot reach `{{config.wwwroot}}` on its own — the URL
        // has to travel in the context with the rest of the panel's data.
        'homeurl'  => (new moodle_url('/'))->out(false),
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
 * Serve the theme's admin-uploaded fonts, pictures and category logos.
 *
 * Two shapes, because the files have two owners:
 *
 *   * SYSTEM context — the fonts (theme_nit_font_slots()), the account-screen
 *     pictures (theme_nit_auth_image_slots()) and the alternative site logos
 *     (theme_nit_logo_variants()). Mirrors theme_boost_pluginfile(): each upload
 *     lives in a file area of its own and the theme revision, not the itemid,
 *     busts the cache.
 *   * CATEGORY context — a category's own navbar logo, one file area per display
 *     mode (theme_nit_category_logo_slots()). Stored with the category, so it is
 *     removed with the category, and served only while the category itself is
 *     visible to the reader.
 *
 * The gallery page (site:config only) is the sole writer; this endpoint is a
 * public, cache-able read of a self-hosted file, exactly like the site logo.
 * Public matters for the pictures in particular: whoever is looking at the log-in
 * screen is by definition not logged in yet.
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
    global $CFG;

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

    // A category's own logo. theme_config::setting_file_serve() cannot serve
    // these — it hard-codes the system context and itemid 0 — so the lookup is
    // done here, the same way local_nit_category serves a category's picture.
    $catareas = array_map(static fn($slot) => $slot['filearea'], theme_nit_category_logo_slots());
    if ($context->contextlevel == CONTEXT_COURSECAT && in_array($filearea, $catareas, true)) {
        if (!empty($CFG->forcelogin)) {
            require_login();
        }
        // get() honours visibility, so a hidden category's mark stays hidden.
        if (!core_course_category::get($context->instanceid, IGNORE_MISSING)) {
            send_file_not_found();
        }

        // args = [theme revision, …filepath…, filename]. The revision is only a
        // cache buster; the stored file's real itemid is 0.
        array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $file = get_file_storage()->get_file(
            $context->id,
            'theme_nit',
            $filearea,
            0,
            $filepath,
            $filename
        );
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }

        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        \core\session\manager::write_close(); // Unlock the session while the file streams.
        send_stored_file($file, 60 * 60 * 24 * 60, 0, $forcedownload, $options);
        return true;
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
