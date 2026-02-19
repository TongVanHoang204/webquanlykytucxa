<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once __DIR__ . '/../../includes/admin_header.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// Lấy ID
$logId = (int)($_GET['id'] ?? 0);
if ($logId <= 0) {
    echo "<div class='alert alert-danger m-3'>ID log không hợp lệ.</div>";
    exit;
}

// Lấy chi tiết log
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
    echo "<div class='alert alert-warning m-3'>Không tìm thấy log.</div>";
    exit;
}

// Chuẩn IP
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết log #<?= htmlspecialchars($logId) ?></title>
    <link rel="stylesheet" href="/assets/css/admin/admin_dashboard.css">
    <link rel="stylesheet" href="/assets/css/admin/log.css">
    <style>
        .log-view-wrapper {
            max-width: 800px;
            margin: 24px auto;
            padding: 20px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .log-view-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .log-view-header h1 {
            font-size: 20px;
            margin: 0;
        }
        .log-view-meta {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .log-view-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .log-view-table th,
        .log-view-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .log-view-table th {
            width: 140px;
            background: #f9fafb;
            font-weight: 600;
            color: #4b5563;
        }
        .log-view-description {
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            background: #0f172a;
            color: #e5e7eb;
            padding: 10px 12px;
            border-radius: 8px;
            max-height: 260px;
            overflow: auto;
        }
        .log-view-actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
        }
        .log-btn-back {
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #fff;
            font-size: 13px;
            text-decoration: none;
            color: #111827;
        }
    </style>
</head>
<body>

<div class="log-view-wrapper">
    <div class="log-view-header">
        <h1>Chi tiết log #<?= htmlspecialchars($logId) ?></h1>
        <span class="badge-type <?= htmlspecialchars($log['LogType'] ?? 'activity') ?>">
            <?= htmlspecialchars($log['LogType'] ?? 'activity') ?>
        </span>
    </div>

    <div class="log-view-meta">
        Thời gian: <strong><?= date('d/m/Y H:i:s', strtotime($log['CreatedAt'])) ?></strong> ·
        Người thực hiện: <strong><?= htmlspecialchars($displayName) ?></strong>
    </div>

    <table class="log-view-table">
        <tr>
            <th>Module</th>
            <td><?= htmlspecialchars($log['Module']) ?></td>
        </tr>
        <tr>
            <th>Action</th>
            <td><?= htmlspecialchars($log['Action']) ?></td>
        </tr>
        <tr>
            <th>Loại log</th>
            <td><?= htmlspecialchars($log['LogType']) ?></td>
        </tr>
        <tr>
            <th>UserID</th>
            <td><?= $log['UserID'] ? (int)$log['UserID'] : '-' ?></td>
        </tr>
        <tr>
            <th>IP</th>
            <td><?= htmlspecialchars($ipDisplay) ?></td>
        </tr>
        <tr>
            <th>User Agent</th>
            <td style="font-size:12px; color:#4b5563;">
                <?= $ua ? nl2br(htmlspecialchars($ua)) : '-' ?>
            </td>
        </tr>
        <tr>
            <th>Mô tả</th>
            <td>
                <div class="log-view-description">
<?= htmlspecialchars($log['Description']) ?>
                </div>
            </td>
        </tr>
    </table>

    <div class="log-view-actions">
        <a href="log.php" class="log-btn-back">← Quay lại danh sách</a>
    </div>
</div>

</body>
</html>
