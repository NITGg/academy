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
$string['err_notime']              = 'A valid time is required';
$string['err_forbidden']           = 'You are not allowed to perform this action';
$string['err_badstate']            = 'This action is not allowed for the lesson\'s current status';
$string['err_badaction']           = 'Unknown action';
$string['err_lessonnotfound']      = 'Lesson not found';
$string['err_tooearlytostart']     = 'The lesson cannot be started yet';
$string['err_completetooearly']    = 'The lesson cannot be completed yet — it must run for the configured time first';
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
