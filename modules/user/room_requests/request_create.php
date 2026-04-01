<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Student', 'Manager']);

$preRoomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['_csrf'];

function s($v) { return trim($v ?? ''); }
$conn->set_charset('utf8mb4');
$errors = []; $okMsg = null;

$currentUserId = (int)($_SESSION['UserID'] ?? 0);
$student = null;
if ($currentUserId <= 0) { $errors[] = 'Phiên đăng nhập không hợp lệ.'; }
else {
    $studentRes = $conn->query("SELECT StudentID, FullName, Gender, StudentCode FROM Students WHERE UserID = {$currentUserId} LIMIT 1");
    if ($studentRes && $studentRes->num_rows > 0) $student = $studentRes->fetch_assoc();
    else $errors[] = 'Tài khoản chưa liên kết hồ sơ sinh viên.';
}

$rooms = [];
$sqlRooms = "SELECT r.RoomID, r.RoomNumber AS RoomCode, COALESCE(r.RoomType,'') AS GenderLimit, COALESCE(r.Capacity,0) AS Capacity, COALESCE(c.ActiveCount,0) AS CurrentOccupancy FROM Rooms r LEFT JOIN (SELECT RoomID, COUNT(*) AS ActiveCount FROM Contracts WHERE Status='Hiệu lực' GROUP BY RoomID) c ON c.RoomID=r.RoomID WHERE r.Status='Trống'";
if ($preRoomId > 0) $sqlRooms .= " AND r.RoomID={$preRoomId}";
$sqlRooms .= " ORDER BY r.RoomNumber";
if ($rs = $conn->query($sqlRooms)) { while ($row = $rs->fetch_assoc()) $rooms[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!isset($_POST['_csrf']) || $_POST['_csrf'] !== $_SESSION['_csrf']) $errors[] = 'CSRF không hợp lệ.';
    $RoomID = (int)($_POST['RoomID'] ?? 0);
    $CheckInDate = s($_POST['CheckInDate'] ?? '');
    $CheckOutDate = s($_POST['CheckOutDate'] ?? '');
    $DesiredFrom = $CheckInDate;
    $Deposit = s($_POST['Deposit'] ?? '');
    $Note = s($_POST['Note'] ?? '');

    if ($RoomID <= 0) $errors[] = 'Vui lòng chọn phòng.';
    if ($CheckInDate === '' || strtotime($CheckInDate) === false) $errors[] = 'Ngày vào không hợp lệ.';
    if ($CheckOutDate === '' || strtotime($CheckOutDate) === false) $errors[] = 'Ngày kết thúc không hợp lệ.';
    if ($CheckInDate && $CheckOutDate && strtotime($CheckInDate) > strtotime($CheckOutDate)) $errors[] = 'Ngày vào phải trước ngày kết thúc.';
    if ($Deposit === '' || !is_numeric($Deposit) || (float)$Deposit < 0) $errors[] = 'Tiền cọc phải là số dương.';
    if ($Note !== '' && mb_strlen($Note) > 500) $errors[] = 'Ghi chú tối đa 500 ký tự.';

    if (empty($errors)) {
        $rstm = $conn->prepare("SELECT r.RoomID, COALESCE(r.Capacity,0) AS Capacity, COALESCE(c.ActiveCount,0) AS CurrentOccupancy, COALESCE(r.RoomType,'') AS GenderLimit FROM Rooms r LEFT JOIN (SELECT RoomID, COUNT(*) AS ActiveCount FROM Contracts WHERE Status='Hiệu lực' GROUP BY RoomID) c ON c.RoomID=r.RoomID WHERE r.RoomID=? AND r.Status='Trống' LIMIT 1");
        $rstm->bind_param('i', $RoomID); $rstm->execute(); $room = $rstm->get_result()->fetch_assoc(); $rstm->close();
        if (!$room) $errors[] = 'Phòng không hợp lệ hoặc đã khóa.';
        elseif ((int)$room['Capacity'] > 0 && (int)$room['CurrentOccupancy'] >= (int)$room['Capacity']) $errors[] = 'Phòng đã đủ.';
        else {
            $roomGender = strtoupper(trim($room['GenderLimit'] ?? ''));
            $stuGender = strtoupper(trim($student['Gender'] ?? ''));
            if ($roomGender !== '' && in_array($stuGender, ['NAM','NỮ'], true)) {
                if (($roomGender === 'NAM' && $stuGender !== 'NAM') || ($roomGender === 'NỮ' && $stuGender !== 'NỮ')) $errors[] = 'Phòng không phù hợp giới tính.';
            }
        }
    }
    if (empty($errors)) {
        $cstm = $conn->prepare("SELECT 1 FROM RoomRequests WHERE StudentID=? AND Status='Chờ duyệt' LIMIT 1");
        $cstm->bind_param('i', $student['StudentID']); $cstm->execute(); $cstm->store_result();
        if ($cstm->num_rows > 0) $errors[] = 'Bạn đang có yêu cầu "Chờ duyệt". Vui lòng chờ.';
        $cstm->close();
    }
    if (empty($errors)) {
        $istm = $conn->prepare("INSERT INTO RoomRequests (StudentID, RoomID, DesiredFrom, CheckInDate, CheckOutDate, Note, Status, CreatedAt, UpdatedAt) VALUES (?,?,?,?,?,?,'Chờ duyệt',NOW(),NOW())");
        $istm->bind_param('iissss', $student['StudentID'], $RoomID, $DesiredFrom, $CheckInDate, $CheckOutDate, $Note);
        if ($istm->execute()) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            $okMsg = 'Gửi yêu cầu thành công! Vui lòng chờ duyệt.';
            addLog($conn, $_SESSION['UserID'] ?? null, 'Create room request', 'RoomRequests', "SV gửi yêu cầu phòng RoomID={$RoomID}", 'activity');
        } else $errors[] = 'Lỗi CSDL: ' . $istm->error;
        $istm->close();
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
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .rc-pg { max-width:700px; margin:0 auto; padding:30px 20px; }
        .rc-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .rc-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .rc-head h1 { font-size:1.2rem; font-weight:800; margin:0 0 6px; }
        .rc-head p { font-size:0.88rem; opacity:0.8; margin:0; }

        .rc-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; margin-bottom:16px; }
        .rc-stu { display:flex; align-items:center; gap:14px; }
        .rc-stu-av { width:48px; height:48px; border-radius:14px; background:var(--gradient-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.2rem; font-weight:800; flex-shrink:0; }
        .rc-stu h3 { font-size:0.95rem; font-weight:700; margin:0 0 2px; color:var(--text); }
        .rc-stu p { font-size:0.82rem; color:var(--text-secondary); margin:0; }

        .rc-alert { padding:14px 18px; border-radius:12px; font-size:0.88rem; margin-bottom:16px; }
        .rc-alert.error { background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#ef4444; }
        .rc-alert.success { background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); color:#10b981; }
        .rc-alert ul { margin:6px 0 0 18px; padding:0; }

        .rc-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        @media(max-width:600px) { .rc-form-grid { grid-template-columns:1fr; } }
        .rc-full { grid-column:1/-1; }
        .rc-field label { font-size:0.82rem; font-weight:600; color:var(--text); display:block; margin-bottom:6px; }
        .rc-field label .req { color:#ef4444; }
        .rc-field input, .rc-field select, .rc-field textarea { width:100%; padding:10px 14px; border:1px solid var(--stroke); border-radius:10px; font-size:0.88rem; background:var(--bg); color:var(--text); transition:border 0.2s; box-sizing:border-box; }
        .rc-field input:focus, .rc-field select:focus, .rc-field textarea:focus { border-color:var(--primary); outline:none; }
        .rc-field textarea { min-height:80px; resize:vertical; }
        .rc-field .hint { font-size:0.76rem; color:var(--text-secondary); margin-top:4px; }

        .rc-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
    </style>
</head>
<body>
    <div class="rc-pg">
        <div class="rc-head">
            <h1><i class="fas fa-bed"></i> Gửi yêu cầu đăng ký phòng</h1>
            <p>Điền thông tin bên dưới để gửi yêu cầu đăng ký phòng ở ký túc xá</p>
        </div>

        <?php if ($student): ?>
            <div class="rc-card">
                <div class="rc-stu">
                    <div class="rc-stu-av"><?= strtoupper(mb_substr($student['FullName'], 0, 1, 'UTF-8')) ?></div>
                    <div>
                        <h3><?= htmlspecialchars($student['FullName']) ?></h3>
                        <p>MSSV: <?= htmlspecialchars($student['StudentCode']) ?> · Giới tính: <?= htmlspecialchars($student['Gender'] ?? '—') ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rc-alert error">
                <strong><i class="fas fa-exclamation-triangle"></i> Không thể gửi:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php elseif ($okMsg): ?>
            <div class="rc-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($okMsg) ?></div>
            <script>setTimeout(() => location.href = '<?= $base ?>modules/user/room_requests/request_list.php', 2000);</script>
        <?php endif; ?>

        <?php if ($student && !$okMsg): ?>
            <div class="rc-card">
                <form method="post" id="roomRequestForm">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($CSRF) ?>">
                    <div class="rc-form-grid">
                        <?php $roomSel = isset($_POST['RoomID']) ? (int)$_POST['RoomID'] : ($preRoomId > 0 ? $preRoomId : 0); ?>
                        <div class="rc-field rc-full">
                            <label for="RoomID">Chọn phòng <span class="req">*</span></label>
                            <select name="RoomID" id="RoomID" required <?= $preRoomId > 0 ? 'disabled' : '' ?>>
                                <option value="">-- Chọn phòng --</option>
                                <?php foreach ($rooms as $r):
                                    $full = ((int)$r['Capacity'] > 0 && (int)$r['CurrentOccupancy'] >= (int)$r['Capacity']);
                                    $label = $r['RoomCode'];
                                    if ((int)$r['Capacity'] > 0) $label .= ' · ' . $r['CurrentOccupancy'] . '/' . $r['Capacity'];
                                    if ($r['GenderLimit']) $label .= ' · ' . $r['GenderLimit'];
                                ?>
                                    <option value="<?= (int)$r['RoomID'] ?>" <?= $roomSel === (int)$r['RoomID'] ? 'selected' : '' ?> <?= $full ? 'disabled' : '' ?>><?= htmlspecialchars($label) ?><?= $full ? ' (Đầy)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($preRoomId > 0): ?><input type="hidden" name="RoomID" value="<?= $preRoomId ?>"><?php endif; ?>
                            <div class="hint">Phòng đã đủ sẽ bị khóa.</div>
                        </div>
                        <div class="rc-field">
                            <label for="CheckInDate">Ngày vào <span class="req">*</span></label>
                            <input type="date" name="CheckInDate" id="CheckInDate" required value="<?= htmlspecialchars($_POST['CheckInDate'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                            <div class="hint">Ngày bắt đầu ở</div>
                        </div>
                        <div class="rc-field">
                            <label for="CheckOutDate">Ngày ra <span class="req">*</span></label>
                            <input type="date" name="CheckOutDate" id="CheckOutDate" required value="<?= htmlspecialchars($_POST['CheckOutDate'] ?? '') ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            <div class="hint">Ngày kết thúc hợp đồng</div>
                        </div>
                        <div class="rc-field">
                            <label for="Deposit">Tiền cọc <span class="req">*</span></label>
                            <input type="number" name="Deposit" id="Deposit" step="1000" min="0" required placeholder="VNĐ" value="<?= htmlspecialchars($_POST['Deposit'] ?? '') ?>">
                            <div class="hint">Tiền cọc tháng đầu</div>
                        </div>
                        <div class="rc-field rc-full">
                            <label for="Note">Ghi chú</label>
                            <textarea name="Note" id="Note" maxlength="500" placeholder="VD: muốn ở cùng bạn A, ưu tiên tầng thấp..."><?= htmlspecialchars($_POST['Note'] ?? '') ?></textarea>
                            <div class="hint" id="charCount">0/500 ký tự</div>
                        </div>
                        <div class="rc-field rc-full">
                            <div class="rc-actions">
                                <button type="submit" class="mod-btn mod-btn-primary" id="submitBtn"><i class="fas fa-paper-plane"></i> Gửi yêu cầu</button>
                                <a href="<?= $base ?>modules/user/room_requests/request_list.php" class="mod-btn mod-btn-outline"><i class="fas fa-list"></i> Yêu cầu của tôi</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const note = document.getElementById('Note'), cc = document.getElementById('charCount');
        if (note && cc) { note.addEventListener('input', function() { cc.textContent = this.value.length + '/500 ký tự'; }); note.dispatchEvent(new Event('input')); }
        const form = document.getElementById('roomRequestForm'), btn = document.getElementById('submitBtn');
        if (form && btn) form.addEventListener('submit', function() { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...'; });
    });
    </script>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>