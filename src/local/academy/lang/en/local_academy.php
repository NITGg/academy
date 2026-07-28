<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Academy Flex platform';
$string['academy:managepackages'] = 'Manage lesson (Flex) packages';
$string['academy:managesubscriptions'] = 'Manage course subscriptions';
$string['managepackages'] = 'Manage lesson packages';
$string['managesubscriptions'] = 'Manage subscriptions';
$string['subscriptionhub'] = 'Subscriptions';
$string['mysubscriptions'] = 'My subscriptions';
$string['managesettings'] = 'Admin settings';
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
// B2B purchase card (US-B2B-1-1).
$string['hp_b2b_business']      = 'Business (B2B)';
$string['hp_b2b_confirm_title'] = 'Buy a business subscription';
$string['hp_b2b_confirm_body']  = 'Choose how many user seats you need. You will manage seats and invite users after purchase.';
$string['hp_b2b_capacity']      = 'Capacity';
$string['hp_b2b_users']         = '{n} users';
$string['hp_b2b_base']          = 'Base price';
$string['hp_b2b_discount']      = 'Discount';
$string['hp_b2b_total']         = 'B2B total';
$string['hp_b2b_success']       = 'Business subscription purchased. You are now a B2B administrator.';
$string['hp_b2b_manage']        = 'Manage business plan';
$string['hp_pkg_confirm_title'] = 'Confirm your package purchase';
$string['hp_pkg_confirm_body']  = 'You are about to buy this package. You will be taken to secure checkout to complete the payment.';
$string['hp_flex_count']      = 'Flex Count';
$string['hp_flex_used_total'] = 'Flex (used / total)';
$string['hp_activated']       = 'Activated';
$string['hp_expires']         = 'Expires';
$string['hp_never_expires']   = 'Never expires';
$string['hp_valid_for']       = 'Valid for {$a} days after activation';
$string['hp_pkg_active_note'] = 'You already have an active package. You can buy a new one once it is fully used or expires.';
// Front-page "PM Lounge" marketing sections (testimonials, articles, business CTA) rendered by lib.php.
$string['hp_testi_heading']   = 'Comments from our distinguished customers.';
$string['hp_testi1_quote']    = 'I am proud to say that after a few months of taking this course I passed my exam and am now a certified professional. This content was exactly what the exam covered.';
$string['hp_testi1_name']     = 'Ahmed Mahmoud';
$string['hp_testi1_course']   = 'The Project Management Professional | learn the Skills & Get the job';
$string['hp_testi2_quote']    = 'The instructors explain every concept clearly and the practice questions are very close to the real exam. Highly recommended for anyone starting their PMP journey.';
$string['hp_testi2_name']     = 'Sara Ali';
$string['hp_testi2_course']   = 'The Project Management Professional | learn the Skills & Get the job';
$string['hp_testi3_quote']    = 'A well-structured program that took me from the basics all the way to passing the certification. The support from the community made all the difference.';
$string['hp_testi3_name']     = 'Mohamed Hassan';
$string['hp_testi3_course']   = 'The Project Management Professional | learn the Skills & Get the job';
$string['hp_arts_heading']    = 'Articles';
$string['hp_arts_title']      = 'What is the PMP certificate and the importance of getting a project management certificate?';
$string['hp_arts_body']       = 'The PMP certificate is short for Project Management Professional. It is one of the most important credentials required to become an accredited and experienced project manager — an internationally recognized certificate that demonstrates your ability to manage a project from start to end and to lead a task force efficiently. The PMP certificate is one of the most sought-after goals pursued by project managers around the world.';
$string['hp_arts_readmore']   = 'Read more';
$string['hp_arts_readall']    = 'Read more articles';
$string['hp_biz_title']       = 'PMlounge Business';
$string['hp_biz_body']        = 'Join PMLounge, our groundbreaking educational platform, and be part of a dynamic community dedicated to igniting a passion for learning, fostering innovation, and shaping the future of education.';
$string['hp_biz_join']        = 'Join us';
$string['mywallet'] = 'My earnings';
// manage_withdrawals.php is now the Financial Reports page (withdrawals live in its first tab).
// The old key is kept because it still names the withdrawals section inside that page.
$string['financialreports']  = 'Financial Reports';
$string['manageprograms']    = 'Manage Program';
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
// Paid programs (enrol_programs integration).
$string['err_programsunavailable'] = 'The Programs plugin is not installed';
$string['err_programnotfound']     = 'Program not found';
$string['err_programarchived']     = 'This program is archived';
$string['err_invalidprice']        = 'Price cannot be negative';
$string['err_programsourceinuse']  = 'Students are already signed up through free self-signup, so it cannot be switched off. Remove those allocations first.';
$string['err_programnotpaid']      = 'This program has no price set';
$string['err_programalreadyowned'] = 'You already have access to this program';
$string['err_programnotfree']      = 'This program is paid — use checkout to buy it';
$string['err_programnotjoinable']  = 'This program is not open for self-enrolment';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — manage_programs.php (program pricing) and the catalogue Buy button.
// ─────────────────────────────────────────────────────────────────────────────
$string['prg_intro']          = 'Set a price to make a program paid. Leave the price at 0 to keep it free — free programs behave exactly as they do now.';
$string['prg_col_program']    = 'Program';
$string['prg_col_price']      = 'Price';
$string['prg_col_status']     = 'Status';
$string['prg_col_sales']      = 'Sales';
$string['prg_col_actions']    = 'Actions';
$string['prg_free']           = 'Free';
$string['prg_paid']           = 'Paid';
$string['prg_archived']       = 'Archived';
$string['prg_notpublic']      = 'Not public';
$string['prg_saved']          = 'Price saved.';
$string['prg_makefree_hint']  = 'Set the price to 0 to make the program free again.';
$string['prg_bypass_badge']   = 'Free signup still open';
$string['prg_bypass_warning'] = '{$a} paid program(s) still allow free self-signup. Students can join those without paying, straight from the program catalogue link — setting a price does not close that path. Use "Close free signup" on each one.';
$string['prg_close_free']     = 'Close free signup';
$string['prg_closed_free']    = 'Free signup closed.';
$string['prg_needsopen_badge'] = 'No signup path open';
$string['prg_open_free']      = 'Open free signup';
$string['prg_opened_free']    = 'Free signup opened.';
$string['prg_none']           = 'No programs found.';
$string['prg_tab_programs']   = 'Programs';
$string['prg_tab_settings']   = 'Program settings';
// Catalogue (student-facing).
$string['prg_buy']            = 'Buy this program';
$string['prg_price_label']    = 'Price';
$string['prg_owned']          = 'You already have this program';
$string['prg_login_to_buy']   = 'Log in to buy';
// Front-page program cards ("Programs" and "My programs" sections).
$string['hp_prg_heading']     = 'Programs';
$string['hp_prg_desc']        = 'Structured learning paths that take you from start to certificate.';
$string['hp_myprg_heading']   = 'My programs';
$string['hp_myprg_desc']      = 'The programs you have joined — pick up where you left off.';
$string['hp_prg_free']        = 'Free';
$string['hp_prg_paid']        = 'Paid';
$string['hp_prg_enrolled']    = 'Enrolled';
$string['hp_prg_join']        = 'Join for free';
$string['hp_prg_view']        = 'View program';
$string['hp_prg_open']        = 'Open program';
$string['hp_prg_completed']   = 'Completed';
$string['hp_prg_inprogress']  = 'In progress';
$string['hp_prg_started']     = 'Starts';
$string['hp_prg_due']         = 'Due';
$string['hp_prg_ends']        = 'Ends';
$string['hp_prg_notset']      = 'Not set';
$string['hp_prg_all']         = 'Browse the full program catalogue →';
$string['hp_myprg_all']       = 'See all my programs →';
// Program purchase confirmation modal (same pattern as packages/subscriptions).
$string['hp_prg_confirm_title'] = 'Buy program';
$string['hp_prg_confirm_body']  = 'Review the details below, then confirm your purchase.';
$string['hp_prg_redirecting']   = 'Redirecting to payment…';
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
// ── B2B subscriptions (US-B2B-1-*) ──
$string['err_seatspositive']    = 'Number of seats must be greater than zero';
$string['err_discountrange']    = 'Discount percentage must be between 0 and 100';
$string['err_b2bnotenabled']    = 'This subscription is not available for B2B purchase';
$string['err_seatoptioninvalid'] = 'The selected capacity is not available for this subscription';
$string['err_b2bnotowner']      = 'You do not manage this B2B subscription';
$string['err_b2bnotactive']     = 'This B2B subscription is not active';
$string['err_b2bexpired']       = 'This B2B subscription has expired';
$string['err_nofreeseats']      = 'No available seats remain in this B2B subscription';
$string['err_invalidinvite']    = 'This invitation link is invalid, expired, disabled, or revoked';
$string['err_membershipnotfound'] = 'Membership not found';
$string['err_notpending']       = 'This membership is not pending approval';
$string['err_notapproved']      = 'This membership is not currently approved';
$string['err_b2brole_missing']  = 'The B2B administrator role is not configured on this site';

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

