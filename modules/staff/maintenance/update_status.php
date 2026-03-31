<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/log_helper.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqId = (int)($_POST['reqId'] ?? 0);
    $reqStatus = $_POST['reqStatus'] ?? '';

    $validStatuses = ['Chờ xử lý', 'Đang xử lý', 'Đã hoàn thành', 'Đã hủy'];

    if ($reqId > 0 && in_array($reqStatus, $validStatuses)) {
        // Lấy thông tin sinh viên để gửi thông báo (tuỳ chọn thêm)
        $stmtInfo = $conn->prepare("SELECT StudentID, Title FROM maintenancerequests WHERE RequestID = ?");
        $stmtInfo->bind_param("i", $reqId);
        $stmtInfo->execute();
        $resInfo = $stmtInfo->get_result();
        $reqInfo = $resInfo->fetch_assoc();
        $stmtInfo->close();

        // Cập nhật trạng thái
        $stmt = $conn->prepare("UPDATE maintenancerequests SET Status = ? WHERE RequestID = ?");
        $stmt->bind_param("si", $reqStatus, $reqId);
        
        if ($stmt->execute()) {
            addLog(
                $conn,
                $_SESSION['UserID'] ?? null,
                'Update maintenance',
                'Maintenance',
                "Cập nhật trạng thái sự cố ID={$reqId} thành {$reqStatus}",
                'activity'
            );

            // Gửi thông báo cho hệ thống
            if ($reqInfo) {
                $studentId = $reqInfo['StudentID'];
                $notiTitle = "Cập nhật Yêu cầu Sửa chữa";
                $notiMsg = "Yêu cầu: '{$reqInfo['Title']}' của bạn đã được chuyển trạng thái sang [{$reqStatus}].";
                
                $notiSql = "INSERT INTO Notifications (StudentID, Title, Message, CreatedAt, IsRead) VALUES (?, ?, ?, NOW(), 0)";
                $notiStmt = $conn->prepare($notiSql);
                if ($notiStmt) {
                    $notiStmt->bind_param("iss", $studentId, $notiTitle, $notiMsg);
                    $notiStmt->execute();
                    $notiStmt->close();
                }
            }

            header("Location: index.php?msg=success");
            exit;
        } else {
            header("Location: index.php?msg=error");
            exit;
        }
    }
}

header("Location: index.php?msg=error");
exit;
