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
 * NIT layout for the account screens (log in, sign up, reset, confirm).
 *
 * A copy of theme_boost/layout/login.php with one addition: the left panel's
 * content. Boost's version builds no context for it beyond `leftinstructions`,
 * and `core/login_panel` is rendered as a Mustache PARTIAL — it is handed
 * whatever context the layout built and cannot fetch anything of its own. So the
 * site logo and the admin-written quote have to be put into the context here, in
 * a layout file this theme owns, rather than in the template that displays them.
 *
 * Everything else is Boost's and is deliberately left identical, so a change to
 * the log-in layout in a future Moodle release is a small, obvious diff against
 * this file rather than a silent divergence.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

$bodyattributes = $OUTPUT->body_attributes();

// Left-panel instructions. Only set when the admin has defined custom instructions;
// the template falls back to the default welcome content when this is empty/null.
$leftinstructions = !empty($CFG->auth_instructions)
    ? format_text($CFG->auth_instructions, FORMAT_MOODLE, ['context' => context_system::instance()])
    : null;

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'leftinstructions' => $leftinstructions,
    // NIT: the logo and the quote drawn over the panel picture. Edited on the
    // gallery page's "Log-in & sign-up" tab; see theme_nit_auth_panel_content().
    'nitpanel' => theme_nit_auth_panel_content($OUTPUT),
];

echo $OUTPUT->render_from_template('theme_boost/login', $templatecontext);
