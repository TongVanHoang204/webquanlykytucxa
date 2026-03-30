<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']); // ai cũng có thể xem, sửa xóa vẫn check role ở nút

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

// Sau khi tạo $conn
$conn->set_charset('utf8mb4');
$conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

// --- Helpers ---
function h($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function isImage($path)
{
    $ext = strtolower(pathinfo($path ?? '', PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
}
function fmtVN($dt)
{
    if (!$dt) return '';
    $t = strtotime($dt);
    return date('d/m/Y', $t) . ' • ' . date('H:i', $t);
}

// --- Get ID ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$err = null;
$row = null;

if ($id <= 0) {
    $err = "Thiếu mã thông báo hợp lệ.";
} else {
    // Join linh hoạt vì cột PostedBy của bạn có lúc lưu UserID, lúc lưu Username/FullName
    $sql = "
    SELECT  a.AnnouncementID, a.Title, a.Content, a.DatePosted, a.AttachmentPath, a.PostedBy,
            u.FullName   AS AuthorName,
            u.Role       AS AuthorRole
    FROM Announcements a
    LEFT JOIN Users u
      ON (
           /* a.PostedBy có thể lưu UserID (số) => cast về CHAR theo collation phiên */
           (a.PostedBy REGEXP '^[0-9]+$' AND CAST(a.PostedBy AS UNSIGNED) = u.UserID)
        OR /* So sánh với Username bằng cùng collation */
           (a.PostedBy COLLATE utf8mb4_unicode_ci = u.Username COLLATE utf8mb4_unicode_ci)
        OR /* So sánh với FullName bằng cùng collation */
           (a.PostedBy COLLATE utf8mb4_unicode_ci = u.FullName  COLLATE utf8mb4_unicode_ci)
      )
    WHERE a.AnnouncementID = ?
    LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $err = "Không chuẩn bị được truy vấn: " . $conn->error;
    } else {
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            $err = "Không thực thi được truy vấn: " . $stmt->error;
        } else {
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $row = $res->fetch_assoc();
            } else {
                $err = "Không tìm thấy thông báo (#$id).";
            }
        }
        $stmt->close();
    }
}

// --- Tính toán view model ---
$title        = $row ? $row['Title'] : '';
$content      = $row ? $row['Content'] : '';
$datePosted   = $row ? $row['DatePosted'] : '';
$attachment   = $row['AttachmentPath'] ?? null;
$authorName   = $row['AuthorName'] ?? ($row['PostedBy'] ?? 'System');
$authorRole   = $row['AuthorRole'] ?? null;
$isNew        = $row ? (strtotime($row['DatePosted']) >= strtotime('-1 day')) : false;

// --- Quyền sửa/xóa ---
$canManage = in_array($_SESSION['Role'] ?? '', ['Admin', 'Manager']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= h($csrf) ?>">
    <title>Xem thông báo | Hệ thống Ký túc xá</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/announcements/announcement_view.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <link href="../../../assets/vendor/fonts/fonts.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="view-container">
        <div class="topbar">
            <div class="left">
                <a class="btn ghost" href="announcement_list.php"><i class="fa-solid fa-arrow-left"></i> Danh sách</a>
            </div>
            <div class="right">
                <button class="btn ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> In</button>
                <?php if ($row && $canManage): ?>
                    <a class="btn warning" href="announcement_edit.php?id=<?= (int)$row['AnnouncementID'] ?>">
                        <i class="fa-solid fa-pen-to-square"></i> Sửa
                    </a>
                    <button class="btn danger" onclick="deleteAnnouncement(<?= (int)$row['AnnouncementID'] ?>,'<?= h($title) ?>')">
                        <i class="fa-solid fa-trash"></i> Xóa
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($err): ?>
            <div class="alert error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= h($err) ?>
            </div>
        <?php else: ?>
            <article class="card">
                <header class="card-header">
                    <div class="icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <div class="meta">
                        <h1 class="title">
                            <?= h($title) ?>
                            <?php if ($isNew): ?><span class="badge">Mới</span><?php endif; ?>
                        </h1>
                        <div class="sub">
                            <span><i class="fa-solid fa-calendar"></i> <?= h(fmtVN($datePosted)) ?></span>
                            <span class="dot">•</span>
                            <span><i class="fa-solid fa-user"></i> <?= h($authorName) ?><?= $authorRole ? ' — ' . h($authorRole) : '' ?></span>
                            <?php if ($attachment): ?>
                                <span class="dot">•</span>
                                <a class="attach" href="../../../<?= h($attachment) ?>" target="_blank">
                                    <i class="fa-solid fa-paperclip"></i> Tệp đính kèm
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <div class="card-body">
                    <div class="content">
                        <?= nl2br(h($content)) ?>
                    </div>

                    <?php if ($attachment): ?>
                        <div class="attachment-block">
                            <div class="att-header">
                                <i class="fa-solid fa-paperclip"></i>
                                <span>Tệp đính kèm</span>
                                <a class="btn small" href="../../../<?= h($attachment) ?>" target="_blank">
                                    <i class="fa-solid fa-download"></i> Tải về
                                </a>
                            </div>

                            <?php if (isImage($attachment)): ?>
                                <div class="image-preview">
                                    <a href="../../../<?= h($attachment) ?>" target="_blank">
                                        <img src="../../../<?= h($attachment) ?>" alt="Attachment">
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="file-tile">
                                    <i class="fa-solid fa-file-lines"></i>
                                    <div class="file-info">
                                        <div class="name"><?= h(basename($attachment)) ?></div>
                                        <div class="hint">Nhấn để mở trong tab mới</div>
                                    </div>
                                    <a class="btn small" href="../../../<?= h($attachment) ?>" target="_blank">
                                        <i class="fa-solid fa-up-right-from-square"></i> Mở
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function deleteAnnouncement(id, title) {
            Swal.fire({
                title: 'Xóa thông báo?',
                html: `Bạn có chắc muốn xóa <b>"${title}"</b>?<br><small class="text-muted">Hành động này không thể hoàn tác.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                reverseButtons: true
            }).then(res => {
                if (!res.isConfirmed) return;
                fetch('announcement_delete_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        id,
                        _csrf: CSRF_TOKEN
                    })
                }).then(r => r.json()).then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Đã xóa',
                            timer: 800,
                            showConfirmButton: false
                        });
                        setTimeout(() => location.href = 'announcement_list.php', 800);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Không thể xóa',
                            text: data.message || 'Lỗi không xác định'
                        });
                    }
                }).catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi kết nối'
                    });
                });
            });
        }
    </script>
</body>

</html>
