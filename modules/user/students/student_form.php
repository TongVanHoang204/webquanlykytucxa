<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Admin', 'Manager', 'Student']);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$userID = (int)($_SESSION['UserID'] ?? 0);
if ($userID <= 0) { echo "<div style='text-align:center;padding:60px;'>Bạn chưa đăng nhập.</div>"; include __DIR__.'/../../../includes/footer.php'; exit; }

$errors = []; $success = "";

$st = $conn->prepare("SELECT s.*, f.FacultyName FROM Students s LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE s.UserID = ? LIMIT 1");
$st->bind_param("i", $userID); $st->execute(); $studentRes = $st->get_result(); $st->close();

if (!$studentRes || $studentRes->num_rows === 0) {
    $userInfo = $conn->query("SELECT Username, FullName FROM Users WHERE UserID = $userID LIMIT 1")->fetch_assoc();
    $defaultFullName = $userInfo['FullName'] ?? $userInfo['Username'] ?? 'Sinh viên mới';
    $maxCodeRow = $conn->query("SELECT MAX(CAST(SUBSTRING(StudentCode, 3) AS UNSIGNED)) as MaxCode FROM Students WHERE StudentCode LIKE 'SV%'")->fetch_assoc();
    $newStudentCode = 'SV' . str_pad(($maxCodeRow['MaxCode'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);
    $ins = $conn->prepare("INSERT INTO Students (UserID, StudentCode, FullName) VALUES (?, ?, ?)");
    $ins->bind_param("iss", $userID, $newStudentCode, $defaultFullName); $ins->execute(); $ins->close();
    $st2 = $conn->prepare("SELECT s.*, f.FacultyName FROM Students s LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE s.UserID = ? LIMIT 1");
    $st2->bind_param("i", $userID); $st2->execute(); $studentRes = $st2->get_result(); $st2->close();
}
$student = $studentRes->fetch_assoc();
if (!$student) { echo "<div style='text-align:center;padding:60px;'>Không thể tải thông tin.</div>"; include __DIR__.'/../../../includes/footer.php'; exit; }
$studentID = (int)$student['StudentID'];

$faculties = [];
$fq = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName ASC");
if ($fq) { while ($row = $fq->fetch_assoc()) $faculties[] = $row; }

$formData = [
    'FullName' => $student['FullName'] ?? '', 'Email' => $student['Email'] ?? '', 'Gender' => $student['Gender'] ?? '',
    'BirthDate' => $student['BirthDate'] ?? '', 'CitizenID' => $student['CitizenID'] ?? '', 'Hometown' => $student['Hometown'] ?? '',
    'Address' => $student['Address'] ?? '', 'Phone' => $student['Phone'] ?? '', 'ClassName' => $student['ClassName'] ?? '',
    'CourseYear' => $student['CourseYear'] ?? '', 'EmergencyContact' => $student['EmergencyContact'] ?? '', 'FacultyID' => $student['FacultyID'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $errors[] = "CSRF token không hợp lệ.";
    } else {
        $formData['FullName'] = trim($_POST['FullName'] ?? ''); $formData['Email'] = trim($_POST['Email'] ?? '');
        $formData['Gender'] = trim($_POST['Gender'] ?? ''); $formData['BirthDate'] = trim($_POST['BirthDate'] ?? '');
        $formData['CitizenID'] = trim($_POST['CitizenID'] ?? ''); $formData['Hometown'] = trim($_POST['Hometown'] ?? '');
        $formData['Address'] = trim($_POST['Address'] ?? ''); $formData['Phone'] = trim($_POST['Phone'] ?? '');
        $formData['ClassName'] = trim($_POST['ClassName'] ?? ''); $formData['CourseYear'] = trim($_POST['CourseYear'] ?? '');
        $formData['EmergencyContact'] = trim($_POST['EmergencyContact'] ?? ''); $formData['FacultyID'] = (int)($_POST['FacultyID'] ?? 0);

        if ($formData['FullName'] === '' || mb_strlen($formData['FullName']) < 3) $errors[] = "Họ tên phải có ít nhất 3 ký tự.";
        if ($formData['Email'] !== '' && !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email không hợp lệ.";
        if (!in_array($formData['Gender'], ['Nam','Nữ','Khác',''], true)) $errors[] = "Giới tính không hợp lệ.";
        if ($formData['Phone'] !== '' && !preg_match('/^0[0-9]{9,10}$/', $formData['Phone'])) $errors[] = "SĐT phải bắt đầu bằng 0, 10-11 số.";
        if ($formData['CourseYear'] !== '' && !ctype_digit((string)$formData['CourseYear'])) $errors[] = "Niên khóa phải là số.";
        if ($formData['FacultyID'] !== 0 && !in_array($formData['FacultyID'], array_column($faculties, 'FacultyID'))) $errors[] = "Khoa không hợp lệ.";

        $avatarFileName = $student['Avatar'] ?? null;
        if (!empty($_FILES['Avatar']['name'])) {
            $file = $_FILES['Avatar'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) $errors[] = "Ảnh chỉ chấp nhận jpg, png, gif, webp.";
                elseif ($file['size'] > 2*1024*1024) $errors[] = "Ảnh tối đa 2MB.";
                else {
                    $uploadDir = __DIR__.'/../../assets/img/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $avatarFileName = 'stu_'.$studentID.'_'.time().'.'.$ext;
                    if (!move_uploaded_file($file['tmp_name'], $uploadDir.$avatarFileName)) $errors[] = "Không thể lưu ảnh.";
                }
            } else $errors[] = "Lỗi upload ảnh (mã: {$file['error']}).";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE Students SET FullName=?, Email=?, Gender=?, BirthDate=NULLIF(?,''), CitizenID=?, Hometown=?, Address=?, Phone=?, ClassName=?, CourseYear=NULLIF(?,''), EmergencyContact=?, FacultyID=NULLIF(?,0), Avatar=? WHERE StudentID=?");
            $stmt->bind_param("sssssssssssisi", $formData['FullName'], $formData['Email'], $formData['Gender'], $formData['BirthDate'], $formData['CitizenID'], $formData['Hometown'], $formData['Address'], $formData['Phone'], $formData['ClassName'], $formData['CourseYear'], $formData['EmergencyContact'], $formData['FacultyID'], $avatarFileName, $studentID);
            if ($stmt->execute()) {
                $success = "Cập nhật thông tin thành công!";
                addLog($conn, $_SESSION['UserID'] ?? null, 'Update student profile', 'Students', 'SV tự cập nhật hồ sơ', 'history');
            } else $errors[] = "Không thể cập nhật.";
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
$avatarFile = $student['Avatar'] ?? '';
$avatarPath = !empty($avatarFile) ? $base."assets/img/avatars/".htmlspecialchars($avatarFile) : $base."assets/img/avatars/user.png";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoàn thiện hồ sơ sinh viên | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .sf-pg { max-width:900px; margin:0 auto; padding:30px 20px; }
        .sf-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .sf-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .sf-head h1 { font-size:1.2rem; font-weight:800; margin:0 0 6px; }
        .sf-head p { font-size:0.88rem; opacity:0.8; margin:0; }

        .sf-alert { padding:14px 18px; border-radius:12px; font-size:0.88rem; margin-bottom:16px; }
        .sf-alert.error { background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#ef4444; }
        .sf-alert.success { background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); color:#10b981; }
        .sf-alert ul { margin:6px 0 0 18px; padding:0; }

        .sf-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; margin-bottom:16px; }
        .sf-card h3 { font-size:0.95rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .sf-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media(max-width:600px) { .sf-grid { grid-template-columns:1fr; } }
        .sf-field label { font-size:0.82rem; font-weight:600; color:var(--text); display:block; margin-bottom:6px; }
        .sf-field label .req { color:#ef4444; }
        .sf-field input, .sf-field select { width:100%; padding:10px 14px; border:1px solid var(--stroke); border-radius:10px; font-size:0.88rem; background:var(--bg); color:var(--text); transition:border 0.2s; box-sizing:border-box; }
        .sf-field input:focus, .sf-field select:focus { border-color:var(--primary); outline:none; }
        .sf-field small { font-size:0.76rem; color:var(--text-secondary); }

        .sf-avatar { display:flex; align-items:center; gap:16px; }
        .sf-avatar img { width:64px; height:64px; border-radius:16px; object-fit:cover; border:2px solid var(--stroke); }
        .sf-avatar input[type="file"] { font-size:0.82rem; }

        .sf-actions { display:flex; gap:10px; flex-wrap:wrap; }
    </style>
</head>
<body>
    <div class="sf-pg">
        <div class="sf-head">
            <h1><i class="fas fa-user-graduate"></i> Hoàn thiện thông tin sinh viên</h1>
            <p>Vui lòng điền đầy đủ và chính xác thông tin cá nhân.</p>
        </div>

        <?php if (!empty($_SESSION['flash_error_profile'])): ?>
            <div class="sf-alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['flash_error_profile']) ?></div>
            <?php unset($_SESSION['flash_error_profile']); ?>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="sf-alert error"><strong><i class="fas fa-exclamation-triangle"></i> Có lỗi:</strong><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="sf-alert success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- Personal Info -->
            <div class="sf-card">
                <h3><i class="fas fa-user"></i> Thông tin cá nhân</h3>
                <div class="sf-grid">
                    <div class="sf-field"><label>Họ và tên <span class="req">*</span></label><input type="text" name="FullName" value="<?= htmlspecialchars($formData['FullName']) ?>" required></div>
                    <div class="sf-field"><label>Email</label><input type="email" name="Email" value="<?= htmlspecialchars($formData['Email']) ?>" placeholder="email@sinhvien.edu.vn"></div>
                    <div class="sf-field">
                        <label>Giới tính</label>
                        <select name="Gender">
                            <option value="">-- Chọn --</option>
                            <option value="Nam" <?= $formData['Gender']=='Nam'?'selected':'' ?>>Nam</option>
                            <option value="Nữ" <?= $formData['Gender']=='Nữ'?'selected':'' ?>>Nữ</option>
                            <option value="Khác" <?= $formData['Gender']=='Khác'?'selected':'' ?>>Khác</option>
                        </select>
                    </div>
                    <div class="sf-field"><label>Ngày sinh</label><input type="date" name="BirthDate" value="<?= htmlspecialchars($formData['BirthDate']) ?>"></div>
                    <div class="sf-field"><label>CMND/CCCD</label><input type="text" name="CitizenID" value="<?= htmlspecialchars($formData['CitizenID']) ?>"></div>
                    <div class="sf-field"><label>Số điện thoại</label><input type="text" name="Phone" value="<?= htmlspecialchars($formData['Phone']) ?>"></div>
                    <div class="sf-field"><label>Liên hệ khẩn cấp</label><input type="text" name="EmergencyContact" value="<?= htmlspecialchars($formData['EmergencyContact']) ?>" placeholder="Tên + SĐT người thân"></div>
                </div>
            </div>

            <!-- Academic Info -->
            <div class="sf-card">
                <h3><i class="fas fa-graduation-cap"></i> Thông tin học tập</h3>
                <div class="sf-grid">
                    <div class="sf-field">
                        <label>Khoa</label>
                        <select name="FacultyID">
                            <option value="0">-- Chọn khoa --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?= $f['FacultyID'] ?>" <?= (int)$formData['FacultyID']===(int)$f['FacultyID']?'selected':'' ?>><?= htmlspecialchars($f['FacultyName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sf-field"><label>Lớp</label><input type="text" name="ClassName" value="<?= htmlspecialchars($formData['ClassName']) ?>" placeholder="VD: DCT1234"></div>
                    <div class="sf-field"><label>Niên khóa</label><input type="text" name="CourseYear" value="<?= htmlspecialchars($formData['CourseYear']) ?>" placeholder="VD: 2022"></div>
                </div>
            </div>

            <!-- Address & Avatar -->
            <div class="sf-card">
                <h3><i class="fas fa-map-marker-alt"></i> Địa chỉ & Ảnh đại diện</h3>
                <div class="sf-grid">
                    <div class="sf-field"><label>Quê quán</label><input type="text" name="Hometown" value="<?= htmlspecialchars($formData['Hometown']) ?>"></div>
                    <div class="sf-field"><label>Địa chỉ liên lạc</label><input type="text" name="Address" value="<?= htmlspecialchars($formData['Address']) ?>"></div>
                </div>
                <div style="margin-top:14px;">
                    <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:8px;">Ảnh đại diện</label>
                    <div class="sf-avatar">
                        <img src="<?= $avatarPath ?>" onerror="this.onerror=null;this.src='<?= $base ?>assets/img/avatars/user.png';" alt="Avatar">
                        <div>
                            <input type="file" name="Avatar" accept="image/*">
                            <small style="display:block;margin-top:4px;font-size:0.76rem;color:var(--text-secondary);">Tối đa 2MB · jpg, png, gif, webp</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="sf-actions">
                <a href="<?= $base ?>modules/user/dashboard.php" class="mod-btn mod-btn-outline"><i class="fas fa-arrow-left"></i> Quay lại</a>
                <button type="submit" class="mod-btn mod-btn-primary"><i class="fas fa-save"></i> Lưu thông tin</button>
            </div>
        </form>
    </div>

    <?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>