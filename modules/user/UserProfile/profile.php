<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireLogin();

// Bật debug mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$userID = (int)($_SESSION['UserID'] ?? 0);
if ($userID <= 0) {
    header('Location: /login.php');
    exit;
}

/* =========================
   1. LẤY THÔNG TIN TÀI KHOẢN
========================= */
$sqlUser = "
    SELECT UserID, Username, FullName, Email, Phone, Role, CreatedAt
    FROM Users
    WHERE UserID = ?
    LIMIT 1
";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("i", $userID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Không tìm thấy thông tin tài khoản.");
}

/* =========================
   2. CSRF CHO FORM SINH VIÊN
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* =========================
   3. LẤY / TẠO THÔNG TIN SINH VIÊN
========================= */
$errorsAccount = [];
$okMsgAccount  = null;

$errorsStudent = [];
$okMsgStudent  = null;

$studentSql = "
    SELECT s.*, f.FacultyName
    FROM Students s
    LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID
    WHERE s.UserID = ?
    LIMIT 1
";
$st = $conn->prepare($studentSql);
$st->bind_param("i", $userID);
$st->execute();
$studentRes = $st->get_result();
$st->close();

// Nếu chưa có record Students -> tạo mới
if (!$studentRes || $studentRes->num_rows === 0) {
    $userInfo = $conn->query("SELECT Username, FullName FROM Users WHERE UserID = $userID LIMIT 1")->fetch_assoc();
    $defaultFullName = $userInfo['FullName'] ?? $userInfo['Username'] ?? 'Sinh viên mới';

    $maxCodeQuery = $conn->query("
        SELECT MAX(CAST(SUBSTRING(StudentCode, 3) AS UNSIGNED)) AS MaxCode
        FROM Students
        WHERE StudentCode LIKE 'SV%'
    ");
    $maxCodeRow = $maxCodeQuery->fetch_assoc();
    $nextNumber = ($maxCodeRow['MaxCode'] ?? 0) + 1;
    $newStudentCode = 'SV' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

    $insertStmt = $conn->prepare("INSERT INTO Students (UserID, StudentCode, FullName) VALUES (?, ?, ?)");
    $insertStmt->bind_param("iss", $userID, $newStudentCode, $defaultFullName);
    $insertStmt->execute();
    $insertStmt->close();

    // lấy lại
    $st2 = $conn->prepare($studentSql);
    $st2->bind_param("i", $userID);
    $st2->execute();
    $studentRes = $st2->get_result();
    $st2->close();
}

$student = $studentRes->fetch_assoc();
$studentID = (int)($student['StudentID'] ?? 0);

// Lấy danh sách khoa
$faculties = [];
$fq = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName ASC");
while ($row = $fq->fetch_assoc()) {
    $faculties[] = $row;
}

// Giá trị mặc định form sinh viên
$formData = [
    'FullName'         => $student['FullName'] ?? '',
    'Email'            => $student['Email'] ?? '',
    'Gender'           => $student['Gender'] ?? '',
    'BirthDate'        => $student['BirthDate'] ?? '',
    'CitizenID'        => $student['CitizenID'] ?? '',
    'Hometown'         => $student['Hometown'] ?? '',
    'Address'          => $student['Address'] ?? '',
    'Phone'            => $student['Phone'] ?? '',
    'ClassName'        => $student['ClassName'] ?? '',
    'CourseYear'       => $student['CourseYear'] ?? '',
    'EmergencyContact' => $student['EmergencyContact'] ?? '',
    'FacultyID'        => $student['FacultyID'] ?? '',
];

/* =========================
   4. XỬ LÝ SUBMIT 2 FORM
      - form_type = account | student
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    /* ----- FORM HỒ SƠ TÀI KHOẢN ----- */
    if ($formType === 'account') {
        $FullName = trim($_POST['FullName'] ?? '');
        $Email    = trim($_POST['Email'] ?? '');
        $Phone    = trim($_POST['Phone'] ?? '');

        if ($FullName === '') {
            $errorsAccount[] = 'Vui lòng nhập họ tên.';
        }
        if ($Email !== '' && !filter_var($Email, FILTER_VALIDATE_EMAIL)) {
            $errorsAccount[] = 'Email không hợp lệ.';
        }
        if ($Phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $Phone)) {
            $errorsAccount[] = 'Số điện thoại không hợp lệ.';
        }

        if (empty($errorsAccount)) {
            $upd = "
                UPDATE Users
                SET FullName = ?, Email = ?, Phone = ?
                WHERE UserID = ?
            ";
            $stmt = $conn->prepare($upd);
            $stmt->bind_param("sssi", $FullName, $Email, $Phone, $userID);
            $stmt->execute();
            $stmt->close();

            $okMsgAccount = "✅ Cập nhật thông tin tài khoản thành công!";
            $_SESSION['FullName'] = $FullName;

            $user['FullName'] = $FullName;
            $user['Email']    = $Email;
            $user['Phone']    = $Phone;
        }
    }

    /* ----- FORM THÔNG TIN SINH VIÊN ----- */
    if ($formType === 'student') {

        if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
            $errorsStudent[] = "Yêu cầu không hợp lệ (CSRF token không khớp). Vui lòng tải lại trang.";
        } else {
            // lấy dữ liệu
            $formData['FullName']         = trim($_POST['FullName'] ?? '');
            $formData['Email']            = trim($_POST['Email'] ?? '');
            $formData['Gender']           = trim($_POST['Gender'] ?? '');
            $formData['BirthDate']        = trim($_POST['BirthDate'] ?? '');
            $formData['CitizenID']        = trim($_POST['CitizenID'] ?? '');
            $formData['Hometown']         = trim($_POST['Hometown'] ?? '');
            $formData['Address']          = trim($_POST['Address'] ?? '');
            $formData['Phone']            = trim($_POST['Phone'] ?? '');
            $formData['ClassName']        = trim($_POST['ClassName'] ?? '');
            $formData['CourseYear']       = trim($_POST['CourseYear'] ?? '');
            $formData['EmergencyContact'] = trim($_POST['EmergencyContact'] ?? '');
            $formData['FacultyID']        = (int)($_POST['FacultyID'] ?? 0);

            // validate
            if ($formData['FullName'] === '' || mb_strlen($formData['FullName']) < 3) {
                $errorsStudent[] = "Họ tên phải có ít nhất 3 ký tự.";
            }
            if ($formData['Email'] !== '' && !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
                $errorsStudent[] = "Email không hợp lệ.";
            }
            $allowedGender = ['Nam', 'Nữ', 'Khác', ''];
            if (!in_array($formData['Gender'], $allowedGender, true)) {
                $errorsStudent[] = "Giới tính không hợp lệ.";
            }
            if ($formData['Phone'] !== '' && !preg_match('/^0[0-9]{9,10}$/', $formData['Phone'])) {
                $errorsStudent[] = "Số điện thoại phải bắt đầu bằng 0 và có 10–11 chữ số.";
            }
            if ($formData['CourseYear'] !== '' && !ctype_digit((string)$formData['CourseYear'])) {
                $errorsStudent[] = "Niên khóa (CourseYear) phải là số.";
            }

            if ($formData['FacultyID'] !== 0) {
                $validFacultyIds = array_column($faculties, 'FacultyID');
                if (!in_array($formData['FacultyID'], $validFacultyIds)) {
                    $errorsStudent[] = "Khoa không hợp lệ.";
                }
            }

            // Upload avatar
            $avatarFileName = $student['Avatar'] ?? null;
            if (!empty($_FILES['Avatar']['name'])) {
                $file = $_FILES['Avatar'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (!in_array($ext, $allowedExt)) {
                        $errorsStudent[] = "Ảnh đại diện chỉ chấp nhận: jpg, jpeg, png, gif, webp.";
                    } elseif ($file['size'] > 2 * 1024 * 1024) {
                        $errorsStudent[] = "Ảnh đại diện không được lớn hơn 2MB.";
                    } else {
                        $uploadDir  = __DIR__ . '/../../../uploads/avatars/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $avatarFileName = 'stu_' . $studentID . '_' . time() . '.' . $ext;
                        $destPath = $uploadDir . $avatarFileName;

                        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                            $errorsStudent[] = "Không thể lưu file ảnh đại diện. Vui lòng thử lại.";
                        }
                    }
                } else {
                    $errorsStudent[] = "Lỗi upload ảnh đại diện (mã lỗi: {$file['error']}).";
                }
            }

            if (empty($errorsStudent)) {
                $updateSql = "
                    UPDATE Students
                    SET FullName        = ?,
                        Email           = ?,
                        Gender          = ?,
                        BirthDate       = NULLIF(?, ''),
                        CitizenID       = ?,
                        Hometown        = ?,
                        Address         = ?,
                        Phone           = ?,
                        ClassName       = ?,
                        CourseYear      = NULLIF(?, ''),
                        EmergencyContact= ?,
                        FacultyID       = NULLIF(?, 0),
                        Avatar          = ?
                    WHERE StudentID = ?
                ";

                $stmt = $conn->prepare($updateSql);
                $courseYear = $formData['CourseYear'] === '' ? null : (int)$formData['CourseYear'];
                $facultyId  = $formData['FacultyID'] === 0 ? null : $formData['FacultyID'];

                $stmt->bind_param(
                    "sssssssssssisi",
                    $formData['FullName'],
                    $formData['Email'],
                    $formData['Gender'],
                    $formData['BirthDate'],
                    $formData['CitizenID'],
                    $formData['Hometown'],
                    $formData['Address'],
                    $formData['Phone'],
                    $formData['ClassName'],
                    $formData['CourseYear'],
                    $formData['EmergencyContact'],
                    $facultyId,
                    $avatarFileName,
                    $studentID
                );
                $stmt->execute();
                $stmt->close();

                $okMsgStudent = "✅ Cập nhật thông tin sinh viên thành công.";

                // cập nhật lại mảng $student
                $student = array_merge($student, $formData, ['Avatar' => $avatarFileName]);
                addLog(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'Update student profile',
                    'Students',
                    'Sinh viên tự cập nhật hồ sơ của mình',
                    'history'
                );
            }
        }
    }
}



