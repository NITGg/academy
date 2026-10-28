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
 * Cache definitions for local_appleauth.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Per-IP request counters for throttling the token endpoint (see token.php).
    'ratelimit' => [
        'mode'         => cache_store::MODE_APPLICATION,
        'simplekeys'   => true,
        'simpledata'   => false,
    ],
    // Apple's public signing keys. Entries carry their fetch time; apple_token
    // refetches after 24h, or sooner (at most every 5 min) on an unknown key id.
    'jwks' => [
        'mode'         => cache_store::MODE_APPLICATION,
        'simplekeys'   => true,
        'simpledata'   => false,
    ],
];
