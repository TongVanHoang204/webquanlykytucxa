<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

/* ===================== CSRF ===================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

/* ===================== Helper ===================== */
function sanitize($s)
{
    return trim($s ?? '');
}

$conn->set_charset('utf8mb4');

/* ===================== LẤY DANH SÁCH USER STUDENT CHƯA LIÊN KẾT ===================== */
/* Điều kiện:
   - Role = 'Student' (trim/uppercase cho chắc)
   - IsActive = 1
   - Chưa tồn tại trong Students.UserID
*/
$availableUsers = [];

$sqlUsers = "
    SELECT 
        u.UserID, u.Username, u.FullName, u.Email
    FROM Users u
    LEFT JOIN Students s 
        ON s.UserID = u.UserID
    WHERE 
        UPPER(TRIM(u.Role)) = 'STUDENT'
        AND COALESCE(u.IsActive, 0) = 1
        AND s.UserID IS NULL
    ORDER BY COALESCE(NULLIF(u.FullName,''), u.Username) ASC
";

if ($res = $conn->query($sqlUsers)) {
    while ($row = $res->fetch_assoc()) {
        $availableUsers[] = $row;
    }
}

/* ===================== LẤY DANH SÁCH KHOA TỪ Faculties ===================== */
$faculties = [];
$facRes = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName");
if ($facRes) {
    while ($f = $facRes->fetch_assoc()) {
        $faculties[] = $f;
    }
}

