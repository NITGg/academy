<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
global $DB;
$p = $DB->get_record('message_providers', ['component'=>'local_academy', 'name'=>'lessonnotification']);
print_r($p);
