<?php
// reset_password.php
require_once "includes/session.php";
require_once "includes/db.php";

if (!isset($_GET["token"])) {
    $_SESSION["error"] = "Invalid or missing reset token.";
    header("Location: login.php");
    exit;
}

$token = $_GET["token"];

$stmt = $pdo->prepare("SELECT id, reset_expires FROM users WHERE reset_token = :token LIMIT 1");
$stmt->execute(['token' => $token]);
$user = $stmt->fetch();

if (!$user || strtotime($user["reset_expires"]) < time()) {
    $_SESSION["error"] = "This reset link is invalid or has expired.";
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION["error"] = "Invalid request. Please try again.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    if (empty($password) || empty($confirm)) {
        $_SESSION["error"] = "Both password fields are required.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION["error"] = "Passwords do not match.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $_SESSION["error"] = "Password must be at least 8 characters and include uppercase, lowercase, and a number.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = :pass, reset_token = NULL, reset_expires = NULL WHERE id = :id");
    $stmt->execute(['pass' => $hashed, 'id' => $user["id"]]);

    $_SESSION["success"] = "Your password has been reset. Please log in.";
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password | FarmApp</title>
  <?php require_once 'includes/pwa_head.php'; ?>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
</head>
<body>
  <div class="form-container">
    <h2>Reset Password</h2>

    <?php if (isset($_SESSION['error'])): ?>
      <p class="error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form action="reset_password.php?token=<?php echo urlencode($token); ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

      <label for="password">New Password</label>
      <input type="password" id="password" name="password" autocomplete="new-password" required>

      <label for="confirm_password">Confirm New Password</label>
      <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>

      <button type="submit">Update Password</button>
    </form>

    <div class="form-links">
      <a href="login.php">Back to Login</a>
    