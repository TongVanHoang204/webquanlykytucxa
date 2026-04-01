<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

$conn->set_charset('utf8mb4');

$requestId = (int)($_GET['id'] ?? 0);

if ($requestId <= 0) {
    $_SESSION['message'] = 'Yêu cầu không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}

/**
 * Lấy chi tiết yêu cầu: RoomRequests + Students + Rooms + Buildings
 * LƯU Ý: RoomRequests KHÔNG có cột Deposit, Students KHÔNG có cột Faculty
 */
$sql = "
    SELECT 
        rr.RequestID,
        rr.StudentID,
        rr.RoomID,
        rr.DesiredFrom,
        rr.CheckInDate,
        rr.CheckOutDate,
        rr.Note,
        rr.Status,
        rr.CreatedAt,
        rr.UpdatedAt,
        rr.ReviewedBy,
        rr.ReviewedAt,

        st.FullName       AS StudentName,
        st.StudentCode,
        st.Gender,
        st.FacultyID,
        st.Phone          AS StudentPhone,
        st.IsInDorm,

        r.RoomNumber,
        r.RoomType,
        r.Capacity,
        r.CurrentOccupants,
        r.RoomPrice,
        r.Status          AS RoomStatus,
        r.Description     AS RoomDescription,

        b.BuildingName,
        b.Description     AS BuildingDescription
    FROM roomrequests rr
    JOIN students st ON st.StudentID = rr.StudentID
    JOIN rooms r     ON r.RoomID     = rr.RoomID
    JOIN buildings b ON b.BuildingID = r.BuildingID
    WHERE rr.RequestID = ?
    LIMIT 1
";

$stm = $conn->prepare($sql);
if (!$stm) {
    $_SESSION['message'] = 'Lỗi hệ thống (prepare): ' . $conn->error;
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}
$stm->bind_param('i', $requestId);
$stm->execute();
$res = $stm->get_result();
$request = $res ? $res->fetch_assoc() : null;
$stm->close();

if (!$request) {
    $_SESSION['message'] = 'Không tìm thấy yêu cầu tương ứng.';
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function formatDate($dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00') return '-';
    $ts = strtotime($dateStr);
    if ($ts === false) return e($dateStr);
    return date('d/m/Y', $ts);
}

function formatDateTime($dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00 00:00:00') return '-';
    $ts = strtotime($dateStr);
    if ($ts === false) return e($dateStr);
    return date('d/m/Y H:i', $ts);
}

function statusBadgeClass($status) {
    switch ($status) {
        case 'Chờ duyệt':
            return 'badge pending';
        case 'Đã duyệt':
            return 'badge success';
        case 'Từ chối':
        case 'Đã hủy':
            return 'badge danger';
        default:
            return 'badge secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết yêu cầu đăng ký phòng #<?= (int)$request['RequestID'] ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css"><!-- nếu có -->
    <link rel="stylesheet" href="../../../assets/css/staff/room_requests/staff_request_detail.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
</head>
<body>
<div class="page-wrapper">
    <div class="page-header">
        <h1>
            <i class="fa-solid fa-file-circle-info"></i>
            Chi tiết yêu cầu #<?= (int)$request['RequestID'] ?>
        </h1>
        <span class="<?= statusBadgeClass($request['Status']) ?>">
            <?= e($request['Status']) ?>
        </span>
    </div>

    <div class="grid-2">
        <!-- Cột trái: sinh viên + yêu cầu -->
        <div>
            <!-- Thông tin sinh viên -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-user-graduate"></i>
                    <h2>Thông tin sinh viên</h2>
                </div>
                <div class="detail-row">
                    <label>Họ tên:</label>
                    <span><?= e($request['StudentName']) ?></span>
                </div>
                <div class="detail-row">
                    <label>MSSV:</label>
                    <span><?= e($request['StudentCode']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Giới tính:</label>
                    <span><?= e($request['Gender']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Mã khoa (FacultyID):</label>
                    <span><?= e($request['FacultyID']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Số điện thoại:</label>
                    <span><?= e($request['StudentPhone']) ?></span>
                </div>
            </div>

            <!-- Thông tin yêu cầu -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-file-signature"></i>
                    <h2>Thông tin yêu cầu</h2>
                </div>
                <div class="detail-row">
                    <label>Mã yêu cầu:</label>
                    <span>#<?= (int)$request['RequestID'] ?></span>
                </div>
                <div class="detail-row">
                    <label>Ngày tạo yêu cầu:</label>
                    <span><?= formatDateTime($request['CreatedAt']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Ngày cập nhật:</label>
                    <span><?= formatDateTime($request['UpdatedAt']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Ngày mong muốn (DesiredFrom):</label>
                    <span><?= formatDate($request['DesiredFrom']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Ngày vào (CheckInDate):</label>
                    <span><?= formatDate($request['CheckInDate']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Ngày ra (CheckOutDate):</label>
                    <span><?= formatDate($request['CheckOutDate']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Trạng thái:</label>
                    <span class="<?= statusBadgeClass($request['Status']) ?>">
                        <?= e($request['Status']) ?>
                    </span>
                </div>

                <?php if (!empty($request['ReviewedAt'])): ?>
                    <div class="meta">
                        <i class="fa-regular fa-clock"></i>
                        Đã xử lý lúc: <?= formatDateTime($request['ReviewedAt']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cột phải: phòng + ghi chú -->
        <div>
            <!-- Thông tin phòng -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-bed"></i>
                    <h2>Thông tin phòng đăng ký</h2>
                </div>
                <div class="detail-row">
                    <label>Tòa nhà:</label>
                    <span><?= e($request['BuildingName']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Phòng:</label>
                    <span><?= e($request['RoomNumber']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Loại phòng / Giới hạn:</label>
                    <span><?= e($request['RoomType']) ?></span>
                </div>
                <div class="detail-row">
                    <label>Sức chứa:</label>
                    <span><?= (int)$request['CurrentOccupants'] ?>/<?= (int)$request['Capacity'] ?> người</span>
                </div>
                <div class="detail-row">
                    <label>Giá phòng:</label>
                    <span><?= number_format((float)$request['RoomPrice'], 0, ',', '.') ?> ₫/tháng</span>
                </div>
                <div class="detail-row">
                    <label>Trạng thái phòng:</label>
                    <span><?= e($request['RoomStatus']) ?></span>
                </div>
                <?php if (!empty($request['RoomDescription'])): ?>
                    <div class="meta">
                        <i class="fa-regular fa-sticky-note"></i>
                        <?= e($request['RoomDescription']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Ghi chú sinh viên -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-regular fa-comment-dots"></i>
                    <h2>Ghi chú của sinh viên</h2>
                </div>
                <?php if (!empty($request['Note'])): ?>
                    <div class="note-box">
                        <?= nl2br(e($request['Note'])) ?>
                    </div>
                <?php else: ?>
                    <p class="note-empty">Không có ghi chú thêm.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Thanh hành động -->
    <div class="actions-bar">
        <div class="left-actions">
            <a href="staff_request_list.php" class="btn back">
                <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách
            </a>
        </div>

        <div class="right-actions">
            <?php if ($request['Status'] === 'Chờ duyệt'): ?>
                <a href="#"
                   data-id="<?= (int)$request['RequestID'] ?>"
                   class="btn approve" id="btnApprove">
                    <i class="fa-solid fa-check"></i> Duyệt yêu cầu
                </a>

                <a href="#"
                   data-id="<?= (int)$request['RequestID'] ?>"
                   class="btn reject" id="btnReject">
                    <i class="fa-solid fa-xmark"></i> Từ chối
                </a>
            <?php else: ?>
                <span class="meta">
                    <i class="fa-regular fa-circle-check"></i>
                    Yêu cầu đã được xử lý. Bạn chỉ có thể xem lại thông tin.
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function submitSecurePost(url, payload = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    Object.entries(payload).forEach(([key, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    });

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_csrf';
    csrfInput.value = CSRF_TOKEN;
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        const approveBtn = document.getElementById('btnApprove');
        const rejectBtn  = document.getElementById('btnReject');

        if (approveBtn) {
            approveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const requestId = this.getAttribute('data-id');

                Swal.fire({
                    title: 'Duyệt yêu cầu này?',
                    text: 'Hệ thống sẽ chuyển tới trang duyệt / tạo hợp đồng.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Duyệt ngay',
                    cancelButtonText: 'Hủy',
                    confirmButtonColor: '#27ae60',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitSecurePost('staff_request_approve.php', { id: requestId || '' });
                    }
                });
            });
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const requestId = this.getAttribute('data-id');

                Swal.fire({
                    title: 'Từ chối yêu cầu?',
                    text: 'Bạn chắc chắn muốn từ chối yêu cầu này?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Từ chối',
                    cancelButtonText: 'Hủy',
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#7f8c8d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitSecurePost('request_reject.php', { id: requestId || '' });
                    }
                });
            });
        }
    } catch (error) {
        console.error('Error in staff_request_detail.php:', error);
    }
});
</script>
</body>
</html>
