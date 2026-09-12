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
 * Strings for local_nit_finance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Finance';
$string['local_nit_finance:manage'] = 'Manage NIT platform finances and withdrawals';

// Admin page.
$string['financialreports'] = 'Financial Reports';
$string['platformwallet'] = 'Platform Wallet';
$string['currentmoney'] = 'Current money';
$string['undistributedmoney'] = 'Undistributed package money';
$string['teachersmoney'] = 'Teachers\' money';
$string['platformearnings'] = 'Platform earnings';
$string['totalpaidout'] = 'Total paid out';
$string['withdrawals'] = 'Withdrawal requests';
$string['nowithdrawals'] = 'There are no withdrawal requests.';
$string['teacher'] = 'Teacher';
$string['amount'] = 'Amount';
$string['method'] = 'Method';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['pay'] = 'Mark as paid';

// Statuses.
$string['status_pending'] = 'Pending';
$string['status_approved'] = 'Approved';
$string['status_rejected'] = 'Rejected';
$string['status_paid'] = 'Paid';

// Errors.
$string['err_amountpositive'] = 'The amount must be greater than zero.';
$string['err_busy'] = 'Another withdrawal request for this teacher is being processed. Please try again in a moment.';
$string['err_insufficientbalance'] = 'The requested amount exceeds the available balance.';
$string['err_withdrawalnotfound'] = 'Withdrawal request not found.';
$string['err_withdrawalstate'] = 'The withdrawal is not in a state that allows this action.';
$string['err_reasonrequired'] = 'A reason is required.';
$string['err_badaction'] = 'Unknown action.';
$string['err_notdistributed'] = 'The lesson could not be distributed (no valid purchase).';
$string['err_lessonnotfound'] = 'Lesson not found.';
$string['err_earningnotfound'] = 'No active earning found for this lesson.';
$string['err_alreadyreversed'] = 'This earning has already been reversed.';

// Privacy.
$string['privacy:metadata:nit_earning'] = 'Revenue split records crediting a teacher for a completed lesson.';
$string['privacy:metadata:nit_earning:teacherid'] = 'The teacher credited by the earning.';
$string['privacy:metadata:nit_earning:teacher_amount_minor'] = 'The teacher\'s share, in minor currency units.';
$string['privacy:metadata:nit_earning:timecreated'] = 'When the earning was recorded.';
$string['privacy:metadata:nit_withdrawal'] = 'Teacher requests to withdraw earned money.';
$string['privacy:metadata:nit_withdrawal:teacherid'] = 'The teacher requesting the withdrawal.';
$string['privacy:metadata:nit_withdrawal:amount_minor'] = 'The requested amount, in minor currency units.';
$string['privacy:metadata:nit_withdrawal:account'] = 'The payout account details supplied by the teacher.';
$string['privacy:metadata:nit_withdrawal:timecreated'] = 'When the request was made.';

// ── The shared financial report (one panel, five pages) ────────────────────────────────
$string['rep_tab'] = 'Reports';
$string['rep_noaccess'] = 'You do not have permission to view financial figures.';
$string['rep_loading'] = 'Loading…';
$string['rep_none'] = 'Nothing matches these filters.';
$string['rep_error'] = 'The report could not be loaded. Please try again.';
$string['rep_truncated'] = 'Only the most recent orders are shown. Narrow the date range, or export to CSV for the full list.';

// Scopes.
$string['rep_scope_all'] = 'All revenue';
$string['rep_scope_courses'] = 'Courses';
$string['rep_scope_subscriptions'] = 'Subscriptions';
$string['rep_scope_coupons'] = 'Coupons';
$string['rep_scope_offers'] = 'Offers';
$string['rep_scope_refunds'] = 'Refunds';

// What each scope is for, in one line.
$string['rep_intro'] = 'What the platform earned, and where each pound came from.';
$string['rep_intro_all'] = 'Every pound the platform took in, and what brought it in: a course or a plan, bought at full price or through a coupon or an offer. Click any row of the breakdown to see just those orders.';
$string['rep_intro_courses'] = 'Money from single-course purchases: what each course earned, what discounts cost, and what was refunded.';
$string['rep_intro_subscriptions'] = 'Money from subscription plans: what each plan earned, what discounts cost, and what was refunded.';
$string['rep_intro_coupons'] = 'Orders a coupon code actually discounted — what each code brought in, and what it gave away to do it.';
$string['rep_intro_offers'] = 'Orders an automatic offer actually discounted — what each offer brought in, and what it gave away to do it.';
$string['rep_intro_refunds'] = 'Money that went back out, and how it left: through the payment gateway, or by an administrator revoking a purchase.';

