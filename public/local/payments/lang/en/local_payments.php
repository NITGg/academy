<?php
defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'Payments';
$string['subplugintype_paymentprovider'] = 'Payment provider';
$string['subplugintype_paymentprovider_plural'] = 'Payment providers';

// Free-course self-registration.
$string['registerfree'] = 'Register for free';
$string['freecourseintro'] = 'This course is free. Click below to register and start learning.';
$string['freeenrolled'] = 'You are now enrolled. Enjoy the course!';

// Settings.
$string['generalsettings'] = 'Payment settings';
$string['default_country'] = 'Default country';
$string['default_country_desc'] = 'Fallback country code (ISO 3166-1 alpha-2) when detection fails.';
$string['default_currency'] = 'Default currency';
$string['default_currency_desc'] = 'Default currency code (ISO 4217).';
$string['payment_ttl'] = 'Payment timeout (seconds)';
$string['payment_ttl_desc'] = 'How long a pending payment remains valid before auto-expiring. Default: 1800 (30 minutes).';
$string['show_sale_badge'] = 'Show sale badge';
$string['show_sale_badge_desc'] = 'Display sale discount badge on course cards.';
$string['auto_expire_enabled'] = 'Auto-expire pending payments';
$string['auto_expire_enabled_desc'] = 'Automatically expire pending payments after the timeout period.';
$string['course_preview'] = 'Locked course preview';
$string['course_preview_desc'] = 'Let anyone — including visitors who are not logged in — open a course page and read its description and activity list, with every activity locked until they enrol or buy. Turn this off to go back to sending them to the login or buy page instead.';

// Locked course preview.
$string['preview_notice'] = 'You are previewing this course. The activities unlock once you have access.';
$string['preview_unlock'] = 'Unlock this course';
$string['preview_login'] = 'Log in to continue';
$string['expired_notice'] = 'Your access to this course has ended. Renew to continue where you left off — your progress has been saved.';
$string['expired_renew'] = 'Renew';
$string['preview_locked'] = 'This activity is locked until you have access to the course.';

// Free preview lessons (AC-4.9.5).
$string['freepreview'] = 'Free preview';
$string['freepreview_label'] = 'Let visitors play this lesson before enrolling';
$string['freepreview_help'] = 'Tick this to make the activity a free sample of the course.

Anyone reading the course page — including visitors who are not logged in — can open and play it, and it shows on the course page as a normal link with a **Free preview** badge instead of a padlock.

Every activity that is not ticked stays locked until the student enrols or buys the course. Hidden activities are never previewable, whatever this setting says.';
$string['freepreview_badge'] = 'Free preview';
$string['freepreview_lockedlesson'] = 'Enrol in this course to unlock this lesson.';
$string['manageproviders'] = 'Manage payment providers';
$string['providersettings'] = 'Provider settings';
$string['reports'] = 'Payment reports';

// Course pricing.
$string['coursepricing'] = 'Course pricing';
$string['addprice'] = 'Add pricing rule';
$string['editprice'] = 'Edit pricing rule';
$string['noprices'] = 'No pricing rules configured for this course.';
$string['pricesaved'] = 'Pricing rule saved.';
$string['pricedeleted'] = 'Pricing rule deleted.';
$string['confirmdeleteprice'] = 'Are you sure you want to delete this pricing rule?';
$string['defaultprice'] = 'Default (all countries)';

// Form fields.
$string['country'] = 'Country';
$string['currency'] = 'Currency';
$string['price'] = 'Price';
$string['sale_price'] = 'Sale price';
$string['start_date'] = 'Start date';
$string['end_date'] = 'End date';
$string['is_default'] = 'Default price';
$string['is_active'] = 'Active';
$string['priority'] = 'Priority';
$string['actions'] = 'Actions';

// Validation.
$string['error_price_positive'] = 'Price must be greater than zero.';
$string['error_sale_price_lower'] = 'Sale price must be lower than the regular price.';
$string['error_end_after_start'] = 'End date must be after start date.';
$string['error_one_default'] = 'Only one default price is allowed per course.';
$string['error_one_active_per_country'] = 'There is already an active pricing rule for this country. Deactivate it first, or edit that rule instead.';

