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
use local_profilefields\form\faq_form;
use local_profilefields\form\staticpage_form;
use moodle_url;
use tabobject;

defined('MOODLE_INTERNAL') || die();

/**
 * The management screen: register, login, profile, password reset, footer, and the
 * six static pages.
 *
 * The first five tabs are plain tables of toggles that read from, and write back
 * to, the native Moodle settings behind each field (see manager, custom_fields,
 * core_locks and provision). There is deliberately no parallel field store there -
 * the page is a friendlier front end onto controls Moodle already has, spread
 * across several screens, plus the sign-up-form reshaping this plugin adds.
 *
 * The sixth is a second row of tabs, one per static page of AC-4.21, and those are
 * moodleforms rather than toggle tables because their fields are rich text - see
 * {@see \local_profilefields\staticpages} for what each page is made of, and
 * {@see \local_profilefields\form\staticpage_form} for why they are forms.
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

    /** @var string Password-reset tab. */
    const TAB_PASSWORDRESET = 'passwordreset';

    /** @var string Site-footer tab. */
    const TAB_FOOTER = 'footer';

    /** @var string Prefix of the six static-page tabs - 'page' . slug (AC-4.21). */
    const TAB_PAGE_PREFIX = 'page';

    /**
     * The valid tab identifiers.
     *
     * @return string[]
     */
    public static function tabs(): array {
        return array_merge(
            [self::TAB_REGISTER, self::TAB_LOGIN, self::TAB_PROFILE,
                self::TAB_PASSWORDRESET, self::TAB_FOOTER],
            array_map(static function (string $slug): string {
                return self::TAB_PAGE_PREFIX . $slug;
            }, staticpages::slugs())
        );
    }

    /**
     * The static page a tab id names, if it names one.
     *
     * @param string $tab
     * @return string slug, or '' when this is not a static-page tab
     */
    public static function tab_slug(string $tab): string {
        if (strpos($tab, self::TAB_PAGE_PREFIX) !== 0) {
            return '';
        }

        $slug = substr($tab, strlen(self::TAB_PAGE_PREFIX));

        return staticpages::exists($slug) ? $slug : '';
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
        // The static pages are moodleforms, so they carry their own submission and
        // their own sesskey. Handled first and on their own terms - the toggle
        // machinery below is for the hand-built tabs.
        $slug = self::tab_slug($tab);
        if ($slug !== '') {
            self::process_staticpage($slug, $tab);
            return;
        }

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
            // Anything a tab could keep only partly. Everything saved, but the
            // administrator is told what did not stick instead of watching a row
            // disappear with a green "changes saved" over it.
            $problems = [];

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
                case self::TAB_PASSWORDRESET:
                    self::save_passwordreset();
                    break;
                case self::TAB_FOOTER:
                    $problems = footer::save();
                    break;
            }

            if (!empty($problems)) {
                redirect(self::url($tab), implode(' ', $problems),
                    null, \core\output\notification::NOTIFY_WARNING);
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
            new tabobject(self::TAB_PASSWORDRESET, self::url(self::TAB_PASSWORDRESET),
                get_string('tabpasswordreset', 'local_profilefields')),
            new tabobject(self::TAB_FOOTER, self::url(self::TAB_FOOTER),
                get_string('tabfooter', 'local_profilefields')),
            self::pages_tab($tab),
        ];
        echo $OUTPUT->tabtree($rows, $tab);

        $slug = self::tab_slug($tab);
        if ($slug !== '') {
            self::render_staticpage($slug);
            return;
        }

        switch ($tab) {
            case self::TAB_LOGIN:
                self::render_login();
                break;
            case self::TAB_PROFILE:
                self::render_profile();
                break;
            case self::TAB_PASSWORDRESET:
                self::render_passwordreset();
                break;
            case self::TAB_FOOTER:
                self::render_footer();
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
        $body = self::username_row() . self::country_from_phone_row() . self::completion_gate_row();

        $last = count($tokens) - 1;
        foreach ($tokens as $i => $token) {
            $body .= self::register_row($token, $custom, $i === 0, $i === $last);
        }

        echo html_writer::tag('table', $head . $body, ['class' => 'generaltable w-100']);

        // Sections that live under the table but inside the same form and Save.
        echo self::ipmatch_section();
        echo self::terms_section();

        // Email confirmation belongs to the registration journey, not the login
        // one: it is the step between submitting this form and being able to use
        // the account at all. It first lived on the Login tab, where nobody
        // looking for it thought to check - and a setting that cannot be found is
        // barely better than a setting that does not exist.
        echo self::verification_section();

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
     * The "hold incomplete accounts" gate, as a table row.
     *
     * Sits with the other behaviour toggles because that is what it is: it does
     * not add or remove a field, it decides whether the fields above are enforced
     * on accounts that never saw this form (an OAuth2 login).
     *
     * @return string HTML
     */
    protected static function completion_gate_row(): string {
        return html_writer::tag('tr',
            html_writer::tag('td', '') .
            html_writer::tag('td',
                html_writer::span(get_string('completiongate', 'local_profilefields'), 'fw-semibold') . ' ' .
                self::badge('special') . html_writer::div(
                    get_string('completiongate_desc', 'local_profilefields'), 'text-muted small')) .
            html_writer::tag('td',
                self::yesno_select('completiongate', completion::enabled()), ['colspan' => 3]),
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

        // What the check does when it has no location to check against. Kept as a
        // separate switch rather than folded into the one above, because "block a
        // country mismatch" and "block an address we cannot place at all" refuse
        // very different populations - the second one also catches anyone on a LAN
        // address, which on a misconfigured reverse proxy is everybody.
        $out .= html_writer::tag('div',
            self::checkbox([
                'name' => 'blockunresolvedip',
                'checked' => manager::block_unresolved_ip(),
            ]) . ' ' .
            html_writer::tag('label', get_string('blockunresolvedip', 'local_profilefields'),
                ['class' => 'ms-2 mb-0']),
            ['class' => 'form-check form-switch mt-3']);
        $out .= html_writer::tag('p', get_string('blockunresolvedip_desc', 'local_profilefields'),
            ['class' => 'text-muted small mt-1']);

        // Where the refusals this section produces end up.
        $out .= html_writer::tag('p', html_writer::link(
            new moodle_url('/local/profilefields/reports.php'),
            get_string('seereports', 'local_profilefields')), ['class' => 'small']);

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

        $docs = policies::signup_document_records();
        if (!empty($docs)) {
            $items = '';
            foreach ($docs as $doc) {
                $items .= html_writer::tag('li', html_writer::link($doc->url, s($doc->name), ['target' => '_blank']));
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
        set_config(completion::SETTING, optional_param('completiongate', 0, PARAM_BOOL) ? 1 : 0, manager::COMPONENT);
        set_config('ipmatchphone', optional_param('ipmatchphone', 0, PARAM_BOOL) ? 1 : 0, manager::COMPONENT);
        set_config('blockunresolvedip', optional_param('blockunresolvedip', 0, PARAM_BOOL) ? 1 : 0,
            manager::COMPONENT);

        // Email confirmation (AC-4.2.2, AC-4.2.3, AC-4.2.10) - saved here because
        // the section is drawn on this tab, beside the rest of registration.
        set_config('linkttlhours', self::posted_number('linkttlhours', 1, 168, 24), manager::COMPONENT);
        set_config('resendcooldown', self::posted_number('resendcooldown', 0, 600, 60), manager::COMPONENT);
        set_config('resendmax', self::posted_number('resendmax', 1, 50, 5), manager::COMPONENT);
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

        echo self::security_section();
        echo self::rememberme_section();

        echo html_writer::tag('div',
            html_writer::tag('button', get_string('savechanges'),
                ['type' => 'submit', 'class' => 'btn btn-primary']),
            ['class' => 'mt-3']);
        echo html_writer::end_tag('form');
    }

    /**
     * Lock-out, session length and the submit-button gate.
     *
     * The first three write straight into core settings that live under Site
     * administration > Security > Site security settings. They are repeated here
     * because an administrator asking "what governs the login screen?" should not
     * have to know that half the answer is in our page and half in Moodle's - and
     * because AC-4.3.2 and AC-4.3.5 name values (5 attempts, 15 minutes, 24 hours)
     * that someone has to be able to check without hunting.
     *
     * Nothing is duplicated: these read and write $CFG directly, so a change made
     * in Moodle's own page shows up here and the reverse, with no second copy to
     * fall out of step.
     *
     * @return string HTML
     */
    protected static function security_section(): string {
        global $CFG;

        $rows = [
            self::number_row('lockoutthreshold', (int) ($CFG->lockoutthreshold ?? 0),
                get_string('lockoutthreshold', 'local_profilefields'),
                get_string('lockoutthreshold_desc', 'local_profilefields'), 0, 20),
            self::number_row('lockoutminutes', (int) round(($CFG->lockoutduration ?? 1800) / MINSECS),
                get_string('lockoutduration', 'local_profilefields'),
                get_string('lockoutduration_desc', 'local_profilefields'), 1, 1440),
            self::number_row('sessionhours', (int) round(($CFG->sessiontimeout ?? 8 * HOURSECS) / HOURSECS),
                get_string('sessiontimeoutlabel', 'local_profilefields'),
                get_string('sessiontimeout_desc', 'local_profilefields'), 1, 720),
            self::switch_row('gatebuttons', (bool) get_config(manager::COMPONENT, 'gatebuttons'),
                get_string('gatebuttons', 'local_profilefields'),
                get_string('gatebuttons_desc', 'local_profilefields')),
        ];

        return html_writer::tag('h3', get_string('securityheading', 'local_profilefields'), ['class' => 'mt-4 h5']) .
            html_writer::tag('p', get_string('securityintro', 'local_profilefields'), ['class' => 'text-muted']) .
            html_writer::tag('table', implode('', $rows), ['class' => 'generaltable w-100']);
    }

    /**
     * The "Remember me" token settings (AC-4.3.5).
     *
     * @return string HTML
     */
    protected static function rememberme_section(): string {
        $rows = [
            self::switch_row('remembermeenabled', (bool) get_config(manager::COMPONENT, 'remembermeenabled'),
                get_string('remembermeenabled', 'local_profilefields'),
                get_string('remembermeenabled_desc', 'local_profilefields')),
            self::number_row('remembermedays', (int) (get_config(manager::COMPONENT, 'remembermedays') ?: 30),
                get_string('remembermedays', 'local_profilefields'),
                get_string('remembermedays_desc', 'local_profilefields'), 1, 365),
        ];

        return html_writer::tag('h3', get_string('rememberme', 'local_profilefields'), ['class' => 'mt-4 h5']) .
            html_writer::tag('table', implode('', $rows), ['class' => 'generaltable w-100']);
    }

    /**
     * Confirmation-link lifetime and resend limits (AC-4.2.2, 4.2.3, 4.2.10).
     *
     * @return string HTML
     */
    protected static function verification_section(): string {
        $rows = [
            self::number_row('linkttlhours', (int) (get_config(manager::COMPONENT, 'linkttlhours') ?: 24),
                get_string('linkttl', 'local_profilefields'),
                get_string('linkttl_desc', 'local_profilefields'), 1, 168),
            self::number_row('resendcooldown', (int) (get_config(manager::COMPONENT, 'resendcooldown') ?: 60),
                get_string('resendcooldown', 'local_profilefields'),
                get_string('resendcooldown_desc', 'local_profilefields'), 0, 600),
            self::number_row('resendmax', (int) (get_config(manager::COMPONENT, 'resendmax') ?: 5),
                get_string('resendmax', 'local_profilefields'),
                get_string('resendmax_desc', 'local_profilefields'), 1, 50),
        ];

        return html_writer::tag('h3', get_string('verifyheading', 'local_profilefields'), ['class' => 'mt-4 h5']) .
            html_writer::tag('p', get_string('verifyintro', 'local_profilefields'), ['class' => 'text-muted']) .
            html_writer::tag('table', implode('', $rows), ['class' => 'generaltable w-100']);
    }

    // -----------------------------------------------------------------
    // Password reset tab.
    // -----------------------------------------------------------------

    /**
     * Is the plugin that owns these limits installed?
     *
     * local_profilefields has to keep working on a site that does not run
     * local_academy, so the tab explains itself rather than fataling.
     *
     * @return bool
     */
    protected static function passwordreset_available(): bool {
        return class_exists('\local_academy\password_reset_manager');
    }

    /**
     * Render the password-reset tab (AC-4.4.4, AC-4.4.5).
     *
     * The numbers belong to local_academy, which owns the reset flow; this page
     * only edits them, the same way the login tab edits core's lock-out settings
     * rather than keeping a second copy. The rows are built from
     * password_reset_manager::limits(), so the ranges shown here and the ranges
     * enforced on save are by construction the same ones.
     *
     * @return void
     */
    protected static function render_passwordreset(): void {
        if (!self::passwordreset_available()) {
            echo html_writer::div(
                get_string('resetnoacademy', 'local_profilefields'), 'alert alert-info');
            return;
        }

        $limits = \local_academy\password_reset_manager::limits();

        echo html_writer::tag('p', get_string('tabpasswordreset_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => self::url(self::TAB_PASSWORDRESET)->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

        $rows = [];
        foreach ($limits as $name => $meta) {
            $value = \local_academy\password_reset_manager::limit_value($name);
            $isminutes = ($meta['unit'] === 'minutes');

            $rows[] = self::number_row(
                $name,
                $isminutes ? (int) round($value / MINSECS) : $value,
                get_string('reset_' . $name, 'local_profilefields'),
                get_string('reset_' . $name . '_desc', 'local_profilefields'),
                $isminutes ? (int) ceil($meta['min'] / MINSECS) : $meta['min'],
                $isminutes ? (int) floor($meta['max'] / MINSECS) : $meta['max']
            );
        }

        echo html_writer::tag('table', implode('', $rows), ['class' => 'generaltable w-100']);

        // Lock-out is the other thing an administrator reaches for on this screen
        // and is a different mechanism entirely, so say so rather than let someone
        // change the wrong number.
        echo html_writer::div(get_string('resetnotlockout', 'local_profilefields'),
            'alert alert-info mt-3');

        echo html_writer::tag('div',
            html_writer::tag('button', get_string('savechanges'),
                ['type' => 'submit', 'class' => 'btn btn-primary']),
            ['class' => 'mt-3']);
        echo html_writer::end_tag('form');
    }

    /**
     * Save the password-reset tab back into local_academy's settings.
     *
     * set_limit() clamps, so a hand-made POST cannot set a limit of zero and turn
     * password reset off for the whole site.
     *
     * @return void
     */
    protected static function save_passwordreset(): void {
        if (!self::passwordreset_available()) {
            return;
        }

        foreach (\local_academy\password_reset_manager::limits() as $name => $meta) {
            $isminutes = ($meta['unit'] === 'minutes');

            $min = $isminutes ? (int) ceil($meta['min'] / MINSECS) : $meta['min'];
            $max = $isminutes ? (int) floor($meta['max'] / MINSECS) : $meta['max'];
            $default = $isminutes ? (int) round($meta['default'] / MINSECS) : $meta['default'];

            $posted = self::posted_number($name, $min, $max, $default);

            \local_academy\password_reset_manager::set_limit(
                $name, $isminutes ? $posted * MINSECS : $posted);
        }
    }

    /**
     * A labelled whole-number box, shaped like the switch rows beside it.
     *
     * @param string $name form field name
     * @param int $value current value
     * @param string $label
     * @param string $desc the sentence under the label
     * @param int $min lowest accepted value
     * @param int $max highest accepted value
     * @return string HTML for one table row
     */
    protected static function number_row(string $name, int $value, string $label, string $desc,
            int $min, int $max): string {
        $input = html_writer::empty_tag('input', [
            'type' => 'number', 'name' => $name, 'value' => $value,
            'min' => $min, 'max' => $max, 'class' => 'form-control', 'style' => 'width:6rem',
        ]);

        return html_writer::tag('tr',
            html_writer::tag('td',
                html_writer::tag('div', html_writer::span($label, 'fw-semibold'), []) .
                html_writer::span($desc, 'text-muted small')) .
            html_writer::tag('td', $input, ['class' => 'text-center', 'style' => 'width:6rem']));
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

        // Core settings, written unprefixed so Moodle's own security page and this
        // one are reading and writing the same value rather than two copies.
        // Stored in seconds; shown in the unit an administrator thinks in.
        set_config('lockoutthreshold', self::posted_number('lockoutthreshold', 0, 20, 5));
        set_config('lockoutduration', self::posted_number('lockoutminutes', 1, 1440, 15) * MINSECS);
        set_config('sessiontimeout', self::posted_number('sessionhours', 1, 720, 24) * HOURSECS);

        // Ours.
        set_config('gatebuttons', optional_param('gatebuttons', 0, PARAM_BOOL) ? 1 : 0, manager::COMPONENT);
        set_config('remembermeenabled', optional_param('remembermeenabled', 0, PARAM_BOOL) ? 1 : 0,
            manager::COMPONENT);
        set_config('remembermedays', self::posted_number('remembermedays', 1, 365, 30), manager::COMPONENT);
    }

    /**
     * A posted whole number, clamped to the range the box advertised.
     *
     * The browser enforces `min`/`max` on a number input, but a hand-made POST
     * does not have to, and a lock-out duration of zero or a session length of
     * minus one would be a live outage rather than a bad setting.
     *
     * @param string $name form field name
     * @param int $min lowest accepted value
     * @param int $max highest accepted value
     * @param int $default used when nothing usable was posted
     * @return int
     */
    protected static function posted_number(string $name, int $min, int $max, int $default): int {
        $value = optional_param($name, null, PARAM_INT);

        if ($value === null) {
            return $default;
        }

        return max($min, min($max, $value));
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
    // Footer tab (AC-4.7.13).
    // -----------------------------------------------------------------

    /**
     * Render the footer tab.
     *
     * The footer is drawn by theme_nit; what it says is edited here. It sits on
     * this page rather than in the theme settings because it is content - an
     * address, a phone number, six links - and content an administrator changes
     * belongs with the other content controls, not behind Appearance.
     *
     * Every piece of prose gets one box per language, side by side, rather than a
     * single box the administrator has to fill with {mlang} markup: the markup is
     * invisible to anyone who has not been told about it, and renders as literal
     * text the moment the multilang filter is off.
     *
     * @return void
     */
    protected static function render_footer(): void {
        echo html_writer::tag('p', get_string('tabfooter_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => self::url(self::TAB_FOOTER)->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

        echo html_writer::tag('table',
            self::switch_row('footerenabled', footer::enabled(),
                get_string('footerenabled', 'local_profilefields'),
                get_string('footerenabled_desc', 'local_profilefields')),
            ['class' => 'generaltable w-100']);

        echo self::footer_contact_section();
        echo self::footer_links_section();
        echo self::footer_social_section();
        echo self::footer_copyright_section();

        echo html_writer::tag('div',
            html_writer::tag('button', get_string('savechanges'),
                ['type' => 'submit', 'class' => 'btn btn-primary']),
            ['class' => 'mt-3']);
        echo html_writer::end_tag('form');
    }

    /**
     * The contact column: its heading and the four icon rows under it.
     *
     * @return string HTML
     */
    protected static function footer_contact_section(): string {
        $rows = self::footer_lang_row('contactheading',
            get_string('footercontactheading', 'local_profilefields'),
            get_string('footercontactheading_desc', 'local_profilefields'));

        foreach (array_keys(footer::contactrows()) as $name) {
            $label = get_string('footer' . $name, 'local_profilefields');
            $desc = get_string('footer' . $name . '_desc', 'local_profilefields');

            $rows .= in_array($name, footer::translatable(), true)
                ? self::footer_lang_row($name, $label, $desc, $name === 'address')
                : self::footer_plain_row('footer' . $name, footer::get($name), $label, $desc);
        }

        return self::footer_heading('footercontactheadingsection') .
            html_writer::tag('table', $rows, ['class' => 'generaltable w-100']);
    }

    /**
     * The two link columns - between them, the six static pages of AC-4.21.1.
     *
     * @return string HTML
     */
    protected static function footer_links_section(): string {
        $out = self::footer_heading('footerlinkssection') .
            html_writer::tag('p', get_string('footerlinkssection_desc', 'local_profilefields'),
                ['class' => 'text-muted']);

        foreach (['col2', 'col3'] as $col) {
            $out .= html_writer::tag('table',
                self::footer_lang_row($col . 'heading',
                    get_string('footer' . $col . 'heading', 'local_profilefields'),
                    get_string('footercolheading_desc', 'local_profilefields')),
                ['class' => 'generaltable w-100 mt-3']);
            $out .= self::footer_link_table($col);
        }

        return $out;
    }

    /**
     * One column's links, as a table of rows rather than a text area.
     *
     * The rows are plain inputs and the spare ones at the bottom are always
     * rendered, so adding a link needs no JavaScript and no knowledge of a
     * `Label|url` syntax - which is what the text area it replaces demanded, and
     * what made a typo look like the field refusing to save.
     *
     * @param string $col 'col2' or 'col3'
     * @return string HTML
     */
    protected static function footer_link_table(string $col): string {
        $links = footer::links($col);

        $head = html_writer::tag('tr',
            html_writer::tag('th', '', ['style' => 'width:3rem']) .
            html_writer::tag('th', get_string('footerlinkurl', 'local_profilefields')) .
            implode('', array_map(static function (string $lang): string {
                return html_writer::tag('th',
                    get_string('footerlinklabel', 'local_profilefields',
                        get_string('lang' . $lang, 'local_profilefields')));
            }, footer::langs())));

        $body = '';
        for ($i = 0; $i < footer::MAXLINKS; $i++) {
            $link = $links[$i] ?? null;

            $cells = html_writer::tag('td',
                html_writer::span($i + 1, 'text-muted small'), ['class' => 'align-middle']);
            $cells .= html_writer::tag('td', html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'footer' . $col . 'url_' . $i,
                'value' => $link['url'] ?? '',
                'class' => 'form-control',
                'dir' => 'ltr',
                'placeholder' => '/course/',
            ]));

            foreach (footer::langs() as $lang) {
                $cells .= html_writer::tag('td', html_writer::empty_tag('input', [
                    'type' => 'text',
                    'name' => 'footer' . $col . 'label_' . $i . '_' . $lang,
                    'value' => $link[$lang] ?? '',
                    'class' => 'form-control',
                    'dir' => $lang === 'ar' ? 'rtl' : 'ltr',
                ]));
            }

            // Spare rows are dimmed so it is obvious where the list ends and
            // where there is room to add to it.
            $body .= html_writer::tag('tr', $cells, $link === null ? ['class' => 'opacity-75'] : []);
        }

        return html_writer::tag('table', $head . $body, ['class' => 'generaltable w-100']);
    }

    /**
     * The social media links.
     *
     * @return string HTML
     */
    protected static function footer_social_section(): string {
        $rows = '';
        foreach (array_keys(footer::networks()) as $network) {
            $rows .= self::footer_plain_row('footersocial' . $network, footer::get('social' . $network),
                get_string('footersocial' . $network, 'local_profilefields'),
                get_string('footersocialurl_desc', 'local_profilefields'), 'ltr');
        }

        return self::footer_heading('footersocialsection') .
            html_writer::tag('table', $rows, ['class' => 'generaltable w-100']);
    }

    /**
     * The copyright year and sentence.
     *
     * @return string HTML
     */
    protected static function footer_copyright_section(): string {
        $rows = self::footer_plain_row('footercopyrightyear', footer::get('copyrightyear'),
            get_string('footercopyrightyear', 'local_profilefields'),
            get_string('footercopyrightyear_desc', 'local_profilefields'), 'ltr');

        $rows .= self::footer_lang_row('copyright',
            get_string('footercopyright', 'local_profilefields'),
            get_string('footercopyright_desc', 'local_profilefields'));

        // What the sentence actually comes out as once {year} is filled in - the
        // placeholder is the one part of this tab that is not what you see.
        $rows .= html_writer::tag('tr',
            html_writer::tag('td', html_writer::span(
                get_string('footercopyrightpreview', 'local_profilefields'), 'fw-semibold')) .
            html_writer::tag('td', html_writer::span(s(footer::copyright()), 'text-muted')));

        return self::footer_heading('footercopyrightsection') .
            html_writer::tag('table', $rows, ['class' => 'generaltable w-100']);
    }

    /**
     * A section heading on the footer tab.
     *
     * @param string $key language string key
     * @return string HTML
     */
    protected static function footer_heading(string $key): string {
        return html_writer::tag('h3', get_string($key, 'local_profilefields'), ['class' => 'mt-4 h5']);
    }

    /**
     * A translatable field: one labelled box per language, side by side.
     *
     * @param string $name config suffix, without the `footer` prefix or language
     * @param string $label
     * @param string $desc
     * @param bool $multiline render text areas instead of single-line inputs
     * @return string HTML
     */
    protected static function footer_lang_row(string $name, string $label, string $desc,
            bool $multiline = false): string {

        $boxes = '';
        foreach (footer::langs() as $lang) {
            $id = 'id_footer' . $name . '_' . $lang;
            $attrs = [
                'name' => 'footer' . $name . '_' . $lang,
                'id' => $id,
                'class' => 'form-control',
                'dir' => $lang === 'ar' ? 'rtl' : 'ltr',
            ];

            $field = $multiline
                ? html_writer::tag('textarea', s(footer::raw($name, $lang)), $attrs + ['rows' => 3])
                : html_writer::empty_tag('input',
                    ['type' => 'text', 'value' => footer::raw($name, $lang)] + $attrs);

            $boxes .= html_writer::div(
                html_writer::tag('label', get_string('lang' . $lang, 'local_profilefields'),
                    ['for' => $id, 'class' => 'form-label small text-muted mb-1']) . $field,
                'flex-fill');
        }

        return html_writer::tag('tr',
            html_writer::tag('td',
                html_writer::span($label, 'fw-semibold') .
                html_writer::div($desc, 'text-muted small'),
                ['style' => 'width:28%']) .
            html_writer::tag('td', html_writer::div($boxes, 'd-flex flex-wrap gap-3')));
    }

    /**
     * A field that is the same in every language: one box.
     *
     * @param string $name form field name (the full config name)
     * @param string $value
     * @param string $label
     * @param string $desc
     * @param string $dir text direction for the input
     * @return string HTML
     */
    protected static function footer_plain_row(string $name, string $value, string $label,
            string $desc, string $dir = 'auto'): string {

        return html_writer::tag('tr',
            html_writer::tag('td',
                html_writer::tag('label', $label, ['for' => 'id_' . $name, 'class' => 'fw-semibold mb-0']) .
                html_writer::div($desc, 'text-muted small'),
                ['style' => 'width:28%']) .
            html_writer::tag('td', html_writer::empty_tag('input', [
                'type' => 'text', 'name' => $name, 'id' => 'id_' . $name,
                'value' => $value, 'class' => 'form-control', 'dir' => $dir,
            ])));
    }

    // -----------------------------------------------------------------
    // Static pages (AC-4.21).
    // -----------------------------------------------------------------

    /**
     * The "Static pages" tab, with one sub-tab per page.
     *
     * Six pages would be six more tabs on a bar that already has five, and the
     * result reads as eleven unrelated screens. A second row instead: one tab that
     * says these six belong together, and inside it the page being edited.
     *
     * @return tabobject
     */
    protected static function pages_tab(string $tab): tabobject {
        $first = staticpages::slugs()[0];

        $parent = new tabobject('pages', self::url(self::TAB_PAGE_PREFIX . $first),
            get_string('tabpages', 'local_profilefields'));

        // The subtree is attached only while a page is being edited. tabtree draws
        // the second row for any top-level tab that has one, selected or not (see
        // tabtree::export_for_template), so attaching it unconditionally would put
        // six page tabs under the Register tab as well.
        if (self::tab_slug($tab) === '') {
            return $parent;
        }

        $parent->subtree = array_map(static function (string $slug): tabobject {
            return new tabobject(self::TAB_PAGE_PREFIX . $slug,
                self::url(self::TAB_PAGE_PREFIX . $slug),
                staticpages::default_title($slug, current_language()));
        }, staticpages::slugs());

        return $parent;
    }

    /**
     * Handle a submission on one of the static-page tabs.
     *
     * Two forms can be on the FAQ tab at once - the page itself and its list of
     * questions - so each is asked whether it was the one submitted. moodleform's
     * `_qf__<formname>` marker keeps them apart, which is why the question list is
     * its own class rather than more elements on the page form: the two are saved
     * independently, and an editor full of Arabic prose should not be revalidated
     * because somebody added a question.
     *
     * @param string $slug the page being edited
     * @param string $tab the tab id, for the redirect
     * @return void
     */
    protected static function process_staticpage(string $slug, string $tab): void {
        $pageform = self::staticpage_form($slug);
        if ($data = $pageform->get_data()) {
            staticpage_form::save($data);
            redirect(self::url($tab), get_string('changessaved'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }

        if (staticpages::kind($slug) !== staticpages::KIND_FAQ) {
            return;
        }

        $faqform = self::faq_form();
        if ($data = $faqform->get_data()) {
            faq::save_all(faq_form::extract($data));
            redirect(self::url($tab), get_string('changessaved'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    /**
     * The page form for this request - the same instance every time it is asked for.
     *
     * process() and render() have to be looking at one object. A second instance
     * would be built from the same POST and hold the same typed values, but not the
     * validation errors the first one found: a submission that failed validation
     * would redraw as if nothing were wrong, with no message beside the field.
     *
     * @param string $slug
     * @return staticpage_form
     */
    protected static function staticpage_form(string $slug): staticpage_form {
        static $forms = [];

        // Keyed by slug, not a single instance: the six pages are six different
        // forms, and one request that touched two of them would otherwise get the
        // first one back for both.
        if (!isset($forms[$slug])) {
            $forms[$slug] = new staticpage_form(
                self::url(self::TAB_PAGE_PREFIX . $slug)->out(false), ['slug' => $slug]);
        }

        return $forms[$slug];
    }

    /**
     * The FAQ list form for this request, for the same reason.
     *
     * @return faq_form
     */
    protected static function faq_form(): faq_form {
        static $form = null;

        if ($form === null) {
            $form = new faq_form(
                self::url(self::TAB_PAGE_PREFIX . 'faq')->out(false), ['items' => faq::all()]);
        }

        return $form;
    }

    /**
     * Render one static-page tab.
     *
     * @param string $slug
     * @return void
     */
    protected static function render_staticpage(string $slug): void {
        global $OUTPUT;

        echo html_writer::tag('p', get_string('tabpages_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        echo self::staticpage_address($slug);

        // load() sets the stored values as the form's defaults. On a submission
        // that failed validation QuickForm prefers what was posted, so nothing the
        // administrator typed is replaced by what is in the database.
        $pageform = self::staticpage_form($slug);
        $pageform->load($slug);
        $pageform->display();

        if (staticpages::kind($slug) !== staticpages::KIND_FAQ) {
            return;
        }

        echo $OUTPUT->heading(get_string('faqheading', 'local_profilefields'), 3, 'mt-5 h4');
        echo html_writer::tag('p', get_string('faqheading_desc', 'local_profilefields'),
            ['class' => 'text-muted']);

        $faqform = self::faq_form();
        $faqform->load(faq::all());
        $faqform->display();
    }

    /**
     * Where the page being edited can be seen, and whether anyone can see it.
     *
     * The address is on the tab because it is the thing an administrator needs
     * next: to check the page, and to paste the link into the footer, a course
     * summary or an email.
     *
     * @param string $slug
     * @return string HTML
     */
    protected static function staticpage_address(string $slug): string {
        $url = staticpages::url($slug);

        $out = html_writer::link($url, $url->out(false),
            ['class' => 'fw-semibold', 'target' => '_blank', 'rel' => 'noopener']);
        $out = get_string('staticpageaddress', 'local_profilefields') . ' ' . $out;

        if (!staticpages::enabled($slug)) {
            $out .= html_writer::div(get_string('staticpageoffnotice', 'local_profilefields'), 'small mt-1');
        }

        return html_writer::div($out, 'alert alert-info');
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
        // Delegated: the account screen renders the same fields under the same
        // names, so the rename an administrator types here has to reach it too.
        return manager::core_label($name);
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
