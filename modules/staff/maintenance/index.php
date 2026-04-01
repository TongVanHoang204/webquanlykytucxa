<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/admin_header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

// Lấy danh sách yêu cầu
$query = "
    SELECT m.RequestID, m.Title, m.Description, m.ImagePath, m.Status, m.Priority, m.CreatedAt, m.UpdatedAt,
           s.FullName, s.StudentCode, r.RoomNumber, b.BuildingName
    FROM maintenancerequests m
    JOIN students s ON m.StudentID = s.StudentID
    JOIN rooms r ON m.RoomID = r.RoomID
    JOIN buildings b ON r.BuildingID = b.BuildingID
    ORDER BY FIELD(m.Status, 'Chờ xử lý', 'Đang xử lý', 'Đã hoàn thành', 'Đã hủy'), m.CreatedAt DESC
";
$requests = $conn->query($query);
?>
<div class="page-header" style="padding: 2rem; background: var(--card); border-bottom: 1px solid var(--stroke);">
    <h2 style="margin: 0; color: var(--text);"><i class="fas fa-tools"></i> Quản lý Bảo trì & Sửa chữa</h2>
    <p style="margin: 5px 0 0; color: var(--muted);">Theo dõi và xử lý các sự cố cơ sở vật chất mà sinh viên gửi về.</p>
</div>

<div class="app-content" style="padding: 2rem;">
    <div class="card" style="background: var(--card); border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow:hidden; border:1px solid var(--stroke);">
        
        <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 1rem; margin:1rem; border-radius: 6px; <?= $_GET['msg'] === 'success' ? 'background:#d1fae5; color:#065f46;' : 'background:#fee2e2; color:#b91c1c;' ?>">
                <?= $_GET['msg'] === 'success' ? 'Cập nhật trạng thái thành công!' : 'Đã xảy ra lỗi, vui lòng thử lại.' ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive" style="padding:1rem;">
            <table class="table table-hover" id="maintenanceTable" style="width:100%; border-collapse: collapse; text-align:left;">
                <thead style="background:var(--bg); border-bottom:2px solid var(--stroke);">
                    <tr>
                        <th style="padding:15px 10px;">ID</th>
                        <th style="padding:15px 10px;">Sự cố</th>
                        <th style="padding:15px 10px;">Phòng</th>
                        <th style="padding:15px 10px;">Sinh viên báo</th>
                        <th style="padding:15px 10px;">Mức độ</th>
                        <th style="padding:15px 10px;">Trạng thái</th>
                        <th style="padding:15px 10px;">Ngày tạo</th>
                        <th style="padding:15px 10px; text-align:center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests && $requests->num_rows > 0): ?>
                        <?php while ($req = $requests->fetch_assoc()): 
                            $statusColors = [
                                'Chờ xử lý' => '#f59e0b',
                                'Đang xử lý' => '#3b82f6',
                                'Đã hoàn thành' => '#10b981',
                                'Đã hủy' => '#6b7280'
                            ];
                            $priColors = [
                                'Khẩn cấp' => '#dc2626',
                                'Cao' => '#d97706',
                                'Bình thường' => '#0284c7',
                                'Thấp' => '#4b5563'
                            ];
                        ?>
                            <tr style="border-bottom: 1px solid var(--stroke);">
                                <td style="padding:15px 10px;">#<?= $req['RequestID'] ?></td>
                                <td style="padding:15px 10px; max-width:250px;">
                                    <strong><?= htmlspecialchars($req['Title']) ?></strong>
                                    <?php if(!empty($req['ImagePath'])): ?>
                                        <i class="fas fa-image" style="color:#3b82f6; cursor:pointer; margin-left:5px;" onclick="viewImage('<?= $base . htmlspecialchars($req['ImagePath']) ?>')"></i>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:15px 10px;">
                                    <strong><?= htmlspecialchars($req['RoomNumber']) ?></strong><br>
                                    <small><?= htmlspecialchars($req['BuildingName']) ?></small>
                                </td>
                                <td style="padding:15px 10px;">
                                    <?= htmlspecialchars($req['FullName']) ?><br>
                                    <small><?= htmlspecialchars($req['StudentCode']) ?></small>
                                </td>
                                <td style="padding:15px 10px;">
                                    <span style="font-weight:600; color:<?= $priColors[$req['Priority']] ?? '#000' ?>"><?= $req['Priority'] ?></span>
                                </td>
                                <td style="padding:15px 10px;">
                                    <span style="padding:4px 8px; border-radius:12px; font-size:12px; font-weight:bold; background: <?= $statusColors[$req['Status']] ?? '#ccc' ?>; color:#fff;">
                                        <?= $req['Status'] ?>
                                    </span>
                                </td>
                                <td style="padding:15px 10px;">
                                    <?= date('d/m/Y H:i', strtotime($req['CreatedAt'])) ?>
                                </td>
                                <td style="padding:15px 10px; text-align:center;">
                                    <button class="btn btn-sm" style="background:var(--primary); color:#fff; border:none; padding:8px 12px; border-radius:4px; cursor:pointer;" onclick="openUpdateModal(<?= $req['RequestID'] ?>, '<?= $req['Status'] ?>')">
                                        Cập nhật
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:20px;">Chưa có yêu cầu sự cố nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal cập nhật trạng thái -->
<div id="updateModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div style="background:var(--card); width:400px; margin: 10% auto; padding: 20px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0;">Cập nhật trạng thái</h3>
        <form action="update_status.php" method="POST">
            <input type="hidden" id="reqId" name="reqId">
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px;">Trạng thái mới</label>
                <select id="reqStatus" name="reqStatus" style="width:100%; padding:10px; border-radius:4px; border:1px solid var(--stroke); background:var(--bg); color:var(--text);">
                    <option value="Chờ xử lý">Chờ xử lý</option>
                    <option value="Đang xử lý">Đang xử lý</option>
                    <option value="Đã hoàn thành">Đã hoàn thành</option>
                    <option value="Đã hủy">Đã hủy</option>
                </select>
            </div>
            <div style="text-align:right;">
                <button type="button" onclick="closeUpdateModal()" style="padding:10px 15px; background:var(--bg); color:var(--text); border:1px solid var(--stroke); border-radius:4px; cursor:pointer; margin-right:10px;">Hủy</button>
                <button type="submit" style="padding:10px 15px; background:var(--primary); color:#fff; border:none; border-radius:4px; cursor:pointer;">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUpdateModal(id, currentStatus) {
    document.getElementById('reqId').value = id;
    document.getElementById('reqStatus').value = currentStatus;
    document.getElementById('updateModal').style.display = 'block';
}
function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
}
function viewImage(src) {
    Swal.fire({
        imageUrl: src,
        imageAlt: 'Hình ảnh sự cố',
        width: 600,
        showConfirmButton: false
    });
}
window.onclick = function(event) {
    let mod = document.getElementById('updateModal');
    if (event.target == mod) {
        mod.style.display = "none";
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
