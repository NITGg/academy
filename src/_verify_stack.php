<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
global $DB;
$adminid = ($u = $DB->get_record('user', ['username'=>'admin'])) ? $u->id : 2;
$pkg = $DB->get_records('academy_packages', null, '', 'id,price', 0, 1);
$p = reset($pkg); $pid = $p->id; $base = (float)$p->price;
echo "package #$pid base=$base\n";
$o1 = $o2 = null;
try {
    $o1 = \local_academy\offer_manager::create_offer(['name'=>'{mlang en}Off30A{mlang}{mlang ar}عرض30أ{mlang}','discount_type'=>'percent','discount_value'=>30,'active'=>1,'items'=>[['item_type'=>'package','item_id'=>0]]], $adminid);
    $o2 = \local_academy\offer_manager::create_offer(['name'=>'{mlang en}Off30B{mlang}{mlang ar}عرض30ب{mlang}','discount_type'=>'percent','discount_value'=>30,'active'=>1,'items'=>[['item_type'=>'package','item_id'=>0]]], $adminid);
    $r = \local_academy\discount_manager::resolve('package', $pid, $adminid, '');
    $expDisc = round($base*0.6,2); $expFinal = round($base*0.4,2);
    echo "resolve: offer_discount={$r['offer_discount']} final={$r['final']} offers=".count($r['offers'])."\n";
    echo "  expected discount=$expDisc final=$expFinal => ".(($r['offer_discount']==$expDisc && $r['final']==$expFinal && count($r['offers'])==2)?'PASS':'FAIL')."\n";
    $s = \local_academy\discount_manager::offer_summary('package', $pid, $base);
    echo "summary: label={$s['label']} name='{$s['name']}' discount={$s['discount']} final={$s['final']}\n";
    // record_usage writes 2 rows
    \local_academy\discount_manager::record_usage($r, $adminid, 424242, 'package', $pid);
    $cnt = $DB->count_records('academy_offer_usages', ['transactionid'=>424242]);
    echo "usage rows for txn=".$cnt." => ".($cnt==2?'PASS':'FAIL')."\n";
    $DB->delete_records('academy_offer_usages', ['transactionid'=>424242]);
    echo "STACK OK\n";
} finally {
    foreach ([$o1,$o2] as $oid) { if ($oid) { $DB->delete_records('academy_offer_items',['offerid'=>$oid]); $DB->delete_records('academy_offers',['id'=>$oid]); } }
    echo "cleaned\n";
}
