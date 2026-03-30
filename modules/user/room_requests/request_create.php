<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Student', 'Manager']); // sinh viên hoặc admin

/* ===== Nhận room_id truyền từ room_detail.php (nếu có) ===== */
$preRoomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

/* ===== CSRF ===== */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['_csrf'];

function s($v)
{
    return trim($v ?? '');
}

$conn->set_charset('utf8mb4');
$errors = [];
$okMsg  = null;

/* ===== Lấy hồ sơ sinh viên theo UserID trong session ===== */
$currentUserId = (int)($_SESSION['UserID'] ?? 0);
$student = null;

if ($currentUserId <= 0) {
    $errors[] = 'Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại.';
} else {
    // Dùng query() cho đơn giản, an toàn vì đã ép kiểu int
    $sqlStudent = "
        SELECT StudentID, FullName, Gender, StudentCode
        FROM Students
        WHERE UserID = {$currentUserId}
        LIMIT 1
    ";
    $studentRes = $conn->query($sqlStudent);

    if ($studentRes && $studentRes->num_rows > 0) {
        $student = $studentRes->fetch_assoc();
    } else {
        $errors[] = 'Tài khoản của bạn chưa được liên kết hồ sơ sinh viên. Vui lòng liên hệ quản trị viên.';
    }
}

/* ===== Lấy danh sách phòng có thể đăng ký ===== */
/* Đếm số SV đang ở từ Contracts (Status = "Hiệu lực") */
$rooms = [];
$sqlRooms = "
    SELECT 
        r.RoomID,
        r.RoomNumber AS RoomCode,
        COALESCE(r.RoomType, '') AS GenderLimit,
        COALESCE(r.Capacity, 0) AS Capacity,
        COALESCE(c.ActiveCount, 0) AS CurrentOccupancy
    FROM Rooms r
    LEFT JOIN (
        SELECT RoomID, COUNT(*) AS ActiveCount
        FROM Contracts
        WHERE Status = 'Hiệu lực'
        GROUP BY RoomID
    ) c ON c.RoomID = r.RoomID
    WHERE r.Status = 'Trống'
";

if ($preRoomId > 0) {
    // Nếu từ room_detail.php sang, chỉ lấy đúng 1 phòng đó
    $sqlRooms .= " AND r.RoomID = {$preRoomId} ";
}

$sqlRooms .= " ORDER BY r.RoomNumber";

if ($rs = $conn->query($sqlRooms)) {
    while ($row = $rs->fetch_assoc()) {
        $rooms[] = $row;
    }
}

