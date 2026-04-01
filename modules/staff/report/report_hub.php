<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$pageTitle = 'Trung tâm báo cáo';
require_once __DIR__ . '/../../../includes/admin_header.php';
?>
<section class="mod-container" style="display:grid;gap:24px;">
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-file-export"></i> Trung tâm báo cáo</h2>
            <p style="margin:6px 0 0;color:var(--text-secondary);">Preview và export chung cho tài chính, sinh viên, hợp đồng và phòng ở.</p>
        </div>
    </div>

    <div class="mod-card" style="display:grid;gap:16px;padding:24px;">
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
            <label style="display:grid;gap:8px;">
                <span>Báo cáo</span>
                <select id="reportKey" class="form-control">
                    <option value="finance_summary">Tổng hợp tài chính</option>
                    <option value="finance_detail">Chi tiết hóa đơn</option>
                    <option value="students_list">Danh sách sinh viên</option>
                    <option value="contracts_list">Danh sách hợp đồng</option>
                    <option value="rooms_occupancy">Mức lấp đầy phòng ở</option>
                </select>
            </label>
            <label style="display:grid;gap:8px;">
                <span>Từ ngày</span>
                <input id="reportFrom" class="form-control" type="date">
            </label>
            <label style="display:grid;gap:8px;">
                <span>Đến ngày</span>
                <input id="reportTo" class="form-control" type="date">
            </label>
            <label style="display:grid;gap:8px;">
                <span>Trạng thái</span>
                <input id="reportStatus" class="form-control" type="text" placeholder="Ví dụ: Đã thanh toán">
            </label>
            <label style="display:grid;gap:8px;">
                <span>Từ khóa</span>
                <input id="reportSearch" class="form-control" type="text" placeholder="Tên, mã SV, email...">
            </label>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <button id="reportPreviewBtn" class="mod-btn mod-btn-primary" type="button">Xem trước</button>
            <a id="reportExportXlsxBtn" class="mod-btn mod-btn-outline" href="#">Xuất Excel</a>
            <a id="reportExportPdfBtn" class="mod-btn mod-btn-outline" href="#">Xuất PDF</a>
        </div>
    </div>

    <div class="mod-card" style="padding:24px;">
        <div id="reportMeta" style="margin-bottom:16px;color:var(--text-secondary);">Chọn bộ lọc và bấm Xem trước để tải dữ liệu.</div>
        <div id="reportPreview">Chưa có dữ liệu báo cáo.</div>
    </div>
</section>

<script>
(() => {
    const baseUrl = document.body.dataset.base || '/';
    const reportKeyEl = document.getElementById('reportKey');
    const fromEl = document.getElementById('reportFrom');
    const toEl = document.getElementById('reportTo');
    const statusEl = document.getElementById('reportStatus');
    const searchEl = document.getElementById('reportSearch');
    const previewBtn = document.getElementById('reportPreviewBtn');
    const exportXlsxBtn = document.getElementById('reportExportXlsxBtn');
    const exportPdfBtn = document.getElementById('reportExportPdfBtn');
    const previewEl = document.getElementById('reportPreview');
    const metaEl = document.getElementById('reportMeta');

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildQueryString() {
        const params = new URLSearchParams();
        params.set('report_key', reportKeyEl.value);
        if (fromEl.value) params.set('from', fromEl.value);
        if (toEl.value) params.set('to', toEl.value);
        if (statusEl.value.trim()) params.set('status', statusEl.value.trim());
        if (searchEl.value.trim()) params.set('search', searchEl.value.trim());
        return params.toString();
    }

    function updateExportLinks() {
        const query = buildQueryString();
        exportXlsxBtn.href = `${baseUrl}modules/api/report_export.php?${query}&format=xlsx`;
        exportPdfBtn.href = `${baseUrl}modules/api/report_export.php?${query}&format=pdf`;
    }

    function renderPreview(dataset) {
        const columns = Array.isArray(dataset?.columns) ? dataset.columns : [];
        const rows = Array.isArray(dataset?.rows) ? dataset.rows : [];
        const meta = dataset?.meta || {};
        const summary = meta.summary || {};
        const summaryText = Object.entries(summary)
            .map(([key, value]) => `${key}: ${value}`)
            .join(' • ');

        metaEl.textContent = summaryText || 'Không có số liệu tóm tắt.';

        if (!columns.length) {
            previewEl.textContent = 'Dataset không có cột dữ liệu.';
            return;
        }

        if (!rows.length) {
            previewEl.innerHTML = '<div class="empty-state">Báo cáo hiện không có bản ghi phù hợp.</div>';
            return;
        }

        previewEl.innerHTML = `
            <div style="overflow:auto;">
                <table class="bento-table" style="width:100%;">
                    <thead>
                        <tr>${columns.map((column) => `<th>${escapeHtml(column.label || column.key || '')}</th>`).join('')}</tr>
                    </thead>
                    <tbody>
                        ${rows.slice(0, 100).map((row) => `
                            <tr>
                                ${columns.map((column) => {
                                    const key = column.key || '';
                                    const value = row?.[key] ?? '';
                                    return `<td>${escapeHtml(value)}</td>`;
                                }).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function loadReportPreview() {
        previewEl.textContent = 'Đang tải dữ liệu báo cáo...';
        metaEl.textContent = 'Đang xử lý bộ lọc...';
        updateExportLinks();

        const response = await fetch(`${baseUrl}modules/api/report_data.php?${buildQueryString()}`, {
            cache: 'no-store',
            credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Không tải được dữ liệu báo cáo.');
        }

        renderPreview(payload.dataset);
    }

    previewBtn.addEventListener('click', () => {
        loadReportPreview().catch((error) => {
            metaEl.textContent = error.message;
            previewEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
        });
    });

    [reportKeyEl, fromEl, toEl, statusEl, searchEl].forEach((element) => {
        element.addEventListener('change', updateExportLinks);
        element.addEventListener('input', updateExportLinks);
    });

    updateExportLinks();
    loadReportPreview().catch((error) => {
        metaEl.textContent = error.message;
        previewEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
    });
})();
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
