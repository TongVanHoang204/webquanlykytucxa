<?php
/**
 * modules/staff/access_card/manage.php
 * Danh sách tất cả thẻ và trạng thái — Admin/Manager quản lý
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/admin_header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

// Xử lý toggle IsActive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $toggleId = (int)$_POST['toggle_id'];
    $conn->query("UPDATE accesscards SET IsActive = NOT IsActive WHERE CardID = $toggleId");
    header("Location: manage.php?msg=updated");
    exit;
}

// Xử lý xóa thẻ
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM accesscards WHERE CardID = $delId");
    header("Location: manage.php?msg=deleted");
    exit;
}

// Lấy danh sách thẻ
$cards = $conn->query("
    SELECT ac.CardID, ac.CardCode, ac.CardType, ac.IssuedDate, ac.IsActive,
           s.FullName, s.StudentCode
    FROM accesscards ac
    JOIN students s ON ac.StudentID = s.StudentID
    ORDER BY ac.IssuedDate DESC
");
?>

<div class="page-header" style="padding:2rem; background:var(--card); border-bottom:1px solid var(--stroke);">
    <h2 style="margin:0; color:var(--text);"><i class="fas fa-id-card"></i> Quản lý Thẻ Ra Vào</h2>
    <p style="margin:5px 0 0; color:var(--muted);">Xem danh sách và quản lý thẻ QR của toàn bộ sinh viên nội trú.</p>
</div>

<div class="app-content" style="padding:2rem;">
    <?php if (isset($_GET['msg'])): ?>
    <div style="padding:12px 16px; margin-bottom:16px; border-radius:8px; <?= $_GET['msg'] === 'deleted' ? 'background:#fee2e2;color:#991b1b;' : 'background:#d1fae5;color:#065f46;' ?>">
        <?= $_GET['msg'] === 'deleted' ? '<i class="fas fa-trash"></i> Đã xóa thẻ.' : '<i class="fas fa-check"></i> Cập nhật thành công!' ?>
    </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <a href="verify.php" style="padding:10px 18px; background:var(--primary,#4361ee); color:#fff; border-radius:8px; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-qrcode"></i> Quét & Xác minh thẻ
        </a>
    </div>

    <div style="background:var(--card); border-radius:10px; border:1px solid var(--stroke); overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead style="background:var(--bg); border-bottom:2px solid var(--stroke);">
                <tr>
                    <th style="padding:14px 16px;">Sinh viên</th>
                    <th style="padding:14px 16px;">Mã thẻ</th>
                    <th style="padding:14px 16px;">Loại</th>
                    <th style="padding:14px 16px;">Ngày cấp</th>
                    <th style="padding:14px 16px;">Trạng thái</th>
                    <th style="padding:14px 16px; text-align:center;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cards && $cards->num_rows > 0): while ($c = $cards->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid var(--stroke);">
                    <td style="padding:14px 16px;">
                        <strong><?= htmlspecialchars($c['FullName']) ?></strong><br>
                        <small style="color:var(--muted);"><?= htmlspecialchars($c['StudentCode']) ?></small>
                    </td>
                    <td style="padding:14px 16px; font-family:monospace; font-size:0.8rem; color:var(--muted);">
                        <?= htmlspecialchars(mb_strcut($c['CardCode'], 0, 28)) ?>...
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="padding:4px 10px; border-radius:6px; font-size:0.8rem; background:<?= $c['CardType']==='QR' ? '#eff3ff' : '#f3e8ff' ?>; color:<?= $c['CardType']==='QR' ? '#4361ee' : '#7c3aed' ?>; font-weight:600;">
                            <?= $c['CardType'] ?>
                        </span>
                    </td>
                    <td style="padding:14px 16px; font-size:0.9rem;"><?= date('d/m/Y', strtotime($c['IssuedDate'])) ?></td>
                    <td style="padding:14px 16px;">
                        <span style="padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:600; background:<?= $c['IsActive'] ? '#d1fae5' : '#fee2e2' ?>; color:<?= $c['IsActive'] ? '#065f46' : '#991b1b' ?>;">
                            <?= $c['IsActive'] ? 'Hoạt động' : 'Vô hiệu' ?>
                        </span>
                    </td>
                    <td style="padding:14px 16px; text-align:center;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="toggle_id" value="<?= $c['CardID'] ?>">
                            <button type="submit" title="<?= $c['IsActive'] ? 'Vô hiệu hoá' : 'Kích hoạt' ?>"
                                style="padding:6px 12px; background:<?= $c['IsActive'] ? '#f59e0b' : '#10b981' ?>; color:#fff; border:none; border-radius:6px; cursor:pointer; margin-right:4px;">
                                <i class="fas fa-<?= $c['IsActive'] ? 'ban' : 'check' ?>"></i>
                            </button>
                        </form>
                        <a href="manage.php?delete_id=<?= $c['CardID'] ?>"
                           onclick="return confirm('Xác nhận xóa thẻ này?')"
                           style="padding:6px 12px; background:#ef4444; color:#fff; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center;">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" style="padding:30px; text-align:center; color:var(--muted);">Chưa có thẻ nào trong hệ thống.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
