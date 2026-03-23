<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

requireRole(['Admin']);

$conn->set_charset('utf8mb4');

function redirectToList(string $message, string $type = 'info'): void
{
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header('Location: building_list.php');
    exit;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$buildingId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($buildingId <= 0) {
    redirectToList('Tòa nhà không hợp lệ.', 'error');
}

$buildingStmt = $conn->prepare('SELECT BuildingID, BuildingName, Description, Floors FROM Buildings WHERE BuildingID = ? LIMIT 1');
$buildingStmt->bind_param('i', $buildingId);
$buildingStmt->execute();
$building = $buildingStmt->get_result()->fetch_assoc();
$buildingStmt->close();

if (!$building) {
    redirectToList('Không tìm thấy tòa nhà cần chỉnh sửa.', 'error');
}

$csrf = csrfToken();
$errors = [];
$old = [
    'BuildingName' => (string)$building['BuildingName'],
    'Description' => (string)($building['Description'] ?? ''),
    'Floors' => (string)$building['Floors'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $old['BuildingName'] = trim($_POST['BuildingName'] ?? '');
    $old['Description'] = trim($_POST['Description'] ?? '');
    $old['Floors'] = trim($_POST['Floors'] ?? '');

    if ($old['BuildingName'] === '') {
        $errors[] = 'Vui lòng nhập tên tòa nhà.';
    } elseif (mb_strlen($old['BuildingName']) > 100) {
        $errors[] = 'Tên tòa nhà tối đa 100 ký tự.';
    }

    if ($old['Description'] !== '' && mb_strlen($old['Description']) > 500) {
        $errors[] = 'Mô tả tối đa 500 ký tự.';
    }

    if ($old['Floors'] === '' || !ctype_digit($old['Floors'])) {
        $errors[] = 'Số tầng phải là số nguyên dương.';
    } else {
        $floorValue = (int)$old['Floors'];
        if ($floorValue < 1 || $floorValue > 200) {
            $errors[] = 'Số tầng phải trong khoảng 1 đến 200.';
        }
    }

    if (!$errors) {
        $dupStmt = $conn->prepare('SELECT 1 FROM Buildings WHERE BuildingName = ? AND BuildingID <> ? LIMIT 1');
        $dupStmt->bind_param('si', $old['BuildingName'], $buildingId);
        $dupStmt->execute();
        $dupStmt->store_result();
        if ($dupStmt->num_rows > 0) {
            $errors[] = 'Tên tòa nhà đã tồn tại. Vui lòng chọn tên khác.';
        }
        $dupStmt->close();
    }

    if (!$errors) {
        $description = $old['Description'] === '' ? null : $old['Description'];
        $floorValue = (int)$old['Floors'];

        $updateStmt = $conn->prepare('UPDATE Buildings SET BuildingName = ?, Description = ?, Floors = ? WHERE BuildingID = ?');
        $updateStmt->bind_param('ssii', $old['BuildingName'], $description, $floorValue, $buildingId);

        if ($updateStmt->execute()) {
            $updateStmt->close();
            redirectToList('Cập nhật thông tin tòa nhà thành công.', 'success');
        }

        $errors[] = 'Không thể lưu thay đổi. Vui lòng thử lại.';
        $updateStmt->close();
    }
}

$statsStmt = $conn->prepare("
    SELECT
        COUNT(*) AS TotalRooms,
        COALESCE(SUM(Capacity), 0) AS TotalCapacity,
        SUM(CASE WHEN Status = 'Bảo trì' THEN 1 ELSE 0 END) AS MaintenanceRooms
    FROM Rooms
    WHERE BuildingID = ?
");
$statsStmt->bind_param('i', $buildingId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc() ?: [
    'TotalRooms' => 0,
    'TotalCapacity' => 0,
    'MaintenanceRooms' => 0,
];
$statsStmt->close();

$occupantsStmt = $conn->prepare("
    SELECT COUNT(*) AS CurrentOccupants
    FROM Contracts c
    JOIN Rooms r ON r.RoomID = c.RoomID
    WHERE c.Status = 'Hiệu lực' AND r.BuildingID = ?
");
$occupantsStmt->bind_param('i', $buildingId);
$occupantsStmt->execute();
$currentOccupants = (int)(($occupantsStmt->get_result()->fetch_assoc()['CurrentOccupants'] ?? 0));
$occupantsStmt->close();

require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa tòa nhà | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="/assets/css/staff/buildings/building_create.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="building-create-container">
    <div class="page-header">
        <div class="header-content">
            <div class="header-title">
                <div class="title-icon">
                    <i class="fas fa-pen"></i>
                </div>
                <div>
                    <h1>Chỉnh sửa tòa nhà</h1>
                    <p>Cập nhật thông tin tòa nhà và đồng bộ bộ lọc quản lý phòng</p>
                </div>
            </div>
            <div class="header-actions">
                <a class="btn btn-secondary" href="building_list.php">
                    <i class="fas fa-list"></i>
                    <span>Danh sách tòa nhà</span>
                </a>
                <a class="btn btn-ghost" href="../rooms/rooms.php?building_id=<?= $buildingId ?>&type=all&status=all">
                    <i class="fas fa-door-open"></i>
                    <span>Xem phòng của tòa</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h4>Có lỗi xảy ra</h4>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-building"></i> Thông tin tòa nhà</h2>
                <p>Chỉnh sửa thông tin vận hành cho tòa <?= e($building['BuildingName']) ?></p>
            </div>

            <form method="post" action="" class="building-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $buildingId ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="BuildingName" class="form-label">
                            <span>Tên tòa nhà</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-signature"></i>
                            </div>
                            <input
                                type="text"
                                id="BuildingName"
                                name="BuildingName"
                                class="form-input"
                                maxlength="100"
                                required
                                value="<?= e($old['BuildingName']) ?>"
                                placeholder="Ví dụ: Tòa A, Nhà B, Khu C..."
                            >
                        </div>
                        <div class="input-help">Tên tòa nhà là duy nhất trong hệ thống.</div>
                    </div>

                    <div class="form-group">
                        <label for="Floors" class="form-label">
                            <span>Số tầng</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <input
                                type="number"
                                id="Floors"
                                name="Floors"
                                class="form-input"
                                min="1"
                                max="200"
                                step="1"
                                required
                                value="<?= e($old['Floors']) ?>"
                                placeholder="Ví dụ: 5"
                            >
                        </div>
                        <div class="input-help">Số tầng từ 1 đến 200.</div>
                    </div>

                    <div class="form-group full-width">
                        <label for="Description" class="form-label">
                            <span>Mô tả</span>
                            <span class="optional">(Tùy chọn)</span>
                        </label>
                        <div class="textarea-group">
                            <div class="textarea-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <textarea
                                id="Description"
                                name="Description"
                                class="form-textarea"
                                maxlength="500"
                                rows="4"
                                placeholder="Ghi chú về vị trí, tiện ích, khu vực vận hành..."
                            ><?= e($old['Description']) ?></textarea>
                        </div>
                        <div class="textarea-footer">
                            <span class="char-count" id="charCount">0/500 ký tự</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i>
                        <span>Lưu thay đổi</span>
                    </button>
                    <a href="building_list.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left"></i>
                        <span>Quay lại</span>
                    </a>
                </div>
            </form>
        </div>

        <div class="tips-sidebar">
            <div class="tips-card">
                <div class="tips-header">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Lưu ý vận hành</h3>
                </div>
                <div class="tips-content">
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div class="tip-text">
                            <strong>Không đổi mã phòng</strong>
                            <p>Danh sách phòng của tòa được giữ nguyên. Chỉ cập nhật thông tin tòa.</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-filter"></i>
                        </div>
                        <div class="tip-text">
                            <strong>Lọc phòng theo ID tòa</strong>
                            <p>Danh sách phòng mới đã lọc theo `BuildingID` để ổn định hơn khi đổi tên tòa.</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <div class="tip-text">
                            <strong>Xóa có kiểm soát</strong>
                            <p>Tòa nhà chỉ được xóa khi không còn phòng để tránh làm vỡ dữ liệu liên kết.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-header">
                    <i class="fas fa-chart-column"></i>
                    <h3>Thống kê tòa</h3>
                </div>
                <div class="stats-content">
                    <div class="stat-item">
                        <span class="stat-label">Tổng phòng</span>
                        <span class="stat-value"><?= (int)$stats['TotalRooms'] ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Tổng sức chứa</span>
                        <span class="stat-value"><?= number_format((int)$stats['TotalCapacity']) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Đang ở</span>
                        <span class="stat-value"><?= number_format($currentOccupants) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Phòng bảo trì</span>
                        <span class="stat-value"><?= (int)$stats['MaintenanceRooms'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const descriptionTextarea = document.getElementById('Description');
    const charCount = document.getElementById('charCount');

    function updateCharCount() {
        const length = descriptionTextarea.value.length;
        charCount.textContent = `${length}/500 ký tự`;
        charCount.style.color = length > 450 ? '#ef4444' : (length > 400 ? '#f59e0b' : '#64748b');
    }

    updateCharCount();
    descriptionTextarea.addEventListener('input', updateCharCount);
});
</script>
</body>
</html>