// Program expiry reminder (sent by the daily program_expiry_reminder task).
$string['myprograms'] = 'My programs';
$string['task_program_expiry_reminder'] = 'Send program-expiry reminders to students';
$string['notif_program_expiring_subject'] = 'Your program expires in {$a->days} day(s)';
$string['notif_program_expiring_body']    = 'Your access to the "{$a->program}" program expires on {$a->date} ({$a->days} day(s) left).';

// ── B2B subscription notifications (US-B2B-1-*) ──
$string['messageprovider:b2bnotification'] = 'B2B subscription updates (purchase, join requests, approvals)';
$string['notif_b2b_purchased_subject'] = 'Your B2B subscription is active';
$string['notif_b2b_purchased_body']    = 'Your business subscription "{$a->subscription}" with {$a->seats} seat(s) is now active. You are a B2B administrator and can invite and approve users.';
$string['notif_b2b_pending_subject']   = 'A user is waiting for your approval';
$string['notif_b2b_pending_body']      = '{$a->user} requested to join your "{$a->subscription}" B2B subscription. Open your B2B dashboard to approve or reject the request.';
$string['notif_b2b_approved_subject']  = 'Your B2B membership was approved';
$string['notif_b2b_approved_body']     = 'You now have access to the courses of the "{$a->subscription}" subscription.';
$string['notif_b2b_rejected_subject']  = 'Your B2B join request was rejected';
$string['notif_b2b_rejected_body']     = 'Your request to join the "{$a->subscription}" subscription was rejected. {$a->reason}';
$string['notif_b2b_removed_subject']   = 'Your B2B access was removed';
$string['notif_b2b_removed_body']      = 'Your access through the "{$a->subscription}" B2B subscription has been removed.';

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
$string['ui_pager_info']   = 'Showing {from}–{to} of {total}';
$string['ui_picker_placeholder'] = 'Search by name or email…';
$string['ui_picker_searching']   = 'Searching…';
$string['ui_picker_none']        = 'No matches found';
$string['ui_picker_hint']        = 'Type 2 or more characters';
$string['ui_picker_teacher_ph']  = 'Search teacher by name or email…';
$string['ui_picker_student_ph']  = 'Search student by name or email…';
$string['ui_picker_lesson_ph']   = 'Search lesson by subject, student or teacher…';

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
// Tabs on manage_packages.php — the page now also hosts what used to be the standalone
// "Admin settings" (package half), "Assign package to student" and "Flex platform reports" pages.
$string['pkg_tab_packages']       = 'Packages';
$string['pkg_tab_assign']         = 'Assign package';
$string['pkg_tab_settings']       = 'Package settings';
$string['pkg_tab_reports']        = 'Flex reports';
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
// Settings tabs (US-AD-2-1).
$string['set_tab_lesson']           = 'Package settings';
$string['set_tab_b2b']              = 'Subscription settings';
$string['set_sub_expiry_reminder']      = 'Subscription expiry reminder (days before)';
$string['set_sub_expiry_reminder_help'] = 'Notify the student this many days before their subscription expires. 0 disables the reminder.';
$string['set_program_expiry_reminder']      = 'Program expiry reminder (days before)';
$string['set_program_expiry_reminder_help'] = 'Notify the student this many days before their program access expires. 0 disables the reminder.';
$string['set_b2b_auto_approve']     = 'Automatically approve invited users';
$string['set_b2b_auto_approve_help'] = 'When enabled, an invited user is approved automatically if the B2B subscription has an available seat; otherwise they stay pending until the B2B administrator approves them.';
$string['set_b2b_return_seat']      = 'Return seat when a user is removed';
$string['set_b2b_return_seat_help'] = 'When enabled, removing an approved user frees their seat so another user can be approved. When disabled, the seat stays consumed until the subscription expires.';
$string['set_enabled']              = 'Enabled';
$string['set_disabled']             = 'Disabled';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — assign_package.php (admin: assign package to student).
// ─────────────────────────────────────────────────────────────────────────────
$string['ap_student_label']       = 'Student';
$string['ap_student_help']        = 'Search for the student by name or email and select them.';
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
$string['ap_enter_student']       = 'Search and select a student.';
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
$string['wd_lesson_id']          = 'Lesson';
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
$string['wd_enter_lesson']       = 'Search and select a lesson.';
$string['wd_flex_returned']      = 'Flex returned and earning reversed.';
$string['wd_withdrawals_title']  = 'Teacher withdrawal requests';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — Financial Reports (manage_withdrawals.php, 5 tabs).
// ─────────────────────────────────────────────────────────────────────────────
// Tabs.
$string['fr_tab_overview']      = 'Overview';
$string['fr_tab_packages']      = 'Packages';
$string['fr_tab_subscriptions'] = 'Subscriptions';
$string['fr_tab_courses']       = 'Courses';
$string['fr_tab_programs']      = 'Programs';
$string['fr_tab_coupons']       = 'Coupons';
$string['fr_tab_offers']        = 'Offers';
// Date filter.
$string['fr_from']              = 'From';
$string['fr_to']                = 'To';
$string['fr_apply']             = 'Apply';
$string['fr_clear']             = 'Clear';
$string['fr_alldates']          = 'All time';
$string['fr_range']             = 'Showing {$a}';
$string['fr_export']            = 'Export CSV';
$string['fr_norows']            = 'No data for this period.';
$string['fr_total']             = 'Total';
// Overview sections.
$string['fr_sec_wallet']        = 'Platform current money';
$string['fr_sec_wallet_help']   = 'Live balances — not affected by the date filter.';
$string['fr_sec_revenue']       = 'Revenue collected';
$string['fr_sec_discounts']     = 'Discounts given';
$string['fr_sec_payouts']       = 'Teacher payouts';
$string['fr_sec_volume']        = 'Volume';
$string['fr_sec_monthly']       = 'Revenue by month';
$string['fr_rev_packages']      = 'From packages';
$string['fr_rev_subscriptions'] = 'From subscriptions';
$string['fr_rev_courses']       = 'From courses';
$string['fr_rev_programs']      = 'From programs';
$string['fr_rev_total']         = 'Total revenue';
$string['fr_disc_coupons']      = 'Via coupons';
$string['fr_disc_offers']       = 'Via offers';
$string['fr_disc_total']        = 'Total discount';
$string['fr_disc_gross']        = 'Gross before discount';
$string['fr_vol_packages']      = 'Package purchases';
$string['fr_vol_subscriptions'] = 'Subscription purchases';
$string['fr_vol_courses']       = 'Course purchases';
$string['fr_vol_programs']      = 'Program purchases';
$string['fr_vol_coupons']       = 'Coupon redemptions';
$string['fr_vol_offers']        = 'Offers applied';
$string['fr_c_month']           = 'Month';
// Shared columns.
$string['fr_c_name']            = 'Name';
$string['fr_c_price']           = 'Current price';
$string['fr_c_status']          = 'Status';
$string['fr_c_sales']           = 'Sales';
$string['fr_c_revenue']         = 'Revenue';
$string['fr_c_avgprice']        = 'Avg. price';
$string['fr_c_soldprice']       = 'Price when sold';
$string['fr_c_pricechanged']    = 'Price changed';
$string['fr_pricechanged_help'] = 'These sales were not all made at the current list price. Revenue and averages use the price each buyer actually paid.';
$string['fr_d_show']            = 'Show the individual sales';
$string['fr_d_hide']            = 'Hide the individual sales';
$string['fr_d_date']            = 'Date';
$string['fr_d_buyer']           = 'Buyer';
$string['fr_d_listprice']       = 'Price at sale';
$string['fr_d_paid']            = 'Paid';
$string['fr_d_discount']        = 'Discount';
$string['fr_d_source']          = 'Source';
$string['fr_d_source_online']   = 'Online';
$string['fr_d_source_assigned'] = 'Assigned by admin';
$string['fr_d_seats']           = '{$a} seats';
$string['fr_d_none']            = 'No individual sales in this period.';
$string['fr_d_loading']         = 'Loading…';
$string['fr_buyer_deleted']     = 'Deleted user';
// Packages tab.
$string['fr_c_online']          = 'Online';
$string['fr_c_assigned']        = 'Assigned';
$string['fr_c_flexsold']        = 'Flex sold';
$string['fr_c_flexconsumed']    = 'Flex used';
$string['fr_c_flexunused']      = 'Flex unused';
$string['fr_c_unusedvalue']     = 'Unused value';
$string['fr_unusedvalue_help']  = 'Money already collected for Flex the students have not used yet (a liability).';
// Subscriptions tab.
$string['fr_c_duration']        = 'Duration (days)';
$string['fr_c_normal']          = 'Normal';
$string['fr_c_b2b']             = 'B2B';
$string['fr_c_seats']           = 'Seats sold';
$string['fr_c_activesubs']      = 'Active';
$string['fr_c_b2bdiscount']     = 'B2B discount';
$string['fr_c_perseat']         = 'Per seat';
$string['fr_sub_normal_sales']  = 'Normal sales';
$string['fr_sub_normal_rev']    = 'Normal revenue';
$string['fr_sub_b2b_sales']     = 'B2B sales';
$string['fr_sub_b2b_rev']       = 'B2B revenue';
$string['fr_sub_normal_help']   = 'One student buys access for themselves. Revenue is the price they paid.';
$string['fr_sub_b2b_help']      = 'Same as a normal subscription, except the buyer is an organisation purchasing a number of seats instead of one user (a bulk discount may apply based on seat count). Revenue is simply the price actually paid — the "per seat" column is an extra figure for comparing unit price across purchases with different seat counts.';
// Courses tab.
$string['fr_c_course']          = 'Course';
$string['fr_c_program']         = 'Program';
$string['fr_c_buyers']          = 'Buyers';
$string['fr_c_netrevenue']      = 'Net revenue';
$string['fr_c_refunded']        = 'Refunded';
$string['fr_c_revoked']         = 'Revoked';
$string['fr_c_failed']          = 'Not completed';
$string['fr_course_deleted']    = 'deleted';
$string['fr_netrevenue_help']   = 'Revenue minus refunds. Revoked purchases (unbought via Manage courses) did not return money.';
// Coupons / offers tabs.
$string['fr_c_code']            = 'Code';
$string['fr_c_discount']        = 'Discount';
$string['fr_c_uses']            = 'Uses';
$string['fr_c_uniqueusers']     = 'Unique users';
$string['fr_c_original']        = 'Original total';
$string['fr_c_discounted']      = 'Discount given';
$string['fr_c_final']           = 'Charged total';
$string['fr_c_avgdiscount']     = 'Avg. discount';
$string['fr_c_window']          = 'Valid';
$string['fr_c_items']           = 'Applied to';
$string['fr_never']             = 'No limit';

