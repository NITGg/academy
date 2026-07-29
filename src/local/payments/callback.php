<?php
require_once(__DIR__ . '/../../config.php');

$order_id      = required_param('order_id', PARAM_TEXT);
// Kashier appends paymentStatus=SUCCESS|FAILED to the redirect URL.
$kashier_status = strtoupper(optional_param('paymentStatus', '', PARAM_ALPHANUMEXT));

require_login();

// Front-end site: after the payment is processed we send the student back to the matching page on
// the Next.js front-end instead of showing the Moodle result pages, so they stay on their own site.
// The admin setting takes priority; when it's empty we fall back to the known front-end base so the
// redirect works as soon as this file is deployed (no plugin upgrade / config step required).
$frontendurl = trim((string) get_config('local_payments', 'frontend_url'));
if ($frontendurl === '') {
    $frontendurl = 'https://academy2026.nitg-eg.com/nextjs-frontend-student';
}

/** Map an item to its front-end landing path and return the absolute URL (with a payment status flag). */
function local_payments_frontend_landing($frontendbase, $item_type, $itemid, $status) {
    $base = rtrim($frontendbase, '/');
    switch ($item_type) {
        case 'course':       $path = '/courses/' . (int) $itemid; break;
        case 'program':      $path = '/programs/' . (int) $itemid; break;
        case 'subscription': $path = '/subscriptions'; break;
        case 'package':      $path = '/packages'; break;
        default:             $path = '/'; break;
    }
    return $base . $path . '?payment=' . $status;
}

/** Resolve [item_type, item_id] for a transaction row (courseid column, else metadata). */
function local_payments_txn_item($transaction) {
    if (!$transaction) {
        return ['', 0];
    }
    if (!empty($transaction->courseid)) {
        return ['course', (int) $transaction->courseid];
    }
    $meta = json_decode($transaction->metadata ?? '', true);
    if (is_array($meta)) {
        return [$meta['item_type'] ?? '', (int) ($meta['item_id'] ?? 0)];
    }
    return ['', 0];
}

/** True when $url lives on the same origin (scheme + host + port) as $base. Open-redirect guard. */
function local_payments_url_on_site($url, $base) {
    $u = parse_url($url);
    $b = parse_url(rtrim($base, '/'));
    if (empty($u['host']) || empty($b['host']) || strcasecmp($u['host'], $b['host']) !== 0) {
        return false;
    }
    if (!empty($u['scheme']) && !empty($b['scheme']) && strcasecmp($u['scheme'], $b['scheme']) !== 0) {
        return false;
    }
    return (($u['port'] ?? null) === ($b['port'] ?? null));
}

/**
 * Where to send the student after payment: the exact page the checkout was launched from
 * (transaction metadata `return_url`) when it belongs to the configured front-end site, otherwise
 * the item-type landing page. A `payment=success|failed` flag is always added.
 */
function local_payments_return_target($frontendbase, $transaction, $item_type, $itemid, $status) {
    if ($transaction && !empty($transaction->metadata)) {
        $meta = json_decode($transaction->metadata, true);
        $returnurl = (is_array($meta) && !empty($meta['return_url'])) ? (string) $meta['return_url'] : '';
        if ($returnurl !== '' && local_payments_url_on_site($returnurl, $frontendbase)) {
            // moodle_url safely preserves any existing query and overwrites the payment flag.
            return (new moodle_url($returnurl, ['payment' => $status]))->out(false);
        }
    }
    return local_payments_frontend_landing($frontendbase, $item_type, $itemid, $status);
}

