<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$pageTitle = 'Quét QR cổng';
$csrf = csrfToken();
require_once __DIR__ . '/../../../includes/admin_header.php';
?>
<section class="mod-container" style="display:grid;gap:24px;">
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-door-open"></i> Quét QR cổng ký túc xá</h2>
            <p style="margin:6px 0 0;color:var(--text-secondary);">Dùng camera để quét hoặc nhập tay token dự phòng khi cần.</p>
        </div>
    </div>

    <div class="mod-card" style="padding:24px;display:grid;gap:16px;">
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));align-items:end;">
            <label style="display:grid;gap:8px;">
                <span>Tên cổng</span>
                <input id="gateName" class="form-control" type="text" value="Cổng chính">
            </label>
            <label style="display:grid;gap:8px;">
                <span>Hướng quét</span>
                <select id="gateDirection" class="form-control">
                    <option value="">Tự suy luận</option>
                    <option value="IN">Vào</option>
                    <option value="OUT">Ra</option>
                </select>
            </label>
        </div>

        <div id="gateScannerReader" style="min-height:320px;"></div>

        <div style="display:grid;gap:8px;">
            <label for="manualGateToken">Token dự phòng</label>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <input id="manualGateToken" class="form-control" type="text" placeholder="Dán token hoặc nội dung QR">
                <button id="manualGateScanBtn" class="mod-btn mod-btn-primary" type="button">Quét thủ công</button>
            </div>
        </div>

        <div id="gateScannerResult" style="color:var(--text-secondary);">Sẵn sàng quét mã QR.</div>
    </div>
</section>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(() => {
    const baseUrl = document.body.dataset.base || '/';
    const csrfToken = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const resultEl = document.getElementById('gateScannerResult');
    const gateNameEl = document.getElementById('gateName');
    const directionEl = document.getElementById('gateDirection');
    const manualTokenEl = document.getElementById('manualGateToken');
    const manualBtn = document.getElementById('manualGateScanBtn');
    let lastToken = '';
    let lastScanAt = 0;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function submitScan(token) {
        const trimmed = String(token || '').trim();
        if (!trimmed) {
            throw new Error('Thiếu token để quét.');
        }

        const now = Date.now();
        if (trimmed === lastToken && now - lastScanAt < 3000) {
            return;
        }
        lastToken = trimmed;
        lastScanAt = now;

        resultEl.textContent = 'Đang gửi yêu cầu quét QR...';

        const response = await fetch(`${baseUrl}modules/api/scan_gate_qr.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                token: trimmed,
                gate_name: gateNameEl.value.trim(),
                direction: directionEl.value,
            }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || payload.reason || 'Quét QR thất bại.');
        }

        resultEl.innerHTML = `
            <div style="padding:16px;border-radius:12px;background:rgba(16,185,129,0.12);color:#065f46;">
                <strong>Quét thành công</strong><br>
                Sinh viên: ${escapeHtml(payload.studentName || '')}<br>
                Hướng: ${escapeHtml(payload.direction || '')}<br>
                Cổng: ${escapeHtml(payload.gateName || gateNameEl.value)}
            </div>
        `;
    }

    manualBtn.addEventListener('click', () => {
        submitScan(manualTokenEl.value).catch((error) => {
            resultEl.innerHTML = `<div style="padding:16px;border-radius:12px;background:rgba(239,68,68,0.12);color:#991b1b;">${escapeHtml(error.message)}</div>`;
        });
    });

    if (window.Html5Qrcode) {
        const scanner = new Html5Qrcode('gateScannerReader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            (decodedText) => {
                submitScan(decodedText).catch((error) => {
                    resultEl.innerHTML = `<div style="padding:16px;border-radius:12px;background:rgba(239,68,68,0.12);color:#991b1b;">${escapeHtml(error.message)}</div>`;
                });
            },
            () => {}
        ).catch(() => {
            resultEl.textContent = 'Không khởi tạo được camera. Bạn có thể dán token dự phòng để quét thủ công.';
        });
    } else {
        resultEl.textContent = 'Không tải được thư viện quét camera. Bạn có thể dán token dự phòng để quét thủ công.';
    }
})();
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
