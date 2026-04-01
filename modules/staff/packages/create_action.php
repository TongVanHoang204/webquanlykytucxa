<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/log_helper.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)($_POST['studentId'] ?? 0);
    $senderInfo = trim($_POST['senderInfo'] ?? '');
    $notes = trim($_POST['packageNotes'] ?? '');

    if ($studentId > 0 && !empty($senderInfo)) {
        // Luu DB
        $sql = "INSERT INTO packages (StudentID, SenderInfo, PackageNotes, Status, ReceivedAt) VALUES (?, ?, ?, 'Chờ lấy', NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iss", $studentId, $senderInfo, $notes);
            if ($stmt->execute()) {
                $pkgId = $stmt->insert_id;
                
                // Ghi log
                addLog(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'Receive Package',
                    'Packages',
                    "Nhận bưu phẩm mới [{$senderInfo}] cho Sinh viên ID={$studentId}",
                    'activity'
                );

                // Gửi Notification
                $notiTitle = "Bạn có Bưu phẩm mới từ: {$senderInfo}";
                $notiMsg = "Ban quản lý đã ghi nhận bưu kiện của bạn. Ghi chú dạng: " . ($notes ?: 'Không có') . ". Hãy xuống Cổng/Sảnh để nhận hàng nhé!";
                
                $notiSql = "INSERT INTO Notifications (StudentID, Title, Message, CreatedAt, IsRead) VALUES (?, ?, ?, NOW(), 0)";
                $notiStmt = $conn->prepare($notiSql);
                if ($notiStmt) {
                    $notiStmt->bind_param("iss", $studentId, $notiTitle, $notiMsg);
                    $notiStmt->execute();
                    $notiStmt->close();
                }

                header("Location: index.php?msg=success");
                exit;
            }
        }
    }
}

header("Location: index.php?msg=error");
exit;
