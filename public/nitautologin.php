<?php
// THROW-AWAY dev helper — signs the local browser in as admin (or ?uid=N). Delete after use.
require_once(__DIR__ . '/config.php');
$uid = (int) ($_GET['uid'] ?? 0);
$user = $uid ? core_user::get_user($uid, '*', MUST_EXIST) : get_admin();
complete_user_login($user);
redirect(new moodle_url($_GET['go'] ?? '/'));
