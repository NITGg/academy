<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$priceid = optional_param('priceid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/payments:managecoursepricing', $context);

$PAGE->set_url(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('coursepricing', 'local_payments'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Handle delete.
if ($action === 'delete' && $priceid && confirm_sesskey()) {
    if ($confirm) {
        $DB->delete_records('local_payments_course_prices', ['id' => $priceid, 'courseid' => $courseid]);
        redirect(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]),
            get_string('pricedeleted', 'local_payments'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeleteprice', 'local_payments'),
        new moodle_url('/local/payments/course_pricing.php', [
            'courseid' => $courseid, 'action' => 'delete', 'priceid' => $priceid,
            'confirm' => 1, 'sesskey' => sesskey(),
        ]),
        new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid])
    );
    echo $OUTPUT->footer();
    exit;
}

// Handle add/edit form.
if ($action === 'edit' || $action === 'add') {
    $data = null;
    if ($priceid) {
        $data = $DB->get_record('local_payments_course_prices', ['id' => $priceid, 'courseid' => $courseid], '*', MUST_EXIST);
    }

    $formurl = new moodle_url('/local/payments/course_pricing.php', [
        'courseid' => $courseid,
        'action' => $action,
        'priceid' => $priceid,
    ]);

    $form = new \local_payments\form\course_pricing_form($formurl, [
        'courseid' => $courseid,
        'priceid' => $priceid,
        'data' => $data,
    ]);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]));
    }

    if ($formdata = $form->get_data()) {
        // The first price for a course is always the default.
        $isfirst = !$DB->record_exists('local_payments_course_prices', ['courseid' => $courseid]);
        $record = (object) [
            'courseid' => $courseid,
            'country' => $formdata->country,
            'currency' => $formdata->currency,
            'price' => $formdata->price,
            // Blank means "no rule here", which is different from a zero fee:
            // zero is a deliberate full refund, blank falls back to the policy.
            'refund_fee' => ($formdata->refund_fee === '' || $formdata->refund_fee === null)
                ? null : max(0, (float) $formdata->refund_fee),
            'is_default' => ($isfirst || !empty($formdata->is_default)) ? 1 : 0,
            'is_active' => !empty($formdata->is_active) ? 1 : 0,
            'timemodified' => time(),
        ];

        if ($priceid) {
            $record->id = $priceid;
            $DB->update_record('local_payments_course_prices', $record);
        } else {
            $record->created_by = $USER->id;
            $record->timecreated = time();
            $DB->insert_record('local_payments_course_prices', $record);
        }

        redirect(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]),
            get_string('pricesaved', 'local_payments'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($priceid ? 'editprice' : 'addprice', 'local_payments'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// List prices.
$prices = $DB->get_records('local_payments_course_prices', ['courseid' => $courseid], 'is_default DESC, country ASC');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursepricing', 'local_payments'));

// Admin note: explain how the price a user sees is chosen. Three key ideas to make clear:
// pricing is ALWAYS by country (the IP only *identifies* a country, it carries no price of its
// own); a SIGNED-IN account is priced on its profile country and nothing else, so an empty one
// means no price and no purchase at all rather than a borrowed price; and every remaining dead
// end — guest, no usable IP — resolves to the Default price row rather than to some other
// country's price. Mirrors local_payments\country_detector.
// Rendered in the admin's current language (site is en/ar).
$pricingnote_isar = (strpos(current_language(), 'ar') === 0);
if ($pricingnote_isar) {
    $pricingnote =
        '<strong>كيف يظهر سعر الكورس للمستخدم؟</strong><br>'
        . 'السعر يعتمد دائمًا على <strong>دولة المستخدم</strong>. تُحدَّد الدولة بالترتيب التالي، '
        . 'وأوّل خطوة تنجح هي المعتمدة:'
        . '<ol style="margin:.5rem 0 .25rem; padding-inline-start:1.25rem;">'
        . '<li>مستخدم <strong>مسجّل دخول ولبروفايله دولة</strong>: تُؤخذ الدولة من بروفايله '
        . '(الدولة التي سجّل بها).</li>'
        . '<li>مستخدم <strong>مسجّل دخول لكن بروفايله بلا دولة</strong>: <strong>لا يُعرض له أي سعر '
        . 'ولا يستطيع الشراء</strong>. تظهر له بدلًا من السعر رسالة تطلب تحديد دولته مع رابط لصفحة '
        . 'بياناته، وبمجرد حفظ الدولة تعمل الأسعار والشراء فورًا. (لا نُخمّن دولته من الـ IP: '
        . 'الحساب الذي نستطيع سؤاله لا يُسعَّر بالتخمين، وإلّا لرأى المستخدم الواحد سعرين مختلفين '
        . 'من شبكتين مختلفتين.)</li>'
        . '<li>زائر <strong>غير مسجّل دخول</strong>: نُخمّن الدولة من عنوان الـ IP (موقعه التقريبي) — '
        . 'فلا بروفايل لديه لنسأله.</li>'
        . '<li>إذا <strong>تعذّر تحديد الدولة</strong> للزائر (فشل تحديد الموقع من الـ IP، أو عنوان داخلي/غير '
        . 'صالح، أو خدمة تحديد الموقع غير مُفعّلة): يظهر <strong>السعر الافتراضي</strong> مباشرةً.</li>'
        . '</ol>'
        . 'بعد تحديد الدولة: إذا كان للكورس سعرٌ مضبوطٌ <strong>ومفعّل</strong> لتلك الدولة يظهر هذا السعر، '
        . 'وإذا لم يوجد سعرٌ لتلك الدولة يظهر <strong>السعر الافتراضي</strong> (صف «Default price» بالأسفل).<br>'
        . '<span style="opacity:.85;">ملاحظة: عنوان الـ IP نفسه ليس له سعر — هو فقط يحدّد الدولة، '
        . 'ثم يُطبَّق سعر تلك الدولة إن وُجد. ولأن السعر الافتراضي هو المُستخدَم في كل الحالات '
        . 'غير المُغطّاة، يجب أن يكون للكورس دائمًا صف «Default price» مفعّل.</span><br>'
        . 'أضِف أسعار الدول من زر «' . get_string('addprice', 'local_payments') . '» بالأسفل.';
} else {
    $pricingnote =
        '<strong>How is a user&rsquo;s course price chosen?</strong><br>'
        . 'The price is always based on the <strong>user&rsquo;s country</strong>. The country is worked out '
        . 'in this order, and the first step that succeeds wins:'
        . '<ol style="margin:.5rem 0 .25rem; padding-inline-start:1.25rem;">'
        . '<li><strong>Logged-in user whose profile has a country</strong>: that profile country is used '
        . '(the country used at registration).</li>'
        . '<li><strong>Logged-in user whose profile has no country</strong>: <strong>no price is shown and '
        . 'they cannot buy</strong>. Wherever a price would appear they get a short message asking them to '
        . 'set their country, with a link to their profile; saving it switches prices and checkout back on '
        . 'immediately. (Their IP is deliberately not used: an account we can simply ask is not priced by a '
        . 'guess, which would otherwise quote the same buyer two different prices from two networks.)</li>'
        . '<li>A visitor who is <strong>not logged in</strong>: the country is guessed from their IP address '
        . '(approximate location) &mdash; there is no profile to ask.</li>'
        . '<li>If a visitor&rsquo;s country <strong>still cannot be determined</strong> (IP lookup fails, the '
        . 'address is private/invalid, or geolocation is not configured): the <strong>Default price</strong> is '
        . 'shown straight away.</li>'
        . '</ol>'
        . 'Once the country is known: if the course has an <strong>active</strong> price set for that country, '
        . 'it is shown; otherwise the <strong>Default price</strong> row below is shown.<br>'
        . '<span style="opacity:.85;">Note: an IP address has no price of its own &mdash; it only identifies the country, '
        . 'then that country&rsquo;s price is applied if one exists. Because the Default price covers every '
        . 'case not matched above, each course should always keep an active &ldquo;Default price&rdquo; row.</span><br>'
        . 'Add per-country prices with the &ldquo;' . get_string('addprice', 'local_payments') . '&rdquo; button below.';
}
echo html_writer::div($pricingnote, 'alert alert-info',
    ['dir' => $pricingnote_isar ? 'rtl' : 'ltr']);

$addurl = new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid, 'action' => 'add']);
echo html_writer::link($addurl, get_string('addprice', 'local_payments'), ['class' => 'btn btn-primary mb-3']);

