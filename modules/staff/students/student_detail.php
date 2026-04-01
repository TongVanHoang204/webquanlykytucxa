<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

/* ===================== Helper ===================== */
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function date_vn($s)
{
    return $s ? date('d/m/Y', strtotime($s)) : '-';
}

function yesNoChip($isInDorm, $hasActiveContract = false)
{
    if ($hasActiveContract) {
        return "<span class='chip yes'>Đang ở</span>";
    } elseif ((int)$isInDorm === 0 && !$hasActiveContract) {
        return "<span class='chip no'>Đã rời</span>";
    } else {
        return "<span class='chip pending'>Chưa đăng ký</span>";
    }
}

function avatarUrl(?string $path, ?string $gender): string
{
    $fallback = '/assets/img/avatars/user.png';
    if ($gender === 'Nam') $fallback = '/assets/img/avatars/user.png';
    elseif ($gender === 'Nữ') $fallback = '/assets/img/avatars/user.png';

    $rel = trim((string)$path);
    if ($rel === '') return $fallback;
    if ($rel[0] !== '/') $rel = '/' . $rel;
    return e($rel);
}

$conn->set_charset('utf8mb4');

/* ===================== Đọc tham số ===================== */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "<h3>Yêu cầu không hợp lệ.</h3>";
    exit;
}

/* ===================== Lấy thông tin sinh viên + khoa + phòng hiện tại ===================== */
/*
 * - JOIN Faculties để lấy FacultyName theo FacultyID
 * - Lấy HĐ 'Hiệu lực' mới nhất (nếu có) làm phòng hiện tại
 */
$sql = "
SELECT 
    s.StudentID, s.UserID, s.StudentCode, s.FullName, s.Gender,
    s.BirthDate, s.CitizenID, s.Email, s.Phone,
    s.FacultyID, f.FacultyName,
    s.ClassName, s.CourseYear, s.Hometown, s.Address,
    s.Avatar, s.IsInDorm, s.CreatedAt, s.UpdatedAt,

    c.ContractID AS CurContractID,
    c.StartDate  AS CurStart,
    c.EndDate    AS CurEnd,
    c.Status     AS CurStatus,
    r.RoomNumber AS CurRoom,
    b.BuildingName AS CurBuilding
FROM Students s
LEFT JOIN Faculties f
    ON f.FacultyID = s.FacultyID
LEFT JOIN Contracts c
    ON c.ContractID = (
        SELECT c2.ContractID
        FROM Contracts c2
        WHERE c2.StudentID = s.StudentID
          AND c2.Status = 'Hiệu lực'
        ORDER BY c2.StartDate DESC, c2.ContractID DESC
        LIMIT 1
    )
LEFT JOIN Rooms r
    ON r.RoomID = c.RoomID
LEFT JOIN Buildings b
    ON b.BuildingID = r.BuildingID
WHERE s.StudentID = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "<h3>Lỗi truy vấn sinh viên.</h3>";
    echo "<pre>" . e($conn->error) . "</pre>";
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$detail) {
    http_response_code(404);
    echo "<h3>Không tìm thấy sinh viên.</h3>";
    exit;
}

/* ===================== Lịch sử hợp đồng (tất cả) ===================== */
$contracts = [];
$csql = "
SELECT 
    c.ContractID,
    c.Status,
    c.StartDate,
    c.EndDate,
    r.RoomNumber,
    b.BuildingName
FROM Contracts c
LEFT JOIN Rooms r
    ON r.RoomID = c.RoomID
LEFT JOIN Buildings b
    ON b.BuildingID = r.BuildingID
