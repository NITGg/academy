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

namespace local_profilefields\privacy;

use core_privacy\local\metadata\null_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_profilefields.
 *
 * The plugin stores site-wide layout settings only. The field values themselves
 * belong to core - `user` columns and `user_info_data` rows - and are reported
 * by core's own providers.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {

    /**
     * The reason this plugin stores no personal data.
     *
     * @return string language string identifier
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
