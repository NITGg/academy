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
 * Language strings for theme_nit.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT';
$string['choosereadme'] = 'NIT is a Boost-based theme foundation for the NIT LMS Framework. This M2 release provides the theme skeleton and asset pipeline; the design system and branding arrive in later milestones.';
$string['configtitle'] = 'NIT settings';
$string['foundation'] = 'Foundation';
$string['gallery'] = 'NIT Design System — Component Gallery';
$string['foundation_desc'] = 'This is the M2 foundation release: a thin Boost child with the SCSS and JavaScript build pipeline in place. Branding and component controls arrive in later milestones.';

// Brand colours — user-editable design tokens.
$string['colours'] = 'Brand colours';
$string['colours_desc'] = 'These colours drive the whole site. They are compiled into the Bootstrap palette and published as CSS custom properties (<code>--nit-primary</code>, <code>--nit-success</code>, …), so every component — core Moodle, Bootstrap and NIT-built alike — reads from them. Leave a field empty to keep the design-system default. Changing a colour rebuilds the theme CSS.';
$string['brandprimary'] = 'Primary';
$string['brandprimary_desc'] = 'The main brand colour: primary buttons, links, active states and accents.';
$string['brandsecondary'] = 'Secondary';
$string['brandsecondary_desc'] = 'Muted secondary actions and neutral UI accents.';
$string['brandsuccess'] = 'Success';
$string['brandsuccess_desc'] = 'Positive states: confirmations, completions, upward trends.';
$string['brandwarning'] = 'Warning';
$string['brandwarning_desc'] = 'Cautionary states: pending items and things needing attention.';
$string['branddanger'] = 'Danger';
$string['branddanger_desc'] = 'Errors, destructive actions and overdue states.';
$string['brandinfo'] = 'Info';
$string['brandinfo_desc'] = 'Informational highlights and neutral notices.';
$string['surfacecolour'] = 'Surface';
$string['surfacecolour_desc'] = 'The page background / card surface colour.';
$string['inkcolour'] = 'Ink (text)';
$string['inkcolour_desc'] = 'The default body text colour.';
$string['linecolour'] = 'Line';
$string['linecolour_desc'] = 'Borders, dividers and card outlines.';

// Block region (inherited Boost layouts use side-pre).
$string['region-side-pre'] = 'Right';

// Front page (Site home) full-width block regions — see config.php.
$string['region-fullwidth-top'] = 'Full width (top)';
$string['region-above-content'] = 'Above content';
$string['region-below-content'] = 'Below content';
$string['region-fullwidth-bottom'] = 'Full width (bottom)';

// Privacy.
$string['privacy:metadata'] = 'The NIT theme does not store any personal data.';
