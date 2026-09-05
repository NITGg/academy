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
 * Explain why the "complete your registration" page asks what it asks.
 *
 * `local/profilefields/complete.php` draws exactly the boxes
 * `completion::missing()` reports, and that function drops a field silently for
 * any of half a dozen reasons - a flag on the field record, a mode on the
 * register tab, a value the account was born holding. When the page comes up
 * short there is nothing on screen to say which of those it was.
 *
 * This script walks the same decision for one account and prints the verdict for
 * every candidate field, with the reason attached. Read-only: it writes nothing.
 *
 * Usage (inside the container, from the Moodle code root):
 *   php public/local/profilefields/cli/completion_diagnose.php --user=someone@example.com
 *   php public/local/profilefields/cli/completion_diagnose.php --user=42
 *   php public/local/profilefields/cli/completion_diagnose.php --latest-oauth2
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_profilefields\completion;
use local_profilefields\manager;
use local_profilefields\policies;

[$options, $unrecognized] = cli_get_params(
    ['user' => '', 'latest-oauth2' => false, 'help' => false],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help'] || ($options['user'] === '' && !$options['latest-oauth2'])) {
    echo "Explain what the completion page asks a given account, and why.\n\n";
    echo "  --user=<id|username|email>   the account to inspect\n";
    echo "  --latest-oauth2              inspect the newest oauth2 account instead\n";
    echo "  -h, --help                   this text\n";
    exit(0);
}

// ---------------------------------------------------------------- The account.
if ($options['latest-oauth2']) {
    $found = $DB->get_records_select('user', "auth = 'oauth2' AND deleted = 0", [], 'id DESC', '*', 0, 1);
    $user = reset($found);
    if (!$user) {
        cli_error('No oauth2 account on this site.');
    }
} else {
    $needle = core_text::strtolower((string) $options['user']);
    $user = ctype_digit($needle)
        ? $DB->get_record('user', ['id' => (int) $needle, 'deleted' => 0])
        : ($DB->get_record('user', ['username' => $needle, 'deleted' => 0])
            ?: $DB->get_record('user', ['email' => $needle, 'deleted' => 0]));
    if (!$user) {
        cli_error("No such user: {$options['user']}");
    }
}
profile_load_custom_fields($user);

$askall = completion::skipped_signup($user);
$pref = get_user_preferences(completion::PREF_DONE, 0, $user->id);

echo str_repeat('=', 78) . "\n";
echo "User      : {$user->id}  {$user->username}  <{$user->email}>\n";
echo "Auth      : {$user->auth}" . (in_array($user->auth, completion::SKIPS_SIGNUP, true)
    ? '  (skips the sign-up form)' : '  (answered the sign-up form)') . "\n";
echo 'Done pref : ' . ($pref ? '1  -> already been through this page' : '0') . "\n";
echo 'Ask-all   : ' . ($askall ? 'YES - confirm every sign-up box' : 'no  - only ask what is empty') . "\n";
echo 'Gate      : ' . (completion::enabled() ? 'on' : 'OFF - the gate never fires') . "\n";
echo str_repeat('=', 78) . "\n\n";

// ------------------------------------------------------------------ Core fields.
echo "CORE FIELDS\n";
printf("  %-12s %-9s %-8s %-22s %s\n", 'field', 'mode', 'renders', 'value', 'verdict');
echo '  ' . str_repeat('-', 90) . "\n";

foreach (manager::get_config() as $name => $settings) {
    $renderable = in_array($name, completion::RENDERABLE_CORE, true);
    $signuponly = in_array($name, completion::SIGNUP_ONLY, true);
    $current = trim((string) ($user->$name ?? ''));
    $mode = manager::mode($name);

    if ($signuponly) {
        $verdict = 'skipped: only exists while creating an account';
    } else if (!$renderable) {
        $verdict = 'skipped: not in RENDERABLE_CORE';
    } else if (!manager::on_signup($name)) {
        $verdict = "skipped: off the sign-up form (mode={$mode})";
    } else {
        $sweep = $askall && !in_array($name, completion::PROVIDER_SUPPLIED, true);
        if (!$sweep && $current !== '') {
            $verdict = 'skipped: already answered'
                . (in_array($name, completion::PROVIDER_SUPPLIED, true) ? ' (provider supplied)' : '');
        } else {
            $meta = manager::core_fields()[$name] ?? [];
            $blocking = empty($meta['canrequire']) || !empty($settings['required']);
            $verdict = 'ASKED' . ($blocking ? ' (blocking)' : ' (optional)');
        }
    }

    printf("  %-12s %-9s %-8s %-22s %s\n", $name, $mode, $renderable ? 'yes' : 'no',
        $current === '' ? '-' : core_text::substr($current, 0, 20), $verdict);
}

// ---------------------------------------------------------------- Custom fields.
echo "\nCUSTOM FIELDS\n";
printf("  %-18s %-5s %-4s %-5s %-4s %-6s %-15s %s\n",
    'shortname', 'sign', 'req', 'lock', 'vis', 'empty', 'value', 'verdict');
echo '  ' . str_repeat('-', 110) . "\n";

foreach (profile_get_user_fields_with_data($user->id) as $field) {
    $f = $field->field;
    $visible = $field->get_field_config_for_external()['visible'];

    if (!$visible) {
        $verdict = '*** SKIPPED: visibility is "Not visible", so it is not a sign-up box either ***';
    } else if ($field->is_locked() && !$field->is_signup_field()) {
        $verdict = 'skipped: locked ("user can edit" off) and not a sign-up box';
    } else {
        $unanswered = $field->is_empty() || ($askall && $field->is_signup_field());
        if (!$unanswered) {
            $verdict = 'skipped: already has a value';
        } else if (!$field->is_required() && !$field->is_signup_field()) {
            $verdict = 'skipped: neither required nor on sign-up';
        } else {
            $verdict = 'ASKED' . ($field->is_required() ? ' (blocking)' : ' (optional)')
                . ($field->is_locked() ? ' - locked, but sign-up asks it so this page does too' : '');
        }
    }

    printf("  %-18s %-5s %-4s %-5s %-4s %-6s %-15s %s\n",
        $f->shortname, $f->signup, $f->required, $f->locked, $f->visible,
        $field->is_empty() ? 'yes' : 'no',
        (string) $field->data === '' ? '-' : core_text::substr((string) $field->data, 0, 13),
        $verdict);
}

// --------------------------------------------------------------------- Consent.
echo "\nCONSENT\n";
echo '  consentenabled : ' . (manager::consent_enabled() ? 'on' : 'off') . "\n";
echo '  has agreed     : ' . (policies::has_agreed($user) ? 'yes' : 'no') . "\n";

// --------------------------------------------------------------------- Summary.
$missing = completion::missing($user);
echo "\nWHAT THE PAGE WILL DRAW\n";
if (empty($missing['fields']) && empty($missing['consent'])) {
    echo "  (nothing - the page redirects away)\n";
}
foreach ($missing['fields'] as $entry) {
    echo "  - {$entry['token']}" . ($entry['blocking'] ? '  [required]' : '  [optional]') . "\n";
}
if (!empty($missing['consent'])) {
    echo "  - the terms checkbox\n";
}
echo "\n  is_complete() = " . (completion::is_complete($user) ? 'true' : 'false') . "\n";
