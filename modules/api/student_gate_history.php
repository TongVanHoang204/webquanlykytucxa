<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';

try {
    requireRole(['Student']);

    $userId = (int) ($_SESSION['UserID'] ?? 0);
    $sql = "
        SELECT l.LogID, l.GateName, l.Direction, l.Status, l.RejectReason, l.CreatedAt
        FROM gate_access_logs l
        INNER JOIN students s ON s.StudentID = l.StudentID
        WHERE s.UserID = ?
        ORDER BY l.CreatedAt DESC, l.LogID DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể đọc lịch sử ra/vào.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
