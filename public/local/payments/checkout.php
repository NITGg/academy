<?php
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$lang = optional_param('lang', current_language() === 'ar' ? 'ar' : 'en', PARAM_ALPHA);
$couponcode = optional_param('coupon_code', '', PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Use require_login() without $course so unenrolled users (who are here
// specifically to purchase access) aren't bounced to the native enrolment
// page by Moodle's can_access_course() check.
require_login();
require_capability('local/payments:purchasecourse', $context);
// State-changing (creates a transaction + opens a gateway session), so require a
// valid sesskey — stops a logged-in victim being lured to checkout.php?courseid=X.
require_sesskey();

// No active pricing — course is free, nothing to check out.
if (!\local_payments\price_resolver::has_pricing($courseid)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$PAGE->set_url(new moodle_url('/local/payments/checkout.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('buycourse', 'local_payments'));
$PAGE->set_pagelayout('standard');

// Which method to charge. 0 means "not chosen yet"; the gateway decides whether
// that means asking the buyer or picking one itself.
$methodid = optional_param('payment_method_id', 0, PARAM_INT);

// AC-4.13.6: the price the checkout modal actually showed this buyer. Anything below zero (the
// default, and what an older link carries) means "no quote" — the checkout then proceeds at
// whatever the price is now, exactly as before. When a quote IS supplied and the price has since
// moved (an offer that lapsed while the modal was open), manager::create_checkout() refuses and
// this page shows the revised figure for a fresh confirmation instead of charging it.
$quoted = optional_param('quoted_amount', -1, PARAM_FLOAT);

try {
    // Offer the choice before taking the money, when there is a choice to offer
    // and the buyer has not already made it. One method is not a choice, and a
    // gateway with its own picker does not need ours.
    //
    // Not behind a setting: the mobile web service hands the app every method
    // unconditionally, so gating the web on a checkbox meant the same gateway
    // asked on one platform and decided for the buyer on the other.
    if (!$methodid) {
        $pricing = \local_payments\price_resolver::resolve($courseid, $USER->id);
        $offer = \local_payments\manager::get_provider_payment_methods(
            $pricing->country, $pricing->currency);

        if ($offer->supports_payment_methods && count($offer->methods) > 1) {
            $PAGE->set_title(get_string('choose_method', 'local_payments'));
            echo $OUTPUT->header();

            $methods = [];
            foreach ($offer->methods as $i => $method) {
                $methods[] = [
                    'id' => $method['id'],
                    'name' => (current_language() === 'ar' && $method['name_ar'] !== '')
                        ? $method['name_ar'] : $method['name_en'],
                    'logo' => $method['logo'],
                    // A method that hands back a code behaves differently enough
                    // that the buyer should know before choosing it.
                    'is_reference' => !$method['redirect'],
                    'checked' => ($i === 0),
                ];
            }

            echo $OUTPUT->render_from_template('local_payments/method_picker', [
                'course_name' => format_string($course->fullname),
                'amount_formatted' => format_float((float) $pricing->price, 2, true, true)
                    . ' ' . $pricing->currency,
                'action' => (new moodle_url('/local/payments/checkout.php'))->out(false),
                'sesskey' => sesskey(),
                'courseid' => $courseid,
                'coupon_code' => $couponcode,
                'quoted_amount' => $quoted,
                'methods' => $methods,
                'cancel_url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            ]);

            echo $OUTPUT->footer();
            exit;
        }
    }

    $result = \local_payments\manager::create_checkout($courseid, $USER->id, null, $lang,
        $couponcode, $methodid, $quoted);

    // Card-style methods hand back a page to send the buyer to. Offline methods
    // (Fawry, Meeza) hand back a code instead — there is nothing to redirect to,
    // so the code itself is the checkout result and gets its own screen.
    $paymentdata = $result->payment_data ?? [];
    if (($paymentdata['type'] ?? '') === 'reference') {
        $transaction = $DB->get_record('local_payments_transactions',
            ['id' => $result->transaction_id], '*', MUST_EXIST);

        $PAGE->set_title(get_string('reference_title', 'local_payments'));
        echo $OUTPUT->header();
        echo $OUTPUT->render_from_template('local_payments/payment_reference',
            \local_payments\reference_screen::export($transaction));
        echo $OUTPUT->footer();
        exit;
    }

    if (empty($result->checkout_url)) {
        throw new moodle_exception('paymentinitiationfailed', 'local_payments', '', null,
            'Gateway returned no checkout URL and no payment reference.');
    }

    redirect(new moodle_url($result->checkout_url));
} catch (\local_payments\price_changed_exception $e) {
    // AC-4.13.6: the offer behind the quoted price lapsed (or the price changed some other way)
    // while the buyer was on the confirmation screen. Nothing has been created and nothing has
    // been charged — show what it costs now against what they were told, and take the decision
    // again. Confirming re-posts the same checkout with the revised figure as the agreed one, so
    // if it moves a second time this screen simply comes back.
    $PAGE->set_title(get_string('pricechanged_title', 'local_payments'));
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_payments/price_changed', [
        'item_name' => format_string($course->fullname),
        'old_amount_formatted' => format_float($e->quoted, 2, true, true) . ' ' . $e->currency,
        'new_amount_formatted' => format_float($e->amount, 2, true, true) . ' ' . $e->currency,
        'increase' => $e->amount > $e->quoted,
        'action' => (new moodle_url('/local/payments/checkout.php'))->out(false),
        'sesskey' => sesskey(),
        'fields' => [
            ['name' => 'courseid', 'value' => $courseid],
            ['name' => 'coupon_code', 'value' => $couponcode],
            ['name' => 'payment_method_id', 'value' => $methodid],
            ['name' => 'lang', 'value' => $lang],
            ['name' => 'quoted_amount', 'value' => format_float($e->amount, 2, false)],
        ],
        'cancel_url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    ]);
    echo $OUTPUT->footer();
} catch (\local_payments\country_required_exception $e) {
    // No profile country = no price = nothing to charge. Send them to fill it in rather than
    // to the course they cannot buy yet.
    $notice = \local_payments\country_detector::country_required_notice();
    echo $OUTPUT->header();
    echo $OUTPUT->notification($notice['message'], 'info');
    echo $OUTPUT->single_button(new moodle_url($notice['url']), $notice['action'], 'get');
    echo $OUTPUT->footer();
} catch (\Exception $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
    echo $OUTPUT->footer();
}
