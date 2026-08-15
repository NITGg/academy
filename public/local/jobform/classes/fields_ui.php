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
     * Render the full fields panel: action buttons, the groups bar and the table.
     *
     * @param array $fields field records (->id, ->name, ->groupid, ->type, ->configdata, ->required)
     * @param moodle_url $editurl page that shows the add/edit field form
     * @param moodle_url $actionurl page that handles field/group delete/move (gets ?fieldaction=…&sesskey=)
     * @param array $opts optional extras:
     *      'groups'        => group records keyed by id (for the column + the groups bar)
     *      'groupediturl'  => moodle_url to the add/edit group page (enables "Add group" + group editing)
     *      'usedefaulturl' => moodle_url that resets to the default template (enables "Use default fields")
     * @return string HTML
     */
    public static function render(array $fields, moodle_url $editurl, moodle_url $actionurl,
            array $opts = []): string {
        $groups = $opts['groups'] ?? [];
        $groupediturl = $opts['groupediturl'] ?? null;
        $usedefaulturl = $opts['usedefaulturl'] ?? null;

        $out = html_writer::start_div('local-jobform-fields');

        // Action buttons: Add field · Add group · Use default fields.
        $buttons = html_writer::link(new moodle_url($editurl),
            get_string('addfield', 'local_jobform'), ['class' => 'btn btn-primary']);
        if ($groupediturl) {
            $buttons .= ' ' . html_writer::link(new moodle_url($groupediturl),
                get_string('addgroup', 'local_jobform'), ['class' => 'btn btn-outline-primary']);
        }
        if ($usedefaulturl) {
            $buttons .= ' ' . html_writer::link($usedefaulturl,
                get_string('usedefaultfields', 'local_jobform'), ['class' => 'btn btn-outline-secondary']);
        }
        $out .= html_writer::div($buttons, 'mb-3');

        // Groups bar.
        if ($groupediturl) {
            $out .= self::render_groups_bar($groups, $groupediturl, $actionurl);
        }

        if (!$fields) {
            $out .= html_writer::div(get_string('nofields', 'local_jobform'), 'alert alert-info');
            $out .= html_writer::end_div();
            return $out;
        }

        $table = new html_table();
        $table->head = [
            get_string('fieldname', 'local_jobform'),
            get_string('fieldgroup', 'local_jobform'),
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
            $gid = (int) ($field->groupid ?? 0);
            $groupcell = ($gid && isset($groups[$gid]))
                ? html_writer::span(s(mlang::resolve($groups[$gid]->name)), 'badge badge-info')
                : html_writer::span('—', 'text-muted');
            $table->data[] = [
                mlang::display($field->name),
                $groupcell,
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
     * Render the groups bar: each group as a chip with edit/delete, or a hint if none.
     *
     * @param array $groups group records keyed by id
     * @param moodle_url $groupediturl add/edit group page (gets ?groupid=)
     * @param moodle_url $actionurl handles group delete (gets ?groupaction=delete&groupid=&sesskey=)
     * @return string HTML
     */
    protected static function render_groups_bar(array $groups, moodle_url $groupediturl,
            moodle_url $actionurl): string {
        global $OUTPUT;

        $out = html_writer::start_div('local-jobform-groups card p-3 mb-3');
        $out .= html_writer::tag('strong', get_string('groups', 'local_jobform')) . ' ';

        if (!$groups) {
            $out .= html_writer::span(get_string('nogroups', 'local_jobform'), 'text-muted');
            $out .= html_writer::end_div();
            return $out;
        }

        foreach ($groups as $group) {
            $edit = new moodle_url($groupediturl, ['groupid' => $group->id]);
            $delete = new moodle_url($actionurl,
                ['groupaction' => 'delete', 'groupid' => $group->id, 'sesskey' => sesskey()]);
            $chip = html_writer::span(s(mlang::resolve($group->name)), 'mr-1') .
                $OUTPUT->action_icon($edit, new \pix_icon('t/edit', get_string('edit'))) .
                $OUTPUT->action_icon($delete, new \pix_icon('t/delete', get_string('delete')));
            $out .= html_writer::span($chip, 'badge badge-light border mr-2 p-2');
        }

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
            $resolved = array_map([mlang::class, 'resolve'], $config['options']);
            $preview = implode(', ', array_slice($resolved, 0, 5));
            if (count($resolved) > 5) {
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
