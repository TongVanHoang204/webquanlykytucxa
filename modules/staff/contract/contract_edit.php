<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';
requireRole(['Admin']);

/* ================== HÀM DÙNG CHUNG ================== */

// Cập nhật số người & trạng thái phòng dựa trên HĐ hiệu lực
function syncRoomOccupancy(mysqli $conn, int $roomId): void
{
    if ($roomId <= 0) return;

    // Lấy capacity
    $capStmt = $conn->prepare("SELECT Capacity FROM Rooms WHERE RoomID = ?");
    $capStmt->bind_param("i", $roomId);
    $capStmt->execute();
    $capStmt->bind_result($capacity);
    $capStmt->fetch();
    $capStmt->close();

    if ($capacity <= 0) return;

    // Đếm hợp đồng hiệu lực
    $count = 0;
    $cnt = $conn->prepare("SELECT COUNT(*) FROM Contracts WHERE RoomID = ? AND Status = 'Hiệu lực'");
    $cnt->bind_param("i", $roomId);
    $cnt->execute();
    $cnt->bind_result($count);
    $cnt->fetch();
    $cnt->close();

    if ($count <= 0) {
        $status = 'Trống';
    } elseif ($count >= $capacity) {
        $status = 'Đầy';
    } else {
        $status = 'Trống'; // còn chỗ nhưng chưa đầy
    }

    $upd = $conn->prepare("UPDATE Rooms SET CurrentOccupants = ?, `Status` = ? WHERE RoomID = ?");
    $upd->bind_param("isi", $count, $status, $roomId);
    $upd->execute();
    $upd->close();
}

// Cập nhật IsInDorm dựa trên hợp đồng hiệu lực
function syncStudentDormStatus(mysqli $conn, int $studentId): void
{
    if ($studentId <= 0) return;

    $cnt = 0;
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM Contracts 
        WHERE StudentID = ? AND Status = 'Hiệu lực'
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    $isInDorm = $cnt > 0 ? 1 : 0;

    $upd = $conn->prepare("UPDATE Students SET IsInDorm = ? WHERE StudentID = ?");
    $upd->bind_param("ii", $isInDorm, $studentId);
    $upd->execute();
    $upd->close();
}

/* ================== LẤY CONTRACT ID ================== */

$contractId = 0;

// Ưu tiên POST (sau submit), còn không thì GET
if (isset($_POST['ContractID'])) {
    $contractId = (int)$_POST['ContractID'];
} elseif (isset($_GET['id'])) {
    $contractId = (int)$_GET['id'];
}

if ($contractId <= 0) {
    $_SESSION['message'] = 'Hợp đồng không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: contract_list.php');
    exit;
}

/* ================== CSRF TOKEN ================== */

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf  = $_SESSION['_csrf'];
$error = '';

/* ================== LẤY THÔNG TIN HỢP ĐỒNG HIỆN TẠI ================== */

$contract = null;

