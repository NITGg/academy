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

defined('MOODLE_INTERNAL') || die();

/**
 * The record of registration attempts the location guard refused.
 *
 * Nothing here is tied to a user account, because by definition no account was
 * created: a row is an address that tried, what country it claimed, what country
 * it resolved to, and why it was turned away. That is the whole report.
 *
 * Writing is deliberately best-effort. A logging failure must never be the thing
 * that stops a sign-up page from rendering its error, so `record()` swallows
 * database errors rather than letting them surface as a fatal on the sign-up form.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blocklog {

    /** @var string Table holding the refused attempts. */
    const TABLE = 'local_profilefields_log';

    /** @var string The declared country and the detected country disagreed. */
    const REASON_MISMATCH = 'mismatch';

    /** @var string No geo-IP source could resolve the address to a country. */
    const REASON_UNRESOLVED = 'unresolved';

    /** @var string The address is on the deny list. */
    const REASON_BLOCKED = 'blocked';

    /**
     * @var string Every geolocation source was unreachable.
     *
     * Distinct from REASON_UNRESOLVED, and the distinction is the whole point of
     * AC-4.6.10. "Unresolved" is about one address the services declined to place -
     * a private range, a brand-new allocation - and is a property of that visitor.
     * "Service down" is a property of *us*: nothing is being placed for anybody,
     * and every registration on the site is failing until someone notices. They
     * need different messages and only one of them deserves an alert.
     */
    const REASON_SERVICEDOWN = 'servicedown';

    /** @var string Refused on the web sign-up form. */
    const ORIGIN_SIGNUP = 'signup';

    /** @var string Refused on the "finish your registration" page. */
    const ORIGIN_COMPLETE = 'complete';

    /** @var string Refused on the web-service sign-up the mobile app uses. */
    const ORIGIN_APP = 'app';

    /**
     * The reason codes, in the order the filter offers them.
     *
     * @return string[]
     */
    public static function reasons(): array {
        return [self::REASON_MISMATCH, self::REASON_UNRESOLVED, self::REASON_BLOCKED,
            self::REASON_SERVICEDOWN];
    }

    /**
     * Write down one refused attempt.
     *
     * @param string $reason one of the REASON_* constants
     * @param string $declared alpha-2 country the visitor claimed, or ''
     * @param string $detected alpha-2 country the lookup resolved, or ''
     * @return void
     */
    public static function record(string $reason, string $declared = '', string $detected = ''): void {
        global $DB;

        // The same submit can reach the rule from more than one caller (the sign-up
        // callback and profilefield_phone both ask), and one attempt is one row.
        static $seen = [];
        $key = $reason . '|' . $declared . '|' . $detected;
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        $row = (object) [
            'timecreated' => time(),
            'ip'          => (string) getremoteaddr(),
            'declared'    => \core_text::strtoupper(trim($declared)),
            'detected'    => \core_text::strtoupper(trim($detected)),
            'reason'      => $reason,
            'origin'      => self::origin(),
        ];

        try {
            $DB->insert_record(self::TABLE, $row);
        } catch (\Throwable $e) {
            // Logging is a side effect of the refusal, never a precondition for it.
            debugging('local_profilefields: could not log a refused sign-up attempt: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Which registration entry point this request came through.
     *
     * @return string one of the ORIGIN_* constants
     */
    protected static function origin(): string {
        if (defined('WS_SERVER') && WS_SERVER) {
            return self::ORIGIN_APP;
        }
        // complete.php is the only registration path a signed-in user can be on:
        // the sign-up form is unreachable once you have an account.
        if (isloggedin() && !isguestuser()) {
            return self::ORIGIN_COMPLETE;
        }
        return self::ORIGIN_SIGNUP;
    }

    /**
     * Empty the log.
     *
     * @return int how many rows were removed
     */
    public static function clear(): int {
        global $DB;

        $count = $DB->count_records(self::TABLE);
        $DB->delete_records(self::TABLE);

        return $count;
    }

    /**
     * How many attempts the log holds.
     *
     * @return int
     */
    public static function count_all(): int {
        global $DB;

        return $DB->count_records(self::TABLE);
    }

    /**
     * The addresses with the most refused attempts, worst first.
     *
     * Feeds the "these are the ones worth blocking" hint above the report, so an
     * admin does not have to eyeball a paged table to spot a repeat offender.
     *
     * @param int $limit how many to return
     * @param int $min ignore addresses with fewer attempts than this
     * @return array ip => attempt count
     */
    public static function top_offenders(int $limit = 5, int $min = 3): array {
        global $DB;

        $rows = $DB->get_records_sql("
                  SELECT ip, COUNT(*) AS attempts
                    FROM {" . self::TABLE . "}
                   WHERE ip <> ''
                GROUP BY ip
                  HAVING COUNT(*) >= ?
                ORDER BY attempts DESC, ip ASC", [$min], 0, $limit);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->ip] = (int) $row->attempts;
        }

        return $out;
    }
}
