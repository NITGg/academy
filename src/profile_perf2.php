<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

$t1 = microtime(true);
$plugins = core_plugin_manager::instance()->get_plugins_of_type('block');
$t2 = microtime(true);
echo "Block scan (1st): " . round(($t2-$t1)*1000) . "ms - " . count($plugins) . " blocks\n";

$t3 = microtime(true);
$plugins2 = core_plugin_manager::instance()->get_plugins_of_type('block');
$t4 = microtime(true);
echo "Block scan (2nd/cached): " . round(($t4-$t3)*1000) . "ms\n";

// Check if MUC (Moodle Universal Cache) is working
$t5 = microtime(true);
$cache = cache::make('core', 'plugin_manager');
$data = $cache->get('siteinfo');
$t6 = microtime(true);
echo "MUC cache get: " . round(($t6-$t5)*1000) . "ms - " . ($data ? "HIT" : "MISS") . "\n";

// Check mod plugin scan
$t7 = microtime(true);
$mods = core_plugin_manager::instance()->get_plugins_of_type('mod');
$t8 = microtime(true);
echo "Mod scan: " . round(($t8-$t7)*1000) . "ms - " . count($mods) . " mods\n";

// Check theme scan
$t9 = microtime(true);
$themes = core_plugin_manager::instance()->get_plugins_of_type('theme');
$t10 = microtime(true);
echo "Theme scan: " . round(($t10-$t9)*1000) . "ms\n";

// Check the actual file system speed
$t11 = microtime(true);
$blockdir = $CFG->dirroot . '/blocks';
$dirs = scandir($blockdir);
$t12 = microtime(true);
echo "Scandir blocks/: " . round(($t12-$t11)*1000) . "ms - " . count($dirs) . " entries\n";

// Check a single block version load
$t13 = microtime(true);
foreach ($dirs as $d) {
    $vp = $blockdir . '/' . $d . '/version.php';
    if (file_exists($vp)) {
        // just stat the file
    }
}
$t14 = microtime(true);
echo "File exists check all blocks: " . round(($t14-$t13)*1000) . "ms\n";

echo "\nMoodle cache stores:\n";
$cacheconfig = cache_config::instance();
$stores = $cacheconfig->get_all_stores();
foreach ($stores as $name => $store) {
    echo "  $name => " . $store['plugin'] . "\n";
}
