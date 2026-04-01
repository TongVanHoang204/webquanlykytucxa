<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/SimpleXLSX.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';
require_once __DIR__ . '/student_import_helpers.php';

requireRole(['Admin', 'Manager']);
requirePost();
requireCsrf();

if (empty($_POST['filename'])) {
    header('Location: student_import.php?error=InvalidRequest');
    exit;
}

$conn->set_charset('utf8mb4');

$fileName = basename((string)$_POST['filename']);
$filePath = '../../../uploads/imports/' . $fileName;

if (!is_file($filePath)) {
    header('Location: student_import.php?error=FileNotFound');
    exit;
}

$xlsx = Shuchkin\SimpleXLSX::parse($filePath);
if (!$xlsx) {
    @unlink($filePath);
    header('Location: student_import.php?error=ParseError');
    exit;
}

$analysis = studentImportAnalyzeRows($xlsx->rows(), studentImportBuildContext($conn));
$validRows = $analysis['validRows'];
$failCount = count($analysis['errorRows']);
$successCount = 0;

if (empty($validRows)) {
    @unlink($filePath);
    header("Location: student_list.php?msg=Imported&success=0&fail={$failCount}");
    exit;
}

$conn->begin_transaction();

try {
    $stmtUser = $conn->prepare("
        INSERT INTO Users (Username, FullName, PasswordHash, Email, Phone, Role, CreatedAt)
        VALUES (?, ?, ?, ?, ?, 'Student', NOW())
    ");
    $stmtStudent = $conn->prepare("
        INSERT INTO Students (
            UserID, StudentCode, FullName, Gender, FacultyID,
            ClassName, CourseYear, Phone, Email, Address, CreatedAt
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmtDeleteUser = $conn->prepare("DELETE FROM Users WHERE UserID = ?");

    if (!$stmtUser || !$stmtStudent || !$stmtDeleteUser) {
        throw new RuntimeException('Không thể chuẩn bị câu lệnh import sinh viên.');
    }

    foreach ($validRows as $row) {
        $passwordHash = password_hash($row['StudentCode'], PASSWORD_DEFAULT);

        $stmtUser->bind_param(
            'sssss',
            $row['StudentCode'],
            $row['FullName'],
            $passwordHash,
            $row['Email'],
            $row['Phone']
        );

        if (!$stmtUser->execute()) {
            $failCount++;
            continue;
        }

        $userID = (int)$conn->insert_id;

        $stmtStudent->bind_param(
            'isssisssss',
            $userID,
            $row['StudentCode'],
            $row['FullName'],
            $row['Gender'],
            $row['FacultyID'],
            $row['ClassName'],
            $row['CourseYear'],
            $row['Phone'],
            $row['Email'],
            $row['Address']
        );

        if ($stmtStudent->execute()) {
            $successCount++;
            continue;
        }

        $stmtDeleteUser->bind_param('i', $userID);
        $stmtDeleteUser->execute();
        $failCount++;
    }

    $conn->commit();
    logStudentAction(
        $conn,
        $_SESSION['UserID'] ?? null,
        'import',
        "Import sinh viên: thành công {$successCount}, lỗi {$failCount}, file {$fileName}",
        'activity'
    );
    @unlink($filePath);
} catch (Throwable $throwable) {
    $conn->rollback();
    logStudentAction(
        $conn,
        $_SESSION['UserID'] ?? null,
        'import_failed',
        "Import sinh viên thất bại từ file {$fileName}: " . $throwable->getMessage(),
        'warning'
    );
    @unlink($filePath);
    header('Location: student_import.php?error=SystemError');
    exit;
}

header("Location: student_list.php?msg=Imported&success={$successCount}&fail={$failCount}");
exit;
