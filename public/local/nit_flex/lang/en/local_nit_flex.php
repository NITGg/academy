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
 * Strings for local_nit_flex. UI labels are kept identical to the reference local_academy plugin.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Flex';
$string['local_nit_flex:managepackages'] = 'Manage NIT Flex packages';
$string['local_nit_flex:purchase'] = 'Purchase a NIT Flex package';

// Page + nav titles.
$string['managepackages'] = 'Manage lesson packages';
$string['studenthub'] = 'Book lessons & Flex';
$string['studenthubdesc'] = 'Book a lesson, track your lessons, and manage your packages, Flex, and subscriptions — all in one place.';
$string['availpkgs_heading'] = 'Available packages';
$string['availpkgs_desc'] = 'Buy a Flex package to book one-on-one lessons with our teachers.';
$string['mypackages'] = 'My packages';

// Shared UI.
$string['ui_refresh'] = 'Refresh';
$string['ui_loading'] = 'Loading…';
$string['ui_save'] = 'Save';
$string['ui_cancel'] = 'Cancel';
$string['ui_confirm'] = 'Confirm';
$string['ui_search'] = 'Search';
$string['ui_edit'] = 'Edit';
$string['ui_delete'] = 'Delete';
$string['ui_activate'] = 'Activate';
$string['ui_deactivate'] = 'Deactivate';
$string['ui_active'] = 'Active';
$string['ui_never'] = 'Never';
$string['ui_optional'] = '(optional)';
$string['ui_currency_egp'] = 'EGP';
$string['ui_pager_info'] = 'Showing {from}–{to} of {total}';
$string['ui_picker_searching'] = 'Searching…';
$string['ui_picker_none'] = 'No matches found';
$string['ui_picker_hint'] = 'Type 2 or more characters';
$string['ui_picker_student_ph'] = 'Search student by name or email…';

// Admin messages.
$string['err_requestfailed'] = 'Request failed';
$string['err_sessionexpired'] = 'Session expired — please reload the page and log in again.';
$string['msg_package_created'] = 'Package created.';
$string['msg_package_updated'] = 'Package updated.';
$string['msg_package_activated'] = 'Package activated.';
$string['msg_package_deactivated'] = 'Package deactivated.';
$string['msg_package_deleted'] = 'Package deleted.';
$string['msg_package_unassigned'] = 'Package unassigned successfully.';
$string['msg_package_purchased'] = 'Package purchased.';

// Admin package form + table.
$string['pkg_new'] = 'New package';
$string['pkg_edit_titled'] = 'Edit package #{$a}';
$string['pkg_field_name_en'] = 'Name (English)';
$string['pkg_field_name_ar'] = 'Name (Arabic)';
$string['pkg_field_desc_en'] = 'Description (English)';
$string['pkg_field_desc_ar'] = 'Description (Arabic)';
$string['pkg_field_flexcount'] = 'Flex count';
$string['pkg_field_price'] = 'Price (EGP)';
$string['pkg_field_expdays'] = 'Expiration days (0 = unlimited)';
$string['pkg_col_id'] = 'ID';
$string['pkg_col_name'] = 'Name';
$string['pkg_col_flexes'] = 'Flexes';
$string['pkg_col_price'] = 'Price';
$string['pkg_col_expdays'] = 'Expiration (days)';
$string['pkg_col_status'] = 'Status';
$string['pkg_col_actions'] = 'Actions';
$string['pkg_none'] = 'No packages yet.';
$string['pkg_confirm_delete'] = 'Delete this package? This cannot be undone.';

// Admin tabs.
$string['pkg_tab_packages'] = 'Packages';
$string['pkg_tab_assign'] = 'Assign package';
$string['pkg_tab_settings'] = 'Package settings';