// Filters.
$string['rep_apply'] = 'Apply';
$string['rep_reset'] = 'Reset';
$string['rep_export'] = 'Export CSV';
$string['rep_search'] = 'Search';
$string['rep_search_ph'] = 'Buyer, e-mail or order reference';
$string['rep_from'] = 'From';
$string['rep_to'] = 'To';
$string['rep_state'] = 'Orders';
$string['rep_currency'] = 'Currency';
$string['rep_narrow'] = 'Narrow to';
$string['rep_all'] = 'All';
$string['rep_state_paid'] = 'Paid (including refunded)';
$string['rep_state_completed'] = 'Paid and still standing';
$string['rep_state_refunded'] = 'Refunded only';
$string['rep_state_lost'] = 'Never paid';
$string['rep_state_all'] = 'Every attempt';
$string['rep_clearsource'] = 'Show all sources again';

// Totals.
$string['rep_kpi_gross'] = 'List value';
$string['rep_kpi_gross_help'] = 'What these orders would have cost undiscounted.';
$string['rep_kpi_discount'] = 'Discounts';
$string['rep_kpi_discount_help'] = 'Given away through coupons and offers.';
$string['rep_kpi_net'] = 'Charged';
$string['rep_kpi_net_help'] = 'What buyers actually paid.';
$string['rep_kpi_refunded'] = 'Refunded';
$string['rep_kpi_refunded_help'] = 'Money given back to buyers.';
$string['rep_kpi_earned'] = 'Earned';
$string['rep_kpi_earned_help'] = 'Charged, less refunds. This is the profit.';
$string['rep_kpi_orders'] = 'Orders';
$string['rep_kpi_orders_help'] = '{$a->learners} buyers · {$a->average} average';

// Breakdown groupings.
$string['rep_by_source'] = 'Where the money came from';
$string['rep_by_course'] = 'By course';
$string['rep_by_plan'] = 'By plan';
$string['rep_by_coupon'] = 'By coupon';
$string['rep_by_offer'] = 'By offer';
$string['rep_by_refundkind'] = 'By how the refund was made';

// Columns.
$string['rep_col_share'] = 'Share';
$string['rep_col_orders'] = 'Orders';
$string['rep_col_learners'] = 'Buyers';
$string['rep_col_gross'] = 'List value';
$string['rep_col_discount'] = 'Discount';
$string['rep_col_net'] = 'Charged';
$string['rep_col_refunded'] = 'Refunded';
$string['rep_col_earned'] = 'Earned';
$string['rep_col_date'] = 'Date';
$string['rep_col_order'] = 'Order';
$string['rep_col_user'] = 'Buyer';
$string['rep_col_item'] = 'Bought';
$string['rep_col_source'] = 'Source';
$string['rep_col_detail'] = 'Why the price changed';
$string['rep_col_status'] = 'Status';
$string['rep_col_total'] = 'Total';
$string['rep_orders_heading'] = 'Orders';
$string['rep_pager'] = 'Showing {$a->first}–{$a->last} of {$a->total}';
$string['rep_prev'] = 'Previous';
$string['rep_next'] = 'Next';

// What was sold, and what brought the buyer in.
$string['rep_item_course'] = 'Course';
$string['rep_item_subscription'] = 'Subscription';
$string['rep_source_full'] = '{$a} — full price';
$string['rep_source_coupon'] = '{$a} — coupon';
$string['rep_source_offer'] = '{$a} — offer';
$string['rep_detail_coupon'] = 'Coupon {$a->code} (−{$a->amount})';
$string['rep_detail_offer'] = 'Offer {$a->name} (−{$a->amount})';
$string['rep_detail_couponbeaten'] = 'Coupon {$a} was beaten by the offer';
$string['rep_offer'] = 'Offer';
$string['rep_nocode'] = 'No code';
$string['rep_itemgone'] = '(deleted)';

// How a refund was made.
$string['rep_refund_gateway'] = 'Through the payment gateway';
$string['rep_refund_manual'] = 'Revoked by an administrator';
$string['rep_refund_unrecorded'] = 'Marked refunded, no record';

// The master page.
$string['rep_masterintro'] = 'Every financial figure on the platform, in one place. The same report appears, already narrowed, on the Reports tab of the course, subscription, coupon and offer pages.';

// Orders that never paid. These only appear when the admin asks for a state that includes
// them, which is why the card and the column come and go together.
$string['rep_kpi_lost'] = 'Not paid';
$string['rep_kpi_lost_help'] = 'Orders that never completed: {$a->orders}';
$string['rep_col_lost'] = 'Not paid';
