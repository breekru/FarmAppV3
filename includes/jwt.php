<?php
// includes/jwt.php — Pure-PHP JWT (HS256). No Composer required.
// Functions: jwt_encode(), jwt_decode()

/**
 * URL-safe Base64 encode (no padding).
 */
function jwt_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * URL-safe Base64 decode (handles missing padding).
 */
function jwt_base64url_decode(string $data): string {
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Create a signed JWT token.
 *
 * @param array  $payload  Claims to embed (should include 'exp', 'iat', 'sub').
 * @param string $secret   Signing secret from JWT_SECRET constant.
 * @return string          Signed token string.
 */
function jwt_encode(array $payload, string $secret): string {
    $header  = jwt_base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body    = jwt_base64url_encode(json_encode($payload));
    $sig     = jwt_base64url_encode(hash_hmac('sha256', "$header.$body", $secret, true));
    return "$header.$body.$sig";
}

/**
 * Verify and decode a JWT token.
 *
 * Returns the payload array on success.
 * Returns null if the token is malformed, signature-invalid, or expired.
 *
 * @param string $token   Token string (three dot-separated segments).
 * @param string $secret  Signing secret from JWT_SECRET constant.
 * @return array|null
 */
function jwt_decode(string $token, string $secret): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $body, $sig] = $parts;

    // Constant-time signature comparison prevents timing attacks
    $expected = jwt_base64url_encode(hash_hmac('sha256', "$header.$body", $secret, true));
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $payload = json_decode(jwt_base64url_decode($body), true);
    if (!is_array($payload)) {
        return null;
    }

    // Reject expired tokens
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return null;
    }

    // Reject tokens issued in the future (clock-skew tolerance: 60 s)
    if (isset($payload['iat']) && $payload['iat'] > time() + 60) {
        return null;
    }

    return $payload;
}

/**
 * Build a standard FarmApp token payload.
 *
 * @param int    $user_id
 * @param string $username
 * @param int    $ttl      Seconds until expiry (default 7 days).
 * @return array
 */
function jwt_make_payload(int $user_id, string $username, int $ttl = 604800): array {
    $now = time();
    return [
        'sub'      => $user_id,
        'username' => $username,
        'iat'      => $now,
        'exp'      => $now + $ttl,
    ];
}
