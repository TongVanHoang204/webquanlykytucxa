<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';

try {
    requireRole(['Admin', 'Manager']);

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
    $limit = max(1, min(100, $limit));

    $sql = "
        SELECT c.CampaignID, c.Subject, c.AudienceType, c.Status, c.TotalRecipients, c.SuccessCount, c.FailCount,
               c.CreatedAt, c.SentAt, u.FullName AS CreatedByName
        FROM email_campaigns c
        LEFT JOIN users u ON u.UserID = c.CreatedByUserID
        ORDER BY c.CreatedAt DESC, c.CampaignID DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn chiến dịch email.');
    }

    $stmt->bind_param('i', $limit);
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