require_once __DIR__ . '/../../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/user/UserProfile/profile.css">
<link rel="stylesheet" href="/assets/css/user/students/profile_student.css">

<div class="profile-container">

    <!-- HEADER + TAB -->
    <div class="page-header with-tabs">
        <div class="page-title">
            <h2><i class="fa-solid fa-user-circle"></i> Hồ sơ của bạn</h2>
            <p>Quản lý thông tin tài khoản và hồ sơ sinh viên ký túc xá.</p>
        </div>

        <div class="profile-tabs-nav">
            <button type="button" class="profile-tab-link active" data-tab="tab-account">
                <i class="fa-solid fa-id-badge"></i> Hồ sơ cá nhân
            </button>
            <button type="button" class="profile-tab-link" data-tab="tab-student">
                <i class="fa-solid fa-user-graduate"></i> Thông tin sinh viên
            </button>
        </div>
    </div>

    <div class="profile-tabs-content">
        <!-- TAB 1: HỒ SƠ TÀI KHOẢN -->
        <div class="profile-tab-pane active" id="tab-account">
            <?php if ($errorsAccount): ?>
                <div class="alert error">
                    <strong>Không thể cập nhật:</strong>
                    <ul>
                        <?php foreach ($errorsAccount as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($okMsgAccount): ?>
                <div class="alert success"><?= htmlspecialchars($okMsgAccount) ?></div>
            <?php endif; ?>

            <form method="post" class="profile-form">
                <input type="hidden" name="form_type" value="account">

                <div class="profile-grid">
                    <div class="col-6">
                        <label>Tên đăng nhập</label>
                        <input type="text" value="<?= htmlspecialchars($user['Username']) ?>" disabled>
                    </div>

                    <div class="col-6">
                        <label>Vai trò</label>
                        <input type="text" value="<?= htmlspecialchars($user['Role']) ?>" disabled>
                    </div>

                    <div class="col-6">
                        <label>Họ và tên <span class="req">*</span></label>
                        <input type="text" name="FullName" required value="<?= htmlspecialchars($user['FullName']) ?>">
                    </div>

                    <div class="col-6">
                        <label>Email</label>
                        <input type="email" name="Email" value="<?= htmlspecialchars($user['Email']) ?>">
                    </div>

                    <div class="col-6">
                        <label>Số điện thoại</label>
                        <input type="text" name="Phone" value="<?= htmlspecialchars($user['Phone']) ?>">
                    </div>

                    <div class="col-12">
                        <label>Ngày tạo tài khoản</label>
                        <input type="text" value="<?= htmlspecialchars($user['CreatedAt']) ?>" disabled>
                    </div>

                    <div class="col-12 actions">
                        <button class="btn" type="submit">
                            <i class="fa-solid fa-save"></i> Lưu thay đổi
                        </button>
                        <a class="btn ghost" href="/logout.php">
                            <i class="fa-solid fa-sign-out-alt"></i> Đăng xuất
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- TAB 2: THÔNG TIN SINH VIÊN -->
        <div class="profile-tab-pane" id="tab-student">
            <?php if ($errorsStudent): ?>
                <div class="alert alert-danger">
                    <h4><i class="fa-solid fa-triangle-exclamation"></i> Có lỗi xảy ra</h4>
                    <ul>
                        <?php foreach ($errorsStudent as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($okMsgStudent): ?>
                <div class="alert alert-success">
                    <h4><i class="fa-solid fa-circle-check"></i> Thành công</h4>
                    <p><?= htmlspecialchars($okMsgStudent, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="student-form">
                <input type="hidden" name="form_type" value="student">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-grid">
                    <!-- Cột trái -->
                    <div class="form-column">
                        <div class="form-group">
                            <label>Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="FullName"
                                value="<?= htmlspecialchars($formData['FullName'], ENT_QUOTES, 'UTF-8') ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="Email"
                                value="<?= htmlspecialchars($formData['Email'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="email@sinhvien.edu.vn">
                        </div>

                        <div class="form-group">
                            <label>Giới tính</label>
                            <select name="Gender">
                                <option value="">-- Chọn giới tính --</option>
                                <option value="Nam" <?= $formData['Gender'] == 'Nam'  ? 'selected' : '' ?>>Nam</option>
                                <option value="Nữ" <?= $formData['Gender'] == 'Nữ'   ? 'selected' : '' ?>>Nữ</option>
                                <option value="Khác" <?= $formData['Gender'] == 'Khác' ? 'selected' : '' ?>>Khác</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Ngày sinh</label>
                            <input type="date" name="BirthDate"
                                value="<?= htmlspecialchars($formData['BirthDate'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label>CMND/CCCD</label>
                            <input type="text" name="CitizenID"
                                value="<?= htmlspecialchars($formData['CitizenID'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label>Số điện thoại</label>
                            <input type="text" name="Phone"
                                value="<?= htmlspecialchars($formData['Phone'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label>Liên hệ khẩn cấp</label>
                            <input type="text" name="EmergencyContact"
                                value="<?= htmlspecialchars($formData['EmergencyContact'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Họ tên + SĐT người thân">
                        </div>
                    </div>

                    <!-- Cột phải -->
                    <div class="form-column">
                        <div class="form-group">
                            <label>Khoa</label>
                            <select name="FacultyID">
                                <option value="0">-- Chọn khoa --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?= $f['FacultyID'] ?>"
                                        <?= ((int)$formData['FacultyID'] === (int)$f['FacultyID']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['FacultyName'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Lớp</label>
                            <input type="text" name="ClassName"
                                value="<?= htmlspecialchars($formData['ClassName'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="VD: DCT1234">
                        </div>

                        <div class="form-group">
                            <label>Niên khóa</label>
                            <input type="text" name="CourseYear"
                                value="<?= htmlspecialchars($formData['CourseYear'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="VD: 2022">
                        </div>

                        <div class="form-group">
                            <label>Quê quán</label>
                            <input type="text" name="Hometown"
                                value="<?= htmlspecialchars($formData['Hometown'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label>Địa chỉ liên lạc</label>
                            <input type="text" name="Address"
                                value="<?= htmlspecialchars($formData['Address'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label>Ảnh đại diện</label>
                            <div class="avatar-preview">
                                <?php
                                $avatarFile = $student['Avatar'] ?? '';
                                if (!empty($avatarFile)) {
                                    $avatarPath = "/assets/img/avatars/" . htmlspecialchars($avatarFile, ENT_QUOTES, 'UTF-8');
                                } else {
                                    $avatarPath = "/assets/img/avatars/user.png";
                                }
                                ?>
                                <img src="<?= $avatarPath ?>"
                                    alt="Avatar"
                                    onerror="this.onerror=null;this.src='/assets/img/avatars/user.png';">
                            </div>
                            <input type="file" name="Avatar" accept="image/*">
                            <small class="text-muted">Tối đa 2MB, hỗ trợ: jpg, jpeg, png, gif, webp.</small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../dashboard.php" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Lưu thông tin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

<script>
    // JS chuyển tab
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.profile-tab-link');
        const tabPanes = document.querySelectorAll('.profile-tab-pane');

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-tab');
                if (!targetId) return;

                tabButtons.forEach(b => b.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));

                btn.classList.add('active');
                const pane = document.getElementById(targetId);
                if (pane) pane.classList.add('active');
            });
        });
    });
</script>