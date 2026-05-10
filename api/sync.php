<?php
// api/sync.php — Offline sync endpoint.
//
// POST /api/sync.php
//   Authorization: Bearer <jwt>
//   Body:
//   {
//     "changes": [
//       {
//         "action":   "create",
//         "local_id": "uuid-generated-on-device",   // returned in result so client can map
//         "data": { "type": "Cow", "name": "Bessie", ... }
//       },
//       {
//         "action":    "update",
//         "server_id": 12,
//         "data": { "type": "Cow", "name": "Bessie Updated", ... }
//       }
//     ]
//   }
//
//   Response:
//   {
//     "success": true,
//     "data": {
//       "applied": 2,
//       "skipped": 0,
//       "results": [
//         { "action": "create", "local_id": "uuid", "server_id": 7,  "status": "created" },
//         { "action": "update", "server_id": 12, "status": "updated" }
//       ]
//     }
//   }
//
// Conflict strategy: last-write-wins.
// The client is responsible for sending only genuinely pending changes.

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/db.php';

api_register_error_handler();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error(405, 'Method not allowed. Use POST.');
}

$user    = api_require_auth();
$user_id = (int)$user['sub'];
$body    = api_json_body();

if (!isset($body['changes']) || !is_array($body['changes'])) {
    api_error(400, 'Request body must contain a "changes" array.');
}

$changes = $body['changes'];
if (count($changes) === 0) {
    api_success(['applied' => 0, 'skipped' => 0, 'results' => []]);
}

// Cap batch size to prevent abuse
if (count($changes) > 200) {
    api_error(400, 'Maximum 200 changes per sync request.');
}

// -----------------------------------------------
// Allowed animal fields (whitelist)
// -----------------------------------------------
$ALLOWED_FIELDS = ['type', 'name', 'dob', 'weight', 'notes'];

/**
 * Validate and sanitise an animal data payload.
 * Returns cleaned array or an error string.
 */
function sync_validate_animal(array $data, array $allowed): array|string {
    $type   = trim($data['type']   ?? '');
    $name   = trim($data['name']   ?? '');
    $dob    = trim($data['dob']    ?? '');
    $weight = $data['weight'] ?? null;
    $notes  = trim($data['notes']  ?? '');

    if ($type === '' || $name === '') {
        return 'type and name are required';
    }

    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        return 'dob must be YYYY-MM-DD';
    }

    if ($weight !== null && $weight !== '') {
        if (!is_numeric($weight) || (float)$weight < 0) {
            return 'weight must be a positive number';
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

// -----------------------------------------------
// Process each change inside a transaction
// -----------------------------------------------
$results  = [];
$applied  = 0;
$skipped  = 0;

$pdo->beginTransaction();

try {
    foreach ($changes as $idx => $change) {
        $action = strtolower(trim($change['action'] ?? ''));

        if (!in_array($action, ['create', 'update'], true)) {
            $results[] = [
                'index'  => $idx,
                'action' => $action,
                'status' => 'skipped',
                'reason' => 'Unknown action. Supported: create, update',
            ];
            $skipped++;
            continue;
        }

        $data = $change['data'] ?? [];
        if (!is_array($data)) {
            $results[] = [
                'index'  => $idx,
                'action' => $action,
                'status' => 'skipped',
                'reason' => 'data field must be an object',
            ];
            $skipped++;
            continue;
        }

        $validated = sync_validate_animal($data, $ALLOWED_FIELDS);
        if (is_string($validated)) {
            $results[] = [
                'index'    => $idx,
                'action'   => $action,
                'local_id' => $change['local_id'] ?? null,
                'status'   => 'skipped',
                'reason'   => $validated,
            ];
            $skipped++;
            continue;
        }

        // ---- CREATE ----
        if ($action === 'create') {
            $local_id = $change['local_id'] ?? null;

            $stmt = $pdo->prepare(
                "INSERT INTO animals (user_id, type, name, dob, weight, notes)
                 VALUES (:user_id, :type, :name, :dob, :weight, :notes)"
            );
            $stmt->execute([
                'user_id' => $user_id,
                'type'    => $validated['type'],
                'name'    => $validated['name'],
                'dob'     => $validated['dob'],
                'weight'  => $validated['weight'],
                'notes'   => $validated['notes'],
            ]);

            $server_id = (int)$pdo->lastInsertId();
            $results[] = [
                'action'    => 'create',
                'local_id'  => $local_id,
                'server_id' => $server_id,
                'status'    => 'created',
            ];
            $applied++;
        }

        // ---- UPDATE ----
        if ($action === 'update') {
            $server_id = isset($change['server_id']) && is_int($change['server_id'])
                ? $change['server_id']
                : null;

            if ($server_id === null) {
                $results[] = [
                    'index'  => $idx,
                    'action' => 'update',
                    'status' => 'skipped',
                    'reason' => 'server_id is required for update',
                ];
                $skipped++;
                continue;
            }

            // Ownership check is baked into the WHERE clause
            $stmt = $pdo->prepare(
                "UPDATE animals
                 SET type = :type, name = :name, dob = :dob, weight = :weight, notes = :notes
                 WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([
                'type'    => $validated['type'],
                'name'    => $validated['name'],
                'dob'     => $validated['dob'],
                'weight'  => $validated['weight'],
                'notes'   => $validated['notes'],
                'id'      => $server_id,
                'user_id' => $user_id,
            ]);

            if ($stmt->rowCount() === 0) {
                $results[] = [
                    'action'    => 'update',
                    'server_id' => $server_id,
                    'status'    => 'skipped',
                    'reason'    => 'Animal not found or not owned by this user',
                ];
                $skipped++;
            } else {
                $results[] = [
                    'action'    => 'update',
                    'server_id' => $server_id,
                    'status'    => 'updated',
                ];
                $applied++;
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Sync transaction failed: ' . $e->getMessage());
    api_error(500, 'Sync failed. Changes were rolled back. Please try again.');
}

api_success([
    'applied' => $applied,
    'skipped' => $skipped,
    'results' => $results,
]);
