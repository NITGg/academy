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
 * Admin settings for local_nit_category.
 *
 * There is deliberately almost nothing here: the catalogue derives its filters from the
 * course custom fields that exist, so the normal way to add or remove a filter is to add
 * or remove a custom field, not to configure this page.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nit_category',
        get_string('pluginname', 'local_nit_category'));

    $settings->add(new admin_setting_heading('local_nit_category/catalogueheading',
        get_string('catalogueheading', 'local_nit_category'),
        get_string('cataloguedesc', 'local_nit_category')));

    $settings->add(new admin_setting_configtext('local_nit_category/excludefilterfields',
        get_string('excludefilterfields', 'local_nit_category'),
        get_string('excludefilterfields_desc', 'local_nit_category'),
        '', PARAM_TEXT));

    // Which course field answers which of the six filters. The panel is a fixed list —
    // see catalogue::filter_roles() — so this is a wiring diagram, not a way to add
    // filters: name a field and that filter reads it, leave it blank for the default.
    $settings->add(new admin_setting_heading('local_nit_category/filterfieldsheading',
        get_string('filterfieldsheading', 'local_nit_category'),
        get_string('filterfieldsdesc', 'local_nit_category')));

    foreach (\local_nit_category\catalogue::filter_roles() as $role => $spec) {
        $settings->add(new admin_setting_configtext('local_nit_category/filterfield_' . $role,
            get_string('filterfield_' . $role, 'local_nit_category'),
            get_string('filterfield_' . $role . '_desc', 'local_nit_category'),
            $spec['field'], PARAM_ALPHANUMEXT));
    }

    $ADMIN->add('localplugins', $settings);

    // The record of searches that found nothing (AC-4.22.4). A report rather than a
    // setting, so it gets its own entry instead of a link buried in the settings page.
    $ADMIN->add('reports', new admin_externalpage(
        'local_nit_category_searchlog',
        get_string('searchlog', 'local_nit_category'),
        new moodle_url('/local/nit_category/searchlog.php'),
        'moodle/site:config'
    ));
}
