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
    ['help' => false, 'purge-cache' => false, 'logs' => false, 'webhooks' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Check the Fawaterk credentials and list the account's payment methods.

Options:
  --purge-cache   Drop the cached method list and access tokens before checking.
  --logs[=N]      Print the last N API log entries (default 15) and exit. This is
                  the full response body from every failed call — the fastest way
                  to see what Fawaterk actually objected to.
  --webhooks[=N]  Print the last N received webhooks (default 10) and exit,
                  including whether the signature verified.
  -h, --help      Print this help.
");
    exit(0);
}

$providerid = $DB->get_field('local_payments_providers', 'id', ['name' => 'fawaterk']);

// Reading the logs needs no credentials, so handle it before any config checks.
if ($options['logs'] !== false) {
    $limit = is_numeric($options['logs']) ? max(1, (int) $options['logs']) : 15;
    $rows = $DB->get_records('local_payments_logs', $providerid ? ['provider_id' => $providerid] : null,
        'id DESC', '*', 0, $limit);

    if (empty($rows)) {
        cli_writeln('No log entries.');
        exit(0);
    }
    foreach (array_reverse($rows) as $row) {
        cli_writeln(str_repeat('-', 78));
        cli_writeln(sprintf('%s  [%s]  %s', userdate($row->timecreated), strtoupper($row->level), $row->message));
        if (!empty($row->context) && $row->context !== '[]') {
            cli_writeln('  ' . $row->context);
        }
    }
    exit(0);
}

if ($options['webhooks'] !== false) {
    $limit = is_numeric($options['webhooks']) ? max(1, (int) $options['webhooks']) : 10;
    $rows = $DB->get_records('local_payments_webhooks', $providerid ? ['provider_id' => $providerid] : null,
        'id DESC', '*', 0, $limit);

    if (empty($rows)) {
        cli_writeln('No webhooks received yet. If payments are completing at Fawaterk but');
        cli_writeln('nobody is being enrolled, this is the reason — check the webhook URL.');
        exit(0);
    }
    foreach (array_reverse($rows) as $row) {
        cli_writeln(str_repeat('-', 78));
        cli_writeln(sprintf('%s  event=%s  status=%s  signature=%s',
            userdate($row->timecreated), $row->event_type ?: '?', $row->status,
            $row->signature_valid ? 'VALID' : 'INVALID — check the HASH API key'));
        cli_writeln('  ' . \core_text::substr((string) $row->payload, 0, 600));
    }
    exit(0);
}

$record = $DB->get_record('local_payments_providers', ['name' => 'fawaterk']);
if (!$record) {
    cli_error('No fawaterk provider row. Run admin/cli/upgrade.php first.');
}

/**
 * Show a secret without printing it: enough to tell two keys apart, not enough
 * to use. A diagnostic that leaks credentials into shell history is worse than
 * no diagnostic.
 */
function fawaterk_mask(string $value): string {
    if ($value === '') {
        return 'NOT SET';
    }
    if (strlen($value) <= 12) {
        return str_repeat('*', strlen($value)) . ' (' . strlen($value) . ' chars)';
    }
    return substr($value, 0, 6) . str_repeat('*', 6) . substr($value, -4)
        . ' (' . strlen($value) . ' chars)';
}

$sandbox = (bool) get_config('paymentprovider_fawaterk', 'sandbox_mode');
$authmode = get_config('paymentprovider_fawaterk', 'auth_mode') === 'apikey' ? 'apikey' : 'oauth';
$key = trim((string) get_config('paymentprovider_fawaterk', 'vendor_key'));
$clientid = trim((string) get_config('paymentprovider_fawaterk', 'client_id'));
$clientsecret = trim((string) get_config('paymentprovider_fawaterk', 'client_secret'));
$base = $sandbox
    ? (get_config('paymentprovider_fawaterk', 'sandbox_url') ?: 'https://staging.fawaterk.com')
    : (get_config('paymentprovider_fawaterk', 'base_url') ?: 'https://app.fawaterk.com');
$tokenurl = trim((string) get_config('paymentprovider_fawaterk', 'token_url')) ?: ($base . '/oauth/token');

