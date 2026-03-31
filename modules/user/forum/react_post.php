<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['UserID'] ?? 0;
// Tìm StudentID
$studentId = 0;

if ($userId > 0) {
    if (!empty($_SESSION['StudentID'])) {
        $studentId = (int)$_SESSION['StudentID'];
    } else {
        $stmt = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $studentId = (int)$res->fetch_assoc()['StudentID'];
            }
            $stmt->close();
        }
    }
}

if ($studentId === 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$postId = (int)$_POST['post_id'] ?? 0;
if ($postId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid post']);
    exit;
}

// Kiểm tra xem đã like chưa
$sqlCheck = "SELECT 1 FROM postlikes WHERE PostID = ? AND StudentID = ?";
$stmt = $conn->prepare($sqlCheck);
$stmt->bind_param("ii", $postId, $studentId);
$stmt->execute();
$stmt->store_result();
$isLiked = $stmt->num_rows > 0;
$stmt->close();

if ($isLiked) {
    // Un-like
    $sqlUn = "DELETE FROM postlikes WHERE PostID = ? AND StudentID = ?";
    $stmtU = $conn->prepare($sqlUn);
    $stmtU->bind_param("ii", $postId, $studentId);
    $stmtU->execute();
    $stmtU->close();
    $action = 'unliked';
} else {
    // Like
    $sqlIn = "INSERT INTO postlikes (PostID, StudentID, Reaction, CreatedAt) VALUES (?, ?, 'Like', NOW())";
    $stmtI = $conn->prepare($sqlIn);
    $stmtI->bind_param("ii", $postId, $studentId);
    $stmtI->execute();
    $stmtI->close();
    $action = 'liked';
}

// Lấy tổng like mới
$totalLikes = 0;
$stmtC = $conn->prepare("SELECT COUNT(*) as t FROM postlikes WHERE PostID = ?");
$stmtC->bind_param("i", $postId);
$stmtC->execute();
$resC = $stmtC->get_result();
if ($resC && $resC->num_rows > 0) {
    $totalLikes = (int)$resC->fetch_assoc()['t'];
}
$stmtC->close();

echo json_encode([
    'status' => 'success',
    'action' => $action,
    'likes' => $totalLikes
]);
