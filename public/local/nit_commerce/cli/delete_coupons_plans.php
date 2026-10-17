<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Delete every coupon and every subscription plan, so the shop can be set up again from scratch
 * under the one-category rule.
 *
 * It prints what it would remove and removes nothing unless --execute is given. With --execute it
 * first writes every row it is about to delete to a JSON file in moodledata, then deletes through
 * the plugins' own delete functions, so each coupon's scope rows and each plan's prices, seat
 * tiers, category and course list go with it.
 *
 * A coupon that was ever redeemed, or a plan that was ever bought, is NOT deleted: its history is
 * what the financial reports, refunds and the buyers' own access are built on. Those are listed
 * as "kept". To delete them too, clear that history first with
 * local/nit_finance/cli/reset_financial_data.php --execute, then run this again.
 *
 * Usage:
 *   php public/local/nit_commerce/cli/delete_coupons_plans.php            (dry run)
 *   php public/local/nit_commerce/cli/delete_coupons_plans.php --execute
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    ['execute' => false, 'help' => false],
    ['e' => 'execute', 'h' => 'help']
);
if ($unrecognized) {
    cli_error('Unknown option: ' . implode(', ', $unrecognized));
}
if ($options['help']) {
    echo "Delete every coupon and every subscription plan (unused ones only).

  (no options)    Dry run: print what would be deleted, change nothing.
  --execute, -e   Back up the rows to moodledata, then delete them.
  --help, -h      This message.
";
    exit(0);
}

global $DB;
$dbman = $DB->get_manager();
$hassubs = $dbman->table_exists('nit_subscription')
    && class_exists('\local_nit_subscriptions\subscription_manager');
$execute = !empty($options['execute']);

$name = function (string $raw): string {
    return \local_nit_commerce\discount_manager::resolve_mlang($raw);
};

echo $execute ? "DELETING coupons and subscription plans\n\n" : "DRY RUN — nothing will be changed\n\n";

// ── What goes and what stays ──
$coupons = $DB->get_records('nit_coupon', null, 'id ASC');
$deletecoupons = [];
echo "Coupons (" . count($coupons) . "):\n";
foreach ($coupons as $c) {
    $used = $DB->count_records('nit_coupon_usage', ['couponid' => $c->id]);
    if ($used) {
        echo sprintf("  KEEP    #%d %s — redeemed %d time(s)\n", $c->id, $c->code, $used);
    } else {
        echo sprintf("  delete  #%d %s\n", $c->id, $c->code);
        $deletecoupons[] = $c;
    }
}

$plans = $hassubs ? $DB->get_records('nit_subscription', null, 'id ASC') : [];
$deleteplans = [];
echo "\nSubscription plans (" . count($plans) . "):\n";
foreach ($plans as $p) {
    $bought = $DB->count_records('nit_sub_purchase', ['subscriptionid' => $p->id]);
    if ($bought) {
        $active = $DB->count_records('nit_sub_purchase', ['subscriptionid' => $p->id, 'status' => 'active']);
        echo sprintf("  KEEP    #%d %s — %d purchase(s), %d active\n", $p->id, $name($p->name), $bought, $active);
    } else {
        echo sprintf("  delete  #%d %s\n", $p->id, $name($p->name));
        $deleteplans[] = $p;
    }
}

// Offers that name a plan about to disappear would be left pointing at nothing.
$deadofferitems = [];
if ($deleteplans && $dbman->table_exists('nit_offer_item')) {
    list($insql, $params) = $DB->get_in_or_equal(array_column($deleteplans, 'id'), SQL_PARAMS_NAMED);
    $deadofferitems = $DB->get_records_select('nit_offer_item', "item_type = 'subscription' AND item_id {$insql}", $params);
    foreach ($deadofferitems as $it) {
        echo sprintf("  (offer #%d loses its scope row for plan #%d)\n", $it->offerid, $it->item_id);
    }
}

$kept = (count($coupons) - count($deletecoupons)) + (count($plans) - count($deleteplans));
if ($kept) {
    echo "\n{$kept} item(s) are kept because they have purchase/redemption history.\n"
        . "To delete those too: run local/nit_finance/cli/reset_financial_data.php --execute first\n"
        . "(it clears ALL orders, redemptions and plan purchases), then run this again.\n";
}

if (!$execute) {
    echo "\nNothing was changed. Re-run with --execute to do it.\n";
    exit(0);
}
if (!$deletecoupons && !$deleteplans) {
    echo "\nNothing to delete.\n";
    exit(0);
}

// ── Backup ──
$backup = ['created' => date('c'), 'coupons' => [], 'plans' => [], 'offer_items' => array_values($deadofferitems)];
foreach ($deletecoupons as $c) {
    $backup['coupons'][] = [
        'coupon' => $c,
        'items'  => array_values($DB->get_records('nit_coupon_item', ['couponid' => $c->id])),
    ];
}
foreach ($deleteplans as $p) {
    $row = ['plan' => $p];
    foreach (['nit_sub_price', 'nit_sub_seat_option', 'nit_subscription_category', 'nit_course_access'] as $t) {
        $row[$t] = $dbman->table_exists($t) ? array_values($DB->get_records($t, ['subscriptionid' => $p->id])) : [];
    }
    $backup['plans'][] = $row;
}
$dir = make_writable_directory($CFG->dataroot . '/nit_backups');
$file = $dir . '/coupons_plans_' . date('Ymd_His') . '.json';
if (file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    cli_error("Could not write the backup to {$file}; nothing was deleted.");
}
echo "\nBackup: {$file}\n";

// ── Delete ──
foreach ($deletecoupons as $c) {
    \local_nit_commerce\coupon_manager::delete_coupon($c->id);
    echo "  deleted coupon #{$c->id} {$c->code}\n";
}
foreach ($deleteplans as $p) {
    \local_nit_subscriptions\subscription_manager::delete_subscription($p->id);
    // delete_subscription() leaves the per-country price rows behind.
    $DB->delete_records('nit_sub_price', ['subscriptionid' => $p->id]);
    echo "  deleted plan #{$p->id} " . $name($p->name) . "\n";
}
foreach ($deadofferitems as $it) {
    $DB->delete_records('nit_offer_item', ['id' => $it->id]);
}

echo "\nDone. Purge caches so the home and category pages stop showing them:\n"
    . "  php admin/cli/purge_caches.php\n";
exit(0);
