<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/gate_qr_service.php';
require_once __DIR__ . '/../../includes/notification_service.php';

try {
    requireRole(['Admin', 'Manager']);
    requirePost();
    requireCsrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $token = trim((string) ($input['token'] ?? ''));
    $gateName = trim((string) ($input['gate_name'] ?? 'Cổng chính'));
    $direction = trim((string) ($input['direction'] ?? ''));

    if ($token === '') {
        throw new InvalidArgumentException('Thiếu token QR.');
    }

    $validation = validateGateQrToken($conn, $token);
    if (!$validation['valid']) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => strtoupper((string) ($validation['reason'] ?? 'INVALID_TOKEN')),
            'validation' => $validation,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $scan = registerGateScan(
        $conn,
        (int) $validation['StudentID'],
        $token,
        (int) ($_SESSION['UserID'] ?? 0),
        $gateName !== '' ? $gateName : 'Cổng chính',
        $direction !== '' ? $direction : null
    );

    if (!empty($scan['ok'])) {
        $student = gateQrLoadStudent($conn, (int) $validation['StudentID']);
        if ($student && !empty($student['UserID'])) {
            createUserNotification($conn, [
                'UserID' => (int) $student['UserID'],
                'Title' => 'Đã quét QR cổng',
                'Message' => sprintf('Bạn vừa %s tại %s.', ($scan['direction'] ?? 'IN') === 'IN' ? 'vào ký túc xá' : 'ra khỏi ký túc xá', $scan['gateName'] ?? $gateName),
                'Type' => 'access',
                'Severity' => 'info',
                'PayloadJson' => [
                    'direction' => $scan['direction'] ?? null,
                    'gate_name' => $scan['gateName'] ?? $gateName,
                    'log_id' => $scan['logId'] ?? null,
                ],
            ]);
        }
    }

    http_response_code(!empty($scan['ok']) ? 200 : 422);
    echo json_encode($scan, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
