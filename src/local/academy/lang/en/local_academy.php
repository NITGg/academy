<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Academy Flex platform';
$string['academy:managepackages'] = 'Manage lesson (Flex) packages';
$string['academy:managesubscriptions'] = 'Manage course subscriptions';
$string['managepackages'] = 'Manage lesson packages';
$string['managesubscriptions'] = 'Manage subscriptions';
$string['subscriptionhub'] = 'Subscriptions';
$string['mysubscriptions'] = 'My subscriptions';
$string['managesettings'] = 'Lesson settings';
$string['myteacherprofile'] = 'My teacher profile';
$string['teacherprofile'] = 'Teacher profile';
$string['editmyteacherprofile'] = 'Edit my teacher profile';
$string['notateacher'] = 'This page is only available to teachers.';
$string['mylessons'] = 'My lessons';
$string['studenthub'] = 'Book lessons & Flex';
$string['studenthubdesc'] = 'Book a lesson, track your lessons, and manage your packages, Flex, and subscriptions — all in one place.';
$string['availsubs_heading'] = 'Available subscriptions';
$string['availsubs_desc'] = 'Subscribe to unlock full access to a set of courses for a fixed period.';
$string['availpkgs_heading'] = 'Available packages';
$string['availpkgs_desc'] = 'Buy a Flex package to book one-on-one lessons with our teachers.';
// Front-page subscription/package card + modal strings (rendered in JS by lib.php). {n} is a
// JS-side number placeholder (passed to get_string as '{n}' so it survives into the client).
$string['hp_days']            = '{$a} days';
$string['hp_flex']            = '{$a} Flex';
$string['hp_active']          = 'Active';
$string['hp_subscribe']       = 'Subscribe';
$string['hp_subscribed']      = 'Subscribed';
$string['hp_login_to_subscribe'] = 'Log in to subscribe';
$string['hp_buy_package']     = 'Buy package';
$string['hp_login_to_buy']    = 'Log in to buy';
$string['hp_purchased']       = 'Purchased';
$string['hp_redirecting']     = 'Redirecting…';
$string['hp_cancel']          = 'Cancel';
$string['hp_proceed']         = 'Proceed to payment';
$string['hp_total']           = 'Total';
$string['hp_secure']          = 'Secure payment via Kashier';
$string['hp_egp']             = 'EGP';
$string['hp_sess_expired']    = 'Session expired — reload the page.';
$string['hp_req_failed']      = 'Request failed';
$string['hp_sub_confirm_title'] = 'Confirm your subscription';
$string['hp_sub_confirm_body']  = 'You are about to subscribe to this plan. You will be taken to secure checkout to complete the payment.';
$string['hp_duration']        = 'Duration';
$string['hp_start_date']      = 'Start date';
$string['hp_end_date']        = 'End date';
$string['hp_never']           = 'Never';
$string['hp_sub_active_note'] = 'You already have an active subscription. You can subscribe to another plan once your current subscription ends.';
$string['hp_pkg_confirm_title'] = 'Confirm your package purchase';
$string['hp_pkg_confirm_body']  = 'You are about to buy this package. You will be taken to secure checkout to complete the payment.';
$string['hp_flex_count']      = 'Flex Count';
$string['hp_flex_used_total'] = 'Flex (used / total)';
$string['hp_activated']       = 'Activated';
$string['hp_expires']         = 'Expires';
$string['hp_never_expires']   = 'Never expires';
$string['hp_valid_for']       = 'Valid for {$a} days after activation';
$string['hp_pkg_active_note'] = 'You already have an active package. You can buy a new one once it is fully used or expires.';
$string['mywallet'] = 'My earnings';
$string['managewithdrawals'] = 'Teacher withdrawals';
$string['assignpackage'] = 'Assign package to student';
$string['reports'] = 'Flex platform reports';

// Error messages (surfaced in API JSON via getMessage()).
$string['err_namerequired']  = 'Package name is required';
$string['err_nameempty']     = 'Package name cannot be empty';
$string['err_flexpositive']  = 'Flex count must be greater than zero';
$string['err_pricenegative'] = 'Price cannot be negative';
$string['err_expnegative']   = 'Expiration days cannot be negative';
$string['err_status']        = 'Status must be "active" or "inactive"';
$string['err_notfound']      = 'Package not found';
$string['err_haspurchases']  = 'This package has purchase records and cannot be deleted. Deactivate it instead.';
$string['err_packagenotavailable'] = 'This package is not available for purchase';
$string['err_alreadyhaspackage']   = 'You already have an active package';
$string['err_settingnegative']     = 'Setting values must be zero or greater';
$string['err_percenttotal']        = 'Teacher percentage and platform percentage must total 100';
$string['err_badhours']            = 'Working hours are invalid (use HH:MM and end after start)';
$string['err_hoursoverlap']        = 'Working hours must not overlap';
$string['err_teachernotfound']     = 'Teacher not found';

