<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/gate_qr_service.php';

try {
    requireRole(['Student']);

    $userId = (int) ($_SESSION['UserID'] ?? 0);
    $stmt = $conn->prepare('SELECT StudentID, StudentCode, FullName FROM students WHERE UserID = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Không thể tra cứu sinh viên.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$student) {
        throw new RuntimeException('Không tìm thấy hồ sơ sinh viên.');
    }

    $tokenPayload = issueStudentGateQrToken($conn, (int) $student['StudentID']);
    echo json_encode([
        'ok' => true,
        'student' => [
            'StudentID' => (int) $student['StudentID'],
            'StudentCode' => $student['StudentCode'] ?? '',
            'FullName' => $student['FullName'] ?? '',
        ],
        'token' => $tokenPayload['Token'],
        'expires_at' => $tokenPayload['ExpiresAt'],
        'expires_in_seconds' => $tokenPayload['ExpiresInSeconds'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
