<?php
// login.php — Hybrid Lockout with IP block fix
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();
require_once "includes/db.php";

// CSRF protection
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("Invalid CSRF token");
    }
}

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];
$username = trim(strip_tags($_POST["username"] ?? ""));

// --- Check IP-based lockout (log IP attempts separately) ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND attempt_time > (NOW() - INTERVAL 1 HOUR)");
$stmt->execute(['ip' => $ip]);
$ip_attempts = $stmt->fetchColumn();

if ($ip_attempts >= 20) {
    $_SESSION['lockout'] = "Too many failed attempts from this IP address. Please try again in 1 hour.";
}

// --- Check Username-based lockout ---
if (!empty($username)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = :username AND attempt_time > (NOW() - INTERVAL 1 HOUR)");
    $stmt->execute(['username' => $username]);
    $user_attempts = $stmt->fetchColumn();

    if ($user_attempts >= 5) {
        $_SESSION['user_locked'] = true;
    }
}

// --- Handle login attempt only if not IP-blocked ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_SESSION['lockout'])) {
    $password = $_POST["password"] ?? "";

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

        // Clear login attempts for IP and username
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip OR username = :username");
        $stmt->execute(['ip' => $ip, 'username' => $username]);

        header("Location: dashboard.php");
        exit;
    } else {
        // Log failed attempt by username and IP
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (:username, :ip, NOW())");
        $stmt->execute(['username' => $username, 'ip' => $ip]);

        $_SESSION['error'] = "Invalid username/email or password.";
        header("Location: login.php");
        exit;
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    if (isset($_SESSION['lockout'])) {
        echo "<p class='error'>" . htmlspecialchars($_SESSION['lockout'], ENT_QUOTES, 'UTF-8') . "</p>";
        unset($_SESSION['lockout']);
        exit;
    }

    if (isset($_SESSION['user_locked']) && $_SESSION['user_locked'] === true) {
        echo "<p class='error'>Too many failed attempts for this username. You can try again in 1 hour or use Forgot Password.</p>";
        unset($_SESSION['user_locked']);
    }

    if (isset($_SESSION['error'])) {
        echo "<p class='error'>" . htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') . "</p>";
        unset($_SESSION['error']);
    }
    ?>

    <form action="login.php" method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
