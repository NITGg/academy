<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Academy Flex platform';
$string['academy:managepackages'] = 'Manage lesson (Flex) packages';
$string['managepackages'] = 'Manage lesson packages';
$string['managesettings'] = 'Lesson settings';
$string['myteacherprofile'] = 'My teacher profile';
$string['teacherprofile'] = 'Teacher profile';
$string['editmyteacherprofile'] = 'Edit my teacher profile';
$string['notateacher'] = 'This page is only available to teachers.';
$string['mylessons'] = 'My lessons';
$string['studenthub'] = 'Book lessons & Flex';
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
