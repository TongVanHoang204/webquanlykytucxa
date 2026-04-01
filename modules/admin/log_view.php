<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once __DIR__ . '/../../includes/admin_header.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$logId = (int)($_GET['id'] ?? 0);
if ($logId <= 0) {
    echo "<div class='mod-alert mod-alert-danger' style='margin:20px;'>ID log không hợp lệ.</div>";
    exit;
}

$sql = "
    SELECT 
        l.*,
        u.Username,
        u.FullName
    FROM SystemLogs l
    LEFT JOIN Users u ON l.UserID = u.UserID
    WHERE l.LogID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $logId);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$log) {
    echo "<div class='mod-alert mod-alert-warning' style='margin:20px;'>Không tìm thấy log.</div>";
    exit;
}

$rawIp = $log['IPAddress'] ?? '';
if ($rawIp === '::1') {
    $ipDisplay = '127.0.0.1 (localhost)';
} elseif (empty($rawIp)) {
    $ipDisplay = '-';
} else {
    $ipDisplay = $rawIp;
}

$ua = $log['UserAgent'] ?? '';
$displayName = $log['FullName'] ?: $log['Username'] ?: 'Khách / Hệ thống';

$typeClass = $log['LogType'] ?? 'activity';
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
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết log #<?= htmlspecialchars($logId) ?></title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
</head>
<body>
<div class="mod-container" style="max-width:900px;">
    <!-- Header -->
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-file-alt"></i> Chi tiết log #<?= htmlspecialchars($logId) ?></h2>
        </div>
        <div class="mod-header-right">
            <span class="mod-badge <?= $badgeClass ?>">
                <i class="fas <?= $badgeIcon ?>"></i>
                <?= ucfirst(htmlspecialchars($typeClass)) ?>
            </span>
            <a href="log.php" class="mod-btn mod-btn-outline mod-btn-sm">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Thông tin tổng quan -->
    <div class="mod-card">
        <div class="mod-card-header">
            <div class="mod-card-title"><i class="fas fa-info-circle"></i> Thông tin chung</div>
            <span class="mod-cell-muted"><?= date('d/m/Y H:i:s', strtotime($log['CreatedAt'])) ?></span>
        </div>
        <div class="mod-card-body">
            <div class="mod-info-grid">
                <div class="mod-info-item">
                    <span class="mod-info-label"><i class="fas fa-cube"></i> Module</span>
                    <span class="mod-info-value"><?= htmlspecialchars($log['Module']) ?></span>
                </div>
                <div class="mod-info-item">
                    <span class="mod-info-label"><i class="fas fa-bolt"></i> Action</span>
                    <span class="mod-info-value"><?= htmlspecialchars($log['Action']) ?></span>
                </div>
                <div class="mod-info-item">
                    <span class="mod-info-label"><i class="fas fa-tag"></i> Loại log</span>
                    <span class="mod-info-value">
                        <span class="mod-badge <?= $badgeClass ?>">
                            <i class="fas <?= $badgeIcon ?>"></i> <?= ucfirst(htmlspecialchars($typeClass)) ?>
                        </span>
                    </span>
                </div>
                <div class="mod-info-item">
                    <span class="mod-info-label"><i class="fas fa-user"></i> Người thực hiện</span>
                    <span class="mod-info-value"><?= htmlspecialchars($displayName) ?><?= $log['UserID'] ? ' (ID: ' . (int)$log['UserID'] . ')' : '' ?></span>
                </div>
                <div class="mod-info-item">
                    <span class="mod-info-label"><i class="fas fa-network-wired"></i> Địa chỉ IP</span>
                    <span class="mod-info-value"><?= htmlspecialchars($ipDisplay) ?></span>
                </div>
                <div class="mod-info-item">
                    <span class="mod-info-label"><i class="fas fa-mobile-alt"></i> User Agent</span>
                    <span class="mod-info-value" style="font-size:0.82rem; word-break:break-all;"><?= $ua ? htmlspecialchars($ua) : '-' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Mô tả -->
    <div class="mod-card">
        <div class="mod-card-header">
            <div class="mod-card-title"><i class="fas fa-align-left"></i> Mô tả chi tiết</div>
        </div>
        <div class="mod-card-body">
            <pre style="white-space:pre-wrap; font-family:'JetBrains Mono',ui-monospace,monospace; font-size:0.85rem; background:var(--bg); padding:18px; border-radius:12px; border:1px solid var(--stroke); color:var(--text); max-height:300px; overflow:auto; margin:0;"><?= htmlspecialchars($log['Description']) ?></pre>
        </div>
    </div>
</div>
</body>
</html>
