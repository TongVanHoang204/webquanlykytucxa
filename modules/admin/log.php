<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Admin', 'Manager']); // chỉ Admin/Manager xem log
require_once __DIR__ . '/../../includes/admin_header.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// ====== LỌC & PHÂN TRANG ======
$logType  = $_GET['log_type']  ?? '';        // activity | history | system | ''
$module   = $_GET['module']    ?? '';        // Rooms, Students,...
$q        = trim($_GET['q']    ?? '');       // từ khóa
$dateFrom = $_GET['date_from'] ?? '';        // 2025-01-01
$dateTo   = $_GET['date_to']   ?? '';        // 2025-01-31
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = "";

// Lọc theo loại log
if ($logType !== '') {
    $where[]  = "l.LogType = ?";
    $params[] = $logType;
    $types   .= "s";
}

// Lọc theo module
if ($module !== '') {
    $where[]  = "l.Module = ?";
    $params[] = $module;
    $types   .= "s";
}

// Lọc theo từ khóa (tìm trong Action + Description)
if ($q !== '') {
    $where[]  = "(l.Action LIKE CONCAT('%', ?, '%') OR l.Description LIKE CONCAT('%', ?, '%'))";
    $params[] = $q;
    $params[] = $q;
    $types   .= "ss";
}

// Lọc theo khoảng thời gian
if ($dateFrom !== '') {
    $where[]  = "DATE(l.CreatedAt) >= ?";
    $params[] = $dateFrom;
    $types   .= "s";
}
if ($dateTo !== '') {
    $where[]  = "DATE(l.CreatedAt) <= ?";
    $params[] = $dateTo;
    $types   .= "s";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ====== TÍNH TỔNG DÒNG ======
$countSql = "SELECT COUNT(*) AS Total FROM SystemLogs l $whereSql";
$stmt = $conn->prepare($countSql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['Total'];
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

// ====== LẤY DANH SÁCH LOG ======
$listSql = "
    SELECT 
        l.LogID,
        l.UserID,
        l.Action,
        l.Module,
        l.Description,
        l.LogType,
        l.IPAddress,
        l.UserAgent,
        l.CreatedAt,
        u.Username,
        u.FullName
    FROM SystemLogs l
    LEFT JOIN Users u ON l.UserID = u.UserID
    $whereSql
    ORDER BY l.LogID DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($listSql);

// thêm LIMIT/OFFSET
$paramsWithLimit = $params;
$typesWithLimit  = $types . "ii";
$paramsWithLimit[] = $perPage;
$paramsWithLimit[] = $offset;

$stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Lấy danh sách module có trong hệ thống (để đổ dropdown)
$modules = [];
$modRes = $conn->query("SELECT DISTINCT Module FROM SystemLogs ORDER BY Module ASC");
while ($row = $modRes->fetch_assoc()) {
    if (!empty($row['Module'])) {
        $modules[] = $row['Module'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Nhật ký hệ thống</title>
    <link rel="stylesheet" href="/assets/css/admin/admin_dashboard.css">
    <link rel="stylesheet" href="/assets/css/admin/admin_users.css">
    <link rel="stylesheet" href="/assets/css/admin/log.css">

</head>

<body>
    <div class="log-page">
        <div class="log-header">
            <h1>📜 Nhật ký / Log hệ thống</h1>
            <div>
                <span class="log-summary-chip">
                    Tổng: <strong><?= $total ?></strong> bản ghi
                </span>
            </div>
        </div>


        <!-- Tabs loại log -->
        <div class="log-tabs">
            <?php
            // helper tạo link giữ nguyên các param khác
            function logTabUrl(string $typeVal = ''): string
            {
                $params = $_GET;
                if ($typeVal === '') unset($params['log_type']);
                else $params['log_type'] = $typeVal;
                return '?' . http_build_query($params);
            }
            ?>
            <a href="<?= logTabUrl('') ?>" class="log-tab <?= $logType === '' ? 'active' : '' ?>">
                🔎 Tất cả
            </a>
            <a href="<?= logTabUrl('activity') ?>" class="log-tab <?= $logType === 'activity' ? 'active' : '' ?>">
                ✅ Activity / Theo dõi
            </a>
            <a href="<?= logTabUrl('history') ?>" class="log-tab <?= $logType === 'history' ? 'active' : '' ?>">
                ⏱️ Lịch sử
            </a>
            <a href="<?= logTabUrl('system') ?>" class="log-tab <?= $logType === 'system' ? 'active' : '' ?>">
                ⚙️ Logs hệ thống
            </a>
        </div>
        <form method="get" class="log-filters">
            <!-- Giữ lại log_type khi submit bộ lọc -->
            <input type="hidden" name="log_type" value="<?= htmlspecialchars($logType) ?>">

            <div class="field">
                <label>Module</label>
                <select name="module" onchange="this.form.submit()">
                    <option value="">Tất cả module</option>
                    <?php foreach ($modules as $mod): ?>
                        <option value="<?= htmlspecialchars($mod) ?>" <?= $module === $mod ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mod) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Từ khóa</label>
                <input type="text" name="q" placeholder="Action, mô tả..."
                    value="<?= htmlspecialchars($q) ?>">
            </div>

            <div class="field">
                <label>Từ ngày</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>

            <div class="field">
                <label>Đến ngày</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>

            <button type="submit" class="btn-reset">
                Áp dụng
            </button>
            <a href="log.php" class="btn-reset" style="border-style:dashed;">
                Đặt lại
            </a>
        </form>


        <!-- Bảng log -->
        <table class="log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Loại</th>
                    <th>Hành động / Module</th>
                    <th>Mô tả</th>
                    <th>Người thực hiện</th>
                    <th>IP / Thiết bị</th>
                    <th>Thời gian</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        $typeClass = $row['LogType'] ?? 'activity';
                        $displayName = $row['FullName'] ?: $row['Username'];
                        if (!$displayName) $displayName = 'Khách / Hệ thống';

                        // Chuẩn hóa IP hiển thị
                        $rawIp = $row['IPAddress'] ?? '';
                        if ($rawIp === '::1') {
                            $ipDisplay = '127.0.0.1 (localhost)';
                        } elseif (empty($rawIp)) {
                            $ipDisplay = '-';
                        } else {
                            $ipDisplay = $rawIp;
                        }

                        // User Agent
                        $ua = $row['UserAgent'] ?? '';

                        // CSRF token
                        if (empty($_SESSION['_csrf'])) {
                            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
                        }
                        $csrfToken = $_SESSION['_csrf'];
                        ?>
                        <tr>
                            <td><?= (int)$row['LogID'] ?></td>
                            <td>
                                <span class="badge-type <?= htmlspecialchars($typeClass) ?>">
                                    <?php if ($typeClass === 'activity'): ?>
                                        ✔ Activity
                                    <?php elseif ($typeClass === 'history'): ?>
                                        ⏱ History
                                    <?php else: ?>
                                        ⚙ System
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="log-action"><?= htmlspecialchars($row['Action']) ?></div>
                                <div class="log-module">Module: <?= htmlspecialchars($row['Module']) ?></div>
                            </td>
                            <td>
                                <div class="log-description">
                                    <?= nl2br(htmlspecialchars($row['Description'])) ?>
                                </div>
                            </td>
                            <td class="log-user">
                                <?= htmlspecialchars($displayName) ?>
                                <?php if ($row['UserID']): ?>
                                    <div style="font-size:11px;color:#9ca3af;">UserID: <?= (int)$row['UserID'] ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- CỘT IP / THIẾT BỊ -->
                            <td>
                                <div class="log-ip"><?= htmlspecialchars($ipDisplay) ?></div>
                                <?php if (!empty($ua)): ?>
                                    <div class="log-ip ua-chip" title="<?= htmlspecialchars($ua) ?>">
                                        Thiết bị (UA…)
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td><?= date('d/m/Y H:i', strtotime($row['CreatedAt'])) ?></td>

                            <!-- ✅ CỘT THAO TÁC -->
                            <td class="log-actions">
                                <!-- Xem chi tiết -->
                                <a href="log_view.php?id=<?= (int)$row['LogID'] ?>"
                                    class="log-btn log-btn-view">
                                </a>

                                <!-- Xóa log -->
                                <form action="log_delete.php" method="post"
                                    onsubmit="return confirm('Xóa log #<?= (int)$row['LogID'] ?>? Hành động này không thể hoàn tác.');">
                                    <input type="hidden" name="log_id" value="<?= (int)$row['LogID'] ?>">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="log-btn log-btn-delete">

                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-row">Chưa có bản ghi log nào phù hợp.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>


        <!-- Phân trang -->
        <div class="pagination">
            <?php
            $queryBase = $_GET;
            ?>
            <?php if ($page > 1): ?>
                <?php $queryBase['page'] = $page - 1; ?>
                <a href="?<?= http_build_query($queryBase) ?>">« Trước</a>
            <?php else: ?>
                <a class="disabled">« Trước</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php $queryBase['page'] = $i; ?>
                <a href="?<?= http_build_query($queryBase) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <?php $queryBase['page'] = $page + 1; ?>
                <a href="?<?= http_build_query($queryBase) ?>">Sau »</a>
            <?php else: ?>
                <a class="disabled">Sau »</a>
            <?php endif; ?>
        </div>
    </div>

</body>

</html>