/* ===== Xử lý submit ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {

    // Check CSRF
    if (!isset($_POST['_csrf']) || $_POST['_csrf'] !== $_SESSION['_csrf']) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ. Vui lòng tải lại trang.';
    }

    $RoomID       = (int)($_POST['RoomID'] ?? 0);
    $CheckInDate  = s($_POST['CheckInDate'] ?? '');
    $CheckOutDate = s($_POST['CheckOutDate'] ?? '');
    // DesiredFrom = ngày vào ở (không lấy từ form nữa)
    $DesiredFrom = $CheckInDate;
    $Deposit      = s($_POST['Deposit'] ?? '');
    $Note         = s($_POST['Note'] ?? '');

    // Validate cơ bản
    if ($RoomID <= 0) {
        $errors[] = 'Vui lòng chọn phòng muốn đăng ký.';
    }
    if ($CheckInDate === '') {
        $errors[] = 'Vui lòng nhập ngày vào ở.';
    } elseif (strtotime($CheckInDate) === false) {
        $errors[] = 'Ngày vào ở không hợp lệ.';
    }
    if ($CheckOutDate === '') {
        $errors[] = 'Vui lòng nhập ngày kết thúc.';
    } elseif (strtotime($CheckOutDate) === false) {
        $errors[] = 'Ngày kết thúc không hợp lệ.';
    }
    if ($CheckInDate && $CheckOutDate && strtotime($CheckInDate) > strtotime($CheckOutDate)) {
        $errors[] = 'Ngày vào ở phải trước ngày kết thúc.';
    }

    if ($Deposit === '') {
        $errors[] = 'Vui lòng nhập số tiền cọc.';
    } elseif (!is_numeric($Deposit) || (float)$Deposit < 0) {
        $errors[] = 'Tiền cọc phải là số dương.';
    }

    if ($Note !== '' && mb_strlen($Note) > 500) {
        $errors[] = 'Ghi chú tối đa 500 ký tự.';
    }

    /* Kiểm tra lại thông tin phòng trong CSDL */
    if (empty($errors)) {
        $sqlCheckRoom = "
            SELECT 
                r.RoomID,
                r.RoomNumber AS RoomCode,
                COALESCE(r.RoomType,'') AS GenderLimit,
                COALESCE(r.Capacity,0) AS Capacity,
                COALESCE(c.ActiveCount,0) AS CurrentOccupancy
            FROM Rooms r
            LEFT JOIN (
                SELECT RoomID, COUNT(*) AS ActiveCount
                FROM Contracts
                WHERE Status = 'Hiệu lực'
                GROUP BY RoomID
            ) c ON c.RoomID = r.RoomID
            WHERE r.RoomID = ? AND r.Status = 'Trống'
            LIMIT 1
        ";

        $rstm = $conn->prepare($sqlCheckRoom);
        if (!$rstm) {
            $errors[] = 'Lỗi SQL (check room): ' . $conn->error;
        } else {
            $rstm->bind_param('i', $RoomID);
            $rstm->execute();
            $roomRes = $rstm->get_result();
            $room = $roomRes ? $roomRes->fetch_assoc() : null;
            $rstm->close();

            if (!$room) {
                $errors[] = 'Phòng không hợp lệ hoặc đã bị khóa.';
            } else {
                // kiểm tra sức chứa
                if ((int)$room['Capacity'] > 0 && (int)$room['CurrentOccupancy'] >= (int)$room['Capacity']) {
                    $errors[] = 'Phòng đã đủ số lượng. Vui lòng chọn phòng khác.';
                }

                // kiểm tra giới tính
                $roomGender = strtoupper(trim($room['GenderLimit'] ?? ''));
                $stuGender  = strtoupper(trim($student['Gender'] ?? ''));
                if ($roomGender !== '' && in_array($stuGender, ['NAM', 'NỮ'], true)) {
                    if (($roomGender === 'NAM' && $stuGender !== 'NAM') ||
                        ($roomGender === 'NỮ' && $stuGender !== 'NỮ')
                    ) {
                        $errors[] = 'Phòng không phù hợp giới tính của bạn.';
                    }
                }
            }
        }
    }

    /* Chặn sinh viên gửi nhiều yêu cầu "Chờ duyệt" */
    if (empty($errors)) {
        $cstm = $conn->prepare("
            SELECT 1 FROM RoomRequests
            WHERE StudentID = ? AND Status = 'Chờ duyệt'
            LIMIT 1
        ");
        if (!$cstm) {
            $errors[] = 'Lỗi SQL (check request): ' . $conn->error;
        } else {
            $cstm->bind_param('i', $student['StudentID']);
            $cstm->execute();
            $cstm->store_result();
            if ($cstm->num_rows > 0) {
                $errors[] = 'Bạn đang có một yêu cầu đang "Chờ duyệt" . Vui lòng chờ kết quả hoặc liên hệ quản trị viên.';
            }
            $cstm->close();
        }
    }

    /* Lưu yêu cầu vào RoomRequests */
    if (empty($errors)) {
        $sqlInsert = "
    INSERT INTO RoomRequests
        (StudentID, RoomID, DesiredFrom, CheckInDate, CheckOutDate, Note, Status, CreatedAt, UpdatedAt)
    VALUES
        (?, ?, ?, ?, ?, ?, 'Chờ duyệt', NOW(), NOW())
";

        $istm = $conn->prepare($sqlInsert);
        if (!$istm) {
            $errors[] = 'Lỗi SQL (insert): ' . $conn->error;
        } else {
            $istm->bind_param(
                'iissss',
                $student['StudentID'],
                $RoomID,
                $DesiredFrom,
                $CheckInDate,
                $CheckOutDate,
                $Note
            );


            if ($istm->execute()) {
                $_SESSION['_csrf'] = bin2hex(random_bytes(32)); // refresh CSRF
                $okMsg = '✅ Gửi yêu cầu đăng ký phòng thành công! Vui lòng chờ quản trị viên duyệt.';
                addLog(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'Create room request',
                    'RoomRequests',
                    "Sinh viên gửi yêu cầu đăng ký phòng ID={$roomID}",
                    'activity'
                );
            } else {
                $errors[] = 'Lỗi khi lưu vào CSDL: ' . $istm->error;
            }
            $istm->close();
        }
    }
}




include '../../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gửi yêu cầu đăng ký phòng | Ký túc xá</title>
    <link rel="stylesheet" href="../../../assets/css/user/room_requests/request_create.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
</head>

