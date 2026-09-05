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
 * The rule matrix: which cohort may open a conversation with which other cohort.
 *
 * A rule row means "allowed"; a direction with no row is denied. That way the matrix reads
 * as a permission grid rather than a list of prohibitions, and a brand-new cohort starts
 * locked down instead of wide open.
 *
 * Direction matters. A row (from = Students, to = Instructors) lets a student write to an
 * instructor and says nothing about the reply; the reverse direction needs its own row.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules {

    /**
     * Pseudo-cohort for accounts that belong to no cohort at all.
     *
     * Without it the matrix would have nothing to say about a fresh sign-up, and "no rule
     * matched" would be indistinguishable from "this user is outside the scheme". Real cohort
     * ids are sequence values and never 0, so the id is free to borrow.
     */
    public const NOCOHORT = 0;

    /**
     * Is the plugin switched on?
     *
     * Off is the shipped default: installing the plugin must not silently cut every
     * conversation on the site before an administrator has drawn the matrix.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return !empty(get_config('local_msgrules', 'enabled'));
    }

    /**
     * The ceiling on how many accounts a rebuild will process.
     *
     * The rules are stored as one block row per denied ordered pair, so the row count grows
     * with the square of the roster. The guard turns "the site quietly got slow" into a
     * refusal with a number in it.
     *
     * @return int
     */
    public static function get_max_users(): int {
        return (int) (get_config('local_msgrules', 'maxusers') ?: 2000);
    }

    /**
     * The allowed directions, as a nested lookup.
     *
     * @return array [fromcohortid][tocohortid] => true
     */
    public static function get_rules(): array {
        global $DB;

        $out = [];
        foreach ($DB->get_records('local_msgrules_rule') as $rule) {
            $out[(int) $rule->fromcohortid][(int) $rule->tocohortid] = true;
        }
        return $out;
    }

    /**
     * Replace the whole matrix with the given set of allowed directions.
     *
     * Written as a whole rather than row by row because the management screen submits the
     * complete grid: a direction the administrator cleared is absent from the post, and the
     * only way to tell "cleared" from "not shown" is to treat the submission as the truth.
     *
     * @param array $allowed [fromcohortid][tocohortid] => truthy
     * @return void
     */
    public static function set_rules(array $allowed): void {
        global $DB;

        $now = time();
        $existing = [];
        foreach ($DB->get_records('local_msgrules_rule') as $rule) {
            $existing[(int) $rule->fromcohortid . ':' . (int) $rule->tocohortid] = $rule->id;
        }

        $insert = [];
        foreach ($allowed as $from => $tos) {
            foreach ($tos as $to => $on) {
                if (empty($on)) {
                    continue;
                }
                $key = (int) $from . ':' . (int) $to;
                if (isset($existing[$key])) {
                    // Already allowed - keep the row (and its timestamp) untouched.
                    unset($existing[$key]);
                    continue;
                }
                $insert[] = (object) [
                    'fromcohortid' => (int) $from,
                    'tocohortid'   => (int) $to,
                    'timemodified' => $now,
                ];
            }
        }

        // Whatever is left in $existing was allowed before and is not any more.
        if ($existing) {
            $DB->delete_records_list('local_msgrules_rule', 'id', array_values($existing));
        }
        if ($insert) {
            $DB->insert_records('local_msgrules_rule', $insert);
        }
    }

    /**
     * The cohorts the matrix is drawn over, with the "no cohort" pseudo-entry first.
     *
     * @return array [cohortid => name]
     */
    public static function get_cohort_menu(): array {
        global $DB;

        $menu = [self::NOCOHORT => get_string('nocohort', 'local_msgrules')];
        $cohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name, idnumber');
        foreach ($cohorts as $cohort) {
            $menu[(int) $cohort->id] = format_string($cohort->name);
        }
        return $menu;
    }

    /**
     * Every account the rules apply to, and the cohorts each one sits in.
     *
     * Site administrators are left out on purpose - they are the one exemption, so they are
     * never blocked and never blocked from. Deleted accounts and the guest account are out
     * because neither can hold a conversation.
     *
     * @return array [userid => int[] cohort ids, or [self::NOCOHORT] when the user is in none]
     */
    public static function get_user_cohorts(): array {
        global $DB, $CFG;

        $exclude = array_map('intval', explode(',', (string) $CFG->siteadmins));
        $exclude[] = (int) $CFG->siteguest;
        $exclude = array_values(array_unique(array_filter($exclude)));

        [$notin, $params] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);

        $users = $DB->get_fieldset_select('user', 'id', "deleted = 0 AND id $notin", $params);

        $map = [];
        foreach ($users as $userid) {
            $map[(int) $userid] = [];
        }

        // One pass over the membership table rather than a query per user.
        $sql = "SELECT cm.id, cm.userid, cm.cohortid
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid AND u.deleted = 0";
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $member) {
            $userid = (int) $member->userid;
            if (isset($map[$userid])) {
                $map[$userid][] = (int) $member->cohortid;
            }
        }
        $rs->close();

        foreach ($map as $userid => $cohorts) {
            if (!$cohorts) {
                $map[$userid] = [self::NOCOHORT];
            }
        }

        return $map;
    }

    /**
     * May a member of these cohorts write to a member of those cohorts?
     *
     * A user in several cohorts gets the union of what those cohorts allow: one permitting
     * rule anywhere in the pair is enough. The alternative - requiring every cohort to permit
     * it - would mean adding somebody to a second cohort could take away access they had,
     * which is not how administrators read a permission grid.
     *
     * @param int[] $sendercohorts
     * @param int[] $recipientcohorts
     * @param array $rules As returned by {@see self::get_rules()}
     * @return bool
     */
    public static function is_allowed(array $sendercohorts, array $recipientcohorts, array $rules): bool {
        foreach ($sendercohorts as $from) {
            if (empty($rules[$from])) {
                continue;
            }
            foreach ($recipientcohorts as $to) {
                if (!empty($rules[$from][$to])) {
                    return true;
                }
            }
        }
        return false;
    }
}