// ─────────────────────────────────────────────────────────────────────────────
// UI strings — manage_reports.php (admin reports, 4 tabs).
// ─────────────────────────────────────────────────────────────────────────────
// Tabs.
$string['rp_tab_lessons']     = 'Lessons & attendance';
$string['rp_tab_platform']    = 'Platform earnings';
$string['rp_tab_packages']    = 'Packages & Flex';
$string['rp_tab_studentflex'] = 'Student Flex';
$string['rp_tab_useractivity'] = 'User Activity';
// User activity report (US-B2B-1-9).
$string['rp_f_userid']        = 'User ID';
$string['rp_f_email']         = 'Email';
$string['rp_ua_registered']   = 'Registered';
$string['rp_ua_lastlogin']    = 'Last login';
$string['rp_ua_status']       = 'Account';
$string['rp_ua_roles']        = 'Roles';
$string['rp_ua_subs']         = 'Subscriptions';
$string['rp_ua_memberships']  = 'B2B memberships';
$string['rp_ua_courses']      = 'Courses accessed';
$string['rp_ua_actions']      = 'Recent actions';
$string['rp_ua_none']         = 'None.';
$string['rp_enter_user']      = 'Enter a user ID or email and click Run.';
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
// Tabs on manage_subscriptions.php — the page now also hosts the subscription half of the
// old standalone "Admin settings" page.
$string['sub_tab_plans']           = 'Subscriptions';
$string['sub_tab_settings']        = 'Subscription settings';
$string['sub_plans_heading']       = 'Subscription plans';
$string['sub_new']                 = 'New subscription';
$string['sub_col_days']            = 'Days';
$string['sub_field_desc']          = 'Description (optional)';
$string['sub_field_days']          = 'Number of days';
// B2B plan fields (US-AD-5-1 / US-AD-5-2).
$string['sub_field_b2b']           = 'B2B purchase available';
$string['sub_seat_options']        = 'Seat options';
$string['sub_seat_options_help']   = 'Add one or more user-capacity options, each with its own discount %. The B2B price is calculated as (normal price × seats) − discount.';
$string['sub_col_seats']           = 'Seats';
$string['sub_col_discount']        = 'Discount %';
$string['sub_col_b2bprice']        = 'B2B price';
$string['sub_seat_add']            = 'Add seat option';
$string['sub_b2b_badge']           = 'B2B';
$string['ui_remove']               = 'Remove';

