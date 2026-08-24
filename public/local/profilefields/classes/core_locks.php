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
 * Reads and writes the native controls for core user fields.
 *
 * "Can the user edit this field" and "must e-mail addresses be unique" are already
 * decisions Moodle stores - the first as a per-auth-plugin field lock
 * (`auth_<name>/field_lock_<field>`, which `user/edit_form.php` turns into a frozen
 * element), the second as `$CFG->allowaccountssameemail`. This class is the bridge:
 * the management page reads and writes those exact settings, so nothing is
 * re-implemented and the core edit forms keep behaving as they always have.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_locks {

    /** @var string[] The core fields whose lock this page exposes. */
    const LOCKABLE = ['firstname', 'lastname', 'email', 'city', 'country'];

    /**
     * The auth plugins a lock must be written to for it to actually bite.
     *
     * `manual` is always included - it backs admin-created accounts and is not in
     * `$CFG->auth` - alongside every enabled auth plugin.
     *
     * @return string[] auth plugin names
     */
    protected static function auth_plugins(): array {
        $auths = get_enabled_auth_plugins();
        $auths[] = 'manual';
        return array_values(array_unique($auths));
    }

    /**
     * Whether a core field is locked (i.e. the user cannot edit it).
     *
     * Read from `manual`, the auth plugin behind self-service and admin-made
     * accounts alike. A field is "locked" when its value is frozen outright.
     *
     * @param string $field core field name
     * @return bool
     */
    public static function is_locked(string $field): bool {
        return get_config('auth_manual', 'field_lock_' . $field) === 'locked';
    }

    /**
     * Lock or unlock a core field across every relevant auth plugin.
     *
     * @param string $field core field name
     * @param bool $locked true to stop the user editing it
     * @return void
     */
    public static function set_locked(string $field, bool $locked): void {
        if (!in_array($field, self::LOCKABLE, true)) {
            return;
        }
        $value = $locked ? 'locked' : 'unlocked';
        foreach (self::auth_plugins() as $auth) {
            set_config('field_lock_' . $field, $value, 'auth_' . $auth);
        }
    }

    /**
     * Whether e-mail addresses must be unique across accounts.
     *
     * @return bool
     */
    public static function email_unique(): bool {
        global $CFG;
        return empty($CFG->allowaccountssameemail);
    }

    /**
     * Set whether e-mail addresses must be unique.
     *
     * @param bool $unique true to forbid two accounts sharing an address
     * @return void
     */
    public static function set_email_unique(bool $unique): void {
        set_config('allowaccountssameemail', $unique ? 0 : 1);
    }
}
