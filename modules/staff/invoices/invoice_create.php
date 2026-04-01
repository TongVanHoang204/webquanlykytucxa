<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

$success = false;
$error = '';

// XỬ LÝ SUBMIT FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contractId     = (int)($_POST['ContractID'] ?? 0);
    $month          = (int)($_POST['Month'] ?? 0);
    $year           = (int)($_POST['Year'] ?? 0);
    $roomFee        = (float)($_POST['RoomFee'] ?? 0);
    $electricUsage  = (float)($_POST['ElectricUsage'] ?? 0);
    $electricPrice  = (float)($_POST['ElectricPrice'] ?? 0);
    $waterUsage     = (float)($_POST['WaterUsage'] ?? 0);
    $waterPrice     = (float)($_POST['WaterPrice'] ?? 0);
    $dueDate        = trim($_POST['DueDate'] ?? '');

    // Validate cơ bản
    if ($contractId <= 0) {
        $error = 'Vui lòng chọn hợp đồng / sinh viên.';
    } elseif ($month < 1 || $month > 12) {
        $error = 'Tháng không hợp lệ.';
    } elseif ($year < 2000 || $year > 2100) {
        $error = 'Năm không hợp lệ.';
    } elseif ($roomFee < 0 || $electricUsage < 0 || $electricPrice < 0 || $waterUsage < 0 || $waterPrice < 0) {
        $error = 'Số tiền / chỉ số không hợp lệ.';
    } elseif ($dueDate === '') {
        $error = 'Vui lòng chọn hạn thanh toán.';
    }

    // Kiểm tra hợp đồng còn hiệu lực
    if ($error === '') {
        $stmt = $conn->prepare("SELECT Status FROM Contracts WHERE ContractID = ? LIMIT 1");
        $stmt->bind_param('i', $contractId);
        $stmt->execute();
        $res = $stmt->get_result();
        $contract = $res->fetch_assoc();
        $stmt->close();

        if (!$contract) {
            $error = 'Hợp đồng không tồn tại.';
        } elseif ($contract['Status'] !== 'Hiệu lực') {
            $error = 'Hợp đồng này không còn hiệu lực, không thể tạo hóa đơn.';
        }
    }

    // Kiểm tra trùng hóa đơn (ContractID + Month + Year)
    if ($error === '') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS c 
            FROM Invoices 
            WHERE ContractID = ? AND Month = ? AND Year = ?
        ");
        $stmt->bind_param('iii', $contractId, $month, $year);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['c']) && $row['c'] > 0) {
            $error = "Đã tồn tại hóa đơn cho tháng {$month}/{$year} của hợp đồng này.";
        }
    }

    // Thêm hóa đơn
    if ($error === '') {
        $totalAmount = $roomFee + ($electricUsage * $electricPrice) + ($waterUsage * $waterPrice);

        $stmt = $conn->prepare("
            INSERT INTO Invoices
                (ContractID, Month, Year, RoomFee,
                 ElectricUsage, ElectricPrice,
                 WaterUsage, WaterPrice,
                 TotalAmount, DueDate, Status, CreatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chưa thanh toán', NOW())
        ");
        if ($stmt === false) {
            $error = 'Lỗi hệ thống: không thể chuẩn bị câu lệnh.';
        } else {
            $stmt->bind_param(
                'iiidididds',
                $contractId,
                $month,
                $year,
                $roomFee,
                $electricUsage,
                $electricPrice,
                $waterUsage,
                $waterPrice,
                $totalAmount,
                $dueDate
            );

            if ($stmt->execute()) {
                $success = true;
                $stmt->close();

                $_SESSION['message'] = '✅ Tạo hóa đơn mới thành công!';
                $_SESSION['message_type'] = 'success';
                header('Location: invoice_list.php');
                exit;
            } else {
                $error = 'Không thể lưu hóa đơn: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Create invoice',
        'Invoices',
        "Tạo hóa đơn ID={$invoiceID} cho hợp đồng #{$contractID}",
        'activity'
    );
}




// SAU KHI XỬ LÝ POST MỚI GỌI HEADER (TRÁNH LỖI header already sent)
require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Tạo hóa đơn mới | Quản lý KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/invoice/staff_invoice_create.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .select2-selection__rendered {
            line-height: 42px !important;
        }

        .select2-selection__arrow {
            height: 40px !important;
        }

        .inline-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .inline-inputs input {
            width: 100px;
            text-align: right;
        }

        #contractWarning {
            display: none;
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            font-weight: 500;
            transition: 0.3s;
        }

        .alert-error {
            margin-bottom: 15px;
            padding: 10px 12px;
            border-radius: 6px;
            background: #ffe6e6;
            color: #c0392b;
            border: 1px solid #e74c3c;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="invoice-create-container">
        <div class="header">
            <h2><i class="fas fa-file-invoice"></i> Tạo hóa đơn mới</h2>
            <a href="invoice_list.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại danh sách</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="invoice-form" id="invoiceForm">
            <div class="form-group">
                <label for="ContractID"><i class="fas fa-user-graduate"></i> Hợp đồng / Sinh viên:</label>
                <select name="ContractID" id="ContractID" style="width:100%;" required></select>
                <div id="contractWarning"></div>
            </div>

            <div class="form-inline">
                <div class="form-group">
                    <label for="Month"><i class="fas fa-calendar-alt"></i> Tháng:</label>
                    <input type="number" name="Month" id="Month" min="1" max="12"
                        value="<?= isset($_POST['Month']) ? (int)$_POST['Month'] : (int)date('n') ?>" required>
                </div>
                <div class="form-group">
                    <label for="Year"><i class="fas fa-calendar"></i> Năm:</label>
                    <input type="number" name="Year" id="Year"
                        value="<?= isset($_POST['Year']) ? (int)$_POST['Year'] : (int)date('Y') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="RoomFee"><i class="fas fa-home"></i> Tiền phòng:</label>
                <input type="number" step="1000" name="RoomFee" id="RoomFee"
                    required min="0"
                    value="<?= isset($_POST['RoomFee']) ? (float)$_POST['RoomFee'] : 0 ?>">
            </div>

            <!-- Điện -->
            <div class="form-group">
                <label><i class="fas fa-bolt"></i> Điện năng tiêu thụ:</label>
                <div class="inline-inputs">
                    <input type="number" step="0.1" name="ElectricUsage" id="ElectricUsage"
                        placeholder="Số kWh" min="0" required
                        value="<?= isset($_POST['ElectricUsage']) ? (float)$_POST['ElectricUsage'] : 0 ?>">
                    <span>x</span>
                    <input type="number" step="100" name="ElectricPrice" id="ElectricPrice"
                        placeholder="Đơn giá" value="<?= isset($_POST['ElectricPrice']) ? (float)$_POST['ElectricPrice'] : 3500 ?>"
                        min="0" required>
                    <span>=</span>
                    <input type="text" id="ElectricTotal" readonly value="0 ₫"
                        style="border:none;background:none;width:120px;">
                </div>
            </div>

            <!-- Nước -->
            <div class="form-group">
                <label><i class="fas fa-tint"></i> Nước tiêu thụ:</label>
                <div class="inline-inputs">
                    <input type="number" step="0.1" name="WaterUsage" id="WaterUsage"
                        placeholder="Số m³" min="0" required
                        value="<?= isset($_POST['WaterUsage']) ? (float)$_POST['WaterUsage'] : 0 ?>">
                    <span>x</span>
                    <input type="number" step="100" name="WaterPrice" id="WaterPrice"
                        placeholder="Đơn giá" value="<?= isset($_POST['WaterPrice']) ? (float)$_POST['WaterPrice'] : 10000 ?>"
                        min="0" required>
                    <span>=</span>
                    <input type="text" id="WaterTotal" readonly value="0 ₫"
                        style="border:none;background:none;width:120px;">
                </div>
            </div>

            <div class="form-group total-preview">
                <label><i class="fas fa-coins"></i> Tổng cộng:</label>
                <div id="totalValue">0 ₫</div>
            </div>

            <div class="form-group">
                <label for="DueDate"><i class="fas fa-clock"></i> Hạn thanh toán:</label>
                <input type="date" name="DueDate" id="DueDate"
                    value="<?= isset($_POST['DueDate']) ? htmlspecialchars($_POST['DueDate']) : date('Y-m-d', strtotime('+7 days')) ?>"
                    required>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-save"></i> Lưu hóa đơn
            </button>
        </form>
    </div>

    <script>
        $(function() {
            // Select2 tìm hợp đồng
            $('#ContractID').select2({
                placeholder: 'Chọn sinh viên hoặc nhập tên/MSSV...',
                ajax: {
                    url: 'invoice_search_contract_api.php',
                    type: 'POST',
                    dataType: 'json',
                    delay: 300,
                    data: params => ({
                        keyword: params.term || ''
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.ContractID,
                            text: `${item.FullName} (${item.StudentCode}) - ${item.BuildingName}${item.RoomNumber}`
                        }))
                    }),
                    cache: true
                },
                minimumInputLength: 0,
                width: '100%'
            });

            function updateTotals() {
                const room = Number($('#RoomFee').val()) || 0;
                const eUse = Number($('#ElectricUsage').val()) || 0;
                const ePrice = Number($('#ElectricPrice').val()) || 0;
                const wUse = Number($('#WaterUsage').val()) || 0;
                const wPrice = Number($('#WaterPrice').val()) || 0;

                const eTotal = eUse * ePrice;
                const wTotal = wUse * wPrice;
                const total = room + eTotal + wTotal;

                $('#ElectricTotal').val(new Intl.NumberFormat('vi-VN').format(eTotal) + ' ₫');
                $('#WaterTotal').val(new Intl.NumberFormat('vi-VN').format(wTotal) + ' ₫');
                $('#totalValue').text(new Intl.NumberFormat('vi-VN').format(total) + ' ₫');
            }

            $('#RoomFee, #ElectricUsage, #ElectricPrice, #WaterUsage, #WaterPrice').on('input', updateTotals);
            updateTotals();

            // Khi chọn hợp đồng → load giá + usage
            $('#ContractID').on('change', function() {
                const id = $(this).val();
                const month = $('#Month').val();
                const year = $('#Year').val();
                if (!id) return;

                $.post('invoice_get_price_api.php', {
                    id
                }, res => {
                    if (res && res.price) {
                        $('#RoomFee').val(res.price);
                        updateTotals();
                    }
                }, 'json');

                $.post('invoice_usage_api.php', {
                    id,
                    month,
                    year
                }, res => {
                    $('#ElectricUsage').val(res.Electric || 0);
                    $('#WaterUsage').val(res.Water || 0);
                    updateTotals();
                }, 'json');

                checkContractStatus();
            });

            // Khi đổi tháng/năm → update usage + check
            $('#Month, #Year').on('input change', function() {
                const id = $('#ContractID').val();
                const month = $('#Month').val();
                const year = $('#Year').val();
                if (!id) return;

                $.post('invoice_usage_api.php', {
                    id,
                    month,
                    year
                }, res => {
                    $('#ElectricUsage').val(res.Electric || 0);
                    $('#WaterUsage').val(res.Water || 0);
                    updateTotals();
                }, 'json');

                checkContractStatus();
            });

            // Check trạng thái HĐ + trùng hóa đơn
            function checkContractStatus() {
                const id = $('#ContractID').val();
                const month = $('#Month').val();
                const year = $('#Year').val();
                if (!id || !month || !year) return;

                $.post('invoice_contract_status_api.php', {
                    id,
                    month,
                    year
                }, res => {
                    const box = $('#contractWarning').show();

                    if (!res) return;

                    if (res.status !== 'Hiệu lực') {
                        box.html(`<i class="fas fa-times-circle"></i> ${res.message || 'Hợp đồng không còn hiệu lực.'}`)
                            .css({
                                background: '#ffe6e6',
                                color: '#c0392b',
                                border: '1px solid #e74c3c'
                            });
                        $('#submitBtn').prop('disabled', true).css('opacity', 0.6);
                    } else if (res.exists) {
                        box.html(`<i class="fas fa-exclamation-triangle"></i> ${res.message || 'Đã tồn tại hóa đơn tháng này.'}`)
                            .css({
                                background: '#fff3cd',
                                color: '#856404',
                                border: '1px solid #ffeeba'
                            });
                        $('#submitBtn').prop('disabled', true).css('opacity', 0.6);
                    } else {
                        box.html(`<i class="fas fa-check-circle"></i> Hợp đồng còn hiệu lực, có thể tạo hóa đơn.`)
                            .css({
                                background: '#e8f8f5',
                                color: '#1e8449',
                                border: '1px solid #27ae60'
                            });
                        $('#submitBtn').prop('disabled', false).css('opacity', 1);
                    }
                }, 'json');
            }

            $('#invoiceForm').on('submit', function() {
                $('#submitBtn')
                    .html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...')
                    .prop('disabled', true);
            });
        });
    </script>
</body>

</html>