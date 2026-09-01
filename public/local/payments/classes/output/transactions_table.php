<?php
namespace local_payments\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * The list of payments behind the summary figures.
 *
 * Serves two audiences from one query: an administrator looking across the whole
 * site, and a teacher looking at one course. The difference is the where-clause
 * and one hidden column, so they do not get to drift apart.
 *
 * Everything renders twice — once as HTML and once as plain text for the
 * spreadsheet export — because a finance export full of badge markup is no use
 * to anyone.
 */
class transactions_table extends \table_sql {

    /** @var int Course this is scoped to, or 0 for the whole site. */
    protected int $courseid;

    /** @var bool Whether the viewer may refund from this list. */
    protected bool $canrefund = false;

    /**
     * @param string $uniqueid
     * @param int $courseid 0 for every course.
     * @param string $status Filter to one status, or '' for all.
     * @param string $search Match on student name, email or order id.
     */
    public function __construct(string $uniqueid, int $courseid = 0, string $status = '',
            string $search = '') {
        parent::__construct($uniqueid);

        $this->courseid = $courseid;

        // Deliberately not called "timecreated": user and course both have a
        // column of that name, so sorting on it produces an ambiguous ORDER BY
        // and the query dies. Every column here has to be unique across the join.
        $columns = ['paidon', 'order_id', 'student', 'amount', 'status', 'providername',
            'payment_method_type', 'invoice'];
        $headers = [
            get_string('date', 'local_payments'),
            get_string('orderid', 'local_payments'),
            get_string('student', 'local_payments'),
            get_string('amount', 'local_payments'),
            get_string('status', 'local_payments'),
            get_string('provider', 'local_payments'),
            get_string('paymentmethod', 'local_payments'),
            get_string('invoice', 'local_payments'),
        ];

        // Refunding is an action, not a report, so the column is only drawn for
        // somebody who is allowed to take it.
        $this->canrefund = \local_payments\refund_policy::enabled()
            && has_capability('local/payments:managerefunds', \context_system::instance());
        if ($this->canrefund) {
            $columns[] = 'refund';
            $headers[] = get_string('refund_column', 'local_payments');
        }

        // The course column is noise when every row is the same course.
        if (!$courseid) {
            array_splice($columns, 3, 0, ['coursename']);
            array_splice($headers, 3, 0, [get_string('course', 'local_payments')]);
        }

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->sortable(true, 'paidon', SORT_DESC);
        // These render from several fields, so there is no single column to sort on.
        $this->no_sorting('student');
        $this->no_sorting('invoice');
        $this->no_sorting('refund');
        $this->collapsible(false);

        $this->build_sql($courseid, $status, $search);
    }

