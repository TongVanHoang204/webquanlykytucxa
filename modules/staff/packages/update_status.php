<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/log_helper.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pkgId = (int)($_POST['packageId'] ?? 0);
    $newStatus = trim($_POST['newStatus'] ?? '');

    $allowedStatuses = ['Đã nhận', 'Đã hoàn trả'];

    if ($pkgId > 0 && in_array($newStatus, $allowedStatuses)) {
        // Chỉ update nếu đang ở "Chờ lấy"
        $sqlCh = "SELECT Status, SenderInfo FROM packages WHERE PackageID = ?";
        $stmtC = $conn->prepare($sqlCh);
        $stmtC->bind_param("i", $pkgId);
        $stmtC->execute();
        $resC = $stmtC->get_result();
        $pkgInfo = $resC->fetch_assoc();
        $stmtC->close();

        if ($pkgInfo && $pkgInfo['Status'] === 'Chờ lấy') {
            
            $sqlUp = "UPDATE packages SET Status = ?";
            if ($newStatus === 'Đã nhận') {
                $sqlUp .= ", DeliveredAt = NOW()";
            }
            $sqlUp .= " WHERE PackageID = ?";

            $stmtU = $conn->prepare($sqlUp);
            $stmtU->bind_param("si", $newStatus, $pkgId);
            if ($stmtU->execute()) {
                addLog(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'Package Status',
                    'Packages',
                    "Chuyển trạng thái bưu kiện ID={$pkgId} sang [{$newStatus}]",
                    'activity'
                );
                
                header("Location: index.php?msg=success");
                exit;
            }
        }
    }
}

header("Location: index.php?msg=error");
exit;