// Course display.
$string['enrolled'] = 'Enrolled';
$string['purchased'] = 'Purchased';
$string['sale'] = 'Sale';
$string['buynow'] = 'Subscribe';
$string['buycourse'] = 'Subscribe Now';
$string['free'] = 'Free';
$string['entercourse'] = 'Join';
$string['already_enrolled'] = 'You are enrolled in this course';
$string['already_purchased'] = 'You have purchased this course';
$string['secure_checkout'] = 'Secure checkout powered by trusted payment providers';
$string['covered_by_subscription'] = 'Covered by your subscription';
$string['enroll'] = 'Enroll';
$string['renew_subscription'] = 'Renew your subscription';

// Payment flow.
$string['paymentfor'] = 'Payment for: {$a}';
$string['payment_success'] = 'Payment Successful';
$string['payment_success_message'] = 'Your payment has been processed successfully. You now have access to the course.';
$string['payment_failure'] = 'Payment Failed';
$string['payment_failure_message'] = 'Your payment could not be processed. Please try again or contact support.';
$string['gotocourse'] = 'Go to Course';
$string['viewhistory'] = 'View Payment History';
$string['tryagain'] = 'Try Again';

// History.
$string['paymenthistory'] = 'Payment History';
$string['nopayments'] = 'You have no payment records.';
$string['orderid'] = 'Order ID';
$string['course'] = 'Course';
$string['amount'] = 'Amount';
$string['status'] = 'Status';
$string['paymentmethod'] = 'Payment Method';
$string['invoice'] = 'Invoice';
$string['date'] = 'Date';

// Reports.
$string['total_revenue'] = 'Total Revenue';
$string['total_transactions'] = 'Total Transactions';
$string['pending'] = 'Pending';
$string['failedpayments'] = 'Failed';
$string['refunds'] = 'Refunds';
$string['revenue_by_country'] = 'Revenue by Country';
$string['revenue_by_provider'] = 'Revenue by Provider';
$string['top_selling_courses'] = 'Top Selling Courses';
$string['transactions'] = 'Transactions';
$string['revenue'] = 'Revenue';
$string['provider'] = 'Provider';
$string['purchases'] = 'Purchases';

// Events.
$string['event_payment_completed'] = 'Payment completed';
$string['event_payment_created'] = 'Payment created';
$string['event_payment_failed'] = 'Payment failed';
$string['event_refund_processed'] = 'Refund processed';

// Tasks.
$string['task_expire_pending'] = 'Expire pending payments';

// Messages.
$string['payment_confirmation_subject'] = 'Payment Confirmed: {$a}';
$string['payment_confirmation_body'] = 'Your payment for "{$a->coursename}" has been confirmed.

Amount: {$a->amount} {$a->currency}
Order ID: {$a->order_id}

You now have full access to the course.';
$string['payment_confirmation_html'] = '<p>Your payment for <strong>{$a->coursename}</strong> has been confirmed.</p>
<p>Amount: <strong>{$a->amount} {$a->currency}</strong><br>Order ID: <code>{$a->order_id}</code></p>
<p>You now have full access to the course.</p>';
$string['payment_confirmation_small'] = 'Payment confirmed for {$a}';
$string['messageprovider:payment_confirmation'] = 'Payment confirmation notifications';

// Errors.
$string['nopricefound'] = 'No pricing rule found for this course in your region.';
$string['noproviderfound'] = 'No payment provider available for your region.';
$string['alreadypurchased'] = 'You have already purchased this course.';
$string['alreadyenrolled'] = 'You are already enrolled in this course.';
$string['paymentinitiationfailed'] = 'Payment could not be initiated: {$a}';
$string['transactionnotfound'] = 'Transaction not found.';
$string['enrolpluginnotinstalled'] = 'The enrolment plugin "{$a}" is not installed.';

// Privacy.
$string['privacy:metadata:transactions'] = 'Payment transaction records.';
$string['privacy:metadata:transactions:userid'] = 'The user who made the payment.';
$string['privacy:metadata:transactions:courseid'] = 'The course being purchased.';
$string['privacy:metadata:transactions:amount'] = 'The payment amount.';
$string['privacy:metadata:transactions:currency'] = 'The payment currency.';
$string['privacy:metadata:transactions:status'] = 'The payment status.';
$string['privacy:metadata:transactions:ip_address'] = 'The IP address of the user.';
$string['privacy:metadata:transactions:customer_email'] = 'The email sent to the payment provider.';
$string['privacy:metadata:invoices'] = 'Payment invoice records.';
$string['privacy:metadata:invoices:userid'] = 'The user the invoice belongs to.';
$string['privacy:metadata:invoices:invoice_number'] = 'The invoice number.';
$string['privacy:metadata:invoices:amount'] = 'The invoice amount.';

