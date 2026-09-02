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
 * English strings for local_nit_commerce. Labels match the reference local_academy plugin.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Commerce';
$string['managecoupons'] = 'Manage coupons';
$string['manageoffers']  = 'Manage offers';
$string['privacy:metadata'] = 'The NIT Commerce plugin stores discount coupons and offers defined by administrators; it does not store personal data by itself.';

// Shared UI.
$string['ui_refresh']      = 'Refresh';
$string['ui_loading']      = 'Loading…';
$string['ui_save']         = 'Save';
$string['ui_cancel']       = 'Cancel';
$string['ui_active']       = 'Active';
$string['ui_activate']     = 'Activate';
$string['ui_deactivate']   = 'Deactivate';
$string['ui_edit']         = 'Edit';
$string['ui_delete']       = 'Delete';
$string['ui_never']        = 'Never';
$string['ui_optional']     = '(optional)';
$string['ui_pager_info']   = 'Showing {from}–{to} of {total}';
$string['ui_showmore']     = '+{$a} more';
$string['ui_showless']     = 'Show less';
$string['pkg_col_status']  = 'Status';
$string['pkg_col_actions'] = 'Actions';
$string['pkg_field_name_en'] = 'Name (English)';
$string['pkg_field_name_ar'] = 'Name (Arabic)';
$string['pkg_field_desc_en'] = 'Description (English)';
$string['pkg_field_desc_ar'] = 'Description (Arabic)';
$string['sub_inactive']    = 'Inactive';

// Discount scope "all of type" labels.
$string['scope_all_course']       = 'All courses';
$string['scope_all_package']      = 'All packages';
$string['scope_all_subscription'] = 'All subscriptions';
$string['scope_all_program']      = 'All programs';

// Coupons.
$string['cpn_new']         = 'Create coupon';
$string['cpn_none']        = 'No coupons yet.';
$string['cpn_col_code']    = 'Code';
$string['cpn_col_type']    = 'Type';
$string['cpn_col_value']   = 'Value';
$string['cpn_col_scope']   = 'Applies to';
$string['cpn_col_usage']   = 'Usage';
$string['cpn_col_dates']   = 'Valid';
$string['cpn_col_max']     = 'Max discount';
$string['cpn_field_code']  = 'Coupon code';
$string['cpn_field_dtype'] = 'Discount type';
$string['cpn_field_value'] = 'Discount value';
$string['cpn_field_max']   = 'Max discount amount';
$string['cpn_field_utype'] = 'Usage type';
$string['cpn_field_limit'] = 'Usage limit';
$string['cpn_field_start'] = 'Start date';
$string['cpn_field_end']   = 'End date';
$string['cpn_field_scope'] = 'Applicable items';
$string['cpn_type_percent'] = 'Percentage';
$string['cpn_type_fixed']   = 'Fixed';
$string['cpn_usage_once']     = 'One-time';
$string['cpn_usage_multiple'] = 'Multiple use';
$string['cpn_scope_courses']       = 'Courses';
$string['cpn_scope_packages']      = 'Packages';
$string['cpn_scope_subscriptions'] = 'Subscriptions';
$string['cpn_scope_programs']      = 'Programs';
$string['cpn_scope_all']      = 'All';
$string['cpn_scope_specific'] = 'Selected';
$string['cpn_created']     = 'Coupon created';
$string['cpn_updated']     = 'Coupon updated';
$string['cpn_activated']   = 'Coupon activated';
$string['cpn_deactivated'] = 'Coupon deactivated';
$string['cpn_deleted']     = 'Coupon deleted';
$string['cpn_confirm_delete'] = 'Delete this coupon? This cannot be undone.';
$string['cpn_edit_titled']    = 'Edit coupon {$a}';
$string['cpn_scope_required'] = 'Select at least one applicable item.';
$string['cpn_unlimited']      = 'Unlimited';
$string['cpn_used_count']     = 'Used {$a}';

// Offers.
$string['ofr_new']        = 'Create offer';
$string['ofr_none']       = 'No offers yet.';
$string['ofr_col_name']   = 'Name';
$string['ofr_field_name'] = 'Offer name';
$string['ofr_created']     = 'Offer created';
$string['ofr_updated']     = 'Offer updated';
$string['ofr_activated']   = 'Offer activated';
$string['ofr_deactivated'] = 'Offer deactivated';
$string['ofr_deleted']     = 'Offer deleted';
$string['ofr_confirm_delete'] = 'Delete this offer? This cannot be undone.';
$string['ofr_edit_titled']    = 'Edit offer {$a}';
$string['ofr_delete_title']   = 'Delete offer';

