<?php
// login.php
require_once "includes/session.php";
require_once "includes/db.php";

// Already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$ip       = $_SERVER['REMOTE_ADDR'];
$username = trim(strip_tags($_POST["username"] ?? ""));

// CSRF check on POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("Location: login.php");
        exit;
    }
}

// IP-based lockout
$stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND attempt_time > (NOW() - INTERVAL 1 HOUR)");
$stmt->execute(['ip' => $ip]);
$ip_attempts = (int) $stmt->fetchColumn();

if ($ip_attempts >= 20) {
    $_SESSION['lockout'] = "Too many failed attempts from this IP address. Please try again in 1 hour.";
}

// Username-based lockout
if (!empty($username)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = :username AND attempt_time > (NOW() - INTERVAL 1 HOUR)");
    $stmt->execute(['username' => $username]);
    $user_attempts = (int) $stmt->fetchColumn();

    if ($user_attempts >= 5) {
        $_SESSION['user_locked'] = true;
    }
}

// Process login if not IP-locked
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_SESSION['lockout'])) {
    $password = $_POST["password"] ?? "";

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: login.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->execute(['username' => $username, 'email' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"]       = $user["id"];
        $_SESSION["username"]      = $user["username"];
        $_SESSION["last_activity"] = time();

        // Clear login attempts for this IP and username
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip OR username = :username");
        $stmt->execute(['ip' => $ip, 'username' => $username]);

        header("Location: dashboard.php");
        exit;
    } else {
        // Log failed attempt
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (:username, :ip, NOW())");
        $stmt->execute(['username' => $username, 'ip' => $ip]);

        $_SESSION['error'] = "Invalid username/email or password.";
        header("Location: login.php");
        exit;
    }
}

// Generate CSRF token for the form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login | FarmApp</title>
  <?php require_once 'includes/pwa_head.php'; ?>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
</head>
<body>
  <div class="form-container">
    <h2>FarmApp Login</h2>

    <?php if (isset($_SESSION['lockout'])): ?>
      <p class="error"><?php echo htmlspecialchars($_SESSION['lockout'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php unset($_SESSION['lockout']); ?>
    <?php else: ?>

      <?php if (isset($_SESSION['user_locked']) && $_SESSION['user_locked'] === true): ?>
        <p class="error">Too many failed attempts for this username. Try again in 1 hour or use Forgot Password.</p>
        <?php unset($_SESSION['user_locked']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        <p class="error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['success'])): ?>
        <p class="success"><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <form action="login.php" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <label for="username">Email or Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>

        <button type="subm