// ── B2B administrator dashboard + join page (US-B2B-1-2 .. 1-8) ──
$string['b2b_dashboard_title'] = 'B2B subscription';
$string['b2b_no_subs']         = 'You do not manage any B2B subscription.';
$string['b2b_purchased']       = 'Purchased seats';
$string['b2b_consumed']        = 'Consumed seats';
$string['b2b_available']       = 'Available seats';
$string['b2b_expires']         = 'Expires';
$string['b2b_pending']         = 'Pending';
$string['b2b_approved']        = 'Approved';
$string['b2b_rejected']        = 'Rejected';
$string['b2b_removed']         = 'Removed';
$string['b2b_expired']         = 'Expired';
$string['b2b_removed_returned'] = 'Removed (seat returned)';
$string['b2b_removed_kept']    = 'Removed (seat kept)';
$string['b2b_invite_heading']  = 'Invitation link';
$string['b2b_generate']        = 'Generate link';
$string['b2b_revoke']          = 'Revoke';
$string['b2b_copy']            = 'Copy';
$string['b2b_copied']          = 'Copied!';
$string['b2b_link_none']       = 'No active invitation link. Generate one to invite users.';
$string['b2b_link_active']     = 'An invitation link is active.';
$string['b2b_readonly_notice'] = 'This B2B subscription has ended. It is shown as history — you can no longer generate links or manage members.';
$string['b2b_members']         = 'Members';
$string['b2b_col_user']        = 'User';
$string['b2b_col_status']      = 'Status';
$string['b2b_col_seat']        = 'Seat';
$string['b2b_col_actions']     = 'Actions';
$string['b2b_approve']         = 'Approve';
$string['b2b_reject']          = 'Reject';
$string['b2b_remove']          = 'Remove';
$string['b2b_seat_yes']        = 'Consumes seat';
$string['b2b_seat_no']         = 'No seat';
$string['b2b_none']            = 'No members yet.';
$string['b2b_reason_prompt']   = 'Reason (optional)';
$string['b2b_confirm_reject_title'] = 'Reject request';
$string['b2b_confirm_reject_body']  = 'Reject the join request from {name}?';
$string['b2b_confirm_remove_title'] = 'Remove member';
$string['b2b_confirm_remove']  = 'Remove {name} from your B2B subscription?';
$string['b2b_confirm_revoke_title'] = 'Revoke invitation link';
$string['b2b_confirm_revoke_body']  = 'Anyone holding this link will no longer be able to join using it.';
$string['b2b_tab_all']         = 'All';
$string['b2b_action_done']     = 'Done.';
$string['b2b_never']           = 'Never';
// Join page.
$string['b2b_join_title']      = 'Join a B2B subscription';
$string['b2b_join_login']      = 'Please log in or register to join this B2B subscription.';
$string['b2b_join_guest_intro'] = 'You have been invited to join a B2B subscription. Log in with your existing account, or create a new account to continue.';
$string['b2b_join_loginbtn']   = 'Log in';
$string['b2b_join_registerbtn'] = 'Create new account';
$string['b2b_join_pending']    = 'Your request to join has been received and is pending approval by the B2B administrator.';
$string['b2b_join_already_pending'] = 'You already have a pending request for this subscription, awaiting the administrator\'s approval.';
$string['b2b_join_approved']   = 'You have been approved and now have access to the subscription courses.';
$string['b2b_join_already_approved'] = 'You are already a member of this subscription and have access to its courses.';
$string['b2b_join_rejected']   = 'Your previous request to join this subscription was rejected.';
$string['b2b_join_removed']    = 'You were removed from this subscription.';
$string['b2b_join_goto']       = 'Go to my dashboard';
$string['sub_courseavail_heading'] = 'Course subscription availability';
$string['sub_courseavail_desc']    = 'Choose courses and append them to a specific subscription.';
$string['sub_target']              = 'Target Subscription:';
$string['sub_select_placeholder']  = 'Select a subscription...';
$string['sub_save_courses']        = 'Save courses to subscription';
$string['sub_courses_search']      = 'Search courses…';
$string['sub_selectall']           = 'Select all';
$string['sub_clear']               = 'Clear';
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
$string['set_reminder_add']        = 'Add';
$string['set_reminder_placeholder'] = 'Mins (e.g. 15)';
$string['notif_lesson_reminder_subject'] = 'Your lesson starts soon!';
$string['notif_lesson_reminder_body'] = 'Hi {$a->studentname},