// Errors.
$string['err_itemtype']            = 'Invalid item type.';
$string['err_itemnotfound']        = 'The requested item was not found.';
$string['err_discounttype']        = 'Discount type must be percentage or fixed.';
$string['err_discountvalue']       = 'Discount value cannot be negative.';
$string['err_discountpercent']     = 'A percentage discount must be between 0 and 100.';
$string['err_maxdiscount']         = 'Max discount cannot be negative.';
$string['err_daterange']           = 'The end date must be after the start date.';
$string['err_usagetype']           = 'Usage type must be one-time or multiple.';
$string['err_status']              = 'Status must be "active" or "inactive"';
$string['err_couponcoderequired']  = 'A coupon code is required.';
$string['err_couponcodetaken']     = 'That coupon code is already in use.';
$string['err_couponnotfound']      = 'Coupon not found.';
$string['err_couponinactive']      = 'This coupon is not active.';
$string['err_couponnotstarted']    = 'This coupon is not valid yet.';
$string['err_couponexpired']       = 'This coupon has expired.';
$string['err_couponnotapplicable'] = 'This coupon does not apply to this item.';
$string['err_couponusedup']        = 'This coupon has reached its usage limit.';
$string['err_couponalreadyusedbyuser'] = 'You have already used this coupon.';
$string['err_couponbusy'] = 'This coupon is being processed by another request. Please try again in a moment.';
$string['cleanupreservations'] = 'Release abandoned coupon reservations';
$string['err_couponhasusages']     = 'This coupon has been used and can only be deactivated.';
$string['err_offernamerequired']   = 'An offer name is required.';
$string['err_offernotfound']       = 'Offer not found.';
$string['err_offerhasusages']      = 'This offer has been used and can only be deactivated.';
$string['err_postrequired']        = 'This action requires POST';
$string['err_permissiondenied']    = 'Permission denied';
$string['err_unknownfunction']     = 'Unknown function';
$string['err_requestfailed']       = 'Request failed';
$string['err_sessionexpired']      = 'Session expired — please reload the page and log in again.';

// Shared checkout modal (course/subscription buy).
$string['co_title']         = 'Confirm your purchase';
$string['co_intro']         = 'You will be taken to secure checkout to complete the payment.';
$string['co_total']         = 'Total';
$string['co_offer']         = 'Offer';
$string['co_coupon']        = 'Coupon';
$string['co_apply']         = 'Apply';
$string['co_discount']      = 'Discount';
$string['co_method']        = 'Payment method';
$string['co_method_code']   = 'pay with a code';
$string['co_secure']        = 'Secure payment';
$string['co_proceed']       = 'Proceed to payment';
$string['co_cancel']        = 'Cancel';
$string['co_loading']       = 'Loading…';
$string['co_coupon_failed'] = 'Could not apply coupon.';
$string['co_currency']      = 'EGP';
$string['co_buy']           = 'Buy now';

// Public coupon-details page (/local/nit_commerce/coupon.php) and the "View details"
// button the home-page coupon cards link to it with.
$string['cpn_details']       = 'Coupon details';
$string['cpn_viewdetails']   = 'View details';
$string['cpn_allcoupons']    = 'Available coupons';
$string['cpn_notavailable']  = 'This coupon is not available.';
$string['cpn_about']         = 'About this coupon';
$string['cpn_code_label']    = 'Coupon code';
$string['cpn_copy']          = 'Copy code';
$string['cpn_copied']        = 'Copied';
$string['cpn_off_percent']   = '{$a}% off';
$string['cpn_off_fixed']     = '{$a} off';
$string['cpn_where']         = 'Where you can use it';
$string['cpn_where_none']    = 'No items are attached to this coupon yet.';
$string['cpn_courses_under'] = 'Courses included in {$a}';
$string['cpn_browse_all']    = 'Browse the catalogue';
$string['cpn_view_plan']     = 'View plan';
$string['cpn_open_course']   = 'Open';
$string['cpn_terms']         = 'Terms';
$string['cpn_uses_left']     = '{$a} uses left';
$string['cpn_uses_none']     = 'Fully redeemed';
$string['cpn_no_expiry']     = 'No expiry date';
$string['cpn_starts']        = 'Starts';
$string['cpn_expires']       = 'Expires';
$string['cpn_howto']         = 'Enter this code at checkout to get the discount.';
$string['cpn_stub_off']      = 'OFF';

