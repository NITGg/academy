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

namespace local_nit_emails;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Which emails a learner may switch off, and which they may not (AC-4.5.5).
 *
 * "Email notification preferences ... Marketing messages may be switched off;
 * transactional and security messages may not." And AC-4.5.5 again: "Transactional
 * and security emails cannot be switched off, and the interface states this beside
 * the locked rows."
 *
 * The distinction is not a matter of taste. A receipt, a confirmation link and a
 * "your password was changed" notice are all part of operating the account: a
 * learner who could turn them off would lose the only evidence they get that
 * somebody else is using their account. Marketing is the opposite - it exists for
 * us, not for them, so it is theirs to refuse.
 *
 * Everything this plugin currently sends is transactional. The marketing kinds are
 * declared anyway, because the preference has to exist and be respected *before*
 * the first marketing email is written, not after - otherwise the first campaign
 * goes out to people who would have opted out if they had been asked.
 *
 * Storage is a single user preference holding the kinds that are switched OFF.
 * Off-by-exception rather than a row per kind, so that adding a new marketing
 * kind later defaults to "send it" for existing accounts without a migration, and
 * an account that never visits this screen costs one short string.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences {

    /** @var string The user preference holding the opted-out kinds. */
    const PREF = 'local_nit_emails_optout';

    /** @var string Operating the account. Cannot be switched off. */
    const GROUP_TRANSACTIONAL = 'transactional';

    /** @var string Protecting the account. Cannot be switched off. */
    const GROUP_SECURITY = 'security';

    /** @var string Selling to the learner. Theirs to refuse. */
    const GROUP_MARKETING = 'marketing';

    /**
     * Every kind of email the academy sends, and which group it belongs to.
     *
     * The keys of the transactional entries match {@see templates}' event
     * constants, so a template that exists is a row the learner can see explained
     * even though they cannot turn it off. The rest are declared here only.
     *
     * @return array<string, string> kind => group
     */
    public static function kinds(): array {
        return [
            // Transactional: the record of something that happened to the account.
            templates::EVENT_REGISTRATION  => self::GROUP_TRANSACTIONAL,
            templates::EVENT_COURSE        => self::GROUP_TRANSACTIONAL,
            templates::EVENT_SUBSCRIPTION  => self::GROUP_TRANSACTIONAL,
            'invoice'                      => self::GROUP_TRANSACTIONAL,
            'expiry'                       => self::GROUP_TRANSACTIONAL,

            // Security: the only warning a learner gets that somebody else is in
            // their account. Never optional, in any jurisdiction worth naming.
            'accountsecurity'              => self::GROUP_SECURITY,

            // Marketing: ours to send, theirs to refuse.
            'offers'                       => self::GROUP_MARKETING,
            'newcourses'                   => self::GROUP_MARKETING,
            'newsletter'                   => self::GROUP_MARKETING,
        ];
    }

    /**
     * The kinds in one group.
     *
     * @param string $group one of the GROUP_* constants
     * @return string[]
     */
    public static function kinds_in(string $group): array {
        return array_keys(array_filter(self::kinds(),
            static fn(string $g): bool => $g === $group));
    }

    /**
     * May a learner switch this kind off?
     *
     * @param string $kind
     * @return bool
     */
    public static function is_optional(string $kind): bool {
        return (self::kinds()[$kind] ?? '') === self::GROUP_MARKETING;
    }

    /**
     * Would this account accept an email of this kind?
     *
     * The default is yes, for everything. A kind nobody has declared is treated as
     * transactional rather than marketing: an email this class has not been told
     * about is more likely to be a receipt somebody forgot to register than a
     * campaign, and failing towards "deliver" is the safe direction for the first
     * and the wrong one only for the second.
     *
     * @param int $userid
     * @param string $kind
     * @return bool
     */
    public static function accepts(int $userid, string $kind): bool {
        if (!self::is_optional($kind)) {
            return true;
        }

        return !in_array($kind, self::opted_out($userid), true);
    }

    /**
     * The kinds this account has switched off.
     *
     * Filtered against the declared list on read, so a kind that is retired - or
     * that stops being optional - leaves no lingering preference behind that would
     * silently suppress a mail nobody meant to suppress.
     *
     * @param int $userid
     * @return string[]
     */
    public static function opted_out(int $userid): array {
        $raw = (string) get_user_preferences(self::PREF, '', $userid);
        if ($raw === '') {
            return [];
        }

        $stored = array_map('trim', explode(',', $raw));

        return array_values(array_filter($stored, [self::class, 'is_optional']));
    }

    /**
     * Record which optional kinds this account wants.
     *
     * @param int $userid
     * @param string[] $wanted the optional kinds to keep receiving
     * @return void
     */
    public static function save(int $userid, array $wanted): void {
        $optional = self::kinds_in(self::GROUP_MARKETING);
        $off = array_values(array_diff($optional, $wanted));

        if (!$off) {
            unset_user_preference(self::PREF, $userid);
            return;
        }

        set_user_preference(self::PREF, implode(',', $off), $userid);
    }
}
