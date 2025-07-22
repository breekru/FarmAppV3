<?php
// reset_password.php (Secure Version)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (!isset($_GET["token"])) {
    $_SESSION["error"] = "Invalid or missing token.";
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if (empty($password) || empty($confirm)) {
        $_SESSION["error"] = "Both password fields are required.";
        header("Location: reset_password.php?token=$token");
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION["error"] = "Passwords do not match.";
        header("Location: reset_password.php?token=$token");
        exit;
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).{8,}$/', $password)) {
    $_SESSION['error'] = "Password must be at least 8 characters long and include upper/lowercase letters and a number.";
    header("Location: reset_password.php?token=$token");
    exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = :pass, reset_token = NULL, reset_expires = NULL WHERE id = :id");
    $stmt->execute([
        'pass' => $hashed,
        'id' => $user["id"]
    ]);

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
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div class="form-container">
    <h2>Reset Password</h2>

    <?php
    if (isset($_SESSION['error'])) {
        echo "<p class='error'>" . htmlspecialchars($_SESSION['error']) . "</p>";
        unset($_SESSION['error']);
    }
    ?>

    <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
      <label>New Password</label>
      <input type="password" name="password" required>

      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required>

      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      <button type="submit">Update Password</button>
    </form>

    <div class="form-links">
      <a href="login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>
