<?php
// api/auth.php — Authentication endpoint.
//
// POST /api/auth.php
//   Body: { "username": "...", "password": "..." }
//   Returns: { "success": true, "token": "<jwt>", "user": { "id": 1, "username": "..." } }
//
// POST /api/auth.php   (token refresh)
//   Body: { "refresh_token": "<existing-jwt>" }
//   Returns: { "success": true, "token": "<new-jwt>" }

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/db.php';

api_register_error_handler();

// Only POST is accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error(405, 'Method not allowed. Use POST.');
}

$body = api_json_body();

// -----------------------------------------------
// Token refresh path
// -----------------------------------------------
if (isset($body['refresh_token'])) {
    $payload = jwt_decode($body['refresh_token'], JWT_SECRET);

    // Allow refresh up to 30 days after issuance even if token is expired
    // (we re-check by decoding without expiry check)
    if ($payload === null) {
        // Try decoding ignoring expiry
        $parts = explode('.', $body['refresh_token']);
        if (count($parts) === 3) {
            $raw = json_decode(jwt_base64url_decode($parts[1]), true);
            // Only allow refresh within 30 days of issuance
            if (
                is_array($raw) &&
                isset($raw['iat'], $raw['sub'], $raw['username']) &&
                (time() - $raw['iat']) <= (30 * 86400)
            ) {
                // Verify signature on the raw parts still
                $expected = jwt_base64url_encode(hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET, true));
                if (hash_equals($expected, $parts[2])) {
                    $payload = $raw; // signature valid, allow refresh
                }
            }
        }
    }

    if ($payload === null || empty($payload['sub']) || empty($payload['username'])) {
        api_error(401, 'Cannot refresh — token is invalid or too old. Please log in again.');
    }

    $new_token = jwt_encode(
        jwt_make_payload((int)$payload['sub'], $payload['username']),
        JWT_SECRET
    );

    api_success(['token' => $new_token]);
}

// -----------------------------------------------
// Login path
// -----------------------------------------------
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === '' || $password === '') {
    api_error(400, 'username and password are required.');
}

$ip = $_SERVER['REMOTE_ADDR'];

// IP-based rate limit (reuse web login_attempts table)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM login_attempts
     WHERE ip_address = :ip AND attempt_time > (NOW() - INTERVAL 1 HOUR)"
);
$stmt->execute(['ip' => $ip]);
if ((int)$stmt->fetchColumn() >= 20) {
    api_error(429, 'Too many failed attempts from this IP. Try again in 1 hour.');
}

// Username-based rate limit
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM login_attempts
     WHERE username = :username AND attempt_time > (NOW() - INTERVAL 1 HOUR)"
);
$stmt->execute(['username' => $username]);
if ((int)$stmt->fetchColumn() >= 5) {
    api_error(429, 'Too many failed attempts for this username. Try again in 1 hour.');
}

// Fetch user
$stmt = $pdo->prepare(
    "SELECT id, username, password FROM users
     WHERE username = :u OR email = :e LIMIT 1"
);
$stmt->execute(['u' => $username, 'e' => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    // Clear login attempts on success
    $stmt = $pdo->prepare(
        "DELETE FROM login_attempts WHERE ip_address = :ip OR username = :username"
    );
    $stmt->execute(['ip' => $ip, 'username' => $username]);

    $token = jwt_encode(
        jwt_make_payload((int)$user['id'], $user['username']),
        JWT_SECRET
    );

    api_success([
        'token' => $token,
        'user'  => [
            'id'       => (int)$user['id'],
            'username' => $user['username'],
        ],
    ]);
} else {
    // Log the failed attempt
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (:username, :ip, NOW())"
    );
    $stmt->execute(['username' => $username, 'ip' => $ip]);

    api_error(401, 'Invalid credentials.');
}
