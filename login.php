<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "includes/db.php";

// Redirect if already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: login.php");
        exit;
    }

    // Fetch user using username or email
    $stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->execute([
        'username' => $username,
        'email' => $username
    ]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        // Login successful
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = "Invalid username/email or password.";
        header("Location: login.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login | FarmApp</title>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div class="form-container">
    <h2>FarmApp Login</h2>

    <?php
    if (isset($_SESSION['error'])) {
        echo "<p class='error'>" . $_SESSION['error'] . "</p>";
        unset($_SESSION['error']);
    }
    ?>

    <form action="login.php" method="post" novalidate>
      <label>Email or Username</label>
      <input type="text" name="username" required>

      <label>Password</label>
      <input type="password" name="password" required>

      <button type="submit">Login</button>
    </form>

    <div class="form-links">
      <a href="register.php">Create Account</a> |
      <a href="forgot_password.php">Forgot Password?</a>
    </div>
  </div>

  <script src="js/form.js"></script>
</body>
</html>