// ── Coupon redemption report (AC-4.12.8) ──
$string['tab_manage']          = 'Coupons';
$string['tab_reports']         = 'Reports';
$string['rep_title']           = 'Coupon redemptions';
$string['rep_intro']           = 'Every coupon actually spent, with the learner, the order, the date and the amount discounted.';
$string['rep_none']            = 'No redemptions match these filters.';
$string['rep_filter_coupon']   = 'Coupon';
$string['rep_filter_all']      = 'All coupons';
$string['rep_filter_item']     = 'Item type';
$string['rep_filter_anyitem']  = 'Any item type';
$string['rep_filter_from']     = 'From';
$string['rep_filter_to']       = 'To';
$string['rep_filter_state']    = 'Orders';
$string['rep_state_confirmed'] = 'Paid only';
$string['rep_state_pending']   = 'Held (unpaid)';
$string['rep_state_all']       = 'All';
$string['rep_search']          = 'Search code, learner or order';
$string['rep_apply']           = 'Apply';
$string['rep_reset']           = 'Reset';
$string['rep_export']          = 'Export CSV';
$string['rep_col_date']        = 'Date';
$string['rep_col_code']        = 'Code';
$string['rep_col_learner']     = 'Learner';
$string['rep_col_order']       = 'Order';
$string['rep_col_orderstatus'] = 'Order status';
$string['rep_col_item']        = 'Item';
$string['rep_col_original']    = 'Price';
$string['rep_col_discount']    = 'Discounted';
$string['rep_col_paid']        = 'Paid';
$string['rep_col_redemptions'] = 'Redemptions';
$string['rep_col_learners']    = 'Learners';
$string['rep_col_last']        = 'Last used';
$string['rep_total']           = 'Total';
$string['rep_kpi_redemptions'] = 'Redemptions';
$string['rep_kpi_learners']    = 'Learners';
$string['rep_kpi_discounted']  = 'Total discounted';
$string['rep_kpi_net']         = 'Net collected';
$string['rep_bycoupon']        = 'By coupon';
$string['rep_alldetail']       = 'Every redemption';
$string['rep_held']            = 'Held';
$string['rep_noorder']         = 'No order';
$string['rep_heldnote']        = 'Held rows are seats reserved by a checkout that has not been paid. They count against the usage limit until the payment completes or the reservation is released.';

// ── Offer usage report (AC-4.13.7) ──
// The manage-offers page is two tabs: the offers themselves, and this.
$string['tab_offers']          = 'Offers';
$string['ofr_rep_title']       = 'Offer usage';
$string['ofr_rep_intro']       = 'How many times each offer was used and the orders it was applied to, with the learner, the date and the amount it took off.';
$string['ofr_rep_none']        = 'No offer usage matches these filters.';
$string['rep_filter_offer']    = 'Offer';
$string['rep_filter_alloffers'] = 'All offers';
$string['rep_col_usages']      = 'Times used';
$string['rep_col_offer']       = 'Offer';
$string['rep_byoffer']         = 'By offer';
$string['rep_allorders']       = 'Every order';

$string['ofr_rep_heldnote']    = 'Held rows belong to a checkout that has not been paid. They are shown so an offer\'s numbers can be reconciled against the gateway, but they are not sales until the payment completes.';

$string['ofr_col_usage']       = 'Usage';
$string['ofr_rep_open']        = 'View orders';
$string['ofr_lowest_note']     = 'Where more than one offer covers the same item, the one giving the learner the lowest price is the one applied — offers are never combined.';

// Coupon vs offer: only the larger discount is applied (AC-4.12.6).
$string['co_offer_won']     = 'Your code is valid, but the current offer saves you more, so the offer was applied.';
$string['co_coupon_won']    = 'Your code saves more than the current offer, so the code was applied instead.';
$string['co_notcombined']   = 'Offers and coupons are not combined — you always get the larger of the two.';

// The price moved between this sheet opening and Proceed being pressed (AC-4.13.6) — normally an
// offer that reached its end date. {old}/{new} are filled in by the modal, not by get_string.
$string['co_pricechanged']  = 'The price changed while this window was open: it was {old} and is now {new}. Nothing has been charged — press again to continue at the new price.';
$string['co_confirm_price'] = 'Confirm new price';
