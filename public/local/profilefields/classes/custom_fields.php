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

namespace local_profilefields;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom profile fields, expressed in the same "where does it appear" terms as
 * the core fields.
 *
 * Nothing here invents storage: `user_info_field.signup` and
 * `user_info_field.visible` already answer the question, they are just spread
 * across two settings on two different screens. This class folds them into one
 * choice per field, and folds it back out again on save.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_fields {

    /**
     * Every custom profile field, in the order the profile pages show them.
     *
     * @return stdClass[] field records with categoryname attached, keyed by field id
     */
    public static function get_all(): array {
        global $DB;

        return $DB->get_records_sql("
            SELECT f.*, c.name AS categoryname, c.sortorder AS categorysortorder
              FROM {user_info_field} f
              JOIN {user_info_category} c ON c.id = f.categoryid
          ORDER BY c.sortorder, f.sortorder, f.id
        ");
    }

    /**
     * Where a field currently appears, as one of the manager's MODE_* values.
     *
     * @param stdClass $field a user_info_field record
     * @return string
     */
    public static function mode(stdClass $field): string {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        // The PROFILE_VISIBLE_* constants are defined as strings, so compare as ints.
        if ((int) $field->visible === (int) PROFILE_VISIBLE_NONE) {
            return manager::MODE_HIDDEN;
        }

        return empty($field->signup) ? manager::MODE_PROFILE : manager::MODE_BOTH;
    }

    /**
     * The placements a custom field can be given.
     *
     * @return array MODE_* value => menu label
     */
    public static function mode_options(): array {
        return [
            manager::MODE_BOTH    => get_string('modeboth', 'local_profilefields'),
            manager::MODE_PROFILE => get_string('modeprofile', 'local_profilefields'),
            manager::MODE_HIDDEN  => get_string('modehiddencustom', 'local_profilefields'),
        ];
    }

    /**
     * Who gets to see the field's value, once it is visible at all.
     *
     * @return array PROFILE_VISIBLE_* value => menu label
     */
    public static function visibility_options(): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        return [
            PROFILE_VISIBLE_ALL      => get_string('profilevisibleall', 'admin'),
            PROFILE_VISIBLE_TEACHERS => get_string('profilevisibleteachers', 'admin'),
            PROFILE_VISIBLE_PRIVATE  => get_string('profilevisibleprivate', 'admin'),
        ];
    }

    /**
     * Write one field's placement back to `user_info_field`.
     *
     * @param stdClass $field the current record
     * @param string $mode one of the manager's MODE_* values
     * @param int $visible a PROFILE_VISIBLE_* value
     * @param int $required 1 to make the field required
     * @param int $locked 1 to stop the user editing it
     * @return bool true when the record actually changed
     */
    public static function apply(stdClass $field, string $mode, int $visible, int $required, int $locked): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        // The PROFILE_VISIBLE_* constants are defined as strings, so work in ints.
        $none = (int) PROFILE_VISIBLE_NONE;
        $all = (int) PROFILE_VISIBLE_ALL;

        switch ($mode) {
            case manager::MODE_HIDDEN:
                $signup = 0;
                $visible = $none;
                break;
            case manager::MODE_PROFILE:
                $signup = 0;
                break;
            case manager::MODE_BOTH:
            default:
                $signup = 1;
                break;
        }

        // profile_get_signup_fields() skips anything nobody can see, so a field asked
        // to appear on sign-up has to be visible to someone. Anything else unexpected
        // falls back to the most permissive setting rather than vanishing silently.
        if ($visible !== $none && !array_key_exists($visible, self::visibility_options())) {
            $visible = $all;
        }
        if ($signup && $visible === $none) {
            $visible = $all;
        }

        $required = $required ? 1 : 0;
        $locked = $locked ? 1 : 0;

        if ((int) $field->signup === $signup && (int) $field->visible === $visible
                && (int) $field->required === $required && (int) $field->locked === $locked) {
            return false;
        }

        $DB->update_record('user_info_field', (object) [
            'id'       => $field->id,
            'signup'   => $signup,
            'visible'  => $visible,
            'required' => $required,
            'locked'   => $locked,
        ]);

        $updated = $DB->get_record('user_info_field', ['id' => $field->id]);
        \core\event\user_info_field_updated::create_from_field($updated)->trigger();

        return true;
    }

    /**
     * The field types an admin can create, ready for a menu.
     *
     * @return array datatype => human-readable name
     */
    public static function datatypes(): array {
        global $CFG;

        // Every row of the management page asks for this, and answering means a
        // directory scan plus a string lookup per field type.
        static $datatypes = null;
        if ($datatypes === null) {
            require_once($CFG->dirroot . '/user/profile/definelib.php');
            $datatypes = profile_list_datatypes();
            \core_collator::asort($datatypes);
        }

        return $datatypes;
    }
}
