<?php
// dashboard.php — with navigation
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
  <style>
    .grid-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      justify-content: center;
      margin-top: 2rem;
    }
    .grid-buttons a {
      display: inline-block;
      padding: 1rem 2rem;
      text-align: center;
      text-decoration: none;
      color: white;
      background-color: #2d2d30;
      border-radius: 8px;
      font-size: 1rem;
      min-width: 160px;
    }
    .grid-buttons a:hover {
      background-color: #1f1f21;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Welcome to FarmApp, <?php echo $username; ?>!</h2>

    <p>What would you like to do?</p>

    <div class="grid-buttons">
      <a href="add_animal.php">➕ Add Animal</a>
      <a href="view_animals.php">📋 View Animals</a>
      <a href="reset_password.php">🔐 Change Password</a>
      <a href="logout.php">🚪 Logout</a>
    </div>
  </div>
</body>
</html>
