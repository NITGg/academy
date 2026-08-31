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

use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Bridges the tool_policy documents into a single sign-up consent checkbox.
 *
 * tool_policy is deliberately built around a separate acceptance page shown before
 * the sign-up form (its own `signup_form()` only adds a hidden `policyagreed`). The
 * academy wants one checkbox on the form itself with the documents linked from it -
 * the pattern common to most sites. This class reads the documents tool_policy holds
 * and turns them into the links for that checkbox; the documents themselves stay
 * authored and versioned in tool_policy.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policies {

    /**
     * User preference: this account ticked the box while nobody was logged in.
     *
     * tool_policy refuses to file an acceptance for a visitor - `can_accept_policies()`
     * throws `noguest` before it looks at anything else - and sign-up happens
     * precisely then, so the audit row cannot be written at the moment of the tick.
     * The tick is not lost: `agree()` stores the flag the site actually gates on and
     * leaves this marker, and `settle_pending()` files the row on the first login,
     * which for a self-registration is the confirmation link.
     *
     * @var string
     */
    const PREF_PENDING = 'local_profilefields_consentpending';

    /**
     * User preference: this account ticked the sign-up terms box. Ever.
     *
     * `policyagreed` cannot be that record on its own, because it is not ours: it is
     * a derived column that tool_policy recomputes from its acceptance table every
     * time `\tool_policy\api::update_policyagreed()` runs, and that call overwrites
     * whatever we put there. A single policy version the sign-up checkbox does not
     * cover is enough to make it recompute to 0 - which is exactly how a learner who
     * ticked the box ended up being asked for it a second time the moment the
     * confirmation link logged them in.
     *
     * So the tick is written down here as well, once, and never recomputed. It is
     * what {@see has_agreed()} - and through it the completion gate - actually reads.
     *
     * @var string
     */
    const PREF_AGREED = 'local_profilefields_consented';

    /**
     * Whether the tool_policy plugin is installed.
     *
     * @return bool
     */
    public static function tool_available(): bool {
        return \core_component::get_component_directory('tool_policy') !== null;
    }

    /**
     * The policy documents a person signing up should see.
     *
     * Every current version, whatever audience it was authored for. That is wider
     * than it looks at first - a document marked "Authenticated users" is not shown
     * to guests by tool_policy's own pages - and it is deliberate, for two reasons.
     *
     * The person filling in the sign-up form is about to *become* an authenticated
     * user, so a policy aimed at logged-in users is one of the terms they are
     * agreeing to; and this site keeps `sitepolicyhandler` on "Default" (see
     * settings.php), which means tool_policy's acceptance page never runs and this
     * checkbox is the only place anyone is ever asked.
     *
     * Narrowing it to the guest audience is what broke the flow: the tick then
     * accepted a *subset* of what `\tool_policy\api::update_policyagreed()` judges
     * on - it counts every current version aimed at logged-in users or at everyone -
     * so the first acceptance we filed made tool_policy recompute `policyagreed`
     * back to 0 and the completion gate asked for the terms all over again.
     *
     * Returns an empty array when tool_policy is absent or defines no document.
     *
     * Each entry carries the identifiers as well as the link, because a client that
     * renders the document itself (a mobile app with no browser view) needs the
     * version id, not a URL to open.
     *
     * @return stdClass[] objects with policyid, versionid, name, url and the raw
     *                    tool_policy version record
     */
    public static function signup_document_records(): array {
        if (!self::tool_available()) {
            return [];
        }

        try {
            // No audience argument: list_current_versions() then filters nothing.
            $versions = \tool_policy\api::list_current_versions();
        } catch (\Throwable $e) {
            return [];
        }

        $docs = [];
        foreach ($versions as $version) {
            $url = new moodle_url('/admin/tool/policy/view.php', [
                'policyid' => $version->policyid,
                'versionid' => $version->id,
            ]);
            $docs[] = (object) [
                'policyid'  => (int) $version->policyid,
                'versionid' => (int) $version->id,
                'name'      => format_string($version->name),
                'url'       => $url->out(false),
                'version'   => $version,
            ];
        }

        return $docs;
    }

    /**
     * The label for the consent checkbox, with the policy names as links.
     *
     * Falls back to plain wording when no documents are configured yet, so the
     * checkbox still makes sense while an admin is still writing the policies.
     *
     * Built from the records rather than from a name => url map, because two
     * policies are allowed to share a name and a map would silently drop one -
     * leaving the learner ticking a box for a document the label never showed them,
     * which `record_acceptance()` would then accept on their behalf.
     *
     * @return string HTML
     */
    public static function consent_label(): string {
        $docs = self::signup_document_records();

        if (empty($docs)) {
            return get_string('consentlabelplain', 'local_profilefields');
        }

        $links = [];
        foreach ($docs as $doc) {
            $links[] = \html_writer::link($doc->url, s($doc->name), ['target' => '_blank', 'rel' => 'noopener']);
        }

        // "I agree to X and Y" - a localisable list separator and connector.
        $last = array_pop($links);
        if (empty($links)) {
            $list = $last;
        } else {
            $list = implode(get_string('listsep', 'langconfig') . ' ', $links)
                . ' ' . get_string('and', 'local_profilefields') . ' ' . $last;
        }

        return get_string('consentlabel', 'local_profilefields', $list);
    }

    /**
     * Write a formal acceptance for everything the sign-up checkbox covered.
     *
     * Without this the learner is asked twice. The inline checkbox of AC-4.1 is a
     * condition of submitting the form - it stops the account being created - but
     * it leaves no record anywhere, because it was only ever borrowing tool_policy's
     * documents to build its label. tool_policy therefore still believes nothing has
     * been accepted, and the first time the account reaches `require_login()` -
     * which is the moment the confirmation link is opened - it puts its own
     * acceptance page in front of the learner for the same documents they already
     * agreed to on the form.
     *
     * So the tick is recorded against every current guest-audience version, which
     * is exactly the set the checkbox's label listed. tool_policy then has nothing
     * outstanding to ask about, and the site has a real, versioned audit trail
     * instead of the tick vanishing into a form submission.
     *
     * Safe to call more than once: accept_policies() updates the existing row
     * rather than adding a second one, so a re-run after an interrupted sign-up
     * changes nothing.
     *
     * @param int $userid the account that ticked the box
     * @return int how many policy versions were recorded
     */
    public static function record_acceptance(int $userid): int {
        if (!self::tool_available() || $userid <= 0) {
            return 0;
        }

        $recorded = 0;

        foreach (self::signup_document_records() as $doc) {
            try {
                \tool_policy\api::accept_policies(
                    $doc->versionid,
                    $userid,
                    get_string('consentnote', 'local_profilefields')
                );
                $recorded++;
            } catch (\Throwable $e) {
                // One document failing must not stop the others, and must never
                // break the sign-up that is still in progress. The worst case is
                // the learner being asked once on the policy page - which is the
                // behaviour they had before this method existed.
                debugging('local_profilefields: could not record policy acceptance for version '
                    . $doc->versionid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $recorded;
    }

    /**
     * Record the sign-up tick on an account that is not logged in yet.
     *
     * This is the one that had to exist. `record_acceptance()` alone cannot carry
     * a sign-up tick, because tool_policy will not file an acceptance for anyone
     * who is not logged in, and at sign-up nobody is: the whole call was throwing
     * `noguest`, being swallowed by the catch, and leaving no trace at all. The
     * tick then had nowhere else to live either - the checkbox is our own element,
     * so core stores nothing for it, and `$CFG->sitepolicy` is empty on this site
     * (the documents live in tool_policy while the handler stays on "Default"), so
     * core's own `policyagreed` checkbox is never added to the form to store it.
     *
     * The result was that every learner who ticked the box on sign-up was asked
     * again the moment they opened the confirmation link, because
     * `completion::missing()` reads `policyagreed` and it was still 0.
     *
     * So the flag is written here directly - the same flag core's default site
     * policy handler writes, and the same one `complete.php` writes when it asks -
     * and the versioned tool_policy row is left pending for `settle_pending()` to
     * file on the first login, when there is finally a session to file it under.
     *
     * @param int $userid the account that ticked the box
     * @return void
     */
    public static function agree(int $userid): void {
        global $DB, $USER;

        if ($userid <= 0) {
            return;
        }

        // Ours, and permanent. Written before anything else, because everything
        // below can end up inside tool_policy, and tool_policy owns `policyagreed`.
        set_user_preference(self::PREF_AGREED, 1, $userid);

        $DB->set_field('user', 'policyagreed', 1, ['id' => $userid]);
        if ((int) ($USER->id ?? 0) === $userid) {
            $USER->policyagreed = 1;
        }

        // Logged in already (the completion page, an app session): the row can be
        // filed now and nothing needs to be remembered. Otherwise leave the marker.
        if (isloggedin() && !isguestuser() && (int) ($USER->id ?? 0) === $userid) {
            self::record_acceptance($userid);
            return;
        }

        set_user_preference(self::PREF_PENDING, 1, $userid);
    }

    /**
     * Has this account already agreed to the terms?
     *
     * Two records, either of which is a yes. `policyagreed` is the flag the rest of
     * Moodle uses, and it answers for an account that agreed through core's own site
     * policy handler rather than through our checkbox. The preference is the tick on
     * our checkbox, and it is the one that survives: `policyagreed` is recomputed by
     * tool_policy and can be flipped back to 0 by a policy version this site never
     * puts in front of anybody.
     *
     * Asking a learner to agree twice is the bug this guards against, so the test is
     * deliberately generous - a stale "yes" is a far smaller failure than a gate that
     * will not let go.
     *
     * @param stdClass $user
     * @return bool
     */
    public static function has_agreed(\stdClass $user): bool {
        if (empty($user->id)) {
            return false;
        }

        return !empty($user->policyagreed)
            || (bool) get_user_preferences(self::PREF_AGREED, 0, $user->id);
    }

    /**
     * File the tool_policy row a sign-up tick could not file at the time.
     *
     * Called from the login observer, which is the first moment the account has a
     * session - for a self-registration that is the confirmation link. Only an
     * account carrying the marker is touched, so this can never quietly accept a
     * policy version published after the tick: a new version clears `policyagreed`
     * site-wide and is asked for properly, and the marker is long since gone.
     *
     * @param int $userid
     * @return void
     */
    public static function settle_pending(int $userid): void {
        if ($userid <= 0 || !get_user_preferences(self::PREF_PENDING, 0, $userid)) {
            return;
        }

        self::record_acceptance($userid);
        unset_user_preference(self::PREF_PENDING, $userid);
    }
}
