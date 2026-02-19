<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']); // chỉ Admin/Manager được export

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// ===== 1️⃣ LẤY DỮ LIỆU HỢP ĐỒNG =====
$sql = "
    SELECT 
        c.ContractID,
        s.FullName       AS StudentName,
        r.RoomNumber     AS RoomName,
        r.RoomPrice      AS RoomPrice,
        c.StartDate,
        c.EndDate,
        c.Deposit,
        c.Status,
        c.PaymentStatus,
        c.CreatedAt
    FROM Contracts c
    LEFT JOIN Students s ON s.StudentID = c.StudentID
    LEFT JOIN Rooms r    ON r.RoomID    = c.RoomID
    ORDER BY c.CreatedAt DESC
";

try {
    $result = $conn->query($sql);
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Lỗi truy vấn Contracts:\n" . $e->getMessage();
    exit;
}

// ===== 2️⃣ HEADER FILE EXCEL =====
$filename = "HopDong_" . date('d-m-Y') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Pragma: no-cache");
header("Expires: 0");

// ===== 3️⃣ XUẤT FILE EXCEL =====
echo '
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table border="1" cellspacing="0" cellpadding="6"
       style="border-collapse:collapse; font-family: Arial, sans-serif; font-size:13px;">
    <thead style="background:#ddebf7; font-weight:bold; text-align:center;">
        <tr>
            <th>ID Hợp đồng</th>
            <th>Tên sinh viên</th>
            <th>Tên phòng</th>
            <th>Ngày bắt đầu</th>
            <th>Ngày kết thúc</th>
            <th>Giá phòng (₫/tháng)</th>
            <th>Tiền đặt cọc</th>
            <th>Tổng tiền hợp đồng</th>
            <th>Số tiền còn nợ</th>
            <th>Trạng thái</th>
            <th>Ngày tạo</th>
        </tr>
    </thead>
    <tbody>
';

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $price   = (float)$row['RoomPrice'];
        $deposit = (float)$row['Deposit'];

        // 🧮 Tính số tháng thuê
        $months = 1;
        if (!empty($row['StartDate']) && !empty($row['EndDate'])) {
            $start = new DateTime($row['StartDate']);
            $end   = new DateTime($row['EndDate']);
            $interval = $start->diff($end);
            $months = max(1, ($interval->y * 12) + $interval->m);
        }

        // 💰 Tổng tiền hợp đồng và còn nợ
        $total = $price * $months;
        $remaining = max(0, $total - $deposit);

        // Nếu PaymentStatus = 'Đã thanh toán' → còn nợ = 0
        if (mb_strtolower(trim($row['PaymentStatus']), 'UTF-8') === 'đã thanh toán') {
            $remaining = 0;
        }

        echo '<tr>';
        echo '<td style="text-align:center">' . htmlspecialchars($row['ContractID']) . '</td>';
        echo '<td>' . htmlspecialchars($row['StudentName'] ?? '') . '</td>';
        echo '<td style="text-align:center">' . htmlspecialchars($row['RoomName'] ?? '') . '</td>';
        echo '<td style="text-align:center">' . htmlspecialchars($row['StartDate']) . '</td>';
        echo '<td style="text-align:center">' . htmlspecialchars($row['EndDate']) . '</td>';
        echo '<td style="text-align:right">' . number_format($price, 0, ',', '.') . ' ₫</td>';
        echo '<td style="text-align:right; color:#2e86de;">' . number_format($deposit, 0, ',', '.') . ' ₫</td>';
        echo '<td style="text-align:right">' . number_format($total, 0, ',', '.') . ' ₫</td>';
        echo '<td style="text-align:right; color:#e67e22;">' . number_format($remaining, 0, ',', '.') . ' ₫</td>';
        echo '<td style="text-align:center">' . htmlspecialchars($row['Status']) . '</td>';
        echo '<td style="text-align:center">' . htmlspecialchars($row['CreatedAt']) . '</td>';
        echo '</tr>';
    }
}

echo '</tbody></table>';
exit;
