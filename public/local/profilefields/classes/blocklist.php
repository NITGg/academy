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
 * The registration IP deny list.
 *
 * An entry is stored verbatim in the notation `address_in_subnet()` already
 * understands - a single address, a CIDR block, a partial address or a range -
 * so there is no matching logic of our own to get wrong, and an admin who has
 * used Moodle's own `allowedip`/`blockedip` settings types the same thing here.
 *
 * The list only gates *account creation*. It is deliberately not a site-wide ban:
 * blocking an address from logging in, or from reading the site at all, is
 * Moodle's `blockedip` setting, and putting a second implementation of that in a
 * local plugin would be a good way to lock an admin out of their own site.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blocklist {

    /** @var string Table holding the deny list. */
    const TABLE = 'local_profilefields_ip';

    /**
     * @var string[]|null The entries, read once per request.
     *
     * A class property rather than a `static` inside {@see self::blocks()}, so
     * that {@see self::add()} and {@see self::remove()} can throw it away. While
     * the cache lived inside the method, a request that added an address and then
     * asked whether it was covered got the answer from before the write - which
     * is precisely what the administration screen does when it adds an entry and
     * redraws the list underneath it.
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
     * Whether an address may not create an account.
     *
     * @param string $ip the address to test; defaults to the current visitor's
     * @return bool
     */
    public static function blocks(string $ip = ''): bool {
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
                // The table is missing because the code was deployed without running
                // the upgrade. That is an admin's problem to fix, but it must not be
                // the reason nobody can register in the meantime.
                debugging('local_profilefields: could not read the registration deny list: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
                self::$entries = [];
            }
        }
        if (!self::$entries) {
            return false;
        }

        // address_in_subnet() takes the whole comma-separated list in one call and
        // handles every notation the column accepts, IPv6 included.
        return address_in_subnet($ip, implode(',', self::$entries));
    }

    /**
     * Whether an entry is already on the list, verbatim.
     *
     * This is an exact-text check on the stored notation, not an address match -
     * "is 1.2.3.4 already typed here?", which is what the add form needs to ask.
     * Use {@see self::blocks()} to ask whether an address is covered.
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
        if ($ip === '' || $DB->record_exists(self::TABLE, ['ip' => $ip])) {
            return false;
        }

        $DB->insert_record(self::TABLE, (object) [
            'ip'           => $ip,
            'note'         => \core_text::substr(trim($note), 0, 255),
            'usermodified' => (int) $USER->id,
            'timecreated'  => time(),
        ]);

        self::$entries = null;

        return true;
    }

    /**
     * Remove one entry.
     *
     * @param int $id the entry's id
     * @return void
     */
    public static function remove(int $id): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['id' => $id]);

        self::$entries = null;
    }

    /**
     * Tidy a typed entry, and reject anything that is not a usable address.
     *
     * Accepts the four notations `address_in_subnet()` reads. Anything else comes
     * back as '' so the form can say so rather than storing a row that silently
     * never matches.
     *
     * @param string $ip the raw entry
     * @return string the entry to store, or '' when it is not usable
     */
    public static function normalise(string $ip): string {
        $ip = trim($ip);
        if ($ip === '' || \core_text::strlen($ip) > 100) {
            return '';
        }

        // A plain address, v4 or v6.
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // CIDR: 1.2.3.0/24 or 2001:db8::/32.
        if (preg_match('/^([0-9a-f:.]+)\/(\d{1,3})$/i', $ip, $m)
                && filter_var($m[1], FILTER_VALIDATE_IP)
                && (int) $m[2] <= 128) {
            return $ip;
        }

        // Partial address: 1.2.3. or 2001:db8:. Trailing separator is what marks it.
        if (preg_match('/^(\d{1,3}\.){1,3}$/', $ip) || preg_match('/^([0-9a-f]{1,4}:){1,7}$/i', $ip)) {
            return $ip;
        }

        // Range: 1.2.3.4-16 (the last group only), or an IPv6 equivalent.
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}-\d{1,3}$/', $ip)
                || preg_match('/^([0-9a-f]{1,4}:){1,7}[0-9a-f]{1,4}-[0-9a-f]{1,4}$/i', $ip)) {
            return $ip;
        }

        return '';
    }
}
