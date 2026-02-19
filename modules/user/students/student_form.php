<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db_connect.php';

require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Admin', 'Manager', 'Student']); // chỉ sinh viên

// ===== CSRF TOKEN =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$userID = (int)($_SESSION['UserID'] ?? 0);
if ($userID <= 0) {
    echo "<div class='alert alert-danger text-center'>Bạn chưa đăng nhập.</div>";
    include __DIR__ . '/../../../includes/footer.php';
    exit;
}

$errors  = [];
$success = "";

// ===== LẤY THÔNG TIN SINH VIÊN HIỆN TẠI =====
$studentSql = "
    SELECT s.*, f.FacultyName
    FROM Students s
    LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID
    WHERE s.UserID = ?
    LIMIT 1
";
$st = $conn->prepare($studentSql);
if (!$st) {
    die("Lỗi SQL (studentSql): " . $conn->error);
}
$st->bind_param("i", $userID);
$st->execute();
$studentRes = $st->get_result();
$st->close();

// ===== TỰ ĐỘNG TẠO HỒ SƠ SINH VIÊN NẾU CHƯA TỒN TẠI =====
if (!$studentRes || $studentRes->num_rows === 0) {
    // Lấy thông tin từ bảng Users
    $userInfo = $conn->query("SELECT Username, FullName FROM Users WHERE UserID = $userID LIMIT 1")->fetch_assoc();
    $defaultFullName = $userInfo['FullName'] ?? $userInfo['Username'] ?? 'Sinh viên mới';

    // Tạo StudentCode tự động
    $maxCodeQuery = $conn->query("SELECT MAX(CAST(SUBSTRING(StudentCode, 3) AS UNSIGNED)) as MaxCode FROM Students WHERE StudentCode LIKE 'SV%'");
    $maxCodeRow = $maxCodeQuery->fetch_assoc();
    $nextNumber = ($maxCodeRow['MaxCode'] ?? 0) + 1;
    $newStudentCode = 'SV' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

    // Insert record mới
    $insertStmt = $conn->prepare("INSERT INTO Students (UserID, StudentCode, FullName) VALUES (?, ?, ?)");
    $insertStmt->bind_param("iss", $userID, $newStudentCode, $defaultFullName);
    $insertStmt->execute();
    $insertStmt->close();

    // Lấy lại thông tin vừa tạo
    $st2 = $conn->prepare($studentSql);
    $st2->bind_param("i", $userID);
    $st2->execute();
    $studentRes = $st2->get_result();
    $st2->close();
}

$student = $studentRes->fetch_assoc();
if (!$student) {
    echo "<div class='alert alert-danger text-center'>Không thể tải thông tin sinh viên. Vui lòng thử lại.</div>";
    include __DIR__ . '/../../../includes/footer.php';
    exit;
}
$studentID = (int)$student['StudentID'];

// ===== LẤY DANH SÁCH KHOA =====
$faculties = [];
$fq = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName ASC");
if ($fq) {
    while ($row = $fq->fetch_assoc()) {
        $faculties[] = $row;
    }
}

// ===== GIÁ TRỊ MẶC ĐỊNH CHO FORM =====
$formData = [
    'FullName'        => $student['FullName'] ?? '',
    'Email'           => $student['Email'] ?? '',
    'Gender'          => $student['Gender'] ?? '',
    'BirthDate'       => $student['BirthDate'] ?? '',
    'CitizenID'       => $student['CitizenID'] ?? '',
    'Hometown'        => $student['Hometown'] ?? '',
    'Address'         => $student['Address'] ?? '',
    'Phone'           => $student['Phone'] ?? '',
    'ClassName'       => $student['ClassName'] ?? '',
    'CourseYear'      => $student['CourseYear'] ?? '',
    'EmergencyContact' => $student['EmergencyContact'] ?? '',
    'FacultyID'       => $student['FacultyID'] ?? '',
];

