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
 * English strings for local_nit_subscriptions. Labels match the reference local_academy plugin.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Subscriptions';
$string['managesubscriptions'] = 'Manage subscriptions';
$string['managecourses']       = 'Manage courses';
$string['privacy:metadata'] = 'The NIT Subscriptions plugin stores subscription plans and course-access rules defined by administrators; it does not store personal data by itself.';

// Manage courses (single-course purchases: list + "Unbuy").
$string['mc_desc']            = 'Users who bought a single course. Use "Unbuy" to unenrol a user and revoke the purchase. Unenrolling a buyer from the course itself revokes their purchase too, so they can buy it again.';
$string['mc_col_course']      = 'Course';
$string['mc_col_purchased']   = 'Purchased';
$string['mc_none']            = 'No course purchases yet.';
$string['mc_status_enrolled'] = 'Enrolled';
$string['mc_status_norole']   = 'No access';
$string['mc_status_revoked']  = 'Revoked';
$string['mc_status_refunded'] = 'Refunded';
$string['mc_unbuy']           = 'Unbuy';
$string['mc_unbuy_title']     = 'Revoke course purchase';
$string['mc_unbuy_confirm']   = 'Unenrol <b>{$a->user}</b> from <b>{$a->course}</b> and revoke this purchase?';
$string['mc_unbuy_confirm_norole'] = 'Revoke <b>{$a->user}</b>\'s purchase of <b>{$a->course}</b>? They are already unenrolled from it.';
$string['mc_unbuy_refund']    = 'Mark this purchase as refunded';
$string['mc_unbuy_success']   = 'The course purchase was revoked.';
$string['mc_course_deleted']  = '(deleted course)';
$string['mc_txn_notfound']    = 'Purchase not found.';
$string['mc_not_active']      = 'This purchase is not active and cannot be revoked.';

// Shared UI.
$string['ui_refresh']      = 'Refresh';
$string['ui_loading']      = 'Loading…';
$string['ui_save']         = 'Save';
$string['ui_cancel']       = 'Cancel';
$string['ui_edit']         = 'Edit';
$string['ui_delete']       = 'Delete';
$string['ui_activate']     = 'Activate';
$string['ui_deactivate']   = 'Deactivate';
$string['ui_active']       = 'Active';
$string['ui_never']        = 'Never';
$string['ui_optional']     = '(optional)';
$string['ui_remove']       = 'Remove';
$string['ui_search']       = 'Search';
$string['ui_showmore']           = '+{$a} more';
$string['ui_showless']           = 'Show less';
$string['ui_pager_info']   = 'Showing {from}–{to} of {total}';

// Package-shared column/field labels reused by the subscriptions page.
$string['pkg_col_id']        = 'ID';
$string['pkg_col_name']      = 'Name';
$string['pkg_col_price']     = 'Price';
$string['pkg_col_status']    = 'Status';
$string['pkg_col_actions']   = 'Actions';
$string['pkg_col_user']      = 'User';
$string['pkg_col_pricepaid'] = 'Price Paid';
$string['pkg_col_expiresat'] = 'Expires At';
$string['pkg_field_name']    = 'Name';
$string['pkg_field_price']   = 'Default price';
$string['pkg_field_name_en'] = 'Name (English)';
$string['pkg_field_name_ar'] = 'Name (Arabic)';
$string['pkg_field_desc_en'] = 'Description (English)';
$string['pkg_field_desc_ar'] = 'Description (Arabic)';
$string['pkg_field_currency'] = 'Default currency';
$string['pkg_unassign_paid'] = ' — <strong>{$a}</strong> paid';

// Per-country pricing (managed inside the create/edit subscription form).
$string['sub_prices_heading']     = 'Country prices';
$string['sub_prices_help']        = 'Optional. Set a price for specific countries; buyers elsewhere pay the default price above.';
$string['sub_price_add']          = '+ Add country price';
$string['sub_price_country']      = 'Country';
$string['sub_price_currency']     = 'Currency';
$string['sub_price_amount']       = 'Price';
$string['sub_price_active']       = 'Active';
$string['sub_price_remove']       = 'Remove';
$string['sub_price_pickcountry']  = 'Choose country…';