// Capabilities.
$string['payments:purchasecourse'] = 'Purchase a course';
$string['payments:viewownhistory'] = 'View own payment history';
$string['payments:managecoursepricing'] = 'Manage course pricing';
$string['payments:viewreports'] = 'View payment reports';
$string['payments:managerefunds'] = 'Manage refunds';
$string['payments:manageproviders'] = 'Manage payment providers';
$string['payments:viewalltransactions'] = 'View all transactions';
$string['payments:viewauditlogs'] = 'View audit logs';

// Country-gated pricing: a signed-in account with no profile country is shown no price at all
// (see local_payments\country_detector::pricing_blocked) and cannot reach checkout.
$string['countryrequired'] = 'Set your country to see the price';
$string['countryrequired_desc'] = 'Prices are set per country. Add your country to your profile to see the price for this course and continue to checkout.';
$string['countryrequired_action'] = 'Add your country';

// Offline payment reference (Fawry / Meeza / wallet codes).
$string['reference_title'] = 'Almost done — pay with this code';
$string['reference_lead'] = 'Your order is reserved. Pay the code below to complete it.';
$string['reference_lead_method'] = 'Your order is reserved. Pay the code below using {$a} to complete it.';
$string['reference_copy'] = 'Copy';
$string['reference_copied'] = 'Copied';
$string['reference_amount'] = 'Amount';
$string['reference_item'] = 'For';
$string['reference_expires'] = 'Pay before';
$string['reference_order'] = 'Order';
$string['reference_note'] = 'Access is granted automatically as soon as the payment is confirmed — usually within a few minutes of paying. You can close this page; the code is saved in your payment history.';
$string['reference_check'] = 'I have paid — check now';
$string['reference_history'] = 'Payment history';
$string['reference_pending'] = 'We have not received this payment yet. If you have just paid, give it a few minutes and check again.';

// Invoice PDF.
$string['invoice_download'] = 'Download invoice';
$string['invoice_download_en'] = 'Download the invoice in English';
$string['invoice_download_ar'] = 'Download the invoice in Arabic';
$string['invoice_lang_en'] = 'English';
$string['invoice_lang_ar'] = 'العربية';
$string['invoice_notavailable'] = 'There is no invoice for this order, because the payment was not completed.';
$string['invoice_title'] = 'Invoice';
$string['invoice_number'] = 'Invoice number';
$string['invoice_date'] = 'Invoice date';
$string['invoice_from'] = 'From';
$string['invoice_to'] = 'Billed to';
$string['invoice_description'] = 'Description';
$string['invoice_item'] = 'Course purchase';
$string['invoice_subtotal'] = 'Subtotal';
$string['invoice_discount'] = 'Discount';
$string['invoice_total'] = 'Total paid';
$string['invoice_paid_on'] = 'Paid on';
$string['invoice_footer_default'] = 'Thank you for your purchase.';
$string['invoice_seller_name'] = 'Invoice: seller name';
$string['invoice_seller_name_desc'] = 'The name printed as the issuer on invoice PDFs. Leave empty to use the site name.';
$string['invoice_seller_details'] = 'Invoice: seller details';
$string['invoice_seller_details_desc'] = 'Address, tax registration number, and anything else that must appear under the seller name. One item per line.';
$string['invoice_footer'] = 'Invoice: footer note';
$string['invoice_footer_desc'] = 'Printed in small text at the bottom of every invoice. Leave empty for a simple thank-you.';
$string['invoice_logo'] = 'Invoice: logo';
$string['invoice_logo_desc'] = 'Printed in the top corner of every invoice PDF. PNG or JPG; it is scaled to 18mm tall, so a wide logo is fine but a tall one will be small. Leave empty for a text-only header.';

// Payment lists for staff.
$string['alltransactions'] = 'All payments';
$string['coursepayments'] = 'Course payments';
$string['searchpayments'] = 'Student name, email or order ID';
$string['allstatuses'] = 'Any status';
$string['student'] = 'Student';
$string['provider'] = 'Gateway';
$string['payments:viewcoursepayments'] = 'View the payments made for a course';

