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

use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * The account screen's shell: the left-hand navigation box and the frame around a pane.
 *
 * WF-5.1 to WF-5.6 are one screen with a list down the left and one pane to the
 * right of it, so the list is defined once, here, rather than being repeated by
 * every page that needs to appear inside it. A page joins the screen by calling
 * {@see self::open()} before its own output and {@see self::close()} after -
 * which is how `/local/payments/history.php` shows the same navigation without
 * this plugin having to own the payment history.
 *
 * Three of the entries lead somewhere that already exists (`/my/courses.php`, the
 * certificate list, the payment history). Those are links, not panes: rebuilding
 * three working screens to change the box they sit in would be a lot of new code
 * that displays the same rows.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account {

    /** @var string WF-5.1 - personal details, country and telephone. */
    const SECTION_PROFILE = 'profile';

    /** @var string WF-5.2 - the password. */
    const SECTION_SECURITY = 'security';

    /** @var string WF-5.3 - delete my account. */
    const SECTION_DELETE = 'delete';

    /**
     * @var string[] The panes account.php itself serves.
     *
     * Delete is not among them. It already has a page of its own, and that page
     * owns the deletion, the goodbye mail and the sign-out; giving account.php a
     * second copy of that sequence would be two places to get an irreversible act
     * wrong. It joins the screen by wrapping itself in {@see self::open()}
     * instead.
     */
    const OWN_SECTIONS = [self::SECTION_PROFILE, self::SECTION_SECURITY];

    /**
     * The core user fields this screen can draw, in the order it draws them.
     *
     * The same list the management page offers a lock for
     * ({@see core_locks::LOCKABLE}), in the order somebody reads their own
     * details - so "what an administrator can lock" and "what this screen shows"
     * cannot drift apart.
     *
     * @var string[]
     */
    const CORE_FIELDS = ['firstname', 'lastname', 'email', 'city', 'country'];

    /**
     * The URL of one of this plugin's own panes.
     *
     * @param string $section one of the SECTION_* constants
     * @return moodle_url
     */
    public static function url(string $section = self::SECTION_PROFILE): moodle_url {
        $params = ($section === self::SECTION_PROFILE) ? [] : ['section' => $section];

        return new moodle_url('/local/profilefields/account.php', $params);
    }

    /**
     * The navigation entries, in the order the wireframe lists them.
     *
     * @param string $active the SECTION_* constant of the entry to mark current
     * @return array<int, array{key: string, label: string, url: string, active: bool, danger: bool}>
     */
    public static function nav(string $active): array {
        global $CFG;

        $items = [];

        $items[] = [
            'key' => self::SECTION_PROFILE,
            'label' => get_string('navprofile', 'local_profilefields'),
            'url' => self::url(self::SECTION_PROFILE)->out(false),
        ];

        $items[] = [
            'key' => self::SECTION_SECURITY,
            'label' => get_string('navsecurity', 'local_profilefields'),
            'url' => self::url(self::SECTION_SECURITY)->out(false),
        ];

        $items[] = [
            'key' => 'mylearning',
            'label' => get_string('navmylearning', 'local_profilefields'),
            'url' => (new moodle_url('/my/courses.php'))->out(false),
        ];

        // The certificate list belongs to mod_customcert, which an academy may not
        // have installed. No plugin, no entry - rather than an entry that 404s.
        if (file_exists($CFG->dirroot . '/mod/customcert/my_certificates.php')) {
            $items[] = [
                'key' => 'certificates',
                'label' => get_string('navcertificates', 'local_profilefields'),
                'url' => (new moodle_url('/mod/customcert/my_certificates.php'))->out(false),
            ];
        }

        if (file_exists($CFG->dirroot . '/local/payments/history.php')) {
            $items[] = [
                'key' => 'invoices',
                'label' => get_string('navinvoices', 'local_profilefields'),
                'url' => (new moodle_url('/local/payments/history.php'))->out(false),
            ];
        }

        foreach ($items as $i => $item) {
            $items[$i]['active'] = ($item['key'] === $active);
            $items[$i]['danger'] = false;
        }

        // Kept out of the loop above and appended last: the wireframe separates it
        // from the rest with a rule, because it is the one entry that destroys
        // something.
        $items[] = [
            'key' => self::SECTION_DELETE,
            'label' => get_string('navdelete', 'local_profilefields'),
            'url' => (new moodle_url('/local/profilefields/deleteaccount.php'))->out(false),
            'active' => ($active === self::SECTION_DELETE),
            'danger' => true,
        ];

        return $items;
    }

    /**
     * The navigation box on its own.
     *
     * @param string $active the SECTION_* constant of the entry to mark current
     * @return string HTML
     */
    public static function nav_html(string $active): string {
        $links = '';

        foreach (self::nav($active) as $item) {
            // `nit-link-plain` opts the entry out of the site-wide link treatment in
            // theme_nit (scss/post.scss), which underlines and recolours every
            // content link on hover. These entries are navigation, not prose: they
            // draw their own hover fill and their own current-page state, and the
            // underline landed on top of both. That rule carries eleven :not()s, so
            // it cannot be out-specified from a component stylesheet - opting out in
            // the markup is the way it is meant to be answered.
            $classes = 'nit-account__navlink nit-link-plain';
            if ($item['active']) {
                $classes .= ' nit-account__navlink--active';
            }
            if ($item['danger']) {
                $classes .= ' nit-account__navlink--danger';
            }

            $links .= html_writer::link($item['url'], $item['label'], [
                'class' => $classes,
                // Read out as the current page by a screen reader, not merely
                // coloured differently.
                'aria-current' => $item['active'] ? 'page' : null,
            ]);
        }

        return html_writer::tag('nav', $links, [
            'class' => 'nit-account__nav',
            'aria-label' => get_string('accounttitle', 'local_profilefields'),
        ]);
    }

    /**
     * Open the screen: the navigation box, then the frame the pane is drawn into.
     *
     * Echoes rather than returns because the panes it wraps are `moodleform`s,
     * and `moodleform::display()` prints straight to the output buffer.
     *
     * @param string $active the SECTION_* constant of the entry to mark current
     * @return void
     */
    public static function open(string $active): void {
        echo html_writer::start_div('nit-account');
        echo self::nav_html($active);
        echo html_writer::start_div('nit-account__body');
    }

    /**
     * Put a reveal toggle on the password box of a pane that has one.
     *
     * Two panes ask for a password - changing the address, and deleting the
     * account - and neither had a reveal control of its own. What the reader got
     * instead was whatever the browser draws by itself: Edge paints a black eye
     * inside the box (`::-ms-reveal`) that appears only once there is something
     * to reveal and takes no notice of the site being dark, so it reads as a
     * black smudge that comes and goes; Chrome and Firefox draw nothing at all,
     * so the same page has a reveal control on one browser and not on the next.
     *
     * Core's own control instead - the same one the sign-in screens use, which
     * theme_nit styles into the field (scss/components/_account.scss). That
     * stylesheet also hides the browser's native button inside this screen, so
     * there is never a second, unstyled eye beside ours.
     *
     * `core/togglesensitive` keeps the element id in module state, so it drives
     * one field per page - which is what these panes have.
     *
     * @param string $elementid the input's DOM id
     * @return void
     */
    public static function password_toggle(string $elementid = 'id_password'): void {
        global $PAGE;

        $PAGE->requires->js_call_amd('core/togglesensitive', 'init', [$elementid]);
    }

    /**
     * Close the frame opened by {@see self::open()}.
     *
     * @return void
     */
    public static function close(): void {
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    /**
     * Can this account prove who it is with a password it holds here?
     *
     * An account created through Google signs in with `oauth2` and has no local
     * password at all. Every screen on this page that asks for one - changing the
     * address, changing the password - is therefore a dead end for that account:
     * `validate_internal_user_password()` cannot succeed against a password that
     * does not exist, so the form would refuse every attempt without ever saying
     * why. Those screens ask this first and say what is actually going on instead.
     *
     * @param \stdClass $user
     * @return bool
     */
    public static function can_verify_password(\stdClass $user): bool {
        global $DB;

        if (empty($user->id) || empty($user->auth)) {
            return false;
        }

        try {
            $auth = get_auth_plugin($user->auth);
        } catch (\Throwable $e) {
            return false;
        }

        // is_internal() is the auth API's own answer to "is the password mine to
        // check" - true for manual and email, false for oauth2, LDAP and the rest.
        if (!$auth->is_internal()) {
            return false;
        }

        // Read the hash rather than trust the object. `$USER` never carries one:
        // \core\session\manager::set_user() unsets it on the way into the session,
        // so testing `$user->password` here would answer "no local password" for
        // every account on the site, including the manual ones this is meant to
        // let through.
        $hash = (string) $DB->get_field('user', 'password', ['id' => $user->id]);

        return $hash !== '' && $hash !== AUTH_PASSWORD_NOT_CACHED;
    }

    /**
     * Check a password the learner has just typed against the one on the account.
     *
     * Always go through this rather than calling `validate_internal_user_password()`
     * with `$USER`. That function reads `$user->password`, and `$USER` never has
     * one - \core\session\manager::set_user() unsets it on the way into the
     * session. Passing `$USER` therefore hands core a null where it declares a
     * string, and PHP 8 raises
     *
     *   password_is_legacy_hash(): Argument #1 ($password) must be of type string,
     *   null given
     *
     * which is a fatal page error, not a failed password check. The record is
     * re-read here so the hash is actually present.
     *
     * @param int $userid whose password to check
     * @param string $password the plain text they typed
     * @return bool
     */
    public static function verify_password(int $userid, string $password): bool {
        global $DB;

        if ($userid <= 0 || $password === '') {
            return false;
        }

        $record = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);

        // An account with no local password (oauth2, or a broken row) cannot be
        // verified. Refused rather than passed to core, which would throw.
        if (!$record || $record->password === null || $record->password === '') {
            return false;
        }

        return validate_internal_user_password($record, $password);
    }

    /**
     * When this account's password was last changed, or 0 when that is not known.
     *
     * Read from core's password history, which is the only record Moodle keeps of
     * when a password changed. That table is written only while
     * `$CFG->passwordreuselimit` is above zero, so 0 here means "the site does not
     * keep this", not "never changed" - and WF-5.2 is told the difference so it can
     * say so rather than invent a date.
     *
     * @param int $userid
     * @return int unix time, or 0
     */
    public static function password_changed(int $userid): int {
        global $DB;

        try {
            $latest = $DB->get_field_sql(
                'SELECT MAX(timecreated) FROM {user_password_history} WHERE userid = :userid',
                ['userid' => $userid]
            );
        } catch (\Throwable $e) {
            return 0;
        }

        return (int) $latest;
    }

    /**
     * Which core fields this screen shows right now, and whether each is locked.
     *
     * Both halves are read from the management page rather than decided here.
     * *Whether a field appears* is Site administration -> Profile fields, Sign-up
     * tab - the field's placement, which is what `manager::on_profile()` answers.
     * *Whether it can be typed into* is the Profile tab of the same page, stored
     * as core's own per-auth field lock. The form and the save path both ask this
     * one method, so they cannot disagree about a field a hand-crafted POST tries
     * to set.
     *
     * The lock applies to everybody, an administrator looking at their own account
     * included - which is exactly what core does on `/user/edit.php`. An
     * administrator who has to correct a locked field does it where core intends,
     * on `/user/editadvanced.php`, reached from Site administration -> Users ->
     * Browse list of users. That route only works if the profile page is reachable
     * for one's own account too, which is why
     * `hook_callbacks::redirect_own_profile()` leaves `/user/profile.php?id=...`
     * alone whoever the id names.
     *
     * @return array<string,bool> field name => true when the learner may not edit it
     */
    public static function core_fields(): array {
        $fields = [];

        foreach (self::CORE_FIELDS as $name) {
            if (!manager::on_profile($name)) {
                continue;
            }
            $fields[$name] = core_locks::is_locked($name);
        }

        return $fields;
    }

    /**
     * The custom profile fields this screen draws, in the site's own field order.
     *
     * Everything an administrator has not set to "Hidden", so a field created on
     * Site administration -> User profile fields appears here without this plugin
     * being told about it. Ordered by profile-field category and then by position
     * within it, which is the order every other profile page uses.
     *
     * @param int $userid whose values to load
     * @return \profile_field_base[] keyed by form element name
     */
    public static function profile_fields(int $userid): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $fields = [];

        foreach (profile_get_user_fields_with_data($userid) as $field) {
            // PROFILE_VISIBLE_NONE is what the management page writes for "Hidden".
            // Deliberately not is_visible(): that also turns away a field marked
            // "visible to me only", and the one person it is visible to is exactly
            // the one looking at this screen.
            if ((int) $field->field->visible === (int) PROFILE_VISIBLE_NONE) {
                continue;
            }
            $fields[$field->inputname] = $field;
        }

        return $fields;
    }

    /**
     * The label to draw for a core field.
     *
     * An administrator may rename a field on the management page; the name they
     * chose is the name the learner should see here too, not core's.
     *
     * @param string $name core field name
     * @return string plain text
     */
    public static function core_label(string $name): string {
        return manager::core_label($name);
    }

    /**
     * The current value of a core field, ready to show as read-only text.
     *
     * @param \stdClass $user the account being shown
     * @param string $name core field name
     * @return string plain text, '' when the field is empty
     */
    public static function core_display(\stdClass $user, string $name): string {
        $value = trim((string) ($user->{$name} ?? ''));

        if ($name !== 'country' || $value === '') {
            return $value;
        }

        // A country is stored as its ISO code, which is not what anybody calls it.
        $iso = strtoupper($value);
        $countries = get_string_manager()->get_list_of_countries(true);
        $name = $countries[$iso] ?? $iso;

        // The phone field ships a flag per country; use it when it is installed so
        // the row reads the same as the dialling-code menu it was derived from.
        if (class_exists('\profilefield_phone\dialcodes')) {
            $name = \profilefield_phone\dialcodes::flag($iso) . ' ' . $name;
        }

        return $name;
    }

    /**
     * A value the learner may read but not change.
     *
     * One shape for every locked field on the screen, wherever the lock comes
     * from. Before this there were three different ways of saying it - a padlock
     * beside the name, a sentence in its own wording under the e-mail address, a
     * dashed box for the country - and three ways of saying one thing reads as
     * three different rules rather than one.
     *
     * @param string $value the current value
     * @param bool $ishtml true when $value is already-safe HTML rather than text
     * @return string HTML
     */
    public static function locked_value(string $value, bool $ishtml = false): string {
        $shown = trim($value) !== ''
            ? ($ishtml ? $value : s($value))
            : html_writer::span(get_string('notset', 'local_profilefields'), 'nit-account__notset');

        return html_writer::div(
            html_writer::span($shown, 'nit-account__lockedvalue')
            . html_writer::span('', 'nit-account__lock', [
                'aria-hidden' => 'true',
                'title' => get_string('lockedfield', 'local_profilefields'),
            ]),
            'nit-account__lockedbox');
    }

    /**
     * The one sentence that explains a locked field, printed under its value.
     *
     * The padlock is what a glance picks up; this is the answer to "why can I not
     * type here", and it is the same answer, in the same words, in the same place,
     * for every locked field on the screen. A field whose reason is genuinely a
     * different one - an address that belongs to a Google account rather than to
     * an administrator - passes its own string id.
     *
     * @param string $reason string id in this plugin
     * @return string HTML
     */
    public static function locked_note(string $reason = 'lockedfield'): string {
        return html_writer::div(get_string($reason, 'local_profilefields'),
            'nit-account__help nit-account__help--locked');
    }

    /**
     * One card: a heading, an optional lead line, and its contents.
     *
     * @param string $title the card's heading, already localised
     * @param string $body HTML for the card's contents
     * @param string $lead an optional sentence under the heading
     * @param string $extraclass an extra class for the card element
     * @return string HTML
     */
    public static function card(string $title, string $body, string $lead = '', string $extraclass = ''): string {
        $head = '';
        if ($title !== '') {
            $head .= html_writer::tag('h2', $title, ['class' => 'nit-account__cardtitle']);
        }
        if ($lead !== '') {
            $head .= html_writer::div($lead, 'nit-account__cardlead');
        }

        return html_writer::div($head . $body, trim('nit-account__card ' . $extraclass));
    }
}
