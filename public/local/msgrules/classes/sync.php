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

/**
 * Turns each course's mode into rows core already enforces.
 *
 * There is no hook, callback or overridable method anywhere in the messaging subsystem:
 * \core_message\api::can_contact_user() is a closed protected static, and message/ ships
 * neither classes/hook/ nor db/hooks.php. So rather than intercept the decision, this class
 * feeds the one input to it that is writable - the recipient's blocked-users list. The very
 * first thing can_contact_user() does is consult message_users_blocked, which means a row
 * written here is honoured identically by the message drawer, /message/index.php, every
 * core_message web service and therefore the mobile app, with no core file touched.
 *
 * Two consequences follow from that choice and are handled here:
 *
 *  - The rows live in a table users can edit (their own "Blocked users" list), so
 *    {@see observer::message_user_unblocked()} puts back anything a course still denies.
 *  - A block a user made themselves must survive a rebuild, so every row this plugin writes
 *    is recorded in local_msgrules_managed and only those rows are ever removed.
 *
 * Rows are written with the data API rather than \core_message\api::block_user() on purpose:
 * a rebuild is one row per person a restricted student may not write to, and firing that many
 * message_user_blocked events would flood the log store to describe policy rather than
 * anything a person did.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync {

    /** @var int How many rows to insert or delete per database round trip. */
    private const CHUNK = 500;

    /**
     * Up to this many accounts, a rebuild runs in the request that asked for it.
     *
     * Queueing everything was correct but unusable: an administrator set a course's mode, saw
     * nothing change, and had no way to tell a wrong setting from one cron had not picked up
     * yet. Below this figure the whole rebuild is well under a second, so it happens before
     * the page reloads and the screen tells the truth immediately.
     */
    private const INLINE_LIMIT = 300;

    /**
     * How many accounts the rules apply to.
     *
     * @return int
     */
    public static function count_eligible(): int {
        global $DB, $CFG;

        // Matches roster::get_eligible_users(): administrators are counted, because they can
        // be a recipient the rules keep a student away from.
        return $DB->count_records_select(
            'user',
            'deleted = 0 AND id <> :guest',
            ['guest' => (int) $CFG->siteguest]
        );
    }

    /**
     * Is this site small enough to rebuild inside a web request?
     *
     * @return bool
     */
    public static function can_rebuild_inline(): bool {
        return self::count_eligible() <= self::INLINE_LIMIT;
    }

    /**
     * Put the current settings into effect now if that is affordable, and say what happened.
     *
     * Shared by the management screen and the on/off switch so both behave the same way. An
     * administrator who changes a course and one who enables the feature are asking the same
     * question - "is it live?" - and both deserve the same answer in the same place.
     *
     * @return string A message describing the outcome, ready to show.
     */
    public static function apply_now(): string {
        if (!self::can_rebuild_inline()) {
            task\rebuild::queue();
            return get_string('rebuildqueued', 'local_msgrules');
        }

        \core_php_time_limit::raise(300);
        $result = self::rebuild();

        return get_string('rebuildapplied', 'local_msgrules', (object) [
            'students' => $result['students'],
            'added'    => $result['added'],
            'removed'  => $result['removed'],
        ]);
    }

    /**
     * May this student write to this person, according to the courses they share?
     *
     * @param array $ctx From {@see roster::build()}
     * @param int $senderid
     * @param int $recipientid
     * @return bool
     */
    private static function is_allowed(array $ctx, int $senderid, int $recipientid): bool {
        if (!isset($ctx['restricted'][$senderid])) {
            // Not a student on any restricted course - the plugin has no opinion about them.
            return true;
        }

        return isset($ctx['allowed'][$senderid][$recipientid]);
    }

    /**
     * Rebuild every block row on the site from the course settings.
     *
     * Walks senders rather than pairs, and only the senders that can own a row: the students
     * some course restricts, plus anyone the plugin has rows for from a previous run whose
     * restriction may since have been lifted.
     *
     * @param \progress_trace|null $trace Where to report progress, for cron and the CLI.
     * @return array{added: int, removed: int, students: int, skipped: int}
     */
    public static function rebuild(?\progress_trace $trace = null): array {
        global $DB;

        $trace = $trace ?? new \null_progress_trace();

        if (!rules::is_enabled()) {
            $trace->output('local_msgrules is disabled - removing every rule-owned block row.');
            $removed = self::remove_all_managed();
            return ['added' => 0, 'removed' => $removed, 'students' => 0, 'skipped' => 0];
        }

        $count = self::count_eligible();
        $max = rules::get_max_users();
        if ($count > $max) {
            // Refusing loudly beats spending an hour of cron writing millions of rows. The
            // settings are unchanged, so raising the ceiling and re-running is all it takes.
            throw new \moodle_exception('errortoomanyusers', 'local_msgrules', '', (object) [
                'count' => $count,
                'max'   => $max,
            ]);
        }

        $ctx = roster::build();

        // Students a course restricts now, plus anyone we already hold rows for - the second
        // set is how a lifted restriction gets cleaned up rather than lingering forever.
        $senders = $ctx['restricted'];
        foreach ($DB->get_fieldset_sql('SELECT DISTINCT blockeduserid FROM {local_msgrules_managed}') as $id) {
            $senders[(int) $id] = true;
        }

        $totals = ['added' => 0, 'removed' => 0, 'students' => count($ctx['restricted']), 'skipped' => 0];
        foreach (array_keys($senders) as $senderid) {
            $result = self::reconcile_outgoing((int) $senderid, $ctx);
            $totals['added'] += $result['added'];
            $totals['removed'] += $result['removed'];
            $totals['skipped'] += $result['skipped'];
        }

        $totals['removed'] += self::remove_orphans($ctx['eligible']);

        $trace->output(sprintf(
            'local_msgrules: %d restricted students, %d blocks added, %d removed, %d left to the user.',
            $totals['students'],
            $totals['added'],
            $totals['removed'],
            $totals['skipped']
        ));

        return $totals;
    }

    /**
     * Re-derive every pair one account takes part in, in both directions.
     *
     * Used when one person changes - a new account, an enrolment, a role - so a change that
     * affects one student does not cost a whole-site rebuild.
     *
     * @param int $userid
     * @return array{added: int, removed: int, skipped: int}
     */
    public static function sync_user(int $userid): array {
        if (!rules::is_enabled() || self::count_eligible() > rules::get_max_users()) {
            return ['added' => 0, 'removed' => 0, 'skipped' => 0];
        }

        $ctx = roster::build();

        // Their own outgoing rows, and the rows other restricted students hold against them -
        // joining a course changes both who they may write to and who may write to them.
        $out = self::reconcile_outgoing($userid, $ctx);
        $in = self::reconcile_incoming($userid, $ctx);

        return [
            'added'   => $in['added'] + $out['added'],
            'removed' => $in['removed'] + $out['removed'],
            'skipped' => $in['skipped'] + $out['skipped'],
        ];
    }

    /**
     * Do the course settings deny this one direction?
     *
     * The single-pair question, for the unblock observer.
     *
     * @param int $recipientid Who would receive the message.
     * @param int $senderid Who would send it.
     * @return bool True when the pair must be blocked.
     */
    public static function should_block(int $recipientid, int $senderid): bool {
        if (!rules::is_enabled() || $recipientid == $senderid) {
            return false;
        }
        if (is_siteadmin($senderid)) {
            // An administrator is never restricted. The reverse is not true: whether a student
            // may write *to* an administrator is exactly what the "admins" tick decides.
            return false;
        }

        $ctx = roster::build();
        if (!isset($ctx['eligible'][$senderid]) || !isset($ctx['eligible'][$recipientid])) {
            return false;
        }

        return !self::is_allowed($ctx, $senderid, $recipientid);
    }

    /**
     * Reconcile the rows that keep one student out of other people's inboxes.
     *
     * @param int $senderid
     * @param array $ctx From {@see roster::build()}
     * @return array{added: int, removed: int, skipped: int}
     */
    private static function reconcile_outgoing(int $senderid, array $ctx): array {
        global $DB;

        $desired = [];
        if (isset($ctx['eligible'][$senderid]) && isset($ctx['restricted'][$senderid])) {
            foreach (array_keys($ctx['eligible']) as $recipientid) {
                if ($recipientid == $senderid) {
                    continue;
                }
                if (!self::is_allowed($ctx, $senderid, (int) $recipientid)) {
                    $desired[(int) $recipientid] = true;
                }
            }
        }

        $managed = $DB->get_records_menu(
            'local_msgrules_managed',
            ['blockeduserid' => $senderid],
            '',
            'userid, id'
        );
        $existing = array_flip($DB->get_fieldset_select(
            'message_users_blocked',
            'userid',
            'blockeduserid = ?',
            [$senderid]
        ));

        return self::apply($senderid, $desired, $managed, $existing, false);
    }

    /**
     * Reconcile the rows in one person's inbox - everybody a course keeps out of it.
     *
     * @param int $recipientid
     * @param array $ctx From {@see roster::build()}
     * @return array{added: int, removed: int, skipped: int}
     */
    private static function reconcile_incoming(int $recipientid, array $ctx): array {
        global $DB;

        $desired = [];
        if (isset($ctx['eligible'][$recipientid])) {
            foreach (array_keys($ctx['restricted']) as $senderid) {
                if ($senderid == $recipientid) {
                    continue;
                }
                if (!self::is_allowed($ctx, (int) $senderid, $recipientid)) {
                    $desired[(int) $senderid] = true;
                }
            }
        }

        $managed = $DB->get_records_menu(
            'local_msgrules_managed',
            ['userid' => $recipientid],
            '',
            'blockeduserid, id'
        );
        $existing = array_flip($DB->get_fieldset_select(
            'message_users_blocked',
            'blockeduserid',
            'userid = ?',
            [$recipientid]
        ));

        return self::apply($recipientid, $desired, $managed, $existing, true);
    }

    /**
     * Write the difference between what the settings want and what is on disk.
     *
     * @param int $anchorid The account both sides of every pair share.
     * @param array $desired [otheruserid => true] pairs that must be blocked.
     * @param array $managed [otheruserid => managed row id] pairs this plugin already owns.
     * @param array $existing [otheruserid => anything] pairs already in message_users_blocked.
     * @param bool $anchorisrecipient True when $anchorid owns the block rows.
     * @return array{added: int, removed: int, skipped: int}
     */
    private static function apply(
        int $anchorid,
        array $desired,
        array $managed,
        array $existing,
        bool $anchorisrecipient
    ): array {
        global $DB;

        $now = time();
        $added = $skipped = $removed = 0;
        $blockrows = [];
        $managedrows = [];

        foreach (array_keys($desired) as $otherid) {
            if (isset($managed[$otherid])) {
                continue;                       // Already ours and already correct.
            }
            if (isset($existing[$otherid])) {
                // The user blocked this person themselves. Their row already denies the pair,
                // and claiming it would mean deleting their decision when a course changes.
                $skipped++;
                continue;
            }
            $pair = $anchorisrecipient ? [$anchorid, $otherid] : [$otherid, $anchorid];
            $blockrows[] = (object) [
                'userid'        => $pair[0],
                'blockeduserid' => $pair[1],
                'timecreated'   => $now,
            ];
            $managedrows[] = (object) [
                'userid'        => $pair[0],
                'blockeduserid' => $pair[1],
                'timecreated'   => $now,
            ];
            $added++;
        }

        foreach ($blockrows ? array_chunk($blockrows, self::CHUNK) : [] as $chunk) {
            $DB->insert_records('message_users_blocked', $chunk);
        }
        foreach ($managedrows ? array_chunk($managedrows, self::CHUNK) : [] as $chunk) {
            $DB->insert_records('local_msgrules_managed', $chunk);
        }

        // Anything we own that the settings now permit.
        $stale = array_diff_key($managed, $desired);
        if ($stale) {
            $anchorfield = $anchorisrecipient ? 'userid' : 'blockeduserid';
            $otherfield = $anchorisrecipient ? 'blockeduserid' : 'userid';
            foreach (array_chunk(array_keys($stale), self::CHUNK) as $chunk) {
                [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'o');
                $params['anchor'] = $anchorid;
                $where = "$anchorfield = :anchor AND $otherfield $insql";
                $DB->delete_records_select('message_users_blocked', $where, $params);
                $DB->delete_records_select('local_msgrules_managed', $where, $params);
            }
            $removed = count($stale);
        }

        return ['added' => $added, 'removed' => $removed, 'skipped' => $skipped];
    }

    /**
     * Drop every block row the plugin owns, leaving the ones users made themselves.
     *
     * Runs when the plugin is switched off and on uninstall, so turning the feature off
     * actually restores the site rather than leaving it silently locked down.
     *
     * @return int Rows removed.
     */
    public static function remove_all_managed(): int {
        global $DB;

        $total = 0;
        $rs = $DB->get_recordset('local_msgrules_managed', null, 'id ASC', 'id, userid, blockeduserid');
        $batch = [];
        foreach ($rs as $row) {
            $batch[] = $row;
            if (count($batch) >= self::CHUNK) {
                $total += self::delete_managed_batch($batch);
                $batch = [];
            }
        }
        $rs->close();
        if ($batch) {
            $total += self::delete_managed_batch($batch);
        }

        return $total;
    }

    /**
     * Delete rows for accounts that are no longer in scope.
     *
     * @param array<int, bool> $eligible Accounts the rules still apply to.
     * @return int Rows removed.
     */
    private static function remove_orphans(array $eligible): int {
        global $DB;

        $orphans = [];
        $rs = $DB->get_recordset('local_msgrules_managed', null, 'id ASC', 'id, userid, blockeduserid');
        foreach ($rs as $row) {
            if (!isset($eligible[(int) $row->userid]) || !isset($eligible[(int) $row->blockeduserid])) {
                $orphans[] = $row;
            }
        }
        $rs->close();

        $total = 0;
        foreach ($orphans ? array_chunk($orphans, self::CHUNK) : [] as $chunk) {
            $total += self::delete_managed_batch($chunk);
        }

        return $total;
    }

    /**
     * Remove one batch of managed rows from both tables.
     *
     * @param \stdClass[] $rows Each with id, userid and blockeduserid.
     * @return int
     */
    private static function delete_managed_batch(array $rows): int {
        global $DB;

        foreach ($rows as $row) {
            $DB->delete_records('message_users_blocked', [
                'userid'        => $row->userid,
                'blockeduserid' => $row->blockeduserid,
            ]);
        }
        $DB->delete_records_list('local_msgrules_managed', 'id', array_column($rows, 'id'));

        return count($rows);
    }

    /**
     * How many block rows the plugin currently owns.
     *
     * @return int
     */
    public static function count_managed(): int {
        global $DB;

        return $DB->count_records('local_msgrules_managed');
    }
}
