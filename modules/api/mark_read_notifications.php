<?php
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';

if (empty($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['UserID'];

try {
    /* GET STUDENT ID */
    $stmt = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || !$res->num_rows) {
        echo json_encode(['ok' => false, 'msg' => 'Not a student']);
        exit;
    }

    $studentId = (int)$res->fetch_assoc()['StudentID'];
    $stmt->close();


    /* MARK INVOICE READ */
    $sql1 = "
        INSERT INTO InvoiceNotificationReads (InvoiceID, StudentID)
    SELECT i.InvoiceID, ?
    FROM Invoices i
    INNER JOIN Contracts c ON i.ContractID = c.ContractID
    LEFT JOIN InvoiceNotificationReads r 
    ON r.InvoiceID = i.InvoiceID AND r.StudentID = ?
    WHERE c.StudentID = ?
        AND i.Status = 'Chưa thanh toán'
        AND r.InvoiceID IS NULL )";

    $stmt = $conn->prepare($sql1);
    if (!$stmt) {
        throw new Exception("SQL1 ERROR: " . $conn->error);
    }

    $stmt->bind_param('iii', $studentId, $studentId, $studentId);
    $stmt->execute();
    $stmt->close();


    /* MARK ANNOUNCEMENT READ */
    $sql2 = "
        INSERT INTO AnnouncementViews (AnnouncementID, StudentID, ViewedAt)
        SELECT a.AnnouncementID, ?, NOW()
        FROM announcements a
        WHERE NOT EXISTS (
            SELECT 1 FROM AnnouncementViews v 
            WHERE v.AnnouncementID = a.AnnouncementID 
              AND v.StudentID = ?
        )
    ";

    $stmt = $conn->prepare($sql2);
    if (!$stmt) {
        throw new Exception("SQL2 ERROR: " . $conn->error);
    }

    $stmt->bind_param('ii', $studentId, $studentId);
    $stmt->execute();
    $stmt->close();


    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