Your lesson "{$a->subject}" with {$a->teachername} is starting in {$a->time}.

Please join the lesson room on time.';

// ── Coupons + Offers (Phase 1: US-AD-7-*, US-AD-8-*, US-US-CP-*, US-US-OF-*) ──

// Capabilities + admin page titles.
$string['academy:managecoupons'] = 'Manage discount coupons';
$string['academy:manageoffers']  = 'Manage automatic offers';
$string['managecoupons'] = 'Manage coupons';
$string['manageoffers']  = 'Manage offers';
$string['mycoupons_title'] = 'Coupons & Offers';

// Scope "all of a type" labels (discount_manager::item_label).
$string['scope_all_course']       = 'All courses';
$string['scope_all_package']      = 'All packages';
$string['scope_all_subscription'] = 'All subscriptions';
$string['scope_all_program']      = 'All programs';

// Coupon admin UI.
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

// Coupon student UI.
$string['cpn_avail_heading'] = 'Available coupons';
$string['cpn_avail_desc']    = 'Coupon codes you can enter at checkout.';
$string['cpn_none_avail']    = 'No coupons available right now.';
$string['cpn_hist_heading']  = 'My coupon usage';
$string['cpn_hist_desc']     = 'Coupons you have redeemed.';
$string['cpn_no_history']    = 'You have not used any coupons yet.';
$string['cpn_have_code']     = 'Have a coupon code?';
$string['cpn_code']          = 'Coupon code';
$string['cpn_apply_buy']     = 'Apply & buy';

