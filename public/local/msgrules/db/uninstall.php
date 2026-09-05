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
 * Uninstall hook for local_msgrules.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Hand the site back the conversations the rules had closed.
 *
 * The rules live as rows in other people's blocked-users lists, so dropping our own tables
 * without clearing them first would leave every restriction in force permanently, with
 * nothing left on the site to explain where it came from or how to undo it.
 *
 * @return bool
 */
function xmldb_local_msgrules_uninstall(): bool {
    \local_msgrules\sync::remove_all_managed();

    return true;
}
