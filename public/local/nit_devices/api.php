<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Mobile JSON API for the device limit. Same envelope as /local/academy/api.php.
 *
 *   GET /local/nit_devices/api.php?function=get_my_devices&token=<wstoken>
 *   → {"status":"success","data":{"enabled","limited","max","policy","devices":[...]}}
 *   → {"status":"fail","error":"<translated>","errorcode":"invalidtoken"}   HTTP 401
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_nit_devices\manager;

header('Content-Type: application/json; charset=utf-8');

/**
 * Emit a JSON envelope and stop.
 *
 * @param array $payload
 * @param int $http HTTP status; 401 only when the token is dead (the app then signs out)
 */
function local_nit_devices_respond(array $payload, int $http = 200): void {
    http_response_code($http);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token = optional_param('token', '', PARAM_ALPHANUM);

$user = $token === '' ? null : \local_academy\token_auth::validate($token);
if (!$user) {
    local_nit_devices_respond(['status' => 'fail', 'error' => get_string('err_invalidtoken', 'local_academy'),
        'errorcode' => 'invalidtoken'], 401);
}
\core\session\manager::set_user($user);

switch ($function) {
    // The signed-in user's devices; `current` marks the one making this call.
    case 'get_my_devices':
        $tokenid = (int) $DB->get_field('external_tokens', 'id', ['token' => $token]);
        $devices = [];
        foreach (manager::devices((int) $user->id) as $device) {
            $devices[] = [
                'id' => (int) $device->id,
                'kind' => $device->kind,
                'name' => $device->name,
                'platform' => $device->platform,
                'current' => $device->kind === manager::MOBILE && (int) $device->tokenid === $tokenid,
                'firstseen' => (int) $device->timecreated,
                'lastseen' => (int) $device->lastseen,
            ];
        }
        local_nit_devices_respond(['status' => 'success', 'data' => [
            'enabled' => manager::enabled(),
            'limited' => manager::applies_to((int) $user->id),
            'max' => manager::max_devices(),
            'policy' => manager::policy(),
            'devices' => $devices,
        ]]);
}
local_nit_devices_respond(['status' => 'fail', 'error' => get_string('err_unknownfunction', 'local_academy'),
    'errorcode' => 'unknownfunction']);