$PAGE->set_url(new moodle_url('/local/payments/callback.php', ['order_id' => $order_id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

// ── FAILED redirect from Kashier ────────────────────────────────────────────
// Kashier told us right now that the payment failed. Update the DB and show
// the failure page immediately — no need to call Kashier's API again.
if ($kashier_status === 'FAILED') {
    $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);

    if ($transaction && $transaction->status === \local_payments\status_machine::PENDING) {
        $DB->update_record('local_payments_transactions', (object) [
            'id'            => $transaction->id,
            'status'        => \local_payments\status_machine::FAILED,
            'reject_reason' => 'Payment failed at provider (redirect)',
            'timemodified'  => time(),
        ]);
    }

    // Front-end site configured → bounce back to the originating page with a failure flag.
    if ($frontendurl !== '') {
        list($itype, $iid) = local_payments_txn_item($transaction);
        redirect(local_payments_return_target($frontendurl, $transaction, $itype, $iid, 'failed'));
    }

    $PAGE->set_title(get_string('payment_failure', 'local_payments'));
    echo $OUTPUT->header();
    $templatedata = [
        'success'      => false,
        'status'       => 'FAILED',
        'order_id'     => $order_id,
        'retry_url'    => $transaction
            ? (new moodle_url('/local/payments/buy.php', ['courseid' => $transaction->courseid]))->out(false)
            : (new moodle_url('/'))->out(false),
        'history_url'  => (new moodle_url('/local/payments/history.php'))->out(false),
    ];
    echo $OUTPUT->render_from_template('local_payments/payment_failure', $templatedata);
    echo $OUTPUT->footer();
    exit;
}

// ── SUCCESS / unknown redirect — read DB state set by the webhook ────────────
// The webhook (server-to-server) is the authoritative path for enrollment.
// We just read what the webhook already wrote. The Kashier API fallback inside
// verify_callback() is only reached when the webhook hasn't fired yet.
try {
    $result = \local_payments\manager::verify_callback($order_id);

    if ($result->success) {
        $item_type = $result->item_type ?? 'course';

        // Front-end site configured → send the student back to the exact page they launched the
        // checkout from (transaction return_url), falling back to the item's landing page.
        if ($frontendurl !== '') {
            $iid = ($item_type === 'course')
                ? (int) $result->courseid
                : (int) ($result->itemid ?? 0);
            $txn = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);
            redirect(local_payments_return_target($frontendurl, $txn, $item_type, $iid, 'success'));
        }

        // Packages and subscriptions are not tied to a single page to land on. Send the student
        // straight to the home dashboard with a success notice instead of the generic success page.
        if ($item_type === 'package' || $item_type === 'subscription') {
            redirect(
                new moodle_url('/'),
                get_string('payment_success', 'local_payments'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $PAGE->set_title(get_string('payment_success', 'local_payments'));
        echo $OUTPUT->header();

        if ($item_type === 'program') {
            $programid = (int) ($result->itemid ?? 0);
            $programname = $DB->get_field('enrol_programs_programs', 'fullname', ['id' => $programid]);
            $itemlabel = get_string('program', 'local_payments');
            $itemname = $programname ? format_string($programname) : '';
            // The plugin's own "my program" page — not /course/view.php, a program has no single
            // course of its own.
            $itemurl = (new moodle_url('/enrol/programs/my/program.php', ['id' => $programid]))->out(false);
            $buttontext = get_string('gotoprogram', 'local_payments');
        } else {
            $course = $DB->get_record('course', ['id' => $result->courseid], 'id, fullname');
            $itemlabel = get_string('course', 'local_payments');
            $itemname = $course->fullname ?? '';
            $itemurl = (new moodle_url('/course/view.php', ['id' => $result->courseid]))->out(false);
            $buttontext = get_string('gotocourse', 'local_payments');
        }

        $templatedata = [
            'success'          => true,
            'item_label'       => $itemlabel,
            'item_name'        => $itemname,
            'item_url'         => $itemurl,
            'item_button_text' => $buttontext,
            'order_id'         => $order_id,
            'history_url'      => (new moodle_url('/local/payments/history.php'))->out(false),
        ];
        echo $OUTPUT->render_from_template('local_payments/payment_success', $templatedata);
    } else {
        $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);

        // Front-end site configured → bounce back to the originating page with a failure flag.
        if ($frontendurl !== '') {
            list($itype, $iid) = local_payments_txn_item($transaction);
            redirect(local_payments_return_target($frontendurl, $transaction, $itype, $iid, 'failed'));
        }

        $PAGE->set_title(get_string('payment_failure', 'local_payments'));
        echo $OUTPUT->header();

        $templatedata = [
            'success'     => false,
            'status'      => $result->status,
            'order_id'    => $order_id,
            'retry_url'   => $transaction
                ? (new moodle_url('/local/payments/buy.php', ['courseid' => $transaction->courseid]))->out(false)
                : (new moodle_url('/'))->out(false),
            'history_url' => (new moodle_url('/local/payments/history.php'))->out(false),
        ];
        echo $OUTPUT->render_from_template('local_payments/payment_failure', $templatedata);
    }
} catch (\Exception $e) {
    $PAGE->set_title(get_string('payment_failure', 'local_payments'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/'));
}

echo $OUTPUT->footer();
