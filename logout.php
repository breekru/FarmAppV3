<?php
// logout.php
require_once "includes/session.php";

// Clear all session data
$_SESSION = [];

// Expire the session cookie
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
);

session_destroy();

// Start a fresh session to carry the success message
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

$_SESSION['success'] = "You have been logged out.";
header("Location: login.php");
exit;