// Lessons + Flex engine (Phase 2).
$string['err_subjectrequired']     = 'A subject is required';
$string['err_subjectunsupported']  = 'This teacher does not offer the selected subject';
$string['err_selfbooking']         = 'You cannot request a lesson with yourself';
$string['err_noflex']              = 'You need an active package with available Flex';
$string['err_minbooking']          = 'The lesson must be booked further in advance';
$string['err_timeconflict']        = 'The teacher already has a lesson scheduled at this time.';
$string['err_notime']              = 'A valid time is required';
$string['err_forbidden']           = 'You are not allowed to perform this action';
$string['err_badstate']            = 'This action is not allowed for the lesson\'s current status';
$string['err_badaction']           = 'Unknown action';
$string['err_lessonnotfound']      = 'Lesson not found';
$string['err_tooearlytostart']     = 'The lesson cannot be started yet';
$string['err_completetooearly']    = 'The lesson cannot be completed yet (minimum duration has not been met)';
$string['err_noterequired']        = 'A note is required to request a lesson';
$string['err_reasonrequired']      = 'A reason is required';
$string['err_absencetooearly']     = 'It is too early to report an absence';
$string['err_updatedeadline']      = 'The time-update deadline has passed';
$string['err_updatepending']       = 'There is already a pending time-update request';
$string['err_noupdaterequest']     = 'There is no pending time-update request to respond to';
$string['err_nolessonscourse']     = 'The lessons course for meeting rooms is not configured';

// Financial (Phase 3).
$string['err_notdistributed']      = 'The lesson has no purchase to distribute revenue from';
$string['err_earningnotfound']     = 'No active earning found for this lesson';
$string['err_alreadyreversed']     = 'This lesson\'s Flex has already been reversed';
$string['err_amountpositive']      = 'Amount must be greater than zero';
$string['err_insufficientbalance'] = 'Amount exceeds your available balance';
$string['err_withdrawalnotfound']  = 'Withdrawal request not found';
$string['err_withdrawalstate']     = 'This action is not allowed for the withdrawal\'s current status';

// Reports / assign (Phase 4).
$string['err_studentnotfound']     = 'Student not found';
$string['err_studenthaspackage']   = 'This student already has an active package';

// API-level system messages (moved out of api.php so they localise via ?lang=).
$string['err_postrequired']      = 'This action requires POST';
$string['err_authrequired']      = 'Authentication required';
$string['err_invalidtoken']      = 'Invalid token';
$string['err_permissiondenied']  = 'Permission denied';
$string['err_unknownfunction']   = 'Unknown function';
$string['err_requestfailed']     = 'Request failed';
$string['err_sessionexpired']    = 'Session expired — please reload the page and log in again.';

// API success messages (returned in the top-level "message" field on state-changing actions).
$string['msg_package_created']     = 'Package created.';
$string['msg_package_updated']     = 'Package updated.';
$string['msg_package_activated']   = 'Package activated.';
$string['msg_package_deactivated'] = 'Package deactivated.';
$string['msg_package_deleted']     = 'Package deleted.';
$string['msg_package_unassigned']  = 'Package unassigned successfully.';
$string['msg_package_purchased']   = 'Package purchased.';

// API success messages — subscriptions.
$string['msg_subscription_created']     = 'Subscription created.';
$string['msg_subscription_updated']     = 'Subscription updated.';
$string['msg_subscription_activated']   = 'Subscription activated.';
$string['msg_subscription_deactivated'] = 'Subscription deactivated.';
$string['msg_subscription_deleted']     = 'Subscription deleted.';
$string['msg_subscription_courses_set'] = 'Courses assigned successfully.';
$string['msg_user_unsubscribed']        = 'User unsubscribed successfully.';

// Subscriptions (US-AD-5-*, US-AD-6-1, US-SB-*).
$string['err_subnamerequired']  = 'Subscription name is required';
$string['err_subnameempty']     = 'Subscription name cannot be empty';
$string['err_durationpositive'] = 'Number of days must be greater than zero';
$string['err_subnotfound']      = 'Subscription not found';
$string['err_subhaspurchases']  = 'This subscription has purchase records and cannot be deleted. Deactivate it instead.';
$string['err_subnotavailable']  = 'This subscription is not available for purchase';
$string['err_alreadyhassubscription'] = 'You already have an active subscription';
$string['err_coursenotfound']   = 'Course not found';

// ── Lesson-lifecycle notifications (in-app + email) ──
$string['messageprovider:lessonnotification'] = 'Lesson updates (requests, responses, reminders)';

// US-LS-1-1: student requested a lesson → teacher.
$string['notif_requested_subject'] = 'New lesson request from {$a->student}';
$string['notif_requested_body']    = '{$a->student} requested a {$a->subject} lesson for {$a->time}. Note: {$a->note}. Open "My lessons" to accept, reject, or suggest another time.';

// US-LS-2-1 / US-LS-2-3: teacher confirmed → student.
$string['notif_confirmed_by_teacher_subject'] = 'Lesson confirmed: {$a->subject}';
$string['notif_confirmed_by_teacher_body']    = '{$a->teacher} confirmed your {$a->subject} lesson for {$a->time}.';

// US-LS-2-1 / US-LS-2-3: teacher rejected → student.
$string['notif_rejected_by_teacher_subject'] = 'Lesson request declined: {$a->subject}';
$string['notif_rejected_by_teacher_body']    = '{$a->teacher} declined your {$a->subject} lesson request. Reason: {$a->reason}';

// US-LS-2-1: teacher suggested another time → student.
$string['notif_teacher_suggested_subject'] = 'New time suggested: {$a->subject}';
$string['notif_teacher_suggested_body']    = '{$a->teacher} suggested a new time for your {$a->subject} lesson: {$a->time}. Open "My lessons" to accept, reject, or suggest another time.';

// US-LS-2-2: student accepted the suggested time → teacher.
$string['notif_confirmed_by_student_subject'] = 'Lesson confirmed: {$a->subject}';
$string['notif_confirmed_by_student_body']    = '{$a->student} accepted the suggested time. The {$a->subject} lesson is confirmed for {$a->time}.';

