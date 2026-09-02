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
 * CSV export of the coupon redemption log (AC-4.12.8: "and is reportable").
 *
 * The report tab on manage_coupons.php links here with whatever filters are on screen, so the file
 * is exactly the rows the admin is looking at — not the whole table. It lives in its own script
 * rather than in api.php because that endpoint has already committed to a JSON content type.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

use local_nit_commerce\coupon_manager;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/nit_commerce:managecoupons', $context);
require_sesskey();

$alang = optional_param('alang', '', PARAM_LANG);
if ($alang !== '') {
    force_current_language($alang);
}

// No paging: an export that stopped at page one would be a trap.
$data = coupon_manager::get_redemptions(nit_commerce_redemption_filters(), 0, 0);
$currency = $data['totals']['currency'];

$filename = 'coupon-redemptions-' . userdate(time(), '%Y-%m-%d') . '.csv';

// Excel opens a UTF-8 CSV as mojibake unless it finds a BOM — and these files carry Arabic
// learner names, so the BOM is not optional here.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    get_string('rep_col_date', 'local_nit_commerce'),
    get_string('rep_col_code', 'local_nit_commerce'),
    get_string('ofr_col_name', 'local_nit_commerce'),
    get_string('rep_col_learner', 'local_nit_commerce'),
    get_string('email'),
    get_string('rep_col_order', 'local_nit_commerce'),
    get_string('rep_col_orderstatus', 'local_nit_commerce'),
    get_string('rep_col_item', 'local_nit_commerce'),
    get_string('rep_col_original', 'local_nit_commerce') . ' (' . $currency . ')',
    get_string('rep_col_discount', 'local_nit_commerce') . ' (' . $currency . ')',
    get_string('rep_col_paid', 'local_nit_commerce') . ' (' . $currency . ')',
]);

foreach ($data['rows'] as $row) {
    fputcsv($out, [
        $row['date'],
        $row['code'],
        $row['coupon_name'],
        $row['learner'],
        $row['email'],
        $row['order_id'],
        $row['order_status'] !== '' ? $row['order_status'] : '-',
        $row['item_label'],
        number_format($row['original_amount'], 2, '.', ''),
        number_format($row['discount_amount'], 2, '.', ''),
        number_format($row['final_amount'], 2, '.', ''),
    ]);
}

// A totals line, so the file answers "what did the coupons cost us" without a spreadsheet formula.
fputcsv($out, []);
fputcsv($out, [
    get_string('rep_total', 'local_nit_commerce'),
    '', '', '', '', '', '', '',
    number_format($data['totals']['gross'], 2, '.', ''),
    number_format($data['totals']['discounted'], 2, '.', ''),
    number_format($data['totals']['net'], 2, '.', ''),
]);

fclose($out);
exit;