WHERE c.StudentID = ?
ORDER BY c.StartDate DESC, c.ContractID DESC
";
$cstmt = $conn->prepare($csql);
if ($cstmt) {
    $cstmt->bind_param("i", $id);
    $cstmt->execute();
    $res = $cstmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $contracts[] = $row;
    }
    $cstmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title>Chi tiết sinh viên | Hệ thống KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/students/staff_student_detail.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container">
    <div class="page-bar">
        <h2><i class="fa-solid fa-id-card-clip"></i> Chi tiết sinh viên</h2>
        <div class="actions">
            <a class="btn" href="student_edit.php?id=<?= (int)$detail['StudentID'] ?>">
                <i class="fa-regular fa-pen-to-square"></i>
                <span class="btn-text">Sửa hồ sơ</span>
            </a>
            <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
                <button class="btn danger"
                        onclick="deleteStudent(<?= (int)$detail['StudentID'] ?>,'<?= e($detail['FullName']) ?>')">
                    <i class="fa-regular fa-trash-can"></i>
                    <span class="btn-text">Xóa hồ sơ</span>
                </button>
            <?php endif; ?>
            <a class="btn ghost" href="student_list.php">
                <i class="fa-solid fa-angles-left"></i>
                <span class="btn-text">Quay lại</span>
            </a>
        </div>
    </div>

    <div class="grid">
        <!-- Cột trái: avatar + trạng thái + phòng hiện tại -->
        <div class="col-4">
            <div class="card profile">
                <img class="avatar"
                     src="<?= avatarUrl($detail['Avatar'] ?? '', $detail['Gender'] ?? null) ?>"
                     alt="avatar">
                <div class="name">
                    <div class="fullname"><?= e($detail['FullName']) ?></div>
                    <div class="code">MSSV: <strong><?= e($detail['StudentCode']) ?></strong></div>
                </div>
                <div class="state">
                    <?= yesNoChip((int)$detail['IsInDorm'], !empty($detail['CurContractID'])) ?>
                </div>

                <div class="room-block">
                    <div class="title"><i class="fa-solid fa-bed"></i> Phòng hiện tại</div>
                    <?php if (!empty($detail['CurRoom'])): ?>
                        <div class="room">
                            <strong><?= e($detail['CurBuilding']) ?> <?= e($detail['CurRoom']) ?></strong>
                            <div class="muted">
                                HĐ #<?= (int)$detail['CurContractID'] ?> •
                                <?= e($detail['CurStatus']) ?> •
                                <?= date_vn($detail['CurStart']) ?> – <?= date_vn($detail['CurEnd']) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="muted">Chưa xếp phòng</div>
                    <?php endif; ?>
                </div>

                <div class="times">
                    <div><span class="label">Ngày tạo:</span> <?= date_vn($detail['CreatedAt']) ?></div>
                    <div><span class="label">Cập nhật cuối:</span> <?= date_vn($detail['UpdatedAt']) ?></div>
                </div>
            </div>
        </div>

        <!-- Cột phải: thông tin cá nhân + lịch sử hợp đồng -->
        <div class="col-8">
            <div class="card">
                <div class="card-title">
                    <i class="fa-solid fa-user"></i> Thông tin cá nhân
                </div>
                <div class="info-grid">
                    <div class="row">
                        <span class="k">Họ tên</span>
                        <span class="v"><?= e($detail['FullName']) ?></span>
                    </div>
                    <div class="row">
                        <span class="k">MSSV</span>
                        <span class="v"><?= e($detail['StudentCode']) ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Giới tính</span>
                        <span class="v"><?= e($detail['Gender'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Ngày sinh</span>
                        <span class="v"><?= date_vn($detail['BirthDate']) ?></span>
                    </div>
                    <div class="row">
                        <span class="k">CMND/CCCD</span>
                        <span class="v"><?= e($detail['CitizenID'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Email</span>
                        <span class="v"><?= e($detail['Email'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Số điện thoại</span>
                        <span class="v"><?= e($detail['Phone'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Khoa</span>
                        <span class="v"><?= e($detail['FacultyName'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Lớp</span>
                        <span class="v"><?= e($detail['ClassName'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Khóa</span>
                        <span class="v"><?= e($detail['CourseYear'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Quê quán</span>
                        <span class="v"><?= e($detail['Hometown'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">Địa chỉ</span>
                        <span class="v"><?= e($detail['Address'] ?: '-') ?></span>
                    </div>
                    <div class="row">
                        <span class="k">UserID</span>
                        <span class="v"><?= $detail['UserID'] ? (int)$detail['UserID'] : '-' ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="fa-solid fa-file-signature"></i> Lịch sử hợp đồng
                </div>
                <?php if (count($contracts) > 0): ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Hợp đồng</th>
                                <th>Phòng</th>
                                <th>Trạng thái</th>
                                <th>Bắt đầu</th>
                                <th>Kết thúc</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($contracts as $c): ?>
                                <tr>
                                    <td>#<?= (int)$c['ContractID'] ?></td>
                                    <td><?= e(($c['BuildingName'] ?: '-') . ' ' . ($c['RoomNumber'] ?: '-')) ?></td>
                                    <td><span class="badge"><?= e($c['Status']) ?></span></td>
                                    <td><?= date_vn($c['StartDate']) ?></td>
                                    <td><?= date_vn($c['EndDate']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty">
                        <i class="fa-regular fa-inbox"></i>
                        <p>Chưa có hợp đồng nào.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function deleteStudent(id, name) {
    Swal.fire({
        title: 'Xác nhận xóa',
        html: `Bạn có chắc muốn xóa hồ sơ <b>${name}</b>?<br><small class='muted'>Hành động này không thể hoàn tác.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fa-regular fa-trash-can"></i> Xóa',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    }).then((res) => {
        if (!res.isConfirmed) return;
        fetch('student_delete_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ id, _csrf: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã xóa',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                setTimeout(() => { window.location.href = 'student_list.php'; }, 700);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: data.message || 'Không thể xóa'
                });
            }
        })
        .catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: 'Không thể kết nối máy chủ'
            });
        });
    });
}
</script>
</body>
</html>
