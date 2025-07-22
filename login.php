<?php
// login.php (Secure Version)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // if using HTTPS

session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

// Redirect if already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

// CSRF setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $username = trim(strip_tags($_POST["username"]));
    $password = $_POST["password"];

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: login.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->execute([
        'username' => $username,
        'email' => $username
    ]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);
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
        echo "<p class='error'>" . htmlspecialchars($_SESSION['error']) . "</p>";
        unset($_SESSION['error']);
    }
    ?>

    <form action="login.php" method="post" novalidate>
      <label>Email or Username</label>
      <input type="text" name="username" required>

      <label>Password</label>
      <input type="password" name="password" required>

      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
