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

// Quiz manager.
$string['notenrolled'] = 'You are not enrolled in this course';
