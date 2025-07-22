<?php
// logout.php (Secure Version)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();

// Clear session data
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Fully destroy session
session_destroy();

// Start a new session for logout message
session_start();
$_SESSION['success'] = "You have been logged out.";

header("Location: login.php");
exit;
?>
