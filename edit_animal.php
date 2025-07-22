<?php
// edit_animal.php — secure update form
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$animal_id = $_GET["id"] ?? null;

if (!$animal_id || !is_numeric($animal_id)) {
    header("Location: view_animals.php");
    exit;
}

// Fetch animal and ensure it belongs to this user
$stmt = $pdo->prepare("SELECT * FROM animals WHERE id = :id AND user_id = :user_id");
$stmt->execute(['id' => $animal_id, 'user_id' => $user_id]);
$animal = $stmt->fetch();

if (!$animal) {
    $_SESSION["error"] = "Animal not found or access denied.";
    header("Location: view_animals.php");
    exit;
}

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
        header("Location: edit_animal.php?id=" . urlencode($animal_id));
        exit;
    }

    $stmt = $pdo->prepare("UPDATE animals SET type = :type, name = :name, dob = :dob, weight = :weight, notes = :notes WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        'type' => $type,
        'name' => $name,
        'dob' => $dob ?: null,
        'weight' => $weight ?: null,
        'notes' => $notes,
        'id' => $animal_id,
        'user_id' => $user_id
    ]);

    $_SESSION["success"] = "Animal updated successfully.";
    header("Location: view_animals.php");
    exit;
}

// CSRF token
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Animal | FarmApp</title>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="form-container">
  <h2>Edit Animal: <?php echo htmlspecialchars($animal["name"]); ?></h2>

  <?php
  if (isset($_SESSION["error"])) {
      echo "<p class='error'>" . htmlspecialchars($_SESSION["error"], ENT_QUOTES, 'UTF-8') . "</p>";
      unset($_SESSION["error"]);
  }
  ?>

  <form action="edit_animal.php?id=<?php echo urlencode($animal_id); ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION["csrf_token"]; ?>">

    <label>Type</label>
    <input type="text" name="type" value="<?php echo htmlspecialchars($animal["type"]); ?>" required>

    <label>Name</label>
    <input type="text" name="name" value="<?php echo htmlspecialchars($animal["name"]); ?>" required>

    <label>Date of Birth</label>
    <input type="date" name="dob" value="<?php echo htmlspecialchars($animal["dob"]); ?>">

    <label>Weight (lbs)</label>
    <input type="number" step="0.01" name="weight" value="<?php echo htmlspecialchars($animal["weight"]); ?>">

    <label>Notes</label>
    <textarea name="notes" rows="4"><?php echo htmlspecialchars($animal["notes"]); ?></textarea>

    <button type="submit">Update Animal</button>
  </form>

  <div class="form-links">
    <a href="view_animals.php">Back to Animal List</a>
  </div>
</div>
</body>
</html>
