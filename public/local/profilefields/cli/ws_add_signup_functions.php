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
 *   php public/local/profilefields/cli/ws_add_signup_functions.php --sync
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
    ['token' => '', 'service' => '', 'add' => false, 'addall' => false, 'sync' => false, 'help' => false],
    ['h' => 'help']
);

/** @var string[] The functions the mobile sign-up and profile flows need. */
$wanted = \local_profilefields\ws_registry::all();

// --sync is the same repair the plugin now runs on every upgrade: complete each
// family of functions on whichever services already use part of it, without
// needing to know which service that is. Use it when an upgrade has already been
// run by hand, or to check what the upgrade would do.
if (!empty($options['sync'])) {
    $missing = \local_profilefields\ws_registry::sync(true);
    if (empty($missing)) {
        echo "Every service using these functions already has all of them. Nothing to do.\n";
        exit(0);
    }
    foreach ($missing as $row) {
        echo "  [ADDED]  {$row['function']} -> \"{$row['servicename']}\" (service id {$row['serviceid']})\n";
    }
    echo "\n>>> Done. Now purge caches:\n    php admin/cli/purge_caches.php\n";
    exit(0);
}

if ($options['help'] || ($options['token'] === '' && $options['service'] === '')) {
    echo "Put the sign-up and profile web-service functions on the service a token uses.\n\n";
    echo "  --token=WSTOKEN     the token the app calls with (the service is read from it)\n";
    echo "  --service=NAME|ID   the service, when you know it instead of a token\n";
    echo "  --add               complete the families this service already uses\n";
    echo "  --addall            also add a family the service does not use yet (a decision, not a repair)\n";
    echo "  --sync              repair every service already using these functions (no token needed)\n\n";
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

// 2. What is already there - family by family, because that is how they are used.
// A service that carries no member of a family is not "broken": the sign-up family
// belongs on a pre-login registration service, the profile family on the service
// the app calls with a real user's token, and a site is perfectly entitled to keep
// them apart. Reporting the absent family as a failure is how somebody ends up
// putting update_profile on a shared registration token.
$existing = $DB->get_records_menu('external_services_functions',
    ['externalserviceid' => $service->id], '', 'id, functionname');
$existing = array_flip($existing);

$labels = [
    'signup' => 'Sign-up functions (pre-login: describe the form, submit it, policies, resend)',
    'profile' => 'Profile functions (signed-in: read the profile, edit form, save, completion)',
];

$missing = [];   // Families this service uses, minus the members it has not got.
$unused = [];    // Families this service does not use at all.

foreach (\local_profilefields\ws_registry::families() as $familyname => $family) {
    echo "\n---- {$labels[$familyname]} ----\n";

    $inuse = array_filter($family, fn($name) => isset($existing[$name]));
    if (empty($inuse)) {
        echo "  ....    Not used by this service - none of these are on it, so none are missing.\n";
        echo "  ....    " . implode(', ', $family) . "\n";
        $unused[$familyname] = $family;
        continue;
    }

    foreach ($family as $name) {
        if (!$DB->record_exists('external_functions', ['name' => $name])) {
            echo $bad . "{$name}: NOT INSTALLED. Run admin/cli/upgrade.php first "
                . "(the plugin version must be bumped for new functions to register).\n";
            continue;
        }
        if (isset($existing[$name])) {
            echo $ok . "{$name}: already on the service\n";
        } else {
            echo $bad . "{$name}: missing from a family this service DOES use - "
                . "this is what causes accessexception\n";
            $missing[] = $name;
        }
    }
}

// 3. Fix, if asked to.
if (empty($missing)) {
    echo "\nNothing to add: every family this service uses is complete.\n";
    if (!empty($unused)) {
        echo "The families listed as unused are a deliberate choice, not a fault. Add one only if\n";
        echo "this service is genuinely meant to serve it - via the admin UI, or --addall.\n";
    }
    exit(0);
}

if (!$options['add']) {
    echo "\n    Re-run with --add to complete the families this service already uses.\n";
    echo "    (Same thing by hand: Site administration > Server > Web services >\n";
    echo "     External services > \"{$service->name}\" > Functions > Add functions.)\n";
    exit(0);
}

foreach ($missing as $name) {
    $webservice->add_external_function_to_service($name, $service->id);
    echo $ok . "Added {$name}\n";
}

// --addall is the deliberate "this service should serve that family too" case. It
// is separate from --add on purpose: widening what a token can reach is a decision,
// not a repair.
if (!empty($options['addall'])) {
    foreach ($unused as $family) {
        foreach ($family as $name) {
            if (!$DB->record_exists('external_functions', ['name' => $name])
                    || $DB->record_exists('external_services_functions',
                        ['externalserviceid' => $service->id, 'functionname' => $name])) {
                continue;
            }
            $webservice->add_external_function_to_service($name, $service->id);
            echo $ok . "Added {$name} (previously unused family)\n";
        }
    }
}

echo "\n>>> Done. Purge caches, then retry the REST call:\n";
echo "    php admin/cli/purge_caches.php\n";
