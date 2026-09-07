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
 * Show what a category landing page will advertise, and why.
 *
 * The three sections a category page grows — its plans, its coupons and the visitor's own
 * courses — are each filtered by a rule that lives on the server, and a rule that cannot be
 * inspected is a rule nobody trusts. This prints the answer for one category, or for every
 * category at once, together with the reason each item was kept or dropped.
 *
 * Usage:
 *   php local/nit_category/cli/catscope_diagnose.php --category=14
 *   php local/nit_category/cli/catscope_diagnose.php --all
 *   php local/nit_category/cli/catscope_diagnose.php --category=14 --user=25
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'category' => 0,
    'all'      => false,
    'user'     => 0,
    'help'     => false,
], ['c' => 'category', 'a' => 'all', 'u' => 'user', 'h' => 'help']);

if ($options['help'] || (!$options['all'] && !$options['category'])) {
    cli_writeln("Show what each category landing page advertises.

  --category=ID   one category
  --all           every category
  --user=ID       resolve the coupon list as this user (default: nobody, i.e. a guest)
  --help          this text
");
    exit(0);
}

$catids = [];
if ($options['all']) {
    $catids = $DB->get_fieldset_select('course_categories', 'id', '1=1', [], 'sortorder ASC');
} else {
    $catids = [(int) $options['category']];
}

$userid = (int) $options['user'];

foreach ($catids as $catid) {
    $catid = (int) $catid;
    $name = \local_nit_core\helper\category::name($catid);
    if ($name === '') {
        cli_writeln("Category {$catid}: does not exist.");
        continue;
    }

    $branch = \local_nit_core\helper\category::branch($catid);
    $subtree = \local_nit_core\helper\category::subtree($catid);

    cli_separator();
    cli_writeln("Category {$catid}: {$name}");
    cli_writeln('  branch (matched against): ' . implode(', ', $branch));
    cli_writeln('  subtree (its courses):    ' . implode(', ', $subtree));

    // ── Plans ──
    cli_writeln('  Plans:');
    $plans = \local_nit_subscriptions\subscription_manager::get_subscriptions(
        \local_nit_subscriptions\subscription_manager::STATUS_ACTIVE);
    $shown = 0;
    foreach ($plans as $plan) {
        $assigned = \local_nit_subscriptions\subscription_manager::get_categories((int) $plan->id);
        $why = $assigned
            ? ('assigned to ' . (in_array(0, $assigned, true) ? 'ALL' : implode('/', $assigned)))
            : ('auto, courses in ' . (implode('/', \local_nit_core\helper\category::of_courses(
                \local_nit_subscriptions\subscription_manager::courses_for_subscription((int) $plan->id))) ?: 'nothing'));
        $match = \local_nit_subscriptions\subscription_manager::matches_category((int) $plan->id, $catid);
        $shown += $match ? 1 : 0;
        cli_writeln(sprintf('    [%s] #%d %-40s (%s)', $match ? 'x' : ' ', $plan->id,
            \local_nit_subscriptions\subscription_manager::resolve_mlang($plan->name), $why));
    }
    cli_writeln("    -> {$shown} of " . count($plans) . ' shown');

    // ── Coupons ──
    cli_writeln('  Coupons:');
    $all = \local_nit_commerce\coupon_manager::get_available_coupons($userid ?: null, 0);
    $here = \local_nit_commerce\coupon_manager::get_available_coupons($userid ?: null, $catid);
    $herecodes = array_column($here, 'code');
    foreach ($all as $c) {
        $scope = [];
        foreach ($c['applies_to'] as $a) {
            $scope[] = $a['item_type'] . ':' . $a['item_id'];
        }
        cli_writeln(sprintf('    [%s] %-20s (%s)',
            in_array($c['code'], $herecodes, true) ? 'x' : ' ', $c['code'], implode(' ', $scope)));
    }
    cli_writeln('    -> ' . count($here) . ' of ' . count($all) . ' shown');

    // ── Offers ──
    cli_writeln('  Offers:');
    $alloffers = \local_nit_commerce\offer_manager::get_available_offers(0);
    $hereoffers = \local_nit_commerce\offer_manager::get_available_offers($catid);
    $hereids = array_column($hereoffers, 'id');
    foreach ($alloffers as $o) {
        $scope = [];
        foreach ($o['applies_to'] as $a) {
            $scope[] = $a['item_type'] . ':' . $a['item_id'];
        }
        cli_writeln(sprintf('    [%s] #%d %-30s (%s)', in_array($o['id'], $hereids, true) ? 'x' : ' ',
            $o['id'], $o['name'], implode(' ', $scope)));
    }
    cli_writeln('    -> ' . count($hereoffers) . ' of ' . count($alloffers) . ' shown');
}

cli_writeln('');
exit(0);
