<?php
// TEMPORARY local-only preview helper - deleted immediately after use.
require(__DIR__ . '/config.php');
if (($_SERVER['HTTP_HOST'] ?? '') !== 'localhost:8080') {
    die('local only');
}
$u = $DB->get_record('user', ['username' => 'admin']);
if (!$u) {
    $u = $DB->get_record_sql("SELECT * FROM {user} WHERE deleted=0 AND confirmed=1 AND username<>'guest' ORDER BY id", null, IGNORE_MULTIPLE);
}
complete_user_login($u);
redirect(new moodle_url('/local/profilefields/account.php'));
