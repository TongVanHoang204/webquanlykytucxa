<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$pageTitle = 'Mass Email';
require_once __DIR__ . '/../../../includes/admin_header.php';
$csrf = csrfToken();
?>
<section class="mod-container" style="display:grid;gap:24px;">
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-envelope-open-text"></i> Gửi email hàng loạt</h2>
            <p style="margin:6px 0 0;color:var(--text-secondary);">Gửi ngay từ giao diện quản trị tới sinh viên hoặc staff/admin.</p>
        </div>
    </div>

    <div class="mod-card" style="display:grid;gap:16px;padding:24px;">
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
            <label style="display:grid;gap:8px;">
                <span>Nhóm người nhận</span>
                <select id="massEmailAudience" class="form-control">
                    <option value="students">Sinh viên</option>
                    <option value="staff_admin">Staff/Admin</option>
                </select>
            </label>
            <label style="display:grid;gap:8px;">
                <span>Tiêu đề</span>
                <input id="massEmailSubject" class="form-control" type="text" maxlength="255" placeholder="Thông báo quan trọng từ ký túc xá">
            </label>
        </div>

        <label style="display:grid;gap:8px;">
            <span>Nội dung HTML / văn bản</span>
            <textarea id="massEmailBody" class="form-control" rows="10" placeholder="<p>Nội dung email...</p>"></textarea>
        </label>

        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <button id="massEmailSendBtn" class="mod-btn mod-btn-primary" type="button">
                <i class="fas fa-paper-plane"></i> Gửi ngay
            </button>
            <span id="massEmailStatus" style="color:var(--text-secondary);">Sẵn sàng gửi.</span>
        </div>
    </div>

    <div class="mod-card" style="padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <h3 style="margin:0;"><i class="fas fa-clock-rotate-left"></i> Lịch sử chiến dịch</h3>
            <button id="massEmailReloadBtn" class="mod-btn mod-btn-outline" type="button">Tải lại</button>
        </div>
        <div id="massEmailCampaigns">Đang tải danh sách chiến dịch...</div>
    </div>

    <div class="mod-card" style="padding:24px;">
        <h3 style="margin-top:0;"><i class="fas fa-list-check"></i> Chi tiết chiến dịch</h3>
        <div id="massEmailDetail">Chọn một chiến dịch để xem danh sách người nhận.</div>
    </div>
</section>

<script>
(() => {
    const baseUrl = document.body.dataset.base || '/';
    const csrfToken = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const audienceEl = document.getElementById('massEmailAudience');
    const subjectEl = document.getElementById('massEmailSubject');
    const bodyEl = document.getElementById('massEmailBody');
    const sendBtn = document.getElementById('massEmailSendBtn');
    const reloadBtn = document.getElementById('massEmailReloadBtn');
    const statusEl = document.getElementById('massEmailStatus');
    const campaignsEl = document.getElementById('massEmailCampaigns');
    const detailEl = document.getElementById('massEmailDetail');

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value || '';
        }
        return date.toLocaleString('vi-VN');
    }

    async function fetchCampaigns() {
        campaignsEl.textContent = 'Đang tải danh sách chiến dịch...';
        const response = await fetch(`${baseUrl}modules/api/admin_email_campaigns.php`, {
            cache: 'no-store',
            credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Không tải được lịch sử chiến dịch.');
        }

        const rows = Array.isArray(payload.rows) ? payload.rows : [];
        if (!rows.length) {
            campaignsEl.innerHTML = '<div class="empty-state">Chưa có chiến dịch email nào.</div>';
            return;
        }

        campaignsEl.innerHTML = `
            <div style="overflow:auto;">
                <table class="bento-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tiêu đề</th>
                            <th>Nhóm nhận</th>
                            <th>Trạng thái</th>
                            <th>Kết quả</th>
                            <th>Thời gian</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <td>#${escapeHtml(row.CampaignID)}</td>
                                <td>${escapeHtml(row.Subject)}</td>
                                <td>${escapeHtml(row.AudienceType)}</td>
                                <td>${escapeHtml(row.Status)}</td>
                                <td>${escapeHtml(row.SuccessCount)}/${escapeHtml(row.TotalRecipients)}</td>
                                <td>${escapeHtml(formatDate(row.SentAt || row.CreatedAt))}</td>
                                <td><button class="mod-btn mod-btn-outline mass-email-detail-btn" type="button" data-id="${escapeHtml(row.CampaignID)}">Xem chi tiết</button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        campaignsEl.querySelectorAll('.mass-email-detail-btn').forEach((button) => {
            button.addEventListener('click', () => {
                loadDetail(button.dataset.id).catch((error) => {
                    detailEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
                });
            });
        });
    }

    async function loadDetail(campaignId) {
        detailEl.textContent = 'Đang tải chi tiết chiến dịch...';
        const response = await fetch(`${baseUrl}modules/api/admin_email_campaign_detail.php?campaign_id=${encodeURIComponent(campaignId)}`, {
            cache: 'no-store',
            credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Không tải được chi tiết chiến dịch.');
        }

        const campaign = payload.campaign || {};
        const recipients = Array.isArray(payload.recipients) ? payload.recipients : [];

        detailEl.innerHTML = `
            <div style="display:grid;gap:12px;">
                <div>
                    <strong>${escapeHtml(campaign.Subject)}</strong>
                    <div style="color:var(--text-secondary);margin-top:6px;">${escapeHtml(campaign.AudienceType)} • ${escapeHtml(campaign.Status)} • ${escapeHtml(formatDate(campaign.SentAt || campaign.CreatedAt))}</div>
                </div>
                <div style="max-height:360px;overflow:auto;">
                    <table class="bento-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Người nhận</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th>Lỗi</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${recipients.map((row) => `
                                <tr>
                                    <td>${escapeHtml(row.FullName || ('User #' + row.UserID))}</td>
                                    <td>${escapeHtml(row.Email)}</td>
                                    <td>${escapeHtml(row.Role || '')}</td>
                                    <td>${escapeHtml(row.SendStatus)}</td>
                                    <td>${escapeHtml(row.ErrorMessage || '')}</td>
                                </tr>
                            `).join('') || '<tr><td colspan="5">Chưa có dữ liệu người nhận.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    async function sendMassEmail() {
        const payload = {
            audience_type: audienceEl.value,
            subject: subjectEl.value.trim(),
            body_html: bodyEl.value.trim(),
        };

        if (!payload.subject || !payload.body_html) {
            throw new Error('Cần nhập đầy đủ tiêu đề và nội dung email.');
        }

        statusEl.textContent = 'Đang gửi chiến dịch email...';
        sendBtn.disabled = true;

        const response = await fetch(`${baseUrl}modules/api/admin_send_mass_email.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Gửi mass email thất bại.');
        }

        const result = data.result || {};
        statusEl.textContent = `Đã gửi xong chiến dịch #${result.CampaignID}: ${result.SuccessCount}/${result.TotalRecipients} thành công.`;
        await fetchCampaigns();
        if (result.CampaignID) {
            await loadDetail(result.CampaignID);
        }
    }

    sendBtn.addEventListener('click', () => {
        sendMassEmail().catch((error) => {
            statusEl.textContent = error.message;
        }).finally(() => {
            sendBtn.disabled = false;
        });
    });

    reloadBtn.addEventListener('click', () => {
        fetchCampaigns().catch((error) => {
            campaignsEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
        });
    });

    fetchCampaigns().catch((error) => {
        campaignsEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
    });
})();
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
