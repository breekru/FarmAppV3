<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

// Generate a secure reset token
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    if (empty($email)) {
        $_SESSION["error"] = "Please enter your email.";
        header("Location: forgot_password.php");
        exit;
    }

    // Check if the user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = generateToken();
        $expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour from now

        // Save token to DB
        $stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
        $stmt->execute([
            'token' => $token,
            'expires' => $expires,
            'id' => $user['id']
        ]);

        // Build reset link
        $resetLink = "https://farmapp.blkfarms.com/reset_password.php?token=$token";

        // Send reset email
        $sent = sendResetEmail($email, $resetLink);
        if ($sent) {
            $_SESSION["success"] = "A password reset link has been sent to your email.";
        } else {
            $_SESSION["error"] = "Unable to send the reset email. Please try again later.";
        }
        header("Location: forgot_password.php");
        exit;
    }

    // Generic response even if user is not found (security best practice)
    $_SESSION["success"] = "A password reset link has been sent to your email.";
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
        echo "<p class='error'>" . $_SESSION['error'] . "</p>";
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
        echo "<p class='success'>" . $_SESSION['success'] . "</p>";
        unset($_SESSION['success']);
    }
    ?>

    <form action="forgot_password.php" method="post" novalidate>
      <label>Enter Your Email Address</label>
      <input type="email" name="email" required>

      <button type="submit">Send Reset Link</button>
    </form>

    <div class="form-links">
      <a href="login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>
