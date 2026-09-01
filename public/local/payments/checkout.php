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
                'methods' => $methods,
                'cancel_url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            ]);

            echo $OUTPUT->footer();
            exit;
        }
    }

    $result = \local_payments\manager::create_checkout($courseid, $USER->id, null, $lang,
        $couponcode, $methodid);

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
