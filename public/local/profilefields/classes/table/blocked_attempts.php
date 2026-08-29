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

namespace local_profilefields\table;

use html_writer;
use local_profilefields\blocklist;
use local_profilefields\blocklog;
use moodle_url;
use table_sql;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * The paged, sortable list of refused registration attempts.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blocked_attempts extends table_sql {

    /** @var array<string,string> ISO alpha-2 code => localised country name. */
    protected $countries;

    /** @var string[] the deny-list entries, so a row can offer the right action. */
    protected $blocked;

    /**
     * Build the table for the current filter.
     *
     * @param moodle_url $baseurl the reports page, on the attempts tab
     * @param string $reason filter to one reason code, or '' for all
     * @param string $ip filter to addresses containing this string, or ''
     */
    public function __construct(moodle_url $baseurl, string $reason = '', string $ip = '') {
        global $DB;

        parent::__construct('local_profilefields_attempts');

        $this->countries = get_string_manager()->get_list_of_countries(true);
        $this->blocked = $DB->get_fieldset_select(blocklist::TABLE, 'ip', '');

        $this->define_baseurl($baseurl);
        $this->define_columns(['timecreated', 'ip', 'declared', 'detected', 'reason', 'origin', 'actions']);
        $this->define_headers([
            get_string('colwhen', 'local_profilefields'),
            get_string('colip', 'local_profilefields'),
            get_string('coldeclared', 'local_profilefields'),
            get_string('coldetected', 'local_profilefields'),
            get_string('colreason', 'local_profilefields'),
            get_string('colorigin', 'local_profilefields'),
            get_string('colactions', 'local_profilefields'),
        ]);

        $this->no_sorting('actions');
        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->collapsible(false);
        $this->set_attribute('class', 'generaltable local-profilefields-attempts');

        [$where, $params] = self::filter($reason, $ip);
        $this->set_sql('id, timecreated, ip, declared, detected, reason, origin',
            '{' . blocklog::TABLE . '}', $where, $params);
        $this->set_count_sql('SELECT COUNT(1) FROM {' . blocklog::TABLE . '} WHERE ' . $where, $params);
    }

    /**
     * Build the WHERE clause for the active filters.
     *
     * @param string $reason reason code, or ''
     * @param string $ip substring of the address, or ''
     * @return array [where clause, named params]
     */
    protected static function filter(string $reason, string $ip): array {
        global $DB;

        $where = '1 = 1';
        $params = [];

        if ($reason !== '' && in_array($reason, blocklog::reasons(), true)) {
            $where .= ' AND reason = :reason';
            $params['reason'] = $reason;
        }
        if ($ip !== '') {
            $where .= ' AND ' . $DB->sql_like('ip', ':ip', false);
            $params['ip'] = '%' . $DB->sql_like_escape($ip) . '%';
        }

        return [$where, $params];
    }

    /**
     * When the attempt happened.
     *
     * @param \stdClass $row the row being rendered
     * @return string
     */
    public function col_timecreated($row) {
        return userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * The address, monospaced so a column of them lines up.
     *
     * @param \stdClass $row the row being rendered
     * @return string
     */
    public function col_ip($row) {
        return html_writer::tag('code', s($row->ip));
    }

    /**
     * The country the visitor claimed.
     *
     * @param \stdClass $row the row being rendered
     * @return string
     */
    public function col_declared($row) {
        return $this->country($row->declared);
    }

    /**
     * The country the geo-IP lookup resolved.
     *
     * @param \stdClass $row the row being rendered
     * @return string
     */
    public function col_detected($row) {
        return $this->country($row->detected);
    }

    /**
     * Why the attempt was refused.
     *
     * @param \stdClass $row the row being rendered
     * @return string
     */
    public function col_reason($row) {
        $classes = [
            blocklog::REASON_MISMATCH   => 'badge bg-warning text-dark',
            blocklog::REASON_UNRESOLVED => 'badge bg-secondary',
            blocklog::REASON_BLOCKED    => 'badge bg-danger',
        ];

        $key = 'reason' . $row->reason;
        $label = get_string_manager()->string_exists($key, 'local_profilefields')
            ? get_string($key, 'local_profilefields')
            : s($row->reason);

        return html_writer::span($label, $classes[$row->reason] ?? 'badge bg-secondary');
    }

    /**
     * Which registration entry point the attempt came through.
     *
     * @param \stdClass $row the row being rendered
     * @return string
     */
    public function col_origin($row) {
        $key = 'origin' . $row->origin;

        return get_string_manager()->string_exists($key, 'local_profilefields')
            ? get_string($key, 'local_profilefields')
            : s($row->origin);
    }

    /**
     * The "add this address to the deny list" shortcut.
     *
     * The report exists to decide what to block, so the decision sits one click from
     * the evidence rather than behind a retyped address on the other tab.
     *
     * @param \stdClass $row the row being rendered
     * @return string
     */
    public function col_actions($row) {
        if ($row->ip === '') {
            return '';
        }

        if (in_array($row->ip, $this->blocked, true)) {
            return html_writer::span(get_string('alreadyblocked', 'local_profilefields'), 'text-muted small');
        }

        return html_writer::link(
            new moodle_url($this->baseurl, ['blockip' => $row->ip, 'sesskey' => sesskey()]),
            get_string('blockthisip', 'local_profilefields'),
            ['class' => 'btn btn-sm btn-outline-danger']);
    }

    /**
     * A country code as a readable name, or a dash when there is none.
     *
     * @param string $iso alpha-2 code, or ''
     * @return string
     */
    protected function country(string $iso): string {
        if ($iso === '') {
            return html_writer::span('&mdash;', 'text-muted');
        }

        $name = $this->countries[$iso] ?? $iso;

        return s($name) . ' ' . html_writer::span('(' . s($iso) . ')', 'text-muted small');
    }
}
