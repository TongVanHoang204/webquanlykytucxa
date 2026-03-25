<?php
// ==============================
// 💳 HÓA ĐƠN CỦA SINH VIÊN
// ==============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../../db_connect.php';
include '../../../includes/header.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Student', 'Admin', 'Manager']);

$userID = (int)$_SESSION['UserID'];

$studentQuery = $conn->query("SELECT StudentID, FullName FROM Students WHERE UserID = $userID");
if (!$studentQuery || $studentQuery->num_rows == 0) {
    echo "<div class='alert alert-danger'>Không tìm thấy thông tin sinh viên!</div>";
    include '../../../includes/footer.php';
    exit;
}
$student = $studentQuery->fetch_assoc();
$studentID = $student['StudentID'];

// 🔍 Lọc
$month_filter = $_GET['month'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');
$status_filter = $_GET['status'] ?? '';

// 📦 Truy vấn hóa đơn của sinh viên
$sql = "
    SELECT 
        i.InvoiceID, i.Month, i.Year,
        i.RoomFee, i.ElectricUsage, i.ElectricPrice,
        i.WaterUsage, i.WaterPrice, i.TotalAmount,
        i.Status, i.CreatedAt,
        r.RoomNumber, b.BuildingName
    FROM Invoices i
    INNER JOIN Contracts c ON i.ContractID = c.ContractID
    INNER JOIN Rooms r ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE c.StudentID = $studentID
";

// Thêm bộ lọc
if ($status_filter != '') {
    $sql .= " AND i.Status = '$status_filter'";
}
if ($month_filter != '') {
    $sql .= " AND i.Month = '$month_filter'";
}
if ($year_filter != '') {
    $sql .= " AND i.Year = '$year_filter'";
}

$sql .= " ORDER BY i.Year DESC, i.Month DESC";
$result = $conn->query($sql);

// 📊 Thống kê nhanh
$stats_sql = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN i.Status = 'Đã thanh toán' THEN 1 ELSE 0 END) AS paid,
        SUM(CASE WHEN i.Status = 'Chưa thanh toán' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN i.Status = 'Chưa thanh toán' THEN i.TotalAmount ELSE 0 END) AS total_debt
    FROM Invoices i
    INNER JOIN Contracts c ON i.ContractID = c.ContractID
    WHERE c.StudentID = $studentID
";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

addLog(
    $conn,
    $_SESSION['UserID'] ?? null,
    'View bills',
    'Bills',
    'Sinh viên xem danh sách hóa đơn',
    'history'
);

// Kiểm tra hóa đơn nào đang có payment chờ xác nhận
$pendingPayments = [];
$pendingSQL = "SELECT InvoiceID FROM payments WHERE StudentID = $studentID AND Status = 'Chờ xác nhận'";
$pendingResult = $conn->query($pendingSQL);
if ($pendingResult) {
    while ($pRow = $pendingResult->fetch_assoc()) {
        $pendingPayments[$pRow['InvoiceID']] = true;
    }
}


