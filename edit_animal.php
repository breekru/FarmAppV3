<?php
// edit_animal.php
require_once "includes/session.php";
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION["user_id"];
$animal_id = $_GET["id"] ?? null;

if (!$animal_id || !ctype_digit((string)$animal_id)) {
    header("Location: view_animals.php");
    exit;
}

// Fetch animal — must belong to this user
$stmt = $pdo->prepare("SELECT * FROM animals WHERE id = :id AND user_id = :user_id");
$stmt->execute(['id' => $animal_id, 'user_id' => $user_id]);
$animal = $stmt->fetch();

if (!$animal) {
    $_SESSION["error"] = "Animal not found or access denied.";
    header("Location: view_animals.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== ($_SESSION["csrf_token"] ?? '')) {
        $_SESSION["error"] = "Invalid request. Please try again.";
        header("Location: edit_animal.php?id=" . urlencode($animal_id));
        exit;
    }

    $type   = trim($_POST["type"] ?? "");
    $name   = trim($_POST["name"] ?? "");
    $dob    = $_POST["dob"] ?? null;
    $weight = $_POST["weight"] ?? null;
    $notes  = trim($_POST["notes"] ?? "");

    if (empty($type) || empty($name)) {
        $_SESSION["error"] = "Type and Name are required.";
        header("Location: edit_animal.php?id=" . urlencode($animal_id));
        exit;
    }

    $stmt = $pdo->prepare("UPDATE animals SET type = :type, name = :name, dob = :dob, weight = :weight, notes = :notes WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        'type'    => $type,
        'name'    => $name,
        'dob'     => $dob ?: null,
        'weight'  => $weight ?: null,
        'notes'   => $notes,
        'id'      => $animal_id,
        'user_id' => $user_id,
    ]);

    $_SESSION["success"] = "Animal updated successfully.";
    header("Location: view_animals.php");
    exit;
}

$_SESSION["csrf_token"] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Animal | FarmApp</title>
  <?php require_once 'includes/pwa_head.php'; ?>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
</head>
<body>
<div class="form-container">
  <h2>Edit: <?php echo htmlspecialchars($animal["name"], ENT_QUOTES, 'UTF-8'); ?></h2>

  <?php if (isset($_SESSION["error"])): ?>
    <p class="error"><?php echo htmlspecialchars($_SESSION["error"], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php unset($_SESSION["error"]); ?>
  <?php endif; ?>

  <form action="edit_animal.php?id=<?php echo urlencode($animal_id); ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, 'UTF-8'); ?>">

    <label for="type">Type</label>
    <input type="text" id="type" name="type" value="<?php echo htmlspecialchars($animal["type"], ENT_QUOTES, 'UTF-8'); ?>" required>

    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($animal["name"], ENT_QUOTES, 'UTF-8'); ?>" required>

    <label for="dob">Date of Birth</label>
    <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($animal["dob"] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

    <label for="weight">Weight (lbs)</label>
    <input type="number" id="weight" name="weight" step="0.01" min="0" value="<?php echo htmlspecialchars($animal["weight"] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

    <label for="notes">Notes</label>
    <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($animal["notes"] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

    <button type="submit">Update Animal</button>