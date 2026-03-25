<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// User đang thực hiện
$userID = $_SESSION['UserID'] ?? null;

/**
 * GHI LOG HỆ THỐNG
 *
 * @param mysqli $conn
 * @param int|null $userID
 * @param string $action
 * @param string $module
 * @param string $description
 * @param string $type
 */
function addLog(mysqli $conn, ?int $userID, string $action, string $module, string $description = '', string $type = 'activity')
{
    if (!isset($conn)) return;

    $ip = $_SERVER['REMOTE_ADDR']     ?? 'UNKNOWN';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $sql = "
        INSERT INTO SystemLogs (UserID, Action, Module, Description, LogType, IPAddress, UserAgent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return;

    $stmt->bind_param("issssss", $userID, $action, $module, $description, $type, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}

function logBuildingAction(mysqli $conn, ?int $userID, string $verb, string $description, string $type = 'activity'): void
{
    addLog($conn, $userID, 'building.' . $verb, 'Buildings', $description, $type);
}

function logRoomAction(mysqli $conn, ?int $userID, string $verb, string $description, string $type = 'activity'): void
{
    addLog($conn, $userID, 'room.' . $verb, 'Rooms', $description, $type);
}

function logContractAction(mysqli $conn, ?int $userID, string $verb, string $description, string $type = 'activity'): void
{
    addLog($conn, $userID, 'contract.' . $verb, 'Contracts', $description, $type);
}

function logStudentAction(mysqli $conn, ?int $userID, string $verb, string $description, string $type = 'activity'): void
{
    addLog($conn, $userID, 'student.' . $verb, 'Students', $description, $type);
}


/**
 * GỬI THÔNG BÁO CHO NGƯỜI DÙNG
 */
function addNotification(mysqli $conn, int $targetUserID, string $title, string $message, string $type = 'general', ?string $link = null)
{
    if (!isset($conn)) return;

    $sql = "
        INSERT INTO Notifications (UserID, Title, Message, Type, Link, IsRead, CreatedAt)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return;

    $stmt->bind_param("issss", $targetUserID, $title, $message, $type, $link);
    $stmt->execute();
    $stmt->close();
}


/**
 * VỪA LOG VỪA NOTIFY (TIỆN DỤNG)
 */
function logAndNotify(
    mysqli $conn,
    ?int $actorUserID,
    string $action,
    string $module,
    string $logDescription = '',
    string $logType = 'activity',
    ?int $targetUserID = null,
    ?string $notifyTitle = null,
    ?string $notifyMessage = null,
    string $notifyType = 'general',
    ?string $notifyLink = null
) {
    // Ghi log
    addLog($conn, $actorUserID, $action, $module, $logDescription, $logType);

    // Nếu có người nhận thông báo → gửi notify
    if ($targetUserID !== null && $notifyTitle !== null && $notifyMessage !== null) {
        addNotification($conn, $targetUserID, $notifyTitle, $notifyMessage, $notifyType, $notifyLink);
    }
}
