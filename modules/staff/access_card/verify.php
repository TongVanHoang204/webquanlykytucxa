<?php
/**
 * modules/staff/access_card/verify.php
 * Trang xác minh thẻ QR — Staff quét mã QR sinh viên
 * Hoạt động theo 2 cách:
 *  - GET  ?code=xxx  → Hiện thông tin sinh viên (mở từ QR)
 *  - POST (form nhập tay) → Staff gõ mã code để tra cứu
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/admin_header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

$code     = trim($_GET['code'] ?? $_POST['code'] ?? '');
$student  = null;
$card     = null;
$notFound = false;

if ($code !== '') {
    // Tìm thẻ theo mã
    $stmt = $conn->prepare("
        SELECT ac.CardID, ac.CardCode, ac.CardType, ac.IssuedDate, ac.IsActive,
               s.StudentID, s.FullName, s.StudentCode, s.Phone, s.Email,
               r.RoomNumber, b.BuildingName
        FROM accesscards ac
        JOIN students s ON ac.StudentID = s.StudentID
        LEFT JOIN contracts c ON s.StudentID = c.StudentID AND c.Status = 'Hiệu lực'
        LEFT JOIN rooms r ON c.RoomID = r.RoomID
        LEFT JOIN buildings b ON r.BuildingID = b.BuildingID
        WHERE ac.CardCode = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row     = $res->fetch_assoc();
        $card    = $row;
        $student = $row;
    } else {
        $notFound = true;
    }
    $stmt->close();
}
?>

<style>
.verify-wrapper {
    max-width: 640px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}
.verify-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1.5rem;
}
.verify-title .icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #4361ee, #7209b7);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.3rem;
}
.verify-title h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
}
.verify-title p { margin: 4px 0 0; color: var(--muted); font-size: 0.9rem; }

/* Search form */
.search-card {
    background: var(--card);
    border: 1px solid var(--stroke);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.search-card h3 {
    margin: 0 0 1rem;
    font-size: 0.95rem;
    color: var(--text);
    font-weight: 600;
}
.search-row {
    display: flex;
    gap: 10px;
}
.search-row input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid var(--stroke);
    border-radius: 8px;
    background: var(--bg);
    color: var(--text);
    font-size: 1rem;
    transition: border-color 0.2s;
}
.search-row input:focus {
    outline: none;
    border-color: #4361ee;
}
.search-row button {
    padding: 12px 20px;
    background: #4361ee;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}
.search-row button:hover { background: #3a56d4; }

/* QR Scanner area */
.scanner-area {
    background: var(--card);
    border: 1px solid var(--stroke);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    text-align: center;
}
.scanner-area h3 {
    margin: 0 0 1rem;
    font-size: 0.95rem;
    color: var(--text);
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
#reader {
    width: 100%;
    max-width: 380px;
    margin: 0 auto;
    border-radius: 12px;
    overflow: hidden;
}
#scan-result-msg {
    margin-top: 10px;
    font-size: 0.9rem;
    color: var(--muted);
}
.btn-scan {
    margin-top: 12px;
    padding: 10px 24px;
    background: var(--bg);
    border: 1px solid var(--stroke);
    border-radius: 8px;
    color: var(--text);
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-scan.active {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444;
}

/* Result card */
.result-success {
    background: var(--card);
    border: 2px solid #10b981;
    border-radius: 16px;
    overflow: hidden;
}
.result-error {
    background: var(--card);
    border: 2px solid #ef4444;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
}
.result-header {
    background: linear-gradient(135deg, #10b981, #059669);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 14px;
    color: #fff;
}
.result-header .big-check {
    width: 52px; height: 52px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.result-header h2 { margin: 0; font-size: 1.4rem; font-weight: 700; }
.result-header p  { margin: 4px 0 0; opacity: 0.85; font-size: 0.9rem; }

.result-body {
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.r-box {
    background: var(--bg, #f8f9fa);
    border-radius: 10px;
    padding: 12px 14px;
}
.r-box .r-label {
    font-size: 0.72rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.r-box .r-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text);
}
.result-footer {
    padding: 1rem 1.5rem 1.5rem;
    display: flex;
    gap: 10px;
}
.btn-green {
    flex: 1;
    padding: 12px;
    background: #10b981;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-gray {
    padding: 12px 16px;
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--stroke);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
}

.card-status-active   { color: #065f46; background: #d1fae5; }
.card-status-inactive { color: #991b1b; background: #fee2e2; }
.card-status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    display: inline-block;
    margin-top: 4px;
}

@media (max-width: 500px) {
    .result-body { grid-template-columns: 1fr; }
    .search-row { flex-direction: column; }
}
</style>

<div class="verify-wrapper">
    <div class="verify-title">
        <div class="icon"><i class="fas fa-qrcode"></i></div>
        <div>
            <h1>Xác minh Thẻ Sinh viên</h1>
            <p>Quét mã QR hoặc nhập mã thẻ để tra cứu thông tin</p>
        </div>
    </div>

    <!-- Manual search form -->
    <div class="search-card">
        <h3><i class="fas fa-keyboard"></i> Nhập mã thẻ thủ công</h3>
        <form method="POST" action="verify.php">
            <div class="search-row">
                <input type="text" name="code" placeholder="VD: KTX-SV001-1743..." value="<?= htmlspecialchars($code) ?>" required autocomplete="off">
                <button type="submit"><i class="fas fa-search"></i> Tra cứu</button>
            </div>
        </form>
    </div>

    <!-- QR Camera Scanner -->
    <div class="scanner-area">
        <h3><i class="fas fa-camera"></i> Quét mã QR bằng camera</h3>
        <div id="reader"></div>
        <div id="scan-result-msg">Nhấn nút bên dưới để bật camera và quét mã QR</div>
        <button class="btn-scan" id="scanBtn" onclick="toggleScanner()">
            <i class="fas fa-camera"></i> Bật Scanner
        </button>
    </div>

    <!-- Result area -->
    <?php if ($code !== ''): ?>
        <?php if ($student && $card): ?>
            <div class="result-success">
                <div class="result-header">
                    <div class="big-check"><i class="fas fa-check"></i></div>
                    <div>
                        <h2><?= htmlspecialchars($student['FullName']) ?></h2>
                        <p>Xác minh thành công lúc <?= date('H:i, d/m/Y') ?></p>
                    </div>
                </div>
                <div class="result-body">
                    <div class="r-box">
                        <div class="r-label">Mã sinh viên</div>
                        <div class="r-value"><?= htmlspecialchars($student['StudentCode']) ?></div>
                    </div>
                    <div class="r-box">
                        <div class="r-label">Trạng thái thẻ</div>
                        <div class="r-value">
                            <span class="card-status-badge <?= $card['IsActive'] ? 'card-status-active' : 'card-status-inactive' ?>">
                                <?= $card['IsActive'] ? '✓ Hợp lệ' : '✗ Vô hiệu' ?>
                            </span>
                        </div>
                    </div>
                    <div class="r-box">
                        <div class="r-label">Phòng ở</div>
                        <div class="r-value"><?= htmlspecialchars($student['RoomNumber'] ?? 'Chưa có phòng') ?></div>
                    </div>
                    <div class="r-box">
                        <div class="r-label">Tòa nhà</div>
                        <div class="r-value"><?= htmlspecialchars($student['BuildingName'] ?? '—') ?></div>
                    </div>
                    <div class="r-box">
                        <div class="r-label">SĐT</div>
                        <div class="r-value"><?= htmlspecialchars($student['Phone'] ?? '—') ?></div>
                    </div>
                    <div class="r-box">
                        <div class="r-label">Ngày cấp thẻ</div>
                        <div class="r-value"><?= date('d/m/Y', strtotime($card['IssuedDate'])) ?></div>
                    </div>
                    <div class="r-box" style="grid-column: 1/-1;">
                        <div class="r-label">Mã thẻ</div>
                        <div class="r-value" style="font-family: monospace; font-size: 0.85rem; letter-spacing: 1px;">
                            <?= htmlspecialchars($card['CardCode']) ?>
                        </div>
                    </div>
                </div>
                <div class="result-footer">
                    <button class="btn-green" onclick="window.location.href='verify.php'">
                        <i class="fas fa-qrcode"></i> Quét thẻ tiếp theo
                    </button>
                    <a href="<?= $base ?>modules/staff/students/student_list.php" class="btn-gray">
                        <i class="fas fa-list"></i> DS Sinh viên
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="result-error">
                <i class="fas fa-times-circle fa-3x" style="color:#ef4444;"></i>
                <h3 style="color:var(--text); margin-top:12px;">Không tìm thấy thẻ</h3>
                <p style="color:var(--muted);">Mã thẻ <strong><?= htmlspecialchars($code) ?></strong> không tồn tại trong hệ thống.</p>
                <button onclick="window.location.href='verify.php'" style="margin-top:12px; padding:10px 24px; background:#ef4444; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600;">
                    Thử lại
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- html5-qrcode library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;
let scannerRunning = false;

function toggleScanner() {
    const btn = document.getElementById('scanBtn');
    const msg = document.getElementById('scan-result-msg');

    if (!scannerRunning) {
        // Khởi động scanner
        html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText) => {
                // Khi quét được mã → redirect sang trang verify với code
                const url = new URL(decodedText);
                const code = url.searchParams.get('code');
                if (code) {
                    window.location.href = 'verify.php?code=' + encodeURIComponent(code);
                } else {
                    // Nếu mã QR không phải URL hệ thống, thử tra cứu thẳng
                    window.location.href = 'verify.php?code=' + encodeURIComponent(decodedText);
                }
            },
            (errorMsg) => { /* bỏ qua lỗi quét liên tục */ }
        ).then(() => {
            scannerRunning = true;
            btn.textContent = '';
            btn.innerHTML = '<i class="fas fa-stop"></i> Tắt Scanner';
            btn.classList.add('active');
            msg.textContent = 'Hướng camera vào mã QR của sinh viên...';
        }).catch(err => {
            msg.textContent = 'Không thể mở camera: ' + err;
        });
    } else {
        // Dừng scanner
        html5QrCode.stop().then(() => {
            scannerRunning = false;
            btn.innerHTML = '<i class="fas fa-camera"></i> Bật Scanner';
            btn.classList.remove('active');
            msg.textContent = 'Scanner đã tắt.';
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
