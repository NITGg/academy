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
 * MUC cache definitions for local_nit_media.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Video playing times, in seconds. Two kinds of key share the store because
    // both answer the same question at different costs: "hash_<contenthash>" is
    // the parse result for a set of bytes, "cm_<cmid>_<cacherev>" is the same
    // number reached without the file query. Both are immutable for their key,
    // so nothing here is ever invalidated - see \local_nit_media\duration.
    'duration' => [
        'mode'                   => \core_cache\store::MODE_APPLICATION,
        'simplekeys'             => true,
        'simpledata'             => true,
        'staticacceleration'     => true,
        'staticaccelerationsize' => 100,
    ],
];
