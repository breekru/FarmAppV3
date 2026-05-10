<?php
// api/animals.php — Animal CRUD endpoint.
//
// All requests require:  Authorization: Bearer <jwt>
//
// GET    /api/animals.php           → list all animals for the authenticated user
// GET    /api/animals.php?id=N      → fetch single animal by ID
// POST   /api/animals.php           → create animal
// PUT    /api/animals.php?id=N      → update animal
// DELETE /api/animals.php?id=N      → delete animal

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/db.php';

api_register_error_handler();

$user    = api_require_auth();
$user_id = (int)$user['sub'];
$method  = $_SERVER['REQUEST_METHOD'];

// -----------------------------------------------
// Helpers
// -----------------------------------------------

/**
 * Validate and sanitise animal fields from a request body array.
 * Returns cleaned data or exits with 400 on validation failure.
 */
function validate_animal(array $body): array {
    $type   = trim($body['type']   ?? '');
    $name   = trim($body['name']   ?? '');
    $dob    = trim($body['dob']    ?? '');
    $weight = $body['weight'] ?? null;
    $notes  = trim($body['notes']  ?? '');

    if ($type === '' || $name === '') {
        api_error(400, 'type and name are required.');
    }

    // Validate DOB format
    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        api_error(400, 'dob must be in YYYY-MM-DD format.');
    }

    // Validate weight
    if ($weight !== null && $weight !== '') {
        if (!is_numeric($weight) || (float)$weight < 0) {
            api_error(400, 'weight must be a positive number.');
        }
        $weight = round((float)$weight, 2);
    } else {
        $weight = null;
    }

    return [
        'type'   => $type,
        'name'   => $name,
        'dob'    => $dob !== '' ? $dob : null,
        'weight' => $weight,
        'notes'  => $notes,
    ];
}

/**
 * Format an animal row for API output (cast types).
 */
function format_animal(array $row): array {
    return [
        'id'         => (int)$row['id'],
        'user_id'    => (int)$row['user_id'],
        'type'       => $row['type'],
        'name'       => $row['name'],
        'dob'        => $row['dob'],
        'weight'     => $row['weight'] !== null ? (float)$row['weight'] : null,
        'notes'      => $row['notes'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

// -----------------------------------------------
// GET — list or single fetch
// -----------------------------------------------
if ($method === 'GET') {
    $id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id !== null) {
        $stmt = $pdo->prepare(
            "SELECT * FROM animals WHERE id = :id AND user_id = :user_id LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'user_id' => $user_id]);
        $animal = $stmt->fetch();

        if (!$animal) {
            api_error(404, 'Animal not found.');
        }

        api_success(format_animal($animal));
    }

    // List all
    $stmt = $pdo->prepare(
        "SELECT * FROM animals WHERE user_id = :user_id ORDER BY created_at DESC"
    );
    $stmt->execute(['user_id' => $user_id]);
    $animals = array_map('format_animal', $stmt->fetchAll());

    api_success($animals);
}

// -----------------------------------------------
// POST — create
// -----------------------------------------------
if ($method === 'POST') {
    $body   = api_json_body();
    $fields = validate_animal($body);

    $stmt = $pdo->prepare(
        "INSERT INTO animals (user_id, type, name, dob, weight, notes)
         VALUES (:user_id, :type, :name, :dob, :weight, :notes)"
    );
    $stmt->execute([
        'user_id' => $user_id,
        'type'    => $fields['type'],
        'name'    => $fields['name'],
        'dob'     => $fields['dob'],
        'weight'  => $fields['weight'],
        'notes'   => $fields['notes'],
    ]);

    $new_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $new_id]);
    $animal = $stmt->fetch();

    http_response_code(201);
    api_success(format_animal($animal));
}

// -----------------------------------------------
// PUT — update
// -----------------------------------------------
if ($method === 'PUT') {
    $id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;
    if ($id === null) {
        api_error(400, 'id query parameter is required for PUT.');
    }

    // Confirm the animal belongs to this user before accepting the body
    $stmt = $pdo->prepare("SELECT id FROM animals WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmt->execute(['id' => $id, 'user_id' => $user_id]);
    if (!$stmt->fetch()) {
        api_error(404, 'Animal not found.');
    }

    $body   = api_json_body();
    $fields = validate_animal($body);

    $stmt = $pdo->prepare(
        "UPDATE animals
         SET type = :type, name = :name, dob = :dob, weight = :weight, notes = :notes
         WHERE id = :id AND user_id = :user_id"
    );
    $stmt->execute([
        'type'    => $fields['type'],
        'name'    => $fields['name'],
        'dob'     => $fields['dob'],
        'weight'  => $fields['weight'],
        'notes'   => $fields['notes'],
        'id'      => $id,
        'user_id' => $user_id,
    ]);

    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $animal = $stmt->fetch();

    api_success(format_animal($animal));
}

// -----------------------------------------------
// DELETE
// -----------------------------------------------
if ($method === 'DELETE') {
    $id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;
    if ($id === null) {
        api_error(400, 'id query parameter is required for DELETE.');
    }

    $stmt = $pdo->prepare("DELETE FROM animals WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $id, 'user_id' => $user_id]);

    if ($stmt->rowCount() === 0) {
        api_error(404, 'Animal not found.');
    }

    api_success(['deleted_id' => $id]);
}

// -----------------------------------------------
// Unsupported method
// -----------------------------------------------
api_error(405, 'Method not allowed.');