// US-LS-2-2: student rejected the suggested time → teacher.
$string['notif_rejected_by_student_subject'] = 'Suggested time declined: {$a->subject}';
$string['notif_rejected_by_student_body']    = '{$a->student} declined the suggested time for the {$a->subject} lesson. Reason: {$a->reason}';

// US-LS-2-2: student suggested another time → teacher.
$string['notif_student_suggested_subject'] = 'Student suggested a new time: {$a->subject}';
$string['notif_student_suggested_body']    = '{$a->student} suggested a new time for the {$a->subject} lesson: {$a->time}. Open "My lessons" to accept or reject.';

// US-LS-3-1: lesson started → student.
$string['notif_started_subject'] = 'Your lesson has started: {$a->subject}';
$string['notif_started_body']    = '{$a->teacher} started the {$a->subject} lesson. Open the lesson and tap "Join Lesson" to enter the meeting room.';

// US-LS-3-2: lesson completed → student.
$string['notif_completed_subject'] = 'Lesson completed: {$a->subject}';
$string['notif_completed_body']    = 'Your {$a->subject} lesson with {$a->teacher} is complete. {$a->reason}';

// US-LS-3-3: student reported absent → student.
$string['notif_student_absent_subject'] = 'You were marked absent: {$a->subject}';
$string['notif_student_absent_body']    = '{$a->teacher} reported that you did not attend the {$a->subject} lesson scheduled for {$a->time}.';

// US-LS-3-4: teacher reported absent → teacher + admins.
$string['notif_teacher_absent_subject'] = 'Absence reported: {$a->subject}';
$string['notif_teacher_absent_body']    = '{$a->student} reported that you did not attend the {$a->subject} lesson scheduled for {$a->time}. The student\'s Flex has been returned.';
$string['notif_teacher_absent_admin_subject'] = 'Teacher absence reported: {$a->subject}';
$string['notif_teacher_absent_admin_body']    = '{$a->student} reported teacher {$a->teacher} absent for the {$a->subject} lesson scheduled for {$a->time}.';

// US-ST-2-2: student withdrew a pending request → teacher.
$string['notif_request_cancelled_subject'] = 'Lesson request withdrawn: {$a->subject}';
$string['notif_request_cancelled_body']    = '{$a->student} withdrew the {$a->subject} lesson request for {$a->time}. Reason: {$a->reason}';

// US-LS-4-1: student cancelled a confirmed lesson → teacher.
$string['notif_cancelled_by_student_subject'] = 'Lesson cancelled: {$a->subject}';
$string['notif_cancelled_by_student_body']    = '{$a->student} cancelled the {$a->subject} lesson scheduled for {$a->time}. Reason: {$a->reason}';

// US-LS-4-2: teacher cancelled a confirmed lesson → student.
$string['notif_cancelled_by_teacher_subject'] = 'Lesson cancelled by teacher: {$a->subject}';
$string['notif_cancelled_by_teacher_body']    = '{$a->teacher} cancelled the {$a->subject} lesson scheduled for {$a->time}. Your Flex has been returned. Reason: {$a->reason}';

// US-LS-5-1: time-update requested → other party.
$string['notif_time_update_requested_subject'] = 'New time requested: {$a->subject}';
$string['notif_time_update_requested_body']    = '{$a->actor} requested to move the {$a->subject} lesson to {$a->time}. Open the lesson to accept or reject.';

// US-LS-5-2: time-update accepted → requester.
$string['notif_time_update_accepted_subject'] = 'New time accepted: {$a->subject}';
$string['notif_time_update_accepted_body']    = '{$a->actor} accepted the new time. The {$a->subject} lesson is now scheduled for {$a->time}.';

// US-LS-5-2: time-update rejected → requester.
$string['notif_time_update_rejected_subject'] = 'New time rejected: {$a->subject}';
$string['notif_time_update_rejected_body']    = '{$a->actor} rejected the new time. The {$a->subject} lesson stays on {$a->time}.';

// Package expiry reminder (sent by the daily expiry_reminder task).
$string['notif_package_expiring_subject'] = 'Your package expires in {$a->days} day(s)';
$string['notif_package_expiring_body']    = 'Your "{$a->package}" package expires on {$a->date} ({$a->days} day(s) left). You still have {$a->flex} Flex remaining — book a lesson before it expires so you don\'t lose it.';

$string['mypackages'] = 'My packages';
$string['task_expiry_reminder'] = 'Send package-expiry reminders to students';

// Subscription expiry reminder + expiry (sent/run by the daily subscription_expiry task).
$string['task_subscription_expiry'] = 'Expire subscriptions and send expiry reminders';
$string['notif_subscription_expiring_subject'] = 'Your subscription expires in {$a->days} day(s)';
$string['notif_subscription_expiring_body']    = 'Your "{$a->subscription}" subscription expires on {$a->date} ({$a->days} day(s) left). Renew it to keep access to your courses.';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — packages pilot (manage_packages.php + student.php Packages tab).
// Consumed in the browser via window.ACADEMY_STR (see local_academy_string_map()).
// ─────────────────────────────────────────────────────────────────────────────

// Generic / shared UI.
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
$string['ui_currency_egp'] = 'EGP';

