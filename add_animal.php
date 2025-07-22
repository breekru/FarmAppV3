<?php
// add_animal.php — secure version
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();
require_once "includes/db.php";

// Redirect if not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== ($_SESSION["csrf_token"] ?? '')) {
        die("Invalid CSRF token");
    }

    $type = trim($_POST["type"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $dob = $_POST["dob"] ?? null;
    $weight = $_POST["weight"] ?? null;
    $notes = trim($_POST["notes"] ?? "");

    if (empty($type) || empty($name)) {
        $_SESSION["error"] = "Type and Name are required.";
        header("Location: add_animal.php");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO animals (user_id, type, name, dob, weight, notes) VALUES (:user_id, :type, :name, :dob, :weight, :notes)");
    $stmt->execute([
        'user_id' => $user_id,
        'type' => $type,
        'name' => $name,
        'dob' => $dob ?: null,
        'weight' => $weight ?: null,
        'notes' => $notes
    ]);

    $_SESSION["success"] = "Animal added successfully.";
    header("Location: dashboard.php");
    exit;
}

// Generate CSRF token
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Animal | FarmApp</title>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="form-container">
  <h2>Add New Animal</h2>

  <?php
  if (isset($_SESSION["error"])) {
      echo "<p class='error'>" . htmlspecialchars($_SESSION["error"], ENT_QUOTES, 'UTF-8') . "</p>";
      unset($_SESSION["error"]);
  }
  ?>

  <form action="add_animal.php" method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION["csrf_token"]; ?>">

    <label>Type (e.g. Cow, Pig, Goat)</label>
    <input type="text" name="type" required>

    <label>Name</label>
    <input type="text" name="name" required>

    <label>Date of Birth</label>
    <input type="date" name="dob">

    <label>Weight (lbs)</label>
    <input type="number" name="weight" step="0.01">

    <label>Notes</label>
    <textarea name="notes" rows="4"></textarea>

    <button type="submit">Add Animal</button>
  </form>

  <div class="form-links">
    <a href="dashboard.php">Back to Dashboard</a>
  </div>
</div>
</body>
</html>
