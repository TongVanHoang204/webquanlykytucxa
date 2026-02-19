<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// Chưa đăng nhập -> trả mảng rỗng
if (empty($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$userId = (int)$_SESSION['UserID'];
$role   = $_SESSION['Role'] ?? '';
$notifications = [];

try {
    // 🔹 Lấy StudentID
    $studentId = 0;
    $stmt = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($row = $r->fetch_assoc()) {
        $studentId = (int)$row['StudentID'];
    }
    $stmt->close();

    if ($studentId > 0) {
        // 🔹 Lấy thông báo từ hóa đơn chưa thanh toán
        $sql = "
            SELECT 
                'invoice' AS Type,
                i.InvoiceID AS ID,
                CONCAT('Hóa đơn tháng ', i.Month, '/', i.Year) AS Title,
                CONCAT(
                    'Bạn có hóa đơn ',
                    FORMAT(i.TotalAmount, 0), ' đ chưa thanh toán. ',
                    'Hạn thanh toán: ', DATE_FORMAT(i.DueDate, '%d/%m/%Y')
                ) AS Message,
                i.DueDate AS CreatedAt,
                CONCAT('/modules/user/bills/bill_detail.php?id=', i.InvoiceID) AS Link,
                IF(inr.ReadID IS NULL, 0, 1) AS IsRead
            FROM Invoices i
            INNER JOIN Contracts c ON i.ContractID = c.ContractID
            LEFT JOIN InvoiceNotificationReads inr ON i.InvoiceID = inr.InvoiceID AND inr.StudentID = ?
            WHERE c.StudentID = ?
              AND i.Status = 'Chưa thanh toán'
              AND i.DueDate >= CURDATE()
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentId, $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
    }

    // 🔹 Lấy thông báo từ bảng announcements
    $sqlAnnouncement = "
        SELECT 
            'announcement' AS Type,
            a.AnnouncementID AS ID,
            a.Title,
            SUBSTRING(a.Content, 1, 120) AS Message,
            a.DatePosted AS CreatedAt,
            CONCAT('/modules/user/accesslogs.php?view=', a.AnnouncementID) AS Link,
            IF(av.ViewID IS NULL, 0, 1) AS IsRead
        FROM announcements a
        LEFT JOIN AnnouncementViews av ON a.AnnouncementID = av.AnnouncementID AND av.StudentID = ?
        ORDER BY a.DatePosted DESC
        LIMIT 5
    ";
    
    $stmt = $conn->prepare($sqlAnnouncement);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();

    // Sắp xếp: Chưa đọc lên trước, sau đó theo thời gian mới nhất
    usort($notifications, function($a, $b) {
        if ($a['IsRead'] != $b['IsRead']) {
            return $a['IsRead'] - $b['IsRead'];
        }
        return strtotime($b['CreatedAt']) - strtotime($a['CreatedAt']);
    });

    echo json_encode($notifications);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
