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

$string['howto'] = 'Finding a field name and a page type';
$string['howto_desc'] = '<p>The two settings below are written in terms of a <b>field name</b> and a <b>page type</b>. Here is how to read both off any page in the site.</p>
<p><b>Field name.</b> Every field on a Moodle form sits inside a container whose id is <code>fitem_id_&lt;fieldname&gt;</code>. Right-click the field, choose <i>Inspect</i>, then look up the tree until you see that container — whatever follows <code>fitem_id_</code> is the name to use here.</p>
<ul>
<li><code>&lt;div id="fitem_id_name"&gt;</code> &rarr; the field name is <code>name</code></li>
<li><code>&lt;div id="fitem_id_introeditor"&gt;</code> &rarr; the field name is <code>introeditor</code></li>
<li><code>&lt;div id="fitem_id_config_text"&gt;</code> &rarr; the field name is <code>config_text</code></li>
</ul>
<p>A quick way to see them all at once: with the <i>Elements</i> panel focused, press <kbd>Ctrl</kbd>+<kbd>F</kbd> and search for <code>fitem_id_</code>.</p>
<p><b>Do not read the name off the visible box.</b> Once this plugin has enhanced a field, the boxes on screen are the ones it created and they carry no name attribute — the real field is hidden behind them. The <code>fitem_id_</code> container is always right.</p>
<p><b>Page type.</b> It is on the page\'s <code>&lt;body&gt;</code> tag, as <code>class="… pagetype-mod-customcert-mod …"</code>. Drop the <code>pagetype-</code> prefix, so that page is <code>mod-customcert-mod</code>.</p>';

$string['extratextfields'] = 'Additional translatable text fields';
$string['extratextfields_desc'] = '<p><b>Adds</b> fields to the feature. One field name per line; <code>*</code> matches anything.</p>
<p>Use it when you find a field that holds a display string but is still a single box — typically one this site added rather than one Moodle ships.</p>
<p>This box <b>adds to</b> the list below, it does not replace it: everything listed there already works without you typing anything. To take a field <em>out</em>, use the Exclusions setting instead.</p>
<p>Already covered:</p><pre>{$a}</pre>';

$string['extraexcludes'] = 'Exclusions';
$string['extraexcludes_desc'] = '<p><b>Removes</b> fields from the feature, so they go back to being one ordinary box. One rule per line, written <code>pagetype|fieldname</code>; <code>*</code> matches anything. Rules apply to plain text fields and rich text editors alike.</p>
<p>Examples:</p>
<ul>
<li><code>*|config_text</code> &mdash; the HTML block\'s Content editor, on every page</li>
<li><code>*|introeditor</code> &mdash; every activity description in the site</li>
<li><code>site-index|introeditor</code> &mdash; activity descriptions, but only on the front page</li>
<li><code>mod-quiz-*|*</code> &mdash; every field on every quiz page</li>
<li><code>course-edit|shortname</code> &mdash; just the course short name, just on the course settings page</li>
</ul>
<p>This is also the only way to switch off one of the fields that are covered by default: the shipped list decides what is <em>in</em>, and this setting decides what comes back <em>out</em>. To switch off every editor at once, untick "Include rich text editors" above rather than listing them here.</p>
<p>Already excluded:</p><pre>{$a}</pre>';

$string['translations'] = 'Translations';
