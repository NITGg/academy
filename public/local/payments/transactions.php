<?php
/**
 * Who paid for what.
 *
 * Two views from one page:
 *  - ?courseid=N  — one course, for whoever teaches it.
 *  - no courseid  — every payment on the site, for administrators.
 *
 * The summary at /local/payments/report.php answers "how much"; this answers
 * "who", which is the question support actually gets asked.
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHAEXT);
$search = trim(optional_param('q', '', PARAM_TEXT));
$download = optional_param('download', '', PARAM_ALPHA);

$baseparams = array_filter([
    'courseid' => $courseid ?: null,
    'status' => $status ?: null,
    'q' => $search ?: null,
]);
$pageurl = new moodle_url('/local/payments/transactions.php', $baseparams);

if ($courseid) {
    // Course view: a teacher sees their own course, and only through the
    // capability held in that course — never the site-wide one.
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    require_login($course);
    require_capability('local/payments:viewcoursepayments', $context);

    $PAGE->set_url($pageurl);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('coursepayments', 'local_payments'));
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('incourse');
    $heading = get_string('coursepayments', 'local_payments');
} else {
    // Site view: reached from the payments admin category.
    admin_externalpage_setup('local_payments_transactions');
    $context = context_system::instance();
    require_capability('local/payments:viewalltransactions', $context);
    $PAGE->set_url($pageurl);
    $heading = get_string('alltransactions', 'local_payments');
}

$table = new \local_payments\output\transactions_table('local_payments_transactions',
    $courseid, $status, $search);
$table->define_baseurl($pageurl);

// Finance and reconciliation live in spreadsheets, so let the list leave as one.
// The export honours the current filters, which is the whole point of it.
$table->is_downloadable(true);
$table->show_download_buttons_at([TABLE_P_BOTTOM]);

// Set before any output: the export replaces the page entirely.
$filename = 'payments-' . ($courseid ? 'course' . $courseid : 'all') . '-' . date('Ymd');
$table->is_downloading($download, $filename, $heading);

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($heading);

    // ── Filters ─────────────────────────────────────────────────────────────
    $statuses = ['' => get_string('allstatuses', 'local_payments')];
    foreach (\local_payments\status_machine::all_statuses() as $value) {
        $statuses[$value] = $value;
    }

    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/payments/transactions.php'),
        'class' => 'lp-txn-filters mb-3',
    ]);
    if ($courseid) {
        echo html_writer::empty_tag('input',
            ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    }
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'q',
        'value' => $search,
        'class' => 'form-control',
        'placeholder' => get_string('searchpayments', 'local_payments'),
    ]);
    echo html_writer::select($statuses, 'status', $status, false, ['class' => 'form-select']);
    echo html_writer::tag('button', get_string('filter'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    if ($search !== '' || $status !== '') {
        echo html_writer::link(
            new moodle_url('/local/payments/transactions.php',
                $courseid ? ['courseid' => $courseid] : []),
            get_string('reset'),
            ['class' => 'btn btn-outline-secondary']
        );
    }
    echo html_writer::end_tag('form');
}

$table->out(50, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