    /**
     * One query for both modes; the filters only ever add conditions.
     *
     * Public and static so a CLI can run exactly what the page runs. "Error
     * reading from database" is all Moodle shows a user, and guessing at a query
     * from that is how an afternoon disappears.
     *
     * @return array [fields, from, where, params]
     */
    public static function build_query(int $courseid = 0, string $status = '',
            string $search = ''): array {
        global $DB;

        $userfields = \core_user\fields::for_name()->get_sql('u')->selects;

        $fields = "t.id, t.order_id, t.amount, t.currency, t.status, t.payment_method_type,
                   t.timecreated AS paidon, t.courseid, t.userid,
                   u.email {$userfields},
                   c.fullname AS coursename,
                   p.display_name AS providername";

        $from = "{local_payments_transactions} t
                 JOIN {user} u ON u.id = t.userid
            LEFT JOIN {course} c ON c.id = t.courseid
            LEFT JOIN {local_payments_providers} p ON p.id = t.provider_id";

        $where = ['1 = 1'];
        $params = [];

        if ($courseid) {
            $where[] = 't.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        if ($status !== '') {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }

        if ($search !== '') {
            // Order id, email, and either name part — the three things somebody
            // actually has to hand when a student says "I paid and got nothing".
            $like = [];
            foreach (['t.order_id', 'u.email', 'u.firstname', 'u.lastname'] as $i => $field) {
                $key = 'search' . $i;
                $like[] = $DB->sql_like($field, ":{$key}", false);
                $params[$key] = '%' . $DB->sql_like_escape($search) . '%';
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
        }

        return [$fields, $from, implode(' AND ', $where), $params];
    }

    protected function build_sql(int $courseid, string $status, string $search): void {
        [$fields, $from, $where, $params] = self::build_query($courseid, $status, $search);

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql("SELECT COUNT(1) FROM {$from} WHERE {$where}", $params);
    }

    public function col_paidon($row) {
        return $this->is_downloading()
            ? userdate($row->paidon, get_string('strftimedatetimeshort', 'langconfig'))
            : userdate($row->paidon);
    }

    public function col_order_id($row) {
        return $this->is_downloading() ? $row->order_id : \html_writer::tag('code', $row->order_id);
    }

    public function col_student($row) {
        $name = fullname($row);
        if ($this->is_downloading()) {
            return $name . ' <' . $row->email . '>';
        }
        return \html_writer::link(
            new \moodle_url('/user/profile.php', ['id' => $row->userid]),
            $name
        ) . \html_writer::tag('div', $row->email, ['class' => 'small text-muted']);
    }

    public function col_coursename($row) {
        $name = $row->coursename ?? '';
        if ($name === '') {
            // A subscription or package is not tied to a course.
            return $this->is_downloading() ? '-' : \html_writer::tag('span', '-', ['class' => 'text-muted']);
        }
        if ($this->is_downloading()) {
            return format_string($name);
        }
        return \html_writer::link(new \moodle_url('/course/view.php', ['id' => $row->courseid]),
            format_string($name));
    }

    public function col_amount($row) {
        return number_format((float) $row->amount, 2) . ' ' . $row->currency;
    }

    public function col_status($row) {
        if ($this->is_downloading()) {
            return $row->status;
        }

        $classes = [
            'completed' => 'text-bg-success',
            'pending' => 'text-bg-warning',
            'failed' => 'text-bg-danger',
            'cancelled' => 'text-bg-danger',
            'expired' => 'text-bg-secondary',
            'refunded' => 'text-bg-info',
            'partially_refunded' => 'text-bg-info',
        ];

        return \html_writer::tag('span', $row->status,
            ['class' => 'badge ' . ($classes[$row->status] ?? 'text-bg-secondary')]);
    }

    /**
     * Named for the SQL alias, not for the concept. A column whose name does not
     * match a field in the select list produces an ORDER BY on a column that
     * does not exist the moment somebody clicks the header to sort by it.
     */
    public function col_providername($row) {
        return $row->providername ?? '-';
    }

    public function col_payment_method_type($row) {
        return $row->payment_method_type ?: '-';
    }

    public function col_invoice($row) {
        $downloadable = in_array($row->status, ['completed', 'refunded', 'partially_refunded'], true);
        if (!$downloadable) {
            return $this->is_downloading() ? '' : \html_writer::tag('span', '—', ['class' => 'text-muted']);
        }
        if ($this->is_downloading()) {
            return '';
        }

        $links = '';
        foreach (['en' => 'invoice_lang_en', 'ar' => 'invoice_lang_ar'] as $lang => $key) {
            $links .= \html_writer::link(
                new \moodle_url('/local/payments/invoice.php',
                    ['transaction_id' => $row->id, 'lang' => $lang]),
                get_string($key, 'local_payments'),
                ['class' => 'btn btn-sm btn-outline-primary']
            );
        }

        return \html_writer::div($links, 'lp-invoice-actions');
    }
    public function col_refund($row) {
        if ($this->is_downloading()) {
            return '';
        }

        if ($row->status !== 'completed') {
            // Already refunded, or never paid: nothing to give back.
            return \html_writer::tag('span', '—', ['class' => 'text-muted']);
        }

        $pending = \local_payments\refund_manager::open_request((int) $row->id);
        if ($pending) {
            // Send it to the queue rather than round it: deciding there records
            // the decision against the request the buyer is waiting on.
            return \html_writer::link(
                new \moodle_url('/local/payments/refund_requests.php'),
                get_string('refund_status_pending', 'local_payments'),
                ['class' => 'badge text-bg-warning text-decoration-none']
            );
        }

        return \html_writer::link(
            new \moodle_url('/local/payments/staff_refund.php', [
                'transaction_id' => $row->id,
                'returnto' => $this->baseurl->out_as_local_url(false),
            ]),
            get_string('refund_now_button', 'local_payments'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }
}
