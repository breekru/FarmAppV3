<?php
// dashboard.php (Secure Version)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | FarmApp</title>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div class="form-container">
    <h2>Welcome to FarmApp, <?php echo $username; ?>!</h2>

    <p>This is your dashboard. From here you’ll be able to:</p>
    <ul style="text-align: left; margin-top: 1rem;">
      <li>👁 View & manage your animals</li>
      <li>➕ Add new animal records</li>
      <li>🧾 Generate farm reports</li>
      <li>⚙️ Update account settings</li>
    </ul>

    <br>
    <a href="logout.php" class="button-link">Logout</a>
  </div>
</body>
</html>
