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

/**
 * The "pay with this code" screen, on a URL of its own.
 *
 * A course checkout can render this inline because it owns the response. A
 * subscription checkout cannot: it happens over AJAX from the plan dialog, and
 * JSON has nowhere to put a page. Both now send the buyer here.
 *
 * It also makes good on what the screen has always promised — that the code is
 * saved and can be opened again later — since a Fawry code is usually paid the
 * next day, from a different device.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$transaction = $DB->get_record('local_payments_transactions', ['id' => $id], '*', MUST_EXIST);

// The buyer's own order, or a staff member who is allowed to look at payments.
// A payment code is worth money to whoever holds it, so this is not a page that
// guesses.
if ((int) $transaction->userid !== (int) $USER->id) {
    require_capability('local/payments:viewalltransactions', context_system::instance());
}

$PAGE->set_url(new moodle_url('/local/payments/reference.php', ['id' => $id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('reference_title', 'local_payments'));
$PAGE->set_pagelayout('standard');

// Paid, cancelled or expired: there is no longer a code to pay, and showing one
// would invite somebody to pay it twice.
if (!\local_payments\reference_screen::applies($transaction)) {
    redirect(new moodle_url('/local/payments/history.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_payments/payment_reference',
    \local_payments\reference_screen::export($transaction));
echo $OUTPUT->footer();
