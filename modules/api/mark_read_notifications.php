<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/notification_service.php';

try {
    requireLogin();
    requirePost();

    $userId = (int) ($_SESSION['UserID'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Phiên đăng nhập không hợp lệ.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $notificationId = isset($input['notification_id']) ? (int) $input['notification_id'] : 0;
    $markAll = !empty($input['mark_all']) || $notificationId <= 0;

    if ($markAll) {
        $marked = markAllUserNotificationsRead($conn, $userId);
    } else {
        $marked = markUserNotificationRead($conn, $userId, $notificationId) ? 1 : 0;
    }

    echo json_encode([
        'ok' => true,
        'marked' => $marked,
        'mode' => $markAll ? 'all' : 'single',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
