<?php

function studentImportNormalizeText(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) {
        $value = $ascii;
    }

    $value = strtr($value, [
        'đ' => 'd',
        'Đ' => 'D',
    ]);

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function studentImportIdentityKey(string $value): string
{
    return strtolower(trim($value));
}

function studentImportHeaderAliases(): array
{
    return [
        'student_code' => ['mssv', 'ma sv', 'masv', 'student code', 'studentcode', 'username'],
        'full_name' => ['họ tên', 'ho ten', 'hoten', 'full name', 'fullname', 'tên sinh viên', 'ten sinh vien'],
        'gender' => ['giới tính', 'gioi tinh', 'gender'],
        'faculty_name' => ['tên khoa', 'ten khoa', 'khoa', 'faculty', 'faculty name'],
        'class_name' => ['lớp', 'lop', 'class', 'class name', 'tên lớp', 'ten lop'],
        'course_year' => ['khóa', 'khoa hoc', 'khóa học', 'course year', 'năm nhập học', 'nam nhap hoc', 'niên khóa', 'nien khoa'],
        'phone' => ['sđt', 'sdt', 'số điện thoại', 'so dien thoai', 'điện thoại', 'dien thoai', 'phone'],
        'email' => ['email', 'mail'],
        'address' => ['địa chỉ', 'dia chi', 'address', 'nơi ở', 'noi o'],
    ];
}

function studentImportMatchHeaderField(string $cellValue): ?string
{
    $raw = mb_strtolower(trim($cellValue), 'UTF-8');
    if ($raw === '') {
        return null;
    }

    static $rawMap = null;
    static $normalizedMap = null;

    if ($rawMap === null || $normalizedMap === null) {
        $rawMap = [];
        $normalizedMap = [];

        foreach (studentImportHeaderAliases() as $field => $aliases) {
            foreach ($aliases as $alias) {
                $rawMap[mb_strtolower($alias, 'UTF-8')] = $field;
                $normalizedMap[studentImportNormalizeText($alias)] = $field;
            }
        }
    }

    if (isset($rawMap[$raw])) {
        return $rawMap[$raw];
    }

    $normalized = studentImportNormalizeText($cellValue);
    if ($normalized !== '' && isset($normalizedMap[$normalized])) {
        return $normalizedMap[$normalized];
    }

    return null;
}

function studentImportResolveColumnConfig(array $rows): array
{
    $defaultMap = [
        'student_code' => 0,
        'full_name' => 1,
        'gender' => 2,
        'faculty_name' => 3,
        'class_name' => 4,
        'course_year' => 5,
        'phone' => 6,
        'email' => 7,
        'address' => 8,
    ];

    if (empty($rows)) {
        return [
            'has_header' => false,
            'start_index' => 0,
            'map' => $defaultMap,
        ];
    }

    $headerMap = array_fill_keys(array_keys($defaultMap), null);
    $matchedFields = 0;

    foreach ($rows[0] as $index => $cellValue) {
        $field = studentImportMatchHeaderField((string)$cellValue);
        if ($field !== null && array_key_exists($field, $headerMap) && $headerMap[$field] === null) {
            $headerMap[$field] = $index;
            $matchedFields++;
        }
    }

    if ($matchedFields >= 4) {
        return [
            'has_header' => true,
            'start_index' => 1,
            'map' => $headerMap,
        ];
    }

    return [
        'has_header' => false,
        'start_index' => 0,
        'map' => $defaultMap,
    ];
}

function studentImportCellValue(array $row, ?int $index): string
{
    if ($index === null || !array_key_exists($index, $row)) {
        return '';
    }

    return trim((string)$row[$index]);
}

function studentImportNormalizeGender(string $value): string
{
    $normalized = studentImportNormalizeText($value);

    if (in_array($normalized, ['nam', 'male', 'm'], true)) {
        return 'Nam';
    }

    if (in_array($normalized, ['nu', 'female', 'f'], true)) {
        return 'Nữ';
    }

    return '';
}

function studentImportNormalizePhone(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[\s\-\.\(\)]+/', '', $value) ?? $value;

    return trim($value);
}

function studentImportParseCourseYear(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    if (preg_match('/\d{4}/', $value, $matches)) {
        return (int)$matches[0];
    }

    if (preg_match('/\d+/', $value, $matches)) {
        return (int)$matches[0];
    }

    return 0;
}

function studentImportBuildContext(mysqli $conn): array
{
    $facultyLookup = [
        'exact' => [],
        'normalized' => [],
    ];

    $facultyResult = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName");
    if ($facultyResult) {
        while ($faculty = $facultyResult->fetch_assoc()) {
            $name = trim((string)$faculty['FacultyName']);
            $facultyLookup['exact'][$name] = (int)$faculty['FacultyID'];
            $facultyLookup['normalized'][studentImportNormalizeText($name)] = (int)$faculty['FacultyID'];
        }
    }

    $existingStudentCodes = [];
    $studentResult = $conn->query("SELECT StudentCode FROM Students");
    if ($studentResult) {
        while ($student = $studentResult->fetch_assoc()) {
            $existingStudentCodes[studentImportIdentityKey((string)$student['StudentCode'])] = true;
        }
    }

    $existingUsernames = [];
    $existingEmails = [];
    $userResult = $conn->query("SELECT Username, Email FROM Users");
    if ($userResult) {
        while ($user = $userResult->fetch_assoc()) {
            $username = studentImportIdentityKey((string)($user['Username'] ?? ''));
            $email = studentImportIdentityKey((string)($user['Email'] ?? ''));

            if ($username !== '') {
                $existingUsernames[$username] = true;
            }

            if ($email !== '') {
                $existingEmails[$email] = true;
            }
        }
    }

    return [
        'faculties' => $facultyLookup,
        'existing_student_codes' => $existingStudentCodes,
        'existing_usernames' => $existingUsernames,
        'existing_emails' => $existingEmails,
    ];
}

