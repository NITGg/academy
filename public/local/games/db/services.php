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
 * External functions for the Games Corner.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // Called by the browser at the end of a round. `ajax => true` is what makes
    // it reachable from /lib/ajax/service.php without a web-service token.
    'local_games_submit_result' => [
        'classname'   => \local_games\external\submit_result::class,
        'description' => 'Record the result of one finished game round and return the updated totals.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/games:play',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
