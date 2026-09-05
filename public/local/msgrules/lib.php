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
 * Callbacks for local_msgrules.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * React to the "Enforce messaging rules" switch being flipped.
 *
 * Both directions need work, which is why this exists at all: switching the feature on has to
 * write the block rows, and switching it off has to take them away again. Without the second
 * half, turning the plugin off would leave the site exactly as locked down as it was and the
 * only way out would be the database.
 *
 * @return void
 */
function local_msgrules_enabled_changed(): void {
    // Either way the answer is a full rebuild: when enabled it derives the rows from the
    // matrix, and when disabled it removes every row the plugin owns. apply_now() does it in
    // this request on a site small enough to afford it, so flipping the switch and testing the
    // result are not separated by a cron run.
    \local_msgrules\sync::apply_now();
}
