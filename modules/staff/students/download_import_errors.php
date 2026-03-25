<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../includes/auth_check.php';
require_once '../../../includes/SimpleXLSXGen.php';

requireRole(['Admin', 'Manager']);

$errorRows = $_SESSION['student_import_error_rows'] ?? [];
if (!is_array($errorRows) || $errorRows === []) {
    header('Location: student_import.php?error=InvalidRequest');
    exit;
}

$data = [[
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
]];

foreach ($errorRows as $row) {
    $data[] = [
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
        (string)($row['Reasons'] ?? ''),
    ];
}

$filenameSeed = pathinfo((string)($_SESSION['student_import_error_filename'] ?? 'loi_import'), PATHINFO_FILENAME);
$filenameSeed = preg_replace('/[^A-Za-z0-9_-]+/', '_', $filenameSeed) ?: 'loi_import';

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs('Loi_Import_SinhVien_' . $filenameSeed . '.xlsx');
