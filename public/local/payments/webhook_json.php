<?php
/**
 * Fawaterk webhook endpoint.
 *
 * Fawaterk only POSTs a JSON body when the configured webhook URL ends in
 * "_json" — a query string on webhook.php would not satisfy that, so Fawaterk
 * gets its own filename. Everything else is handled by the shared manager.
 */
define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

// Defaults to fawaterk, but stays overridable in case another provider ever
// needs the same "_json" URL shape.
$provider_name = optional_param('provider', 'fawaterk', PARAM_ALPHANUMEXT);

$payload = file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $header_name = str_replace('_', '-', substr($key, 5));
        $headers[$header_name] = $value;
    }
}

try {
    $success = \local_payments\manager::process_webhook($provider_name, $payload, $headers);

    if ($success) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'failed']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
