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

// theme_nit_logo_slots() is read below. The theme's lib.php is loaded by its
// config.php, so it is already in memory whenever NIT is the active theme — but
// the admin tree is built under whatever theme the administrator is using, and
// this file has to work under any of them.
require_once(__DIR__ . '/lib.php');

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

// -----------------------------------------------------------------------------
// Logo size — appended to the CORE Logos page, not to a page of our own.
//
// Site administration → Appearance → Logos is where the logo is uploaded, and it
// is the page an administrator goes to when the logo is the wrong size. Sending
// them somewhere else to resize what they just uploaded is the kind of split
// that makes a setting undiscoverable, so the controls are added to that page
// instead — from here, without touching admin/settings/appearance.php.
//
// This works because of load order: appearance.php builds the `logos` page near
// the top of the file and includes every theme's settings.php near the bottom of
// the same file, so the page is already in the tree by the time this runs.
// `locate()` hands back the same object, and the guard means a future core that
// renames or drops the page costs us nothing.
//
// The values themselves are theme_nit config (the sizes are theme CSS), read
// back by theme_nit_logo_slots() / theme_nit_logo_height() in lib.php. Each one
// resets the theme caches on save, because the heights are compiled into the
// stylesheet rather than applied at render time.
if ($ADMIN->fulltree) {
    $logospage = $ADMIN->locate('logos');
    if ($logospage instanceof admin_settingpage) {
        $logospage->add(new admin_setting_heading(
            'theme_nit/logosizeheading',
            get_string('logosize', 'theme_nit'),
            get_string('logosize_desc', 'theme_nit')
        ));

        // The master control: one number that makes every logo on the site
        // bigger or smaller at once, leaving the proportions between the places
        // alone. Most sites will only ever touch this one.
        $setting = new admin_setting_configtext(
            'theme_nit/logoscale',
            get_string('logoscale', 'theme_nit'),
            get_string('logoscale_desc', 'theme_nit'),
            100,
            PARAM_INT,
            5
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $logospage->add($setting);

        // Then one height per place the logo is drawn, for the sites that want
        // a big mark in the footer and a small one in the bar. Defaults are the
        // heights the stylesheet already used, so leaving these alone changes
        // nothing. theme_nit_logo_slots() is the single source of truth: adding
        // a slot there adds its field here.
        foreach (theme_nit_logo_slots() as $slot) {
            $setting = new admin_setting_configtext(
                'theme_nit/' . $slot['setting'],
                get_string($slot['setting'], 'theme_nit'),
                get_string($slot['setting'] . '_desc', 'theme_nit'),
                $slot['default'],
                PARAM_INT,
                5
            );
            $setting->set_updatedcallback('theme_reset_all_caches');
            $logospage->add($setting);
        }
    }
}
