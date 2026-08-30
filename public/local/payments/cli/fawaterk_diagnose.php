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
 * Diagnostic: check the Fawaterk credentials and show what the account offers.
 *
 * Calls getPaymentmethods with the configured vendor key — the cheapest call
 * that proves the key, the mode (sandbox vs live) and the account state are all
 * consistent. "Invalid Token or inactive vendor" almost always means the key
 * belongs to the other environment: staging and live are separate accounts.
 *
 * Usage (from the Moodle root, or via docker):
 *   php local/payments/cli/fawaterk_diagnose.php
 *   php local/payments/cli/fawaterk_diagnose.php --purge-cache
 *
 * Docker (same style as the deploy commands):
 *   docker compose exec moodle php local/payments/cli/fawaterk_diagnose.php
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'purge-cache' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Check the Fawaterk credentials and list the account's payment methods.

Options:
  --purge-cache   Drop the cached method list before checking.
  -h, --help      Print this help.
");
    exit(0);
}

$record = $DB->get_record('local_payments_providers', ['name' => 'fawaterk']);
if (!$record) {
    cli_error('No fawaterk provider row. Run admin/cli/upgrade.php first.');
}

$sandbox = (bool) get_config('paymentprovider_fawaterk', 'sandbox_mode');
$key = trim((string) get_config('paymentprovider_fawaterk', 'vendor_key'));
$base = $sandbox
    ? (get_config('paymentprovider_fawaterk', 'sandbox_url') ?: 'https://staging.fawaterk.com')
    : (get_config('paymentprovider_fawaterk', 'base_url') ?: 'https://app.fawaterk.com');

cli_writeln('Fawaterk configuration');
cli_writeln('  enabled in Moodle : ' . ($record->enabled ? 'yes' : 'NO — enable it under Manage providers'));
cli_writeln('  mode              : ' . ($sandbox ? 'SANDBOX' : 'LIVE'));
cli_writeln('  api base          : ' . $base);
cli_writeln('  vendor key        : ' . ($key === ''
    ? 'NOT SET'
    : substr($key, 0, 6) . str_repeat('*', max(0, strlen($key) - 10)) . substr($key, -4)
      . ' (' . strlen($key) . ' chars)'));
cli_writeln('  webhook to set    : ' . $CFG->wwwroot . '/local/payments/webhook_json.php');
cli_writeln('');

if ($key === '') {
    cli_error('No vendor key configured. Set it under Provider settings → Fawaterk.');
}

if ($options['purge-cache']) {
    \cache::make('local_payments', 'provider_payment_methods')->purge();
    cli_writeln('Cached method list purged.');
    cli_writeln('');
}

$gateway = new \paymentprovider_fawaterk\gateway($record);
$methods = $gateway->get_payment_methods();

if (empty($methods)) {
    cli_writeln('FAILED — no payment methods came back.');
    cli_writeln('');
    cli_writeln('The exact API response is in local_payments_logs. The usual causes:');
    cli_writeln('  1. The key belongs to the other environment. This provider is in '
        . ($sandbox ? 'SANDBOX' : 'LIVE') . ' mode, so it must be the '
        . ($sandbox ? 'staging' : 'live') . ' account\'s API key.');
    cli_writeln('  2. The Fawaterk vendor account is not activated yet.');
    cli_writeln('  3. The key was pasted with stray characters (it is trimmed, but check for gaps).');
    exit(1);
}

cli_writeln('OK — the key works. Methods enabled on this account:');
foreach ($methods as $method) {
    cli_writeln(sprintf('  id=%-3d %-24s %s',
        $method['id'],
        $method['name_en'],
        $method['redirect'] ? '(redirects to a payment page)' : '(returns a reference code)'
    ));
}

cli_writeln('');
$priority = (string) (get_config('paymentprovider_fawaterk', 'method_priority') ?: '2,4,3');
$auto = get_config('paymentprovider_fawaterk', 'auto_select_method');
if ($auto === false || $auto) {
    cli_writeln('Auto-selection is ON, priority: ' . $priority);
    $chosen = 0;
    foreach (array_filter(array_map('intval', explode(',', $priority))) as $id) {
        if (in_array($id, array_column($methods, 'id'), true)) {
            $chosen = $id;
            break;
        }
    }
    if (!$chosen) {
        $chosen = (int) $methods[0]['id'];
        cli_writeln('  (priority list matched nothing enabled — falling back to the first method)');
    }
    foreach ($methods as $method) {
        if ((int) $method['id'] === $chosen) {
            cli_writeln('  → web checkout will charge: ' . $method['name_en'] . ' (id ' . $chosen . ')');
        }
    }
} else {
    cli_writeln('Auto-selection is OFF — the web checkout uses the Fawaterk hosted page.');
}

exit(0);