// manage_packages.php — package CRUD.
$string['pkg_new']              = 'New package';
$string['pkg_edit_titled']      = 'Edit package #{$a}';
$string['pkg_field_name']       = 'Name';
$string['pkg_field_description'] = 'Description';
$string['pkg_field_name_en']    = 'Name (English)';
$string['pkg_field_name_ar']    = 'Name (Arabic)';
$string['pkg_field_desc_en']    = 'Description (English)';
$string['pkg_field_desc_ar']    = 'Description (Arabic)';
$string['pkg_field_flexcount']  = 'Flex count';
$string['pkg_field_price']      = 'Price (EGP)';
$string['pkg_field_expdays']    = 'Expiration days (0 = unlimited)';
$string['pkg_col_id']           = 'ID';
$string['pkg_col_name']         = 'Name';
$string['pkg_col_flexes']       = 'Flexes';
$string['pkg_col_price']        = 'Price';
$string['pkg_col_expdays']      = 'Expiration (days)';
$string['pkg_col_status']       = 'Status';
$string['pkg_col_actions']      = 'Actions';
$string['pkg_none']             = 'No packages yet.';
$string['pkg_confirm_delete']   = 'Delete this package? This cannot be undone.';

// manage_packages.php — user packages + unassign.
$string['pkg_userpackages']       = 'User Packages';
$string['pkg_userpackages_desc']  = 'Manage active and expired user packages.';
$string['pkg_col_user']           = 'User';
$string['pkg_col_package']        = 'Package';
$string['pkg_col_flex']           = 'Flex';
$string['pkg_col_pricepaid']      = 'Price Paid';
$string['pkg_col_expiresat']      = 'Expires At';
$string['pkg_users_none']         = 'No user packages found.';
$string['pkg_unassign']           = 'Unassign';
$string['pkg_unassign_title']     = 'Unassign package';
$string['pkg_unassign_refund']    = 'Refund payment to student';
$string['pkg_unassign_confirm']   = 'Unassign <strong>{$a->name}</strong> from <strong>{$a->user}</strong>{$a->price}? This cannot be undone.';
$string['pkg_unassign_paid']      = ' — <strong>{$a}</strong> paid';

// student.php — tab bar.
$string['tab_book']         = 'Book a lesson';
$string['tab_lessons']      = 'My lessons';
$string['tab_packages']     = 'Packages & Flex';
$string['tab_subavailable'] = 'Available subscriptions';
$string['tab_mysubs']       = 'My subscriptions';

// student.php — flex banner.
$string['st_available_flex']   = 'Available Flex credits';
$string['st_book_up_to']       = 'You can book up to <b>{$a->count}</b> lesson(s).';
$string['st_no_active_pkg']    = 'No active package — buy one in the <b>Packages</b> tab to start booking.';

// student.php — Packages & Flex tab.
$string['st_payment_history']    = 'Payment history';
$string['st_flex_history']       = 'Flex history';
$string['st_pkg_none_available'] = 'No packages available right now.';
$string['st_pkg_none']           = 'No packages yet.';
$string['st_pay_none']           = 'No payments yet.';
$string['st_flex_none']          = 'No Flex activity yet.';
$string['st_already_active_pkg'] = 'You already have an active package.';
$string['st_buy_package']        = 'Buy package';
$string['st_buy_title']          = 'Buy “{$a}”';
$string['st_buy_text']           = 'You will get {$a->flex} Flex for {$a->price} via Kashier secure checkout.';
$string['st_proceed_payment']    = 'Proceed to Payment';
$string['st_pkgmeta_flex']       = '{$a} Flex';
$string['st_pkgmeta_validdays']  = ' · valid {$a} days';
$string['st_pkgmeta_neverexp']   = ' · never expires';
$string['st_flex_left']          = '<span class="st-flex-pill">{$a->remaining}</span> left ({$a->used} / {$a->total})';
// Column headers (packages tab tables).
$string['st_col_package']     = 'Package';
$string['st_col_flexusedtot'] = 'Flex (used / total)';
$string['st_col_status']      = 'Status';
$string['st_col_activated']   = 'Activated';
$string['st_col_expires']     = 'Expires';
$string['st_col_date']        = 'Date';
$string['st_col_amount']      = 'Amount';
$string['st_col_method']      = 'Method';
$string['st_col_transaction'] = 'Transaction';
$string['st_col_type']        = 'Type';
$string['st_col_change']      = 'Change';
$string['st_col_balance']     = 'Balance';
$string['st_col_lesson']      = 'Lesson';
$string['st_col_note']        = 'Note';
// Status label maps.
$string['pstat_active']     = 'Active';
$string['pstat_fully_used'] = 'Fully used';
$string['pstat_expired']    = 'Expired';
$string['pstat_cancelled']  = 'Cancelled';
$string['pstat_pending']    = 'Pending';
$string['pay_completed']    = 'Completed';
$string['pay_pending']      = 'Pending';
$string['pay_failed']       = 'Failed';
$string['pay_refunded']     = 'Refunded';
$string['flx_reserve']      = 'Reserved';
$string['flx_consume']      = 'Consumed';
$string['flx_return']       = 'Returned';
$string['flx_purchase']     = 'Purchased';
$string['flx_assign']       = 'Assigned';
$string['flx_expire']       = 'Expired';
$string['flx_adjust']       = 'Adjusted';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — student.php Book / My lessons / Subscriptions tabs (rollout).
// ─────────────────────────────────────────────────────────────────────────────

// Shared UI extras.
$string['ui_confirm']  = 'Confirm';
$string['ui_search']   = 'Search';

