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
 * which is how `/local/nit_instructors/edit.php` shows the same navigation
 * without this plugin having to own the instructor form.
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
     * @var string WF-5.6 - the instructor's Academic and Professional Background.
     *
     * Not a pane of this plugin: the section is named here only so the entry can
     * be marked current while `local_nit_instructors` renders it.
     */
    const SECTION_BACKGROUND = 'background';

    /**
     * @var string[] The panes account.php itself serves.
     *
     * Delete is not among them. It already has a page of its own, and that page
     * owns the deletion, the goodbye mail and the sign-out; giving account.php a
     * second copy of that sequence would be two places to get an irreversible act
     * wrong. Like the instructor background, it joins the screen by wrapping
     * itself in {@see self::open()} instead.
     */
    const OWN_SECTIONS = [self::SECTION_PROFILE, self::SECTION_SECURITY];

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
     * Is the Academic and Professional Background entry shown for this account?
     *
     * AC-4.5.6: "shown only on accounts holding the instructor role. It is absent
     * from a learner's profile screen entirely, not merely disabled." So this
     * decides whether the entry exists at all, not whether it is greyed out.
     *
     * The check is delegated to `local_nit_instructors`, which reads the site's own
     * `$CFG->coursecontact` roles - the answer has to be the same one the
     * background page itself gives, or a learner could be shown an entry that then
     * refuses them. Guarded with class_exists() because that plugin is a separate
     * install and this screen has to work without it.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_instructor(int $userid): bool {
        if (!class_exists('\local_nit_instructors\profile')) {
            return false;
        }

        try {
            return \local_nit_instructors\profile::is_instructor($userid);
        } catch (\Throwable $e) {
            // A half-installed plugin must not take the account screen down.
            debugging('local_profilefields: instructor check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * The navigation entries, in the order the wireframe lists them.
     *
     * @param string $active the SECTION_* constant of the entry to mark current
     * @return array<int, array{key: string, label: string, url: string, active: bool, danger: bool}>
     */
    public static function nav(string $active): array {
        global $USER, $CFG;

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

        if (self::is_instructor((int) $USER->id)) {
            $items[] = [
                'key' => self::SECTION_BACKGROUND,
                'label' => get_string('navbackground', 'local_profilefields'),
                'url' => (new moodle_url('/local/nit_instructors/edit.php'))->out(false),
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
            $classes = 'nit-account__navlink';
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
     * The raw stored value of the telephone profile field, or '' when there is none.
     *
     * @param int $userid
     * @return string in the phone field's own "ISO:number" form
     */
    public static function phone_value(int $userid): string {
        global $DB;

        $sql = "SELECT d.data
                  FROM {user_info_data} d
                  JOIN {user_info_field} f ON f.id = d.fieldid
                 WHERE d.userid = :userid AND f.shortname = :shortname";

        return (string) $DB->get_field_sql($sql, ['userid' => $userid, 'shortname' => 'phone']);
    }

    /**
     * The read-only "Country and telephone" group of WF-5.1.
     *
     * Three values a learner may see but not set. AC-4.5.3 makes the country of
     * record an administrator's to change, because it is what decides the prices
     * charged, and the telephone's dialling code is the field that country was
     * derived from at registration - so it is locked for the same reason, one step
     * upstream.
     *
     * @param \stdClass $user the account being shown
     * @return string HTML
     */
    public static function locked_group(\stdClass $user): string {
        $notset = get_string('notset', 'local_profilefields');

        // Country of record, with its flag when the phone plugin can supply one.
        $countries = get_string_manager()->get_list_of_countries(true);
        $iso = strtoupper((string) $user->country);
        $countryname = $countries[$iso] ?? $notset;
        if ($iso !== '' && class_exists('\profilefield_phone\dialcodes')) {
            $countryname = \profilefield_phone\dialcodes::flag($iso) . ' ' . $countryname;
        }

        // The telephone is one custom field storing "ISO:number", so the dialling
        // code and the subscriber number are two views of one stored value.
        $dialcode = '';
        $number = '';
        $phone = self::phone_value((int) $user->id);
        if ($phone !== '') {
            [$phoneiso, $number] = array_pad(explode(':', $phone, 2), 2, '');
            if ($phoneiso !== '' && class_exists('\profilefield_phone\dialcodes')) {
                $dialcode = '+' . \profilefield_phone\dialcodes::code(strtoupper($phoneiso));
            }
        }

        $fields = html_writer::div(
            self::locked_field(get_string('countryofrecord', 'local_profilefields'), $countryname)
            . self::locked_field(get_string('phonecountrycode', 'local_profilefields'), $dialcode),
            'nit-account__lockedrow')
            . self::locked_field(get_string('phonenumber', 'local_profilefields'), $number);

        return html_writer::div(
            html_writer::tag('h3', get_string('countryandtelephone', 'local_profilefields'),
                ['class' => 'nit-account__subtitle'])
            . html_writer::div(
                $fields
                . html_writer::div(get_string('countryofrecordhelp', 'local_profilefields'),
                    'nit-account__help'),
                'nit-account__locked'),
            'nit-account__lockedgroup');
    }

    /**
     * One labelled value a learner may read but not change.
     *
     * @param string $label the localised field label
     * @param string $value the value, or '' to show "Not set"
     * @return string HTML
     */
    protected static function locked_field(string $label, string $value): string {
        $shown = ($value !== '') ? $value : get_string('notset', 'local_profilefields');

        return html_writer::div(
            html_writer::tag('span', $label, ['class' => 'nit-account__lockedlabel'])
            . html_writer::div(
                html_writer::span($shown, 'nit-account__lockedvalue')
                . html_writer::span('', 'nit-account__lock', [
                    'aria-hidden' => 'true',
                    'title' => get_string('lockedfield', 'local_profilefields'),
                ]),
                'nit-account__lockedbox'),
            'nit-account__lockedfield');
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
