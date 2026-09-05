<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
$mode = $argv[1] ?? 'test';
if ($mode === 'test') {
    set_config('custommenuitems', "الرئيسية|/\nالدورات|/local/nit_category/search.php?q=\n-كل الأقسام|/local/nit_category/index.php\n-بحث|/local/nit_category/search.php?q=\nمن نحن|/local/profilefields/page.php?page=about");
} else {
    set_config('custommenuitems', "الرئيسية|/\nالدورات|/local/nit_category/search.php?q=\nمن نحن|/local/profilefields/page.php?page=about\nاتصل بنا|/local/profilefields/page.php?page=contact");
}
purge_all_caches();
echo "set: $mode\n";
