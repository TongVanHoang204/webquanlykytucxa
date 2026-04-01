<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/log_helper.php';

$userId = $_SESSION['UserID'] ?? 0;
// Check Role
$isAdmin = isset($_SESSION['Role']) && in_array($_SESSION['Role'], ['Admin', 'Manager']);
$studentId = 0;

if (!$isAdmin && $userId > 0) {
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

$postId = (int)($_GET['post_id'] ?? 0);

if ($postId > 0 && ($studentId > 0 || $isAdmin)) {
    // Kiem tra thuoc tinh va hinh anh
    $imagePath = '';
    $sqlCheck = "SELECT StudentID, ImagePath FROM posts WHERE PostID = ?";
    $stmtCh = $conn->prepare($sqlCheck);
    $stmtCh->bind_param("i", $postId);
    $stmtCh->execute();
    $resCh = $stmtCh->get_result();
    $postData = $resCh->fetch_assoc();
    $stmtCh->close();

    if ($postData) {
        $ownerId = (int)$postData['StudentID'];
        $imagePath = $postData['ImagePath'];
        
        if ($isAdmin || $studentId === $ownerId) {
            // Tien hanh xoa
            // Do khoa ngoai cua postcomments/postlikes chua chac setup CASCADE DELETE, ta phai xoa tay
            $conn->query("DELETE FROM postlikes WHERE PostID = $postId");
            $conn->query("DELETE FROM postcomments WHERE PostID = $postId");
            
            // Xoa post chinh
            $sqlDel = "DELETE FROM posts WHERE PostID = ?";
            $stmtD = $conn->prepare($sqlDel);
            $stmtD->bind_param("i", $postId);
            if ($stmtD->execute()) {
                // Xoa anh that
                if (!empty($imagePath) && file_exists('../../' . $imagePath)) {
                    @unlink('../../' . $imagePath);
                }
                
                addLog(
                    $conn,
                    $userId,
                    'Delete Post',
                    'Community',
                    "Xóa bài đăng ID={$postId} trên Chợ Sinh Viên",
                    'activity'
                );

                header("Location: index.php?msg=deleted");
                exit;
            }
        }
    }
}

header("Location: index.php?msg=del_error");
exit;