// Package settings tab (US-AD-2-1).
$string['set_min_booking'] = 'Minimum booking time (minutes)';
$string['set_cancel_deadline'] = 'Student cancellation deadline (minutes)';
$string['set_update_deadline'] = 'Lesson time-update deadline (minutes)';
$string['set_start_allowed'] = 'Lesson start allowed time (minutes)';
$string['set_complete_allowed'] = 'Minimum minutes after start before completing';
$string['set_absence_report'] = 'Absence reporting time (minutes)';
$string['set_lesson_start_reminder'] = 'Lesson start reminder (minutes)';
$string['set_lesson_start_reminder_help'] = 'Notify the student this many minutes before their lesson starts (0 to disable).';
$string['set_reminder_add'] = 'Add';
$string['set_reminder_placeholder'] = 'Mins (e.g. 15)';
$string['set_expiry_reminder'] = 'Package expiry reminder (days before)';
$string['set_expiry_reminder_help'] = 'Notify the student this many days before their package expires. 0 disables the reminder.';
$string['set_teacher_percent'] = 'Teacher earning %';
$string['set_platform_percent'] = 'Platform earning %';
$string['set_percent_help'] = 'Teacher % + Platform % must total 100.';
$string['set_save'] = 'Save changes';
$string['set_saved'] = 'Saved.';

// Flex reversal (US-FN-1-5).
$string['wd_reversal_title'] = 'Reverse a completed lesson\'s Flex (US-FN-1-5)';
$string['wd_reversal_help'] = 'Returns one consumed Flex to the student and reverses the teacher/platform earning. A reason is required.';
$string['wd_lesson_id'] = 'Lesson';
$string['wd_reason'] = 'Reason';
$string['wd_return_flex'] = 'Return Flex';
$string['wd_enter_lesson'] = 'Search and select a lesson.';
$string['wd_flex_returned'] = 'Flex returned and earning reversed.';
$string['err_reasonrequired'] = 'A reason is required.';
$string['ui_picker_lesson_ph'] = 'Search lesson by subject, student or teacher…';

// User Packages table.
$string['pkg_userpackages'] = 'User Packages';
$string['pkg_userpackages_desc'] = 'Manage active and expired user packages.';
$string['pkg_col_user'] = 'User';
$string['pkg_col_package'] = 'Package';
$string['pkg_col_flex'] = 'Flex';
$string['pkg_col_pricepaid'] = 'Price Paid';
$string['pkg_col_expiresat'] = 'Expires At';
$string['pkg_users_none'] = 'No user packages found.';
$string['pkg_unassign'] = 'Unassign';
$string['pkg_unassign_title'] = 'Unassign package';
$string['pkg_unassign_refund'] = 'Refund payment to student';
$string['pkg_unassign_confirm'] = 'Unassign <strong>{$a->name}</strong> from <strong>{$a->user}</strong>{$a->price}? This cannot be undone.';
$string['pkg_unassign_paid'] = ' — <strong>{$a}</strong> paid';

// Assign-package tab.
$string['ap_student_label'] = 'Student';
$string['ap_student_help'] = 'Search for the student by name or email and select them.';
$string['ap_package_label'] = 'Package';
$string['ap_amount_label'] = 'Amount paid (offline)';
$string['ap_amount_placeholder'] = 'defaults to package price';
$string['ap_method_label'] = 'Payment method';
$string['ap_method_offline'] = 'Offline / cash';
$string['ap_method_bank'] = 'Bank transfer';
$string['ap_method_wallet'] = 'Mobile wallet';
$string['ap_reference_label'] = 'Payment reference';
$string['ap_reference_placeholder'] = 'receipt / transfer no.';
$string['ap_note_label'] = 'Note (optional)';
$string['ap_submit'] = 'Assign package';
$string['ap_pkg_option'] = '{$a->name} — {$a->flex} Flex / {$a->price}';
$string['ap_no_packages'] = 'No active packages exist. Create one first.';
$string['ap_enter_student'] = 'Search and select a student.';
$string['ap_assigned'] = 'Assigned “{$a->name}” ({$a->flex} Flex) to {$a->student}.';

// Student hub — Flex banner + tabs.
$string['st_available_flex'] = 'Available Flex credits';
$string['st_book_up_to'] = 'You can book up to <b>{$a->count}</b> lesson(s).';
$string['st_no_active_pkg'] = 'No active package — buy one in the <b>Packages</b> tab to start booking.';
$string['tab_packages'] = 'Packages & Flex';