if (empty($prices)) {
    echo $OUTPUT->notification(get_string('noprices', 'local_payments'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('country', 'local_payments'),
        get_string('currency', 'local_payments'),
        get_string('price', 'local_payments'),
        get_string('is_default', 'local_payments'),
        get_string('is_active', 'local_payments'),
        get_string('actions', 'local_payments'),
    ];
    $table->attributes['class'] = 'generaltable';

    $countries = get_string_manager()->get_list_of_countries();

    foreach ($prices as $p) {
        $countryname = ($p->country === '*') ? get_string('defaultprice', 'local_payments') : ($countries[$p->country] ?? $p->country);

        $editurl = new moodle_url('/local/payments/course_pricing.php', [
            'courseid' => $courseid, 'action' => 'edit', 'priceid' => $p->id,
        ]);
        $deleteurl = new moodle_url('/local/payments/course_pricing.php', [
            'courseid' => $courseid, 'action' => 'delete', 'priceid' => $p->id, 'sesskey' => sesskey(),
        ]);

        $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit'))) . ' ' .
            html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));

        $table->data[] = [
            $countryname,
            $p->currency,
            number_format((float) $p->price, 2),
            $p->is_default ? get_string('yes') : get_string('no'),
            $p->is_active ? get_string('yes') : get_string('no'),
            $actions,
        ];
    }

    echo html_writer::table($table);
}

// The refund policy sits beside the price: both are terms of the same sale, and
// whoever sets one is the person who should be setting the other.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/payments/refund_rule.php',
            ['itemtype' => 'course', 'itemid' => $courseid]),
        get_string('refund_rule_link', 'local_payments'),
        ['class' => 'btn btn-outline-secondary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
