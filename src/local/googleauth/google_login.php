<?php
/**
 * Google Sign-In → Moodle token exchange.
 *
 * POST /local/googleauth/google_login.php
 * Body (application/x-www-form-urlencoded or JSON):
 *   idtoken  — the id_token string from Google Sign-In SDK
 *
 * Success 200:
 *   { token, userid, username, firstname, lastname, email }
 *
 * Errors:
 *   { error: <code>, message: <string> }
 */

define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/externallib.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed', 'message' => 'Use POST']);
    exit;
}

// Accept both form-encoded and JSON body.
$idtoken = '';
$contenttype = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contenttype, 'application/json')) {
    $body    = json_decode(file_get_contents('php://input'), true);
    $idtoken = $body['idtoken'] ?? '';
} else {
    $idtoken = $_POST['idtoken'] ?? '';
}

$idtoken = clean_param(trim($idtoken), PARAM_RAW);
if (empty($idtoken)) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_idtoken', 'message' => 'idtoken is required']);
    exit;
}

// --- Verify id_token with Google tokeninfo ---
$curl = new curl(['ignoresecurity' => true]);
$response = $curl->get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idtoken));
$payload  = json_decode($response, true);

if (empty($payload) || !empty($payload['error'])) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_token', 'message' => 'Google rejected the id_token']);
    exit;
}

// Verify token has not expired.
if (!empty($payload['exp']) && (int)$payload['exp'] < time()) {
    http_response_code(401);
    echo json_encode(['error' => 'token_expired', 'message' => 'Google id_token has expired']);
    exit;
}

// Verify audience matches our configured client ID (if set).
$clientid = get_config('local_googleauth', 'clientid');
if (!empty($clientid) && ($payload['aud'] ?? '') !== $clientid) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_audience', 'message' => 'id_token audience does not match configured client ID']);
    exit;
}

$email         = $payload['email'] ?? '';
$emailverified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (empty($email) || !$emailverified) {
    http_response_code(400);
    echo json_encode(['error' => 'unverified_email', 'message' => 'Google email is not verified']);
    exit;
}

// --- Find or create Moodle user ---
$user = $DB->get_record('user', [
    'email'      => $email,
    'deleted'    => 0,
    'mnethostid' => $CFG->mnet_localhost_id,
]);

if (!$user) {
    $firstname = clean_param($payload['given_name']  ?? 'Google', PARAM_NOTAGS);
    $lastname  = clean_param($payload['family_name'] ?? 'User',   PARAM_NOTAGS);

    // Build a unique username from the email local-part.
    $base     = strtolower(preg_replace('/[^a-z0-9._-]/i', '', explode('@', $email)[0]));
    $username = $base;
    $suffix   = 2;
    while ($DB->record_exists('user', ['username' => $username])) {
        $username = $base . $suffix++;
    }

    $newuser              = new stdClass();
    $newuser->auth        = 'oauth2';
    $newuser->confirmed   = 1;
    $newuser->mnethostid  = $CFG->mnet_localhost_id;
    $newuser->username    = $username;
    $newuser->email       = $email;
    $newuser->firstname   = $firstname;
    $newuser->lastname    = $lastname;
    $newuser->lang        = current_language();
    $newuser->timecreated = time();

    $userid = user_create_user($newuser, false, false);
    $user   = $DB->get_record('user', ['id' => $userid]);
}

if ($user->suspended) {
    http_response_code(403);
    echo json_encode(['error' => 'user_suspended', 'message' => 'This account has been suspended']);
    exit;
}

// --- Issue Moodle token for the mobile service ---
$service = $DB->get_record('external_services', [
    'shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE,
    'enabled'   => 1,
], '*', MUST_EXIST);

// Reuse an existing permanent token so repeated Google logins don't create orphans.
$existing = $DB->get_record('external_tokens', [
    'userid'            => $user->id,
    'externalserviceid' => $service->id,
    'tokentype'         => EXTERNAL_TOKEN_PERMANENT,
]);

$token = $existing
    ? $existing->token
    : external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $user->id, context_system::instance(), 0, '');

echo json_encode([
    'token'     => $token,
    'userid'    => (int) $user->id,
    'username'  => $user->username,
    'firstname' => $user->firstname,
    'lastname'  => $user->lastname,
    'email'     => $user->email,
]);
