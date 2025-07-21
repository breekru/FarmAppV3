<?php
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Optional: show logout message
session_start();
$_SESSION['error'] = "You have been logged out.";

header("Location: login.php");
exit;
?>
