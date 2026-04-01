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

    $userId = (int) ($_SESSION['UserID'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Phiên đăng nhập không hợp lệ.');
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $limit = max(1, min(50, $limit));

    $items = fetchUserNotifications($conn, $userId, $limit);
    $unreadCount = countUnreadUserNotifications($conn, $userId);

    echo json_encode([
        'ok' => true,
        'items' => $items,
        'unreadCount' => $unreadCount,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
