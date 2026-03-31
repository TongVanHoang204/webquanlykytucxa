<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$pageTitle = 'Nhật ký ra/vào';
require_once __DIR__ . '/../../../includes/admin_header.php';
?>
<section class="mod-container" style="display:grid;gap:24px;">
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-list"></i> Nhật ký ra/vào cổng</h2>
        </div>
    </div>

    <div class="mod-card" style="padding:24px;display:grid;gap:16px;">
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
            <input id="gateLogSearch" class="form-control" type="text" placeholder="Tên sinh viên / mã sinh viên">
            <input id="gateLogGateName" class="form-control" type="text" placeholder="Tên cổng">
            <select id="gateLogStatus" class="form-control">
                <option value="">Tất cả trạng thái</option>
                <option value="accepted">accepted</option>
                <option value="rejected">rejected</option>
            </select>
            <button id="gateLogReloadBtn" class="mod-btn mod-btn-primary" type="button">Tải dữ liệu</button>
        </div>

        <div id="gateLogsTable">Đang tải nhật ký cổng...</div>
    </div>
</section>

<script>
(() => {
    const baseUrl = document.body.dataset.base || '/';
    const root = document.getElementById('gateLogsTable');
    const searchEl = document.getElementById('gateLogSearch');
    const gateNameEl = document.getElementById('gateLogGateName');
    const statusEl = document.getElementById('gateLogStatus');
    const reloadBtn = document.getElementById('gateLogReloadBtn');

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function loadLogs() {
        root.textContent = 'Đang tải nhật ký cổng...';
        const params = new URLSearchParams();
        params.set('limit', '100');
        if (searchEl.value.trim()) params.set('student_search', searchEl.value.trim());
        if (gateNameEl.value.trim()) params.set('gate_name', gateNameEl.value.trim());
        if (statusEl.value) params.set('status', statusEl.value);

        const response = await fetch(`${baseUrl}modules/api/gate_access_logs.php?${params.toString()}`, {
            cache: 'no-store',
            credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Không tải được log cổng.');
        }

        const rows = Array.isArray(payload.rows) ? payload.rows : [];
        if (!rows.length) {
            root.innerHTML = '<div class="empty-state">Không có dữ liệu log phù hợp.</div>';
            return;
        }

        root.innerHTML = `
            <div style="overflow:auto;">
                <table class="bento-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Sinh viên</th>
                            <th>Cổng</th>
                            <th>Hướng</th>
                            <th>Trạng thái</th>
                            <th>Lý do từ chối</th>
                            <th>Người quét</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <td>${escapeHtml(row.CreatedAt)}</td>
                                <td>${escapeHtml(row.FullName)}<br><span style="color:var(--text-secondary);">${escapeHtml(row.StudentCode)}</span></td>
                                <td>${escapeHtml(row.GateName)}</td>
                                <td>${escapeHtml(row.Direction)}</td>
                                <td>${escapeHtml(row.Status)}</td>
                                <td>${escapeHtml(row.RejectReason || '')}</td>
                                <td>${escapeHtml(row.ScannedByName || '')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    reloadBtn.addEventListener('click', () => {
        loadLogs().catch((error) => {
            root.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
        });
    });

    loadLogs().catch((error) => {
        root.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
    });
})();
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
