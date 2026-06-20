<?php
define('CLI_SCRIPT', true);
$t1 = microtime(true);
require(__DIR__ . '/config.php');
$t2 = microtime(true);
echo "Config load: " . round(($t2-$t1)*1000) . "ms\n";

$t3 = microtime(true);
$PAGE->set_context(context_system::instance());
$t4 = microtime(true);
echo "Context setup: " . round(($t4-$t3)*1000) . "ms\n";

$t5 = microtime(true);
$courses = $DB->get_records('course', null, '', 'id', 0, 10);
$t6 = microtime(true);
echo "DB query (10 courses): " . round(($t6-$t5)*1000) . "ms\n";

$t7 = microtime(true);
$plugins = core_plugin_manager::instance()->get_plugins_of_type('block');
$t8 = microtime(true);
echo "Plugin scan (blocks): " . round(($t8-$t7)*1000) . "ms\n";

$t9 = microtime(true);
$allplugins = core_plugin_manager::instance()->get_plugin_types();
$t10 = microtime(true);
echo "All plugin types: " . round(($t10-$t9)*1000) . "ms (" . count($allplugins) . " types)\n";

$t11 = microtime(true);
$strings = get_string_manager()->get_list_of_translations();
$t12 = microtime(true);
echo "String manager: " . round(($t12-$t11)*1000) . "ms\n";

echo "\nTotal: " . round(($t12-$t1)*1000) . "ms\n";
echo "Blocks found: " . count($plugins) . "\n";
echo "PHP memory peak: " . round(memory_get_peak_usage(true)/1024/1024) . "MB\n";
