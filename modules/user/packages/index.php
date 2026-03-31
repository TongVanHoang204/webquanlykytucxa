<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

$userId = $_SESSION['UserID'] ?? 0;
$student = null;
$studentId = 0;

if ($userId > 0) {
    if (!empty($_SESSION['StudentID'])) {
        $studentId = (int)$_SESSION['StudentID'];
    } else {
        $stmt = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $studentId = (int)$student['StudentID'];
            $_SESSION['StudentID'] = $studentId;
        }
        $stmt->close();
    }
}

// -------------------- LẤY DANH SÁCH BƯU PHẨM --------------------
$packages = false;
if ($studentId > 0) {
    $packages = $conn->query("
        SELECT PackageID, SenderInfo, PackageNotes, Status, ReceivedAt, DeliveredAt
        FROM packages
        WHERE StudentID = $studentId
        ORDER BY FIELD(Status, 'Chờ lấy', 'Đã nhận', 'Đã hoàn trả'), ReceivedAt DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bưu phẩm của tôi - Ký túc xá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 900px; margin: 0 auto; padding: 2rem; }
        .page-header h2 { font-size: 2rem; margin-bottom: 0.5rem; color: var(--text); }
        .page-header p { color: var(--muted); margin-bottom: 2rem; }
        .package-card {
            background: var(--card);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--stroke);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s;
        }
        .package-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .package-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .icon-chờ-lấy { background: #fef3c7; color: #d97706; }
        .icon-đã-nhận { background: #d1fae5; color: #059669; }
        .icon-đã-hoàn-trả { background: #f3f4f6; color: #6b7280; }
        
        .package-info { flex: 1; }
        .package-info h4 { margin: 0 0 5px 0; font-size: 1.1rem; color: var(--text); }
        .package-info p { margin: 0; color: var(--muted); font-size: 0.95rem; }
        
        .package-status {
            text-align: right;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-chờ-lấy { background: #fef3c7; color: #d97706; }
        .badge-đã-nhận { background: #d1fae5; color: #059669; }
        .badge-đã-hoàn-trả { background: #f3f4f6; color: #6b7280; }
        
        .time-box {
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 8px;
        }
        
        /* Chế độ tối tự động áp dụng qua biến CSS */
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-box-open"></i> Bưu phẩm của tôi</h2>
            <p>Theo dõi các kiện hàng, bưu phẩm được Ban quản lý/Bảo vệ khu Ký túc xá nhận giúp.</p>
        </div>

        <?php if ($packages && $packages->num_rows > 0): ?>
            <?php while ($pkg = $packages->fetch_assoc()): 
                $sClass = strtolower(str_replace(' ', '-', $pkg['Status'])); 
            ?>
            <div class="package-card">
                <div class="package-icon icon-<?= $sClass ?>">
                    <i class="fas <?= $pkg['Status'] === 'Đã nhận' ? 'fa-check' : ($pkg['Status'] === 'Đã hoàn trả' ? 'fa-undo' : 'fa-box') ?>"></i>
                </div>
                <div class="package-info">
                    <h4>Đơn vị giao: <?= htmlspecialchars($pkg['SenderInfo']) ?></h4>
                    <p>Ghi chú: <?= htmlspecialchars($pkg['PackageNotes'] ?: 'Không có') ?></p>
                    <div class="time-box">
                        <i class="far fa-clock"></i> BQL Nhận lúc: <?= date('H:i d/m/Y', strtotime($pkg['ReceivedAt'])) ?>
                        <?php if ($pkg['DeliveredAt']): ?>
                            <br><i class="fas fa-check-double" style="color:#059669;"></i> Bạn lấy lúc: <?= date('H:i d/m/Y', strtotime($pkg['DeliveredAt'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="package-status">
                    <span class="status-badge badge-<?= $sClass ?>"><?= htmlspecialchars($pkg['Status']) ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding: 40px; background:var(--card); border-radius:8px; border:1px solid var(--stroke);">
                <i class="fas fa-box fa-3x" style="color:var(--muted); opacity: 0.5;"></i>
                <h3 style="margin-top:20px; color:var(--text);">Chưa có bưu phẩm nào</h3>
                <p style="color:var(--muted);">Hiện không có bưu phẩm nào được gửi đến tên bạn.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
