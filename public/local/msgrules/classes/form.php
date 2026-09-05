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

namespace local_msgrules;

use html_writer;

/**
 * Drawing and reading back one row of ticks on the management screen.
 *
 * A class rather than two functions in manage.php so the markup and the parsing can be
 * exercised without standing up a web request - they are the pair most likely to drift apart,
 * because a renamed field breaks the save silently rather than loudly.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form {

    /**
     * Read one row's ticks back into a stored mode.
     *
     * "No restriction" wins over the three group ticks when both are posted: it is the master
     * switch, and honouring it here means a half-finished row can never close a course by
     * accident.
     *
     * @param string $suffix Field-name suffix identifying the row ('default' or a course id).
     * @return int
     */
    public static function read_mode(string $suffix): int {
        if (optional_param('open_' . $suffix, 0, PARAM_BOOL)) {
            return rules::OPEN;
        }

        $mode = rules::ALLOW_NOBODY;
        foreach (array_keys(rules::get_flags()) as $flag) {
            if (optional_param('flag_' . $suffix . '_' . $flag, 0, PARAM_BOOL)) {
                $mode |= $flag;
            }
        }

        return $mode;
    }

    /**
     * Render one row of ticks: the master "no restriction" plus one per group.
     *
     * @param string $suffix Field-name suffix identifying the row.
     * @param int|null $mode The mode to show, or null for "follow the setting for all courses".
     * @param string $rowlabel Accessible prefix so each tick says which row it belongs to.
     * @return string
     */
    public static function render_ticks(string $suffix, ?int $mode, string $rowlabel): string {
        $inherits = $mode === null;
        $effective = $mode ?? rules::get_default_mode();

        $out = self::tick(
            'open_' . $suffix,
            get_string('modeopen', 'local_msgrules'),
            !$inherits && rules::is_open($effective),
            $rowlabel,
            'me-4 fw-bold'
        );

        foreach (rules::get_flags() as $flag => $label) {
            $out .= self::tick(
                'flag_' . $suffix . '_' . $flag,
                $label,
                !$inherits && rules::allows($effective, $flag),
                $rowlabel
            );
        }

        if ($inherits) {
            // Nothing of its own: say so, and show what it currently resolves to, so the row
            // never leaves the reader to go and look the default up.
            $out .= html_writer::div(
                get_string('followsdefault', 'local_msgrules', rules::describe($effective)),
                'small text-muted mt-1'
            );
        }

        return $out;
    }

    /**
     * One checkbox with its label.
     *
     * @param string $name Field name.
     * @param string $label Visible label.
     * @param bool $checked
     * @param string $rowlabel Which row this belongs to, for the title attribute.
     * @param string $extraclass Extra classes on the wrapper.
     * @return string
     */
    private static function tick(
        string $name,
        string $label,
        bool $checked,
        string $rowlabel,
        string $extraclass = ''
    ): string {
        $id = 'id_' . $name;

        return html_writer::div(
            html_writer::empty_tag('input', array_filter([
                'type'    => 'checkbox',
                'class'   => 'form-check-input',
                'name'    => $name,
                'id'      => $id,
                'value'   => 1,
                'checked' => $checked ? 'checked' : null,
            ])) .
            html_writer::tag('label', $label, [
                'for'   => $id,
                'class' => 'form-check-label',
                // The visible label is just "Teachers"; screen readers and hover need to know
                // which of the many identical rows it belongs to.
                'title' => $rowlabel . ' - ' . $label,
            ]),
            trim('form-check form-check-inline ' . $extraclass)
        );
    }
}
