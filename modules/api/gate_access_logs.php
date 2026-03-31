<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';

try {
    requireRole(['Admin', 'Manager']);

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    $limit = max(1, min(200, $limit));

    $sql = "
        SELECT l.LogID, l.GateName, l.Direction, l.Status, l.RejectReason, l.CreatedAt,
               s.StudentID, s.StudentCode, s.FullName,
               u.FullName AS ScannedByName
        FROM gate_access_logs l
        INNER JOIN students s ON s.StudentID = l.StudentID
        LEFT JOIN users u ON u.UserID = l.ScannedByUserID
        WHERE 1 = 1
    ";
    $params = [];
    $types = '';

    if (!empty($_GET['student_search'])) {
        $sql .= ' AND (s.StudentCode LIKE ? OR s.FullName LIKE ?)';
        $keyword = '%' . trim((string) $_GET['student_search']) . '%';
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= 'ss';
    }

    if (!empty($_GET['status'])) {
        $sql .= ' AND l.Status = ?';
        $params[] = trim((string) $_GET['status']);
        $types .= 's';
    }

    if (!empty($_GET['gate_name'])) {
        $sql .= ' AND l.GateName = ?';
        $params[] = trim((string) $_GET['gate_name']);
        $types .= 's';
    }

    $sql .= ' ORDER BY l.CreatedAt DESC, l.LogID DESC LIMIT ?';
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Không thể đọc log cổng.');
    }

    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
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
