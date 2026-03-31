<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Student']);

$pageTitle = 'Lịch sử ra/vào';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="mod-container" style="display:grid;gap:24px;">
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-clock-rotate-left"></i> Lịch sử ra/vào</h2>
            <p style="margin:6px 0 0;color:var(--text-secondary);">Theo dõi các lượt quét QR cổng gần đây của bạn.</p>
        </div>
    </div>

    <div class="mod-card" style="padding:24px;">
        <div id="studentGateHistory">Đang tải lịch sử ra/vào...</div>
    </div>
</section>

<script>
(() => {
    const baseUrl = document.body.dataset.base || '/';
    const root = document.getElementById('studentGateHistory');

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function loadHistory() {
        const response = await fetch(`${baseUrl}modules/api/student_gate_history.php`, {
            cache: 'no-store',
            credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Không tải được lịch sử cổng.');
        }

        const rows = Array.isArray(payload.rows) ? payload.rows : [];
        if (!rows.length) {
            root.innerHTML = '<div class="empty-state">Chưa có lượt quét nào.</div>';
            return;
        }

        root.innerHTML = `
            <div style="overflow:auto;">
                <table class="bento-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Cổng</th>
                            <th>Hướng</th>
                            <th>Trạng thái</th>
                            <th>Lý do từ chối</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <td>${escapeHtml(row.CreatedAt)}</td>
                                <td>${escapeHtml(row.GateName)}</td>
                                <td>${escapeHtml(row.Direction)}</td>
                                <td>${escapeHtml(row.Status)}</td>
                                <td>${escapeHtml(row.RejectReason || '')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    loadHistory().catch((error) => {
        root.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