/* ===================== XỬ LÝ SUBMIT ===================== */
$errors = [];
$okMsg  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ====== CSRF ====== */
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Mã CSRF không hợp lệ. Vui lòng tải lại trang.';
    }

    /* ====== Nhận dữ liệu ====== */
    $StudentCode = sanitize($_POST['StudentCode']);
    $FullName    = sanitize($_POST['FullName']);
    $Email       = sanitize($_POST['Email']);
    $Gender      = sanitize($_POST['Gender']);
    $BirthDate   = sanitize($_POST['BirthDate']);
    $BirthDate   = ($BirthDate !== '') ? $BirthDate : null; // nếu không chọn thì để NULL
    $CitizenID   = sanitize($_POST['CitizenID']);
    $Hometown    = sanitize($_POST['Hometown']);
    $Address     = sanitize($_POST['Address']);
    $Phone       = sanitize($_POST['Phone']);
    $ClassName   = sanitize($_POST['ClassName']);
    $CourseYear  = sanitize($_POST['CourseYear']);
    $IsInDorm    = isset($_POST['IsInDorm']) ? 1 : 0;
    $UserIDPost  = sanitize($_POST['UserID'] ?? '');
    $FacultyID   = isset($_POST['FacultyID']) ? (int)$_POST['FacultyID'] : 0;

    /* ====== Kiểm tra bắt buộc chọn User ====== */
    if ($UserIDPost === '') {
        $errors[] = 'Vui lòng chọn tài khoản người dùng để liên kết.';
    } else {
        $UserID = (int)$UserIDPost;
    }

    /* ====== Validate cơ bản ====== */
    if ($StudentCode === '') $errors[] = 'Vui lòng nhập MSSV.';
    if ($FullName === '')   $errors[] = 'Vui lòng nhập Họ tên.';

    if ($Gender !== '' && !in_array($Gender, ['Nam', 'Nữ'], true))
        $errors[] = 'Giới tính không hợp lệ.';

    if ($Email !== '' && !filter_var($Email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Email không hợp lệ.';

    if ($Phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $Phone))
        $errors[] = 'Số điện thoại không hợp lệ.';

    if ($CourseYear !== '' && !preg_match('/^[0-9]{1,4}$/', $CourseYear))
        $errors[] = 'Khóa học phải là số (vd: 2023).';

    // bắt buộc chọn khoa (theo FacultyID)
    if ($FacultyID <= 0)
        $errors[] = 'Vui lòng chọn Khoa.';

    /* ====== Tránh MSSV trùng ====== */
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT 1 FROM Students WHERE StudentCode = ?");
        $stmt->bind_param("s", $StudentCode);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = 'MSSV đã tồn tại.';
        $stmt->close();
    }

    /* ====== Kiểm tra user hợp lệ ====== */
    if (empty($errors)) {
        $stmt = $conn->prepare("
            SELECT 1 FROM Users 
            WHERE UserID = ? 
              AND COALESCE(IsActive,1) = 1 
              AND UPPER(TRIM(Role)) = 'STUDENT'
              AND UserID NOT IN (
                    SELECT COALESCE(UserID, 0) 
                    FROM Students 
                    WHERE UserID IS NOT NULL
              )
            LIMIT 1
        ");
        $stmt->bind_param("i", $UserID);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $errors[] = 'User đã được liên kết hoặc không hợp lệ. Vui lòng chọn user khác.';
        }
        $stmt->close();
    }

    /* ====== Upload Avatar ====== */
    $avatarRelPath = '';
    if (empty($errors) && !empty($_FILES['Avatar']['name'])) {
        $file = $_FILES['Avatar'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) $errors[] = 'Chỉ chấp nhận ảnh JPG, PNG, WEBP.';
            if ($file['size'] > 2 * 1024 * 1024) $errors[] = 'Ảnh vượt quá 2MB.';

            if (empty($errors)) {
                $dir = '../../../assets/img/avatars/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                $ext      = $allowed[$mime];
                $filename = 'avatar_' . preg_replace('/\W+/', '', $StudentCode ?: 'sv') . '_' . time() . '.' . $ext;
                $dest     = $dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $avatarRelPath = '/assets/img/avatars/' . $filename;
                } else {
                    $errors[] = 'Không thể lưu ảnh.';
                }
            }
        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Tải ảnh thất bại (mã ' . $file['error'] . ').';
        }
    }

    /* ====== INSERT SINH VIÊN (DÙNG FacultyID) ====== */
    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO Students 
            (UserID, StudentCode, FullName, Email, Gender, BirthDate, CitizenID, 
             Hometown, Address, Phone, FacultyID, ClassName, CourseYear, Avatar, 
             IsInDorm, CreatedAt, UpdatedAt)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $errors[] = 'Lỗi hệ thống (prepare insert): ' . $conn->error;
        } else {
            // 17 cột → 17 biến → 17 ký tự type
            // UserID(i),
            // StudentCode–Phone (9 trường) → 9 x s,
            // FacultyID(i),
            // ClassName, CourseYear, Avatar (3 x s),
            // IsInDorm(i),
            // CreatedAt, UpdatedAt (2 x s)
            $stmt->bind_param(
                "isssssssssisssiss",
                $UserID,        // i
                $StudentCode,   // s
                $FullName,      // s
                $Email,         // s
                $Gender,        // s
                $BirthDate,     // s
                $CitizenID,     // s
                $Hometown,      // s
                $Address,       // s
                $Phone,         // s
                $FacultyID,     // i
                $ClassName,     // s
                $CourseYear,    // s
                $avatarRelPath, // s
                $IsInDorm,      // i
                $now,           // s
                $now            // s
            );

            if ($stmt->execute()) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $okMsg = '✅ Đã tạo hồ sơ sinh viên thành công!';
            } else {
                $errors[] = 'Lỗi khi lưu CSDL: ' . $stmt->error;
            }
            $stmt->close();
        }
        addLog(
            $conn,
            $_SESSION['UserID'] ?? null,
            'Update student',
            'Students',
            "Cập nhật hồ sơ sinh viên ID={$studentID}",
            'history'
        );
    }
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Thêm hồ sơ sinh viên | Hệ thống KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/students/staff_student_create.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="container">
        <h2><i class="fa-solid fa-user-graduate"></i> Thêm hồ sơ sinh viên</h2>

        <?php if ($errors): ?>
            <div class="alert error-list">
                <strong>Không thể tạo hồ sơ:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php elseif ($okMsg): ?>
            <div class="alert success"><?= htmlspecialchars($okMsg) ?></div>
            <script>
                setTimeout(() => location.href = 'student_list.php', 1000);
            </script>
        <?php endif; ?>

        <?php if (empty($availableUsers)): ?>
            <div class="alert warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Hiện không còn tài khoản <b>User</b> khả dụng để liên kết.
                Hãy tạo user mới hoặc hủy liên kết ở một hồ sơ khác trước khi tạo.
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">

            <div class="grid">
                <div class="col-6">
                    <label>MSSV <span class="req">*</span></label>
                    <input type="text" name="StudentCode" required
                        value="<?= htmlspecialchars($_POST['StudentCode'] ?? '') ?>"
                        placeholder="Nhập mã số sinh viên">
                </div>
                <div class="col-6">
                    <label>Họ tên <span class="req">*</span></label>
                    <input type="text" name="FullName" required
                        value="<?= htmlspecialchars($_POST['FullName'] ?? '') ?>"
                        placeholder="Nhập họ và tên đầy đủ">
                </div>

                <div class="col-4">
                    <label>Giới tính</label>
                    <select name="Gender">
                        <option value=""> Chọn giới tính </option>
                        <option value="Nam" <?= (($_POST['Gender'] ?? '') === 'Nam') ? 'selected' : '' ?>>Nam</option>
                        <option value="Nữ" <?= (($_POST['Gender'] ?? '') === 'Nữ') ? 'selected' : '' ?>>Nữ</option>
                    </select>
                </div>
                <div class="col-4">
                    <label>Ngày sinh</label>
                    <input type="date" name="BirthDate"
                        value="<?= htmlspecialchars($_POST['BirthDate'] ?? '') ?>">
                </div>
                <div class="col-4">
                    <label>CMND/CCCD</label>
                    <input type="text" name="CitizenID"
                        value="<?= htmlspecialchars($_POST['CitizenID'] ?? '') ?>"
                        placeholder="Số căn cước">
                </div>

                <div class="col-6">
                    <label>Email</label>
                    <input type="email" name="Email"
                        value="<?= htmlspecialchars($_POST['Email'] ?? '') ?>"
                        placeholder="email@example.com">
                </div>
                <div class="col-6">
                    <label>Số điện thoại</label>
                    <input type="text" name="Phone"
                        value="<?= htmlspecialchars($_POST['Phone'] ?? '') ?>"
                        placeholder="+84...">
                </div>

                <!-- KHOA: DÙNG FacultyID + Faculties -->
                <div class="col-6">
                    <label>Khoa <span class="req">*</span></label>
                    <select name="FacultyID" required>
                        <option value=""> Chọn khoa </option>
                        <?php
                        $selectedFacultyID = (int)($_POST['FacultyID'] ?? 0);
                        foreach ($faculties as $f):
                            $fid = (int)$f['FacultyID'];
                            $sel = ($selectedFacultyID === $fid) ? 'selected' : '';
                        ?>
                            <option value="<?= $fid ?>" <?= $sel ?>>
                                <?= htmlspecialchars($f['FacultyName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6">
                    <label>Lớp</label>
                    <input type="text" name="ClassName"
                        value="<?= htmlspecialchars($_POST['ClassName'] ?? '') ?>"
                        placeholder="Tên lớp">
                </div>

                <div class="col-4">
                    <label>Khóa</label>
                    <input type="text" name="CourseYear"
                        value="<?= htmlspecialchars($_POST['CourseYear'] ?? '') ?>"
                        placeholder="VD: 2023">
                </div>
                <div class="col-8">
                    <label>Quê quán</label>
                    <input type="text" name="Hometown"
                        value="<?= htmlspecialchars($_POST['Hometown'] ?? '') ?>"
                        placeholder="Quê quán">
                </div>

                <div class="col-12">
                    <label>Địa chỉ</label>
                    <input type="text" name="Address"
                        value="<?= htmlspecialchars($_POST['Address'] ?? '') ?>"
                        placeholder="Địa chỉ thường trú">
                </div>

                <!-- BẮT BUỘC CHỌN USER -->
                <div class="col-6">
                    <label>Liên kết User <span class="req">*</span></label>
                    <select name="UserID" required <?= empty($availableUsers) ? 'disabled' : '' ?>>
                        <option value="">-- Chọn tài khoản Student --</option>
                        <?php
                        $selectedUserID = $_POST['UserID'] ?? '';
                        foreach ($availableUsers as $u):
                            $uid   = (int)$u['UserID'];
                            $label = trim(($u['FullName'] ?: $u['Username']) . ' — ' . ($u['Email'] ?: ''));
                            $sel   = ($selectedUserID !== '' && (int)$selectedUserID === $uid) ? 'selected' : '';
                        ?>
                            <option value="<?= $uid ?>" <?= $sel ?>>
                                #<?= $uid ?> · <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="hint">
                        Bắt buộc chọn một tài khoản <b>Student</b> (đang hoạt động, chưa liên kết).
                    </small>
                </div>

                <div class="col-12">
                    <label>Ảnh đại diện</label>
                    <input type="file" name="Avatar" accept="image/*" id="inpAvatar">
                    <img id="preview" class="avatar-preview" style="display:none">
                </div>

                <div class="col-12 actions">
                    <button class="btn" type="submit"
                        <?= empty($availableUsers) ? 'disabled title="Không còn user khả dụng để liên kết"' : '' ?>>
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span class="btn-text">Lưu hồ sơ</span>
                    </button>
                    <a class="btn ghost" href="student_list.php">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span class="btn-text">Quay lại danh sách</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Preview avatar
        document.getElementById('inpAvatar').addEventListener('change', e => {
            const f = e.target.files[0];
            const p = document.getElementById('preview');
            if (!f) return p.style.display = 'none';
            const reader = new FileReader();
            reader.onload = ev => {
                p.src = ev.target.result;
                p.style.display = 'block';
            };
            reader.readAsDataURL(f);
        });

        // Check chọn User trước khi submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const selectUser = this.querySelector('select[name="UserID"]');
            if (!selectUser.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Thiếu thông tin',
                    text: 'Vui lòng chọn tài khoản người dùng để liên kết trước khi lưu.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            const btn = this.querySelector('button[type="submit"]');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
        });

        // Auto-focus
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelector('input[name="StudentCode"]').focus();
        });
    </script>
</body>

</html>