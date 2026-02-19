<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/SimpleXLSX.php';

requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

// Cache faculties for lookup
$faculties = [];
$facResult = $conn->query("SELECT FacultyID, FacultyName FROM Faculties");
while ($fRow = $facResult->fetch_assoc()) {
    $faculties[$fRow['FacultyName']] = $fRow['FacultyID'];
}

$previewData = [];
$errorRows = [];
$validRows = [];
$uploadError = '';
$uploadedFile = '';

// Handle File Upload & Preview
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
                    
                    // Detect header row
                    $startIndex = 0;
                    if (!empty($rows) && (strtoupper(trim($rows[0][0])) === 'MSSV' || strtoupper(trim($rows[0][0])) === 'MASV' || strtoupper(trim($rows[0][0])) === 'STT')) {
                        $startIndex = 1;
                    }

                    // Prepare duplicate check
                    $checkStmt = $conn->prepare("SELECT StudentID FROM Students WHERE StudentCode = ?");

                    for ($i = $startIndex; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        
                        // Skip completely empty rows
                        $allEmpty = true;
                        foreach ($row as $cell) {
                            if (trim($cell ?? '') !== '') { $allEmpty = false; break; }
                        }
                        if ($allEmpty) continue;

                        // Clean data — Expected: MSSV | Họ tên | Giới tính | Khoa | Lớp | Khóa | SĐT | Email | Địa chỉ
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

                        // Validation
                        if (empty($studentCode)) $errors[] = 'Thiếu MSSV';
                        if (empty($fullName)) $errors[] = 'Thiếu Họ tên';
                        
                        if (empty($gender)) {
                            $errors[] = 'Thiếu Giới tính';
                        } elseif (!in_array($gender, ['Nam', 'Nữ'])) {
                            $errors[] = 'Giới tính không hợp lệ (Nam/Nữ)';
                        }

                        // Faculty lookup (cached)
                        $facultyID = null;
                        if (empty($facultyName)) {
                            $errors[] = 'Thiếu tên Khoa';
                        } else {
                            // Exact match first
                            if (isset($faculties[$facultyName])) {
                                $facultyID = $faculties[$facultyName];
                            } else {
                                // Fuzzy match
                                $found = false;
                                foreach ($faculties as $fname => $fid) {
                                    if (stripos($fname, $facultyName) !== false || stripos($facultyName, $fname) !== false) {
                                        $facultyID = $fid;
                                        $found = true;
                                        break;
                                    }
                                }
                                if (!$found) $errors[] = 'Khoa không tồn tại';
                            }
                        }

                        if (empty($className)) $errors[] = 'Thiếu tên Lớp';
                        if ($courseYear <= 0) $errors[] = 'Thiếu Khóa';
                        if (!empty($phone) && !preg_match('/^[0-9]{9,11}$/', $phone)) $errors[] = 'SĐT không hợp lệ';

                        // Duplicate check in DB
                        if (!empty($studentCode)) {
                            $checkStmt->bind_param('s', $studentCode);
                            $checkStmt->execute();
                            $checkStmt->store_result();
                            if ($checkStmt->num_rows > 0) {
                                $errors[] = 'MSSV đã tồn tại trong DB';
                            }
                        }

                        // Duplicate check in current file
                        foreach ($validRows as $vr) {
                            if ($vr['StudentCode'] === $studentCode) {
                                $errors[] = 'Trùng MSSV trong file';
                                break;
                            }
                        }

                        $rowData = [
                            'StudentCode' => $studentCode,
                            'FullName'    => $fullName,
                            'Gender'      => $gender,
                            'FacultyID'   => $facultyID,
                            'FacultyName' => $facultyName,
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
                }
            } else {
                $uploadError = 'Lỗi lưu file upload';
            }
        }
    } else {
        $uploadError = 'Lỗi upload file: ' . $_FILES['excel_file']['error'];
    }
}
?>

    <link rel="stylesheet" href="../../../assets/css/staff/student_import.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                <div class="upload-area" id="dropZone">
                    <div class="upload-icon">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <h3>Kéo thả file Excel vào đây</h3>
                    <p class="muted">hoặc click để chọn file — chỉ hỗ trợ <strong>.xlsx</strong></p>
                    <input type="file" name="excel_file" id="fileInput" accept=".xlsx" required>
                    <div class="file-name" id="fileName" style="display: none;">
                        <i class="fa-solid fa-file-excel"></i>
                        <span id="fileNameText"></span>
                    </div>
                    <div class="upload-actions">
                        <button type="submit" class="btn btn-primary" id="uploadBtn" style="display: none;">
                            <i class="fa-solid fa-upload"></i> Tải lên & Xem trước
                        </button>
                        <a href="download_template.php" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-download"></i> Tải file mẫu
                        </a>
                    </div>
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar-wrap">
                        <div class="progress-bar-fill" id="progressBar"></div>
                    </div>
                    <div class="progress-text" id="progressText">Đang xử lý...</div>
                </div>
            </form>

            <div class="guide">
                <h4><i class="fa-solid fa-circle-info"></i> Hướng dẫn file Excel</h4>
                <p>Thứ tự các cột: <strong>MSSV | Họ tên | Giới tính | Tên Khoa | Lớp | Khóa | SĐT | Email | Địa chỉ</strong></p>
                <ul>
                    <li><strong>MSSV</strong> — Bắt buộc, phải duy nhất, chưa tồn tại trong hệ thống</li>
                    <li><strong>Họ tên</strong> — Bắt buộc</li>
                    <li><strong>Giới tính</strong> — Bắt buộc, chỉ "Nam" hoặc "Nữ"</li>
                    <li><strong>Tên Khoa</strong> — Bắt buộc, phải khớp với Khoa trong hệ thống</li>
                    <li><strong>Lớp, Khóa</strong> — Bắt buộc</li>
                    <li><strong>SĐT, Email, Địa chỉ</strong> — Không bắt buộc nhưng khuyến khích điền</li>
                </ul>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', () => {
                const dropZone = document.getElementById('dropZone');
                const fileInput = document.getElementById('fileInput');
                const fileName = document.getElementById('fileName');
                const fileNameText = document.getElementById('fileNameText');
                const uploadBtn = document.getElementById('uploadBtn');
                const uploadForm = document.getElementById('uploadForm');
                const progressContainer = document.getElementById('progressContainer');

                // Click to select file
                dropZone.addEventListener('click', (e) => {
                    if (e.target.closest('.btn')) return;
                    fileInput.click();
                });

                // Drag & Drop
                ['dragenter', 'dragover'].forEach(evt => {
                    dropZone.addEventListener(evt, (e) => {
                        e.preventDefault();
                        dropZone.classList.add('dragover');
                    });
                });

                ['dragleave', 'drop'].forEach(evt => {
                    dropZone.addEventListener(evt, (e) => {
                        e.preventDefault();
                        dropZone.classList.remove('dragover');
                    });
                });

                dropZone.addEventListener('drop', (e) => {
                    const files = e.dataTransfer.files;
                    if (files.length > 0 && files[0].name.endsWith('.xlsx')) {
                        fileInput.files = files;
                        showFileName(files[0].name);
                    } else {
                        Swal.fire('Lỗi', 'Chỉ hỗ trợ file .xlsx', 'error');
                    }
                });

                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length > 0) {
                        showFileName(fileInput.files[0].name);
                    }
                });

                function showFileName(name) {
                    fileNameText.textContent = name;
                    fileName.style.display = 'inline-flex';
                    uploadBtn.style.display = 'inline-flex';
                }

                // Show progress on submit
                uploadForm.addEventListener('submit', () => {
                    progressContainer.style.display = 'block';
                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
                    
                    let progress = 0;
                    const bar = document.getElementById('progressBar');
                    const text = document.getElementById('progressText');
                    const interval = setInterval(() => {
                        progress += Math.random() * 15;
                        if (progress > 90) progress = 90;
                        bar.style.width = progress + '%';
                        text.textContent = `Đang phân tích file... ${Math.round(progress)}%`;
                    }, 300);
                });
            });
            </script>

        <?php else: ?>
            <!-- STEP 2: PREVIEW & CONFIRM -->
            <div class="stats-summary">
                <div class="stat-box stat-total">
                    <span class="stat-number"><?= count($validRows) + count($errorRows) ?></span>
                    <span class="stat-label">Tổng dòng</span>
                </div>
                <div class="stat-box stat-valid">
                    <span class="stat-number"><?= count($validRows) ?></span>
                    <span class="stat-label">Hợp lệ</span>
                </div>
                <div class="stat-box stat-invalid">
                    <span class="stat-number"><?= count($errorRows) ?></span>
                    <span class="stat-label">Lỗi</span>
                </div>
            </div>

            <div class="action-bar">
                <div class="left-actions">
                    <?php if (!empty($validRows)): ?>
                        <form id="confirmForm" action="student_import_action.php" method="POST" style="display: inline;">
                            <input type="hidden" name="filename" value="<?= htmlspecialchars($uploadedFile) ?>">
                            <button type="button" onclick="confirmImport()" class="btn btn-success">
                                <i class="fa-solid fa-file-import"></i> Nhập <?= count($validRows) ?> sinh viên hợp lệ
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="student_import.php" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Hủy bỏ</a>
                </div>
            </div>

            <?php if (empty($validRows)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    Không có dòng nào hợp lệ để nhập. Vui lòng kiểm tra lại file Excel.
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Trạng thái</th>
                            <th>MSSV</th>
                            <th>Họ tên</th>
                            <th>Giới tính</th>
                            <th>Khoa</th>
                            <th>Lớp</th>
                            <th>Khóa</th>
                            <th>SĐT</th>
                            <th>Email</th>
                            <th>Lý do lỗi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowNum = 1;
                        // Error rows first
                        foreach ($errorRows as $row): 
                        ?>
                            <tr class="row-error">
                                <td><?= $rowNum++ ?></td>
                                <td><span class="status-err"><i class="fa-solid fa-times-circle"></i> Lỗi</span></td>
                                <td><?= htmlspecialchars($row['StudentCode']) ?></td>
                                <td><?= htmlspecialchars($row['FullName']) ?></td>
                                <td><?= htmlspecialchars($row['Gender']) ?></td>
                                <td><?= htmlspecialchars($row['FacultyName']) ?></td>
                                <td><?= htmlspecialchars($row['ClassName']) ?></td>
                                <td><?= $row['CourseYear'] ?: '—' ?></td>
                                <td><?= htmlspecialchars($row['Phone']) ?: '—' ?></td>
                                <td><?= htmlspecialchars($row['Email']) ?: '—' ?></td>
                                <td class="error-reasons"><?= htmlspecialchars($row['Reasons']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($validRows as $row): ?>
                            <tr>
                                <td><?= $rowNum++ ?></td>
                                <td><span class="status-ok"><i class="fa-solid fa-check-circle"></i> OK</span></td>
                                <td><?= htmlspecialchars($row['StudentCode']) ?></td>
                                <td><?= htmlspecialchars($row['FullName']) ?></td>
                                <td><?= htmlspecialchars($row['Gender']) ?></td>
                                <td><?= htmlspecialchars($row['FacultyName']) ?></td>
                                <td><?= htmlspecialchars($row['ClassName']) ?></td>
                                <td><?= $row['CourseYear'] ?></td>
                                <td><?= htmlspecialchars($row['Phone']) ?: '—' ?></td>
                                <td><?= htmlspecialchars($row['Email']) ?: '—' ?></td>
                                <td>—</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script>
            function confirmImport() {
                Swal.fire({
                    title: 'Xác nhận nhập dữ liệu',
                    html: `Bạn sẽ nhập <strong><?= count($validRows) ?></strong> sinh viên vào hệ thống.<br>
                           <small style="color:#64748b">Mỗi sinh viên sẽ được tạo tài khoản với mật khẩu mặc định = MSSV</small>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: '<i class="fa-solid fa-file-import"></i> Đồng ý nhập',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Đang nhập dữ liệu...',
                            html: 'Vui lòng chờ, không tắt trang.',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                        document.getElementById('confirmForm').submit();
                    }
                });
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
