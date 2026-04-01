<?php

if (!function_exists('realtimeBase64UrlEncode')) {
    function realtimeBase64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('realtimeBase64UrlDecode')) {
    function realtimeBase64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}

if (!function_exists('realtimeSigningSecret')) {
    function realtimeSigningSecret(?string $fallback = null): string
    {
        $keys = ['WAVE1_REALTIME_SECRET', 'APP_KEY', 'JWT_SECRET'];
        foreach ($keys as $key) {
            $value = getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        if ($fallback !== null) {
            return $fallback;
        }

        throw new RuntimeException('Missing realtime signing secret.');
    }
}

if (!function_exists('issueRealtimeToken')) {
    function issueRealtimeToken(int $userId, string $role, string $secret, int $ttlSeconds = 120): string
    {
        $claims = [
            'uid' => $userId,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + max(1, $ttlSeconds),
            'nonce' => bin2hex(random_bytes(8)),
        ];

        $body = realtimeBase64UrlEncode(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $body, $secret);

        return $body . '.' . $signature;
    }
}

if (!function_exists('verifyRealtimeToken')) {
    function verifyRealtimeToken(string $token, string $secret, ?int $now = null): ?array
    {
        $now = $now ?? time();
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, $secret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payloadJson = realtimeBase64UrlDecode($body);
        if ($payloadJson === '') {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        $uid = (int) ($payload['uid'] ?? 0);
        $role = (string) ($payload['role'] ?? '');
        if ($uid <= 0 || $role === '' || $exp < $now) {
            return null;
        }

        return $payload;
    }
}
