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
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function sanitize($s)
{
    return trim($s ?? '');
}
function avatarPublicPath($filename)
{
    return '/assets/img/avatars/' . $filename;
}
function avatarDiskPathFromUrl($url)
{
    return '../../../' . ltrim($url, '/');
}

$conn->set_charset('utf8mb4');

/* ===================== Lấy id & dữ liệu ===================== */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "<h3>Yêu cầu không hợp lệ.</h3>";
    exit;
}

/* Lấy thông tin sinh viên + Faculty hiện tại */
$stmt = $conn->prepare("
    SELECT s.*, f.FacultyName
    FROM Students s
    LEFT JOIN Faculties f ON f.FacultyID = s.FacultyID
    WHERE s.StudentID = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    http_response_code(404);
    echo "<h3>Không tìm thấy sinh viên.</h3>";
    exit;
}

/* ===================== LẤY DANH SÁCH KHOA ===================== */
$faculties = [];
$facRes = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName");
if ($facRes) {
    while ($f = $facRes->fetch_assoc()) {
        $faculties[] = $f;
    }
}

/* ===================== XỬ LÝ SUBMIT ===================== */
$errors = [];
$okMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* CSRF */
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Mã CSRF không hợp lệ. Vui lòng tải lại trang.';
    }

    /* Nhận dữ liệu */
    $StudentCode = sanitize($_POST['StudentCode']);
    $FullName    = sanitize($_POST['FullName']);
    $Email       = sanitize($_POST['Email']);
    $Gender      = sanitize($_POST['Gender']);
    $BirthDate   = sanitize($_POST['BirthDate']);
    $CitizenID   = sanitize($_POST['CitizenID']);
    $Hometown    = sanitize($_POST['Hometown']);
    $Address     = sanitize($_POST['Address']);
    $Phone       = sanitize($_POST['Phone']);
    $ClassName   = sanitize($_POST['ClassName']);
    $CourseYear  = sanitize($_POST['CourseYear']);
    $IsInDorm    = isset($_POST['IsInDorm']) ? 1 : 0; // nếu có dùng
    $UserID      = sanitize($_POST['UserID']);
    $delAvatar   = isset($_POST['DeleteAvatar']) ? 1 : 0;
    $FacultyID   = isset($_POST['FacultyID']) ? (int)$_POST['FacultyID'] : 0;

    /* Validate cơ bản */
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

    if ($FacultyID <= 0)
        $errors[] = 'Vui lòng chọn Khoa.';

    /* MSSV không trùng */
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT 1 FROM Students WHERE StudentCode = ? AND StudentID <> ? LIMIT 1");
        $stmt->bind_param("si", $StudentCode, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = 'MSSV đã tồn tại ở hồ sơ khác.';
        $stmt->close();
    }

    /* Xử lý avatar */
    $newAvatarUrl = null;
    $removeOld = false;

    if (empty($errors)) {
        // Nếu tick xóa avatar
        if ($delAvatar && !empty($student['Avatar'])) {
            $removeOld = true;
            $newAvatarUrl = null;
        }

        // Nếu upload ảnh mới
        if (!empty($_FILES['Avatar']['name'])) {
            $file = $_FILES['Avatar'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Tải ảnh thất bại (mã lỗi ' . $file['error'] . ').';
            } else {
                if ($file['size'] > 5 * 1024 * 1024) {
                    $errors[] = 'Ảnh vượt quá 5MB.';
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'heic', 'heif', 'avif'];
                if (!in_array($ext, $allowedExt)) {
                    $errors[] = 'Định dạng không được hỗ trợ. Hãy dùng ảnh jpg, png, webp,...';
                }

                if (empty($errors)) {
                    $dirDisk = '../../../assets/img/avatars/';
                    if (!is_dir($dirDisk)) mkdir($dirDisk, 0777, true);

                    $filename = 'avatar_' . preg_replace('/\W+/', '', $StudentCode ?: 'sv') . '_' . time() . '.' . $ext;
                    $destDisk = $dirDisk . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $destDisk)) {
                        $errors[] = 'Không thể lưu ảnh lên máy chủ.';
                    } else {
                        $newAvatarUrl = avatarPublicPath($filename);
                        if (!empty($student['Avatar'])) $removeOld = true;
                    }
                }
            }
        }
    }

    /* Chuẩn hóa & UPDATE */
    if (empty($errors)) {
        $UserID      = ($UserID === '' ? null : (int)$UserID);
        $Email       = ($Email === '' ? null : $Email);
        $BirthDate   = ($BirthDate === '' ? null : $BirthDate);
        $CitizenID   = ($CitizenID === '' ? null : $CitizenID);
        $Hometown    = ($Hometown === '' ? null : $Hometown);
        $Address     = ($Address === '' ? null : $Address);
        $Phone       = ($Phone === '' ? null : $Phone);
        $ClassName   = ($ClassName === '' ? null : $ClassName);
        $CourseYear  = ($CourseYear === '' ? null : $CourseYear);
        $Gender      = ($Gender === '' ? null : $Gender);
        $FacultyID   = ($FacultyID > 0 ? $FacultyID : null);

        if ($newAvatarUrl === null && !$delAvatar) {
            $newAvatarUrl = $student['Avatar']; // giữ avatar cũ
        }

        $now = date('Y-m-d H:i:s');

        $sql = "UPDATE Students SET
    UserID = ?, 
    StudentCode = ?, 
    FullName = ?, 
    Email = ?, 
    Gender = ?, 
    BirthDate = ?, 
    CitizenID = ?,
    Hometown = ?, 
    Address = ?, 
    Phone = ?, 
    FacultyID = ?, 
    ClassName = ?, 
    CourseYear = ?, 
    Avatar = ?,
    IsInDorm = ?, 
    UpdatedAt = ?
WHERE StudentID = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "isssssssssisssisi",
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
            $newAvatarUrl,  // s
            $IsInDorm,      // i
            $now,           // s
            $id             // i
        );

        // Chuỗi types chuẩn (không có khoảng trắng):
        // "isssssssssisssisi"
        //  i UserID
        //  s StudentCode
        //  s FullName
        //  s Email
        //  s Gender
        //  s BirthDate
        //  s CitizenID
        //  s Hometown
        //  s Address
        //  s Phone
        //  i FacultyID
        //  s ClassName
        //  s CourseYear
        //  s Avatar
        //  i IsInDorm
        //  s UpdatedAt
        //  i StudentID
        $stmt->bind_param(
            "isssssssssisssisi",
            $UserID,
            $StudentCode,
            $FullName,
            $Email,
            $Gender,
            $BirthDate,
            $CitizenID,
            $Hometown,
            $Address,
            $Phone,
            $FacultyID,
            $ClassName,
            $CourseYear,
            $newAvatarUrl,
            $IsInDorm,
            $now,
            $id
        );

        if ($stmt->execute()) {
            // Xóa avatar cũ nếu có
            if ($removeOld && !empty($student['Avatar'])) {
                $oldDisk = avatarDiskPathFromUrl($student['Avatar']);
                if (@is_file($oldDisk)) @unlink($oldDisk);
            }

            // Cập nhật lại token + dữ liệu hiển thị
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $CSRF = $_SESSION['csrf_token'];

            $okMsg = '✅ Đã cập nhật hồ sơ sinh viên.';

            $student = array_merge($student, [
                'UserID'      => $UserID,
                'StudentCode' => $StudentCode,
                'FullName'    => $FullName,
                'Email'       => $Email,
                'Gender'      => $Gender,
                'BirthDate'   => $BirthDate,
                'CitizenID'   => $CitizenID,
                'Hometown'    => $Hometown,
                'Address'     => $Address,
                'Phone'       => $Phone,
                'FacultyID'   => $FacultyID,
                'ClassName'   => $ClassName,
                'CourseYear'  => $CourseYear,
                'Avatar'      => $newAvatarUrl,
                'IsInDorm'    => $IsInDorm,
                'UpdatedAt'   => $now
            ]);
        } else {
            $errors[] = 'Lỗi khi cập nhật CSDL: ' . $stmt->error;
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


?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Sửa hồ sơ sinh viên | Hệ thống KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/students/staff_student_edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div id="staff-student-edit">
        <div class="container">
            <div class="page-title">
                <h2><i class="fa-regular fa-pen-to-square"></i> Sửa hồ sơ sinh viên</h2>
                <div>
                    <a class="btn white" href="student_detail.php?id=<?= (int)$student['StudentID'] ?>">
                        <i class="fa-solid fa-angles-left"></i> Quay lại chi tiết
                    </a>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert error-list">
                    <strong>Không thể cập nhật:</strong>
                    <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php elseif ($okMsg): ?>
                <div class="alert success"><?= e($okMsg) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="frmEdit">
                <input type="hidden" name="csrf_token" value="<?= e($CSRF) ?>">

                <div class="grid">
                    <div class="col-6">
                        <label>MSSV <span class="req">*</span></label>
                        <input type="text" name="StudentCode" required
                            value="<?= e($student['StudentCode']) ?>"
                            placeholder="Nhập mã số sinh viên">
                    </div>
                    <div class="col-6">
                        <label>Họ tên <span class="req">*</span></label>
                        <input type="text" name="FullName" required
                            value="<?= e($student['FullName']) ?>"
                            placeholder="Nhập họ và tên đầy đủ">
                    </div>

                    <div class="col-4">
                        <label>Giới tính</label>
                        <select name="Gender">
                            <option value="">-- Chọn giới tính --</option>
                            <option value="Nam" <?= ($student['Gender'] === 'Nam') ? 'selected' : ''; ?>>Nam</option>
                            <option value="Nữ" <?= ($student['Gender'] === 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label>Ngày sinh</label>
                        <input type="date" name="BirthDate"
                            value="<?= e($student['BirthDate']) ?>">
                    </div>
                    <div class="col-4">
                        <label>CMND/CCCD</label>
                        <input type="text" name="CitizenID"
                            value="<?= e($student['CitizenID']) ?>"
                            placeholder="Số căn cước">
                    </div>

                    <div class="col-6">
                        <label>Email</label>
                        <input type="email" name="Email"
                            value="<?= e($student['Email']) ?>"
                            placeholder="email@example.com">
                    </div>
                    <div class="col-6">
                        <label>Số điện thoại</label>
                        <input type="text" name="Phone"
                            value="<?= e($student['Phone']) ?>"
                            placeholder="+84...">
                    </div>

                    <!-- Khoa dùng FacultyID -->
                    <div class="col-6">
                        <label>Khoa <span class="req">*</span></label>
                        <select name="FacultyID" required>
                            <option value="">-- Chọn khoa --</option>
                            <?php
                            $currentFacultyID = (int)($student['FacultyID'] ?? 0);
                            foreach ($faculties as $f):
                                $fid = (int)$f['FacultyID'];
                                $sel = ($currentFacultyID === $fid) ? 'selected' : '';
                            ?>
                                <option value="<?= $fid ?>" <?= $sel ?>>
                                    <?= e($f['FacultyName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6">
                        <label>Lớp</label>
                        <input type="text" name="ClassName"
                            value="<?= e($student['ClassName']) ?>"
                            placeholder="Tên lớp">
                    </div>

                    <div class="col-4">
                        <label>Khóa</label>
                        <input type="text" name="CourseYear"
                            value="<?= e($student['CourseYear']) ?>"
                            placeholder="VD: 2023">
                    </div>
                    <div class="col-8">
                        <label>Quê quán</label>
                        <input type="text" name="Hometown"
                            value="<?= e($student['Hometown']) ?>"
                            placeholder="Quê quán">
                    </div>

                    <div class="col-12">
                        <label>Địa chỉ</label>
                        <input type="text" name="Address"
                            value="<?= e($student['Address']) ?>"
                            placeholder="Địa chỉ thường trú">
                    </div>

                    <div class="col-6">
                        <label>Liên kết UserID (tùy chọn)</label>
                        <input type="number" name="UserID" min="1"
                            value="<?= e($student['UserID']) ?>"
                            placeholder="ID người dùng">
                    </div>

                    <!-- Avatar + crop -->
                    <div class="col-12">
                        <div class="avatar-upload-section">
                            <label>Ảnh đại diện</label>
                            <div class="avatar-preview-container">
                                <?php if (!empty($student['Avatar'])): ?>
                                    <img id="preview" class="avatar-preview"
                                        src="<?= e($student['Avatar'][0] == '/' ? $student['Avatar'] : '/' . $student['Avatar']) ?>"
                                        alt="preview">
                                <?php else: ?>
                                    <img id="preview" class="avatar-preview" style="display:none">
                                <?php endif; ?>

                                <div class="avatar-controls">
                                    <input type="file" name="Avatar" accept="image/*" id="inpAvatar">
                                    <?php if (!empty($student['Avatar'])): ?>
                                        <label class="check-inline">
                                            <input type="checkbox" name="DeleteAvatar">
                                            <span>Xóa avatar hiện tại</span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="muted">
                                Chọn ảnh → cắt tròn trước khi lưu. Hỗ trợ JPG/PNG/WEBP, tối đa 5MB.
                            </div>
                        </div>
                    </div>

                    <div class="col-12 actions space-between">
                        <a class="btn ghost" href="student_detail.php?id=<?= (int)$student['StudentID'] ?>">
                            <i class="fa-solid fa-arrow-left"></i> Quay lại
                        </a>
                        <button class="btn" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Cropper giữ nguyên -->
        <div class="cmodal" id="cropModal" aria-hidden="true">
            <div class="cmodal__box">
                <div class="cmodal__head">
                    <span><i class="fa-solid fa-crop"></i> Cắt ảnh đại diện</span>
                    <button type="button" class="btn gray sm" id="btnCloseCrop">
                        <i class="fa-solid fa-times"></i> Đóng
                    </button>
                </div>
                <div class="cmodal__body">
                    <div class="cropper-wrap">
                        <img id="cropImg" alt="crop source">
                    </div>
                    <div class="preview-side">
                        <div class="preview-circle">
                            <img id="circlePreview" alt="preview">
                        </div>
                        <div class="muted">Xem trước ảnh tròn (120x120px)</div>
                    </div>
                </div>
                <div class="cmodal__foot">
                    <div class="cmodal-controls">
                        <button type="button" class="btn gray sm" id="btnRotateL">
                            <i class="fa-solid fa-rotate-left"></i> Xoay trái
                        </button>
                        <button type="button" class="btn gray sm" id="btnRotateR">
                            <i class="fa-solid fa-rotate-right"></i> Xoay phải
                        </button>
                        <button type="button" class="btn gray sm" id="btnZoomIn">
                            <i class="fa-solid fa-magnifying-glass-plus"></i> Zoom in
                        </button>
                        <button type="button" class="btn gray sm" id="btnZoomOut">
                            <i class="fa-solid fa-magnifying-glass-minus"></i> Zoom out
                        </button>
                    </div>
                    <button type="button" class="btn" id="btnApplyCrop">
                        <i class="fa-solid fa-crop"></i> Cắt & dùng
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const root = document.getElementById('staff-student-edit');
            const inp = root.querySelector('#inpAvatar');
            const preview = root.querySelector('#preview');

            const modal = root.querySelector('#cropModal');
            const cropImg = root.querySelector('#cropImg');
            const circlePreview = root.querySelector('#circlePreview');
            const btnClose = root.querySelector('#btnCloseCrop');
            const btnApply = root.querySelector('#btnApplyCrop');
            const btnRotateL = root.querySelector('#btnRotateL');
            const btnRotateR = root.querySelector('#btnRotateR');
            const btnZoomIn = root.querySelector('#btnZoomIn');
            const btnZoomOut = root.querySelector('#btnZoomOut');

            let cropper = null,
                originalFile = null;

            const openModal = () => modal.classList.add('open');
            const closeModal = () => {
                modal.classList.remove('open');
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
            };

            if (inp) {
                inp.addEventListener('change', function() {
                    const f = this.files && this.files[0];
                    if (!f) return;
                    originalFile = f;

                    const reader = new FileReader();
                    reader.onload = ev => {
                        cropImg.src = ev.target.result;
                        circlePreview.src = ev.target.result;
                        openModal();

                        cropper = new Cropper(cropImg, {
                            viewMode: 1,
                            aspectRatio: 1,
                            background: false,
                            autoCropArea: 1,
                            movable: true,
                            zoomable: true,
                            responsive: true,
                            ready() {
                                const cp = this.cropper;
                                this.cropper.crop();
                                cropImg.addEventListener('crop', () => {
                                    const canvas = cp.getCroppedCanvas({
                                        width: 240,
                                        height: 240
                                    });
                                    if (canvas) circlePreview.src = canvas.toDataURL('image/jpeg', 0.9);
                                });
                            }
                        });
                    };
                    reader.readAsDataURL(f);
                });
            }

            btnClose.addEventListener('click', closeModal);
            btnRotateL.addEventListener('click', () => cropper && cropper.rotate(-90));
            btnRotateR.addEventListener('click', () => cropper && cropper.rotate(90));
            btnZoomIn.addEventListener('click', () => cropper && cropper.zoom(0.1));
            btnZoomOut.addEventListener('click', () => cropper && cropper.zoom(-0.1));

            btnApply.addEventListener('click', () => {
                if (!cropper) return;
                const canvas = cropper.getCroppedCanvas({
                    width: 512,
                    height: 512,
                    imageSmoothingQuality: 'high'
                });
                if (!canvas) return closeModal();

                canvas.toBlob((blob) => {
                    if (!blob) return closeModal();

                    const base = (originalFile && originalFile.name ? originalFile.name : 'avatar')
                        .replace(/\.(jpg|jpeg|png|webp|gif)$/i, '');
                    const newFile = new File([blob], base + '_cropped.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });

                    const dt = new DataTransfer();
                    dt.items.add(newFile);
                    inp.files = dt.files;

                    const url = URL.createObjectURL(newFile);
                    preview.src = url;
                    preview.style.display = 'block';

                    closeModal();
                }, 'image/jpeg', 0.92);
            });

            // Check nhẹ trước submit
            document.getElementById('frmEdit').addEventListener('submit', function(e) {
                const code = this.StudentCode.value.trim();
                const name = this.FullName.value.trim();
                const faculty = this.FacultyID.value;
                if (!code || !name || !faculty) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Thiếu thông tin',
                        text: 'MSSV, Họ tên và Khoa là bắt buộc.'
                    });
                }
            });
        })();
    </script>
</body>

</html>