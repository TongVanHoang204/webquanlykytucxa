<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/mass_email_service.php';
require_once __DIR__ . '/../../includes/notification_service.php';

try {
    requireRole(['Admin', 'Manager']);
    requirePost();
    requireCsrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $subject = trim((string) ($input['subject'] ?? ''));
    $bodyHtml = trim((string) ($input['body_html'] ?? ''));
    $audienceType = trim((string) ($input['audience_type'] ?? 'students'));

    if ($subject === '' || $bodyHtml === '') {
        throw new InvalidArgumentException('Tiêu đề và nội dung email là bắt buộc.');
    }

    if (!in_array($audienceType, ['students', 'staff_admin'], true)) {
        throw new InvalidArgumentException('Nhóm người nhận không hợp lệ.');
    }

    $result = sendImmediateMassEmail(
        $conn,
        $subject,
        $bodyHtml,
        $audienceType,
        (int) ($_SESSION['UserID'] ?? 0)
    );

    createUserNotification($conn, [
        'UserID' => (int) ($_SESSION['UserID'] ?? 0),
        'Title' => 'Mass email đã xử lý',
        'Message' => sprintf(
            'Chiến dịch #%s: %s thành công, %s thất bại.',
            (string) ($result['CampaignID'] ?? 'N/A'),
            (string) ($result['SuccessCount'] ?? 0),
            (string) ($result['FailCount'] ?? 0)
        ),
        'Type' => 'email',
        'Severity' => (($result['FailCount'] ?? 0) > 0 ? 'warning' : 'success'),
        'Link' => null,
        'PayloadJson' => [
            'campaign_id' => $result['CampaignID'] ?? null,
            'status' => $result['Status'] ?? 'unknown',
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
