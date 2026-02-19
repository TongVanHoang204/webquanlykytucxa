<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/SimpleXLSX.php';
require_once '../../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['filename'])) {
    header('Location: student_import.php?error=InvalidRequest');
    exit;
}

$conn->set_charset('utf8mb4');

$fileName = basename($_POST['filename']);
$filePath = '../../../uploads/imports/' . $fileName;

if (!file_exists($filePath)) {
    header('Location: student_import.php?error=FileNotFound');
    exit;
}

$xlsx = Shuchkin\SimpleXLSX::parse($filePath);
if (!$xlsx) {
    header('Location: student_import.php?error=ParseError');
    exit;
}

$rows = $xlsx->rows();
$startIndex = 0;
if (!empty($rows) && (strtoupper($rows[0][0]) === 'MSSV' || strtoupper($rows[0][0]) === 'MASV')) {
    $startIndex = 1;
}

$successCount = 0;
$failCount = 0;
$errors = [];

// Prepare Insert Statement
// We need to also create a User account for the student (Default password: MSSV)
// Or maybe just create Student record and let them register?
// Usually, admin creates account. Let's create User account too.
// Default password = StudentCode

$conn->begin_transaction();

try {
    $stmtUser = $conn->prepare("INSERT INTO Users (Username, PasswordHash, Email, Role, CreatedAt) VALUES (?, ?, ?, 'Student', NOW())");
    $stmtStudent = $conn->prepare("INSERT INTO Students (UserID, StudentCode, FullName, Gender, FacultyID, ClassName, CourseYear, Phone, Email, Address, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $checkStmt = $conn->prepare("SELECT StudentID FROM Students WHERE StudentCode = ?");

    for ($i = $startIndex; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        // Clean data
        $studentCode = trim($row[0] ?? '');
        $fullName    = trim($row[1] ?? '');
        $gender      = trim($row[2] ?? '');
        $facultyName = trim($row[3] ?? '');
        $className   = trim($row[4] ?? '');
        $courseYear  = (int)($row[5] ?? 0);
        $phone       = trim($row[6] ?? '');
        $email       = trim($row[7] ?? '');
        $address     = trim($row[8] ?? '');

        // Validation
        if (empty($studentCode) || empty($fullName)) {
            $failCount++;
            continue;
        }
        
        // Strict Validation (Must match Preview)
        if (empty($gender) || !in_array($gender, ['Nam', 'Nữ'])) {
            $failCount++;
            continue;
        }
        if (empty($facultyName)) {
             $failCount++;
             continue;
        }
        if (empty($className) || $courseYear <= 0) {
             $failCount++;
             continue;
        }
        // Phone check? Not strictly blocking perhaps in action, but good to be consistent
        if (!empty($phone) && !preg_match('/^[0-9]{9,11}$/', $phone)) {
             $failCount++;
             continue;
        }

        // Check Duplicate
        $checkStmt->bind_param('s', $studentCode);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            $failCount++;
            continue;
        }

        // Map Faculty
        $facultyID = null;
        // Check DB
        $stmtFac = $conn->prepare("SELECT FacultyID FROM Faculties WHERE FacultyName LIKE ? LIMIT 1");
        $likeName = "%$facultyName%";
        $stmtFac->bind_param('s', $likeName);
        $stmtFac->execute();
        $resFac = $stmtFac->get_result();
        if ($fRow = $resFac->fetch_assoc()) {
            $facultyID = $fRow['FacultyID'];
        } else {
            $stmtFac->close();
            $failCount++; // Faculty not found
            continue;
        }
        $stmtFac->close();

        // Create User Identity
        // Username = StudentCode
        // Password = PasswordHash(StudentCode)
        $passwordHash = password_hash($studentCode, PASSWORD_DEFAULT);
        
        $stmtUser->bind_param('sss', $studentCode, $passwordHash, $email);
        if (!$stmtUser->execute()) {
            $failCount++;
            continue; // Fail to create user
        }
        $userID = $conn->insert_id;

        // Create Student Record
        $stmtStudent->bind_param('isssisssss', $userID, $studentCode, $fullName, $gender, $facultyID, $className, $courseYear, $phone, $email, $address);
        
        if ($stmtStudent->execute()) {
            $successCount++;
        } else {
            // Rollback User creation if Student creation fails?
            // For bulk import, simple continue might be better, but orphan User is bad.
            // Let's delete the user we just created.
            $conn->query("DELETE FROM Users WHERE UserID = $userID");
            $failCount++;
        }
    }
    
    $conn->commit();
    
    // Clean up file
    unlink($filePath);

} catch (Exception $e) {
    $conn->rollback();
    header('Location: student_import.php?error=SystemError');
    exit;
}

// Redirect with result
header("Location: student_list.php?msg=Imported&success=$successCount&fail=$failCount");
exit;
?>