// Shared usage-history columns.
$string['usg_col_item']     = 'Item';
$string['usg_col_original'] = 'Original';
$string['usg_col_discount'] = 'Discount';
$string['usg_col_final']    = 'Paid';
$string['usg_col_date']     = 'Date';

// Offer admin UI.
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

// Offer student UI.
$string['ofr_avail_heading'] = 'Available offers';
$string['ofr_avail_desc']    = 'Discounts applied automatically at checkout.';
$string['ofr_none_avail']    = 'No active offers right now.';
$string['ofr_hist_heading']  = 'My offer history';
$string['ofr_hist_desc']     = 'Offers applied to your purchases.';
$string['ofr_no_history']    = 'No offers have been applied to your purchases yet.';

// Front-page checkout modal (coupon entry).
$string['hp_coupon']   = 'Coupon';
$string['hp_apply']    = 'Apply';
$string['hp_discount'] = 'Discount';

// API success messages.
$string['msg_coupon_created']     = 'Coupon created';
$string['msg_coupon_updated']     = 'Coupon updated';
$string['msg_coupon_activated']   = 'Coupon activated';
$string['msg_coupon_deactivated'] = 'Coupon deactivated';
$string['msg_coupon_deleted']     = 'Coupon deleted';
$string['msg_offer_created']      = 'Offer created';
$string['msg_offer_updated']      = 'Offer updated';
$string['msg_offer_activated']    = 'Offer activated';
$string['msg_offer_deactivated']  = 'Offer deactivated';
$string['msg_offer_deleted']      = 'Offer deleted';