<body>
    <div class="container">
        <h2><i class="fa-solid fa-bed"></i> Gửi yêu cầu đăng ký phòng</h2>

        <?php if ($student): ?>
            <div class="student-info-card">
                <div class="student-avatar">
                    <?= strtoupper(mb_substr($student['FullName'], 0, 1)) ?>
                </div>
                <div class="student-details">
                    <h3><?= htmlspecialchars($student['FullName']) ?></h3>
                    <p>MSSV: <?= htmlspecialchars($student['StudentCode']) ?> | Giới tính: <?= htmlspecialchars($student['Gender']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert error">
                <strong>Không thể gửi yêu cầu:</strong>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($okMsg): ?>
            <div class="alert success"><?= htmlspecialchars($okMsg) ?></div>
            <script>
                setTimeout(() => location.href = '/modules/user/room_requests/request_list.php', 2000);
            </script>
        <?php endif; ?>

        <?php if ($student && !$okMsg): ?>
            <form method="post" id="roomRequestForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">

                <div class="grid">
                    <div class="col-12">
                        <label for="RoomID">Chọn phòng <span class="req">*</span></label>

                        <?php
                        // Nếu đã POST thì ưu tiên giá trị POST, còn không thì lấy từ room_id truyền sang
                        $roomSel = isset($_POST['RoomID'])
                            ? (int)$_POST['RoomID']
                            : ($preRoomId > 0 ? $preRoomId : 0);
                        ?>

                        <select name="RoomID" id="RoomID" required <?= $preRoomId > 0 ? 'disabled' : '' ?>>
                            <option value="">-- Chọn phòng --</option>
                            <?php foreach ($rooms as $r):
                                $full = ((int)$r['Capacity'] > 0 && (int)$r['CurrentOccupancy'] >= (int)$r['Capacity']);
                                $label = $r['RoomCode'];
                                if ((int)$r['Capacity'] > 0) {
                                    $label .= ' · ' . $r['CurrentOccupancy'] . '/' . $r['Capacity'];
                                }
                                if ($r['GenderLimit']) {
                                    $label .= ' · ' . $r['GenderLimit'];
                                }
                            ?>
                                <option value="<?= (int)$r['RoomID'] ?>"
                                    <?= $roomSel === (int)$r['RoomID'] ? 'selected' : '' ?>
                                    <?= $full ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($label) ?><?= $full ? ' (Đã đầy)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($preRoomId > 0): ?>
                            <!-- Nếu select bị disabled thì gửi thêm hidden để server nhận được RoomID -->
                            <input type="hidden" name="RoomID" value="<?= $preRoomId ?>">
                        <?php endif; ?>

                        <div class="small">Những phòng đã đủ số lượng sẽ bị khóa không thể chọn.</div>
                    </div>

                    <div>
                        <label for="CheckInDate">Ngày vào <span class="req">*</span></label>
                        <input type="date" name="CheckInDate" id="CheckInDate" required
                            value="<?= htmlspecialchars($_POST['CheckInDate'] ?? '') ?>"
                            min="<?= date('Y-m-d') ?>">
                        <div class="small">Ngày bắt đầu ở ký túc xá</div>
                    </div>

                    <div>
                        <label for="CheckOutDate">Ngày ra <span class="req">*</span></label>
                        <input type="date" name="CheckOutDate" id="CheckOutDate" required
                            value="<?= htmlspecialchars($_POST['CheckOutDate'] ?? '') ?>"
                            min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        <div class="small">Ngày kết thúc hợp đồng</div>
                    </div>

                    <div>
                        <label for="Deposit">Tiền cọc tháng đầu <span class="req">*</span></label>
                        <input type="number" name="Deposit" id="Deposit" step="1000" min="0" required
                            placeholder="Nhập số tiền (VNĐ)"
                            value="<?= htmlspecialchars($_POST['Deposit'] ?? '') ?>">
                        <div class="small">Tiền cọc cho tháng đầu tiên</div>
                    </div>

                    <div class="col-12">
                        <label for="Note">Ghi chú (tuỳ chọn)</label>
                        <textarea name="Note" id="Note" maxlength="500"
                            placeholder="Ví dụ: muốn ở cùng bạn A, ưu tiên tầng thấp, ..."><?= htmlspecialchars($_POST['Note'] ?? '') ?></textarea>
                        <div class="small" id="charCount">0/500 ký tự</div>
                    </div>

                    <div class="col-12 actions">
                        <button type="submit" class="btn primary" id="submitBtn">
                            <i class="fa-solid fa-paper-plane"></i> Gửi yêu cầu
                        </button>
                        <a href="/modules/user/room_requests/request_list.php" class="btn ghost">
                            <i class="fa-solid fa-list"></i> Yêu cầu của tôi
                        </a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const noteTextarea = document.getElementById('Note');
            const charCount = document.getElementById('charCount');
            const form = document.getElementById('roomRequestForm');
            const submitBtn = document.getElementById('submitBtn');

            if (noteTextarea && charCount) {
                noteTextarea.addEventListener('input', function() {
                    const length = this.value.length;
                    charCount.textContent = length + '/500 ký tự';
                });
                noteTextarea.dispatchEvent(new Event('input'));
            }

            if (form && submitBtn) {
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi...';
                });
            }
        });
    </script>
</body>

</html>
<?php include '../../../includes/footer.php'; ?>