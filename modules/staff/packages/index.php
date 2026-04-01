<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/admin_header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

// -------------------- LẤY DANH SÁCH BƯU PHẨM --------------------
$sql = "
    SELECT p.PackageID, p.StudentID, p.SenderInfo, p.PackageNotes, p.Status, p.ReceivedAt, p.DeliveredAt,
           s.FullName, s.StudentCode, r.RoomNumber
    FROM packages p
    JOIN students s ON p.StudentID = s.StudentID
    LEFT JOIN contracts c ON s.StudentID = c.StudentID AND c.Status = 'Hiệu lực'
    LEFT JOIN rooms r ON c.RoomID = r.RoomID
    ORDER BY FIELD(p.Status, 'Chờ lấy', 'Đã nhận', 'Đã hoàn trả'), p.ReceivedAt DESC
";
$packages = $conn->query($sql);

// Lấy danh sách SV cho form
$students = $conn->query("
    SELECT s.StudentID, s.FullName, s.StudentCode, r.RoomNumber 
    FROM students s
    LEFT JOIN contracts c ON s.StudentID = c.StudentID AND c.Status = 'Hiệu lực'
    LEFT JOIN rooms r ON c.RoomID = r.RoomID
    ORDER BY s.FullName ASC
");
?>
<div class="page-header" style="padding: 2rem; background: var(--card); border-bottom: 1px solid var(--stroke);">
    <h2 style="margin: 0; color: var(--text);"><i class="fas fa-box"></i> Quản lý Bưu phẩm KTX</h2>
    <p style="margin: 5px 0 0; color: var(--muted);">Lưu trữ và bàn giao gói hàng online cho sinh viên nội trú.</p>
</div>

<div class="app-content" style="padding: 2rem;">
    <!-- Nút thêm bưu phẩm -->
    <div style="margin-bottom: 15px; display:flex; justify-content:space-between; align-items:center;">
        <button class="btn" style="background:var(--primary); color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Nhận bưu phẩm mới
        </button>
    </div>

    <div class="card" style="background: var(--card); border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow:hidden; border:1px solid var(--stroke);">
        
        <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 1rem; margin:1rem; border-radius: 6px; <?= $_GET['msg'] === 'success' ? 'background:#d1fae5; color:#065f46;' : 'background:#fee2e2; color:#b91c1c;' ?>">
                <?= $_GET['msg'] === 'success' ? 'Thao tác thành công!' : 'Đã xảy ra lỗi, vui lòng thử lại.' ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive" style="padding:1rem;">
            <table class="table table-hover" id="pkgTable" style="width:100%; border-collapse: collapse; text-align:left;">
                <thead style="background:var(--bg); border-bottom:2px solid var(--stroke);">
                    <tr>
                        <th style="padding:15px 10px;">ID</th>
                        <th style="padding:15px 10px;">Chủ nhân (Sinh viên)</th>
                        <th style="padding:15px 10px;">Thông tin gói hàng</th>
                        <th style="padding:15px 10px;">Thời gian lưu</th>
                        <th style="padding:15px 10px;">Trạng thái</th>
                        <th style="padding:15px 10px; text-align:center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($packages && $packages->num_rows > 0): ?>
                        <?php while ($pkg = $packages->fetch_assoc()): 
                            $statusColors = [
                                'Chờ lấy' => '#f59e0b',
                                'Đã nhận' => '#10b981',
                                'Đã hoàn trả' => '#6b7280'
                            ];
                        ?>
                            <tr style="border-bottom: 1px solid var(--stroke);">
                                <td style="padding:15px 10px;">#<?= $pkg['PackageID'] ?></td>
                                <td style="padding:15px 10px;">
                                    <strong><?= htmlspecialchars($pkg['FullName']) ?></strong>
                                    <div style="font-size:0.85rem; color:var(--muted);">Mã: <?= htmlspecialchars($pkg['StudentCode']) ?> - Phòng: <?= htmlspecialchars($pkg['RoomNumber'] ?? 'N/A') ?></div>
                                </td>
                                <td style="padding:15px 10px;">
                                    <strong><i class="fas fa-truck" style="color:var(--primary);"></i> <?= htmlspecialchars($pkg['SenderInfo']) ?></strong>
                                    <div style="font-size:0.85rem; color:var(--muted);"><?= htmlspecialchars($pkg['PackageNotes']) ?></div>
                                </td>
                                <td style="padding:15px 10px; font-size:0.9rem;">
                                    Nhận: <?= date('H:i d/m', strtotime($pkg['ReceivedAt'])) ?>
                                    <?php if ($pkg['DeliveredAt']): ?>
                                    <br><span style="color:#10b981;">Giao: <?= date('H:i d/m', strtotime($pkg['DeliveredAt'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:15px 10px;">
                                    <span style="padding:4px 8px; border-radius:12px; font-size:12px; font-weight:bold; background: <?= $statusColors[$pkg['Status']] ?? '#ccc' ?>; color:#fff;">
                                        <?= $pkg['Status'] ?>
                                    </span>
                                </td>
                                <td style="padding:15px 10px; text-align:center;">
                                    <?php if ($pkg['Status'] === 'Chờ lấy'): ?>
                                    <button class="btn btn-sm" style="background:#10b981; color:#fff; border:none; padding:8px; border-radius:4px; cursor:pointer;" onclick="changeStatus(<?= $pkg['PackageID'] ?>, 'Đã nhận')" title="Xác nhận sinh viên đã lấy">
                                        <i class="fas fa-check"></i> Đã Giao
                                    </button>
                                    <button class="btn btn-sm" style="background:#6b7280; color:#fff; border:none; padding:8px; border-radius:4px; cursor:pointer;" onclick="changeStatus(<?= $pkg['PackageID'] ?>, 'Đã hoàn trả')" title="Hoàn trả shipper">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px;">Kho trống, không có bưu phẩm nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm Bưu Phẩm -->
<div id="createModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div style="background:var(--card); width:500px; margin: 5% auto; padding: 20px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0;"><i class="fas fa-box"></i> Nhận Bưu Phẩm Mới</h3>
        <form action="create_action.php" method="POST">
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Chủ nhân (Sinh viên nhận)</label>
                <select name="studentId" required style="width:100%; padding:10px; border-radius:4px; border:1px solid var(--stroke); background:var(--bg); color:var(--text);">
                    <option value="">-- Chọn sinh viên --</option>
                    <?php if ($students): while ($s = $students->fetch_assoc()): ?>
                        <option value="<?= $s['StudentID'] ?>"><?= $s['StudentCode'] ?> - <?= htmlspecialchars($s['FullName']) ?> (P.<?= htmlspecialchars($s['RoomNumber'] ?? '?') ?>)</option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Đơn vị giao / Người gửi</label>
                <input type="text" name="senderInfo" required placeholder="VD: Shopee / Viettel Post..." style="width:100%; padding:10px; border-radius:4px; border:1px solid var(--stroke); background:var(--bg); color:var(--text);">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Mô tả bưu kiện</label>
                <input type="text" name="packageNotes" placeholder="VD: Hộp chữ nhật loại nhỏ..." style="width:100%; padding:10px; border-radius:4px; border:1px solid var(--stroke); background:var(--bg); color:var(--text);">
            </div>
            
            <div style="text-align:right;">
                <button type="button" onclick="closeCreateModal()" style="padding:10px 15px; background:var(--bg); color:var(--text); border:1px solid var(--stroke); border-radius:4px; cursor:pointer; margin-right:10px;">Hủy</button>
                <button type="submit" style="padding:10px 15px; background:var(--primary); color:#fff; border:none; border-radius:4px; cursor:pointer;">Lưu bưu kiện</button>
            </div>
        </form>
    </div>
</div>

<!-- Tự động sinh form để Cập nhật Status -->
<form id="statusForm" action="update_status.php" method="POST" style="display:none;">
    <input type="hidden" name="packageId" id="p_id">
    <input type="hidden" name="newStatus" id="p_status">
</form>

<script>
function openCreateModal() { document.getElementById('createModal').style.display = 'block'; }
function closeCreateModal() { document.getElementById('createModal').style.display = 'none'; }
window.onclick = function(event) {
    if (event.target == document.getElementById('createModal')) closeCreateModal();
}

function changeStatus(id, newStatus) {
    // Nếu là Đã Hủy, hỏi nhẹ. Nếu là Đã Giao, đổi ngay.
    if(confirm(`Đánh dấu bưu phẩm #${id} thành lập '${newStatus}'?`)) {
        document.getElementById('p_id').value = id;
        document.getElementById('p_status').value = newStatus;
        document.getElementById('statusForm').submit();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