// Validation / errors.
$string['err_itemtype']            = 'Invalid item type.';
$string['err_discounttype']        = 'Discount type must be percentage or fixed.';
$string['err_discountvalue']       = 'Discount value cannot be negative.';
$string['err_discountpercent']     = 'A percentage discount must be between 0 and 100.';
$string['err_maxdiscount']         = 'Max discount cannot be negative.';
$string['err_daterange']           = 'The end date must be after the start date.';
$string['err_usagetype']           = 'Usage type must be one-time or multiple.';
$string['err_couponcoderequired']  = 'A coupon code is required.';
$string['err_couponcodetaken']     = 'That coupon code is already in use.';
$string['err_couponnotfound']      = 'Coupon not found.';
$string['err_couponinactive']      = 'This coupon is not active.';
$string['err_couponnotstarted']    = 'This coupon is not valid yet.';
$string['err_couponexpired']       = 'This coupon has expired.';
$string['err_couponnotapplicable'] = 'This coupon does not apply to this item.';
$string['err_couponusedup']        = 'This coupon has reached its usage limit.';
$string['err_couponhasusages']     = 'This coupon has been used and can only be deactivated.';
$string['err_offernamerequired']   = 'An offer name is required.';
$string['err_offernotfound']       = 'Offer not found.';
$string['err_offerhasusages']      = 'This offer has been used and can only be deactivated.';
$string['ofr_delete_title']        = 'Delete offer';

// Manage Courses (admin: single-course purchases + "unbuy").
$string['managecourses']      = 'Manage courses';
$string['mc_heading']         = 'Course purchases';
$string['mc_desc']            = 'Users who bought a single course. Use "Unbuy" to unenrol a user and revoke the purchase.';
$string['mc_col_course']      = 'Course';
$string['mc_col_purchased']   = 'Purchased';
$string['mc_none']            = 'No course purchases yet.';
$string['mc_status_enrolled'] = 'Enrolled';
$string['mc_status_norole']   = 'No access';
$string['mc_unbuy']           = 'Unbuy';
$string['mc_unbuy_title']     = 'Revoke course purchase';
$string['mc_unbuy_confirm']   = 'Unenrol <b>{$a->user}</b> from <b>{$a->course}</b> and revoke this purchase?';
$string['mc_unbuy_refund']    = 'Mark this purchase as refunded';
$string['mc_unbuy_success']   = 'The course purchase was revoked.';
$string['mc_revoked']         = 'The course purchase was revoked.';
$string['mc_course_deleted']  = '(deleted course)';
$string['mc_txn_notfound']    = 'Purchase not found.';
$string['mc_not_active']      = 'This purchase is not active and cannot be revoked.';

