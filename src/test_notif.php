<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
global $DB;
$admin = $DB->get_record('user', ['username' => 'admin']);
$lesson = (object)[
    'id' => 9999,
    'subject' => 'Test Subject from AI',
    'studentid' => $admin->id,
    'teacherid' => $admin->id,
    'confirmed_time' => 0,
    'requested_time' => time(),
    'note' => 'Testing local notification'
];

\local_academy\notification_manager::lesson_event($lesson, 'requested', $admin->id, $admin->id);

$records = $DB->get_records('notifications', ['component' => 'local_academy'], 'id DESC', '*', 0, 1);
$latest = reset($records);
echo "LATEST NOTIFICATION:\n";
print_r($latest);
