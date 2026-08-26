<?php
/**
 * Run the subscription-expiry pass by hand, or preview what it would do.
 *
 * The scheduled task local_nit_subscriptions\task\expire_subscriptions does this hourly on
 * cron. This script exists to (a) clear the backlog right after deploying the feature, on a
 * site where subscriptions have been quietly running past their deadline, and (b) let an
 * admin see exactly who is about to lose which course before anything is changed.
 *
 * Usage (from the Moodle code root, inside the container):
 *   php public/local/nit_subscriptions/cli/expire_subscriptions.php --list
 *   php public/local/nit_subscriptions/cli/expire_subscriptions.php --run
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_nit_subscriptions\subscription_manager;
use local_nit_subscriptions\subscription_purchase_manager;

list($options, $unrecognized) = cli_get_params(
    ['list' => false, 'run' => false, 'help' => false],
    ['h' => 'help', 'l' => 'list', 'r' => 'run']
);

if ($options['help'] || (!$options['list'] && !$options['run'])) {
    echo "End subscriptions whose deadline has passed.\n";
    echo "  --list   show the purchases that are due, change nothing\n";
    echo "  --run    expire them and unenrol the students\n";
    exit(0);
}

$now = time();
$due = $DB->get_records_select('nit_sub_purchase',
    'status = :status AND expires_at > 0 AND expires_at <= :now',
    ['status' => subscription_purchase_manager::STATUS_ACTIVE, 'now' => $now],
    'expires_at ASC');

if (!$due) {
    echo "Nothing due — no active subscription is past its deadline.\n";
    exit(0);
}

echo count($due) . " subscription(s) past their deadline:\n";
foreach ($due as $p) {
    $user = $DB->get_record('user', ['id' => $p->userid], 'id, firstname, lastname, email');
    $plan = $DB->get_field('nit_subscription', 'name', ['id' => $p->subscriptionid]);
    $courses = subscription_manager::courses_for_subscription((int) $p->subscriptionid);
    printf("  #%d  %s <%s>  plan \"%s\"  expired %s  (%d course(s) in plan)\n",
        $p->id,
        $user ? fullname($user) : "user {$p->userid}",
        $user->email ?? '?',
        subscription_manager::resolve_mlang((string) $plan),
        userdate((int) $p->expires_at),
        count($courses));
}

if (!$options['run']) {
    echo "\nPreview only. Re-run with --run to apply.\n";
    exit(0);
}

$result = subscription_purchase_manager::expire_due_purchases();
echo "\nExpired {$result['purchases']} subscription(s); "
    . "removed {$result['unenrolments']} course enrolment(s).\n";
exit(0);
