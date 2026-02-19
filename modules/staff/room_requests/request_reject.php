<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

$conn->set_charset('utf8mb4');

$currentUserId = (int)($_SESSION['UserID'] ?? 0);
$requestId     = (int)($_GET['id'] ?? 0);

if ($requestId <= 0) {
    $_SESSION['message'] = 'Yêu cầu không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}

try {
    $conn->begin_transaction();

    /**
     * 1. Lấy thông tin yêu cầu
     */
    $sql = "
        SELECT 
            rr.RequestID,
            rr.StudentID,
            rr.Status
        FROM roomrequests rr
        WHERE rr.RequestID = ?
        LIMIT 1
    ";

    $stm = $conn->prepare($sql);
    if (!$stm) {
        throw new Exception('Lỗi prepare select: ' . $conn->error);
    }
    $stm->bind_param('i', $requestId);
    $stm->execute();
    $res = $stm->get_result();

    if ($res->num_rows <= 0) {
        $stm->close();
        throw new Exception('Yêu cầu không tồn tại.');
    }

    $row = $res->fetch_assoc();
    $studentId = (int)$row['StudentID'];
    $currentStatus = $row['Status'];
    $stm->close();

    // Chỉ cho phép từ chối nếu đang ở trạng thái "Chờ duyệt"
    if ($currentStatus !== 'Chờ duyệt') {
        throw new Exception('Chỉ có thể từ chối yêu cầu đang chờ duyệt.');
    }

    /**
     * 2. Cập nhật trạng thái yêu cầu thành "Từ chối"
     */
    $sqlUpdate = "
        UPDATE roomrequests
        SET Status = 'Từ chối'
        WHERE RequestID = ?
    ";

    $stm = $conn->prepare($sqlUpdate);
    if (!$stm) {
        throw new Exception('Lỗi prepare update: ' . $conn->error);
    }
    $stm->bind_param('i', $requestId);
    $stm->execute();

    if ($stm->affected_rows <= 0) {
        throw new Exception('Không thể cập nhật yêu cầu.');
    }
    $stm->close();

    $conn->commit();

    $_SESSION['message'] = '✓ Đã từ chối yêu cầu.';
    $_SESSION['message_type'] = 'success';

} catch (Exception $ex) {
    $conn->rollback();
    $_SESSION['message'] = '❌ Lỗi khi từ chối yêu cầu: ' . $ex->getMessage();
    $_SESSION['message_type'] = 'error';
}

header('Location: staff_request_list.php?rejected=1');
exit;
