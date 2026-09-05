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
 * Turns the rule matrix into rows core already enforces.
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
 *    {@see observer::message_user_unblocked()} puts back anything the matrix still denies.
 *  - A block a user made themselves must survive a rebuild, so every row this plugin writes
 *    is recorded in local_msgrules_managed and only those rows are ever removed.
 *
 * Rows are written with the data API rather than \core_message\api::block_user() on purpose:
 * a full rebuild on a mid-sized site is hundreds of thousands of pairs, and firing that many
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
     * Everything a decision needs, read once.
     *
     * @return array{cohorts: array, rules: array}
     */
    public static function build_context(): array {
        return [
            'cohorts' => rules::get_user_cohorts(),
            'rules'   => rules::get_rules(),
        ];
    }

    /**
     * Rebuild every block row on the site from the matrix.
     *
     * Walks recipients rather than pairs: each block row has exactly one owner, so reconciling
     * every recipient covers every pair while never holding more than one roster in memory.
     *
     * @param \progress_trace|null $trace Where to report progress, for cron and the CLI.
     * @return array{added: int, removed: int, users: int, skipped: int}
     */
    public static function rebuild(?\progress_trace $trace = null): array {
        $trace = $trace ?? new \null_progress_trace();

        if (!rules::is_enabled()) {
            $trace->output('local_msgrules is disabled - removing every rule-owned block row.');
            $removed = self::remove_all_managed();
            return ['added' => 0, 'removed' => $removed, 'users' => 0, 'skipped' => 0];
        }

        $ctx = self::build_context();
        $count = count($ctx['cohorts']);
        $max = rules::get_max_users();

        if ($count > $max) {
            // Refusing loudly beats spending an hour of cron writing millions of rows. The
            // matrix is unchanged, so raising the ceiling and re-running is all it takes.
            throw new \moodle_exception('errortoomanyusers', 'local_msgrules', '', (object) [
                'count' => $count,
                'max'   => $max,
            ]);
        }

        $totals = ['added' => 0, 'removed' => 0, 'users' => 0, 'skipped' => 0];
        foreach (array_keys($ctx['cohorts']) as $recipientid) {
            $result = self::reconcile_incoming((int) $recipientid, $ctx);
            $totals['added'] += $result['added'];
            $totals['removed'] += $result['removed'];
            $totals['skipped'] += $result['skipped'];
            $totals['users']++;
        }

        // Accounts that stopped being eligible - promoted to site administrator, deleted, or
        // turned into the guest - still own rows from when they were in scope.
        $totals['removed'] += self::remove_orphans(array_keys($ctx['cohorts']));

        $trace->output(sprintf(
            'local_msgrules: %d users, %d blocks added, %d removed, %d left to the user.',
            $totals['users'],
            $totals['added'],
            $totals['removed'],
            $totals['skipped']
        ));

        return $totals;
    }

    /**
     * Re-derive every pair one account takes part in, in both directions.
     *
     * Used when a single user changes - a new account, or cohort membership added or removed -
     * so a change that affects one person does not cost a whole-site rebuild.
     *
     * @param int $userid
     * @return array{added: int, removed: int, skipped: int}
     */
    public static function sync_user(int $userid): array {
        if (!rules::is_enabled()) {
            return ['added' => 0, 'removed' => 0, 'skipped' => 0];
        }

        $ctx = self::build_context();
        if (count($ctx['cohorts']) > rules::get_max_users()) {
            // Same ceiling as a rebuild: one user against the roster is still a roster scan.
            return ['added' => 0, 'removed' => 0, 'skipped' => 0];
        }

        $in = self::reconcile_incoming($userid, $ctx);
        $out = self::reconcile_outgoing($userid, $ctx);

        return [
            'added'   => $in['added'] + $out['added'],
            'removed' => $in['removed'] + $out['removed'],
            'skipped' => $in['skipped'] + $out['skipped'],
        ];
    }

    /**
     * Does the matrix deny this one direction?
     *
     * The single-pair question, for the unblock observer. Reads only the two accounts
     * involved, so it stays cheap enough to answer inside a request.
     *
     * @param int $recipientid Who would receive the message.
     * @param int $senderid Who would send it.
     * @return bool True when the pair must be blocked.
     */
    public static function should_block(int $recipientid, int $senderid): bool {
        if (!rules::is_enabled() || $recipientid == $senderid) {
            return false;
        }

        $sender = self::get_cohorts_for($senderid);
        $recipient = self::get_cohorts_for($recipientid);
        if ($sender === null || $recipient === null) {
            // One of them is exempt (a site administrator) or gone. Either way, not ours.
            return false;
        }

        return !rules::is_allowed($sender, $recipient, rules::get_rules());
    }

    /**
     * The cohorts one account belongs to, or null when the rules do not apply to it.
     *
     * @param int $userid
     * @return int[]|null
     */
    public static function get_cohorts_for(int $userid): ?array {
        global $DB, $CFG;

        if (!$userid || $userid == $CFG->siteguest || is_siteadmin($userid)) {
            return null;
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return null;
        }

        $cohorts = $DB->get_fieldset_select('cohort_members', 'cohortid', 'userid = ?', [$userid]);
        $cohorts = array_map('intval', $cohorts);

        return $cohorts ?: [rules::NOCOHORT];
    }

    /**
     * Reconcile the block rows owned by one recipient - everybody kept out of their inbox.
     *
     * @param int $recipientid
     * @param array $ctx From {@see self::build_context()}
     * @return array{added: int, removed: int, skipped: int}
     */
    private static function reconcile_incoming(int $recipientid, array $ctx): array {
        global $DB;

        $desired = [];
        if (isset($ctx['cohorts'][$recipientid])) {
            $recipientcohorts = $ctx['cohorts'][$recipientid];
            foreach ($ctx['cohorts'] as $senderid => $sendercohorts) {
                if ($senderid == $recipientid) {
                    // Self-conversations are a core feature; never stand in their way.
                    continue;
                }
                if (!rules::is_allowed($sendercohorts, $recipientcohorts, $ctx['rules'])) {
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
     * Reconcile the block rows that keep one sender out of other people's inboxes.
     *
     * @param int $senderid
     * @param array $ctx From {@see self::build_context()}
     * @return array{added: int, removed: int, skipped: int}
     */
    private static function reconcile_outgoing(int $senderid, array $ctx): array {
        global $DB;

        $desired = [];
        if (isset($ctx['cohorts'][$senderid])) {
            $sendercohorts = $ctx['cohorts'][$senderid];
            foreach ($ctx['cohorts'] as $recipientid => $recipientcohorts) {
                if ($senderid == $recipientid) {
                    continue;
                }
                if (!rules::is_allowed($sendercohorts, $recipientcohorts, $ctx['rules'])) {
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
     * Write the difference between what the matrix wants and what is on disk.
     *
     * @param int $anchorid The account both sides of every pair share.
     * @param array $desired [otheruserid => true] pairs the matrix denies.
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
                // and claiming it would mean deleting their decision when a rule later changes.
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

        // Anything we own that the matrix now permits.
        $stale = array_diff_key($managed, $desired);
        if ($stale) {
            foreach (array_chunk(array_keys($stale), self::CHUNK) as $chunk) {
                [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'o');
                $anchorfield = $anchorisrecipient ? 'userid' : 'blockeduserid';
                $otherfield = $anchorisrecipient ? 'blockeduserid' : 'userid';
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
     * @param int[] $eligible Account ids the rules still apply to.
     * @return int Rows removed.
     */
    private static function remove_orphans(array $eligible): int {
        global $DB;

        $eligible = array_flip(array_map('intval', $eligible));
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
