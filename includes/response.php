<?php
// ============================================================
//  includes/response.php
//  Standardised JSON response helper
//  Every API endpoint uses these functions
// ============================================================

/**
 * Send a success JSON response and exit.
 *
 * @param mixed  $data    Payload to return (array, object, null)
 * @param int    $code    HTTP status code (default 200)
 * @param string $message Optional human-readable message
 */
function send_success($data = null, int $code = 200, string $message = 'Success'): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send an error JSON response and exit.
 *
 * @param string $message Human-readable error
 * @param int    $code    HTTP status code (default 400)
 * @param mixed  $errors  Detailed field errors (optional)
 */
function send_error(string $message, int $code = 400, $errors = null): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $body = [
        'success' => false,
        'message' => $message,
    ];
    if ($errors !== null) {
        $body['errors'] = $errors;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Allow only specific HTTP methods for an endpoint.
 * Sends 405 Method Not Allowed if the method doesn't match.
 *
 * Usage: allow_methods(['GET', 'POST']);
 *
 * @param string[] $methods
 */
function allow_methods(array $methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        send_error('Method not allowed.', 405);
    }
}

/**
 * Decode and return the raw JSON request body as an array.
 * Sends 400 if body is missing or malformed.
 */
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        send_error('Request body is empty.', 400);
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Invalid JSON: ' . json_last_error_msg(), 400);
    }
    return $data;
}