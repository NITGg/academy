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

namespace local_nit_category\privacy;

/**
 * Privacy declaration for local_nit_category.
 *
 * The catalogue pages read courses, categories and prices and store nothing about the
 * person reading them. The one table the plugin owns — the log of searches that found
 * nothing, see {@see \local_nit_category\search_log} — records the term and a count and
 * deliberately has no user column, so a term on it cannot be traced to anybody.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Why this plugin stores no personal data.
     *
     * @return string the identifier of a string explaining the reason
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
