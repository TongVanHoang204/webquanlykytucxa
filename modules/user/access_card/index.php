<?php
/**
 * modules/user/access_card/index.php
 * Trang Thẻ QR Code cá nhân của Sinh viên
 * - Tự động tạo thẻ QR nếu sinh viên chưa có
 * - Hiển thị mã QR để quét tại cổng / bảo vệ
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

$userId    = $_SESSION['UserID'] ?? 0;
$studentId = 0;
$studentInfo = null;

// Lấy thông tin sinh viên
if ($userId > 0) {
    $stmt = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.StudentCode, s.Avatar,
               r.RoomNumber, b.BuildingName
        FROM students s
        LEFT JOIN contracts c ON s.StudentID = c.StudentID AND c.Status = 'Hiệu lực'
        LEFT JOIN rooms r ON c.RoomID = r.RoomID
        LEFT JOIN buildings b ON r.BuildingID = b.BuildingID
        WHERE s.UserID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $studentInfo = $res->fetch_assoc();
        $studentId   = (int)$studentInfo['StudentID'];
    }
    $stmt->close();
}

if ($studentId === 0) {
    // Không phải sinh viên -> hiển thị thông báo
    echo '<div style="text-align:center; padding:40px; color:var(--muted);">';
    echo '<i class="fas fa-id-card fa-3x"></i>';
    echo '<p style="margin-top:15px;">Tài khoản này chưa liên kết hồ sơ sinh viên.</p>';
    echo '</div>';
    include '../../includes/footer.php';
    exit;
}

// Kiểm tra thẻ hiện có
$cardStmt = $conn->prepare("SELECT CardID, CardCode, IssuedDate, IsActive FROM accesscards WHERE StudentID = ? ORDER BY IssuedDate DESC LIMIT 1");
$cardStmt->bind_param("i", $studentId);
$cardStmt->execute();
$cardRes  = $cardStmt->get_result();
$card     = $cardRes ? $cardRes->fetch_assoc() : null;
$cardStmt->close();

// Nếu chưa có thẻ → tự động cấp
if (!$card) {
    // CardCode = "KTX-{StudentCode}-{timestamp}"
    $cardCode = 'KTX-' . preg_replace('/[^A-Za-z0-9]/', '', $studentInfo['StudentCode']) . '-' . time();
    $ins = $conn->prepare("INSERT INTO accesscards (StudentID, CardCode, CardType, IssuedDate, IsActive) VALUES (?, ?, 'QR', NOW(), 1)");
    $ins->bind_param("is", $studentId, $cardCode);
    $ins->execute();
    $ins->close();
    
    // Reload
    $cardStmt2 = $conn->prepare("SELECT CardID, CardCode, IssuedDate, IsActive FROM accesscards WHERE StudentID = ? ORDER BY IssuedDate DESC LIMIT 1");
    $cardStmt2->bind_param("i", $studentId);
    $cardStmt2->execute();
    $card = $cardStmt2->get_result()->fetch_assoc();
    $cardStmt2->close();
}

// Nội dung QR sẽ là URL xác minh (staff quét sẽ hiện thông tin sinh viên)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$verifyUrl = $base_url . '/modules/staff/access_card/verify.php?code=' . urlencode($card['CardCode']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thẻ QR Sinh Viên - Ký túc xá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- QR Code generator (nhẹ, không cần cài đặt) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        .card-page { max-width: 520px; margin: 2rem auto; padding: 0 1rem; }

        .student-card {
            background: var(--card);
            border: 1px solid var(--stroke);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        }

        /* Header gradient */
        .card-header-band {
            background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
            padding: 28px 24px 50px;
            position: relative;
            color: #fff;
        }
        .card-header-band h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.85;
        }
        .card-header-band h2 {
            margin: 6px 0 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .card-logo {
            position: absolute;
            right: 24px;
            top: 24px;
            width: 44px; height: 44px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }
        .wave-sep {
            background: var(--card);
            margin-top: -30px;
            border-radius: 30px 30px 0 0;
            padding-top: 20px;
        }

        /* QR section */
        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 24px 24px;
            background: var(--card);
        }
        .qr-wrap {
            background: #fff;
            padding: 16px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 16px;
        }
        .qr-wrap canvas { display: block; border-radius: 4px; }

        .card-code {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: var(--muted);
            letter-spacing: 2px;
            text-align: center;
        }

        /* Info rows */
        .card-info {
            padding: 0 24px 24px;
            background: var(--card);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .info-box {
            background: var(--bg, #f8f9fa);
            border-radius: 10px;
            padding: 12px 14px;
        }
        .info-box .label {
            font-size: 0.72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-box .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
        }

        /* Status badge */
        .status-row {
            padding: 0 24px 24px;
            background: var(--card);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .badge-active {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .issued-text {
            font-size: 0.82rem;
            color: var(--muted);
        }

        /* Action buttons */
        .card-actions {
            padding: 0 24px 28px;
            background: var(--card);
            display: flex;
            gap: 10px;
        }
        .btn-download {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4361ee, #7209b7);
            color: #fff;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn-download:hover { opacity: 0.9; }
        .btn-refresh {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 10px;
            background: var(--bg, #f3f4f6);
            color: var(--text);
            border: 1px solid var(--stroke);
            font-size: 0.9rem;
            cursor: pointer;
        }

        /* Instructions */
        .instructions {
            margin-top: 20px;
            background: var(--card);
            border: 1px solid var(--stroke);
            border-radius: 12px;
            padding: 1.25rem;
        }
        .instructions h4 {
            margin: 0 0 10px;
            font-size: 0.95rem;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .instructions ol {
            margin: 0;
            padding-left: 1.2rem;
            color: var(--muted);
            font-size: 0.875rem;
            line-height: 1.8;
        }

        @media print {
            body * { visibility: hidden; }
            .student-card, .student-card * { visibility: visible; }
            .student-card { position: fixed; top: 0; left: 50%; transform: translateX(-50%); }
            .card-actions { display: none; }
        }
    </style>
</head>
<body>
<div class="card-page">

    <div class="student-card" id="studentCard">
        <!-- Header Band -->
        <div class="card-header-band">
            <div class="card-logo"><i class="fas fa-building"></i></div>
            <h3>Ký túc xá Sinh viên</h3>
            <h2><?= htmlspecialchars($studentInfo['FullName']) ?></h2>
        </div>

        <!-- Wave separator -->
        <div class="wave-sep">
            <!-- QR Code -->
            <div class="qr-section">
                <div class="qr-wrap">
                    <canvas id="qrCanvas"></canvas>
                </div>
                <div class="card-code"><?= htmlspecialchars($card['CardCode']) ?></div>
            </div>

            <!-- Info Grid -->
            <div class="card-info">
                <div class="info-box">
                    <div class="label">Mã sinh viên</div>
                    <div class="value"><?= htmlspecialchars($studentInfo['StudentCode']) ?></div>
                </div>
                <div class="info-box">
                    <div class="label">Loại thẻ</div>
                    <div class="value"><i class="fas fa-qrcode"></i> QR Code</div>
                </div>
                <div class="info-box">
                    <div class="label">Phòng ở</div>
                    <div class="value"><?= htmlspecialchars($studentInfo['RoomNumber'] ?? 'Chưa có phòng') ?></div>
                </div>
                <div class="info-box">
                    <div class="label">Tòa nhà</div>
                    <div class="value"><?= htmlspecialchars($studentInfo['BuildingName'] ?? '—') ?></div>
                </div>
            </div>

            <!-- Status -->
            <div class="status-row">
                <span class="badge-active <?= $card['IsActive'] ? '' : 'badge-inactive' ?>">
                    <i class="fas fa-<?= $card['IsActive'] ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= $card['IsActive'] ? 'Thẻ đang hoạt động' : 'Thẻ bị vô hiệu hoá' ?>
                </span>
                <span class="issued-text">
                    Cấp: <?= date('d/m/Y', strtotime($card['IssuedDate'])) ?>
                </span>
            </div>

            <!-- Actions -->
            <div class="card-actions">
                <button class="btn-download" onclick="downloadQR()">
                    <i class="fas fa-download"></i> Tải xuống QR
                </button>
                <button class="btn-refresh" onclick="window.print()">
                    <i class="fas fa-print"></i> In thẻ
                </button>
            </div>
        </div>
    </div>

    <!-- Hướng dẫn sử dụng -->
    <div class="instructions">
        <h4><i class="fas fa-info-circle" style="color:#4361ee;"></i> Hướng dẫn sử dụng thẻ</h4>
        <ol>
            <li>Mở trang này trên điện thoại, giữ màn hình đủ sáng.</li>
            <li>Chìa mã QR về phía bảo vệ/bộ phận quản lý khi ra vào cổng.</li>
            <li>Nhân viên sẽ quét mã để xác nhận danh tính của bạn.</li>
            <li>Nếu thẻ bị mất hoặc hết hiệu lực, hãy liên hệ ban quản lý KTX để được cấp lại.</li>
        </ol>
    </div>

</div>

<script>
// Tạo mã QR từ URL xác minh
const qrData = <?= json_encode($verifyUrl) ?>;

QRCode.toCanvas(document.getElementById('qrCanvas'), qrData, {
    width: 220,
    margin: 0,
    color: {
        dark: '#111827',
        light: '#ffffff'
    }
}, function (error) {
    if (error) console.error('QR Error:', error);
});

function downloadQR() {
    const canvas = document.getElementById('qrCanvas');
    const link = document.createElement('a');
    link.download = 'QR-KTX-<?= htmlspecialchars($studentInfo['StudentCode']) ?>.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}
</script>

<?php include '../../includes/footer.php'; ?>
</body>
</html>
