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

use moodle_url;
use html_writer;
use html_table;

/**
 * Renders the "fields" management table. Shared by the admin template editor
 * (local_jobform manage.php) and the per-activity editor (mod_jobform).
 *
 * The caller supplies its own URLs so the same widget drives both the global
 * template and an activity's copy.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields_ui {

    /**
     * Render the full fields panel: an "Add field" button and the table.
     *
     * @param array $fields field records (->id, ->name, ->type, ->configdata, ->required)
     * @param moodle_url $editurl page that shows the add/edit form (gets ?id=)
     * @param moodle_url $actionurl page that handles delete/move (gets ?fieldaction=&fieldid=&sesskey=)
     * @return string HTML
     */
    public static function render(array $fields, moodle_url $editurl, moodle_url $actionurl): string {
        $out = html_writer::start_div('local-jobform-fields');

        $addurl = new moodle_url($editurl);
        $out .= html_writer::div(
            html_writer::link($addurl, get_string('addfield', 'local_jobform'),
                ['class' => 'btn btn-primary']),
            'mb-3'
        );

        if (!$fields) {
            $out .= html_writer::div(get_string('nofields', 'local_jobform'), 'alert alert-info');
            $out .= html_writer::end_div();
            return $out;
        }

        $table = new html_table();
        $table->head = [
            get_string('fieldname', 'local_jobform'),
            get_string('fieldtype', 'local_jobform'),
            get_string('fielddetails', 'local_jobform'),
            get_string('fieldrequired', 'local_jobform'),
            get_string('actions', 'local_jobform'),
        ];
        $table->attributes['class'] = 'generaltable local-jobform-fields-table';

        $count = count($fields);
        $i = 0;
        foreach ($fields as $field) {
            $i++;
            $table->data[] = [
                format_string($field->name),
                get_string(field_types::all()[$field->type] ?? 'fieldtype_text', 'local_jobform'),
                self::describe($field),
                $field->required ? get_string('yes') : get_string('no'),
                self::row_actions($field, $editurl, $actionurl, $i === 1, $i === $count),
            ];
        }

        $out .= html_writer::table($table);
        $out .= html_writer::end_div();
        return $out;
    }

    /**
     * A short human description of a field's type-specific configuration.
     *
     * @param object $field
     * @return string
     */
    protected static function describe(object $field): string {
        $config = field_types::decode_config($field->configdata ?? null);
        if (field_types::has_options($field->type)) {
            $mode = $config['multiple']
                ? get_string('fieldmultiple', 'local_jobform')
                : get_string('fieldsingle', 'local_jobform');
            $preview = implode(', ', array_slice($config['options'], 0, 5));
            if (count($config['options']) > 5) {
                $preview .= ' …';
            }
            return html_writer::span(s($preview), 'text-muted') .
                html_writer::empty_tag('br') .
                html_writer::span('(' . $mode . ')', 'badge badge-secondary');
        }
        if (field_types::is_fixed($field->type)) {
            return html_writer::span(s($config['fixedvalue']), 'text-muted');
        }
        return html_writer::span('—', 'text-muted');
    }

    /**
     * Build the per-row action buttons (edit, delete, move up/down).
     *
     * @param object $field
     * @param moodle_url $editurl
     * @param moodle_url $actionurl
     * @param bool $isfirst
     * @param bool $islast
     * @return string
     */
    protected static function row_actions(object $field, moodle_url $editurl,
            moodle_url $actionurl, bool $isfirst, bool $islast): string {
        global $OUTPUT;

        $buttons = '';

        // Edit.
        $edit = new moodle_url($editurl, ['fieldid' => $field->id]);
        $buttons .= $OUTPUT->action_icon($edit, new \pix_icon('t/edit', get_string('edit')));

        // Move up.
        if (!$isfirst) {
            $up = new moodle_url($actionurl,
                ['fieldaction' => 'moveup', 'fieldid' => $field->id, 'sesskey' => sesskey()]);
            $buttons .= $OUTPUT->action_icon($up, new \pix_icon('t/up', get_string('moveup')));
        }
        // Move down.
        if (!$islast) {
            $down = new moodle_url($actionurl,
                ['fieldaction' => 'movedown', 'fieldid' => $field->id, 'sesskey' => sesskey()]);
            $buttons .= $OUTPUT->action_icon($down, new \pix_icon('t/down', get_string('movedown')));
        }
        // Delete. The action page shows an $OUTPUT->confirm() dialog before deleting.
        $delete = new moodle_url($actionurl,
            ['fieldaction' => 'delete', 'fieldid' => $field->id, 'sesskey' => sesskey()]);
        $buttons .= $OUTPUT->action_icon($delete, new \pix_icon('t/delete', get_string('delete')));

        return $buttons;
    }
}