// Payment history: filters and status labels.
$string['searchmypayments'] = 'Order or invoice number';
$string['allcourses'] = 'All courses';
$string['datefrom'] = 'From';
$string['dateto'] = 'To';
$string['nopaymentsmatch'] = 'No payments match this filter.';
$string['status_pending'] = 'Pending';
$string['status_completed'] = 'Completed';
$string['status_failed'] = 'Failed';
$string['status_cancelled'] = 'Cancelled';
$string['status_expired'] = 'Expired';
$string['status_timed_out'] = 'Timed out';
$string['status_refunded'] = 'Refunded';
$string['status_partially_refunded'] = 'Partially refunded';
$string['status_voided'] = 'Voided';
$string['status_chargeback'] = 'Chargeback';
$string['status_duplicate'] = 'Duplicate';

// Refunds.
$string['refund_heading'] = 'Refunds';
$string['refund_heading_desc'] = 'How long a buyer has to change their mind, and what it costs them. Each thing you sell gets its own window because they are not comparable: a subscription starts billing at once, a course is consumed over weeks. Outside the window the buyer can still ask, and a member of staff decides.';
$string['refund_enabled'] = 'Allow refunds';
$string['refund_enabled_desc'] = 'Off by default. With this off, no refund button appears anywhere and no request can be made.';
$string['refund_group_course'] = 'Refunds: courses';
$string['refund_group_course_desc'] = 'Applies to a one-off course purchase.';
$string['refund_group_subscription'] = 'Refunds: subscriptions';
$string['refund_group_subscription_desc'] = 'Applies to subscription plans, including B2B seat purchases.';
$string['refund_group_default'] = 'Refunds: everything else';
$string['refund_group_default_desc'] = 'Used for any other kind of purchase &mdash; packages today, whatever is added later. Nothing that takes a payment is left without a policy.';
$string['refund_hours'] = 'Refund window (hours)';
$string['refund_hours_desc'] = 'Hours from the moment the payment completed during which the buyer can refund themselves, with no staff involvement. Hours rather than days so that both "the first 24 hours" and "the first two weeks" (336) are expressible. <b>Set 0 for no automatic window</b> &mdash; the buyer then has to ask, and a member of staff decides.';

// Refunds, buyer-facing.
$string['refund_column'] = 'Refund';
$string['refund_request'] = 'Refund';
$string['refund_now_title'] = 'Refund this payment';
$string['refund_ask_title'] = 'Ask for a refund';
$string['refund_now_button'] = 'Refund';
$string['refund_ask_button'] = 'Request refund';
$string['refund_now_notice'] = 'This payment is inside its refund window, which closes {$a}. The refund is made straight away and access is removed.';
$string['refund_ask_notice_closed'] = 'The automatic refund window for this payment closed on {$a}, so this goes to our team to decide. You will be notified either way.';
$string['refund_ask_notice_nowindow'] = 'This purchase has no automatic refund window, so your request goes to our team to decide. You will be notified either way.';
$string['refund_paid'] = 'You paid';
$string['refund_youget'] = 'Refund amount';
$string['refund_after_fee'] = 'after a {$a} fee';
$string['refund_reason_optional'] = 'Reason (optional)';
$string['refund_reason_required'] = 'Why are you asking for a refund?';
$string['refund_requested'] = 'Your refund request has been sent. We will let you know what is decided.';
$string['refund_done'] = 'Refunded {$a->amount}. It should reach your account within a few working days.';
$string['refund_rejected'] = 'The request was declined and the buyer has been told.';

// Refunds, staff-facing.
$string['refund_requests'] = 'Refund requests';
$string['refund_norequests'] = 'Nothing here.';
$string['refund_decide'] = 'Decision';
$string['refund_decision'] = 'Decided by';
$string['refund_approve'] = 'Approve';
$string['refund_reject'] = 'Decline';
$string['refund_note_placeholder'] = 'Note for the buyer';
$string['refund_status_pending'] = 'Awaiting a decision';
$string['refund_status_approved'] = 'Approved';
$string['refund_status_rejected'] = 'Declined';

// Refunds, refusals.
$string['refund_err_disabled'] = 'Refunds are not currently offered.';
$string['refund_err_notrefundable'] = 'This payment cannot be refunded. Only a completed payment can be, and only once.';
$string['refund_err_alreadyasked'] = 'There is already a refund request for this payment, waiting on a decision.';
$string['refund_err_windowclosed'] = 'The refund window for this payment has closed. Please request a refund instead.';
$string['refund_err_needreason'] = 'Please say why you are asking for a refund.';
$string['refund_err_decided'] = 'That request has already been decided.';
$string['refund_err_gateway'] = 'The payment gateway used for this payment cannot process refunds automatically. Raise it in the gateway dashboard instead.';
$string['refund_err_gatewayfailed'] = 'The payment gateway refused the refund. Nothing has been taken back; check the payment logs.';
$string['refund_err_noreference'] = 'This payment has no gateway reference recorded, so it cannot be refunded automatically.';

