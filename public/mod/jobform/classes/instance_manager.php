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

namespace mod_jobform;

use local_jobform\field_types;

/**
 * Manages one Job Form activity's own copy of the fields (table jobform_field).
 *
 * Mirrors local_jobform\template_manager but scoped to a single activity, so
 * editing here never touches the global template or any other course.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instance_manager {

    /** @var string This activity's fields table. */
    const TABLE = 'jobform_field';

    /**
     * Copy the global default template into a freshly created activity.
     *
     * @param int $jobformid
     * @return void
     */
    public static function seed_from_template(int $jobformid): void {
        global $DB, $USER;

        $template = \local_jobform\template_manager::get_fields();
        $now = time();
        foreach ($template as $tfield) {
            $record = new \stdClass();
            $record->jobformid = $jobformid;
            $record->name = $tfield->name;
            $record->type = $tfield->type;
            $record->configdata = $tfield->configdata;
            $record->required = $tfield->required;
            $record->sortorder = $tfield->sortorder;
            $record->usermodified = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * All fields for an activity, in display order.
     *
     * @param int $jobformid
     * @return array records keyed by id
     */
    public static function get_fields(int $jobformid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['jobformid' => $jobformid], 'sortorder ASC, id ASC');
    }

    /**
     * A single field or false.
     *
     * @param int $id
     * @param int $jobformid guard so a field can only be edited via its own activity
     * @return object|false
     */
    public static function get_field(int $id, int $jobformid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'jobformid' => $jobformid]);
    }

    /**
     * Create or update a field for this activity from validated form data.
     *
     * @param int $jobformid
     * @param object $data expects ->id, ->name, ->type, ->required and type-specific inputs
     * @return int the field id
     */
    public static function save_field(int $jobformid, object $data): int {
        global $DB, $USER;

        $now = time();
        $record = new \stdClass();
        $record->jobformid = $jobformid;
        $record->name = trim($data->name);
        $record->type = field_types::is_valid($data->type) ? $data->type : field_types::TYPE_TEXT;
        $record->configdata = field_types::encode_config(
            $record->type,
            $data->options ?? '',
            !empty($data->multiple),
            $data->fixedvalue ?? ''
        );
        $record->required = !empty($data->required) ? 1 : 0;
        $record->usermodified = $USER->id;
        $record->timemodified = $now;

        if (!empty($data->fieldid)) {
            $existing = self::get_field((int) $data->fieldid, $jobformid);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record(self::TABLE, $record);
                return (int) $record->id;
            }
        }

        $record->sortorder = self::next_sortorder($jobformid);
        $record->timecreated = $now;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a field (and any answers referencing it) from this activity.
     *
     * @param int $id
     * @param int $jobformid
     * @return void
     */
    public static function delete_field(int $id, int $jobformid): void {
        global $DB;
        if (!self::get_field($id, $jobformid)) {
            return;
        }
        $DB->delete_records('jobform_submission_data', ['fieldid' => $id]);
        $DB->delete_records(self::TABLE, ['id' => $id, 'jobformid' => $jobformid]);
    }

    /**
     * Move a field up or down within its activity.
     *
     * @param int $id
     * @param int $jobformid
     * @param int $direction -1 up, +1 down
     * @return void
     */
    public static function reorder(int $id, int $jobformid, int $direction): void {
        global $DB;
        $fields = array_values(self::get_fields($jobformid));
        $index = null;
        foreach ($fields as $i => $f) {
            if ((int) $f->id === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $index + ($direction < 0 ? -1 : 1);
        if ($swap < 0 || $swap >= count($fields)) {
            return;
        }
        $a = $fields[$index];
        $b = $fields[$swap];
        $tmp = (int) $a->sortorder;
        $DB->set_field(self::TABLE, 'sortorder', (int) $b->sortorder, ['id' => $a->id]);
        $DB->set_field(self::TABLE, 'sortorder', $tmp, ['id' => $b->id]);
    }

    /**
     * The next sortorder value for appending a field.
     *
     * @param int $jobformid
     * @return int
     */
    protected static function next_sortorder(int $jobformid): int {
        global $DB;
        $max = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {' . self::TABLE . '} WHERE jobformid = ?', [$jobformid]);
        return (int) $max + 1;
    }
}