// Book a lesson tab.
$string['st_search_placeholder'] = 'Search by subject…';
$string['st_teacher_num']        = 'Teacher #{$a}';
$string['st_request_lesson']     = 'Request a lesson';
$string['st_no_subjects']        = 'This teacher has not listed any subjects yet.';
$string['st_request_with']       = 'Request a lesson with {$a}';
$string['st_send_request']       = 'Send request';
$string['st_field_subject']      = 'Subject';
$string['st_field_datetime']     = 'Preferred date & time';
$string['st_field_note_req']     = 'Note to the teacher (required)';
$string['st_note_placeholder']   = 'What do you need help with?';
$string['st_pick_valid_time']    = 'Please pick a valid date and time.';
$string['st_note_required']      = 'A note is required to request a lesson.';
$string['st_lesson_requested']   = 'Lesson requested. Track it in “My lessons”.';
$string['st_no_teachers']        = 'No teachers found.';
$string['st_slot_pickday']       = 'Choose a day';
$string['st_slot_picktime']      = 'Choose a time';
$string['st_slot_noavail']       = 'This teacher has no available times in the coming days.';
$string['st_slot_nodayslots']    = 'No open times on this day.';

// My lessons tab — filter dropdown.
$string['st_status']            = 'Status';
$string['lf_all']               = 'All';
$string['lf_pending']           = 'Pending';
$string['lf_waiting_student']   = 'Waiting for me';
$string['lf_waiting_teacher']   = 'Waiting for teacher';
$string['lf_confirmed']         = 'Confirmed';
$string['lf_in_progress']       = 'In progress';
$string['lf_completed']         = 'Completed';
$string['lf_student_absent']    = 'I was absent';
$string['lf_teacher_absent']    = 'Teacher absent';
$string['lf_cancelled']         = 'Cancelled';
$string['lf_cancelled_teacher'] = 'Cancelled (teacher)';
$string['lf_rejected']          = 'Rejected';

// My lessons tab — status badge labels.
$string['lstat_pending']           = 'Pending teacher response';
$string['lstat_waiting_student']   = 'Waiting for you';
$string['lstat_waiting_teacher']   = 'Waiting for teacher';
$string['lstat_confirmed']         = 'Confirmed';
$string['lstat_in_progress']       = 'In progress';
$string['lstat_completed']         = 'Completed';
$string['lstat_student_absent']    = 'You were absent';
$string['lstat_teacher_absent']    = 'Teacher absent';
$string['lstat_cancelled']         = 'Cancelled';
$string['lstat_cancelled_teacher'] = 'Cancelled by teacher';
$string['lstat_rejected']          = 'Rejected';

// My lessons tab — action button labels.
$string['lact_accept']             = 'Accept';
$string['lact_reject']             = 'Reject';
$string['lact_suggest']            = 'Suggest time';
$string['lact_cancel_request']     = 'Withdraw request';
$string['lact_cancel']             = 'Cancel lesson';
$string['lact_report_teacher_absent'] = 'Report teacher absent';
$string['lact_request_time_update'] = 'Reschedule';
$string['lact_join']               = 'Join lesson';
$string['lact_accept_newtime']     = 'Accept new time';
$string['lact_reject_newtime']     = 'Reject new time';

// My lessons tab — action dialogs.
$string['la_done']              = 'Done.';
$string['la_reason_optional']   = 'Reason (optional)';
$string['la_pick_valid_time']   = 'Pick a valid time.';
$string['la_reject_title']      = 'Reject the suggested time';
$string['la_suggest_title']     = 'Suggest another time';
$string['la_suggested_time']    = 'Suggested date & time';
$string['la_withdraw_title']    = 'Withdraw request';
$string['la_withdraw_text']     = 'Withdraw this lesson request? No Flex has been reserved yet.';
$string['la_cancel_title']      = 'Cancel lesson';
$string['la_cancel_text']       = 'Cancelling before the deadline returns your Flex; cancelling late consumes it.';
$string['la_report_absent_title'] = 'Report teacher absent';
$string['la_report_absent_text']  = 'Confirm the teacher did not show up? Your Flex will be returned.';
$string['la_newtime_title']     = 'Request a new time';
$string['la_newtime_label']     = 'New date & time';
$string['la_room_not_ready']    = 'The meeting room is not ready yet.';

// My lessons tab — lesson card.
$string['lc_teacher_num']    = 'teacher #{$a}';
$string['lc_title']          = '{$a->subject} · with {$a->teacher}';
$string['lc_confirmed']      = 'Confirmed: {$a}';
$string['lc_requested']      = 'Requested: {$a}';
$string['lc_duration']       = '{$a} min';
$string['lc_your_note']      = 'Your note: {$a}';
$string['lc_reject_reason']  = 'Reject reason: {$a}';
$string['lc_cancel_reason']  = 'Cancel reason: {$a}';
$string['lc_flex']           = 'Flex: {$a}';
$string['lc_you']            = 'You';
$string['lc_the_teacher']    = 'The teacher';
$string['lc_resched_moved']  = '{$a->who} requested to move this lesson to <b>{$a->time}</b>.';
$string['st_no_lessons']     = 'No lessons yet — book one from the “Book a lesson” tab.';

// Subscriptions tabs — headings + table headers.
$string['sub_available_heading'] = 'Available subscriptions';
$string['sub_my_heading']        = 'My subscriptions';
$string['sub_payments_heading']  = 'Subscription payments';
$string['sub_col_subscription']  = 'Subscription';
$string['sub_col_daysleft']      = 'Days left';
$string['sub_col_courses']       = 'Courses';

