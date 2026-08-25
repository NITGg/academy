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
use tabobject;

defined('MOODLE_INTERNAL') || die();

/**
 * The three-tab management screen: register, login, profile.
 *
 * Each tab is a plain table of toggles that reads from, and writes back to, the
 * native Moodle settings behind each field (see manager, custom_fields, core_locks
 * and provision). There is deliberately no parallel field store here - the page is
 * a friendlier front end onto controls Moodle already has, spread across several
 * screens, plus the sign-up-form reshaping this plugin adds.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page {

    /** @var string Register tab. */
    const TAB_REGISTER = 'register';

    /** @var string Login tab. */
    const TAB_LOGIN = 'login';

    /** @var string Profile tab. */
    const TAB_PROFILE = 'profile';

    /**
     * The valid tab identifiers.
     *
     * @return string[]
     */
    public static function tabs(): array {
        return [self::TAB_REGISTER, self::TAB_LOGIN, self::TAB_PROFILE];
    }

    /**
     * A URL back to this page on a given tab.
     *
     * @param string $tab tab id
     * @param array $extra extra query params
     * @return moodle_url
     */
    protected static function url(string $tab, array $extra = []): moodle_url {
        return new moodle_url('/local/profilefields/manage.php', ['tab' => $tab] + $extra);
    }

    /**
     * Handle a reorder link or a form submission for the active tab, then redirect.
     *
     * Runs before any output, so a successful action can redirect with a notice.
     *
     * @param string $tab the active tab
     * @return void
     */
    public static function process(string $tab): void {
        // Provisioning: create the recommended custom fields.
        if (optional_param('provision', 0, PARAM_BOOL)) {
            require_sesskey();
            $count = provision::run();
            redirect(self::url($tab),
                get_string('provisiondone', 'local_profilefields', $count),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }

        // A reorder arrow on the register tab (a submit button, so the rest of the
        // form is saved in the same request and nothing typed is lost).
        $moveup = optional_param('moveup', '', PARAM_RAW);
        $movedown = optional_param('movedown', '', PARAM_RAW);
        if (($moveup !== '' || $movedown !== '') && confirm_sesskey()) {
            self::save_register();
            self::move($moveup !== '' ? $moveup : $movedown, $moveup !== '');
            redirect(self::url($tab));
        }

        // Save the toggles for the active tab.
        if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
            switch ($tab) {
                case self::TAB_REGISTER:
                    self::save_register();
                    break;
                case self::TAB_LOGIN:
                    self::save_login();
                    break;
                case self::TAB_PROFILE:
                    self::save_profile();
                    break;
            }
            redirect(self::url($tab), get_string('changessaved'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    /**
     * Render the tab bar plus the active tab's content.
     *
     * @param string $tab the active tab
     * @return void
     */
    public static function render(string $tab): void {
        global $OUTPUT;

        $rows = [
            new tabobject(self::TAB_REGISTER, self::url(self::TAB_REGISTER),
                get_string('tabregister', 'local_profilefields')),
            new tabobject(self::TAB_LOGIN, self::url(self::TAB_LOGIN),
                get_string('tablogin', 'local_profilefields')),
            new tabobject(self::TAB_PROFILE, self::url(self::TAB_PROFILE),
                get_string('tabprofile', 'local_profilefields')),
        ];
        echo $OUTPUT->tabtree($rows, $tab);

        switch ($tab) {
            case self::TAB_LOGIN:
                self::render_login();
                break;
            case self::TAB_PROFILE:
                self::render_profile();
                break;
            case self::TAB_REGISTER:
            default:
                self::render_register();
                break;
        }
    }

    // -----------------------------------------------------------------
    // Register tab.
    // -----------------------------------------------------------------

    /**
     * The order tokens for every field that can appear on the sign-up form.
     *
     * @return string[] tokens in configured order
     */
    protected static function signup_tokens(): array {
        $tokens = [];

        foreach (manager::core_fields() as $name => $meta) {
            if (empty($meta['onsignup'])) {
                continue;
            }
            if ($name === 'username' && manager::username_from_email()) {
                continue;
            }
            if ($name === 'email2') {
                // The confirmation box is an anti-typo helper, not a real field to place.
                continue;
            }
            $tokens[] = $name;
        }

        foreach (custom_fields::get_all() as $field) {
            if ((int) $field->visible !== (int) PROFILE_VISIBLE_NONE) {
                $tokens[] = 'cf:' . $field->shortname;
            }
        }

        return manager::order_tokens($tokens);
    }

    /**
     * Render the register tab.
     *
     * @return void
     */
    protected static function render_register(): void {
        echo html_writer::tag('p', get_string('tabregister_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        // One form for the whole tab, with a single Save button. Splitting the tab
        // into several small forms made a label edit land only if the matching form's
        // own Save was clicked - so edits appeared to save unpredictably.
        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => self::url(self::TAB_REGISTER)->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

        $tokens = self::signup_tokens();
        $custom = self::custom_by_shortname();

        $head = html_writer::tag('tr',
            html_writer::tag('th', '', ['style' => 'width:4rem']) .
            html_writer::tag('th', get_string('colfield', 'local_profilefields')) .
            html_writer::tag('th', get_string('colshow', 'local_profilefields'), ['class' => 'text-center']) .
            html_writer::tag('th', get_string('colrequired', 'local_profilefields'), ['class' => 'text-center']) .
            html_writer::tag('th', get_string('colrename', 'local_profilefields')));

        // The two behaviour rows sit at the top of the same table.
        $body = self::username_row() . self::country_from_phone_row();

        $last = count($tokens) - 1;
        foreach ($tokens as $i => $token) {
            $body .= self::register_row($token, $custom, $i === 0, $i === $last);
        }

        echo html_writer::tag('table', $head . $body, ['class' => 'generaltable w-100']);

        // Sections that live under the table but inside the same form and Save.
        echo self::ipmatch_section();
        echo self::terms_section();

        echo html_writer::tag('div',
            html_writer::tag('button', get_string('savechanges'),
                ['type' => 'submit', 'class' => 'btn btn-primary']),
            ['class' => 'mt-3']);
        echo html_writer::end_tag('form');
    }

    /**
     * The "username from email" behaviour, as a table row.
     *
     * @return string HTML
     */
    protected static function username_row(): string {
        $sources = [
            manager::USERNAME_EMAIL     => get_string('usernamesourceemail', 'local_profilefields'),
            manager::USERNAME_LOCALPART => get_string('usernamesourcelocalpart', 'local_profilefields'),
        ];
        $controls = html_writer::div(
            self::yesno_select('usernamefromemail', manager::username_from_email()) .
            html_writer::tag('label', get_string('usernamesource', 'local_profilefields'),
                ['class' => 'ms-3 me-2 mb-0']) .
            html_writer::select($sources, 'usernamesource', manager::username_source(), false),
            'd-flex flex-wrap align-items-center gap-1');

        return html_writer::tag('tr',
            html_writer::tag('td', '') .
            html_writer::tag('td',
                html_writer::span(get_string('usernameheading', 'local_profilefields'), 'fw-semibold') . ' ' .
                self::badge('special') . html_writer::div(
                    get_string('usernamefromemail', 'local_profilefields'), 'text-muted small')) .
            html_writer::tag('td', $controls, ['colspan' => 3]),
            ['class' => 'table-active']);
    }

    /**
     * The "fill Country from the phone field" behaviour, as a table row.
     *
     * @return string HTML
     */
    protected static function country_from_phone_row(): string {
        return html_writer::tag('tr',
            html_writer::tag('td', '') .
            html_writer::tag('td',
                html_writer::span(get_string('countryfromphone', 'local_profilefields'), 'fw-semibold') . ' ' .
                self::badge('special') . html_writer::div(
                    get_string('countryfromphone_desc', 'local_profilefields'), 'text-muted small')) .
            html_writer::tag('td',
                self::yesno_select('countryfromphone', manager::country_from_phone()), ['colspan' => 3]),
            ['class' => 'table-active']);
    }

    /**
     * One register-table row.
     *
     * @param string $token order token
     * @param array $custom custom fields keyed by shortname
     * @param bool $first is this the first row (no "up")
     * @param bool $slast is this the last row (no "down")
     * @return string HTML
     */
    protected static function register_row(string $token, array $custom, bool $first, bool $slast): string {
        $iscustom = strpos($token, 'cf:') === 0;

        if ($iscustom) {
            $field = $custom[substr($token, 3)] ?? null;
            if ($field === null) {
                return '';
            }
            $label = format_string($field->name);
            $sub = html_writer::span(s($field->shortname), 'text-muted small') . ' ' . self::badge('custom');
            $show = ['name' => 'cfsignup[' . $field->id . ']', 'checked' => !empty($field->signup), 'disabled' => false];
            $req = ['name' => 'cfreq[' . $field->id . ']', 'checked' => !empty($field->required), 'disabled' => false];
            $rename = html_writer::link(
                new moodle_url('/user/profile/index.php', ['id' => $field->id, 'action' => 'editfield']),
                get_string('renameoncore', 'local_profilefields'), ['class' => 'small']);
        } else {
            $meta = manager::core_fields()[$token] ?? null;
            if ($meta === null) {
                return '';
            }
            $label = self::core_label($token);
            $sub = html_writer::span(s($token), 'text-muted small') . ' ' . self::badge('builtin');
            $mandatory = !self::core_can_hide($token);
            $show = [
                'name' => 'show[' . $token . ']',
                'checked' => manager::on_signup($token),
                'disabled' => $mandatory,
            ];
            $reqfixed = in_array($token, ['password', 'email', 'firstname', 'lastname'], true);
            $req = [
                'name' => 'req[' . $token . ']',
                'checked' => $reqfixed ? true : !empty(manager::get_config()[$token]['required']),
                'disabled' => $reqfixed || empty($meta['canrequire']),
            ];
            $rename = self::rename_details($token);
        }

        $up = $first ? '' : self::move_button($token, true);
        $down = $slast ? '' : self::move_button($token, false);

        return html_writer::tag('tr',
            html_writer::tag('td', $up . $down, ['class' => 'text-nowrap']) .
            html_writer::tag('td', html_writer::span($label, 'fw-semibold') . ' ' . $sub) .
            html_writer::tag('td', self::checkbox($show), ['class' => 'text-center']) .
            html_writer::tag('td', self::checkbox($req), ['class' => 'text-center']) .
            html_writer::tag('td', $rename));
    }

    /**
     * The collapsible per-language rename control for a core field.
     *
     * @param string $token core field name
     * @return string HTML
     */
    protected static function rename_details(string $token): string {
        $config = manager::get_config();
        $parts = manager::label_parts((string) ($config[$token]['label'] ?? ''));

        $inputs = '';
        foreach (manager::label_langs() as $code => $langname) {
            $inputs .= html_writer::div(
                html_writer::tag('label', s($langname), ['class' => 'small me-2']) .
                html_writer::empty_tag('input', [
                    'type' => 'text', 'class' => 'form-control form-control-sm d-inline-block w-auto',
                    'name' => 'label[' . $token . '][' . $code . ']',
                    'value' => $parts[$code] ?? '',
                    'placeholder' => self::core_label($token),
                ]), 'mb-1');
        }

        $summary = html_writer::tag('summary', get_string('renamefield', 'local_profilefields'),
            ['class' => 'small text-primary', 'style' => 'cursor:pointer']);

        return html_writer::tag('details', $summary . html_writer::div($inputs, 'mt-2'));
    }

    /**
     * The "match phone country to IP" section, under the fields table.
     *
     * @return string HTML
     */
    protected static function ipmatch_section(): string {
        $out = html_writer::start_div('card card-body mt-4');
        $out .= html_writer::tag('h4', get_string('ipmatchheading', 'local_profilefields'));
        $out .= html_writer::tag('div',
            self::checkbox(['name' => 'ipmatchphone', 'checked' => manager::ip_match_phone()]) . ' ' .
            html_writer::tag('label', get_string('ipmatchphone', 'local_profilefields'), ['class' => 'ms-2 mb-0']),
            ['class' => 'form-check form-switch']);
        $out .= html_writer::tag('p', get_string('ipmatchphone_desc', 'local_profilefields'),
            ['class' => 'text-muted small mt-1']);

        // The check works with no setup via a free online lookup; a local GeoIP
        // database, if the admin installs one, is used instead.
        $url = (new moodle_url('/admin/settings.php', ['section' => 'locationsettings']))->out();
        $note = self::geoip_available()
            ? get_string('ipmatchgeoip', 'local_profilefields')
            : get_string('ipmatchonline', 'local_profilefields', $url);
        $out .= html_writer::div($note, 'alert alert-info');

        $out .= html_writer::end_div();
        return $out;
    }

    /**
     * The terms & privacy section: the inline-consent switch plus policy status.
     *
     * @return string HTML
     */
    protected static function terms_section(): string {
        global $CFG, $OUTPUT;

        $installed = \core_component::get_component_directory('tool_policy') !== null;
        $usingtool = ($CFG->sitepolicyhandler ?? '') === 'tool_policy';

        $out = html_writer::start_div('card card-body mt-4');
        $out .= html_writer::tag('h4', get_string('termsheading', 'local_profilefields'));

        $out .= html_writer::tag('div',
            self::checkbox(['name' => 'consentenabled', 'checked' => manager::consent_enabled()]) . ' ' .
            html_writer::tag('label', get_string('consentenable', 'local_profilefields'), ['class' => 'ms-2 mb-0']),
            ['class' => 'form-check form-switch']);
        $out .= html_writer::tag('p', get_string('consentenable_desc', 'local_profilefields'),
            ['class' => 'text-muted small mt-1']);

        $docs = policies::signup_documents();
        if (!empty($docs)) {
            $items = '';
            foreach ($docs as $name => $url) {
                $items .= html_writer::tag('li', html_writer::link($url, s($name), ['target' => '_blank']));
            }
            $out .= html_writer::tag('p', get_string('termsdocsfound', 'local_profilefields'));
            $out .= html_writer::tag('ul', $items);
        } else {
            $out .= html_writer::div(get_string('termsdocsnone', 'local_profilefields'), 'alert alert-info');
        }

        if (manager::consent_enabled() && $usingtool) {
            // The site policy handler lives on the Policy settings page, not Privacy.
            $url = (new moodle_url('/admin/settings.php', ['section' => 'policysettings']))->out();
            $out .= html_writer::div(get_string('termsdoubleask', 'local_profilefields', $url),
                'alert alert-warning');
        }

        if ($installed) {
            // Theme_nit brands .btn-outline-* from the --nit-brand-* roles, so these
            // follow the palette (and a .nit-brand-2/3 group) instead of sitting on
            // Bootstrap's flat slate. They also need a flex row of their own: the
            // wrapper is a .card, which is display:flex/column, so bare anchors would
            // be stretched into full-width slabs. `gap` replaces a me-* margin, which
            // would sit on the wrong side in Arabic.
            $buttons = html_writer::link(new moodle_url('/admin/tool/policy/managedocs.php'),
                $OUTPUT->pix_icon('i/edit', '') . html_writer::span(
                    get_string('termsmanage', 'local_profilefields')),
                ['class' => 'btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 icon-no-margin']);
            $buttons .= html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'policysettings']),
                $OUTPUT->pix_icon('i/settings', '') . html_writer::span(
                    get_string('termspolicysettings', 'local_profilefields')),
                ['class' => 'btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2 icon-no-margin']);

            $out .= html_writer::div($buttons, 'd-flex flex-wrap gap-2 mt-3');
        } else {
            $out .= html_writer::div(get_string('termsnotool', 'local_profilefields'), 'text-muted small');
        }

        $out .= html_writer::end_div();
        return $out;
    }

    /**
     * A small "Built-in" / "Custom" / behaviour badge for a table row.
     *
     * @param string $kind one of 'builtin', 'custom', 'special'
     * @return string HTML
     */
    protected static function badge(string $kind): string {
        $map = [
            'builtin' => ['badge bg-secondary', get_string('badgebuiltin', 'local_profilefields')],
            'custom'  => ['badge bg-info text-dark', get_string('badgecustom', 'local_profilefields')],
            'special' => ['badge bg-primary', get_string('badgespecial', 'local_profilefields')],
        ];
        [$class, $text] = $map[$kind] ?? $map['builtin'];
        return html_writer::span($text, $class);
    }

    /**
     * Whether a geo-IP source is configured and usable for the IP-match check.
     *
     * @return bool
     */
    protected static function geoip_available(): bool {
        global $CFG;
        return (!empty($CFG->geoip2file) && file_exists($CFG->geoip2file)) || !empty($CFG->geopluginapikey);
    }

    /**
     * Save the whole register tab in one pass.
     *
     * @return void
     */
    protected static function save_register(): void {
        // Behaviour toggles.
        set_config('usernamefromemail', optional_param('usernamefromemail', 0, PARAM_BOOL) ? 1 : 0,
            manager::COMPONENT);
        $source = optional_param('usernamesource', manager::USERNAME_EMAIL, PARAM_ALPHA);
        set_config('usernamesource',
            $source === manager::USERNAME_LOCALPART ? manager::USERNAME_LOCALPART : manager::USERNAME_EMAIL,
            manager::COMPONENT);
        set_config('countryfromphone', optional_param('countryfromphone', 0, PARAM_BOOL) ? 1 : 0, manager::COMPONENT);
        set_config('ipmatchphone', optional_param('ipmatchphone', 0, PARAM_BOOL) ? 1 : 0, manager::COMPONENT);
        set_config('consentenabled', optional_param('consentenabled', 0, PARAM_BOOL) ? 1 : 0, manager::COMPONENT);

        // Core field toggles and per-language labels.
        $show = optional_param_array('show', [], PARAM_BOOL);
        $req = optional_param_array('req', [], PARAM_BOOL);
        $labels = self::posted_labels();

        manager::update_config(function (array $config) use ($show, $req, $labels) {
            foreach (manager::core_fields() as $name => $meta) {
                if (empty($meta['onsignup'])) {
                    continue;
                }
                if (self::core_can_hide($name)) {
                    $onsignup = !empty($show[$name]);
                    // Keep the field's current profile visibility; this tab only owns
                    // the sign-up side.
                    $onprofile = manager::on_profile($name);
                    $config[$name]['mode'] = self::merge_mode($config[$name]['mode'], $onsignup, $onprofile, $meta['modes']);
                }
                if (!empty($meta['canrequire'])) {
                    $config[$name]['required'] = !empty($req[$name]) ? 1 : 0;
                }
                if (isset($labels[$name])) {
                    $config[$name]['label'] = manager::build_label($labels[$name]);
                }
            }
            return $config;
        });

        // Custom fields on the register form: signup flag + required flag.
        $cfsignup = optional_param_array('cfsignup', [], PARAM_BOOL);
        $cfreq = optional_param_array('cfreq', [], PARAM_BOOL);
        self::save_custom_signup($cfsignup, $cfreq);
    }

    // -----------------------------------------------------------------
    // Login tab.
    // -----------------------------------------------------------------

    /**
     * Render the login tab.
     *
     * @return void
     */
    protected static function render_login(): void {
        global $CFG;

        echo html_writer::tag('p', get_string('tablogin_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => self::url(self::TAB_LOGIN)->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

        $rows = [
            self::switch_row('selfregister', !empty($CFG->registerauth),
                get_string('loginselfregister', 'local_profilefields'),
                get_string('loginselfregister_desc', 'local_profilefields')),
            self::switch_row('guestlogin', !empty($CFG->guestloginbutton),
                get_string('loginguest', 'local_profilefields'),
                get_string('loginguest_desc', 'local_profilefields')),
            self::switch_row('rememberusername', !empty($CFG->rememberusername),
                get_string('loginremember', 'local_profilefields'),
                get_string('loginremember_desc', 'local_profilefields')),
        ];
        echo html_writer::tag('table', implode('', $rows), ['class' => 'generaltable w-100']);

        echo html_writer::tag('div',
            html_writer::tag('button', get_string('savechanges'),
                ['type' => 'submit', 'class' => 'btn btn-primary']),
            ['class' => 'mt-3']);
        echo html_writer::end_tag('form');
    }

    /**
     * Save the login tab into the native core settings.
     *
     * @return void
     */
    protected static function save_login(): void {
        // Self registration: the toggle maps to whether an auth plugin handles it.
        // "email" is Moodle's stock self-registration handler; empty turns it off.
        set_config('registerauth', optional_param('selfregister', 0, PARAM_BOOL) ? 'email' : '');
        set_config('guestloginbutton', optional_param('guestlogin', 0, PARAM_BOOL) ? 1 : 0);
        set_config('rememberusername', optional_param('rememberusername', 0, PARAM_BOOL) ? 1 : 0);
    }

    // -----------------------------------------------------------------
    // Profile tab.
    // -----------------------------------------------------------------

    /**
     * Render the profile tab.
     *
     * @return void
     */
    protected static function render_profile(): void {
        echo html_writer::tag('p', get_string('tabprofile_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        echo self::provision_panel();

        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => self::url(self::TAB_PROFILE)->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

        $head = html_writer::tag('tr',
            html_writer::tag('th', get_string('colfield', 'local_profilefields')) .
            html_writer::tag('th', get_string('colcanedit', 'local_profilefields'), ['class' => 'text-center']));

        $body = html_writer::tag('tr',
            html_writer::tag('td', html_writer::tag('strong',
                get_string('corefieldsheading', 'local_profilefields')), ['colspan' => 2]),
            ['class' => 'table-active']);
        foreach (core_locks::LOCKABLE as $name) {
            $body .= self::profile_core_row($name);
        }

        $body .= html_writer::tag('tr',
            html_writer::tag('td', html_writer::tag('strong',
                get_string('customfieldsheading', 'local_profilefields')), ['colspan' => 2]),
            ['class' => 'table-active']);
        foreach (custom_fields::get_all() as $field) {
            $body .= self::profile_custom_row($field);
        }

        echo html_writer::tag('table', $head . $body, ['class' => 'generaltable w-100']);

        echo html_writer::tag('div',
            html_writer::tag('button', get_string('savechanges'),
                ['type' => 'submit', 'class' => 'btn btn-primary']),
            ['class' => 'mt-3']);
        echo html_writer::end_tag('form');
    }

    /**
     * One profile-table row for a core field: name + "user can edit".
     *
     * @param string $name core field name
     * @return string HTML
     */
    protected static function profile_core_row(string $name): string {
        $canedit = self::checkbox(['name' => 'pedit[' . $name . ']', 'checked' => !core_locks::is_locked($name)]);

        return html_writer::tag('tr',
            html_writer::tag('td', html_writer::span(self::core_label($name), 'fw-semibold') . ' ' .
                html_writer::span(s($name), 'text-muted small')) .
            html_writer::tag('td', $canedit, ['class' => 'text-center']));
    }

    /**
     * One profile-table row for a custom field: name + "user can edit".
     *
     * @param \stdClass $field a user_info_field record
     * @return string HTML
     */
    protected static function profile_custom_row(\stdClass $field): string {
        $canedit = self::checkbox(['name' => 'pcfedit[' . (int) $field->id . ']', 'checked' => empty($field->locked)]);

        return html_writer::tag('tr',
            html_writer::tag('td', html_writer::span(format_string($field->name), 'fw-semibold') . ' ' .
                html_writer::span(s($field->shortname), 'text-muted small')) .
            html_writer::tag('td', $canedit, ['class' => 'text-center']));
    }

    /**
     * Save the profile tab: only "which fields the user can edit".
     *
     * Everything else about a field (whether it shows, is required, is unique) is
     * managed where it naturally belongs - the sign-up tab for the register form,
     * and the core "Edit profile field" page for the field's own settings.
     *
     * @return void
     */
    protected static function save_profile(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        // Core fields: the native per-auth field lock.
        $pedit = optional_param_array('pedit', [], PARAM_BOOL);
        foreach (core_locks::LOCKABLE as $name) {
            core_locks::set_locked($name, empty($pedit[$name]));
        }

        // Custom fields: the "locked" flag.
        $edit = optional_param_array('pcfedit', [], PARAM_BOOL);
        $changed = false;
        foreach (custom_fields::get_all() as $field) {
            $wantlocked = empty($edit[(int) $field->id]) ? 1 : 0;
            if ((int) $field->locked === $wantlocked) {
                continue;
            }
            $DB->set_field('user_info_field', 'locked', $wantlocked, ['id' => $field->id]);
            \core\event\user_info_field_updated::create_from_field(
                $DB->get_record('user_info_field', ['id' => $field->id]))->trigger();
            $changed = true;
        }
        if ($changed) {
            profile_purge_user_fields_cache();
        }
    }

    // -----------------------------------------------------------------
    // Shared helpers.
    // -----------------------------------------------------------------

    /**
     * The provisioning panel: create the recommended fields.
     *
     * @return string HTML
     */
    protected static function provision_panel(): string {
        $missing = provision::missing();
        if (empty($missing)) {
            return html_writer::div(
                html_writer::span(get_string('provisionallset', 'local_profilefields'), 'text-success'),
                'alert alert-info');
        }

        $out = html_writer::start_div('card card-body mb-4');
        $out .= html_writer::tag('h4', get_string('provisionheading', 'local_profilefields'));
        $out .= html_writer::tag('p', get_string('provisionintro', 'local_profilefields', count($missing)));
        if (in_array('phone', $missing, true) && !provision::phone_available()) {
            $out .= html_writer::div(get_string('provisionnophone', 'local_profilefields'),
                'alert alert-warning');
        }
        $url = self::url(self::TAB_PROFILE, ['provision' => 1, 'sesskey' => sesskey()]);
        $out .= html_writer::link($url, get_string('provisionbutton', 'local_profilefields'),
            ['class' => 'btn btn-secondary']);
        $out .= html_writer::end_div();

        return $out;
    }

    /**
     * Move a token one place up or down within the stored sign-up order.
     *
     * @param string $token the token to move
     * @param bool $up true to move up, false to move down
     * @return void
     */
    protected static function move(string $token, bool $up): void {
        $tokens = self::signup_tokens();
        $index = array_search($token, $tokens, true);
        if ($index === false) {
            return;
        }
        $swap = $up ? $index - 1 : $index + 1;
        if ($swap < 0 || $swap >= count($tokens)) {
            return;
        }
        [$tokens[$index], $tokens[$swap]] = [$tokens[$swap], $tokens[$index]];
        manager::set_signup_order($tokens);
    }

    /**
     * Custom fields keyed by shortname.
     *
     * @return array<string,\stdClass>
     */
    protected static function custom_by_shortname(): array {
        $out = [];
        foreach (custom_fields::get_all() as $field) {
            $out[$field->shortname] = $field;
        }
        return $out;
    }

    /**
     * The current label for a core field (admin override, else the core string).
     *
     * @param string $name core field name
     * @return string plain text
     */
    protected static function core_label(string $name): string {
        $meta = manager::core_fields()[$name] ?? null;
        if ($meta === null) {
            return $name;
        }
        $override = trim((string) (manager::get_config()[$name]['label'] ?? ''));
        if ($override !== '') {
            return format_string($override);
        }
        return get_string($meta['label'], $meta['labelcomponent'] ?? 'moodle');
    }

    /**
     * Whether a core field may be hidden from the sign-up form at all.
     *
     * @param string $name core field name
     * @return bool
     */
    protected static function core_can_hide(string $name): bool {
        $meta = manager::core_fields()[$name] ?? [];
        foreach ($meta['modes'] ?? [] as $mode) {
            if (!in_array($mode, [manager::MODE_BOTH, manager::MODE_SIGNUP], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fold a boolean signup/profile pair back into a MODE_* value the field allows.
     *
     * @param string $current the current mode (kept when the pair is impossible)
     * @param bool $signup show on sign-up
     * @param bool $profile show on profile
     * @param string[] $allowed the modes this field offers
     * @return string
     */
    protected static function merge_mode(string $current, bool $signup, bool $profile, array $allowed): string {
        if ($signup && $profile) {
            $mode = manager::MODE_BOTH;
        } else if ($signup) {
            $mode = manager::MODE_SIGNUP;
        } else if ($profile) {
            $mode = manager::MODE_PROFILE;
        } else {
            $mode = manager::MODE_HIDDEN;
        }
        return in_array($mode, $allowed, true) ? $mode : $current;
    }

    /**
     * Per-language label input, cleaned, keyed by core field then language.
     *
     * @return array<string,array<string,string>>
     */
    protected static function posted_labels(): array {
        // The label inputs are a two-dimensional array (label[field][lang]);
        // optional_param_array only cleans one level, so the nested structure is read
        // from the submitted data and every leaf cleaned by hand. The sesskey has
        // already been confirmed by the caller.
        $submitted = data_submitted();
        $labels = [];
        if (!$submitted || !isset($submitted->label)) {
            return $labels;
        }
        $raw = $submitted->label;
        if (!is_array($raw) && !is_object($raw)) {
            return $labels;
        }
        foreach ((array) $raw as $field => $perlang) {
            if (!is_array($perlang) && !is_object($perlang)) {
                continue;
            }
            $field = clean_param((string) $field, PARAM_ALPHANUMEXT);
            if ($field === '') {
                continue;
            }
            foreach ((array) $perlang as $lang => $value) {
                $lang = clean_param((string) $lang, PARAM_SAFEDIR);
                if ($lang !== '') {
                    $labels[$field][$lang] = clean_param((string) $value, PARAM_TEXT);
                }
            }
        }
        return $labels;
    }

    /**
     * Apply the custom-field signup and required flags from the register tab.
     *
     * @param array $signup field id => bool
     * @param array $req field id => bool
     * @return void
     */
    protected static function save_custom_signup(array $signup, array $req): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        $changed = false;
        foreach (custom_fields::get_all() as $field) {
            $id = (int) $field->id;
            $wantsignup = !empty($signup[$id]) ? 1 : 0;
            $wantreq = !empty($req[$id]) ? 1 : 0;

            // Do not resurrect a hidden field onto sign-up; it must be visible first.
            if ($wantsignup && (int) $field->visible === (int) PROFILE_VISIBLE_NONE) {
                $wantsignup = 0;
            }
            if ((int) $field->signup === $wantsignup && (int) $field->required === $wantreq) {
                continue;
            }
            $DB->update_record('user_info_field', (object) [
                'id' => $id, 'signup' => $wantsignup, 'required' => $wantreq,
            ]);
            \core\event\user_info_field_updated::create_from_field(
                $DB->get_record('user_info_field', ['id' => $id]))->trigger();
            $changed = true;
        }
        if ($changed) {
            profile_purge_user_fields_cache();
        }
    }

    // -----------------------------------------------------------------
    // Small HTML helpers.
    // -----------------------------------------------------------------

    /**
     * A checkbox cell.
     *
     * @param array $opts name, checked, disabled
     * @return string HTML
     */
    protected static function checkbox(array $opts): string {
        $attrs = [
            'type' => 'checkbox',
            'name' => $opts['name'],
            'value' => 1,
            'class' => 'form-check-input',
        ];
        if (!empty($opts['checked'])) {
            $attrs['checked'] = 'checked';
        }
        if (!empty($opts['disabled'])) {
            $attrs['disabled'] = 'disabled';
            // A disabled checkbox submits nothing; a hidden mirror keeps the value.
            return ($opts['checked'] ? html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => $opts['name'], 'value' => 1,
            ]) : '') . html_writer::empty_tag('input', $attrs);
        }
        return html_writer::empty_tag('input', $attrs);
    }

    /**
     * A yes/no select (used for the username switch).
     *
     * @param string $name
     * @param bool $value
     * @return string HTML
     */
    protected static function yesno_select(string $name, bool $value): string {
        return html_writer::select(
            [1 => get_string('yes'), 0 => get_string('no')],
            $name, $value ? 1 : 0, false, ['id' => 'id_' . $name]);
    }

    /**
     * A settings row with a switch and a description (login tab).
     *
     * @param string $name
     * @param bool $checked
     * @param string $label
     * @param string $desc
     * @return string HTML
     */
    protected static function switch_row(string $name, bool $checked, string $label, string $desc): string {
        return html_writer::tag('tr',
            html_writer::tag('td',
                html_writer::tag('div', html_writer::span($label, 'fw-semibold'), []) .
                html_writer::span($desc, 'text-muted small')) .
            html_writer::tag('td', self::checkbox(['name' => $name, 'checked' => $checked]),
                ['class' => 'text-center', 'style' => 'width:6rem']));
    }

    /**
     * A move-up / move-down arrow, as a submit button.
     *
     * A submit (not a link) so pressing it saves the rest of the form in the same
     * request - a field can be reordered without losing an in-progress label edit.
     *
     * @param string $token
     * @param bool $up
     * @return string HTML
     */
    protected static function move_button(string $token, bool $up): string {
        global $OUTPUT;
        $icon = $up ? 't/up' : 't/down';
        $alt = get_string($up ? 'moveup' : 'movedown');
        return html_writer::tag('button', $OUTPUT->pix_icon($icon, $alt), [
            'type' => 'submit',
            'name' => $up ? 'moveup' : 'movedown',
            'value' => $token,
            'class' => 'btn btn-link p-0 me-1',
        ]);
    }
}