// ===== XỬ LÝ SUBMIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $errors[] = "Yêu cầu không hợp lệ (CSRF token không khớp). Vui lòng tải lại trang.";
    } else {

        // Lấy dữ liệu từ form
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

        // ===== VALIDATION =====
        if ($formData['FullName'] === '' || mb_strlen($formData['FullName']) < 3) {
            $errors[] = "Họ tên phải có ít nhất 3 ký tự.";
        }

        if ($formData['Email'] !== '' && !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ.";
        }

        $allowedGender = ['Nam', 'Nữ', 'Khác', ''];
        if (!in_array($formData['Gender'], $allowedGender, true)) {
            $errors[] = "Giới tính không hợp lệ.";
        }

        if ($formData['Phone'] !== '' && !preg_match('/^0[0-9]{9,10}$/', $formData['Phone'])) {
            $errors[] = "Số điện thoại phải bắt đầu bằng 0 và có 10–11 chữ số.";
        }

        if ($formData['CourseYear'] !== '' && !ctype_digit((string)$formData['CourseYear'])) {
            $errors[] = "Niên khóa (CourseYear) phải là số.";
        }

        // Kiểm tra FacultyID có nằm trong danh sách không (nếu chọn)
        if ($formData['FacultyID'] !== 0) {
            $validFacultyIds = array_column($faculties, 'FacultyID');
            if (!in_array($formData['FacultyID'], $validFacultyIds)) {
                $errors[] = "Khoa không hợp lệ.";
            }
        }

        // ===== XỬ LÝ UPLOAD AVATAR (nếu có) =====
        $avatarFileName = $student['Avatar'] ?? null;

        if (!empty($_FILES['Avatar']['name'])) {
            $file = $_FILES['Avatar'];

            if ($file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($ext, $allowedExt)) {
                    $errors[] = "Ảnh đại diện chỉ chấp nhận các định dạng: jpg, jpeg, png, gif, webp.";
                } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB
                    $errors[] = "Ảnh đại diện không được lớn hơn 2MB.";
                } else {
                    $uploadDir  = __DIR__ . '/../../assets/img/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $avatarFileName = 'stu_' . $studentID . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . $avatarFileName;

                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        $errors[] = "Không thể lưu file ảnh đại diện. Vui lòng thử lại.";
                    }
                }
            } else {
                $errors[] = "Lỗi upload ảnh đại diện (mã lỗi: {$file['error']}).";
            }
        }

        // ===== UPDATE NẾU KHÔNG CÓ LỖI =====
        if (empty($errors)) {
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
            if (!$stmt) {
                $errors[] = "Không thể chuẩn bị câu lệnh cập nhật: " . $conn->error;
            } else {
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

                if ($stmt->execute()) {
                    $success = "Cập nhật thông tin sinh viên thành công.";
                    addLog(
                        $conn,
                        $_SESSION['UserID'] ?? null,
                        'Update student profile',
                        'Students',
                        'Sinh viên tự cập nhật hồ sơ của mình',
                        'history'
                    );
                } else {
                    $errors[] = "Không thể cập nhật dữ liệu. Vui lòng thử lại.";
                }
                $stmt->close();
            }
        }
    }
    // Hàm kiểm tra hồ sơ đã đầy đủ chưa
    function isProfileCompleted(array $stu): bool
    {
        // Danh sách field BẮT BUỘC phải có dữ liệu
        $requiredFields = [
            'FullName',
            'Email',
            'Gender',
            'BirthDate',
            'CitizenID',
            'Hometown',
            'Address',
            'Phone',
            'ClassName',
            'CourseYear',
            'EmergencyContact',
            'FacultyID',
            // 'Avatar', // nếu muốn bắt buộc có ảnh thì bỏ comment dòng này
        ];

        foreach ($requiredFields as $field) {
            // Nếu chưa tồn tại key hoặc rỗng -> coi như chưa hoàn thành
            if (!isset($stu[$field]) || $stu[$field] === '' || $stu[$field] === null) {
                return false;
            }
        }

        return true;
    }

    // Nếu chưa có record Students hoặc hồ sơ chưa hoàn thiện -> chuyển hướng về trang hoàn thiện hồ sơ
    if (!$student || !isProfileCompleted($student)) {
        $_SESSION['flash_error_profile'] = "Bạn cần hoàn thiện đầy đủ thông tin sinh viên trước khi đăng ký phòng.";
        header("Location: /modules/user/students/profile_student.php"); // đổi đúng path file hồ sơ của bạn
        exit;
    }
}
require_once __DIR__ . '/../../../includes/header.php';
?>

<head>
    <meta charset="UTF-8">
    <title>Hoàn thiện thông tin sinh viên</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/user/students/profile_student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container student-form-page">
        <div class="page-header">
            <h1><i class="fa-solid fa-user-graduate"></i> Hoàn thiện thông tin sinh viên</h1>
            <p>Vui lòng điền đầy đủ và chính xác thông tin cá nhân của bạn.</p>
        </div>
        <?php if (!empty($_SESSION['flash_error_profile'])): ?>
            <div class="alert alert-warning">
                <p><?= htmlspecialchars($_SESSION['flash_error_profile'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php unset($_SESSION['flash_error_profile']); ?>
        <?php endif; ?>


        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h4><i class="fa-solid fa-triangle-exclamation"></i> Có lỗi xảy ra</h4>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <h4><i class="fa-solid fa-circle-check"></i> Thành công</h4>
                <p><?= $success ?></p>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="student-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-grid">
                <!-- Cột trái: thông tin cơ bản -->
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

                <!-- Cột phải: học tập & địa chỉ -->
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

                            // Nếu có ảnh trong DB → lấy từ /assets/img/avatars/
                            if (!empty($avatarFile)) {
                                $avatarPath = "/assets/img/avatars/" . htmlspecialchars($avatarFile, ENT_QUOTES, 'UTF-8');
                            } else {
                                // Avatar mặc định
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

    <?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>

</html>