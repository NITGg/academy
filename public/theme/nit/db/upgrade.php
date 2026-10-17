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
 * theme_nit upgrade steps.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the theme.
 *
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_theme_nit_upgrade($oldversion): bool {
    if ($oldversion < 2026091401) {
        // The gear menu setting went through two earlier shapes on the same
        // day (2026-09-14): a text box whose lines were
        // `identifier,component|url|rule`, then six tick-box settings. Both
        // shipped. The text box is back, but its lines are now
        // `English|Arabic|link`, so a value saved in the first shape would be
        // read as names and links it never meant. Clear whatever either shape
        // left behind; admin_apply_default_settings() then writes the new
        // default at the end of this upgrade, exactly as on a fresh site.
        unset_config('gearmenuitems', 'theme_nit');
        foreach (['navigation', 'management'] as $group) {
            foreach (['show', 'name', 'pages'] as $what) {
                unset_config('gearmenu_' . $group . '_' . $what, 'theme_nit');
            }
        }

        upgrade_plugin_savepoint(true, 2026091401, 'theme', 'nit');
    }

    if ($oldversion < 2026091402) {
        // The page lines gained a fourth part, who sees the page (`|admin`,
        // `|user,admin`...). A box the administrator never touched still holds
        // the three-part default, which works — the audience is inferred —
        // but shows nothing of the new syntax. Rewrite exactly that box to the
        // new default; an edited box is the administrator's and is left alone.
        $current = get_config('theme_nit', 'gearmenuitems');
        $new = \theme_nit\local\gear_menu::default_definition();
        $old = preg_replace('/^(-.*)\|[a-z,]+$/m', '$1', $new);
        if ($current !== false && trim((string) $current) === trim($old)) {
            set_config('gearmenuitems', $new, 'theme_nit');
        }

        upgrade_plugin_savepoint(true, 2026091402, 'theme', 'nit');
    }

    if ($oldversion < 2026101701) {
        // The navbar type now follows the Figma design site-wide: links 16px
        // SemiBold (the current page 18px Bold, in _navbar.scss) and "Log in"
        // 18px SemiBold. Those are the defaults in theme_nit_navbar_type_subjects();
        // clearing every group's saved title / log-in size and weight lets each
        // group fall back to them. The gallery can still set them again afterwards.
        global $DB;
        $names = $DB->get_fieldset_select('config_plugins', 'name',
            "plugin = 'theme_nit' AND (" . $DB->sql_like('name', ':a') . ' OR ' . $DB->sql_like('name', ':b') . ')',
            ['a' => 'navbarsize\_%', 'b' => 'navbarweight\_%']);
        foreach ($names as $name) {
            if (preg_match('/_(title|login)$/', $name)) {
                unset_config($name, 'theme_nit');
            }
        }
        theme_reset_all_caches();

        upgrade_plugin_savepoint(true, 2026101701, 'theme', 'nit');
    }

    if ($oldversion < 2026101702) {
        // The top bar is 80px now (the Figma nav), down from 100px, and the logo
        // default went from 66px to 56px so it fits with 12px either side. A site
        // still on the old default gets the new one; a height an administrator
        // chose is theirs and is left alone (the bar grows to carry it).
        if ((string) get_config('theme_nit', 'logoheightnavbar') === '66') {
            set_config('logoheightnavbar', 56, 'theme_nit');
        }
        theme_reset_all_caches();

        upgrade_plugin_savepoint(true, 2026101702, 'theme', 'nit');
    }

    if ($oldversion < 2026101703) {
        // Compact top bar (64px): logo 32px, icons 18px in a 36px box. The logo
        // moves only from a height this theme set (the 66 default, or the 56 the
        // 80px bar asked for); any other value was an administrator's choice.
        // The per-group icon sizes are cleared so every group takes the new
        // 18px default, as the title / log-in sizes were in 2026101701.
        if (in_array((string) get_config('theme_nit', 'logoheightnavbar'), ['66', '56'], true)) {
            set_config('logoheightnavbar', 32, 'theme_nit');
        }
        global $DB;
        $names = $DB->get_fieldset_select('config_plugins', 'name',
            "plugin = 'theme_nit' AND " . $DB->sql_like('name', ':a'), ['a' => 'navbarsize\_%']);
        foreach ($names as $name) {
            if (preg_match('/_icon$/', $name)) {
                unset_config($name, 'theme_nit');
            }
        }
        theme_reset_all_caches();

        upgrade_plugin_savepoint(true, 2026101703, 'theme', 'nit');
    }

    return true;
}
