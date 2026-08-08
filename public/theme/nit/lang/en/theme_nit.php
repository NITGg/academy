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

// Colour palette (edited on the gallery page).
$string['colours'] = 'Colour palette';
$string['colours_desc'] = 'Edit the site colour palette on the design-system gallery page:';
$string['coloureditor'] = 'Colour palette';
$string['coloureditor_desc'] = 'The colours the whole site is built from. Each is published as a CSS custom property (<code>--nit-primary</code>, <code>--nit-navbaraccent</code>, …), so components — the navbar included — read their colour from here. Pick a colour and save to recolour the site.';
$string['colourssaved'] = 'Colour palette saved. The theme CSS has been rebuilt.';
$string['coloursreset'] = 'Colour palette reset to the defaults.';
$string['savecolours'] = 'Save colours';
$string['resetcolours'] = 'Reset to defaults';

// Design-system gallery tabs.
$string['tab_colours'] = 'Colours';
$string['tab_fonts'] = 'Fonts';
$string['tab_components'] = 'Components';

// Fonts (edited on the gallery page). One self-hosted font file per site
// language: applied when the site runs in that language.
$string['fonts'] = 'Fonts';
$string['fonts_desc'] = 'Upload a font file (.ttf or .otf) for each site language. The English font is applied when the site is in English (<code>html[lang="en"]</code>) and the Arabic font when the site is in Arabic (<code>html[lang="ar"]</code>). Fonts are self-hosted — no external request is ever made. Leave a slot empty to keep the current font; the built-in system font is used until you upload one.';
$string['fonten'] = 'English font';
$string['fontar'] = 'Arabic font';
$string['fonten_help'] = 'Applied when the site language is English.';
$string['fontar_help'] = 'Applied when the site language is Arabic.';
$string['fontactive'] = 'Active';
$string['fontnone'] = 'Using the default system font.';
$string['fontpreview'] = 'Preview';
$string['fontsampleen'] = 'The quick brown fox jumps over the lazy dog — 0123456789';
$string['fontsamplear'] = 'أبجد هوّز حطّي كلمن — نصّ تجريبي ٠١٢٣٤٥٦٧٨٩';
$string['savefonts'] = 'Save fonts';
$string['resetfonts'] = 'Remove all fonts';
$string['fontssaved'] = 'Fonts saved. The theme CSS has been rebuilt.';
$string['fontsreset'] = 'Fonts removed. The site is back to the default system font.';
$string['fontinvalidtype'] = 'The {$a} was ignored: only .ttf and .otf font files are accepted.';
$string['fontuploaderror'] = 'The {$a} could not be uploaded. Please try again.';

// Block region (inherited Boost layouts use side-pre).
$string['region-side-pre'] = 'Right';

// Front page (Site home) full-width block regions — see config.php.
$string['region-fullwidth-top'] = 'Full width (top)';
$string['region-above-content'] = 'Above content';
$string['region-below-content'] = 'Below content';
$string['region-fullwidth-bottom'] = 'Full width (bottom)';

// Privacy.
$string['privacy:metadata'] = 'The NIT theme does not store any personal data.';
