<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['UserID'] ?? 0;
// Tìm StudentID
$studentId = 0;
$studentName = 'Sinh viên';

if ($userId > 0) {
    $stmt = $conn->prepare("SELECT StudentID, FullName FROM Students WHERE UserID = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $r = $res->fetch_assoc();
            $studentId = (int)$r['StudentID'];
            $studentName = $r['FullName'];
            $_SESSION['StudentID'] = $studentId; // cache for next req
        }
        $stmt->close();
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Bạn phải đăng nhập tài khoản Sinh viên để bình luận']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if ($postId <= 0 || $content === '' || $studentId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nội dung bình luận quá ngắn hoặc ID sai.']);
    exit;
}

// Xử lý XSS nhẹ nhàng
$contentSafe = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

$sql = "INSERT INTO postcomments (PostID, StudentID, Content, CreatedAt) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iis", $postId, $studentId, $contentSafe);
    $res = $stmt->execute();
    if ($res) {
        $commentId = $stmt->insert_id;
        echo json_encode([
            'status' => 'success',
            'comment_id' => $commentId,
            'content' => nl2br($contentSafe),
            'author_name' => $studentName
        ]);
        exit;
    }
    $stmt->close();
}

echo json_encode(['status' => 'error', 'message' => 'Không thể thêm bình luận do lỗi DB']);
