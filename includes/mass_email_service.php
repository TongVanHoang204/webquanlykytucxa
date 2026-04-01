<?php

if (!function_exists('massEmailNormalizeBody')) {
    function massEmailNormalizeBody(string $bodyHtml): string
    {
        $plain = html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace("/\r?\n\s*\r?\n/", "\n\n", $plain);
        return trim((string) $plain);
    }
}

if (!function_exists('massEmailHeaderValue')) {
    function massEmailHeaderValue(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B');
        }

        return $value;
    }
}

if (!function_exists('massEmailBuildHeaders')) {
    function massEmailBuildHeaders(string $fromEmail = 'no-reply@localhost'): string
    {
        return implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromEmail,
        ]);
    }
}

if (!function_exists('resolveCampaignRecipients')) {
    function resolveCampaignRecipients(mysqli $conn, string $audienceType): array
    {
        $audienceType = trim($audienceType);
        switch ($audienceType) {
            case 'students':
                $sql = "SELECT UserID, FullName, Email, Role FROM users WHERE Role = 'Student' AND IsActive = 1 AND Email IS NOT NULL AND Email <> '' ORDER BY UserID ASC";
                break;
            case 'staff_admin':
                $sql = "SELECT UserID, FullName, Email, Role FROM users WHERE Role IN ('Admin', 'Manager') AND IsActive = 1 AND Email IS NOT NULL AND Email <> '' ORDER BY UserID ASC";
                break;
            default:
                throw new InvalidArgumentException('Unsupported audience type.');
        }

        $result = $conn->query($sql);
        if (!$result instanceof mysqli_result) {
            throw new RuntimeException('Unable to resolve campaign recipients.');
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[(int) $row['UserID']] = $row;
        }

        return array_values($rows);
    }
}

if (!function_exists('createEmailCampaign')) {
    function createEmailCampaign(mysqli $conn, string $subject, string $bodyHtml, string $audienceType, int $createdByUserId, string $status = 'draft'): int
    {
        $stmt = $conn->prepare(
            'INSERT INTO email_campaigns (Subject, BodyHtml, AudienceType, CreatedByUserID, Status) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare email campaign insert.');
        }

        $stmt->bind_param('sssis', $subject, $bodyHtml, $audienceType, $createdByUserId, $status);
        if (!$stmt->execute()) {
            $error = $stmt->error ?: 'Unknown campaign insert failure.';
            $stmt->close();
            throw new RuntimeException($error);
        }
        $campaignId = (int) $stmt->insert_id;
        $stmt->close();
        if ($campaignId <= 0) {
            throw new RuntimeException('Email campaign insert did not return an ID.');
        }

        return $campaignId;
    }
}

if (!function_exists('massEmailRecordRecipient')) {
    function massEmailRecordRecipient(mysqli $conn, int $campaignId, int $userId, string $email, string $status, ?string $errorMessage = null): void
    {
        $sentAtExpression = $status === 'sent' ? 'NOW()' : 'NULL';
        $sql = "INSERT INTO email_campaign_recipients (CampaignID, UserID, Email, SendStatus, ErrorMessage, SentAt)
                VALUES (?, ?, ?, ?, ?, {$sentAtExpression})
                ON DUPLICATE KEY UPDATE
                    Email = VALUES(Email),
                    SendStatus = VALUES(SendStatus),
                    ErrorMessage = VALUES(ErrorMessage),
                    SentAt = VALUES(SentAt)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare campaign recipient insert.');
        }

        $stmt->bind_param('iisss', $campaignId, $userId, $email, $status, $errorMessage);
        if (!$stmt->execute()) {
            $error = $stmt->error ?: 'Unknown campaign recipient insert failure.';
            $stmt->close();
            throw new RuntimeException($error);
        }
        $stmt->close();
    }
}

