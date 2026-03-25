<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/SimpleXLSXGen.php';

requireRole(['Admin', 'Manager']);

function buildImportFixSuggestion(string $reasons): string
{
    $suggestions = [];

    if (stripos($reasons, 'Thiếu MSSV') !== false) {
        $suggestions[] = 'Bổ sung MSSV và đảm bảo duy nhất.';
    }
    if (stripos($reasons, 'Trùng MSSV') !== false) {
        $suggestions[] = 'Đổi MSSV khác chưa tồn tại trong file và hệ thống.';
    }
    if (stripos($reasons, 'Họ tên') !== false) {
        $suggestions[] = 'Điền đầy đủ họ tên sinh viên.';
    }
    if (stripos($reasons, 'Giới tính') !== false) {
        $suggestions[] = 'Chỉ dùng Nam hoặc Nữ.';
    }
    if (stripos($reasons, 'Khoa') !== false) {
        $suggestions[] = 'Dùng đúng tên khoa hoặc mã khoa đang có trong hệ thống.';
    }
    if (stripos($reasons, 'Lớp') !== false) {
        $suggestions[] = 'Bổ sung tên lớp.';
    }
    if (stripos($reasons, 'Khóa') !== false) {
        $suggestions[] = 'Nhập khóa dạng năm như 2022 hoặc niên khóa có chứa năm.';
    }
    if (stripos($reasons, 'SĐT') !== false) {
        $suggestions[] = 'Kiểm tra số điện thoại 9-15 chữ số.';
    }
    if (stripos($reasons, 'Email') !== false) {
        $suggestions[] = 'Kiểm tra định dạng email và đảm bảo email chưa bị trùng.';
    }

    if ($suggestions === []) {
        return 'Kiểm tra lại dữ liệu theo cột lỗi và đối chiếu với danh mục hiện có trong hệ thống.';
    }

    return implode(' ', array_values(array_unique($suggestions)));
}

$errorRows = $_SESSION['student_import_error_rows'] ?? [];
if (!is_array($errorRows) || $errorRows === []) {
    header('Location: student_import.php?error=InvalidRequest');
    exit;
}

$errorSheet = [[
    'Dòng Excel',
    'MSSV',
    'Họ tên',
    'Giới tính',
    'Khoa',
    'Lớp',
    'Khóa',
    'SĐT',
    'Email',
    'Địa chỉ',
    'Lý do lỗi',
    'Gợi ý sửa',
]];

foreach ($errorRows as $row) {
    $reasons = (string)($row['Reasons'] ?? '');
    $errorSheet[] = [
        (string)($row['ExcelRow'] ?? ''),
        (string)($row['StudentCode'] ?? ''),
        (string)($row['FullName'] ?? ''),
        (string)($row['Gender'] ?? ''),
        (string)($row['FacultyName'] ?? ''),
        (string)($row['ClassName'] ?? ''),
        (string)($row['CourseYear'] ?? ''),
        (string)($row['Phone'] ?? ''),
        (string)($row['Email'] ?? ''),
        (string)($row['Address'] ?? ''),
        $reasons,
        buildImportFixSuggestion($reasons),
    ];
}

$facultySheet = [[
    'FacultyID',
    'Mã khoa',
    'Tên khoa',
]];

$facultyResult = $conn->query("SELECT FacultyID, FacultyCode, FacultyName FROM Faculties ORDER BY FacultyName ASC");
if ($facultyResult instanceof mysqli_result) {
    while ($faculty = $facultyResult->fetch_assoc()) {
        $facultySheet[] = [
            (string)($faculty['FacultyID'] ?? ''),
            (string)($faculty['FacultyCode'] ?? ''),
            (string)($faculty['FacultyName'] ?? ''),
        ];
    }
}

if (count($facultySheet) === 1) {
    $facultySheet[] = ['', '', 'Chưa có dữ liệu khoa trong hệ thống'];
}

$filenameSeed = pathinfo((string)($_SESSION['student_import_error_filename'] ?? 'loi_import'), PATHINFO_FILENAME);
$filenameSeed = preg_replace('/[^A-Za-z0-9_-]+/', '_', $filenameSeed) ?: 'loi_import';

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($errorSheet, 'Dong loi');
$xlsx->addSheet($facultySheet, 'Danh muc khoa');
$xlsx->downloadAs('Loi_Import_SinhVien_' . $filenameSeed . '.xlsx');
