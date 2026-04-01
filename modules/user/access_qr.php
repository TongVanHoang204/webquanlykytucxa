<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Student']);

$pageTitle = 'QR ra/vào cổng';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="mod-container" style="display:grid;gap:24px;">
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-qrcode"></i> Mã QR ra/vào cổng</h2>
            <p style="margin:6px 0 0;color:var(--text-secondary);">Mã QR có hiệu lực ngắn hạn để bảo vệ quét tại cổng ký túc xá.</p>
        </div>
    </div>

    <div class="mod-card" style="padding:24px;display:grid;justify-items:center;gap:16px;text-align:center;">
        <canvas id="studentQrCanvas" width="260" height="260" style="max-width:100%;"></canvas>
        <div id="studentQrMeta" style="color:var(--text-secondary);">Đang tạo mã QR...</div>
        <div id="studentQrToken" style="font-family:monospace;word-break:break-all;max-width:520px;"></div>
        <button id="studentQrRefreshBtn" class="mod-btn mod-btn-primary" type="button">Làm mới mã QR</button>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
<script>
(() => {
    const baseUrl = document.body.dataset.base || '/';
    const canvas = document.getElementById('studentQrCanvas');
    const metaEl = document.getElementById('studentQrMeta');
    const tokenEl = document.getElementById('studentQrToken');
    const refreshBtn = document.getElementById('studentQrRefreshBtn');
    let countdownTimer = null;

    function formatTime(seconds) {
        return `${Math.max(0, seconds)} giây`;
    }

    function startCountdown(seconds, expiresAt) {
        window.clearInterval(countdownTimer);
        let remaining = Number(seconds || 0);
        const endText = expiresAt ? ` • hết hạn lúc ${expiresAt}` : '';
        metaEl.textContent = `QR còn hiệu lực ${formatTime(remaining)}${endText}`;
        countdownTimer = window.setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                window.clearInterval(countdownTimer);
                metaEl.textContent = 'Mã QR đã hết hạn. Bấm Làm mới mã QR để tạo mã mới.';
                return;
            }
            metaEl.textContent = `QR còn hiệu lực ${formatTime(remaining)}${endText}`;
        }, 1000);
    }

    async function loadQrToken() {
        metaEl.textContent = 'Đang tạo mã QR...';
        tokenEl.textContent = '';
        const response = await fetch(`${baseUrl}modules/api/student_qr_token.php`, {
            cache: 'no-store',
            credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Không thể tạo mã QR.');
        }

        await QRCode.toCanvas(canvas, payload.token, {
            width: 260,
            margin: 2,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });

        tokenEl.textContent = `Token dự phòng: ${payload.token}`;
        startCountdown(payload.expires_in_seconds, payload.expires_at);
    }

    refreshBtn.addEventListener('click', () => {
        loadQrToken().catch((error) => {
            metaEl.textContent = error.message;
        });
    });

    loadQrToken().catch((error) => {
        metaEl.textContent = error.message;
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
