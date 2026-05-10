<?php
// api/session_token.php — Exchange a live PHP session for a JWT.
//
// GET /api/session_token.php
//   Cookie: FARMSESSID=<valid-session>   (sent automatically by the browser)
//   Returns: { "success": true, "data": { "token": "<jwt>", "user": { ... } } }
//
// Called by js/sync.js immediately after PHP login so the PWA has a JWT
// for offline API access — credentials never have to appear in JavaScript.

header('Content-Type: application/json; charset=UTF-8');

// session.php starts the session and enforces timeout; must come first
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/jwt.php';
require_once __DIR__ . '/../includes/secrets.php';
require_once __DIR__ . '/../includes/api_auth.php';

api_register_error_handler();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error(405, 'Method not allowed. Use GET.');
}

// A valid PHP session with user_id is the only requirement
if (empty($_SESSION['user_id'])) {
    api_error(401, 'No active session. Please log in first.');
}

$token = jwt_encode(
    jwt_make_payload((int)$_SESSION['user_id'], $_SESSION['username']),
    JWT_SECRET
);

api_success([
    'token' => $token,
    'user'  => [
        'id'       => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'],
    ],
]);
