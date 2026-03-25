<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';
requireRole(['Admin']);

/* CSRF token: tạo 1 lần, giữ đến khi submit thành công */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf  = $_SESSION['_csrf'];
$error = '';

/* ================== XỬ LÝ POST (KHÔNG include admin_header.php Ở TRÊN) ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $postedToken  = (string)($_POST['_csrf'] ?? '');
    $sessionToken = (string)$csrf;
    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $error = '❌ Form không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $studentID = (int)($_POST['StudentID'] ?? 0);
        $roomID    = (int)($_POST['RoomID'] ?? 0);
        $startDate = trim($_POST['StartDate'] ?? '');
        $endDate   = trim($_POST['EndDate'] ?? '');
        $deposit   = (float)($_POST['Deposit'] ?? 0);

        // Validate cơ bản
        if ($studentID <= 0)                                 $error = '⚠️ Vui lòng chọn sinh viên.';
        elseif ($roomID <= 0)                                $error = '⚠️ Vui lòng chọn phòng.';
        elseif ($startDate === '' || $endDate === '')        $error = '⚠️ Vui lòng chọn đủ ngày bắt đầu/kết thúc.';
        elseif (strtotime($startDate) && strtotime($endDate) && strtotime($endDate) < strtotime($startDate))
            $error = '⚠️ Ngày kết thúc phải sau ngày bắt đầu.';
        elseif ($deposit < 0)                                $error = '⚠️ Tiền đặt cọc không hợp lệ.';

        // 1) Mỗi sinh viên chỉ có 1 HĐ hiệu lực
        if ($error === '') {
            $checkStudent = $conn->prepare("SELECT 1 FROM Contracts WHERE StudentID=? AND Status='Hiệu lực' LIMIT 1");
            $checkStudent->bind_param("i", $studentID);
            $checkStudent->execute();
            $checkStudent->store_result();
            if ($checkStudent->num_rows > 0) {
                $error = "❌ Sinh viên này đã có hợp đồng đang hiệu lực.";
            }
            $checkStudent->close();
        }

        // 2) Kiểm tra capacity phòng
        $capacity = 0;
        if ($error === '') {
            $capStmt = $conn->prepare("SELECT Capacity FROM Rooms WHERE RoomID=?");
            $capStmt->bind_param("i", $roomID);
            $capStmt->execute();
            $capStmt->bind_result($capacity);
            $capStmt->fetch();
            $capStmt->close();

            if ($capacity <= 0) {
                $error = "❌ Không tìm thấy phòng #$roomID hoặc sức chứa không hợp lệ.";
            }
        }

        // Đếm số HĐ hiệu lực hiện tại của phòng
        $activeCount = 0;
        if ($error === '') {
            $cntStmt = $conn->prepare("SELECT COUNT(*) FROM Contracts WHERE RoomID=? AND Status='Hiệu lực'");
            $cntStmt->bind_param("i", $roomID);
            $cntStmt->execute();
            $cntStmt->bind_result($activeCount);
            $cntStmt->fetch();
            $cntStmt->close();

            if ($activeCount >= $capacity) {
                $error = "⚠️ Phòng đã đủ người ({$activeCount}/{$capacity}).";
            }
        }

        // 3) Tạo HĐ + cập nhật phòng + cập nhật IsInDorm trong transaction
        if ($error === '') {
            try {
                $conn->begin_transaction();

                // Tạo hợp đồng hiệu lực
                $stmt = $conn->prepare("
                    INSERT INTO Contracts (StudentID, RoomID, StartDate, EndDate, Deposit, Status, CreatedAt)
                    VALUES (?, ?, ?, ?, ?, 'Hiệu lực', NOW())
                ");
                if ($stmt === false) {
                    throw new Exception('Lỗi chuẩn bị câu lệnh SQL.');
                }

                $stmt->bind_param("iissd", $studentID, $roomID, $startDate, $endDate, $deposit);

                if (!$stmt->execute()) {
                    throw new Exception('Lỗi MySQL khi tạo hợp đồng: ' . $stmt->error);
                }
                $stmt->close();

                // Đếm lại số HĐ hiệu lực của phòng sau khi insert
                $activeCount2 = 0;
                $cnt2 = $conn->prepare("SELECT COUNT(*) FROM Contracts WHERE RoomID=? AND Status='Hiệu lực'");
                $cnt2->bind_param("i", $roomID);
                $cnt2->execute();
                $cnt2->bind_result($activeCount2);
                $cnt2->fetch();
                $cnt2->close();

                // Tính trạng thái phòng: Trống / Đầy (theo CurrentOccupants & Capacity)
                if ($activeCount2 <= 0) {
                    $roomStatus = 'Trống';
                } elseif ($activeCount2 >= $capacity) {
                    $roomStatus = 'Đầy';
                } else {
                    $roomStatus = 'Trống'; // còn chỗ
                }

                // Cập nhật Rooms
                $upd = $conn->prepare("UPDATE Rooms SET CurrentOccupants = ?, `Status` = ? WHERE RoomID = ?");
                $upd->bind_param("isi", $activeCount2, $roomStatus, $roomID);
                if (!$upd->execute()) {
                    throw new Exception('Không thể cập nhật phòng: ' . $upd->error);
                }
                $upd->close();

                // 🔁 Đồng bộ IsInDorm của sinh viên theo hợp đồng hiệu lực
                $stuActiveContracts = 0;
                $checkStu = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM Contracts 
                    WHERE StudentID = ? AND Status = 'Hiệu lực'
                ");
                $checkStu->bind_param("i", $studentID);
                $checkStu->execute();
                $checkStu->bind_result($stuActiveContracts);
                $checkStu->fetch();
                $checkStu->close();

                $isInDorm = ($stuActiveContracts > 0) ? 1 : 0;

                $updStu = $conn->prepare("UPDATE Students SET IsInDorm = ? WHERE StudentID = ?");
                $updStu->bind_param("ii", $isInDorm, $studentID);
                if (!$updStu->execute()) {
                    throw new Exception('Không thể cập nhật trạng thái IsInDorm: ' . $updStu->error);
                }
                $updStu->close();

                // ✅ Thành công
                $conn->commit();
                $createdContract = true;
        if ($createdContract) {
            logContractAction(
                $conn,
                $_SESSION['UserID'] ?? null,
                'create',
                "Táº¡o há»£p Ä‘á»“ng cho StudentID={$studentID}, RoomID={$roomID}",
                'activity'
            );
        }

                unset($_SESSION['_csrf']); // tránh double submit
                $_SESSION['message'] = "✅ Thêm hợp đồng mới thành công!";
                $_SESSION['message_type'] = "success";
                header("Location: contract_list.php");
                exit;
            } catch (Exception $ex) {
                $conn->rollback();
                logContractAction(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'create_failed',
                    "Táº¡o há»£p Ä‘á»“ng tháº¥t báº¡i cho StudentID={$studentID}, RoomID={$roomID}: " . $ex->getMessage(),
                    'warning'
                );
                $error = "❌ " . $ex->getMessage();
            }
        }
        logContractAction(
            $conn,
            $_SESSION['UserID'] ?? null,
            'create',
            "Tạo hợp đồng cho StudentID={$studentID}, RoomID={$roomID}",
            'activity'
        );
    }
}

/* ================== DỮ LIỆU CHO FORM (GET hoặc có lỗi) ================== */
$students = $conn->query("
    SELECT StudentID, FullName, StudentCode 
    FROM Students 
    ORDER BY FullName ASC
");
$rooms = $conn->query("
    SELECT r.RoomID, r.RoomNumber, r.RoomPrice, b.BuildingName
    FROM Rooms r
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    ORDER BY b.BuildingName, r.RoomNumber ASC
");




/* ================== TỪ ĐÂY MỚI RENDER HTML ================== */
require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Thêm hợp đồng | Quản lý KTX</title>

    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="/assets/css/staff/contract/staff_contract_create.css">
    <link rel="icon" href="/assets/img/favicon.ico">

    <!-- Libs -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            <h2><i class="fas fa-file-signature"></i> Thêm hợp đồng mới</h2>
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

            <div class="form-group">
                <label for="StudentID">
                    <i class="fas fa-user-graduate"></i> Sinh viên:
                </label>

                <select name="StudentID" id="StudentID" required>
                    <option value="">
                        Chọn sinh viên chưa có hợp đồng hiệu lực
                    </option>
                    <?php
                    $selectedStudentID = isset($_POST['StudentID']) ? (int)$_POST['StudentID'] : 0;

                    if ($students && $students->num_rows > 0):
                        while ($s = $students->fetch_assoc()):
                            $id      = (int)$s['StudentID'];
                            $sel     = ($id === $selectedStudentID) ? 'selected' : '';
                            $name    = $s['FullName'] ?: ('SV #' . $id);
                            $mssv    = $s['StudentCode'] ?: '';
                            $faculty = $s['FacultyName'] ?: '';
                    ?>
                            <option value="<?= $id ?>" <?= $sel ?>>
                                <?= htmlspecialchars($name) ?>
                                <?= $mssv ? ' - ' . htmlspecialchars($mssv) : '' ?>
                                <?= $faculty ? ' • ' . htmlspecialchars($faculty) : '' ?>
                            </option>
                        <?php
                        endwhile;
                    else:
                        ?>
                        <option value="" disabled>
                            Không còn sinh viên khả dụng (tất cả đã có HĐ hiệu lực)
                        </option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="RoomID">
                    <i class="fas fa-door-open"></i> Phòng:
                </label>
                <select name="RoomID" id="RoomID" required>
                    <option value="">-- Chọn phòng --</option>
                    <?php while ($r = $rooms->fetch_assoc()): ?>
                        <option value="<?= (int)$r['RoomID'] ?>"
                            data-price="<?= (float)$r['RoomPrice'] ?>"
                            <?= (isset($_POST['RoomID']) && (int)$_POST['RoomID'] === (int)$r['RoomID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['BuildingName']) ?> - Phòng <?= htmlspecialchars($r['RoomNumber']) ?>
                            (<?= number_format($r['RoomPrice'], 0, ',', '.') ?> ₫/tháng)
                        </option>
                    <?php endwhile; ?>
                </select>

                <div class="price-preview" id="pricePreview">
                    <div class="label">Giá phòng:</div>
                    <div class="value" id="priceValue">0 ₫/tháng</div>
                </div>
            </div>

            <div class="form-group">
                <label for="StartDate">
                    <i class="fas fa-calendar-day"></i> Ngày bắt đầu:
                </label>
                <input type="date" name="StartDate" id="StartDate" required
                    min="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars($_POST['StartDate'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="EndDate">
                    <i class="fas fa-calendar-check"></i> Ngày kết thúc:
                </label>
                <input type="date" name="EndDate" id="EndDate" required
                    value="<?= htmlspecialchars($_POST['EndDate'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="Deposit">
                    <i class="fas fa-coins"></i> Tiền đặt cọc:
                </label>
                <input type="text" id="Deposit" name="Deposit" placeholder="Nhập số tiền..." required>

                <div class="price-preview" id="depositPreview">
                    <div class="label">Số tiền đặt cọc:</div>
                    <div class="value" id="depositValue">0 ₫</div>
                </div>

            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-save"></i> Tạo hợp đồng mới
            </button>
        </form>
    </div>

    <script>
        $(function() {
            $('#StudentID').select2({
                placeholder: "🔍 Nhập tên hoặc MSSV...",
                allowClear: true,
                width: '100%'
            });

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

            depositInput.addEventListener('input', function() {
                let raw = this.value.replace(/\D/g, '');
                if (raw === '') raw = '0';
                const num = Number(raw);
                this.value = num ? num.toLocaleString('vi-VN') : '';
                depositValue.textContent = num.toLocaleString('vi-VN') + ' ₫';
            });

            $('#StartDate').on('change', function() {
                const start = this.value;
                const end = $('#EndDate');
                end.attr('min', start);
                if (end.val() && end.val() < start) end.val('');
            });

            $('#contractForm').on('submit', function() {
                $('#submitBtn')
                    .html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...')
                    .prop('disabled', true);
            });
        });
        document.addEventListener('DOMContentLoaded', () => {
            const depositInput = document.getElementById('Deposit');
            const depositValue = document.getElementById('depositValue');

            let rawValue = ''; // lưu giá trị gốc không format

            depositInput.addEventListener('input', (e) => {
                // Lấy toàn bộ số (bỏ dấu chấm, dấu phẩy, ký tự khác)
                rawValue = e.target.value.replace(/[^\d]/g, '');
                if (rawValue === '') rawValue = '0';

                // Format hiển thị VNĐ
                const formatted = new Intl.NumberFormat('vi-VN').format(rawValue);
                e.target.value = formatted; // chỉ hiển thị format
                depositValue.textContent = formatted + ' ₫';
            });

            // Khi submit form: gửi số thật về server
            document.querySelector('form').addEventListener('submit', () => {
                depositInput.value = rawValue;
            });
        });
    </script>
</body>

</html>
