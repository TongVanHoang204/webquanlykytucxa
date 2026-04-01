<?php

if (!function_exists('notificationServiceNormalizePayload')) {
    function notificationServiceNormalizePayload($payload)
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (is_array($payload) || is_object($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $json === false ? null : $json;
        }

        return (string) $payload;
    }
}

if (!function_exists('notificationServiceDecodePayload')) {
    function notificationServiceDecodePayload(?string $payloadJson)
    {
        if ($payloadJson === null || $payloadJson === '') {
            return null;
        }

        $decoded = json_decode($payloadJson, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $payloadJson;
    }
}

if (!function_exists('createUserNotification')) {
    function createUserNotification(mysqli $conn, array $notification): int
    {
        $userId = (int) ($notification['UserID'] ?? 0);
        $title = trim((string) ($notification['Title'] ?? ''));
        $message = trim((string) ($notification['Message'] ?? ''));
        $type = trim((string) ($notification['Type'] ?? 'system'));
        $severity = trim((string) ($notification['Severity'] ?? 'info'));
        $link = $notification['Link'] ?? null;
        $payloadJson = notificationServiceNormalizePayload($notification['PayloadJson'] ?? null);

        if ($userId <= 0) {
            throw new InvalidArgumentException('Missing UserID for notification.');
        }
        if ($title === '') {
            throw new InvalidArgumentException('Missing notification title.');
        }
        if ($message === '') {
            throw new InvalidArgumentException('Missing notification message.');
        }

        $stmt = $conn->prepare(
            'INSERT INTO user_notifications (UserID, Title, Message, Type, Severity, Link, PayloadJson) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare notification insert.');
        }

        $stmt->bind_param(
            'issssss',
            $userId,
            $title,
            $message,
            $type,
            $severity,
            $link,
            $payloadJson
        );

        $stmt->execute();
        $insertId = (int) $stmt->insert_id;
        $stmt->close();

        return $insertId;
    }
}

if (!function_exists('fanOutNotificationToUsers')) {
    function fanOutNotificationToUsers(mysqli $conn, array $userIds, array $notification): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $results = [];

        foreach ($userIds as $userId) {
            $row = $notification;
            $row['UserID'] = $userId;
            $results[] = [
                'UserID' => $userId,
                'NotificationID' => createUserNotification($conn, $row),
            ];
        }

        return $results;
    }
}

if (!function_exists('fetchUserNotifications')) {
    function fetchUserNotifications(mysqli $conn, int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT NotificationID, UserID, Title, Message, Type, Severity, Link, PayloadJson, IsRead, CreatedAt, ReadAt
                FROM user_notifications
                WHERE UserID = ?';

        if ($unreadOnly) {
            $sql .= ' AND IsRead = 0';
        }

        $sql .= ' ORDER BY CreatedAt DESC, NotificationID DESC LIMIT ?';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare notification fetch query.');
        }

        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $row['Payload'] = notificationServiceDecodePayload($row['PayloadJson'] ?? null);
                $rows[] = $row;
            }
        }

        $stmt->close();
        return $rows;
    }
}

if (!function_exists('markUserNotificationRead')) {
    function markUserNotificationRead(mysqli $conn, int $userId, int $notificationId): bool
    {
        $stmt = $conn->prepare(
            'UPDATE user_notifications SET IsRead = 1, ReadAt = NOW() WHERE NotificationID = ? AND UserID = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare notification update.');
        }

        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();

        return $updated;
    }
}

if (!function_exists('markAllUserNotificationsRead')) {
    function markAllUserNotificationsRead(mysqli $conn, int $userId): int
    {
        $stmt = $conn->prepare(
            'UPDATE user_notifications SET IsRead = 1, ReadAt = NOW() WHERE UserID = ? AND IsRead = 0'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare notification bulk update.');
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return max(0, (int) $affected);
    }
}

if (!function_exists('countUnreadUserNotifications')) {
    function countUnreadUserNotifications(mysqli $conn, int $userId): int
    {
        $stmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM user_notifications WHERE UserID = ? AND IsRead = 0');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare unread count query.');
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = 0;
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $count = (int) ($row['unread_count'] ?? 0);
        }
        $stmt->close();

        return $count;
    }
}
