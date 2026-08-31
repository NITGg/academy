<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Academy';
$string['academy:manageplatform'] = 'Manage the Academy platform';

// Welcome notification (sent on first login after email signup).
$string['messageprovider:welcome'] = 'Welcome message';
$string['welcome_subject'] = 'Welcome to {$a}!';
$string['welcome_small'] = 'Welcome to {$a}!';
$string['welcome_body'] = 'Hi {$a->name},

Welcome to {$a->site}! Your account is now active.

You can browse courses, enrol, and start learning right away. We are glad to have you with us.';

// Dispatcher / envelope.
$string['err_postrequired']     = 'This action requires POST';
$string['err_authrequired']     = 'Authentication required';
$string['err_invalidtoken']     = 'Invalid token';
$string['err_permissiondenied'] = 'Permission denied';
$string['err_unknownfunction']  = 'Unknown function';
$string['err_internal']         = 'An internal error occurred. Please try again later.';
$string['err_teachernotfound']  = 'Teacher not found.';

// Password reset (OTP).
$string['err_invalidemail']     = 'Please enter a valid email address.';
$string['err_toomanyrequests']  = 'Too many code requests. Please wait a few minutes and try again.';
$string['err_otpexpired']       = 'This code has expired. Please request a new one.';
$string['err_otplocked']        = 'Too many incorrect attempts. Please request a new code.';
$string['err_otpinvalid']       = 'The code you entered is incorrect.';
$string['err_resetexpired']     = 'Your reset session has expired. Please start again.';
$string['err_weakpassword']     = 'The new password does not meet the requirements.';
$string['err_wrongpassword']    = 'Your current password is incorrect.';
$string['err_authnochange']     = 'This account cannot change its password here (it signs in with Google).';
$string['otp_subject']          = '{$a}: your password reset code';
$string['otp_body']             = 'Hi {$a->name},

Your password reset code for {$a->site} is: {$a->code}

It is valid for {$a->mins} minutes. If you did not request this, you can ignore this email.';

// Quiz manager.
$string['notenrolled'] = 'You are not enrolled in this course';

// Account lockout (AC-4.3.2 / AC-4.3.4). Core blocks the account; these are the
// only place the block is described to the learner, on the login page and in the
// app alike. See \local_academy\lockout.
$string['lockout_blocked'] = 'Your account has been temporarily blocked after {$a->attempts} failed sign-in attempts. Please try again in {$a->wait}, or use the unlock link we have just emailed you.';
$string['lockout_blocked_nowait'] = 'Your account has been blocked after {$a} failed sign-in attempts. Please use the unlock link we have just emailed you, or contact support.';

// Administrator settings.
$string['settings_passwordreset'] = 'Password reset codes';
$string['settings_passwordreset_desc'] = 'Limits for the one-time code sent when someone asks to reset a forgotten password. Account lockout after repeated failed sign-ins is separate and is configured under Security &gt; Site security settings.';
$string['settings_otprequestmax'] = 'Maximum reset requests';
$string['settings_otprequestmax_desc'] = 'How many reset codes one email address may request within the window below. Further requests are refused until the window passes.';
$string['settings_otprequestwindow'] = 'Reset request window';
$string['settings_otprequestwindow_desc'] = 'The period the request limit is counted over.';
$string['settings_otpmaxattempts'] = 'Maximum incorrect code entries';
$string['settings_otpmaxattempts_desc'] = 'How many times a code may be entered incorrectly before that code is invalidated and a new one must be requested.';
$string['settings_otpttl'] = 'Code validity';
$string['settings_otpttl_desc'] = 'How long a reset code remains usable after it is emailed.';

// Login. Worded identically to core's 'invalidlogin' on purpose - see
// \local_academy\login_manager::failure_exception() for why core's own string
// cannot be reached through moodle_exception.
$string['err_invalidlogin'] = 'Invalid login, please try again';
$string['err_usernotconfirmed'] = 'Your account has not been confirmed yet. Please open the confirmation link we emailed you, then sign in again.';
