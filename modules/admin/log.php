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
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-scroll"></i> Nhật ký hệ thống</h2>
            </div>
            <div class="mod-header-right">
                <span class="mod-badge mod-badge-blue">
                    <i class="fas fa-database"></i> Tổng: <strong><?= $total ?></strong> bản ghi
                </span>
            </div>
        </div>

        <!-- Tabs loại log -->
        <div class="mod-tabs">
            <?php
            function logTabUrl(string $typeVal = ''): string
            {
                $params = $_GET;
                if ($typeVal === '') unset($params['log_type']);
                else $params['log_type'] = $typeVal;
                return '?' . http_build_query($params);
            }
            ?>
            <a href="<?= logTabUrl('') ?>" class="mod-tab <?= $logType === '' ? 'active' : '' ?>">
                <i class="fas fa-layer-group"></i> Tất cả
            </a>
            <a href="<?= logTabUrl('activity') ?>" class="mod-tab <?= $logType === 'activity' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Activity
            </a>
            <a href="<?= logTabUrl('history') ?>" class="mod-tab <?= $logType === 'history' ? 'active' : '' ?>">
                <i class="fas fa-history"></i> Lịch sử
            </a>
            <a href="<?= logTabUrl('system') ?>" class="mod-tab <?= $logType === 'system' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i> Hệ thống
            </a>
        </div>

        <!-- Filters -->
        <form method="get" class="mod-filters">
            <input type="hidden" name="log_type" value="<?= htmlspecialchars($logType) ?>">

            <div class="mod-filter-group">
                <label><i class="fas fa-cube"></i> Module</label>
                <select name="module" class="mod-select" onchange="this.form.submit()">
                    <option value="">Tất cả module</option>
                    <?php foreach ($modules as $mod): ?>
                        <option value="<?= htmlspecialchars($mod) ?>" <?= $module === $mod ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mod) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-search"></i> Từ khóa</label>
                <input type="text" name="q" class="mod-input" placeholder="Action, mô tả..."
                    value="<?= htmlspecialchars($q) ?>">
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-calendar"></i> Từ ngày</label>
                <input type="date" name="date_from" class="mod-input" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-calendar-check"></i> Đến ngày</label>
                <input type="date" name="date_to" class="mod-input" value="<?= htmlspecialchars($dateTo) ?>">
            </div>

            <div class="mod-filter-group" style="flex:0; min-width: auto;">
                <label>&nbsp;</label>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="mod-btn mod-btn-primary mod-btn-sm">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                    <a href="log.php" class="mod-btn mod-btn-outline mod-btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Bảng log -->
        <div class="mod-table-wrap">
            <div class="mod-table-scroll">
                <table class="mod-table">
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

                                $rawIp = $row['IPAddress'] ?? '';
                                if ($rawIp === '::1') {
                                    $ipDisplay = '127.0.0.1 (localhost)';
                                } elseif (empty($rawIp)) {
                                    $ipDisplay = '-';
                                } else {
                                    $ipDisplay = $rawIp;
                                }

                                $ua = $row['UserAgent'] ?? '';

                                if (empty($_SESSION['_csrf'])) {
                                    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
                                }
                                $csrfToken = $_SESSION['_csrf'];

                                $badgeClass = match($typeClass) {
                                    'activity' => 'mod-badge-emerald',
                                    'history'  => 'mod-badge-blue',
                                    'system'   => 'mod-badge-amber',
                                    default    => 'mod-badge-gray',
                                };
                                $badgeIcon = match($typeClass) {
                                    'activity' => 'fa-check-circle',
                                    'history'  => 'fa-clock',
                                    'system'   => 'fa-cog',
                                    default    => 'fa-circle',
                                };
                                ?>
                                <tr>
                                    <td><span class="mod-cell-muted"><?= (int)$row['LogID'] ?></span></td>
                                    <td>
                                        <span class="mod-badge <?= $badgeClass ?>">
                                            <i class="fas <?= $badgeIcon ?>"></i>
                                            <?= ucfirst(htmlspecialchars($typeClass)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($row['Action']) ?></div>
                                        <div class="mod-cell-sub"><i class="fas fa-cube"></i> <?= htmlspecialchars($row['Module']) ?></div>
                                    </td>
                                    <td>
                                        <div class="mod-truncate" style="max-width:280px;" title="<?= htmlspecialchars($row['Description']) ?>">
                                            <?= htmlspecialchars($row['Description']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($displayName) ?></div>
                                        <?php if ($row['UserID']): ?>
                                            <div class="mod-cell-sub">ID: <?= (int)$row['UserID'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mod-cell-muted"><?= htmlspecialchars($ipDisplay) ?></div>
                                        <?php if (!empty($ua)): ?>
                                            <div class="mod-cell-sub" title="<?= htmlspecialchars($ua) ?>">
                                                <i class="fas fa-mobile-alt"></i> UA…
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="mod-cell-muted"><?= date('d/m/Y H:i', strtotime($row['CreatedAt'])) ?></span></td>
                                    <td>
                                        <div class="mod-row-actions">
                                            <a href="log_view.php?id=<?= (int)$row['LogID'] ?>" class="mod-btn-icon view" title="Xem">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form action="log_delete.php" method="post" style="display:inline;"
                                                onsubmit="return confirm('Xóa log #<?= (int)$row['LogID'] ?>?');">
                                                <input type="hidden" name="log_id" value="<?= (int)$row['LogID'] ?>">
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <button type="submit" class="mod-btn-icon delete" title="Xóa">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="mod-empty">
                                        <i class="fas fa-scroll"></i>
                                        <p>Chưa có bản ghi log nào phù hợp</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
        <div class="mod-pagination">
            <?php $queryBase = $_GET; ?>
            <?php if ($page > 1): ?>
                <?php $queryBase['page'] = 1; ?>
                <a href="?<?= http_build_query($queryBase) ?>" class="mod-pg" title="Đầu">&laquo;</a>
                <?php $queryBase['page'] = $page - 1; ?>
                <a href="?<?= http_build_query($queryBase) ?>" class="mod-pg">&lsaquo;</a>
            <?php else: ?>
                <span class="mod-pg disabled">&laquo;</span>
                <span class="mod-pg disabled">&lsaquo;</span>
            <?php endif; ?>

            <span class="mod-pg-info">Trang <?= $page ?> / <?= $totalPages ?></span>

            <?php if ($page < $totalPages): ?>
                <?php $queryBase['page'] = $page + 1; ?>
                <a href="?<?= http_build_query($queryBase) ?>" class="mod-pg">&rsaquo;</a>
                <?php $queryBase['page'] = $totalPages; ?>
                <a href="?<?= http_build_query($queryBase) ?>" class="mod-pg" title="Cuối">&raquo;</a>
            <?php else: ?>
                <span class="mod-pg disabled">&rsaquo;</span>
                <span class="mod-pg disabled">&raquo;</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Results info -->
        <div class="mod-results-info">
            <i class="fas fa-info-circle"></i>
            Hiển thị <?= min($perPage, $total - $offset) ?> / <?= $total ?> bản ghi
        </div>
    </div>

</body>
</html>