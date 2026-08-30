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
 * List (and optionally add) the sign-up and profile web-service functions on a service.
 *
 * A REST call fails with `accessexception` when the function is not listed in the
 * service the token belongs to - the token itself and the function can both be
 * perfectly fine. That is what this script reports on, and fixes.
 *
 * Usage (inside the container, from the Moodle code root):
 *   php public/local/profilefields/cli/ws_add_signup_functions.php --token=THE_WSTOKEN
 *   php public/local/profilefields/cli/ws_add_signup_functions.php --token=THE_WSTOKEN --add
 *   php public/local/profilefields/cli/ws_add_signup_functions.php --service=moodle_mobile_app --add
 *
 * Without --add nothing is written; it only reports. Adding is idempotent.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

[$options, $unrecognized] = cli_get_params(
    ['token' => '', 'service' => '', 'add' => false, 'help' => false],
    ['h' => 'help']
);

/** @var string[] The functions the mobile sign-up and profile flows need. */
$wanted = [
    'local_profilefields_get_signup_form',
    'local_profilefields_signup_user',
    'local_profilefields_resend_confirmation',
    'local_profilefields_get_policy_documents',
    'local_profilefields_get_profile',
    'local_profilefields_get_profile_form',
    'local_profilefields_update_profile',
];

if ($options['help'] || ($options['token'] === '' && $options['service'] === '')) {
    echo "Put the sign-up and profile web-service functions on the service a token uses.\n\n";
    echo "  --token=WSTOKEN     the token the app calls with (the service is read from it)\n";
    echo "  --service=NAME|ID   the service, when you know it instead of a token\n";
    echo "  --add               actually add the missing functions (otherwise report only)\n\n";
    echo "Functions handled: " . implode(', ', $wanted) . "\n";
    exit(0);
}

$ok = "  [ OK ]  ";
$bad = "  [FAIL]  ";
$webservice = new webservice();

// 1. Which service are we talking about?
if ($options['token'] !== '') {
    $tokenrec = $DB->get_record('external_tokens', ['token' => trim($options['token'])]);
    if (!$tokenrec) {
        cli_error('No external_tokens row for that token - wrong or deleted token.');
    }
    $service = $webservice->get_external_service_by_id($tokenrec->externalserviceid);
    if (!$service) {
        cli_error('The token points at service id ' . $tokenrec->externalserviceid . ', which no longer exists.');
    }
    echo $ok . "Token belongs to user id {$tokenrec->userid}\n";
} else {
    $wantedservice = trim($options['service']);
    $service = is_numeric($wantedservice)
        ? $webservice->get_external_service_by_id((int) $wantedservice)
        : $webservice->get_external_service_by_shortname($wantedservice);
    if (!$service) {
        cli_error('No external service called "' . $wantedservice . '".');
    }
}

echo $ok . "Service: \"{$service->name}\" (id={$service->id}, shortname="
    . ($service->shortname ?? '-') . ", enabled=" . (int) $service->enabled . ")\n";
if (empty($service->enabled)) {
    echo $bad . "The service is DISABLED - every call to it fails, whatever functions it lists.\n";
}
if (!empty($service->restrictedusers)) {
    echo "  ....    The service is user-restricted: the token's user must also be on its "
        . "authorised-users list.\n";
}
if (!empty($service->requiredcapability)) {
    echo "  ....    The service requires capability '{$service->requiredcapability}'.\n";
}

// 2. What is already there?
$existing = $DB->get_records_menu('external_services_functions',
    ['externalserviceid' => $service->id], '', 'id, functionname');
$existing = array_flip($existing);

echo "\n---- Sign-up and profile functions on this service ----\n";
$missing = [];
foreach ($wanted as $name) {
    $installed = $DB->record_exists('external_functions', ['name' => $name]);
    if (!$installed) {
        echo $bad . "{$name}: NOT INSTALLED. Run admin/cli/upgrade.php first "
            . "(the plugin version must be bumped for new functions to register).\n";
        continue;
    }
    if (isset($existing[$name])) {
        echo $ok . "{$name}: already on the service\n";
    } else {
        echo $bad . "{$name}: missing from the service - this is what causes accessexception\n";
        $missing[] = $name;
    }
}

// 3. Fix, if asked to.
if (empty($missing)) {
    echo "\nNothing to add.\n";
    exit(0);
}

if (!$options['add']) {
    echo "\n    Re-run with --add to put the missing function(s) on this service.\n";
    echo "    (Same thing by hand: Site administration > Server > Web services >\n";
    echo "     External services > \"{$service->name}\" > Functions > Add functions.)\n";
    exit(0);
}

foreach ($missing as $name) {
    $webservice->add_external_function_to_service($name, $service->id);
    echo $ok . "Added {$name}\n";
}

echo "\n>>> Done. Purge caches, then retry the REST call:\n";
echo "    php admin/cli/purge_caches.php\n";