// (Legacy strings from the old standalone pricing page — kept for compatibility.)
$string['sub_pricing']            = 'Pricing';
$string['subscriptionpricing']    = 'Subscription pricing';
$string['backtosubscriptions']    = 'Back to subscriptions';
$string['price_country']          = 'Country';
$string['price_currency']         = 'Currency';
$string['price_amount']           = 'Price';
$string['price_is_active']        = 'Active';
$string['price_add']              = 'Add country price';
$string['price_edit']             = 'Edit country price';
$string['price_none']             = 'No country-specific prices yet. Buyers pay the default price above.';
$string['price_saved']            = 'Price saved.';
$string['price_deleted']          = 'Price deleted.';
$string['price_confirmdelete']    = 'Delete this country price?';
$string['price_default_notice']   = 'Default price (used when a buyer\'s country has no price below): <strong>{$a->price} {$a->currency}</strong>. Edit it on the subscription plan itself.';

// Subscription plans.
$string['sub_plans_heading']  = 'Subscription plans';
$string['sub_new']            = 'New subscription';
$string['sub_col_days']       = 'Days';
$string['sub_col_courses']    = 'Courses';
$string['sub_col_subscription'] = 'Subscription';
$string['sub_field_desc']     = 'Description (optional)';
$string['sub_field_days']     = 'Number of days';
$string['sub_field_b2b']      = 'B2B purchase available';
$string['sub_seat_options']   = 'Seat options';
$string['sub_seat_options_help'] = 'Add one or more user-capacity options, each with its own discount %. The B2B price is calculated as (normal price × seats) − discount.';
$string['sub_col_seats']      = 'Seats';
$string['sub_col_discount']   = 'Discount %';
$string['sub_col_b2bprice']   = 'B2B price';
$string['sub_seat_add']       = 'Add seat option';
$string['sub_b2b_badge']      = 'B2B';

// Course availability.
$string['sub_courseavail_heading'] = 'Course subscription availability';
$string['sub_courseavail_desc']    = 'Choose courses and append them to a specific subscription.';
$string['sub_target']              = 'Target Subscription:';
$string['sub_select_placeholder']  = 'Select a subscription...';
$string['sub_save_courses']        = 'Save courses to subscription';
$string['sub_courses_search']      = 'Search courses…';
$string['sub_selectall']           = 'Select all';
$string['sub_clear']               = 'Clear';
$string['sub_ca_pickplan']        = 'Pick a subscription plan above to choose which courses it unlocks.';
$string['sub_ca_counter']         = '{$a->selected} of {$a->total} courses selected';
$string['sub_ca_unsaved']         = 'Unsaved changes';
$string['sub_ca_reset']           = 'Reset';
$string['sub_ca_onlyselected']    = 'Selected only';
$string['sub_ca_nomatch']         = 'No courses match this filter.';
$string['sub_ca_catall']          = 'All';
$string['sub_ca_catnone']         = 'None';
$string['sub_ca_discard']         = 'You have unsaved course changes for this plan. Switch anyway and lose them?';
$string['sub_ca_catcount']        = '{$a->selected}/{$a->total}';

// User subscriptions.
$string['sub_usersubs_heading']    = 'User Subscriptions';
$string['sub_usersubs_desc']       = 'Manage active and expired user subscriptions.';
$string['sub_unsub_title']         = 'Unsubscribe user';
$string['sub_unsub_refund']        = 'Refund payment to student';
$string['sub_unsubscribe']         = 'Unsubscribe';
$string['sub_none_admin']          = 'No subscriptions yet.';
$string['sub_inactive']            = 'Inactive';
$string['sub_edit_titled']         = 'Edit subscription #{$a}';
$string['sub_updated']             = 'Subscription updated.';
$string['sub_created']             = 'Subscription created.';
$string['sub_activated']           = 'Activated.';
$string['sub_deactivated']         = 'Deactivated.';
$string['sub_deleted']             = 'Deleted.';
$string['sub_confirm_delete']      = 'Delete this subscription? Only possible if it was never purchased. This cannot be undone.';
$string['sub_no_categories']       = 'No categories with courses found.';
$string['sub_select_target']       = 'Please select a target subscription.';
$string['sub_courses_assigned']    = 'Courses assigned successfully.';
$string['sub_no_usersubs']         = 'No user subscriptions found.';
$string['sub_unsub_confirm']       = 'Unsubscribe <strong>{$a->user}</strong> from <strong>{$a->name}</strong>{$a->price}? This cannot be undone.';
$string['sub_unsub_success']       = 'User unsubscribed successfully.';

