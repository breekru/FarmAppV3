<?php
// includes/session.php — Centralized session bootstrap.
// require_once this at the very top of every PHP page (before any output).

// --- Error handling: log errors, never display them ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// --- Secure session cookie settings (must be set before session_start) ---
session_set_cookie_params([
    'lifetime' => 0,           // cookie expires when browser closes
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,        // HTTPS only
    'httponly' => true,        // no JavaScript access
    'samesite' => 'Strict',    // CSRF mitigation
]);

// Use a custom session name so PHPSESSID is not advertised
session_name('FARMSESSID');

// Strict mode: prevents session fixation with uninitialized IDs
ini_set('session.use_strict_mode', 1);

session_start();

// --- Session timeout: 30 minutes of inactivity for authenticated users ---
define('SESSION_TIMEOUT', 1800);

if (isset($_SESSION['user_id'], $_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Wipe session data and destroy
        session_unset();
        session_destroy();

        // Start fresh session to carry the error message to the login page
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_name('FARMSESSID');
        session_start();

        $_SESSION['error'] = "Your session expired. Please log in again.";
        // Auth checks in each page will redirect to login because user_id is gone
    }
}

// Keep last-activity timestamp fresh while logged in
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}
