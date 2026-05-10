<?php
// change_password.php — Authenticated password change for logged-in users.
require_once "includes/session.php";
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("Location: change_password.php");
        exit;
    }

    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: change_password.php");
        exit;
    }

    if ($new !== $confirm) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: change_password.php");
        exit;
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $new)) {
        $_SESSION['error'] = "New password must be at least 8 characters and include uppercase, lowercase, and a number.";
        header("Location: change_password.php");
        exit;
    }

    // Fetch current hash
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password'])) {
        $_SESSION['error'] = "Current password is incorrect.";
        header("Location: change_password.php");
        exit;
    }

    // Prevent reuse of the same password
    if (password_verify($new, $row['password'])) {
        $_SESSION['error'] = "New password must be different from your current password.";
        header("Location: change_password.php");
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "UPDATE users SET password = :password, reset_token = NULL, reset_expires = NULL WHERE id = :id"
    );
    $stmt->execute(['password' => $hash, 'id' => $user_id]);

    $_SESSION['success'] = "Password updated successfully.";
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password | FarmApp</title>
  <?php require_once 'includes/pwa_head.php'; ?>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
</head>
<body>
  <div class="form-container">
    <h2>Change Password</h2>

    <?php if (isset($_SESSION['error'])): ?>
      <p class="error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form action="change_password.php" method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

      <label for="current_password">Current Password</label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>

      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>

      <label for="confirm_password">Confirm New Password</label>
      <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>

      <button type="submit">Update Password</button>
    </form>

    <div class="form-links">
      <a href="dashboard.php">Back to Dashboard</a>
    </div>
  </div>
</body>
</html>
