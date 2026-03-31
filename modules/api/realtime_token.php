<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/realtime_signer.php';

function wave1RealtimePhpConfig(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $env = [];
    $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    if (is_file($envPath)) {
        $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if (is_array($parsed)) {
            $env = $parsed;
        }
    }

    $read = static function (array $keys, bool $required = false) use ($env): ?string {
        foreach ($keys as $key) {
            $value = getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
                return trim((string) $_ENV[$key]);
            }

            if (isset($env[$key]) && trim((string) $env[$key]) !== '') {
                return trim((string) $env[$key]);
            }
        }

        if ($required) {
            throw new RuntimeException('Thiếu cấu hình realtime bắt buộc: ' . implode(', ', $keys));
        }

        return null;
    };

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    $config = [
        'shared_secret' => $read(['WAVE1_REALTIME_SECRET', 'REALTIME_SHARED_SECRET'], true),
        'publish_secret' => $read(['WAVE1_REALTIME_PUBLISH_SECRET']) ?: $read(['WAVE1_REALTIME_SECRET', 'REALTIME_SHARED_SECRET'], true),
        'ws_url' => $read(['REALTIME_WS_URL']) ?: (($isHttps ? 'wss' : 'ws') . '://' . $host . ':3000/ws'),
        'publish_url' => $read(['REALTIME_PUBLISH_URL']) ?: (($isHttps ? 'https' : 'http') . '://' . $host . ':3000/api/realtime/publish'),
    ];

    return $config;
}

try {
    requireLogin();

    $config = wave1RealtimePhpConfig();
    $userId = (int) ($_SESSION['UserID'] ?? 0);
    $role = (string) ($_SESSION['Role'] ?? 'Guest');

    if ($userId <= 0) {
        throw new RuntimeException('Phiên đăng nhập không hợp lệ.');
    }

    $token = issueRealtimeToken($userId, $role, $config['shared_secret']);

    echo json_encode([
        'ok' => true,
        'token' => $token,
        'wsUrl' => $config['ws_url'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof RuntimeException ? 503 : 401);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
