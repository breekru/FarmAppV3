<?php
// includes/api_auth.php — API authentication middleware.
// Include this in every api/*.php file.
// Call api_require_auth() to validate the Bearer token and get the user payload.

require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/secrets.php';

/**
 * Send a JSON error response and halt execution.
 */
function api_error(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Send a JSON success response and halt execution.
 */
function api_success(mixed $data = null): void {
    $body = ['success' => true];
    if ($data !== null) {
        $body['data'] = $data;
    }
    echo json_encode($body);
    exit;
}

/**
 * Read and validate the Authorization: Bearer <token> header.
 * Returns the decoded JWT payload (array with 'sub', 'username', etc.)
 * Exits with HTTP 401 JSON if the token is missing, invalid, or expired.
 */
function api_require_auth(): array {
    // getallheaders() is not available on all SAPI environments — fall back to $_SERVER
    $auth = '';
    if (function_exists('getallheaders')) {
        $hdrs = getallheaders();
        // Header names are case-insensitive
        foreach ($hdrs as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $auth = $value;
                break;
            }
        }
    }
    // Apache mod_rewrite sometimes exposes it here
    if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // Some CGI setups use REDIRECT_HTTP_AUTHORIZATION
    if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
        api_error(401, 'Authorization required. Provide: Authorization: Bearer <token>');
    }

    $payload = jwt_decode($m[1], JWT_SECRET);
    if ($payload === null) {
        api_error(401, 'Invalid or expired token. Please log in again.');
    }

    if (empty($payload['sub']) || !is_int($payload['sub'])) {
        api_error(401, 'Malformed token payload.');
    }

    return $payload;
}

/**
 * Decode and validate the JSON request body.
 * Exits with HTTP 400 if the body is not valid JSON.
 */
function api_json_body(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error(400, 'Request body must be valid JSON.');
    }
    return $data;
}

/**
 * Global JSON error handler — replaces PHP HTML error pages with JSON
 * (register this at the top of each API file).
 */
function api_register_error_handler(): void {
    set_exception_handler(function (Throwable $e) {
        error_log('API exception: ' . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'error' => 'Internal server error.']);
        exit;
    });

    set_error_handler(function (int $errno, string $errstr) {
        error_log("API PHP error [$errno]: $errstr");
        return true; // suppress default handler
    });
}
