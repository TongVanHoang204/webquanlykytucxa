<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

/** Chỉ Admin & Manager được sử dụng API này */
requireRole(['Admin', 'Manager']);

header('Content-Type: application/json; charset=utf-8');

/* ========== 1. Kiểm tra method ========== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

/* ========== 2. Kiểm tra CSRF ========== */
$csrfPost    = $_POST['_csrf'] ?? '';
$csrfSession = $_SESSION['_csrf'] ?? '';

if (!$csrfPost || !$csrfSession || !hash_equals($csrfSession, $csrfPost)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'CSRF token không hợp lệ. Vui lòng tải lại trang.'
    ]);
    exit;
}

/* ========== 3. Lấy thông tin cơ bản ========== */
$userID       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$currentUserID = (int)($_SESSION['UserID'] ?? 0);
$currentRole   = $_SESSION['Role'] ?? '';

if ($userID <= 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'ID người dùng không hợp lệ.'
    ]);
    exit;
}

/* Không cho tự xóa chính mình */
if ($userID === $currentUserID) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Bạn không thể tự xóa chính mình.'
    ]);
    exit;
}

/* ========== 4. Lấy thông tin user mục tiêu ========== */
$stmt = $conn->prepare("
    SELECT UserID, FullName, Role 
    FROM Users 
    WHERE UserID = ? 
    LIMIT 1
");
$stmt->bind_param('i', $userID);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Người dùng không tồn tại.'
    ]);
    exit;
}

$user       = $result->fetch_assoc();
$targetRole = $user['Role'];
$targetName = $user['FullName'];

/* Manager không được xoá Admin */
if ($currentRole === 'Manager' && $targetRole === 'Admin') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Bạn không có quyền xóa tài khoản Admin.'
    ]);
    exit;
}

/* Manager không được xóa Manager khác */
if ($currentRole === 'Manager' && $targetRole === 'Manager') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Bạn không có quyền xóa tài khoản Manager khác.'
    ]);
    exit;
}

/* ========== 5. Kiểm tra có phải sinh viên không ========== */
$stmt = $conn->prepare("
    SELECT StudentID 
    FROM Students 
    WHERE UserID = ? 
    LIMIT 1
");
$stmt->bind_param('i', $userID);
$stmt->execute();
$stuRes = $stmt->get_result();
$stmt->close();

/* Nếu không phải sinh viên -> xóa thẳng user */
if ($stuRes->num_rows === 0) {
    $stmt = $conn->prepare("DELETE FROM Users WHERE UserID = ?");
    $stmt->bind_param('i', $userID);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'status'  => 'success',
            'message' => "Đã xóa người dùng '{$targetName}' (không liên kết sinh viên)."
        ]);
    } else {
        $stmt->close();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Lỗi khi xóa người dùng (ràng buộc dữ liệu).'
        ]);
    }
    exit;
}

/* ========== 6. Nếu là sinh viên: xử lý ràng buộc ========== */
$stu       = $stuRes->fetch_assoc();
$studentId = (int)$stu['StudentID'];

/* Kiểm tra hợp đồng đang hiệu lực */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM Contracts
    WHERE StudentID = ? AND Status = 'Hiệu lực'
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$cntRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!empty($cntRow['cnt']) && $cntRow['cnt'] > 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => "Không thể xóa sinh viên '{$targetName}' vì đang có hợp đồng hiệu lực."
    ]);
    exit;
}

/* ========== 7. Không còn hợp đồng hiệu lực -> xóa liên quan trong transaction ========== */
$conn->begin_transaction();

try {
    // Xóa phản ánh (Feedbacks)
    if ($conn->query("SHOW TABLES LIKE 'Feedbacks'")->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM Feedbacks WHERE StudentID = ?");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $stmt->close();
    }

    // Xóa Payments (nếu có bảng)
    if ($conn->query("SHOW TABLES LIKE 'Payments'")->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM Payments WHERE StudentID = ?");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $stmt->close();
    }

    // Xóa hóa đơn theo hợp đồng của sinh viên (nếu có Invoices)
    if ($conn->query("SHOW TABLES LIKE 'Invoices'")->num_rows > 0) {
        $sqlInvoices = "
            DELETE i FROM Invoices i
            JOIN Contracts c ON i.ContractID = c.ContractID
            WHERE c.StudentID = {$studentId}
        ";
        $conn->query($sqlInvoices);
    }

    // Xóa hợp đồng (nếu còn)
    if ($conn->query("SHOW TABLES LIKE 'Contracts'")->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM Contracts WHERE StudentID = ?");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $stmt->close();
    }

    // Xóa bản ghi sinh viên
    $stmt = $conn->prepare("DELETE FROM Students WHERE StudentID = ?");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $stmt->close();

    // Cuối cùng: xóa user
    $stmt = $conn->prepare("DELETE FROM Users WHERE UserID = ?");
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => "Đã xóa sinh viên & người dùng '{$targetName}' cùng toàn bộ dữ liệu liên quan."
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Đã xảy ra lỗi khi xóa. Vui lòng thử lại.'
    ]);
}

exit;
