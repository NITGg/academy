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

    // The colour palette is edited on the design-system gallery page
    // (Appearance → NIT Design System), not here — it lives beside the live
    // component preview so changes can be seen in context.
    $settings->add(new admin_setting_description(
        'theme_nit/colourslink',
        get_string('colours', 'theme_nit'),
        get_string('colours_desc', 'theme_nit') . ' ' .
            html_writer::link(
                new moodle_url('/theme/nit/gallery.php'),
                get_string('gallery', 'theme_nit')
            )
    ));

    // The picture beside the log-in and sign-up cards.
    //
    // Boost has a setting of this name too, and uploading there does nothing:
    // theme_config::setting_file_url() builds the pluginfile URL from the ACTIVE
    // theme's name, so while NIT is in use every lookup — Boost's own included —
    // goes to theme_nit's file area. With nothing there, Boost falls back to its
    // bundled AI-generated photo, which is what the log-in page has been showing.
    // The setting therefore has to live here, on the theme that is running. See
    // theme_nit_login_background_scss(), which centres the crop and clears the
    // watermark.
    $loginbg = new admin_setting_configstoredfile(
        'theme_nit/loginbackgroundimage',
        get_string('loginbackgroundimage', 'theme_nit'),
        get_string('loginbackgroundimage_desc', 'theme_nit'),
        'loginbackgroundimage',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['web_image']]
    );
    // The URL is baked into the compiled CSS, so a new picture is only a new
    // picture once the theme CSS is rebuilt.
    $loginbg->set_updatedcallback('theme_reset_all_caches');
    $settings->add($loginbg);

    // Performance: how long the Site home caches its course cards + site
    // counters before recomputing them (see theme_nit_get_courses /
    // theme_nit_get_site_stats in lib.php). Higher = less DB load but staler
    // numbers. Set to 0 to disable caching. The picker stores seconds.
    $settings->add(new admin_setting_configduration(
        'theme_nit/frontpagecachettl',
        get_string('frontpagecachettl', 'theme_nit'),
        get_string('frontpagecachettl_desc', 'theme_nit'),
        300
    ));
}
