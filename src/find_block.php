<?php
require_once(__DIR__.'/config.php');
global $DB;
$records = $DB->get_records_sql("SELECT id, blockname, configdata FROM {block_instances}");
foreach($records as $r) {
    if (strpos(base64_decode($r->configdata), 'تصنيفات حسب التخصص') !== false) {
        echo "Found in block: " . $r->blockname . " (ID: " . $r->id . ")\n";
    }
}