if (!function_exists('sendCampaignNow')) {
    function sendCampaignNow(mysqli $conn, int $campaignId, array $recipients, string $subject, string $bodyHtml): array
    {
        $headers = massEmailBuildHeaders();
        $htmlBody = trim($bodyHtml);
        if ($htmlBody === '') {
            $htmlBody = nl2br(htmlspecialchars(massEmailNormalizeBody($bodyHtml), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($recipients as $recipient) {
            $userId = (int) ($recipient['UserID'] ?? 0);
            $email = trim((string) ($recipient['Email'] ?? ''));
            if ($userId <= 0 || $email === '') {
                $failCount++;
                continue;
            }

            $sent = @mail($email, massEmailHeaderValue($subject), $htmlBody, $headers);
            if ($sent) {
                $successCount++;
                massEmailRecordRecipient($conn, $campaignId, $userId, $email, 'sent', null);
                $results[] = ['UserID' => $userId, 'Email' => $email, 'Status' => 'sent'];
                continue;
            }

            $failCount++;
            $error = 'mail() returned false';
            massEmailRecordRecipient($conn, $campaignId, $userId, $email, 'failed', $error);
            $results[] = ['UserID' => $userId, 'Email' => $email, 'Status' => 'failed', 'ErrorMessage' => $error];
        }

        $finalStatus = 'completed';
        if ($successCount === 0 && $failCount > 0) {
            $finalStatus = 'failed';
        } elseif ($failCount > 0) {
            $finalStatus = 'partial';
        }

        $stmt = $conn->prepare(
            'UPDATE email_campaigns SET Status = ?, TotalRecipients = ?, SuccessCount = ?, FailCount = ?, SentAt = NOW() WHERE CampaignID = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare campaign update.');
        }

        $totalRecipients = count($recipients);
        $stmt->bind_param('siiii', $finalStatus, $totalRecipients, $successCount, $failCount, $campaignId);
        if (!$stmt->execute()) {
            $error = $stmt->error ?: 'Unknown campaign summary update failure.';
            $stmt->close();
            throw new RuntimeException($error);
        }
        $stmt->close();

        return [
            'CampaignID' => $campaignId,
            'Status' => $finalStatus,
            'TotalRecipients' => $totalRecipients,
            'SuccessCount' => $successCount,
            'FailCount' => $failCount,
            'Recipients' => $results,
        ];
    }
}

if (!function_exists('massEmailFailCampaign')) {
    function massEmailFailCampaign(mysqli $conn, int $campaignId, ?string $errorMessage = null): void
    {
        $stmt = $conn->prepare(
            'UPDATE email_campaigns SET Status = ?, SentAt = NOW() WHERE CampaignID = ?'
        );
        if (!$stmt) {
            return;
        }

        $status = 'failed';
        $stmt->bind_param('si', $status, $campaignId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('sendImmediateMassEmail')) {
    function sendImmediateMassEmail(mysqli $conn, string $subject, string $bodyHtml, string $audienceType, int $createdByUserId): array
    {
        $campaignId = 0;

        try {
            $campaignId = createEmailCampaign($conn, $subject, $bodyHtml, $audienceType, $createdByUserId, 'sending');
            $recipients = resolveCampaignRecipients($conn, $audienceType);

            if (!$recipients) {
                $stmt = $conn->prepare(
                    'UPDATE email_campaigns SET Status = ?, TotalRecipients = 0, SuccessCount = 0, FailCount = 0, SentAt = NOW() WHERE CampaignID = ?'
                );
                if ($stmt) {
                    $status = 'completed';
                    $stmt->bind_param('si', $status, $campaignId);
                    if (!$stmt->execute()) {
                        $error = $stmt->error ?: 'Unknown empty campaign completion failure.';
                        $stmt->close();
                        throw new RuntimeException($error);
                    }
                    $stmt->close();
                }

                return [
                    'CampaignID' => $campaignId,
                    'Status' => 'completed',
                    'TotalRecipients' => 0,
                    'SuccessCount' => 0,
                    'FailCount' => 0,
                    'Recipients' => [],
                ];
            }

            return sendCampaignNow($conn, $campaignId, $recipients, $subject, $bodyHtml);
        } catch (Throwable $e) {
            if ($campaignId > 0) {
                massEmailFailCampaign($conn, $campaignId, $e->getMessage());
            }

            return [
                'CampaignID' => $campaignId > 0 ? $campaignId : null,
                'Status' => 'failed',
                'TotalRecipients' => 0,
                'SuccessCount' => 0,
                'FailCount' => 0,
                'ErrorMessage' => $e->getMessage(),
                'Recipients' => [],
            ];
        }
    }
}
