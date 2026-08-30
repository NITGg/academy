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
 * Hook callbacks for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Hold a user who never went through the sign-up form (an OAuth2 account,
        // typically) on the page that collects what sign-up would have asked for.
        'hook'     => \core\hook\output\before_http_headers::class,
        'callback' => \local_profilefields\hook_callbacks::class . '::before_http_headers',
    ],
    [
        // Put AC-4.2's expiry and resend limits around core's confirmation flow.
        // after_config is the last moment before page code runs, which is where
        // /login/confirm.php and /login/index.php make the decisions we need to
        // get in front of.
        'hook'     => \core\hook\after_config::class,
        'callback' => \local_profilefields\hook_callbacks::class . '::after_config',
    ],
];
