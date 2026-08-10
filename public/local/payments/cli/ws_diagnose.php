<?php
/**
 * Diagnose (and optionally fix) why REST web-service calls return
 * "accessexception" for a given token.
 *
 * Usage (from the Moodle code root, inside the container):
 *   php public/local/payments/cli/ws_diagnose.php --token=THE_WSTOKEN
 *   php public/local/payments/cli/ws_diagnose.php --token=THE_WSTOKEN --fix
 *
 * --fix will grant webservice/rest:use to the token user's role(s) at system
 * context when that is the missing piece. It changes nothing else.
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');

list($options, $unrecognized) = cli_get_params(
    ['token' => '', 'fix' => false, 'help' => false],
    ['h' => 'help']
);

if ($options['help'] || $options['token'] === '') {
    echo "Diagnose REST accessexception for a web-service token.\n";
    echo "  --token=WSTOKEN   the token string (required)\n";
    echo "  --fix             grant webservice/rest:use to the user's role(s)\n";
    exit(0);
}

$token = trim($options['token']);
$ok = "  [ OK ]  ";
$bad = "  [FAIL]  ";

echo "==== Web-service REST diagnosis ====\n\n";

// 1. Global switches.
$enablews = (int) get_config('core', 'enablewebservices');
echo ($enablews ? $ok : $bad) . "enablewebservices = {$enablews}\n";

$protocols = (string) get_config('core', 'webserviceprotocols');
$hasrest = in_array('rest', array_map('trim', explode(',', $protocols)), true);
echo ($hasrest ? $ok : $bad) . "webserviceprotocols = '{$protocols}' (rest enabled: " . ($hasrest ? 'yes' : 'no') . ")\n";

// 2. Token row.
$tokenrec = $DB->get_record('external_tokens', ['token' => $token]);
if (!$tokenrec) {
    echo $bad . "No external_tokens row for that token. Wrong/expired token.\n";
    exit(1);
}
echo $ok . "Token found: id={$tokenrec->id}, userid={$tokenrec->userid}, externalserviceid={$tokenrec->externalserviceid}\n";
if (!empty($tokenrec->validuntil) && $tokenrec->validuntil < time()) {
    echo $bad . "Token EXPIRED (validuntil=" . userdate($tokenrec->validuntil) . ")\n";
}

// 3. The user.
$user = $DB->get_record('user', ['id' => $tokenrec->userid]);
if (!$user) {
    echo $bad . "Token user does not exist.\n";
    exit(1);
}
echo (($user->deleted) ? $bad : $ok) . "user: {$user->username} (id={$user->id}), deleted={$user->deleted}, "
    . "suspended={$user->suspended}, auth={$user->auth}, confirmed={$user->confirmed}\n";

// 4. Service.
$service = $DB->get_record('external_services', ['id' => $tokenrec->externalserviceid]);
if (!$service) {
    echo $bad . "external_services row {$tokenrec->externalserviceid} missing.\n";
    exit(1);
}
echo (($service->enabled) ? $ok : $bad) . "service: {$service->name} (shortname={$service->shortname}), "
    . "enabled={$service->enabled}, restrictedusers={$service->restrictedusers}\n";

// 5. If restricted, is the user authorised?
if ((int) $service->restrictedusers === 1) {
    $auth = $DB->record_exists('external_services_users', [
        'externalserviceid' => $service->id,
        'userid' => $user->id,
    ]);
    echo ($auth ? $ok : $bad) . "restrictedusers=1 and user " . ($auth ? "IS" : "is NOT") . " in the authorised list\n";
    if (!$auth) {
        echo "         -> Either set the service to 'Authorised users only = No', or add this user.\n";
    }
}

// 6. The decisive check: webservice/rest:use at system context for this user.
$systemctx = context_system::instance();
$canrest = has_capability('webservice/rest:use', $systemctx, $user->id);
echo ($canrest ? $ok : $bad) . "has_capability('webservice/rest:use') for this user = " . ($canrest ? 'yes' : 'NO') . "\n";

if (!$canrest) {
    echo "\n>>> ROOT CAUSE: the token user cannot use the REST protocol.\n";
    echo "    This is what produces 'accessexception' on every REST call.\n";

    // Which roles does the user hold at system level?
    $roles = get_user_roles($systemctx, $user->id, true);
    $rolenames = [];
    foreach ($roles as $r) {
        $rolenames[$r->roleid] = $r->shortname;
    }
    // Also consider the default authenticated-user role.
    if (!empty($CFG->defaultuserroleid)) {
        $rolenames[$CFG->defaultuserroleid] = ($rolenames[$CFG->defaultuserroleid] ?? 'authenticated user (default)');
    }
    echo "    User's applicable roles (system): " . (empty($rolenames) ? '(none)' : implode(', ', $rolenames)) . "\n";

    if ($options['fix']) {
        if (empty($rolenames)) {
            echo $bad . "No role to grant on. Assign the user a role first.\n";
            exit(1);
        }
        foreach (array_keys($rolenames) as $roleid) {
            assign_capability('webservice/rest:use', CAP_ALLOW, $roleid, $systemctx->id, true);
            echo $ok . "Granted webservice/rest:use to roleid={$roleid} at system context.\n";
        }
        $systemctx->mark_dirty();
        purge_all_caches();
        echo "\n>>> FIXED. Re-run without --fix to confirm, then retry the REST call.\n";
    } else {
        echo "\n    Re-run with --fix to grant webservice/rest:use to the above role(s).\n";
    }
} else {
    echo "\n>>> REST access is OK for this user. If a SPECIFIC function still fails,\n";
    echo "    that function requires its own capability the user lacks, or it is not\n";
    echo "    attached to this service.\n";
}

echo "\n==== done ====\n";
