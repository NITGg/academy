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
 * NIT theme settings (M2 placeholder) + admin links.
 *
 * Branding controls (colours, logo, fonts, presets) arrive in M5. For now this
 * reserves the settings surface and adds an admin-only link to the design-system
 * gallery under Site administration → Appearance.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Admin-only link to the design-system gallery (not shown to end users).
$ADMIN->add('appearance', new admin_externalpage(
    'theme_nit_gallery',
    get_string('gallery', 'theme_nit'),
    new moodle_url('/theme/nit/gallery.php'),
    'moodle/site:config'
));

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('themesettingnit', get_string('configtitle', 'theme_nit'));

    $settings->add(new admin_setting_heading(
        'theme_nit/foundationinfo',
        get_string('foundation', 'theme_nit'),
        get_string('foundation_desc', 'theme_nit')
    ));

    // -------------------------------------------------------------------------
    // Brand colours (user-editable design tokens).
    //
    // Each picker feeds a semantic SCSS variable in theme_nit_get_pre_scss(),
    // which Bootstrap compiles against, and is re-published as a --nit-* CSS
    // custom property in scss/foundation/_root.scss — so every component on the
    // site reads its colour from here. Defaults mirror the design-system
    // primitives (scss/tokens/_primitives.scss); an empty value keeps the
    // default. Each setting purges the theme cache so the CSS recompiles.
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'theme_nit/coloursinfo',
        get_string('colours', 'theme_nit'),
        get_string('colours_desc', 'theme_nit')
    ));

    // Config key => [default hex, string key].
    $colours = [
        'brandprimary'   => ['#2a50c8', 'brandprimary'],
        'brandsecondary' => ['#626c7a', 'brandsecondary'],
        'brandsuccess'   => ['#1e7a54', 'brandsuccess'],
        'brandwarning'   => ['#9a6410', 'brandwarning'],
        'branddanger'    => ['#b23a2e', 'branddanger'],
        'brandinfo'      => ['#0e7c86', 'brandinfo'],
        'surfacecolour'  => ['#ffffff', 'surfacecolour'],
        'inkcolour'      => ['#171b22', 'inkcolour'],
        'linecolour'     => ['#dce1e8', 'linecolour'],
    ];

    foreach ($colours as $key => [$default, $stringkey]) {
        $setting = new admin_setting_configcolourpicker(
            'theme_nit/' . $key,
            get_string($stringkey, 'theme_nit'),
            get_string($stringkey . '_desc', 'theme_nit'),
            $default
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $settings->add($setting);
    }
}
