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
 * Addresses the registration location check does not apply to (AC-4.6.6).
 *
 * "An administrator can whitelist an individual IP address or range so that the
 * check is skipped - for use with EAAC's own offices and with testing."
 *
 * The deny list next door and this one look alike and do opposite things, so the
 * distinction is worth stating plainly:
 *
 * - {@see blocklist} refuses an address outright, whatever country it resolves to;
 * - this list exempts an address from ever being compared to a country at all.
 *
 * They are consulted in that order. An address on both is refused: an explicit
 * "never let this address register" is a stronger statement than "do not bother
 * checking where this address is", and the safe reading of a contradiction is the
 * restrictive one.
 *
 * Why an exemption is needed at all: the office a course is built in is not
 * always in the country the account being tested belongs to, and a support agent
 * creating an account on a learner's behalf is legitimately in the wrong place.
 * Without this the only way to do either is to switch the whole check off.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allowlist {

    /** @var string Table holding the exemption list. */
    const TABLE = 'local_profilefields_allow';

    /**
     * @var string[]|null The entries, read once per request.
     *
     * A class property rather than a `static` inside {@see self::exempts()},
     * because {@see self::add()} and {@see self::remove()} have to be able to
     * throw it away. With the cache trapped inside the method, a request that
     * added an entry and then asked about it got the answer from before the
     * write - which is exactly what the administration screen does when it adds
     * an address and then redraws the list.
     */
    protected static $entries = null;

    /**
     * Every entry, newest first.
     *
     * @return \stdClass[] keyed by id
     */
    public static function all(): array {
        global $DB;

        return $DB->get_records(self::TABLE, null, 'timecreated DESC, id DESC');
    }

    /**
     * Whether an address is exempt from the location check.
     *
     * @param string $ip the address to test; defaults to the current visitor's
     * @return bool
     */
    public static function exempts(string $ip = ''): bool {
        global $DB;

        $ip = $ip !== '' ? $ip : (string) getremoteaddr();
        if ($ip === '') {
            return false;
        }

        // One query per request: the sign-up rule can be reached twice in a submit.
        if (self::$entries === null) {
            try {
                self::$entries = $DB->get_fieldset_select(self::TABLE, 'ip', '');
            } catch (\Throwable $e) {
                // Table missing because the code was deployed without the upgrade.
                // Failing closed here is the safe direction - an exemption that
                // silently does not apply refuses a registration, where an
                // exemption that wrongly applies lets one through unchecked.
                debugging('local_profilefields: could not read the location exemption list: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
                self::$entries = [];
            }
        }
        if (!self::$entries) {
            return false;
        }

        return address_in_subnet($ip, implode(',', self::$entries));
    }

    /**
     * Whether an entry is already on the list, verbatim.
     *
     * An exact-text check on the stored notation - "is 1.2.3.4 already typed
     * here?" - which is what the add form needs. Use {@see self::exempts()} to ask
     * whether an address is covered.
     *
     * @param string $ip the entry, as it would be stored
     * @return bool
     */
    public static function listed(string $ip): bool {
        global $DB;

        $ip = self::normalise($ip);

        return $ip !== '' && $DB->record_exists(self::TABLE, ['ip' => $ip]);
    }

    /**
     * Add an entry, or leave the list alone when it is already there.
     *
     * @param string $ip address, CIDR block, partial address or range
     * @param string $note free-text note
     * @return bool true when a row was added, false when it was already listed
     */
    public static function add(string $ip, string $note = ''): bool {
        global $DB, $USER;

        $ip = self::normalise($ip);
        if ($ip === '' || self::listed($ip)) {
            return false;
        }

        $DB->insert_record(self::TABLE, (object) [
            'ip' => $ip,
            'note' => \core_text::substr(trim($note), 0, 255),
            'usermodified' => (int) ($USER->id ?? 0),
            'timecreated' => time(),
        ]);

        self::$entries = null;

        return true;
    }

    /**
     * Remove one entry.
     *
     * @param int $id row id
     * @return void
     */
    public static function remove(int $id): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['id' => $id]);

        self::$entries = null;
    }

    /**
     * Tidy an entry into the form the column stores.
     *
     * Deliberately permissive about notation - `address_in_subnet()` understands
     * single addresses, CIDR blocks, partial addresses and ranges, and validating
     * more tightly here would reject entries the matcher would have handled.
     *
     * @param string $ip raw input
     * @return string the stored form, or '' when there is nothing usable
     */
    public static function normalise(string $ip): string {
        $ip = trim($ip);
        $ip = preg_replace('/\s+/', '', $ip);

        return \core_text::substr((string) $ip, 0, 100);
    }
}
