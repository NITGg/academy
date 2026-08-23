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
 * English strings for local_nit_mlang.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Multilingual fields';
$string['privacy:metadata'] = 'The Multilingual fields plugin does not store any personal data.';

$string['nit_mlang:edit'] = 'Fill translatable fields one language at a time';

$string['status'] = 'Status';
$string['statuslangs'] = 'Translatable fields will be shown with one input per installed language pack: {$a}. Install or remove a language pack to change this list.';
$string['statusnofilter'] = 'Neither the "Multi-language content (v2)" nor the "Multi-language content" filter is switched on, so the {mlang} markup these fields produce will be displayed literally instead of being resolved to the reader\'s language. Enable one of them in Site administration > Plugins > Filters > Manage filters.';

$string['enabled'] = 'Enable multilingual fields';
$string['enabled_desc'] = 'Show one input per installed language instead of a single field, on every field whose content is translatable. Authors never type {mlang} markup by hand; it is composed for them when the form is submitted.';

$string['editors'] = 'Include rich text editors';
$string['editors_desc'] = 'Also add a language switcher to description, summary, intro and question-text editors. Turn this off to limit the feature to plain text fields such as Name and Title.';

$string['extratextfields'] = 'Additional translatable text fields';
$string['extratextfields_desc'] = 'One field name per line ("*" matches anything), added to the shipped list. Use this for a field the site adds that holds a display string. Shipped list:<pre>{$a}</pre>';

$string['extraexcludes'] = 'Exclusions';
$string['extraexcludes_desc'] = 'One <code>pagetype|fieldname</code> rule per line ("*" matches anything), added to the shipped list. Use this for a page where a listed field name is an identifier rather than a display string. The page type is shown in the <code>&lt;body&gt;</code> class of the page. Shipped list:<pre>{$a}</pre>';

$string['translations'] = 'Translations';
