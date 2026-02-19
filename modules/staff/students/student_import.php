<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/SimpleXLSX.php';

requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

$previewData = [];
$errorRows = [];
$validRows = [];
$uploadError = '';
$uploadedFile = '';

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    if ($_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $uploadError = 'Vui lòng chọn file Excel (.xlsx)';
        } else {
            $uploadDir = '../../../uploads/imports/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = 'import_' . time() . '_' . basename($_FILES['excel_file']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $targetPath)) {
                $uploadedFile = $fileName;
                
                if ($xlsx = Shuchkin\SimpleXLSX::parse($targetPath)) {
                    $rows = $xlsx->rows();
                    
                    // Remove header if present (assuming row 1 is header)
                    // We can check if first cell is 'MSSV' or similar to be sure, but for now let's assume valid format starts from row 2
                    // Or better, let's try to detect header
                    $startIndex = 0;
                    if (!empty($rows) && (strtoupper($rows[0][0]) === 'MSSV' || strtoupper($rows[0][0]) === 'MASV')) {
                        $startIndex = 1;
                    }

                    // Prepare statement to check existing StudentCode
                    $checkStmt = $conn->prepare("SELECT StudentID FROM Students WHERE StudentCode = ?");

                    for ($i = $startIndex; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        // Expected format: 
                        // 0: MSSV (Required, Unique)
                        // 1: FullName (Required)
                        // 2: Gender (Nam/Nữ)
                        // 3: Faculty (ID or ignore for now, maybe map by Name?) -> Let's expect FacultyID for simplicity or Name then lookup? 
                        //    Let's keep it simple: Faculty Name. We will lookup ID. if not found -> null
                        // 4: ClassName
                        // 5: CourseYear (Int)
                        // 6: Phone
                        // 7: Email
                        // 8: Address
                        
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
                        
                        $errors = [];

                        // 1. Validation: Required fields
                        if (empty($studentCode)) {
                            $errors[] = 'Thiếu MSSV';
                        }
                        if (empty($fullName)) {
                            $errors[] = 'Thiếu Họ tên';
                        }
                        
                        // Strict Gender Check
                        if (empty($gender)) {
                            $errors[] = 'Thiếu Giới tính';
                        } elseif (!in_array($gender, ['Nam', 'Nữ'])) {
                            $errors[] = 'Giới tính không hợp lệ (Nam/Nữ)';
                        }

                        // Strict Faculty Check
                        $facultyID = null;
                        if (empty($facultyName)) {
                            $errors[] = 'Thiếu tên Khoa';
                        } else {
                            // Check DB
                            // Optimization: Cache faculties to avoid query loop?
                            // For now, query is fine for moderate size.
                            $stmtFac = $conn->prepare("SELECT FacultyID FROM Faculties WHERE FacultyName LIKE ? LIMIT 1");
                            $likeName = "%$facultyName%";
                            $stmtFac->bind_param('s', $likeName);
                            $stmtFac->execute();
                            $resFac = $stmtFac->get_result();
                            if ($fRow = $resFac->fetch_assoc()) {
                                $facultyID = $fRow['FacultyID'];
                            } else {
                                $errors[] = 'Khoa không tồn tại';
                            }
                            $stmtFac->close();
                        }

                        if (empty($className)) {
                            $errors[] = 'Thiếu tên Lớp';
                        }
                        if ($courseYear <= 0) {
                            $errors[] = 'Thiếu Khóa';
                        }
                        if (!empty($phone) && !preg_match('/^[0-9]{9,11}$/', $phone)) {
                             $errors[] = 'SĐT không hợp lệ';
                        }

                        // 2. Validation: Duplicate MSSV in DB
                        if (!empty($studentCode)) {
                            $checkStmt->bind_param('s', $studentCode);
                            $checkStmt->execute();
                            $checkStmt->store_result();
                            if ($checkStmt->num_rows > 0) {
                                $errors[] = 'MSSV đã tồn tại';
                            }
                        }

                        // 3. Validation: Duplicate in current file
                        foreach ($validRows as $vr) {
                            if ($vr['StudentCode'] === $studentCode) {
                                $errors[] = 'Trùng MSSV trong file';
                                break;
                            }
                        }
                        
                        // Check duplicates in errorRows too? No, usually just unique in file.
                        // Actually, if duplicate in file, both might be error if we don't handle carefully.
                        // But let's stick to validRows check.
                        
                        // Map Faculty Name to ID (Done above)

                        $rowData = [
                            'StudentCode' => $studentCode,
                            'FullName'    => $fullName,
                            'Gender'      => $gender,
                            'FacultyID'   => $facultyID,
                            'FacultyName' => $facultyName, // For display
                            'ClassName'   => $className,
                            'CourseYear'  => $courseYear,
                            'Phone'       => $phone,
                            'Email'       => $email,
                            'Address'     => $address,
                        ];

                        if (!empty($errors)) {
                            $rowData['Reasons'] = implode(', ', $errors);
                            $errorRows[] = $rowData;
                        } else {
                            $validRows[] = $rowData;
                        }
                    }
                    $checkStmt->close();

                } else {
                    $uploadError = Shuchkin\SimpleXLSX::parseError();
                    // Fallback invalid file
                }
            } else {
                $uploadError = 'Lỗi lưu file upload';
            }
        }
    } else {
        $uploadError = 'Lỗi upload file: ' . $_FILES['excel_file']['error'];
    }
}

