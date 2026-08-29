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

use core\output\notification;
use html_writer;
use local_profilefields\form\blockip_form;
use local_profilefields\table\blocked_attempts;
use moodle_url;
use tabobject;

defined('MOODLE_INTERNAL') || die();

/**
 * The register reports screen: what the sign-up guard refused, and what it blocks.
 *
 * Two tabs, deliberately next to each other. The first is the evidence - every
 * registration attempt the location rules turned away, with the country the
 * visitor claimed, the country their address resolved to, the reason and the
 * address itself. The second is the lever the evidence points at: the list of
 * addresses that may not create an account at all.
 *
 * The page reports on {@see blocklog} and edits {@see blocklist}; it owns no rules
 * of its own. The rules live where they are enforced - `signup::ip_country_error()`
 * and `signup::validate_ip_allowed()` - so there is one implementation of each and
 * this screen only ever reads their output.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports {

    /** @var string The refused-attempts report. */
    const TAB_ATTEMPTS = 'attempts';

    /** @var string The IP deny list editor. */
    const TAB_BLACKLIST = 'blacklist';

    /** @var int Rows per page on the attempts tab. */
    const PERPAGE = 30;

    /**
     * The valid tab identifiers.
     *
     * @return string[]
     */
    public static function tabs(): array {
        return [self::TAB_ATTEMPTS, self::TAB_BLACKLIST];
    }

    /**
     * A URL back to this page on a given tab.
     *
     * @param string $tab tab id
     * @param array $extra extra query params
     * @return moodle_url
     */
    public static function url(string $tab, array $extra = []): moodle_url {
        return new moodle_url('/local/profilefields/reports.php', ['tab' => $tab] + $extra);
    }

    /**
     * Handle any action the page was asked to perform, then redirect.
     *
     * Runs before output so every action ends in a redirect with a notice - which
     * is also what stops a browser reload from repeating it.
     *
     * @param string $tab the active tab
     * @return void
     */
    public static function process(string $tab): void {
        // "Block this address", from a row of the report.
        $blockip = optional_param('blockip', '', PARAM_RAW_TRIMMED);
        if ($blockip !== '' && confirm_sesskey()) {
            $added = blocklist::add($blockip, get_string('blockipfromreport', 'local_profilefields'));
            redirect(self::url(self::TAB_BLACKLIST),
                get_string($added ? 'blockipadded' : 'blockipduplicate', 'local_profilefields',
                    s($blockip)),
                null, $added ? notification::NOTIFY_SUCCESS : notification::NOTIFY_INFO);
        }

        // "Remove", from a row of the deny list.
        $unblock = optional_param('unblock', 0, PARAM_INT);
        if ($unblock && confirm_sesskey()) {
            blocklist::remove($unblock);
            redirect(self::url(self::TAB_BLACKLIST),
                get_string('blockipremoved', 'local_profilefields'),
                null, notification::NOTIFY_SUCCESS);
        }

        // "Clear the log" - the report is an operational record, not an audit trail,
        // and without this the table only ever grows.
        if (optional_param('clearlog', 0, PARAM_BOOL) && confirm_sesskey()) {
            $count = blocklog::clear();
            redirect(self::url(self::TAB_ATTEMPTS),
                get_string('logcleared', 'local_profilefields', $count),
                null, notification::NOTIFY_SUCCESS);
        }

        // The add form on the deny-list tab.
        if ($tab === self::TAB_BLACKLIST) {
            if ($data = self::block_form()->get_data()) {
                blocklist::add((string) $data->ip, (string) ($data->note ?? ''));
                redirect(self::url(self::TAB_BLACKLIST),
                    get_string('blockipadded', 'local_profilefields', s($data->ip)),
                    null, notification::NOTIFY_SUCCESS);
            }
        }
    }

    /**
     * The one instance of the add-to-deny-list form this request uses.
     *
     * `process()` asks it for submitted data and `render_blacklist()` draws it, and
     * those have to be the same object: validation errors are recorded on the
     * instance that ran `get_data()`, so a second instance would redraw the form
     * looking pristine and silently swallow the message about what was wrong.
     *
     * @return blockip_form
     */
    protected static function block_form(): blockip_form {
        static $form = null;

        if ($form === null) {
            $form = new blockip_form(self::url(self::TAB_BLACKLIST));
        }

        return $form;
    }

    /**
     * Render the tab bar plus the active tab.
     *
     * @param string $tab the active tab
     * @return void
     */
    public static function render(string $tab): void {
        global $OUTPUT;

        echo $OUTPUT->tabtree([
            new tabobject(self::TAB_ATTEMPTS, self::url(self::TAB_ATTEMPTS),
                get_string('tabattempts', 'local_profilefields')),
            new tabobject(self::TAB_BLACKLIST, self::url(self::TAB_BLACKLIST),
                get_string('tabblacklist', 'local_profilefields')),
        ], $tab);

        if ($tab === self::TAB_BLACKLIST) {
            self::render_blacklist();
        } else {
            self::render_attempts();
        }
    }

    // -----------------------------------------------------------------
    // Attempts tab.
    // -----------------------------------------------------------------

    /**
     * Render the refused-attempts report.
     *
     * @return void
     */
    protected static function render_attempts(): void {
        global $OUTPUT;

        $reason = optional_param('reason', '', PARAM_ALPHA);
        $ip = optional_param('ip', '', PARAM_RAW_TRIMMED);

        echo html_writer::tag('p', get_string('tabattempts_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        echo self::guard_status();
        echo self::offenders_hint();
        echo self::attempts_filter($reason, $ip);

        $table = new blocked_attempts(self::url(self::TAB_ATTEMPTS, array_filter([
            'reason' => $reason,
            'ip' => $ip,
        ])), $reason, $ip);
        // No initials bar: it filters on a fullname column this table does not have.
        $table->out(self::PERPAGE, false);

        // Only worth offering once there is something to clear.
        if (blocklog::count_all() > 0) {
            $button = new \single_button(
                self::url(self::TAB_ATTEMPTS, ['clearlog' => 1, 'sesskey' => sesskey()]),
                get_string('clearlog', 'local_profilefields'), 'post');
            $button->add_confirm_action(get_string('clearlogconfirm', 'local_profilefields'));

            echo html_writer::div($OUTPUT->render($button), 'mt-3');
        }
    }

    /**
     * A one-line reminder of which rules are actually switched on.
     *
     * A report that is empty because nothing was refused and a report that is empty
     * because the rule is off look identical, so the page says which it is.
     *
     * @return string HTML
     */
    protected static function guard_status(): string {
        $manageurl = (new moodle_url('/local/profilefields/manage.php',
            ['tab' => page::TAB_REGISTER]))->out();

        if (!manager::ip_match_phone()) {
            return html_writer::div(get_string('guardoff', 'local_profilefields', $manageurl),
                'alert alert-warning');
        }

        $key = manager::block_unresolved_ip() ? 'guardonstrict' : 'guardonlenient';

        return html_writer::div(get_string($key, 'local_profilefields', $manageurl),
            'alert alert-info');
    }

    /**
     * The "these addresses keep trying" hint above the table.
     *
     * @return string HTML
     */
    protected static function offenders_hint(): string {
        $offenders = blocklog::top_offenders();
        if (!$offenders) {
            return '';
        }

        $items = [];
        foreach ($offenders as $ip => $count) {
            $label = html_writer::tag('code', s($ip)) . ' '
                . html_writer::span(get_string('attemptcount', 'local_profilefields', $count),
                    'text-muted small');

            if (!blocklist::listed($ip)) {
                $label .= ' ' . html_writer::link(
                    self::url(self::TAB_ATTEMPTS, ['blockip' => $ip, 'sesskey' => sesskey()]),
                    get_string('blockthisip', 'local_profilefields'),
                    ['class' => 'small ms-2']);
            }

            $items[] = $label;
        }

        return html_writer::div(
            html_writer::tag('strong', get_string('repeatoffenders', 'local_profilefields'))
                . html_writer::alist($items, ['class' => 'mb-0 mt-2']),
            'alert alert-secondary');
    }

    /**
     * The reason / address filter bar.
     *
     * @param string $reason the active reason filter
     * @param string $ip the active address filter
     * @return string HTML
     */
    protected static function attempts_filter(string $reason, string $ip): string {
        $options = ['' => get_string('reasonany', 'local_profilefields')];
        foreach (blocklog::reasons() as $code) {
            $options[$code] = get_string('reason' . $code, 'local_profilefields');
        }

        $out = html_writer::start_tag('form', [
            'method' => 'get',
            'action' => self::url(self::TAB_ATTEMPTS)->out_omit_querystring(),
            'class' => 'row row-cols-lg-auto g-2 align-items-end mb-3',
        ]);
        $out .= html_writer::empty_tag('input',
            ['type' => 'hidden', 'name' => 'tab', 'value' => self::TAB_ATTEMPTS]);

        $out .= html_writer::div(
            html_writer::tag('label', get_string('colreason', 'local_profilefields'),
                ['for' => 'pf-reason', 'class' => 'form-label small mb-1'])
            . html_writer::select($options, 'reason', $reason, false,
                ['id' => 'pf-reason', 'class' => 'form-select']),
            'col');

        $out .= html_writer::div(
            html_writer::tag('label', get_string('colip', 'local_profilefields'),
                ['for' => 'pf-ip', 'class' => 'form-label small mb-1'])
            . html_writer::empty_tag('input', [
                'type' => 'text', 'name' => 'ip', 'id' => 'pf-ip',
                'value' => $ip, 'class' => 'form-control', 'size' => 20,
            ]),
            'col');

        $out .= html_writer::div(
            html_writer::empty_tag('input', [
                'type' => 'submit', 'class' => 'btn btn-secondary',
                'value' => get_string('filter'),
            ]), 'col');

        $out .= html_writer::end_tag('form');

        return $out;
    }

    // -----------------------------------------------------------------
    // Deny-list tab.
    // -----------------------------------------------------------------

    /**
     * Render the deny-list editor.
     *
     * @return void
     */
    protected static function render_blacklist(): void {
        echo html_writer::tag('p', get_string('tabblacklist_intro', 'local_profilefields'),
            ['class' => 'text-muted']);

        self::block_form()->display();

        $entries = blocklist::all();
        if (!$entries) {
            echo html_writer::div(get_string('blocklistempty', 'local_profilefields'),
                'alert alert-info mt-3');
            return;
        }

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable mt-3';
        $table->head = [
            get_string('colip', 'local_profilefields'),
            get_string('colnote', 'local_profilefields'),
            get_string('coladded', 'local_profilefields'),
            get_string('colactions', 'local_profilefields'),
        ];

        foreach ($entries as $entry) {
            $remove = html_writer::link(
                self::url(self::TAB_BLACKLIST, ['unblock' => $entry->id, 'sesskey' => sesskey()]),
                get_string('remove'),
                ['class' => 'btn btn-sm btn-outline-danger']);

            $table->data[] = [
                html_writer::tag('code', s($entry->ip)),
                s($entry->note),
                userdate($entry->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                $remove,
            ];
        }

        echo html_writer::table($table);
    }
}
