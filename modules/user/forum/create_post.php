<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/log_helper.php';

$userId = $_SESSION['UserID'] ?? 0;
// Lấy thẻ sinh viên nếu được
$studentId = 0;

if ($userId > 0) {
    if (!empty($_SESSION['StudentID'])) {
        $studentId = (int)$_SESSION['StudentID'];
    } else {
        $stmt = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $studentId = (int)$res->fetch_assoc()['StudentID'];
        }
        $stmt->close();
    }
}

// Nếu không phải Sinh viên thì không đăng được
if ($studentId === 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?msg=error_auth");
    exit;
}

$content = trim($_POST['content'] ?? '');
if (empty($content)) {
    header("Location: index.php?msg=empty_content");
    exit;
}

$imagePath = null;
if (!empty($_FILES['image']['name'])) {
    $uploadDir = '../../assets/uploads/posts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
    $targetFile = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        $imagePath = 'assets/uploads/posts/' . $filename;
    }
}

// Insert database
$sql = "INSERT INTO posts (StudentID, Content, ImagePath, Visibility, CreatedAt) VALUES (?, ?, ?, 'Công khai', NOW())";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iss", $studentId, $content, $imagePath);
    if ($stmt->execute()) {
        $postId = $stmt->insert_id;
        addLog(
            $conn,
            $userId,
            'Create Post',
            'Community',
            "Tạo bài đăng Mới mang ID={$postId}",
            'activity'
        );
        header("Location: index.php?msg=post_success");
        exit;
    }
}

header("Location: index.php?msg=post_failed");
exit;