?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa đơn của tôi - Ký Túc Xá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/student-bills.css">
</head>
<body>
    <div class="student-bills-container">
        <!-- Header Section -->
        <div class="bills-student-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Hóa đơn của tôi</h1>
            <p>Xin chào <strong><?= htmlspecialchars($student['FullName']) ?></strong> - Quản lý và theo dõi hóa đơn ký túc xá</p>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-stat-card total">
                <i class="fas fa-receipt"></i>
                <span class="number"><?= $stats['total'] ?? 0 ?></span>
                <span class="label">Tổng hóa đơn</span>
            </div>
            
            <div class="quick-stat-card paid">
                <i class="fas fa-check-circle"></i>
                <span class="number"><?= $stats['paid'] ?? 0 ?></span>
                <span class="label">Đã thanh toán</span>
            </div>
            
            <div class="quick-stat-card pending">
                <i class="fas fa-clock"></i>
                <span class="number"><?= $stats['pending'] ?? 0 ?></span>
                <span class="label">Chờ thanh toán</span>
            </div>
            
            <div class="quick-stat-card debt">
                <i class="fas fa-money-bill-wave"></i>
                <span class="number"><?= number_format($stats['total_debt'] ?? 0, 0, ',', '.') ?>₫</span>
                <span class="label">Tổng nợ</span>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section-student">
            <form method="GET" class="filter-form-student">
                <div class="filter-grid-student">
                    <div class="filter-group-student">
                        <label for="status"><i class="fas fa-filter"></i> Trạng thái</label>
                        <select id="status" name="status" class="filter-select-student">
                            <option value="">Tất cả trạng thái</option>
                            <option value="Đã thanh toán" <?= $status_filter === 'Đã thanh toán' ? 'selected' : '' ?>>Đã thanh toán</option>
                            <option value="Chưa thanh toán" <?= $status_filter === 'Chưa thanh toán' ? 'selected' : '' ?>>Chưa thanh toán</option>
                        </select>
                    </div>
                    
                    <div class="filter-group-student">
                        <label for="month"><i class="fas fa-calendar"></i> Tháng</label>
                        <select id="month" name="month" class="filter-select-student">
                            <option value="">Tất cả tháng</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $month_filter == $i ? 'selected' : '' ?>>
                                    Tháng <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group-student">
                        <label for="year"><i class="fas fa-calendar-alt"></i> Năm</label>
                        <select id="year" name="year" class="filter-select-student">
                            <?php for ($i = date('Y'); $i >= 2023; $i--): ?>
                                <option value="<?= $i ?>" <?= $year_filter == $i ? 'selected' : '' ?>>
                                    Năm <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions-student">
                    <button type="submit" class="btn-student btn-primary-student">
                        <i class="fas fa-filter"></i> Áp dụng bộ lọc
                    </button>
                    <a href="bills.php" class="btn-student btn-secondary-student">
                        <i class="fas fa-redo"></i> Đặt lại
                    </a>
                </div>
            </form>
        </div>

        <!-- Bills Table -->
        <div class="table-container-student">
            <?php if ($result && $result->num_rows > 0): ?>
                <table class="bills-table-student">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Phòng</th>
                            <th>Kỳ</th>
                            <th>Tiền phòng</th>
                            <th>Điện (kWh)</th>
                            <th>Nước (m³)</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $row['InvoiceID'] ?></strong></td>
                                <td><?= htmlspecialchars($row['BuildingName']) ?> - <?= htmlspecialchars($row['RoomNumber']) ?></td>
                                <td>Tháng <?= $row['Month'] ?>/<?= $row['Year'] ?></td>
                                <td><?= number_format($row['RoomFee'], 0, ',', '.') ?>₫</td>
                                <td><?= $row['ElectricUsage'] ?></td>
                                <td><?= $row['WaterUsage'] ?></td>
                                <td class="amount-student <?= $row['Status'] === 'Đã thanh toán' ? 'amount-paid-student' : 'amount-unpaid-student' ?>">
                                    <?= number_format($row['TotalAmount'], 0, ',', '.') ?>₫
                                </td>
                                <td>
                                    <?php 
                                    $isPending = isset($pendingPayments[$row['InvoiceID']]);
                                    if ($isPending): 
                                    ?>
                                    <span class="status-badge-student" style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                        <i class="fas fa-hourglass-half"></i>
                                        Chờ xác nhận
                                    </span>
                                    <?php else: ?>
                                    <span class="status-badge-student status-<?= $row['Status'] === 'Đã thanh toán' ? 'paid' : 'unpaid' ?>-student">
                                        <i class="fas <?= $row['Status'] === 'Đã thanh toán' ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                                        <?= $row['Status'] ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                <td>
                                    <div class="action-buttons-student">
                                        <a href="bill_detail.php?id=<?= $row['InvoiceID'] ?>" class="btn-student btn-info-student btn-sm-student">
                                            <i class="fas fa-eye"></i> Chi tiết
                                        </a>
                                        <?php if ($row['Status'] === 'Chưa thanh toán' && !$isPending): ?>
                                            <button type="button" class="btn-student btn-success-student btn-sm-student pay-btn" 
                                                    onclick="openPaymentModal(<?= $row['InvoiceID'] ?>, <?= $row['TotalAmount'] ?>)">
                                                <i class="fas fa-credit-card"></i> Thanh toán
                                            </button>
                                        <?php elseif ($isPending): ?>
                                            <button type="button" class="btn-student btn-sm-student" disabled
                                                    style="background: #ffc107; color: #856404; border: none; cursor: not-allowed;">
                                                <i class="fas fa-hourglass-half"></i> Đang chờ
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-student">
                    <i class="fas fa-receipt"></i>
                    <h3>Không có hóa đơn nào</h3>
                    <p>Không tìm thấy hóa đơn phù hợp với tiêu chí tìm kiếm của bạn.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Summary Section -->
        <div class="export-section">
            <div class="export-info">
                <i class="fas fa-info-circle"></i>
                <span>Hiển thị <?= $result->num_rows ?> hóa đơn</span>
            </div>
            <div class="export-actions">
                <a href="print_bills.php?<?= http_build_query($_GET) ?>" class="btn-student btn-secondary-student" target="_blank">
                    <i class="fas fa-print"></i> In danh sách
                </a>
                <a href="export_bills.php?<?= http_build_query($_GET) ?>" class="btn-student btn-primary-student">
                    <i class="fas fa-download"></i> Xuất Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closePaymentModal()">&times;</button>
            <h3><i class="fas fa-credit-card"></i> Thanh toán hóa đơn</h3>
            <div id="paymentDetails">
                <!-- Payment details will be loaded here -->
            </div>
            <div class="payment-actions">
                <button type="button" class="btn-student btn-secondary-student" onclick="closePaymentModal()">Hủy</button>
                <button type="button" class="btn-student btn-success-student" onclick="processPayment()">Xác nhận thanh toán</button>
            </div>
        </div>
    </div>

    <script>
        // Auto-submit form when filters change
        document.getElementById('year').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('month').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });

        // Payment Modal Functions
        let currentInvoiceId = null;

        function openPaymentModal(invoiceId, amount) {
            currentInvoiceId = invoiceId;
            const modal = document.getElementById('paymentModal');
            const details = document.getElementById('paymentDetails');
            
            details.innerHTML = `
                <div class="payment-info">
                    <p><strong>Mã hóa đơn:</strong> #${invoiceId}</p>
                    <p><strong>Số tiền:</strong> ${amount.toLocaleString('vi-VN')}₫</p>
                    <p><strong>Ngày thanh toán:</strong> ${new Date().toLocaleDateString('vi-VN')}</p>
                </div>
                <div class="payment-methods">
                    <h4>Chọn phương thức thanh toán:</h4>
                    <div class="method-option">
                        <label>
                            <input type="radio" name="paymentMethod" value="bank" checked>
                            <i class="fas fa-university"></i>
                            <span>Chuyển khoản ngân hàng</span>
                        </label>
                    </div>
                    <div class="method-option">
                        <label>
                            <input type="radio" name="paymentMethod" value="momo">
                            <i class="fas fa-mobile-alt"></i>
                            <span>Ví MoMo</span>
                        </label>
                    </div>
                    <div class="method-option">
                        <label>
                            <input type="radio" name="paymentMethod" value="cash">
                            <i class="fas fa-money-bill"></i>
                            <span>Tiền mặt</span>
                        </label>
                    </div>
                </div>
                <div class="payment-instructions" id="paymentInstructions">
                    <p>Vui lòng chọn phương thức thanh toán để xem hướng dẫn</p>
                </div>
            `;
            
            // Add event listeners for payment method changes
            document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
                radio.addEventListener('change', updatePaymentInstructions);
            });
            
            updatePaymentInstructions(); // Initial call
            modal.style.display = 'flex';
        }

        function updatePaymentInstructions() {
            const method = document.querySelector('input[name="paymentMethod"]:checked').value;
            const instructions = document.getElementById('paymentInstructions');
            
            const instructionTexts = {
                'bank': `
                    <h5>Hướng dẫn chuyển khoản:</h5>
                    <p>• Ngân hàng: <strong>Vietcombank</strong></p>
                    <p>• Số tài khoản: <strong>0123456789</strong></p>
                    <p>• Chủ tài khoản: <strong>KY TUC XA SINH VIEN</strong></p>
                    <p>• Nội dung: <strong>Thanh toan HD#${currentInvoiceId}</strong></p>
                `,
                'momo': `
                    <h5>Hướng dẫn thanh toán MoMo:</h5>
                    <p>• Số điện thoại: <strong>0901234567</strong></p>
                    <p>• Tên người nhận: <strong>KY TUC XA</strong></p>
                    <p>• Nội dung: <strong>Thanh toan HD#${currentInvoiceId}</strong></p>
                    <p>• Quét QR code để thanh toán nhanh</p>
                `,
                'cash': `
                    <h5>Hướng dẫn thanh toán tiền mặt:</h5>
                    <p>• Địa điểm: <strong>Văn phòng Ký túc xá</strong></p>
                    <p>• Thời gian: <strong>7:30 - 17:00 (Thứ 2 - Thứ 6)</strong></p>
                    <p>• Mang theo: <strong>CMND/Thẻ sinh viên</strong></p>
                `
            };
            
            instructions.innerHTML = instructionTexts[method];
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            currentInvoiceId = null;
        }

        function processPayment() {
            if (!currentInvoiceId) return;
            
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            
            // Show confirmation with payment details
            const amount = document.querySelector('.payment-info p:nth-child(2)').textContent.split(':')[1].trim();
            
            if (confirm(`Xác nhận thanh toán:\n\nHóa đơn: #${currentInvoiceId}\nSố tiền: ${amount}\nPhương thức: ${getPaymentMethodName(paymentMethod)}\n\nBạn có chắc chắn muốn thanh toán?`)) {
                // Show loading state
                const confirmBtn = document.querySelector('.payment-actions .btn-success-student');
                const originalText = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                confirmBtn.disabled = true;
                
                // Simulate payment processing
                setTimeout(() => {
                    // Redirect to payment processing page
                    window.location.href = `bill_pay.php?id=${currentInvoiceId}&method=${paymentMethod}`;
                }, 2000);
            }
        }

        function getPaymentMethodName(method) {
            const methods = {
                'bank': 'Chuyển khoản ngân hàng',
                'momo': 'Ví MoMo',
                'cash': 'Tiền mặt'
            };
            return methods[method] || method;
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closePaymentModal();
            }
        });
    </script>

    <style>
        .export-section {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .export-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .export-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Payment Modal Styles */
        .payment-info {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .payment-info p {
            margin: 0.5rem 0;
        }

        .payment-methods {
            margin: 1.5rem 0;
        }

        .method-option {
            margin: 1rem 0;
            padding: 1rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .method-option:hover {
            border-color: var(--primary);
        }

        .method-option label {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            margin: 0;
        }

        .method-option input[type="radio"] {
            margin: 0;
        }

        .method-option i {
            font-size: 1.5rem;
            color: var(--primary);
            width: 30px;
        }

        .payment-instructions {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--info);
        }

        .payment-instructions h5 {
            margin-top: 0;
            color: var(--info);
        }

        .payment-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        /* Make sure pay button is visible */
        .pay-btn {
            background: var(--success) !important;
            color: white !important;
            border: none !important;
        }

        .pay-btn:hover {
            background: #27ae60 !important;
            transform: translateY(-2px) !important;
        }
    </style>

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>