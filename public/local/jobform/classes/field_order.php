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

namespace local_jobform;

/**
 * The order the fields table lists fields in, and how "move up / down" walks it.
 *
 * fields_ui lists fields by section — one section per group, in group order,
 * then the ungrouped fields — so a field's neighbours are the previous and next
 * fields of the SAME section, not the globally adjacent rows. Both editors (the
 * template's template_manager and mod_jobform's instance_manager) move fields
 * through here, so an arrow always does what the table shows.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_order {

    /**
     * The section a field is listed under: its group id, or 0 when it has no
     * group or its group no longer exists.
     *
     * @param object $field
     * @param array $groups group records keyed by id
     * @return int
     */
    public static function section_of(object $field, array $groups): int {
        $gid = (int) ($field->groupid ?? 0);
        return ($gid && isset($groups[$gid])) ? $gid : 0;
    }

    /**
     * Fields bucketed by section, each bucket in display order.
     *
     * @param array $fields field records in display order (sortorder ASC, id ASC)
     * @param array $groups group records keyed by id
     * @return array section id => field records; key 0 holds the ungrouped ones
     */
    public static function sections(array $fields, array $groups): array {
        $sections = [];
        foreach ($fields as $field) {
            $sections[self::section_of($field, $groups)][] = $field;
        }
        return $sections;
    }

    /**
     * Sort orders after moving one field a step up or down within its section.
     *
     * The field swaps places with the previous / next field of its own section:
     * the globally adjacent field may sit in another section, and swapping with
     * that one changes nothing on screen. The whole list is then renumbered
     * 0..n-1, which also clears any ties — two fields sharing a sortorder value
     * can never be swapped by exchanging the values alone.
     *
     * @param array $fields field records in display order (sortorder ASC, id ASC)
     * @param array $groups group records keyed by id
     * @param int $id the field to move
     * @param int $direction -1 up, +1 down
     * @return array field id => new sortorder for the rows whose value changes;
     *               empty when the field is unknown or already at the edge of its section
     */
    public static function move(array $fields, array $groups, int $id, int $direction): array {
        $order = array_values($fields);
        $index = null;
        foreach ($order as $i => $field) {
            if ((int) $field->id === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return [];
        }

        // The neighbour is the nearest field of the same section in that direction.
        $section = self::section_of($order[$index], $groups);
        $step = $direction < 0 ? -1 : 1;
        $swap = null;
        for ($i = $index + $step; isset($order[$i]); $i += $step) {
            if (self::section_of($order[$i], $groups) === $section) {
                $swap = $i;
                break;
            }
        }
        if ($swap === null) {
            return [];
        }

        [$order[$index], $order[$swap]] = [$order[$swap], $order[$index]];

        $changes = [];
        foreach ($order as $i => $field) {
            if ((int) $field->sortorder !== $i) {
                $changes[(int) $field->id] = $i;
            }
        }
        return $changes;
    }
}
