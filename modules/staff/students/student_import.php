<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/SimpleXLSX.php';
require_once __DIR__ . '/student_import_helpers.php';

requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

$pageTitle = 'Nhập sinh viên từ Excel';
$pageStylesheets = ['assets/css/staff/student_import.css'];
require_once '../../../includes/admin_header.php';

$conn->set_charset('utf8mb4');

$errorMessages = [
    'InvalidRequest' => 'Yêu cầu không hợp lệ. Vui lòng tải lại trang import.',
    'FileNotFound' => 'Không tìm thấy file Excel tạm. Vui lòng tải lại file và xem trước lại.',
    'ParseError' => 'Không thể đọc file Excel. Vui lòng kiểm tra lại định dạng .xlsx.',
    'SystemError' => 'Có lỗi hệ thống khi nhập dữ liệu. Chưa có thay đổi nào được ghi.',
];

$uploadError = $errorMessages[trim((string)($_GET['error'] ?? ''))] ?? '';
$errorRows = [];
$validRows = [];
$uploadedFile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    requireCsrf();

    if (($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadError = 'Lỗi upload file: ' . (int)($_FILES['excel_file']['error'] ?? 0);
    } else {
        $extension = strtolower(pathinfo((string)$_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $uploadError = 'Vui lòng chọn file Excel định dạng .xlsx.';
        } else {
            $uploadDir = '../../../uploads/imports/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                $uploadError = 'Không thể tạo thư mục tạm để xử lý file import.';
            } else {
                $fileName = 'import_' . time() . '_' . basename((string)$_FILES['excel_file']['name']);
                $targetPath = $uploadDir . $fileName;

                if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $targetPath)) {
                    $uploadError = 'Không thể lưu file upload.';
                } else {
                    $xlsx = Shuchkin\SimpleXLSX::parse($targetPath);
                    if (!$xlsx) {
                        $uploadError = 'Không thể đọc file Excel: ' . Shuchkin\SimpleXLSX::parseError();
                        @unlink($targetPath);
                    } else {
                        $analysis = studentImportAnalyzeRows($xlsx->rows(), studentImportBuildContext($conn));
                        $validRows = $analysis['validRows'];
                        $errorRows = $analysis['errorRows'];

                        if (empty($validRows) && empty($errorRows)) {
                            $uploadError = 'File Excel không có dòng dữ liệu nào để xử lý.';
                            @unlink($targetPath);
                        } else {
                            $uploadedFile = $fileName;
                        }
                    }
                }
            }
        }
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="import-container">
    <div class="page-header">
        <h2><i class="fa-solid fa-file-import"></i> Nhập sinh viên từ Excel</h2>
        <a href="student_list.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Quay lại
        </a>
    </div>

    <?php if ($uploadError !== ''): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($validRows) && empty($errorRows)): ?>
        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="upload-area" id="dropZone">
                <div class="upload-icon">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <h3>Kéo thả file Excel vào đây</h3>
                <p class="muted">
                    hoặc bấm để chọn file, chỉ hỗ trợ <strong>.xlsx</strong>
                </p>
                <input type="file" name="excel_file" id="fileInput" accept=".xlsx" required>

                <div class="file-name" id="fileName" style="display:none;">
                    <i class="fa-solid fa-file-excel"></i>
                    <span id="fileNameText"></span>
                </div>

                <div class="upload-actions">
                    <button type="submit" class="btn btn-primary" id="uploadBtn" style="display:none;">
                        <i class="fa-solid fa-upload"></i> Tải lên và xem trước
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
            <h4><i class="fa-solid fa-circle-info"></i> Quy tắc import</h4>
            <p>
                Hệ thống hỗ trợ đúng bộ cột chuẩn:
                <strong>MSSV | Họ tên | Giới tính | Tên Khoa | Lớp | Khóa | SĐT | Email | Địa chỉ</strong>.
            </p>
            <ul>
                <li>Có thể có hàng tiêu đề và hỗ trợ thêm cột <strong>STT</strong> ở đầu file.</li>
                <li>Preview và bước import dùng cùng một bộ rule, nên không còn tình trạng xem trước hợp lệ nhưng import thất bại vì lệch logic.</li>
                <li>MSSV phải duy nhất, đồng thời không được trùng với username đã có trong bảng <code>Users</code>.</li>
                <li>Email là bắt buộc và sẽ được kiểm tra định dạng, trùng email trong file và trùng email đã có trong hệ thống.</li>
                <li>Tài khoản sinh viên mới sẽ dùng <strong>username = MSSV</strong> và <strong>mật khẩu mặc định = MSSV</strong>.</li>
            </ul>
        </div>
    <?php else: ?>
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
                    <form id="confirmForm" action="student_import_action.php" method="POST" style="display:inline;">
                        <input type="hidden" name="filename" value="<?= htmlspecialchars($uploadedFile, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="button" onclick="confirmImport()" class="btn btn-success">
                            <i class="fa-solid fa-file-import"></i> Nhập <?= count($validRows) ?> sinh viên hợp lệ
                        </button>
                    </form>
                <?php endif; ?>

                <a href="student_import.php" class="btn btn-danger">
                    <i class="fa-solid fa-xmark"></i> Hủy bỏ
                </a>
            </div>
        </div>

        <?php if (empty($validRows)): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                Không có dòng hợp lệ để nhập. Vui lòng chỉnh lại file Excel rồi tải lên lại.
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
                    <?php $rowNumber = 1; ?>

                    <?php foreach ($errorRows as $row): ?>
                        <tr class="row-error">
                            <td><?= $rowNumber++ ?></td>
                            <td><span class="status-err"><i class="fa-solid fa-times-circle"></i> Lỗi</span></td>
                            <td><?= htmlspecialchars($row['StudentCode'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['FullName'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['Gender'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['FacultyName'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['ClassName'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $row['CourseYear'] ?: '—' ?></td>
                            <td><?= htmlspecialchars($row['Phone'], ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                            <td><?= htmlspecialchars($row['Email'], ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                            <td class="error-reasons"><?= htmlspecialchars($row['Reasons'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($validRows as $row): ?>
                        <tr>
                            <td><?= $rowNumber++ ?></td>
                            <td><span class="status-ok"><i class="fa-solid fa-check-circle"></i> OK</span></td>
                            <td><?= htmlspecialchars($row['StudentCode'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['FullName'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['Gender'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['FacultyName'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['ClassName'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $row['CourseYear'] ?></td>
                            <td><?= htmlspecialchars($row['Phone'], ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                            <td><?= htmlspecialchars($row['Email'], ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                            <td>—</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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

    if (!dropZone || !fileInput || !uploadForm) {
        return;
    }

    dropZone.addEventListener('click', (event) => {
        if (event.target.closest('.btn')) {
            return;
        }
        fileInput.click();
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropZone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropZone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropZone.classList.remove('dragover');
        });
    });

    dropZone.addEventListener('drop', (event) => {
        const files = event.dataTransfer.files;
        if (files.length === 0) {
            return;
        }

        if (!files[0].name.toLowerCase().endsWith('.xlsx')) {
            Swal.fire('Lỗi', 'Chỉ hỗ trợ file Excel .xlsx', 'error');
            return;
        }

        fileInput.files = files;
        showFileName(files[0].name);
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            showFileName(fileInput.files[0].name);
        }
    });

    uploadForm.addEventListener('submit', () => {
        if (progressContainer) {
            progressContainer.style.display = 'block';
        }
        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
        }

        let progress = 0;
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const intervalId = window.setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) {
                progress = 90;
            }

            if (progressBar) {
                progressBar.style.width = progress + '%';
            }
            if (progressText) {
                progressText.textContent = `Đang phân tích file... ${Math.round(progress)}%`;
            }
        }, 300);

        window.setTimeout(() => window.clearInterval(intervalId), 10000);
    });

    function showFileName(name) {
        if (fileNameText) {
            fileNameText.textContent = name;
        }
        if (fileName) {
            fileName.style.display = 'inline-flex';
        }
        if (uploadBtn) {
            uploadBtn.style.display = 'inline-flex';
        }
    }
});

function confirmImport() {
    Swal.fire({
        title: 'Xác nhận nhập dữ liệu',
        html: `Bạn sắp nhập <strong><?= count($validRows) ?></strong> sinh viên vào hệ thống.<br>
               <small style="color:#64748b">Tài khoản mới sẽ dùng username = MSSV và mật khẩu mặc định = MSSV.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fa-solid fa-file-import"></i> Đồng ý nhập',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        Swal.fire({
            title: 'Đang nhập dữ liệu...',
            html: 'Vui lòng chờ, không tắt trang.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        document.getElementById('confirmForm').submit();
    });
}
</script>
</body>
</html>
