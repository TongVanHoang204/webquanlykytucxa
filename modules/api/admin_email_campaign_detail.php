<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';

try {
    requireRole(['Admin', 'Manager']);

    $campaignId = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : (int) ($_GET['id'] ?? 0);
    if ($campaignId <= 0) {
        throw new InvalidArgumentException('Thiếu campaign_id hợp lệ.');
    }

    $campaignStmt = $conn->prepare("
        SELECT CampaignID, Subject, BodyHtml, AudienceType, Status, TotalRecipients, SuccessCount, FailCount, CreatedAt, SentAt
        FROM email_campaigns
        WHERE CampaignID = ?
        LIMIT 1
    ");
    if (!$campaignStmt) {
        throw new RuntimeException('Không thể đọc chiến dịch email.');
    }

    $campaignStmt->bind_param('i', $campaignId);
    $campaignStmt->execute();
    $campaignResult = $campaignStmt->get_result();
    $campaign = $campaignResult instanceof mysqli_result ? $campaignResult->fetch_assoc() : null;
    $campaignStmt->close();

    if (!$campaign) {
        throw new InvalidArgumentException('Không tìm thấy chiến dịch email.');
    }

    $recipientStmt = $conn->prepare("
        SELECT r.RecipientID, r.UserID, r.Email, r.SendStatus, r.ErrorMessage, r.SentAt, u.FullName, u.Role
        FROM email_campaign_recipients r
        LEFT JOIN users u ON u.UserID = r.UserID
        WHERE r.CampaignID = ?
        ORDER BY r.RecipientID ASC
    ");
    if (!$recipientStmt) {
        throw new RuntimeException('Không thể đọc danh sách người nhận.');
    }

    $recipientStmt->bind_param('i', $campaignId);
    $recipientStmt->execute();
    $recipientResult = $recipientStmt->get_result();
    $recipients = [];
    while ($recipientResult instanceof mysqli_result && ($row = $recipientResult->fetch_assoc())) {
        $recipients[] = $row;
    }
    $recipientStmt->close();

    echo json_encode([
        'ok' => true,
        'campaign' => $campaign,
        'recipients' => $recipients,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