// Certificate eligibility (plugin-agnostic: decides who is eligible for each certificate).
$string['certeligibility']              = 'Certificate eligibility';
$string['cert_desc']                    = 'Define certificate eligibility for programs. Pick a program to see its certificates, then create, edit or delete them. Each certificate has a single eligibility rule that decides whether a student qualifies. Course certificates are handled separately (the Custom Certificate activity inside the course). This is eligibility information only — no certificate is issued or downloaded here.';
$string['cert_programs_heading']        = 'Programs';
$string['cert_manage']                  = 'Manage certificates';
$string['cert_back']                    = 'Back to programs';
$string['cert_prog_certs']              = 'Certificates — {$a}';
$string['cert_course']                  = 'Course ID';
$string['cert_load']                    = 'Load';
$string['cert_new']                     = '+ New certificate';
$string['cert_none']                    = 'No certificates yet.';
$string['cert_name']                    = 'Certificate name';
$string['cert_type']                    = 'Type';
$string['cert_type_completion']         = 'Completion';
$string['cert_type_attendance']         = 'Attendance';
$string['cert_type_excellence']         = 'Excellence';
$string['cert_type_custom']             = 'Custom';
$string['cert_externalref']             = 'Linked certificate activity ID (optional)';
$string['cert_externalref_help']        = 'Leave 0 until a Custom Certificate activity exists. Then set this to that activity so it stays the single source of truth for the certificate — this wrapper only governs eligibility.';
$string['cert_operator']                = 'When is the student eligible?';
$string['cert_op_and']                  = 'All rules must pass (AND)';
$string['cert_op_or']                   = 'Any rule may pass (OR)';
$string['cert_enabled']                 = 'Eligibility check enabled';
$string['cert_add_rule']                = '+ Add rule';
$string['cert_no_rules']                = 'No rules yet. Add at least one rule.';
$string['cert_saved']                   = 'Certificate saved.';
$string['cert_deleted']                 = 'Certificate deleted.';
$string['cert_confirm_delete']          = 'Delete this certificate and its rules?';
$string['cert_rule']                    = 'Rule';
$string['cert_pick']                    = '— choose —';
$string['cert_note']                    = 'A student must satisfy the rule above before this certificate is considered available. A disabled certificate means no one is eligible.';
$string['cert_rule_course_progress']    = 'Course progress ≥ threshold %';
$string['cert_rule_attendance']         = 'Attendance ≥ threshold %';
$string['cert_rule_quiz_passed']        = 'Quiz passed';
$string['cert_rule_assign_completed']   = 'Assignment completed';
$string['cert_rule_course_completed']   = 'Course completed';
$string['cert_rule_program_completed']  = 'Program completed';
$string['cert_rule_program_progress']   = 'Program progress ≥ threshold %';
$string['cert_rule_program_courses_completed'] = 'All program courses completed';
$string['cert_rule_threshold_percent']  = 'Threshold (%)';
$string['cert_rule_quiz']               = 'Quiz';
$string['cert_rule_assign']             = 'Assignment';
$string['cert_unit_points']             = 'points';
// The same rules stated as a concrete instruction to the student, with the admin's configuration
// filled in. The cert_rule_* strings above name the rule type for the admin picker; these tell a
// student what they actually have to do.
$string['cert_req_course_progress']     = 'Complete at least {$a->percent}% of "{$a->course}"';
$string['cert_req_attendance']          = 'Attend at least {$a}% of your live sessions in this course';
$string['cert_req_quiz_passed']         = 'Pass the quiz "{$a}"';
$string['cert_req_quiz_passed_grade']   = 'Score at least {$a->grade} on the quiz "{$a->quiz}"';
$string['cert_req_assign_completed']    = 'Complete the assignment "{$a}"';
$string['cert_req_course_completed']    = 'Complete the course "{$a}"';
$string['cert_req_program_completed']   = 'Complete the whole program';
$string['cert_req_program_progress']    = 'Complete at least {$a}% of the program\'s courses';
$string['cert_req_program_courses_completed'] = 'Complete all {$a} courses in this program';
$string['cert_req_program_courses_completed_any'] = 'Complete every course in this program';
$string['cert_scope']                   = 'Scope';
$string['cert_scope_course']            = 'Course';
$string['cert_scope_program']           = 'Program';
$string['cert_program']                 = 'Program';
$string['cert_pickprogram']             = '— choose a program —';
// Student-facing card on the program page.
$string['cert_student_title']           = 'Program certificates';
$string['cert_student_eligible']        = 'Eligible';
$string['cert_student_not_eligible']    = 'In progress';
$string['cert_student_all']             = 'You must meet all of the requirements below.';
$string['cert_student_any']             = 'You must meet at least one of the requirements below.';
$string['cert_student_eligible_note']   = 'You have met the requirements for this certificate.';
$string['cert_student_pending_note']    = 'Complete the remaining requirements to qualify for this certificate.';
$string['cert_student_download']        = 'Get your certificate';
// Preview of the same card on the catalogue page, for a student who has not joined the program yet.
$string['cert_student_preview_title']   = 'Certificates you can earn';
$string['cert_student_preview_intro']   = 'This program awards the certificates below. Here is what each one requires.';
$string['cert_student_included']        = 'Included';
$string['cert_student_preview_note']    = 'Join this program to start working towards this certificate.';
$string['cert_link_activity']           = 'Certificate activity';
$string['cert_link_none']               = '— not linked (eligibility only) —';
$string['cert_link_unavailable']        = '— no Custom Certificate activities available —';
$string['cert_link_help']               = 'The Custom Certificate activity that issues this certificate. A program has no course of its own, so create a host course holding one activity per program. Students who become eligible are enrolled into that course automatically so they can open and download it.';
$string['err_certnotfound']             = 'Certificate not found.';
$string['err_certnoteligible']          = 'You have not met the requirements for this certificate yet.';
$string['err_certnotlinked']            = 'This certificate is not available to open yet.';
$string['err_certactivityrestricted']   = 'Finish all the required activities in the program before opening this certificate.';
$string['err_certcoursenotfound']       = 'Course not found.';
$string['err_certprogramnotfound']      = 'Program not found.';
$string['err_certscopeinvalid']         = 'A certificate must be scoped to exactly one course or one program.';
$string['err_certscopemismatch']        = 'Rule {$a} does not belong to this certificate\'s scope.';
$string['err_certruleunknown']          = 'Unknown certificate rule type: {$a}';
$string['err_certrulesinvalid']         = 'The rules payload is not valid JSON.';

