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

// Cache faculties
$faculties = [];
$facResult = $conn->query("SELECT FacultyID, FacultyName FROM Faculties");
while ($fRow = $facResult->fetch_assoc()) {
    $faculties[$fRow['FacultyName']] = $fRow['FacultyID'];
}

$rows = $xlsx->rows();
$startIndex = 0;
if (!empty($rows) && (strtoupper(trim($rows[0][0])) === 'MSSV' || strtoupper(trim($rows[0][0])) === 'MASV' || strtoupper(trim($rows[0][0])) === 'STT')) {
    $startIndex = 1;
}

$successCount = 0;
$failCount = 0;

$conn->begin_transaction();

try {
    $stmtUser = $conn->prepare("INSERT INTO Users (Username, FullName, PasswordHash, Email, Role, CreatedAt) VALUES (?, ?, ?, ?, 'Student', NOW())");
    $stmtStudent = $conn->prepare("INSERT INTO Students (UserID, StudentCode, FullName, Gender, FacultyID, ClassName, CourseYear, Phone, Email, Address, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $checkStmt = $conn->prepare("SELECT StudentID FROM Students WHERE StudentCode = ?");

    for ($i = $startIndex; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        // Skip empty rows
        $allEmpty = true;
        foreach ($row as $cell) {
            if (trim($cell ?? '') !== '') { $allEmpty = false; break; }
        }
        if ($allEmpty) continue;

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
        if (empty($studentCode) || empty($fullName)) { $failCount++; continue; }
        if (empty($gender) || !in_array($gender, ['Nam', 'Nữ'])) { $failCount++; continue; }
        if (empty($facultyName) || empty($className) || $courseYear <= 0) { $failCount++; continue; }
        if (!empty($phone) && !preg_match('/^[0-9]{9,11}$/', $phone)) { $failCount++; continue; }

        // Duplicate check
        $checkStmt->bind_param('s', $studentCode);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) { $failCount++; continue; }

        // Faculty lookup (cached)
        $facultyID = null;
        if (isset($faculties[$facultyName])) {
            $facultyID = $faculties[$facultyName];
        } else {
            foreach ($faculties as $fname => $fid) {
                if (stripos($fname, $facultyName) !== false || stripos($facultyName, $fname) !== false) {
                    $facultyID = $fid;
                    break;
                }
            }
        }
        if ($facultyID === null) { $failCount++; continue; }

        // Create User (Username = MSSV, Password = hash(MSSV))
        $passwordHash = password_hash($studentCode, PASSWORD_DEFAULT);
        $stmtUser->bind_param('ssss', $studentCode, $fullName, $passwordHash, $email);
        
        if (!$stmtUser->execute()) { $failCount++; continue; }
        $userID = $conn->insert_id;

        // Create Student
        $stmtStudent->bind_param('isssisssss', $userID, $studentCode, $fullName, $gender, $facultyID, $className, $courseYear, $phone, $email, $address);
        
        if ($stmtStudent->execute()) {
            $successCount++;
        } else {
            // Rollback user creation
            $conn->query("DELETE FROM Users WHERE UserID = $userID");
            $failCount++;
        }
    }
    
    $conn->commit();
    
    // Clean up file
    @unlink($filePath);

} catch (Exception $e) {
    $conn->rollback();
    header('Location: student_import.php?error=SystemError');
    exit;
}

// Redirect with result
header("Location: student_list.php?msg=Imported&success=$successCount&fail=$failCount");
exit;
?>
