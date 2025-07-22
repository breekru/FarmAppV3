<?php
// register.php (Secure Version)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $username = trim(strip_tags($_POST["username"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: register.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: register.php");
        exit;
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: register.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $stmt->execute(['username' => $username, 'email' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Username or email already taken.";
        header("Location: register.php");
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => $hashed_password
    ]);

    $_SESSION['success'] = "Account created! You can now log in.";
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register | FarmApp</title>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div class="form-container">
    <h2>Create Account</h2>

    <?php
    if (isset($_SESSION['error'])) {
        echo "<p class='error'>" . htmlspecialchars($_SESSION['error']) . "</p>";
        unset($_SESSION['error']);
    }
    ?>

    <form action="register.php" method="post" novalidate>
      <label>Username</label>
      <input type="text" name="username" required>

      <label>Email</label>
      <input type="email" name="email" required>

      <label>Password</label>
      <input type="password" name="password" required>

      <label>Confirm Password</label>
      <input type="password" name="confirm_password" required>

      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      <button type="submit">Register</button>
    </form>

    <div class="form-links">
      <a href="login.php">Already have an account? Login</a>
    </div>
  </div>

  <script src="js/form.js"></script>
</body>
</html>