// Refunds, notifications.
$string['messageprovider:refund_decision'] = 'Refund request decisions';
$string['refund_msg_approved_subject'] = 'Your refund has been approved';
$string['refund_msg_approved_body'] = 'Your refund for order {$a->order} has been approved and the money is on its way back to you. Note: {$a->note}';
$string['refund_msg_rejected_subject'] = 'About your refund request';
$string['refund_msg_rejected_body'] = 'Your refund request for order {$a->order} was not approved. Reason: {$a->note}';

// Refunds, the policy as a sentence (refund_policy::describe).
$string['refund_policy_norwindow'] = 'No automatic refunds. You can still ask for one, and our team will decide.';
$string['refund_policy_windowfree'] = 'Full refund within {$a->hours} hours of purchase.';
$string['refund_policy_windowfee'] = 'Refundable within {$a->hours} hours of purchase, less a {$a->fee} fee.';

// Refunds, staff-initiated from the payments list.
$string['refund_staff_title'] = 'Refund this payment';
$string['refund_staff_warning'] = 'This refunds the buyer immediately and removes their access. The money leaves the gateway at once and cannot be recalled from here.';
$string['refund_staff_applyfee'] = 'Charge the policy refund fee of {$a}. Leave unticked for a full refund.';
$string['refund_staff_button'] = 'Refund now';

// Refunds, per-item override.

// Refunds, the currency a flat fee is stated in.

// Refund fee set on a price rule.

// Refund terms set on a price rule.
$string['refund_feepercent'] = 'Default refund fee (%)';
$string['refund_feepercent_desc'] = 'A percentage of the amount paid, kept when a refund is given. Used when the price rule does not set a fee of its own. It is a percentage rather than a flat amount on purpose: a percentage is the same policy in every currency, whereas a flat amount would mean two different charges to buyers paying in EGP and USD. Set a flat amount on the price rule instead, where the currency is already known. 0 refunds in full.';

// Payment method picker on the web checkout.
$string['choose_method'] = 'How would you like to pay?';
$string['continue_to_payment'] = 'Continue to payment';
$string['method_gives_code'] = 'You will get a code to pay with';

// The price moved between opening checkout and confirming it (AC-4.13.6) — usually an automatic
// offer that reached its end date while the buyer was still deciding.
$string['pricechanged_title'] = 'The price has changed';
$string['pricechanged_up'] = 'An offer on this item ended while you were checking out, so the price is no longer the one you were shown. Nothing has been charged. Please confirm the new price to continue.';
$string['pricechanged_down'] = 'The price of this item changed while you were checking out, and it is now lower than the one you were shown. Nothing has been charged. Please confirm the new price to continue.';
$string['pricechanged_was'] = 'You were shown';
$string['pricechanged_now'] = 'Price now';
$string['pricechanged_confirm'] = 'Confirm and continue';
$string['pricechanged_desc'] = 'The price changed while you were checking out: it was {$a->old} and is now {$a->new}. Nothing has been charged — please confirm the new price to continue.';

// Refund terms for one course, set on its pricing page.
$string['refund_terms_heading'] = 'Refund policy for this course';
$string['refund_terms_intro'] = 'Leave both blank to use the site policy, which is currently a {$a->hours} hour window with a {$a->fee}% fee.';
$string['refund_terms_inherit'] = 'Site policy';
$string['refund_terms_saved'] = 'Refund policy saved for this course.';
$string['refund_terms_offsitewide'] = 'Refunds are switched off site-wide, so nothing set here takes effect until that changes.';
$string['refund_terms_help'] = 'The fee is a percentage of whatever the buyer actually paid, so one number covers every currency this course is priced in and follows any discount they used. Set the window to 0 to allow no automatic refund &mdash; the buyer then has to ask, and a member of staff decides.';
$string['refund_feerow'] = 'Refund fee ({$a}%)';

// The 'Course' column also lists subscriptions, so it names neither.
$string['item_column'] = 'Item';