// Subscription statuses.
$string['sstat_active']         = 'Active';
$string['sstat_expired']        = 'Expired';
$string['sstat_cancelled']      = 'Cancelled';
$string['sstat_pending']        = 'Pending';
$string['sstat_payment_failed'] = 'Payment failed';

// Errors.
$string['err_subnamerequired']  = 'Subscription name is required';
$string['err_subnameempty']     = 'Subscription name cannot be empty';
$string['err_pricenegative']    = 'Price cannot be negative';
$string['err_pricepositive']    = 'Price must be greater than zero';
$string['err_pricecountry']     = 'Please choose a valid country';
$string['err_priceonepercountry'] = 'This subscription already has a price for that country';
$string['err_currency']         = 'Please choose a valid currency';
$string['err_durationpositive'] = 'Number of days must be greater than zero';
$string['err_subnotfound']      = 'Subscription not found';
$string['err_subhaspurchases']  = 'This subscription has purchase records and cannot be deleted. Deactivate it instead.';
$string['err_coursenotfound']   = 'Course not found';
$string['err_seatspositive']    = 'Number of seats must be greater than zero';
$string['err_discountrange']    = 'Discount percentage must be between 0 and 100';
$string['err_status']           = 'Status must be "active" or "inactive"';
$string['err_postrequired']     = 'This action requires POST';
$string['err_permissiondenied'] = 'Permission denied';
$string['err_unknownfunction']  = 'Unknown function';
$string['err_requestfailed']    = 'Request failed';
$string['err_sessionexpired']   = 'Session expired — please reload the page and log in again.';
$string['err_paymentsunavailable'] = 'The payment gateway is not available on this site.';
$string['err_alreadyhassubscription'] = 'You already have an active subscription.';
$string['err_checkoutfailed']   = '{$a}';

// Buy modal (home-page block checkout).
$string['sub_confirm_title']   = 'Confirm your subscription';
$string['sub_confirm_intro']   = 'You are about to subscribe to this plan. You will be taken to secure checkout to complete the payment.';
$string['sub_duration_label']  = 'Duration';
$string['sub_total_label']     = 'Total';
$string['sub_coupon_label']    = 'Coupon';
$string['sub_coupon_apply']    = 'Apply';
$string['sub_discount_label']  = 'Discount';
$string['sub_secure_kashier']  = 'Secure payment via Kashier';
$string['sub_proceed_payment'] = 'Proceed to payment';
$string['sub_buy']             = 'Subscribe';
$string['enrolled']            = 'You are now enrolled in this course.';

// Scheduled task: end subscriptions past their deadline.
$string['task_expire_subscriptions'] = 'Expire finished subscriptions and unenrol students';

// Country-gated pricing. Normally the wording comes from local_payments (which carries the
// Arabic too, and keeps courses and plans saying the same thing); these are the standalone
// fallbacks used only when that plugin is not installed.
$string['countryrequired'] = 'Set your country to see the price';
$string['countryrequired_desc'] = 'Prices are set per country. Add your country to your profile to see this plan\'s price and subscribe.';
$string['countryrequired_action'] = 'Add your country';
$string['sub_refund_rule'] = 'Refund policy';
$string['sub_field_refundhours'] = 'Refund window (hours)';
$string['sub_field_refundfee'] = 'Refund fee (%)';
$string['sub_refundfee_help'] = 'A percentage of whatever the buyer paid, so one number covers every currency and follows any discount. Leave both blank to use the site refund policy.';