// Student packages tab.
$string['st_payment_history'] = 'Payment history';
$string['st_flex_history'] = 'Flex history';
$string['st_pkg_none_available'] = 'No packages available right now.';
$string['st_pkg_none'] = 'No packages yet.';
$string['st_pay_none'] = 'No payments yet.';
$string['st_flex_none'] = 'No Flex activity yet.';
$string['st_already_active_pkg'] = 'You already have an active package.';
$string['st_buy_package'] = 'Buy package';
$string['st_buy_title'] = 'Buy “{$a}”';
$string['st_buy_text'] = 'You will get {$a->flex} Flex for {$a->price} via secure checkout.';
$string['st_proceed_payment'] = 'Proceed to Payment';
$string['st_pkgmeta_flex'] = '{$a} Flex';
$string['st_pkgmeta_validdays'] = ' · valid {$a} days';
$string['st_pkgmeta_neverexp'] = ' · never expires';
$string['st_flex_left'] = '<span class="st-flex-pill">{$a->remaining}</span> left ({$a->used} / {$a->total})';

// Student table columns.
$string['st_col_package'] = 'Package';
$string['st_col_flexusedtot'] = 'Flex (used / total)';
$string['st_col_status'] = 'Status';
$string['st_col_activated'] = 'Activated';
$string['st_col_expires'] = 'Expires';
$string['st_col_date'] = 'Date';
$string['st_col_amount'] = 'Amount';
$string['st_col_method'] = 'Method';
$string['st_col_transaction'] = 'Transaction';
$string['st_col_type'] = 'Type';
$string['st_col_change'] = 'Change';
$string['st_col_balance'] = 'Balance';
$string['st_col_lesson'] = 'Lesson';
$string['st_col_note'] = 'Note';

// Statuses.
$string['pstat_active'] = 'Active';
$string['pstat_fully_used'] = 'Fully used';
$string['pstat_expired'] = 'Expired';
$string['pstat_cancelled'] = 'Cancelled';
$string['pstat_pending'] = 'Pending';
$string['pay_completed'] = 'Completed';
$string['pay_pending'] = 'Pending';
$string['pay_failed'] = 'Failed';
$string['pay_refunded'] = 'Refunded';
$string['flx_reserve'] = 'Reserved';
$string['flx_consume'] = 'Consumed';
$string['flx_return'] = 'Returned';
$string['flx_purchase'] = 'Purchased';
$string['flx_assign'] = 'Assigned';
$string['flx_expire'] = 'Expired';
$string['flx_adjust'] = 'Adjusted';

// Domain errors (raised by services).
$string['err_notfound'] = 'Package not found.';
$string['err_packagenotavailable'] = 'This package is not available for purchase.';
$string['err_alreadyhaspackage'] = 'You already have an active package.';
$string['err_studenthaspackage'] = 'The student already has an active package.';
$string['err_studentnotfound'] = 'Student not found.';
$string['err_noflex'] = 'No active package with available Flex.';
$string['err_packageinuse'] = 'This package has purchases and cannot be deleted; deactivate it instead.';
$string['err_nameflexrequired'] = 'A name and a positive Flex count are required.';
$string['err_postrequired'] = 'This action requires a POST request.';
$string['err_accessdenied'] = 'You do not have permission to perform this action.';
$string['err_unknownfunction'] = 'Unknown API function.';

// Privacy.
$string['privacy:metadata:nit_package_purchase'] = 'A student\'s purchases of Flex packages.';
$string['privacy:metadata:nit_package_purchase:userid'] = 'The student who owns the purchase.';
$string['privacy:metadata:nit_package_purchase:price_paid_minor'] = 'The amount paid, in minor currency units.';
$string['privacy:metadata:nit_package_purchase:timecreated'] = 'When the purchase was made.';
$string['privacy:metadata:nit_payment'] = 'Payment transactions for package purchases.';
$string['privacy:metadata:nit_payment:userid'] = 'The paying user.';
$string['privacy:metadata:nit_payment:amount_minor'] = 'The amount paid, in minor currency units.';
$string['privacy:metadata:nit_payment:timecreated'] = 'When the payment was recorded.';
$string['privacy:metadata:nit_flex_tx'] = 'The Flex balance ledger for a student.';
$string['privacy:metadata:nit_flex_tx:userid'] = 'The student whose balance changed.';
$string['privacy:metadata:nit_flex_tx:amount'] = 'The signed change to the Flex balance.';
$string['privacy:metadata:nit_flex_tx:timecreated'] = 'When the change happened.';
