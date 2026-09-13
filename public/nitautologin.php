<?php
// THROW-AWAY dev helper — signs the local browser in as admin. Delete after use.
require_once(__DIR__ . '/config.php');
$user = get_admin();
complete_user_login($user);
redirect(new moodle_url($_GET['go'] ?? '/'));