// Subscriptions tabs — status badges.
$string['sstat_active']         = 'Active';
$string['sstat_expired']        = 'Expired';
$string['sstat_cancelled']      = 'Cancelled';
$string['sstat_pending']        = 'Pending';
$string['sstat_payment_failed'] = 'Payment failed';

// Subscriptions tabs — cards + dialogs.
$string['sub_days']           = '{$a} days';
$string['sub_courses_label']  = 'Courses:';
$string['sub_already_active'] = 'You already have an active subscription.';
$string['sub_buy']            = 'Buy subscription';
$string['sub_buy_title']      = 'Buy “{$a}”';
$string['sub_buy_text']       = 'You will get {$a->days} days of course access for {$a->price} via Kashier secure checkout.';
$string['sub_none_available'] = 'No subscriptions available right now.';
$string['sub_none_mine']      = 'No subscriptions yet.';
$string['sub_no_payments']    = 'No payments yet.';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — manage_settings.php (admin lesson settings).
// ─────────────────────────────────────────────────────────────────────────────
$string['set_min_booking']          = 'Minimum booking time (minutes)';
$string['set_cancel_deadline']      = 'Student cancellation deadline (minutes)';
$string['set_update_deadline']      = 'Lesson time-update deadline (minutes)';
$string['set_start_allowed']        = 'Lesson start allowed time (minutes)';
$string['set_complete_allowed']     = 'Minimum minutes after start before completing';
$string['set_absence_report']       = 'Absence reporting time (minutes)';
$string['set_expiry_reminder']      = 'Package expiry reminder (days before)';
$string['set_expiry_reminder_help'] = 'Notify the student this many days before their package expires. 0 disables the reminder.';
$string['set_teacher_percent']      = 'Teacher earning %';
$string['set_platform_percent']     = 'Platform earning %';
$string['set_percent_help']         = 'Teacher % + Platform % must total 100.';
$string['set_save']                 = 'Save changes';
$string['set_saved']                = 'Saved.';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — assign_package.php (admin: assign package to student).
// ─────────────────────────────────────────────────────────────────────────────
$string['ap_student_label']       = 'Student user ID';
$string['ap_student_help']        = 'The numeric Moodle user id of the student.';
$string['ap_student_placeholder'] = 'e.g. 4770';
$string['ap_package_label']       = 'Package';
$string['ap_amount_label']        = 'Amount paid (offline)';
$string['ap_amount_placeholder']  = 'defaults to package price';
$string['ap_method_label']        = 'Payment method';
$string['ap_method_offline']      = 'Offline / cash';
$string['ap_method_bank']         = 'Bank transfer';
$string['ap_method_wallet']       = 'Mobile wallet';
$string['ap_reference_label']     = 'Payment reference';
$string['ap_reference_placeholder'] = 'receipt / transfer no.';
$string['ap_note_label']          = 'Note (optional)';
$string['ap_submit']              = 'Assign package';
$string['ap_pkg_option']          = '{$a->name} — {$a->flex} Flex / {$a->price}';
$string['ap_no_packages']         = 'No active packages exist. Create one first.';
$string['ap_enter_student']       = 'Enter a student user id.';
$string['ap_assigned']            = 'Assigned “{$a->name}” ({$a->flex} Flex) to {$a->student}.';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — teacher_profile.php (teacher's own profile editor).
// ─────────────────────────────────────────────────────────────────────────────
$string['tp_headline']      = 'Headline';
$string['tp_headline_ph']   = 'e.g. Senior Mathematics Teacher';
$string['tp_bio']           = 'About me';
$string['tp_experience']    = 'Years of experience';
$string['tp_available']     = 'Available for lessons';
$string['tp_subjects']      = 'Subjects';
$string['tp_add_subject']   = '+ Add subject';
$string['tp_working_hours'] = 'Working hours';
$string['tp_add_slot']      = '+ Add time slot';
$string['tp_subject_ph']    = 'Subject (e.g. Math)';
$string['tp_to']            = 'to';
$string['tp_saved']         = 'Profile saved.';
$string['tp_day_sun']       = 'Sunday';
$string['tp_day_mon']       = 'Monday';
$string['tp_day_tue']       = 'Tuesday';
$string['tp_day_wed']       = 'Wednesday';
$string['tp_day_thu']       = 'Thursday';
$string['tp_day_fri']       = 'Friday';
$string['tp_day_sat']       = 'Saturday';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — wallet.php (teacher earnings / withdrawals).
// ─────────────────────────────────────────────────────────────────────────────
$string['ui_export_csv']         = 'Export CSV';
$string['ui_request']            = 'Request';
$string['w_withdraw']            = 'Withdraw earnings';
$string['w_withdrawals_heading'] = 'Withdrawals';
$string['w_earnings_heading']    = 'Earnings';
$string['w_col_noteref']         = 'Note / ref';
$string['w_col_student']         = 'Student';
$string['w_col_lessondate']      = 'Lesson date';
$string['w_col_flexvalue']       = 'Flex value';
$string['w_col_yourshare']       = 'Your share';
$string['w_amount']              = 'Amount';
$string['w_method']              = 'Method';
$string['w_method_cash']         = 'Cash';
$string['w_account']             = 'Account / payout details';
$string['w_account_ph']          = 'IBAN / phone / note';
$string['w_available_balance']   = 'Available balance';
$string['w_total_earned']        = 'Total earned';
$string['w_pending_withdrawals'] = 'Pending withdrawals';
$string['w_total_withdrawn']     = 'Total withdrawn';
$string['w_no_withdrawals']      = 'No withdrawals yet.';
$string['w_no_earnings']         = 'No earnings yet.';
$string['w_ref']                 = 'Ref: {$a}';
$string['w_requested']           = 'Withdrawal requested.';
$string['w_share']               = '{$a->amount} ({$a->percent}%)';
$string['wstat_pending']         = 'Pending';
$string['wstat_approved']        = 'Approved';
$string['wstat_paid']            = 'Paid';
$string['wstat_rejected']        = 'Rejected';
$string['wstat_active']          = 'Active';
$string['wstat_reversed']        = 'Reversed';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — manage_withdrawals.php (admin withdrawals + Flex reversal).
// ─────────────────────────────────────────────────────────────────────────────
$string['wd_col_teacher']        = 'Teacher';
$string['wd_col_methodaccount']  = 'Method / account';
$string['wd_reversal_title']     = 'Reverse a completed lesson\'s Flex (US-FN-1-5)';
$string['wd_reversal_help']      = 'Returns one consumed Flex to the student and reverses the teacher/platform earning. A reason is required.';
$string['wd_lesson_id']          = 'Lesson ID';
$string['wd_reason']             = 'Reason';
$string['wd_return_flex']        = 'Return Flex';
$string['wd_updated']            = 'Updated.';
$string['wd_approve']            = 'Approve';
$string['wd_reject']             = 'Reject';
$string['wd_markpaid']           = 'Mark paid';
$string['wd_reject_title']       = 'Reject withdrawal';
$string['wd_reason_required_field'] = 'Reason (required)';
$string['wd_markpaid_title']     = 'Mark as paid';
$string['wd_payref_optional']    = 'Payment reference (optional)';
$string['wd_reason_required']    = 'Reason required.';
$string['wd_card_current']       = 'Platform current money';
$string['wd_card_undistributed'] = 'Undistributed (unused Flex)';
$string['wd_card_teachers']      = 'Teachers\' money (unpaid)';
$string['wd_card_platform']      = 'Platform earnings';
$string['wd_none']               = 'No withdrawal requests.';
$string['wd_enter_lesson']       = 'Enter a lesson ID.';
$string['wd_flex_returned']      = 'Flex returned and earning reversed.';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — manage_reports.php (admin reports, 4 tabs).
// ─────────────────────────────────────────────────────────────────────────────
// Tabs.
$string['rp_tab_lessons']     = 'Lessons & attendance';
$string['rp_tab_platform']    = 'Platform earnings';
$string['rp_tab_packages']    = 'Packages & Flex';
$string['rp_tab_studentflex'] = 'Student Flex';
// Filters.
$string['rp_f_status']        = 'Status';
$string['rp_f_teacherid']     = 'Teacher ID';
$string['rp_f_studentid']     = 'Student ID';
$string['rp_f_from']          = 'From (unix)';
$string['rp_f_to']            = 'To (unix)';
$string['rp_f_earnstatus']    = 'Earning status';
$string['rp_f_source']        = 'Source';
$string['rp_f_studentid_req'] = 'Student ID (required)';
$string['rp_run']             = 'Run';
// Column headers.
$string['rp_c_id']         = 'ID';
$string['rp_c_student']    = 'Student';
$string['rp_c_teacher']    = 'Teacher';
$string['rp_c_subject']    = 'Subject';
$string['rp_c_status']     = 'Status';
$string['rp_c_confirmed']  = 'Confirmed';
$string['rp_c_flex']       = 'Flex';
$string['rp_c_lesson']     = 'Lesson';
$string['rp_c_date']       = 'Date';
$string['rp_c_flexvalue']  = 'Flex value';
$string['rp_c_platpct']    = 'Plat %';
$string['rp_c_platform']   = 'Platform';
$string['rp_c_package']    = 'Package';
$string['rp_c_source']     = 'Source';
$string['rp_c_price']      = 'Price';
$string['rp_c_rem']        = 'Rem';
$string['rp_c_resv']       = 'Resv';
$string['rp_c_used']       = 'Used';
$string['rp_c_type']       = 'Type';
$string['rp_c_amount']     = 'Amount';
$string['rp_c_before']     = 'Before';
$string['rp_c_after']      = 'After';
$string['rp_c_by']         = 'By';
$string['rp_c_reason']     = 'Reason';
$string['rp_c_timeline']   = 'Timeline';
// Student-Flex summary chips.
$string['rp_s_available']  = 'Available';
$string['rp_s_reserved']   = 'Reserved';
$string['rp_s_consumed']   = 'Consumed';
$string['rp_s_package']    = 'Package';
$string['rp_s_expires']    = 'Expires';
// Timeline.
$string['rp_timeline_title'] = 'Action timeline';
$string['rp_close']          = 'Close';
$string['rp_tl_num']         = '#';
$string['rp_tl_action']      = 'Action';
$string['rp_tl_by']          = 'By';
$string['rp_tl_role']        = 'Role';
$string['rp_tl_time']        = 'Time';
$string['rp_tl_title_full']  = 'Action timeline — lesson #{$a->id} ({$a->subject}, {$a->student} ↔ {$a->teacher})';
$string['rp_tl_joinedroom']  = 'Teacher joined room';
$string['rp_tl_started']     = 'Lesson started';
$string['rp_tl_ended']       = 'Lesson ended';
$string['rp_tl_none']        = 'No recorded actions.';
// Messages.
$string['rp_no_data']            = 'No data.';
$string['rp_enter_student']      = 'Enter a student ID.';
$string['rp_enter_student_run']  = 'Enter a student ID and click Run.';
// Summary chip labels (keyed off the report summary field names).
$string['rp_sum_total']                   = 'Total';
$string['rp_sum_completed']               = 'Completed';
$string['rp_sum_student_absent']          = 'Student absent';
$string['rp_sum_teacher_absent']          = 'Teacher absent';
$string['rp_sum_attendance_rate']         = 'Attendance rate';
$string['rp_sum_total_platform_earnings'] = 'Total platform earnings';
$string['rp_sum_total_teacher_earnings']  = 'Total teacher earnings';
$string['rp_sum_total_consumed_value']    = 'Total consumed value';
$string['rp_sum_completed_lessons']       = 'Completed lessons';
$string['rp_sum_total_purchases']         = 'Total purchases';
$string['rp_sum_total_sales_amount']      = 'Total sales amount';
$string['rp_sum_online_count']            = 'Online purchases';
$string['rp_sum_assigned_count']          = 'Assigned';
$string['rp_sum_total_flex_added']        = 'Total Flex added';
$string['rp_sum_total_flex_consumed']     = 'Total Flex consumed';
$string['rp_sum_total_flex_returned']     = 'Total Flex returned';
$string['rp_sum_reversals']               = 'Reversals';
// Audit-trail action labels.
$string['rp_act_requested']               = 'Student requested';
$string['rp_act_teacher_accepted']        = 'Teacher accepted';
$string['rp_act_teacher_rejected']        = 'Teacher rejected';
$string['rp_act_teacher_suggested']       = 'Teacher suggested time';
$string['rp_act_student_accepted']        = 'Student accepted';
$string['rp_act_student_rejected']        = 'Student rejected';
$string['rp_act_student_suggested']       = 'Student suggested time';
$string['rp_act_started']                 = 'Teacher started lesson (room created)';
$string['rp_act_teacher_joined']          = 'Teacher joined meeting';
$string['rp_act_student_joined']          = 'Student joined meeting';
$string['rp_act_completed']               = 'Lesson completed';
$string['rp_act_student_absent_reported'] = 'Student absence reported';
$string['rp_act_teacher_absent_reported'] = 'Teacher absence reported';
$string['rp_act_request_cancelled']       = 'Request withdrawn';
$string['rp_act_cancelled_by_student']    = 'Cancelled by student';
$string['rp_act_cancelled_by_teacher']    = 'Cancelled by teacher';
$string['rp_act_time_update_requested']   = 'Time-update requested';
$string['rp_act_time_update_accepted']    = 'Time-update accepted';
$string['rp_act_time_update_rejected']    = 'Time-update rejected';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — my_lessons.php (teacher's lessons; reuses many lesson keys).
// ─────────────────────────────────────────────────────────────────────────────
$string['mlf_waiting_student']        = 'Waiting for student';
$string['mlf_student_absent']         = 'Student absent';
$string['mlf_cancelled']              = 'Cancelled (student)';
$string['ml_act_start']               = 'Start';
$string['ml_act_join']                = 'Join meeting';
$string['ml_act_complete']            = 'Complete';
$string['ml_act_report_student_absent'] = 'Student absent';
$string['ml_act_cancel']              = 'Cancel';
$string['ml_act_respond']             = 'Respond to reschedule';
$string['ml_report_absent_title']     = 'Report student absent';
$string['ml_report_absent_text']      = 'Confirm the student did not attend? The Flex will be consumed.';
$string['ml_reject_title']            = 'Reject request';
$string['ml_complete_title']          = 'Complete lesson';
$string['ml_note_optional']           = 'Note (optional)';
$string['ml_cancel_text']             = 'The reserved Flex will be returned to the student.';
$string['ml_card_title']              = '{$a->subject} · with {$a->student}';
$string['ml_student_num']             = 'student #{$a}';
$string['ml_note']                    = 'Note: {$a}';
$string['ml_the_student']             = 'The student';
$string['ml_no_lessons']              = 'No lessons to show.';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — manage_subscriptions.php (admin subscription plans + user subs).
// ─────────────────────────────────────────────────────────────────────────────
$string['sub_plans_heading']       = 'Subscription plans';
$string['sub_new']                 = 'New subscription';
$string['sub_col_days']            = 'Days';
$string['sub_field_desc']          = 'Description (optional)';
$string['sub_field_days']          = 'Number of days';
$string['sub_courseavail_heading'] = 'Course subscription availability';
$string['sub_courseavail_desc']    = 'Choose courses and append them to a specific subscription.';
$string['sub_target']              = 'Target Subscription:';
$string['sub_select_placeholder']  = 'Select a subscription...';
$string['sub_save_courses']        = 'Save courses to subscription';
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

$string['set_lesson_start_reminder'] = 'Lesson start reminder (minutes)';
$string['set_lesson_start_reminder_help'] = 'Notify the student this many minutes before their lesson starts (0 to disable).';
$string['notif_lesson_reminder_subject'] = 'Your lesson starts soon!';
$string['notif_lesson_reminder_body'] = 'Hi {$a->studentname},

Your lesson "{$a->subject}" with {$a->teachername} is starting in {$a->time}.

Please join the lesson room on time.';