// Public plan-details page (/local/nit_subscriptions/plan.php) and the "View details"
// button the home-page plan cards link to it with.
$string['plan_details']        = 'Plan details';
$string['plan_viewdetails']    = 'View details';
$string['plan_allplans']       = 'Subscription plans';
$string['plan_notavailable']   = 'This subscription plan is not available.';
$string['plan_about']          = 'About this plan';
$string['plan_included']       = 'Courses included in this plan';
$string['plan_included_none']  = 'No courses have been attached to this plan yet.';
$string['plan_included_count'] = '{$a} courses unlocked';
$string['plan_included_one']   = '1 course unlocked';
$string['plan_perdays']        = 'for {$a} days';
$string['plan_duration']       = 'Duration';
$string['plan_days']           = '{$a} days';
$string['plan_b2b']            = 'Available for teams';
$string['plan_seats']          = 'Team pricing';
$string['plan_seats_intro']    = 'Buy several seats at once and pay less per seat.';
$string['plan_seats_col']      = 'Seats';
$string['plan_seats_save']     = 'You save';
$string['plan_seats_price']    = 'Total';
$string['plan_current']        = 'Your current plan';
$string['plan_daysleft']       = '{$a} days left';
$string['plan_activenow']      = 'Active';
$string['plan_otheractive']    = 'You already have an active subscription, so this plan cannot be bought right now.';
$string['plan_opencourse']     = 'Open';
$string['plan_price_from']     = 'Price';
$string['plan_login_tosubscribe'] = 'Log in to subscribe';
$string['plan_offer']          = 'Offer';

// ── Renewal reminders ───────────────────────────────────────────────────────────
// Admin tab that decides how long before a subscription runs out the subscriber is
// warned — and, on the same window, when the Renew button appears on their plan card.
$string['messageprovider:subscriptionreminder'] = 'Subscription about to expire';
$string['task_send_subscription_reminders'] = 'Send subscription expiry reminders';

$string['tab_plans']     = 'Plans & pricing';
$string['tab_courses']   = 'Course availability';
$string['tab_users']     = 'User subscriptions';
$string['tab_reminders'] = 'Renewal reminders';

$string['rem_heading']   = 'Renewal reminders';
$string['rem_desc']      = 'Warn subscribers before their plan runs out, and let them renew early. A reminder goes out once for each lead time below, and the Renew button appears on their plan card from the earliest one onwards.';
$string['rem_enabled']   = 'Send expiry reminders';
$string['rem_enabled_help'] = 'Turn this off to stop all reminders and hide the Renew button. Nothing else is lost — the lead times stay saved.';
$string['rem_days']      = 'Send a reminder this many days before the plan ends';
$string['rem_days_help'] = 'Add one entry per warning, for example 7, 3 and 1. Between 1 and {$a} days.';
$string['rem_days_add']  = 'Add a lead time';
$string['rem_days_none'] = 'No lead times yet — add at least one.';
$string['rem_day_unit']  = 'days before expiry';
$string['rem_remove']    = 'Remove';
$string['rem_save']      = 'Save and apply now';
$string['rem_applied']   = 'Saved. {$a->sent} reminder(s) sent now; {$a->cleared} old reminder record(s) cleared.';
$string['rem_preview']   = 'Right now this would notify {$a->due} of {$a->active} active subscriber(s).';
$string['rem_window_note'] = 'The Renew button uses the largest lead time: {$a} days before the plan ends.';
$string['rem_window_off']  = 'Reminders are off, so no Renew button is shown.';
$string['rem_recalc_note'] = 'Saving re-checks every live subscription straight away: anyone the new window covers is notified now, and reminder records for lead times you removed are cleared so they can fire again if you add them back.';
$string['rem_col_days']  = 'Lead time';

$string['err_reminderdaysrequired'] = 'Add at least one lead time, or switch reminders off.';

// The notification itself. {$a->plan}, {$a->days} and {$a->expires} are filled in per recipient.
$string['reminder_msg_subject'] = 'Your subscription "{$a->plan}" ends in {$a->days} day(s)';
$string['reminder_msg_body']    = 'Your subscription "{$a->plan}" ends on {$a->expires} — that is {$a->days} day(s) from now. Renew before then and the new period starts the day the current one ends, so you lose no time.';
$string['reminder_msg_small']   = 'Your subscription ends in {$a->days} day(s).';
$string['reminder_msg_action']  = 'Renew your subscription';

// Renewing, on the plan card and the plan page.
$string['sub_renew']            = 'Renew now';
$string['sub_renew_endsin']     = 'Ends in {$a} day(s)';
$string['sub_renew_note']       = 'Renewing now extends your plan from the day it currently ends — you lose none of the time you have left.';
$string['sub_renew_confirm']    = 'Confirm your renewal';
$string['sub_renew_newexpiry']  = 'New end date';
$string['plan_accessuntil']  = 'Access until {$a}';
$string['plan_includesrenewal'] = 'includes the renewal you already paid for';
