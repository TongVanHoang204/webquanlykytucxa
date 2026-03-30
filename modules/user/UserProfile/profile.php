<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireLogin();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$userID = (int)($_SESSION['UserID'] ?? 0);
if ($userID <= 0) { header('Location: /login.php'); exit; }

$stmt = $conn->prepare("SELECT UserID, Username, FullName, Email, Phone, Role, CreatedAt FROM Users WHERE UserID = ? LIMIT 1");
$stmt->bind_param("i", $userID); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user) die("Không tìm thấy tài khoản.");

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$errorsAccount = []; $okMsgAccount = null; $errorsStudent = []; $okMsgStudent = null;

$st = $conn->prepare("SELECT s.*, f.FacultyName FROM Students s LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE s.UserID = ? LIMIT 1");
$st->bind_param("i", $userID); $st->execute(); $studentRes = $st->get_result(); $st->close();

if (!$studentRes || $studentRes->num_rows === 0) {
    $userInfo = $conn->query("SELECT Username, FullName FROM Users WHERE UserID = $userID LIMIT 1")->fetch_assoc();
    $defaultFullName = $userInfo['FullName'] ?? $userInfo['Username'] ?? 'Sinh viên mới';
    $maxCodeRow = $conn->query("SELECT MAX(CAST(SUBSTRING(StudentCode, 3) AS UNSIGNED)) AS MaxCode FROM Students WHERE StudentCode LIKE 'SV%'")->fetch_assoc();
    $newStudentCode = 'SV' . str_pad(($maxCodeRow['MaxCode'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);
    $ins = $conn->prepare("INSERT INTO Students (UserID, StudentCode, FullName) VALUES (?, ?, ?)");
    $ins->bind_param("iss", $userID, $newStudentCode, $defaultFullName); $ins->execute(); $ins->close();
    $st2 = $conn->prepare("SELECT s.*, f.FacultyName FROM Students s LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE s.UserID = ? LIMIT 1");
    $st2->bind_param("i", $userID); $st2->execute(); $studentRes = $st2->get_result(); $st2->close();
}
$student = $studentRes->fetch_assoc(); $studentID = (int)($student['StudentID'] ?? 0);

$faculties = [];
$fq = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName ASC");
while ($row = $fq->fetch_assoc()) $faculties[] = $row;

$formData = [
    'FullName' => $student['FullName'] ?? '', 'Email' => $student['Email'] ?? '', 'Gender' => $student['Gender'] ?? '',
    'BirthDate' => $student['BirthDate'] ?? '', 'CitizenID' => $student['CitizenID'] ?? '', 'Hometown' => $student['Hometown'] ?? '',
    'Address' => $student['Address'] ?? '', 'Phone' => $student['Phone'] ?? '', 'ClassName' => $student['ClassName'] ?? '',
    'CourseYear' => $student['CourseYear'] ?? '', 'EmergencyContact' => $student['EmergencyContact'] ?? '', 'FacultyID' => $student['FacultyID'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'account') {
        $FullName = trim($_POST['FullName'] ?? ''); $Email = trim($_POST['Email'] ?? ''); $Phone = trim($_POST['Phone'] ?? '');
        if ($FullName === '') $errorsAccount[] = 'Vui lòng nhập họ tên.';
        if ($Email !== '' && !filter_var($Email, FILTER_VALIDATE_EMAIL)) $errorsAccount[] = 'Email không hợp lệ.';
        if ($Phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $Phone)) $errorsAccount[] = 'SĐT không hợp lệ.';
        if (empty($errorsAccount)) {
            $upd = $conn->prepare("UPDATE Users SET FullName=?, Email=?, Phone=? WHERE UserID=?");
            $upd->bind_param("sssi", $FullName, $Email, $Phone, $userID); $upd->execute(); $upd->close();
            $okMsgAccount = "Cập nhật tài khoản thành công!";
            $_SESSION['FullName'] = $FullName;
            $user['FullName'] = $FullName; $user['Email'] = $Email; $user['Phone'] = $Phone;
        }
    }

    if ($formType === 'student') {
        if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) { $errorsStudent[] = "CSRF không hợp lệ."; }
        else {
            foreach (['FullName','Email','Gender','BirthDate','CitizenID','Hometown','Address','Phone','ClassName','CourseYear','EmergencyContact'] as $k) $formData[$k] = trim($_POST[$k] ?? '');
            $formData['FacultyID'] = (int)($_POST['FacultyID'] ?? 0);
            if ($formData['FullName'] === '' || mb_strlen($formData['FullName']) < 3) $errorsStudent[] = "Họ tên >= 3 ký tự.";
            if ($formData['Email'] !== '' && !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) $errorsStudent[] = "Email không hợp lệ.";
            if (!in_array($formData['Gender'], ['Nam','Nữ','Khác',''], true)) $errorsStudent[] = "Giới tính không hợp lệ.";

            $avatarFileName = $student['Avatar'] ?? null;
            if (!empty($_FILES['Avatar']['name'])) {
                $file = $_FILES['Avatar'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext,['jpg','jpeg','png','gif','webp'])) $errorsStudent[] = "Ảnh chỉ chấp nhận jpg/png/gif/webp.";
                    elseif ($file['size'] > 2*1024*1024) $errorsStudent[] = "Ảnh tối đa 2MB.";
                    else {
                        $uploadDir = __DIR__.'/../../../uploads/avatars/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                        $avatarFileName = 'stu_'.$studentID.'_'.time().'.'.$ext;
                        if (!move_uploaded_file($file['tmp_name'], $uploadDir.$avatarFileName)) $errorsStudent[] = "Không thể lưu ảnh.";
                    }
                }
            }
            if (empty($errorsStudent)) {
                $stmt = $conn->prepare("UPDATE Students SET FullName=?, Email=?, Gender=?, BirthDate=NULLIF(?,''), CitizenID=?, Hometown=?, Address=?, Phone=?, ClassName=?, CourseYear=NULLIF(?,''), EmergencyContact=?, FacultyID=NULLIF(?,0), Avatar=? WHERE StudentID=?");
                $stmt->bind_param("sssssssssssisi", $formData['FullName'],$formData['Email'],$formData['Gender'],$formData['BirthDate'],$formData['CitizenID'],$formData['Hometown'],$formData['Address'],$formData['Phone'],$formData['ClassName'],$formData['CourseYear'],$formData['EmergencyContact'],$formData['FacultyID'],$avatarFileName,$studentID);
                $stmt->execute(); $stmt->close();
                $okMsgStudent = "Cập nhật thông tin sinh viên thành công!";
                $student = array_merge($student, $formData, ['Avatar' => $avatarFileName]);
                addLog($conn, $_SESSION['UserID'] ?? null, 'Update student profile', 'Students', 'SV tự cập nhật hồ sơ', 'history');
            }
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
$avatarFile = $student['Avatar'] ?? '';
$avatarPath = !empty($avatarFile) ? $base."uploads/avatars/".htmlspecialchars($avatarFile) : $base."assets/img/avatars/user.png";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .pf-pg { max-width:900px; margin:0 auto; padding:30px 20px; }
        .pf-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .pf-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .pf-head h1 { font-size:1.2rem; font-weight:800; margin:0 0 6px; }
        .pf-head p { font-size:0.88rem; opacity:0.8; margin:0; }

        .pf-tabs { display:flex; gap:4px; margin-bottom:20px; background:var(--bg); border-radius:12px; padding:4px; }
        .pf-tab { flex:1; text-align:center; padding:10px 16px; border-radius:10px; font-size:0.88rem; font-weight:600; cursor:pointer; border:none; background:transparent; color:var(--text-secondary); transition:all 0.2s; }
        .pf-tab.active { background:var(--surface); color:var(--primary); box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .pf-tab:hover:not(.active) { color:var(--text); }
        .pf-pane { display:none; } .pf-pane.active { display:block; }

        .pf-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; margin-bottom:16px; }
        .pf-card h3 { font-size:0.95rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .pf-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media(max-width:600px) { .pf-grid { grid-template-columns:1fr; } }
        .pf-field label { font-size:0.82rem; font-weight:600; color:var(--text); display:block; margin-bottom:6px; }
        .pf-field label .req { color:#ef4444; }
        .pf-field input, .pf-field select { width:100%; padding:10px 14px; border:1px solid var(--stroke); border-radius:10px; font-size:0.88rem; background:var(--bg); color:var(--text); transition:border 0.2s; box-sizing:border-box; }
        .pf-field input:focus, .pf-field select:focus { border-color:var(--primary); outline:none; }
        .pf-field input:disabled { opacity:0.5; cursor:not-allowed; }

        .pf-alert { padding:14px 18px; border-radius:12px; font-size:0.88rem; margin-bottom:16px; }
        .pf-alert.error { background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#ef4444; }
        .pf-alert.success { background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); color:#10b981; }
        .pf-alert ul { margin:6px 0 0 18px; padding:0; }

        .pf-avatar { display:flex; align-items:center; gap:16px; margin-top:14px; }
        .pf-avatar img { width:64px; height:64px; border-radius:16px; object-fit:cover; border:2px solid var(--stroke); }
        .pf-actions { display:flex; gap:10px; flex-wrap:wrap; }
    </style>
</head>
<body>
    <div class="pf-pg">
        <div class="pf-head">
            <h1><i class="fas fa-user-circle"></i> Hồ sơ của bạn</h1>
            <p>Quản lý thông tin tài khoản và hồ sơ sinh viên ký túc xá.</p>
        </div>

        <div class="pf-tabs">
            <button class="pf-tab active" data-tab="tab-account"><i class="fas fa-id-badge"></i> Tài khoản</button>
            <button class="pf-tab" data-tab="tab-student"><i class="fas fa-user-graduate"></i> Sinh viên</button>
        </div>

        <!-- TAB 1: Account -->
        <div class="pf-pane active" id="tab-account">
            <?php if ($errorsAccount): ?>
                <div class="pf-alert error"><strong>Lỗi:</strong><ul><?php foreach($errorsAccount as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
            <?php elseif ($okMsgAccount): ?>
                <div class="pf-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($okMsgAccount) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="form_type" value="account">
                <div class="pf-card">
                    <h3><i class="fas fa-id-badge"></i> Thông tin tài khoản</h3>
                    <div class="pf-grid">
                        <div class="pf-field"><label>Tên đăng nhập</label><input type="text" value="<?= htmlspecialchars($user['Username']) ?>" disabled></div>
                        <div class="pf-field"><label>Vai trò</label><input type="text" value="<?= htmlspecialchars($user['Role']) ?>" disabled></div>
                        <div class="pf-field"><label>Họ và tên <span class="req">*</span></label><input type="text" name="FullName" required value="<?= htmlspecialchars($user['FullName']) ?>"></div>
                        <div class="pf-field"><label>Email</label><input type="email" name="Email" value="<?= htmlspecialchars($user['Email']) ?>"></div>
                        <div class="pf-field"><label>Số điện thoại</label><input type="text" name="Phone" value="<?= htmlspecialchars($user['Phone']) ?>"></div>
                        <div class="pf-field"><label>Ngày tạo TK</label><input type="text" value="<?= htmlspecialchars($user['CreatedAt']) ?>" disabled></div>
                    </div>
                </div>
                <div class="pf-actions">
                    <button type="submit" class="mod-btn mod-btn-primary"><i class="fas fa-save"></i> Lưu thay đổi</button>
                    <a href="<?= $base ?>logout.php" class="mod-btn mod-btn-outline"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
                </div>
            </form>
        </div>

        <!-- TAB 2: Student -->
        <div class="pf-pane" id="tab-student">
            <?php if ($errorsStudent): ?>
                <div class="pf-alert error"><strong>Lỗi:</strong><ul><?php foreach($errorsStudent as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
            <?php elseif ($okMsgStudent): ?>
                <div class="pf-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($okMsgStudent) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="form_type" value="student">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="pf-card">
                    <h3><i class="fas fa-user"></i> Thông tin cá nhân</h3>
                    <div class="pf-grid">
                        <div class="pf-field"><label>Họ và tên <span class="req">*</span></label><input type="text" name="FullName" value="<?= htmlspecialchars($formData['FullName']) ?>" required></div>
                        <div class="pf-field"><label>Email</label><input type="email" name="Email" value="<?= htmlspecialchars($formData['Email']) ?>"></div>
                        <div class="pf-field"><label>Giới tính</label><select name="Gender"><option value="">-- Chọn --</option><option value="Nam" <?=$formData['Gender']=='Nam'?'selected':''?>>Nam</option><option value="Nữ" <?=$formData['Gender']=='Nữ'?'selected':''?>>Nữ</option><option value="Khác" <?=$formData['Gender']=='Khác'?'selected':''?>>Khác</option></select></div>
                        <div class="pf-field"><label>Ngày sinh</label><input type="date" name="BirthDate" value="<?= htmlspecialchars($formData['BirthDate']) ?>"></div>
                        <div class="pf-field"><label>CMND/CCCD</label><input type="text" name="CitizenID" value="<?= htmlspecialchars($formData['CitizenID']) ?>"></div>
                        <div class="pf-field"><label>SĐT</label><input type="text" name="Phone" value="<?= htmlspecialchars($formData['Phone']) ?>"></div>
                        <div class="pf-field"><label>Liên hệ khẩn cấp</label><input type="text" name="EmergencyContact" value="<?= htmlspecialchars($formData['EmergencyContact']) ?>" placeholder="Tên + SĐT người thân"></div>
                    </div>
                </div>

                <div class="pf-card">
                    <h3><i class="fas fa-graduation-cap"></i> Thông tin học tập</h3>
                    <div class="pf-grid">
                        <div class="pf-field"><label>Khoa</label><select name="FacultyID"><option value="0">-- Chọn --</option><?php foreach($faculties as $f): ?><option value="<?=$f['FacultyID']?>" <?=(int)$formData['FacultyID']===(int)$f['FacultyID']?'selected':''?>><?= htmlspecialchars($f['FacultyName'])?></option><?php endforeach; ?></select></div>
                        <div class="pf-field"><label>Lớp</label><input type="text" name="ClassName" value="<?= htmlspecialchars($formData['ClassName']) ?>" placeholder="VD: DCT1234"></div>
                        <div class="pf-field"><label>Niên khóa</label><input type="text" name="CourseYear" value="<?= htmlspecialchars($formData['CourseYear']) ?>" placeholder="VD: 2022"></div>
                    </div>
                </div>

                <div class="pf-card">
                    <h3><i class="fas fa-map-marker-alt"></i> Địa chỉ & Ảnh</h3>
                    <div class="pf-grid">
                        <div class="pf-field"><label>Quê quán</label><input type="text" name="Hometown" value="<?= htmlspecialchars($formData['Hometown']) ?>"></div>
                        <div class="pf-field"><label>Địa chỉ liên lạc</label><input type="text" name="Address" value="<?= htmlspecialchars($formData['Address']) ?>"></div>
                    </div>
                    <div class="pf-avatar">
                        <img src="<?= $avatarPath ?>" onerror="this.onerror=null;this.src='<?= $base ?>assets/img/avatars/user.png';" alt="Avatar">
                        <div>
                            <input type="file" name="Avatar" accept="image/*" style="font-size:0.82rem;">
                            <small style="display:block;margin-top:4px;font-size:0.76rem;color:var(--text-secondary);">Tối đa 2MB · jpg, png, gif, webp</small>
                        </div>
                    </div>
                </div>

                <div class="pf-actions">
                    <a href="<?= $base ?>modules/user/dashboard.php" class="mod-btn mod-btn-outline"><i class="fas fa-arrow-left"></i> Dashboard</a>
                    <button type="submit" class="mod-btn mod-btn-primary"><i class="fas fa-save"></i> Lưu thông tin</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.pf-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.tab;
                document.querySelectorAll('.pf-tab').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.pf-pane').forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                const pane = document.getElementById(id);
                if (pane) pane.classList.add('active');
            });
        });
    });
    </script>

    <?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>