// Handle Import Action (AJAX or Form Submit from Preview)
// Actually, we can just use a separate file for the action to keep it clean, or post back here.
// The plan said `student_import_action.php`, let's stick to that for the final commit.

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Import Sinh viên | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .import-container { padding: 20px; max-width: 1200px; margin: 20px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .btn { padding: 8px 15px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; transition: all 0.2s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .upload-area { border: 2px dashed #cbd5e1; padding: 40px; text-align: center; border-radius: 8px; margin-bottom: 20px; transition: border-color 0.3s; }
        .upload-area:hover { border-color: #3b82f6; background: #f8fafc; }
        .alert { padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .stats-summary { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-box { padding: 15px; border-radius: 6px; flex: 1; text-align: center; font-weight: bold; }
        .stat-valid { background: #d1fae5; color: #047857; }
        .stat-invalid { background: #fee2e2; color: #b91c1c; }

        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9em; }
        .table th, .table td { padding: 8px 12px; border: 1px solid #e2e8f0; text-align: left; }
        .table th { background: #f1f5f9; font-weight: 600; }
        .row-error { background: #fef2f2; }
        .text-danger { color: #dc2626; font-weight: bold; }
        
        .guide { margin-top: 30px; padding: 15px; background: #f8fafc; border-radius: 6px; border-left: 4px solid #3b82f6; }
        .guide h4 { margin-top: 0; }
        .guide ul { padding-left: 20px; margin-bottom: 0; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="import-container">
        <div class="page-header">
            <h2><i class="fa-solid fa-file-import"></i> Nhập Sinh viên từ Excel</h2>
            <a href="student_list.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Quay lại</a>
        </div>

        <?php if ($uploadError): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($uploadError) ?></div>
        <?php endif; ?>

        <?php if (empty($validRows) && empty($errorRows)): ?>
            <!-- STEP 1: UPLOAD FORM -->
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area">
                    <i class="fa-solid fa-cloud-arrow-up fa-3x" style="color: #cbd5e1; margin-bottom: 15px;"></i>
                    <h3>Kéo thả hoặc chọn file Excel (.xlsx)</h3>
                    <p class="muted">Chỉ hỗ trợ file .xlsx. Dòng đầu tiên là tiêu đề.</p>
                    <input type="file" name="excel_file" accept=".xlsx" required style="margin-top: 10px;">
                    <br><br>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Tải lên & Xem trước</button>
                    
                    <div style="margin-top: 20px;">
                        <a href="download_template.php" class="btn btn-secondary" style="font-size: 0.8em; padding: 5px 10px;">
                            <i class="fa-solid fa-download"></i> Tải file mẫu
                        </a>
                    </div>
                </div>
            </form>

            <div class="guide">
                <h4>Hướng dẫn file Excel:</h4>
                <p>Thứ tự các cột bắt buộc: <strong>MSSV | Họ tên | Giới tính | Tên Khoa | Lớp | Khóa | SĐT | Email | Địa chỉ</strong></p>
                <ul>
                    <li><strong>MSSV</strong>: Phải là duy nhất, chưa tồn tại trong hệ thống.</li>
                    <li><strong>Giới tính</strong>: "Nam" hoặc "Nữ".</li>
                    <li>Các cột khác có thể để trống, nhưng khuyến khích điền đầy đủ.</li>
                </ul>
            </div>

        <?php else: ?>
            <!-- STEP 2: PREVIEW & CONFIRM -->
            <div class="stats-summary">
                <div class="stat-box stat-valid">
                    <i class="fa-solid fa-check-circle"></i> Hợp lệ: <?= count($validRows) ?>
                </div>
                <div class="stat-box stat-invalid">
                    <i class="fa-solid fa-times-circle"></i> Lỗi: <?= count($errorRows) ?>
                </div>
            </div>

            <?php if (!empty($validRows)): ?>
                <div style="margin-bottom: 20px; text-align: right;">
                    <form id="confirmForm" action="student_import_action.php" method="POST" style="display: inline;">
                        <input type="hidden" name="filename" value="<?= htmlspecialchars($uploadedFile) ?>">
                        <button type="button" onclick="confirmImport()" class="btn btn-success">
                            <i class="fa-solid fa-file-import"></i> Nhập <?= count($validRows) ?> sinh viên hợp lệ
                        </button>
                    </form>
                    <a href="student_import.php" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Hủy bỏ</a>
                </div>
            <?php else: ?>
                <div class="alert alert-error">Không có dòng nào hợp lệ để nhập. Vui lòng kiểm tra lại file.</div>
                <a href="student_import.php" class="btn btn-secondary">Thử lại</a>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Trạng thái</th>
                            <th>Lý do / Ghi chú</th>
                            <th>MSSV</th>
                            <th>Họ tên</th>
                            <th>Giới tính</th>
                            <th>Khoa</th>
                            <th>Lớp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Error Rows First -->
                        <?php foreach ($errorRows as $row): ?>
                            <tr class="row-error">
                                <td style="color: #dc2626; text-align: center;"><i class="fa-solid fa-times-circle"></i> Lỗi</td>
                                <td class="text-danger"><?= htmlspecialchars($row['Reasons']) ?></td>
                                <td><?= htmlspecialchars($row['StudentCode']) ?></td>
                                <td><?= htmlspecialchars($row['FullName']) ?></td>
                                <td><?= htmlspecialchars($row['Gender']) ?></td>
                                <td><?= htmlspecialchars($row['FacultyName']) ?></td>
                                <td><?= htmlspecialchars($row['ClassName']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Valid Rows -->
                        <?php foreach ($validRows as $row): ?>
                            <tr>
                                <td style="color: #059669; text-align: center;"><i class="fa-solid fa-check-circle"></i> OK</td>
                                <td>Sẵn sàng nhập</td>
                                <td><?= htmlspecialchars($row['StudentCode']) ?></td>
                                <td><?= htmlspecialchars($row['FullName']) ?></td>
                                <td><?= htmlspecialchars($row['Gender']) ?></td>
                                <td><?= htmlspecialchars($row['FacultyName']) ?></td>
                                <td><?= htmlspecialchars($row['ClassName']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script>
            function confirmImport() {
                Swal.fire({
                    title: 'Xác nhận nhập',
                    text: "Bạn có chắc muốn nhập <?= count($validRows) ?> sinh viên này vào hệ thống?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Đồng ý nhập',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('confirmForm').submit();
                    }
                })
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