function studentImportResolveFacultyId(string $facultyName, array $facultyLookup): ?int
{
    $facultyName = trim($facultyName);
    if ($facultyName === '') {
        return null;
    }

    if (isset($facultyLookup['exact'][$facultyName])) {
        return $facultyLookup['exact'][$facultyName];
    }

    $normalized = studentImportNormalizeText($facultyName);
    if ($normalized !== '' && isset($facultyLookup['normalized'][$normalized])) {
        return $facultyLookup['normalized'][$normalized];
    }

    return null;
}

function studentImportPrepareRow(array $row, array $columnMap): array
{
    $studentCode = preg_replace('/\s+/', '', studentImportCellValue($row, $columnMap['student_code'])) ?? '';
    $fullName = preg_replace('/\s+/', ' ', studentImportCellValue($row, $columnMap['full_name'])) ?? '';
    $facultyName = preg_replace('/\s+/', ' ', studentImportCellValue($row, $columnMap['faculty_name'])) ?? '';
    $className = preg_replace('/\s+/', ' ', studentImportCellValue($row, $columnMap['class_name'])) ?? '';
    $phone = studentImportNormalizePhone(studentImportCellValue($row, $columnMap['phone']));
    $email = strtolower(studentImportCellValue($row, $columnMap['email']));
    $address = preg_replace('/\s+/', ' ', studentImportCellValue($row, $columnMap['address'])) ?? '';

    return [
        'StudentCode' => trim($studentCode),
        'FullName' => trim($fullName),
        'Gender' => studentImportNormalizeGender(studentImportCellValue($row, $columnMap['gender'])),
        'FacultyName' => trim($facultyName),
        'ClassName' => trim($className),
        'CourseYear' => studentImportParseCourseYear(studentImportCellValue($row, $columnMap['course_year'])),
        'Phone' => $phone,
        'Email' => trim($email),
        'Address' => trim($address),
    ];
}

function studentImportAnalyzeRows(array $rows, array $context): array
{
    $config = studentImportResolveColumnConfig($rows);
    $validRows = [];
    $errorRows = [];
    $seenStudentCodes = [];
    $seenEmails = [];

    for ($i = $config['start_index']; $i < count($rows); $i++) {
        $row = $rows[$i];

        $allEmpty = true;
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') {
                $allEmpty = false;
                break;
            }
        }

        if ($allEmpty) {
            continue;
        }

        $rowData = studentImportPrepareRow($row, $config['map']);
        $rowData['FacultyID'] = null;
        $rowData['ExcelRow'] = $i + 1;

        $errors = [];
        $studentKey = studentImportIdentityKey($rowData['StudentCode']);
        $emailKey = studentImportIdentityKey($rowData['Email']);

        if ($rowData['StudentCode'] === '') {
            $errors[] = 'Thiếu MSSV';
        } elseif (!preg_match('/^[a-z0-9_.-]{4,32}$/i', $rowData['StudentCode'])) {
            $errors[] = 'MSSV chỉ được gồm chữ, số, . _ - và dài 4-32 ký tự';
        } else {
            if (isset($seenStudentCodes[$studentKey])) {
                $errors[] = 'Trùng MSSV trong file';
            }
            if (isset($context['existing_student_codes'][$studentKey])) {
                $errors[] = 'MSSV đã tồn tại trong Students';
            }
            if (isset($context['existing_usernames'][$studentKey])) {
                $errors[] = 'Tên đăng nhập đã tồn tại trong Users';
            }
        }

        if ($rowData['FullName'] === '') {
            $errors[] = 'Thiếu Họ tên';
        }

        if ($rowData['Gender'] === '') {
            $errors[] = 'Giới tính không hợp lệ (Nam/Nữ)';
        }

        if ($rowData['FacultyName'] === '') {
            $errors[] = 'Thiếu tên Khoa';
        } else {
            $facultyID = studentImportResolveFacultyId($rowData['FacultyName'], $context['faculties']);
            if ($facultyID === null) {
                $errors[] = 'Khoa không khớp danh mục trong hệ thống';
            } else {
                $rowData['FacultyID'] = $facultyID;
            }
        }

        if ($rowData['ClassName'] === '') {
            $errors[] = 'Thiếu tên Lớp';
        }

        if ($rowData['CourseYear'] <= 0) {
            $errors[] = 'Thiếu Khóa';
        }

        if ($rowData['Phone'] !== '' && !preg_match('/^\+?[0-9]{9,15}$/', $rowData['Phone'])) {
            $errors[] = 'SĐT không hợp lệ';
        }

        if ($rowData['Email'] !== '') {
            if (!filter_var($rowData['Email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email không hợp lệ';
            } else {
                if (isset($seenEmails[$emailKey])) {
                    $errors[] = 'Trùng email trong file';
                }
                if (isset($context['existing_emails'][$emailKey])) {
                    $errors[] = 'Email đã tồn tại trong Users';
                }
            }
        }

        if ($studentKey !== '') {
            $seenStudentCodes[$studentKey] = true;
        }
        if ($emailKey !== '') {
            $seenEmails[$emailKey] = true;
        }

        if ($errors) {
            $rowData['Reasons'] = implode(', ', $errors);
            $errorRows[] = $rowData;
            continue;
        }

        $validRows[] = $rowData;
    }

    return [
        'column_config' => $config,
        'validRows' => $validRows,
        'errorRows' => $errorRows,
    ];
}
