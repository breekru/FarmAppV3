<?php
// view_animals.php — secure animal listing with Edit link
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

// Fetch animals for this user
$stmt = $pdo->prepare("SELECT * FROM animals WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute(['user_id' => $user_id]);
$animals = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Animals | FarmApp</title>
  <link rel="stylesheet" href="css/main.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ccc;
      text-align: left;
    }
    th {
      background: #f5f5f5;
    }
    .actions a {
      margin-right: 10px;
    }
  </style>
</head>
<body>
<div class="form-container">
  <h2>Your Animals</h2>

  <?php if (count($animals) === 0): ?>
    <p>You haven't added any animals yet.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Type</th>
          <th>Name</th>
          <th>DOB</th>
          <th>Weight</th>
          <th>Notes</th>
          <th>Edit</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($animals as $animal): ?>
          <tr>
            <td><?php echo htmlspecialchars($animal["type"]); ?></td>
            <td><?php echo htmlspecialchars($animal["name"]); ?></td>
            <td><?php echo htmlspecialchars($animal["dob"]); ?></td>
            <td><?php echo htmlspecialchars($animal["weight"]); ?></td>
            <td><?php echo nl2br(htmlspecialchars($animal["notes"])); ?></td>
            <td>
              <a href="edit_animal.php?id=<?php echo urlencode($animal['id']); ?>">✏️ Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="form-links" style="margin-top: 1rem;">
    <a href="add_animal.php">Add Another Animal</a> |
    <a href="dashboard.php">Back to Dashboard</a>
  </div>
</div>
</body>
</html>