cli_writeln('Fawaterk configuration');
cli_writeln('  enabled in Moodle : ' . ($record->enabled ? 'yes' : 'NO — enable it under Manage providers'));
cli_writeln('  mode              : ' . ($sandbox ? 'SANDBOX' : 'LIVE'));
cli_writeln('  api base          : ' . $base);
cli_writeln('  api + auth        : ' . ($authmode === 'oauth'
    ? 'v3 with OAuth 2.0 client credentials' : 'v2 with the HASH API key'));
if ($authmode === 'oauth') {
    cli_writeln('  token url         : ' . $tokenurl);
    cli_writeln('  client id         : ' . ($clientid === '' ? 'NOT SET' : $clientid));
    cli_writeln('  client secret     : ' . fawaterk_mask($clientsecret));
}
cli_writeln('  HASH API key      : ' . fawaterk_mask($key) . ($key === ''
    ? ' — webhooks cannot be verified without this' : ''));
cli_writeln('  webhook to set    : ' . $CFG->wwwroot . '/local/payments/webhook_json.php');
cli_writeln('');

// The live dashboard is app.fawaterk.com. Credentials copied from there while
// sandbox mode is on are the single most common reason for "Invalid Token".
if ($sandbox) {
    cli_writeln('NOTE: sandbox mode is ON, so every credential above must come from the');
    cli_writeln('      STAGING account. Credentials copied from app.fawaterk.com are live');
    cli_writeln('      credentials and will be rejected here.');
    cli_writeln('');
}

if ($authmode === 'oauth' && ($clientid === '' || $clientsecret === '')) {
    cli_error('OAuth is selected but the client id/secret are not set. '
        . 'Get them from the Fawaterk dashboard → Integrations.');
}
if ($authmode === 'apikey' && $key === '') {
    cli_error('The static key method is selected but no HASH API key is set.');
}
if ($key === '') {
    cli_writeln('WARNING: no HASH API key. API calls may work, but webhooks will fail');
    cli_writeln('         signature checks and no payment will ever be completed.');
    cli_writeln('');
}

if ($options['purge-cache']) {
    \cache::make('local_payments', 'provider_payment_methods')->purge();
    \cache::make('local_payments', 'provider_oauth_tokens')->purge();
    cli_writeln('Cached method list and access tokens purged.');
    cli_writeln('');
}

$priority = (string) (get_config('paymentprovider_fawaterk', 'method_priority') ?: '2,4,3');

$gateway = new \paymentprovider_fawaterk\gateway($record);
$methods = $gateway->get_payment_methods();

if (empty($methods)) {
    // Not necessarily fatal: getPaymentmethods reports what is configured for
    // the hosted iframe, and accounts have been seen returning [] while
    // invoiceInitPay still charges card fine. So report it, explain the
    // fallback, and let the caller judge.
    cli_writeln('getPaymentmethods returned no methods.');
    cli_writeln('');
    cli_writeln('That is not automatically a failure — this endpoint lists what is set up for');
    cli_writeln('the hosted iframe, and an account can return an empty list while still');
    cli_writeln('accepting a direct charge. Checkout will use the first id in the priority');
    cli_writeln('list (' . $priority . ') instead of the hosted page.');
    cli_writeln('');
    cli_writeln('If payments also fail, check local_payments_logs for the API response. Causes:');
    cli_writeln('  1. The credentials belong to the other environment. This provider is in '
        . ($sandbox ? 'SANDBOX' : 'LIVE') . ' mode,');
    cli_writeln('     so they must come from the ' . ($sandbox ? 'staging' : 'live') . ' account.');
    if ($authmode === 'oauth') {
        cli_writeln('  2. The OAuth client was revoked or its secret is wrong. Check it still');
        cli_writeln('     shows Active on the Integrations page.');
        cli_writeln('  3. The token URL is wrong for this account: ' . $tokenurl);
    } else {
        cli_writeln('  2. The Fawaterk vendor account is not activated for payments yet.');
        cli_writeln('  3. No payment methods are enabled on the account — ask Fawaterk to enable them.');
    }
    exit(1);
}

cli_writeln('OK — the credentials work. Methods enabled on this account:');
foreach ($methods as $method) {
    cli_writeln(sprintf('  id=%-3d %-24s %s',
        $method['id'],
        $method['name_en'],
        $method['redirect'] ? '(redirects to a payment page)' : '(returns a reference code)'
    ));
}

cli_writeln('');
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
