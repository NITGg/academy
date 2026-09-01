<?php
/**
 * The refund policy for one course or one subscription plan.
 *
 * An override, not a copy: with nothing set here the item follows the site
 * settings for its type, and clearing the override puts it back. That matters
 * because the common case is "everything the same, except this one".
 */
require_once(__DIR__ . '/../../config.php');

$itemtype = required_param('itemtype', PARAM_ALPHAEXT);
$itemid = required_param('itemid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();

// A course policy belongs to whoever runs the course; anything else is a
// site-level decision.
if ($itemtype === 'course') {
    $course = $DB->get_record('course', ['id' => $itemid], '*', MUST_EXIST);
    $context = context_course::instance($itemid);
    require_capability('local/payments:managecoursepricing', $context);
    $itemname = format_string($course->fullname);
    $returnurl = new moodle_url('/local/payments/course_pricing.php', ['courseid' => $itemid]);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('incourse');
} else {
    $context = context_system::instance();
    require_capability('local/payments:managerefunds', $context);
    $itemname = '';
    if ($itemtype === 'subscription') {
        $itemname = (string) $DB->get_field('nit_subscription', 'name', ['id' => $itemid]);
    }
    $itemname = $itemname !== '' ? format_string($itemname) : ($itemtype . ' #' . $itemid);
    $returnurl = new moodle_url('/local/nit_subscriptions/manage_subscriptions.php');
    $PAGE->set_pagelayout('admin');
}

$pageurl = new moodle_url('/local/payments/refund_rule.php',
    ['itemtype' => $itemtype, 'itemid' => $itemid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('refund_rule_title', 'local_payments'));

$existing = $DB->get_record('local_payments_refund_rules',
    ['itemtype' => $itemtype, 'itemid' => $itemid]);

if ($action === 'clear' && confirm_sesskey()) {
    $DB->delete_records('local_payments_refund_rules',
        ['itemtype' => $itemtype, 'itemid' => $itemid]);
    redirect($pageurl, get_string('refund_rule_cleared', 'local_payments'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'save' && confirm_sesskey()) {
    $record = (object) [
        'itemtype' => $itemtype,
        'itemid' => $itemid,
        'hours' => max(0, optional_param('hours', 0, PARAM_INT)),
        'feetype' => optional_param('feetype', \local_payments\refund_policy::FEE_PERCENT, PARAM_ALPHA)
            === \local_payments\refund_policy::FEE_FIXED
                ? \local_payments\refund_policy::FEE_FIXED
                : \local_payments\refund_policy::FEE_PERCENT,
        'feevalue' => max(0, optional_param('feevalue', 0, PARAM_FLOAT)),
        'feecurrency' => strtoupper(optional_param('feecurrency', '', PARAM_ALPHA)),
        'timemodified' => time(),
    ];

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_payments_refund_rules', $record);
    } else {
        $DB->insert_record('local_payments_refund_rules', $record);
    }

    redirect($pageurl, get_string('refund_rule_saved', 'local_payments'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// What is in force right now, so the page can show what clearing would restore.
$sitepolicy = \local_payments\refund_policy::for_item_type($itemtype);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('refund_rule_heading', 'local_payments', $itemname));

if (!\local_payments\refund_policy::enabled()) {
    echo $OUTPUT->notification(get_string('refund_rule_offsitewide', 'local_payments'), 'warning');
}

$describe = static function (object $p): string {
    $fee = $p->feevalue <= 0
        ? get_string('refund_rule_nofee', 'local_payments')
        : ($p->feetype === \local_payments\refund_policy::FEE_PERCENT
            ? format_float($p->feevalue, 2, true, true) . '%'
            // A flat fee is meaningless without its currency, which is the whole
            // reason the field exists.
            : format_float($p->feevalue, 2, true, true) . ' ' . $p->feecurrency);
    return get_string('refund_rule_summary', 'local_payments',
        (object) ['hours' => $p->hours, 'fee' => $fee]);
};

echo $OUTPUT->notification(
    get_string('refund_rule_sitedefault', 'local_payments', $describe($sitepolicy)),
    'info'
);

$hours = $existing ? (int) $existing->hours : (int) $sitepolicy->hours;
$feetype = $existing ? $existing->feetype : $sitepolicy->feetype;
$feevalue = $existing ? (float) $existing->feevalue : (float) $sitepolicy->feevalue;
$feecurrency = $existing && !empty($existing->feecurrency)
    ? $existing->feecurrency : $sitepolicy->feecurrency;

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('refund_hours', 'local_payments'),
    ['for' => 'lp-rule-hours', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'min' => 0, 'id' => 'lp-rule-hours', 'name' => 'hours',
    'value' => $hours, 'class' => 'form-control', 'style' => 'max-width:12rem',
]);
echo html_writer::tag('div', get_string('refund_rule_hours_help', 'local_payments'),
    ['class' => 'form-text']);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('refund_feetype', 'local_payments'),
    ['for' => 'lp-rule-feetype', 'class' => 'form-label']);
echo html_writer::select([
    \local_payments\refund_policy::FEE_PERCENT => get_string('refund_feetype_percent', 'local_payments'),
    \local_payments\refund_policy::FEE_FIXED => get_string('refund_feetype_fixed', 'local_payments'),
], 'feetype', $feetype, false, ['id' => 'lp-rule-feetype', 'class' => 'form-select',
    'style' => 'max-width:24rem']);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('refund_fee', 'local_payments'),
    ['for' => 'lp-rule-feevalue', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'min' => 0, 'step' => '0.01', 'id' => 'lp-rule-feevalue',
    'name' => 'feevalue', 'value' => format_float($feevalue, 2, false),
    'class' => 'form-control', 'style' => 'max-width:12rem',
]);
echo html_writer::tag('div', get_string('refund_rule_fee_help', 'local_payments'),
    ['class' => 'form-text']);
echo html_writer::end_div();

// A flat fee is a number in a currency. This site sells in more than one, so
// leaving that implicit means "10" quietly meaning ten of whatever the buyer
// happened to pay in.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('refund_feecurrency', 'local_payments'),
    ['for' => 'lp-rule-feecurrency', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'maxlength' => 3, 'id' => 'lp-rule-feecurrency',
    'name' => 'feecurrency', 'value' => $feecurrency,
    'class' => 'form-control', 'style' => 'max-width:8rem; text-transform:uppercase',
]);
echo html_writer::tag('div', get_string('refund_rule_feecurrency_help', 'local_payments'),
    ['class' => 'form-text']);
echo html_writer::end_div();

echo html_writer::tag('button', get_string('savechanges'),
    ['type' => 'submit', 'class' => 'btn btn-primary']);

if ($existing) {
    echo html_writer::link(
        new moodle_url($pageurl, ['action' => 'clear', 'sesskey' => sesskey()]),
        get_string('refund_rule_clear', 'local_payments'),
        ['class' => 'btn btn-outline-danger ms-2']
    );
}
echo html_writer::link($returnurl, get_string('back'), ['class' => 'btn btn-outline-secondary ms-2']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
