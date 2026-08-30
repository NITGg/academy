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
 * Requests to change a field the learner is not allowed to set themselves.
 *
 * AC-4.5.4: "The country of record and the phone country code are read-only to
 * the learner. An action labelled 'Request a change' raises a support request to
 * an administrator, who may apply the change from the back office. Every such
 * change is written to the audit log with the administrator's identity, the old
 * value, the new value and a reason."
 *
 * Why these two fields and no others: the country of record is what decides which
 * price list a learner is charged from (GEO-2), so a learner who could edit it
 * could move themselves to a cheaper market after registering - which is the
 * whole thing Section 4.6 exists to prevent. The phone country code is locked for
 * the same reason, because it is what the country of record is derived from.
 *
 * The table is both the queue and the audit trail. Keeping them as one record
 * rather than two means the decision can never be separated from what it was
 * about: there is no way to have an approved change with no request behind it, or
 * a request whose outcome was recorded somewhere that has since been tidied away.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class changerequest {

    /** @var string Table holding requests and their outcomes. */
    const TABLE = 'local_profilefields_request';

    /** @var string Awaiting an administrator. */
    const STATUS_PENDING = 'pending';

    /** @var string Granted, and applied to the account. */
    const STATUS_APPROVED = 'approved';

    /** @var string Refused, with a reason the learner is shown. */
    const STATUS_REJECTED = 'rejected';

    /** @var string The country of record - what decides the learner's prices. */
    const FIELD_COUNTRY = 'country';

    /** @var string The phone field's country, from which the above is derived. */
    const FIELD_PHONECOUNTRY = 'phonecountry';

    /**
     * The fields a learner may ask to have changed.
     *
     * @return array<string, string> field key => localised label
     */
    public static function fields(): array {
        return [
            self::FIELD_COUNTRY => get_string('countryofrecord', 'local_profilefields'),
            self::FIELD_PHONECOUNTRY => get_string('fieldphone', 'local_profilefields'),
        ];
    }

    /**
     * This learner's request that is still waiting, if they have one.
     *
     * One at a time, per field. A learner who could stack requests would give the
     * administrator a queue of contradictory asks about the same account and no way
     * to tell which one is current.
     *
     * @param int $userid
     * @param string $field one of the FIELD_* constants, or '' for any
     * @return stdClass|null
     */
    public static function pending_for(int $userid, string $field = ''): ?stdClass {
        global $DB;

        $conditions = ['userid' => $userid, 'status' => self::STATUS_PENDING];
        if ($field !== '') {
            $conditions['field'] = $field;
        }

        $record = $DB->get_record(self::TABLE, $conditions, '*', IGNORE_MULTIPLE);

        return $record ?: null;
    }

    /**
     * Record a learner's request.
     *
     * @param int $userid the learner asking
     * @param string $field one of the FIELD_* constants
     * @param string $newvalue the value they want
     * @param string $reason why they say it should change
     * @return int the new row id, or 0 when one was already outstanding
     */
    public static function raise(int $userid, string $field, string $newvalue, string $reason): int {
        global $DB;

        if (!array_key_exists($field, self::fields())) {
            return 0;
        }
        if (self::pending_for($userid, $field)) {
            return 0;
        }

        return (int) $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'field' => $field,
            'oldvalue' => self::current_value($userid, $field),
            'newvalue' => \core_text::substr(trim($newvalue), 0, 255),
            'reason' => trim($reason),
            'status' => self::STATUS_PENDING,
            'decidedby' => 0,
            'decisionnote' => null,
            'timecreated' => time(),
            'timedecided' => 0,
        ]);
    }

    /**
     * Grant a request and write the new value onto the account.
     *
     * The value applied is the one recorded on the request, not one re-read from
     * the form: the administrator approved a specific change, and applying anything
     * else would make the audit row a description of something that did not happen.
     *
     * @param int $id request id
     * @param string $note the administrator's reason, kept for the audit trail
     * @return bool whether the change was applied
     */
    public static function approve(int $id, string $note = ''): bool {
        global $DB, $USER;

        $request = $DB->get_record(self::TABLE, ['id' => $id, 'status' => self::STATUS_PENDING]);
        if (!$request) {
            return false;
        }

        if (!self::apply($request)) {
            return false;
        }

        $DB->update_record(self::TABLE, (object) [
            'id' => $request->id,
            'status' => self::STATUS_APPROVED,
            'decidedby' => (int) $USER->id,
            'decisionnote' => trim($note),
            'timedecided' => time(),
        ]);

        return true;
    }

    /**
     * Refuse a request, with the reason the learner will be shown.
     *
     * @param int $id request id
     * @param string $note the administrator's reason
     * @return bool
     */
    public static function reject(int $id, string $note = ''): bool {
        global $DB, $USER;

        $request = $DB->get_record(self::TABLE, ['id' => $id, 'status' => self::STATUS_PENDING]);
        if (!$request) {
            return false;
        }

        $DB->update_record(self::TABLE, (object) [
            'id' => $request->id,
            'status' => self::STATUS_REJECTED,
            'decidedby' => (int) $USER->id,
            'decisionnote' => trim($note),
            'timedecided' => time(),
        ]);

        return true;
    }

    /**
     * Write an approved value onto the account.
     *
     * The two fields are stored in different places - the country of record is a
     * core user column, the phone country lives inside a custom profile field's
     * value - so each is applied its own way.
     *
     * @param stdClass $request the approved request
     * @return bool
     */
    protected static function apply(stdClass $request): bool {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $DB->get_record('user', ['id' => $request->userid, 'deleted' => 0]);
        if (!$user) {
            return false;
        }

        if ($request->field === self::FIELD_COUNTRY) {
            // user_update_user() rather than a raw set_field: it fires the
            // user_updated event, which is what any cache of this value listens to.
            user_update_user((object) [
                'id' => $user->id,
                'country' => \core_text::strtoupper($request->newvalue),
            ], false, true);

            return true;
        }

        // The phone field stores "COUNTRY number" in one value, so the number has
        // to be carried across rather than replaced - the learner asked to change
        // where they are, not what their number is.
        $element = signup::phone_element();
        if ($element === '') {
            return false;
        }

        $shortname = substr($element, strlen('profile_field_'));
        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if (!$field) {
            return false;
        }

        $existing = (string) $DB->get_field('user_info_data', 'data',
            ['userid' => $user->id, 'fieldid' => $field->id]);

        $parts = explode(' ', trim($existing), 2);
        $number = trim($parts[1] ?? '');
        $value = \core_text::strtoupper($request->newvalue) . ' ' . $number;

        $existingrow = $DB->get_record('user_info_data',
            ['userid' => $user->id, 'fieldid' => $field->id]);

        if ($existingrow) {
            $DB->set_field('user_info_data', 'data', $value, ['id' => $existingrow->id]);
        } else {
            $DB->insert_record('user_info_data', (object) [
                'userid' => $user->id,
                'fieldid' => $field->id,
                'data' => $value,
                'dataformat' => 0,
            ]);
        }

        return true;
    }

    /**
     * The value a field currently holds, for the "old value" half of the audit row.
     *
     * @param int $userid
     * @param string $field one of the FIELD_* constants
     * @return string
     */
    public static function current_value(int $userid, string $field): string {
        global $DB;

        if ($field === self::FIELD_COUNTRY) {
            return (string) $DB->get_field('user', 'country', ['id' => $userid]);
        }

        $element = signup::phone_element();
        if ($element === '') {
            return '';
        }

        $shortname = substr($element, strlen('profile_field_'));
        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        if (!$fieldid) {
            return '';
        }

        $value = (string) $DB->get_field('user_info_data', 'data',
            ['userid' => $userid, 'fieldid' => $fieldid]);
        $parts = explode(' ', trim($value), 2);

        return \core_text::strtoupper(trim($parts[0] ?? ''));
    }

    /**
     * Requests an administrator has yet to decide, oldest first.
     *
     * Oldest first because this is a queue somebody is waiting in, and a stack
     * would leave the earliest request permanently at the bottom.
     *
     * @param string $status one of the STATUS_* constants
     * @return stdClass[]
     */
    public static function listing(string $status = self::STATUS_PENDING): array {
        global $DB;

        return $DB->get_records(self::TABLE, ['status' => $status], 'timecreated ASC');
    }

    /**
     * How many requests are waiting, for the badge on the admin menu.
     *
     * @return int
     */
    public static function pending_count(): int {
        global $DB;

        try {
            return (int) $DB->count_records(self::TABLE, ['status' => self::STATUS_PENDING]);
        } catch (\Throwable $e) {
            // The table is missing because the code was deployed without the
            // upgrade; a missing badge is not worth a broken menu.
            return 0;
        }
    }
}