$stmt = $conn->prepare("
    SELECT 
        c.ContractID,
        c.StudentID,
        c.RoomID,
        c.StartDate,
        c.EndDate,
        c.Deposit,
        c.Status,
        s.FullName,
        s.StudentCode,
        r.RoomNumber,
        b.BuildingName
    FROM Contracts c
    JOIN Students s ON c.StudentID = s.StudentID
    JOIN Rooms r    ON c.RoomID = r.RoomID
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE c.ContractID = ?
    LIMIT 1
");
$stmt->bind_param("i", $contractId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    $_SESSION['message'] = 'Không tìm thấy hợp đồng.';
    $_SESSION['message_type'] = 'error';
    header('Location: contract_list.php');
    exit;
}

$contract = $result->fetch_assoc();
$studentId = (int)$contract['StudentID'];
$oldRoomId = (int)$contract['RoomID'];
$oldStatus = $contract['Status'];

/* ================== XỬ LÝ SUBMIT ================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $postedToken = (string)($_POST['_csrf'] ?? '');
    if ($postedToken === '' || !hash_equals($csrf, $postedToken)) {
        $error = '❌ Form không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $newRoomId = (int)($_POST['RoomID'] ?? 0);
        $startDate = trim($_POST['StartDate'] ?? '');
        $endDate   = trim($_POST['EndDate'] ?? '');
        $deposit   = (float)($_POST['Deposit'] ?? 0);
        $newStatus = trim($_POST['Status'] ?? $oldStatus);

        // Chỉ cho phép các trạng thái hợp lệ
        $allowedStatus = ['Hiệu lực', 'Hết hạn', 'Đã hủy'];
        if (!in_array($newStatus, $allowedStatus, true)) {
            $newStatus = $oldStatus;
        }

        // Validate cơ bản
        if ($newRoomId <= 0) {
            $error = '⚠️ Vui lòng chọn phòng.';
        } elseif ($startDate === '' || $endDate === '') {
            $error = '⚠️ Vui lòng chọn đầy đủ ngày bắt đầu và kết thúc.';
        } elseif (strtotime($startDate) && strtotime($endDate) && strtotime($endDate) < strtotime($startDate)) {
            $error = '⚠️ Ngày kết thúc phải sau ngày bắt đầu.';
        } elseif ($deposit < 0) {
            $error = '⚠️ Tiền đặt cọc không hợp lệ.';
        }

        // Rule: 1 HĐ hiệu lực / sinh viên
        if ($error === '' && $newStatus === 'Hiệu lực') {
            $chk = $conn->prepare("
                SELECT COUNT(*) 
                FROM Contracts 
                WHERE StudentID = ? AND Status = 'Hiệu lực' AND ContractID <> ?
            ");
            $chk->bind_param("ii", $studentId, $contractId);
            $chk->execute();
            $chk->bind_result($cntActiveOther);
            $chk->fetch();
            $chk->close();

            if ($cntActiveOther > 0) {
                $error = '❌ Sinh viên này đã có hợp đồng hiệu lực khác.';
            }
        }

        // Check capacity phòng khi status là Hiệu lực
        if ($error === '' && $newStatus === 'Hiệu lực') {
            // Lấy capacity
            $capacity = 0;
            $capStmt = $conn->prepare("SELECT Capacity FROM Rooms WHERE RoomID = ?");
            $capStmt->bind_param("i", $newRoomId);
            $capStmt->execute();
            $capStmt->bind_result($capacity);
            $capStmt->fetch();
            $capStmt->close();

            if ($capacity <= 0) {
                $error = "❌ Không tìm thấy phòng hoặc capacity không hợp lệ.";
            } else {
                // Đếm số hợp đồng hiệu lực trong phòng, trừ chính hợp đồng này
                $activeCount = 0;
                $cnt = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM Contracts 
                    WHERE RoomID = ? AND Status = 'Hiệu lực' AND ContractID <> ?
                ");
                $cnt->bind_param("ii", $newRoomId, $contractId);
                $cnt->execute();
                $cnt->bind_result($activeCount);
                $cnt->fetch();
                $cnt->close();

                if ($activeCount >= $capacity) {
                    $error = "⚠️ Phòng đã đủ người ({$activeCount}/{$capacity}).";
                }
            }
        }

        if ($error === '') {
            try {
                $conn->begin_transaction();

                // Cập nhật hợp đồng
                $upd = $conn->prepare("
                    UPDATE Contracts
                    SET RoomID = ?, StartDate = ?, EndDate = ?, Deposit = ?, Status = ?, UpdatedAt = NOW()
                    WHERE ContractID = ?
                ");
                if ($upd === false) {
                    throw new Exception('Lỗi chuẩn bị câu lệnh UPDATE.');
                }
                $upd->bind_param("issdsi", $newRoomId, $startDate, $endDate, $deposit, $newStatus, $contractId);

                if (!$upd->execute()) {
                    throw new Exception('Lỗi khi cập nhật hợp đồng: ' . $upd->error);
                }
                $upd->close();

                // Đồng bộ phòng cũ và phòng mới (nếu đổi)
                syncRoomOccupancy($conn, $oldRoomId);
                if ($newRoomId !== $oldRoomId) {
                    syncRoomOccupancy($conn, $newRoomId);
                }

                // Đồng bộ IsInDorm của sinh viên
                syncStudentDormStatus($conn, $studentId);

                $conn->commit();
                logContractAction(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'update',
                    "Cáº­p nháº­t há»£p Ä‘á»“ng #{$contractId} cho StudentID={$studentId}, RoomID={$newRoomId}",
                    'activity'
                );

                unset($_SESSION['_csrf']);
                $_SESSION['message'] = '✅ Cập nhật hợp đồng thành công!';
                $_SESSION['message_type'] = 'success';
                header('Location: contract_list.php');
                exit;
            } catch (Exception $ex) {
                $conn->rollback();
                $error = '❌ ' . $ex->getMessage();
            }
        }
    }
}

/* ================== DỮ LIỆU PHÒNG CHO FORM ================== */

$rooms = $conn->query("
    SELECT r.RoomID, r.RoomNumber, r.RoomPrice, b.BuildingName
    FROM Rooms r
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    ORDER BY b.BuildingName, r.RoomNumber ASC
");

/* ================== BẮT ĐẦU RENDER HTML ================== */

require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Sửa hợp đồng | Quản lý KTX</title>

    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="/assets/css/staff/contract/staff_contract_create.css">
    <link rel="icon" href="/assets/img/favicon.ico">

    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        .select2-container--default .select2-selection--single {
            height: 42px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .select2-selection__rendered {
            line-height: 42px !important;
        }

        .select2-selection__arrow {
            height: 40px !important;
        }
    </style>
</head>

<body>
    <div class="contract-create-container">
        <div class="header">
            <h2><i class="fas fa-file-signature"></i> Chỉnh sửa hợp đồng</h2>
            <a href="contract_list.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Quay lại danh sách
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="contract-form" id="contractForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="ContractID" value="<?= (int)$contract['ContractID'] ?>">

            <!-- Sinh viên (readonly) -->
            <div class="form-group">
                <label><i class="fas fa-user-graduate"></i> Sinh viên:</label>
                <input type="text" value="<?= htmlspecialchars($contract['FullName']) ?> (<?= htmlspecialchars($contract['StudentCode']) ?>)"
                    class="readonly-input" readonly>
            </div>

            <!-- Phòng -->
            <div class="form-group">
                <label for="RoomID">
                    <i class="fas fa-door-open"></i> Phòng:
                </label>
                <select name="RoomID" id="RoomID" required>
                    <option value="">-- Chọn phòng --</option>
                    <?php while ($r = $rooms->fetch_assoc()): ?>
                        <option value="<?= (int)$r['RoomID'] ?>"
                            data-price="<?= (float)$r['RoomPrice'] ?>"
                            <?= ((int)$r['RoomID'] === (int)$contract['RoomID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['BuildingName']) ?>
                            - Phòng <?= htmlspecialchars($r['RoomNumber']) ?>
                            (<?= number_format($r['RoomPrice'], 0, ',', '.') ?> ₫/tháng)
                        </option>
                    <?php endwhile; ?>
                </select>

                <div class="price-preview" id="pricePreview">
                    <div class="label">Giá phòng:</div>
                    <div class="value" id="priceValue">0 ₫/tháng</div>
                </div>
            </div>

            <!-- Ngày bắt đầu -->
            <div class="form-group">
                <label for="StartDate">
                    <i class="fas fa-calendar-day"></i> Ngày bắt đầu:
                </label>
                <input type="date" name="StartDate" id="StartDate" required
                    value="<?= htmlspecialchars($contract['StartDate']) ?>">
            </div>

            <!-- Ngày kết thúc -->
            <div class="form-group">
                <label for="EndDate">
                    <i class="fas fa-calendar-check"></i> Ngày kết thúc:
                </label>
                <input type="date" name="EndDate" id="EndDate" required
                    value="<?= htmlspecialchars($contract['EndDate']) ?>">
            </div>

            <!-- Tiền cọc -->
            <div class="form-group">
                <label for="Deposit">
                    <i class="fas fa-coins"></i> Tiền đặt cọc:
                </label>
                <input type="number" step="1000" name="Deposit" id="Deposit" min="0"
                    value="<?= htmlspecialchars($contract['Deposit']) ?>">
                <div class="price-preview" id="depositPreview">
                    <div class="label">Số tiền đặt cọc:</div>
                    <div class="value" id="depositValue">
                        <?= number_format((float)$contract['Deposit'], 0, ',', '.') ?> ₫
                    </div>
                </div>
            </div>

            <!-- Trạng thái -->
            <div class="form-group">
                <label for="Status">
                    <i class="fas fa-toggle-on"></i> Trạng thái:
                </label>
                <select name="Status" id="Status">
                    <option value="Hiệu lực" <?= $contract['Status'] === 'Hiệu lực' ? 'selected' : '' ?>>Hiệu lực</option>
                    <option value="Hết hạn" <?= $contract['Status'] === 'Hết hạn'   ? 'selected' : '' ?>>Hết hạn</option>
                    <option value="Đã hủy" <?= $contract['Status'] === 'Đã hủy'    ? 'selected' : '' ?>>Đã hủy</option>
                </select>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-save"></i> Lưu thay đổi
            </button>
        </form>
    </div>

    <script>
        $(function() {
            $('#RoomID').select2({
                placeholder: "🔍 Nhập tòa hoặc số phòng...",
                allowClear: true,
                width: '100%'
            });

            function updatePrice() {
                const price = $('#RoomID').find(':selected').data('price') || 0;
                $('#priceValue').text(
                    new Intl.NumberFormat('vi-VN').format(price) + ' ₫/tháng'
                );
            }
            $('#RoomID').on('change', updatePrice);
            updatePrice();

            $('#Deposit').on('input', function() {
                const val = this.value ? parseFloat(this.value) : 0;
                $('#depositValue').text(
                    new Intl.NumberFormat('vi-VN').format(val) + ' ₫'
                );
            });

            $('#StartDate').on('change', function() {
                const start = this.value;
                const end = $('#EndDate');
                if (start) {
                    end.attr('min', start);
                    if (end.val() && end.val() < start) {
                        end.val('');
                    }
                }
            });

            $('#contractForm').on('submit', function() {
                $('#submitBtn')
                    .html('<i class="fas fa-spinner fa-spin"></i> Đang lưu...')
                    .prop('disabled', true);
            });
        });
    </script>
</body>

</html>
