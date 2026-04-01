<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/notification_service.php';

function wave1PushConfig(): array
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
            throw new RuntimeException('Thiếu cấu hình realtime publish: ' . implode(', ', $keys));
        }

        return null;
    };

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    $sharedSecret = $read(['WAVE1_REALTIME_SECRET', 'REALTIME_SHARED_SECRET'], true);
    $config = [
        'publish_secret' => $read(['WAVE1_REALTIME_PUBLISH_SECRET']) ?: $sharedSecret,
        'publish_url' => $read(['REALTIME_PUBLISH_URL']) ?: (($isHttps ? 'https' : 'http') . '://' . $host . ':3000/api/realtime/publish'),
    ];

    return $config;
}

function pushNotificationResolveUserIds(mysqli $conn, string $scope, array $requestedUserIds): array
{
    $requestedUserIds = array_values(array_unique(array_filter(array_map('intval', $requestedUserIds))));
    if ($scope === 'user_ids') {
        return $requestedUserIds;
    }

    $sql = match ($scope) {
        'students' => "SELECT UserID FROM users WHERE Role = 'Student' AND IsActive = 1",
        'staff_admin' => "SELECT UserID FROM users WHERE Role IN ('Admin', 'Manager') AND IsActive = 1",
        'all' => "SELECT UserID FROM users WHERE IsActive = 1",
        default => throw new InvalidArgumentException('Nhóm nhận thông báo không hợp lệ.'),
    };

    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Không lấy được danh sách người nhận.');
    }

    $userIds = [];
    while ($row = $result->fetch_assoc()) {
        $userIds[] = (int) ($row['UserID'] ?? 0);
    }

    return array_values(array_unique(array_filter($userIds)));
}

function pushNotificationPublish(array $userIds, array $payload): array
{
    $config = wave1PushConfig();
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'ignore_errors' => true,
            'timeout' => 3,
            'header' => implode("\r\n", [
                'Content-Type: application/json; charset=utf-8',
                'X-Realtime-Publish-Secret: ' . $config['publish_secret'],
            ]),
            'content' => json_encode([
                'userIds' => $userIds,
                'event' => 'notification.created',
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ],
    ]);

    $response = @file_get_contents($config['publish_url'], false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $statusCode = isset($matches[1]) ? (int) $matches[1] : 0;

    return [
        'ok' => $statusCode >= 200 && $statusCode < 300,
        'statusCode' => $statusCode,
        'body' => $response,
    ];
}

try {
    requireRole(['Admin', 'Manager']);
    requirePost();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $title = trim((string) ($input['title'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $type = trim((string) ($input['type'] ?? 'system'));
    $severity = trim((string) ($input['severity'] ?? 'info'));
    $link = trim((string) ($input['link'] ?? ''));
    $scope = trim((string) ($input['target_scope'] ?? 'students'));
    $payload = $input['payload'] ?? null;
    $requestedUserIds = $input['user_ids'] ?? [];

    if (!is_array($requestedUserIds)) {
        $requestedUserIds = preg_split('/[\s,]+/', (string) $requestedUserIds) ?: [];
    }

    if ($title === '' || $message === '') {
        throw new InvalidArgumentException('Tiêu đề và nội dung thông báo là bắt buộc.');
    }

    $targetUserIds = pushNotificationResolveUserIds($conn, $scope, $requestedUserIds);
    if (!$targetUserIds) {
        throw new RuntimeException('Không có người nhận phù hợp.');
    }

    $rows = fanOutNotificationToUsers($conn, $targetUserIds, [
        'Title' => $title,
        'Message' => $message,
        'Type' => $type,
        'Severity' => $severity,
        'Link' => $link !== '' ? $link : null,
        'PayloadJson' => $payload,
    ]);

    $publishResult = pushNotificationPublish($targetUserIds, [
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'severity' => $severity,
        'link' => $link,
    ]);

    echo json_encode([
        'ok' => true,
        'createdCount' => count($rows),
        'targetUserIds' => $targetUserIds,
        'publish' => $publishResult,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
