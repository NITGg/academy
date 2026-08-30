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

namespace local_nit_instructors;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * An instructor's Academic and Professional Background (AC-4.5.9 to AC-4.5.17).
 *
 * The shape of this class is dictated by one clause, AC-4.5.14:
 *
 *   "A change an instructor makes to any field in this group is held as pending
 *    and is not published until an administrator approves it ... The previously
 *    approved version stays visible to learners in the meantime."
 *
 * Two versions therefore have to exist at the same time, which is why a profile
 * is a *row* rather than a set of columns on the user record: an instructor has at
 * most one `approved` version and at most one `pending` one, and which of the two
 * you get depends entirely on who is asking. A learner always reads the approved
 * one. The instructor editing their own page always reads the pending one if there
 * is one, because that is what they last typed.
 *
 * Approving is then a swap rather than a merge: the pending version becomes the
 * approved one and the old approved row is dropped. Nothing is copied field by
 * field, so a field added to the group later cannot be forgotten in the copy.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile {

    /** @var string Table holding profile versions. */
    const TABLE = 'local_nit_instructors_profile';

    /** @var string Table holding the repeating entries. */
    const ENTRY_TABLE = 'local_nit_instructors_entry';

    /** @var string Published: this is what learners see. */
    const STATUS_APPROVED = 'approved';

    /** @var string Waiting for an administrator. */
    const STATUS_PENDING = 'pending';

    /** @var string Refused, with a reason the instructor is shown. */
    const STATUS_REJECTED = 'rejected';

    /** @var string Academic qualification: degree, awarding body, year. */
    const TYPE_QUALIFICATION = 'qualification';

    /** @var string Key position held: role, organisation, period. */
    const TYPE_POSITION = 'position';

    /** @var string Professional certification or award: title, body, year. */
    const TYPE_CERTIFICATION = 'certification';

    /** @var int Longest specialty line, per language (AC-4.5). */
    const SPECIALTY_MAX = 120;

    /** @var int Most years of teaching experience that can be claimed. */
    const YEARS_MAX = 60;

    /**
     * The three kinds of repeating entry, in the order the group displays them.
     *
     * @return string[]
     */
    public static function entry_types(): array {
        return [self::TYPE_QUALIFICATION, self::TYPE_POSITION, self::TYPE_CERTIFICATION];
    }

    /**
     * Does this account hold the instructor role anywhere?
     *
     * AC-4.5.9: "The Academic and Professional Background group is shown only on
     * accounts holding the instructor role. It is absent from a learner's profile
     * screen entirely, not merely disabled."
     *
     * "Instructor" is read from `$CFG->coursecontact` - the site's own answer to
     * which roles make somebody the face of a course. Hard-coding `editingteacher`
     * would mean that a site which renames or adds a teaching role silently stops
     * recognising its own instructors.
     *
     * Any course counts. A teacher on one course is an instructor of the academy,
     * and asking "which course?" here would make the profile page depend on where
     * you arrived from.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_instructor(int $userid): bool {
        global $DB, $CFG;

        if ($userid <= 0) {
            return false;
        }

        $roleids = array_filter(array_map('intval', explode(',', (string) ($CFG->coursecontact ?? ''))));
        if (!$roleids) {
            return false;
        }

        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['ctxcourse'] = CONTEXT_COURSE;
        $params['ctxcat'] = CONTEXT_COURSECAT;

        // Category assignments count: an instructor is often given their role over
        // a whole subject area rather than course by course.
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ra.roleid $insql
                   AND ctx.contextlevel IN (:ctxcourse, :ctxcat)";

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * The version a learner should see, or null when nothing is published yet.
     *
     * @param int $userid
     * @return stdClass|null
     */
    public static function approved(int $userid): ?stdClass {
        global $DB;

        $row = $DB->get_record(self::TABLE,
            ['userid' => $userid, 'status' => self::STATUS_APPROVED], '*', IGNORE_MULTIPLE);

        return $row ?: null;
    }

    /**
     * The version awaiting review, or null when there is none.
     *
     * @param int $userid
     * @return stdClass|null
     */
    public static function pending(int $userid): ?stdClass {
        global $DB;

        $row = $DB->get_record(self::TABLE,
            ['userid' => $userid, 'status' => self::STATUS_PENDING], '*', IGNORE_MULTIPLE);

        return $row ?: null;
    }

    /**
     * The most recently rejected version, so its reason can be shown (AC-4.5.15).
     *
     * @param int $userid
     * @return stdClass|null
     */
    public static function rejected(int $userid): ?stdClass {
        global $DB;

        $rows = $DB->get_records(self::TABLE,
            ['userid' => $userid, 'status' => self::STATUS_REJECTED], 'timedecided DESC', '*', 0, 1);

        return $rows ? reset($rows) : null;
    }

    /**
     * The version the instructor themselves should be editing.
     *
     * Their pending draft if they have one, otherwise the published version, so
     * that opening the form twice does not lose the change made the first time.
     *
     * @param int $userid
     * @return stdClass|null
     */
    public static function editable(int $userid): ?stdClass {
        return self::pending($userid) ?? self::approved($userid);
    }

    /**
     * The entries belonging to one version, grouped by type and in display order.
     *
     * @param int $profileid
     * @return array<string, stdClass[]> type => entries
     */
    public static function entries(int $profileid): array {
        global $DB;

        $out = array_fill_keys(self::entry_types(), []);

        if ($profileid <= 0) {
            return $out;
        }

        foreach ($DB->get_records(self::ENTRY_TABLE, ['profileid' => $profileid],
                'type ASC, sortorder ASC, id ASC') as $row) {
            if (isset($out[$row->type])) {
                $out[$row->type][] = $row;
            }
        }

        return $out;
    }

    /**
     * Save what an instructor typed, as a version awaiting approval.
     *
     * Always writes a pending version, never touches the approved one: that is the
     * whole of AC-4.5.14. An instructor who edits twice before anyone reviews
     * simply replaces their own draft.
     *
     * @param int $userid the instructor
     * @param stdClass $data the submitted values (specialty, years)
     * @param array<string, array> $entries type => list of entry value arrays
     * @return int the pending version's id
     */
    public static function save_draft(int $userid, stdClass $data, array $entries): int {
        global $DB;

        $now = time();
        $pending = self::pending($userid);

        $record = (object) [
            'userid' => $userid,
            'status' => self::STATUS_PENDING,
            'specialtyen' => \core_text::substr(trim((string) ($data->specialtyen ?? '')), 0, self::SPECIALTY_MAX),
            'specialtyar' => \core_text::substr(trim((string) ($data->specialtyar ?? '')), 0, self::SPECIALTY_MAX),
            'years' => max(0, min(self::YEARS_MAX, (int) ($data->years ?? 0))),
            'decidedby' => 0,
            'decisionnote' => null,
            'timedecided' => 0,
            'timemodified' => $now,
        ];

        if ($pending) {
            $record->id = $pending->id;
            $DB->update_record(self::TABLE, $record);
            $profileid = (int) $pending->id;
        } else {
            $record->timecreated = $now;
            $profileid = (int) $DB->insert_record(self::TABLE, $record);
        }

        self::replace_entries($profileid, $entries);

        return $profileid;
    }

    /**
     * Replace a version's entries wholesale.
     *
     * Delete-then-insert rather than a diff. The entries have no identity a learner
     * or an administrator ever refers to - they are a list, and the instructor can
     * reorder it freely - so matching old rows to new ones would be work in service
     * of nothing, and would get the ordering subtly wrong the first time somebody
     * moved an entry and edited it in the same submission.
     *
     * @param int $profileid
     * @param array<string, array> $entries type => list of value arrays
     * @return void
     */
    protected static function replace_entries(int $profileid, array $entries): void {
        global $DB;

        $DB->delete_records(self::ENTRY_TABLE, ['profileid' => $profileid]);

        foreach (self::entry_types() as $type) {
            $order = 0;
            foreach ($entries[$type] ?? [] as $entry) {
                $row = (object) [
                    'profileid' => $profileid,
                    'type' => $type,
                    'sortorder' => $order,
                    'titleen' => \core_text::substr(trim((string) ($entry['titleen'] ?? '')), 0, 255),
                    'titlear' => \core_text::substr(trim((string) ($entry['titlear'] ?? '')), 0, 255),
                    'orgen' => \core_text::substr(trim((string) ($entry['orgen'] ?? '')), 0, 255),
                    'orgar' => \core_text::substr(trim((string) ($entry['orgar'] ?? '')), 0, 255),
                    'perioden' => \core_text::substr(trim((string) ($entry['perioden'] ?? '')), 0, 100),
                    'periodar' => \core_text::substr(trim((string) ($entry['periodar'] ?? '')), 0, 100),
                ];

                // A row with nothing in it is a repeated-element placeholder the
                // instructor never filled in, not an entry they meant to add.
                if (self::is_blank($row)) {
                    continue;
                }

                $DB->insert_record(self::ENTRY_TABLE, $row);
                $order++;
            }
        }
    }

    /**
     * Is every value on this entry empty?
     *
     * @param stdClass $row
     * @return bool
     */
    protected static function is_blank(stdClass $row): bool {
        foreach (['titleen', 'titlear', 'orgen', 'orgar', 'perioden', 'periodar'] as $field) {
            if (trim((string) $row->$field) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Publish a pending version (AC-4.5.14).
     *
     * The old approved version and its entries are removed, and the pending one
     * takes its place, keeping the entries already attached to it. Done inside a
     * transaction because a failure halfway would leave an instructor with either
     * two published versions or none.
     *
     * @param int $profileid the pending version's id
     * @param string $note the administrator's note, kept on the record
     * @return bool
     */
    public static function approve(int $profileid, string $note = ''): bool {
        global $DB, $USER;

        $pending = $DB->get_record(self::TABLE, ['id' => $profileid, 'status' => self::STATUS_PENDING]);
        if (!$pending) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        $old = self::approved((int) $pending->userid);
        if ($old) {
            $DB->delete_records(self::ENTRY_TABLE, ['profileid' => $old->id]);
            $DB->delete_records(self::TABLE, ['id' => $old->id]);
        }

        $DB->update_record(self::TABLE, (object) [
            'id' => $pending->id,
            'status' => self::STATUS_APPROVED,
            'decidedby' => (int) $USER->id,
            'decisionnote' => trim($note),
            'timedecided' => time(),
        ]);

        $transaction->allow_commit();

        return true;
    }

    /**
     * Refuse a pending version, with the reason the instructor will be shown.
     *
     * AC-4.5.15: "Where an administrator rejects a change, the instructor is told,
     * and a reason entered by the administrator is displayed."
     *
     * The rejected version is kept rather than deleted, so the instructor can see
     * what was refused next to why - and so their work is not thrown away by
     * somebody else's decision.
     *
     * @param int $profileid the pending version's id
     * @param string $note the reason
     * @return bool
     */
    public static function reject(int $profileid, string $note): bool {
        global $DB, $USER;

        $pending = $DB->get_record(self::TABLE, ['id' => $profileid, 'status' => self::STATUS_PENDING]);
        if (!$pending) {
            return false;
        }

        // Only one rejected version is worth keeping - the last one, whose reason
        // is still being shown. Older ones are noise nobody reads.
        foreach ($DB->get_records(self::TABLE,
                ['userid' => $pending->userid, 'status' => self::STATUS_REJECTED]) as $stale) {
            $DB->delete_records(self::ENTRY_TABLE, ['profileid' => $stale->id]);
            $DB->delete_records(self::TABLE, ['id' => $stale->id]);
        }

        $DB->update_record(self::TABLE, (object) [
            'id' => $pending->id,
            'status' => self::STATUS_REJECTED,
            'decidedby' => (int) $USER->id,
            'decisionnote' => trim($note),
            'timedecided' => time(),
        ]);

        return true;
    }

    /**
     * Everything waiting for an administrator, oldest first.
     *
     * @return stdClass[]
     */
    public static function queue(): array {
        global $DB;

        return $DB->get_records(self::TABLE, ['status' => self::STATUS_PENDING], 'timemodified ASC');
    }

    /**
     * How many versions are waiting, for the badge on the admin menu.
     *
     * @return int
     */
    public static function queue_count(): int {
        global $DB;

        try {
            return (int) $DB->count_records(self::TABLE, ['status' => self::STATUS_PENDING]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Pick the readable half of a bilingual pair (AC-4.5.10).
     *
     * "Where an Arabic value is missing the English value is displayed in its
     * place, and the reverse, rather than an empty field."
     *
     * The point is that a half-translated profile still reads as a profile. An
     * instructor who filled the group in in Arabic only should appear in full to an
     * English-reading learner, in Arabic, rather than as a name above six blanks.
     *
     * @param string $en the English value
     * @param string $ar the Arabic value
     * @param string|null $lang the language to prefer; the current one by default
     * @return string
     */
    public static function pick(string $en, string $ar, ?string $lang = null): string {
        $lang = $lang ?? current_language();
        $wantsarabic = strpos($lang, 'ar') === 0;

        $first = $wantsarabic ? trim($ar) : trim($en);
        $second = $wantsarabic ? trim($en) : trim($ar);

        return $first !== '' ? $first : $second;
    }

    /**
     * The courses an instructor teaches (AC-4.5.13).
     *
     * "Courses Taught is derived from the courses an administrator has assigned to
     * the instructor. It is not editable by the instructor and cannot be added to
     * by hand." So there is no stored value at all - it is computed here, every
     * time, from the role assignments that decide it.
     *
     * Only courses the *viewer* may see are returned, so a hidden course being
     * prepared does not leak from a public profile.
     *
     * @param int $userid the instructor
     * @return array[] one row per course: id, fullname, url
     */
    public static function courses_taught(int $userid): array {
        global $DB, $CFG;

        $roleids = array_filter(array_map('intval', explode(',', (string) ($CFG->coursecontact ?? ''))));
        if (!$roleids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['ctxcourse'] = CONTEXT_COURSE;

        $sql = "SELECT DISTINCT c.id, c.fullname, c.visible, c.category
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxcourse
                  JOIN {course} c ON c.id = ctx.instanceid
                 WHERE ra.userid = :userid
                   AND ra.roleid $insql
              ORDER BY c.fullname ASC";

        $out = [];
        foreach ($DB->get_records_sql($sql, $params) as $course) {
            if (!$course->visible && !can_access_course($course)) {
                continue;
            }

            $context = \context_course::instance($course->id);
            $out[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }

        return $out;
    }

    /**
     * Remove everything held for an instructor.
     *
     * Called when the account is deleted, so a background nobody can reach does not
     * outlive the person it describes.
     *
     * @param int $userid
     * @return void
     */
    public static function purge(int $userid): void {
        global $DB;

        $ids = $DB->get_fieldset_select(self::TABLE, 'id', 'userid = ?', [$userid]);
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            $DB->delete_records_select(self::ENTRY_TABLE, "profileid $insql", $params);
        }

        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }
}
