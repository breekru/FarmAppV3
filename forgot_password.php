<?php
// forgot_password.php (Secure Version)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["error"] = "Invalid email address.";
        header("Location: forgot_password.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = generateToken();
        $expires = date("Y-m-d H:i:s", time() + 3600);

        $stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
        $stmt->execute([
            'token' => $token,
            'expires' => $expires,
            'id' => $user["id"]
        ]);

        $resetLink = "https://farmappv3.blkfarms.com/reset_password.php?token=$token";
        $sent = sendResetEmail($email, $resetLink);
        if ($sent) {
            $_SESSION["success"] = "A password reset link has been sent to your email.";
        } else {
            $_SESSION["error"] = "Failed to send the reset email. Please try again later.";
        }
    } else {
        $_SESSION["success"] = "A password reset link has been sent to your email.";
    }

    header("Location: forgot_password.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password | FarmApp</title>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div class="form-container">
    <h2>Forgot Password</h2>

    <?php
    if (isset($_SESSION['error'])) {
        echo "<p class='error'>" . htmlspecialchars($_SESSION['error']) . "</p>";
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
        echo "<p class='success'>" . htmlspecialchars($_SESSION['success']) . "</p>";
        unset($_SESSION['success']);
    }
    ?>

    <form action="forgot_password.php" method="post" novalidate>
      <label>Enter Your Email Address</label>
      <input type="email" name="email" required>

      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      <button type="submit">Send Reset Link</button>
    </form>

    <div class="form-links">
      <a href="login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>
