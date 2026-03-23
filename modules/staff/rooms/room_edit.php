<?php
// ======= BOOTSTRAP =======
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

// ======= CSRF =======
if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['_csrf'];

// ======= LẤY ID PHÒNG =======
$roomID = (int)($_GET['id'] ?? 0);
if ($roomID <= 0) {
    $_SESSION['message'] = 'Phòng không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

// ======= HÀM LẤY THÔNG TIN PHÒNG =======
function fetchRoom(mysqli $conn, int $roomID): ?array
{
    $stmt = $conn->prepare("
        SELECT r.*, b.BuildingName
        FROM Rooms r
        JOIN Buildings b ON r.BuildingID = b.BuildingID
        WHERE r.RoomID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $roomID);
    $stmt->execute();
    $res = $stmt->get_result();
    $room = $res->fetch_assoc();
    $stmt->close();
    return $room ?: null;
}

$room = fetchRoom($conn, $roomID);
if (!$room) {
    $_SESSION['message'] = 'Không tìm thấy phòng hoặc đã bị xóa.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

$error = '';

// ======= DANH SÁCH TIỆN NGHI =======
$amenityConfig = [
    'wifi'     => ['label' => 'Wi-Fi',             'icon' => 'wifi'],
    'aircon'   => ['label' => 'Điều hòa',          'icon' => 'snowflake'],
    'fridge'   => ['label' => 'Tủ lạnh',           'icon' => 'snowflake'],
    'tv'       => ['label' => 'Tivi',              'icon' => 'tv'],
    'bathroom' => ['label' => 'Phòng tắm riêng',   'icon' => 'bath'],
    'balcony'  => ['label' => 'Ban công',          'icon' => 'door-open'],
    'washing'  => ['label' => 'Máy giặt',          'icon' => 'soap'],
    'windown'  => ['label' => 'Cửa sổ',            'icon' => 'border-none'],
];

// tiện nghi hiện tại (từ DB)
$currentAmenities = [];
if (!empty($room['Amenities'])) {
    $decoded = json_decode($room['Amenities'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $a) {
            if (is_string($a)) {
                $key = strtolower(trim($a));
                if ($key !== '') $currentAmenities[] = $key;
            }
        }
    }
}

// ======= XỬ LÝ POST =======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['_csrf']) || !hash_equals($csrf, (string)$_POST['_csrf'])) {
        $error = '❌ Form không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $roomType = $_POST['room_type'] ?? 'Nam';
        $capacity = (int)($_POST['capacity'] ?? 4);
        $priceRaw = (string)($_POST['price'] ?? '0');
        $price    = (int)preg_replace('/\D+/', '', $priceRaw);
        $electricPriceRaw = (string)($_POST['electric_price'] ?? '0');
        $electricPrice = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $electricPriceRaw));
        $waterPriceRaw = (string)($_POST['water_price'] ?? '0');
        $waterPrice = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $waterPriceRaw));
        $status   = $_POST['status'] ?? 'Trống';
        $description = trim($_POST['description'] ?? '');

        // Tiện nghi
        $selectedAmenities = isset($_POST['amenities']) ? $_POST['amenities'] : [];
        $amenitiesClean = [];
        foreach ($selectedAmenities as $a) {
            $key = strtolower(trim($a));
            if ($key !== '' && isset($amenityConfig[$key])) {
                $amenitiesClean[] = $key;
            }
        }
        $amenitiesClean = array_values(array_unique($amenitiesClean));
        $amenitiesJson = json_encode($amenitiesClean, JSON_UNESCAPED_UNICODE);

        if (!in_array($roomType, ['Nam', 'Nữ', 'Khác'], true)) {
            $error = '⚠️ Loại phòng không hợp lệ.';
        } elseif ($capacity < 1 || $capacity > 8) {
            $error = '⚠️ Sức chứa phải từ 1–8.';
        } elseif ($price <= 0) {
            $error = '⚠️ Giá phòng phải > 0.';
        } elseif ($electricPrice < 0) {
            $error = '⚠️ Giá điện không hợp lệ.';
        } elseif ($waterPrice < 0) {
            $error = '⚠️ Giá nước không hợp lệ.';
        } elseif (!in_array($status, ['Trống', 'Đầy', 'Bảo trì'], true)) {
            $error = '⚠️ Trạng thái chỉ được Trống / Đầy / Bảo trì.';
        }

        // Upload ảnh
        $imagePath = $room['ImagePath'];
        if ($error === '' && !empty($_FILES['image']['name'])) {
            $uploadDirFs = '../../../assets/img/rooms/';
            $uploadDirDb = 'assets/img/rooms/';
            if (!is_dir($uploadDirFs)) mkdir($uploadDirFs, 0777, true);

            $f = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if ($f['error'] !== UPLOAD_ERR_OK) $error = '❌ Lỗi upload ảnh.';
            elseif (!in_array($f['type'], $allowed, true)) $error = '❌ Chỉ chấp nhận JPEG/PNG/WEBP.';
            elseif ($f['size'] > 5 * 1024 * 1024) $error = '❌ Ảnh quá 5MB.';
            else {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $newName = 'room_' . $roomID . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $uploadDirFs . $newName)) {
                    if (!empty($room['ImagePath'])) {
                        $oldFs = '../../../' . $room['ImagePath'];
                        if (is_file($oldFs)) @unlink($oldFs);
                    }
                    $imagePath = $uploadDirDb . $newName;
                } else {
                    $error = '❌ Không thể lưu ảnh mới.';
                }
            }
        }

        // Update DB
        if ($error === '') {
            $stmt = $conn->prepare("
                UPDATE Rooms
                SET RoomType=?, Capacity=?, RoomPrice=?, ElectricPrice=?, WaterPrice=?, `Status`=?, ImagePath=?, Amenities=?, Description=?, UpdatedAt=NOW()
                WHERE RoomID=?
            ");
            if (!$stmt) {
                $error = '❌ Lỗi MySQL (prepare): ' . $conn->error;
            } else {
                $stmt->bind_param(
                    "siiddssssi",
                    $roomType,
                    $capacity,
                    $price,
                    $electricPrice,
                    $waterPrice,
                    $status,
                    $imagePath,
                    $amenitiesJson,
                    $description,
                    $roomID
                );
                if ($stmt->execute()) {
                    unset($_SESSION['_csrf']);
                    $_SESSION['message'] = '✅ Cập nhật thông tin phòng thành công!';
                    $_SESSION['message_type'] = 'success';
                    header('Location: room_view.php?id=' . $roomID);
                    addLog(
                        $conn,
                        $_SESSION['UserID'] ?? null,
                        'Update room',
                        'Rooms',
                        "Cập nhật phòng ID={$roomID}",
                        'history'
                    );
                    exit;
                } else {
                    $error = '❌ Lỗi MySQL: ' . $stmt->error;
                }
                $stmt->close();
            }
            $room = fetchRoom($conn, $roomID);
            $currentAmenities = $amenitiesClean;
        }
    }
}


require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chỉnh sửa Phòng <?= htmlspecialchars($room['RoomNumber']) ?> - Hệ Thống Ký Túc Xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_room_edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>
    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-edit"></i> Chỉnh Sửa Phòng</h2>
            <p class="subtitle">Cập nhật phòng <?= htmlspecialchars($room['RoomNumber']) ?> – <?= htmlspecialchars($room['BuildingName']) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-content">
                <div class="form-grid">
                    <!-- Tòa & Số phòng -->
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Tòa nhà</label>
                        <input type="text" value="<?= htmlspecialchars($room['BuildingName']) ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-door-open"></i> Số phòng</label>
                        <input type="text" value="<?= htmlspecialchars($room['RoomNumber']) ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> Loại phòng *</label>
                        <?php $rt = $_POST['room_type'] ?? $room['RoomType']; ?>
                        <select name="room_type">
                            <option value="Nam" <?= $rt === 'Nam' ? 'selected' : '' ?>>Phòng Nam</option>
                            <option value="Nữ" <?= $rt === 'Nữ' ? 'selected' : '' ?>>Phòng Nữ</option>
                            <option value="Khác" <?= $rt === 'Khác' ? 'selected' : '' ?>>Phòng Khác</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Sức chứa *</label>
                        <input type="number" name="capacity" min="1" max="8" value="<?= htmlspecialchars($_POST['capacity'] ?? $room['Capacity']) ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Giá phòng (VNĐ) *</label>
                        <input type="text" name="price" id="price" value="<?= htmlspecialchars(number_format((int)($_POST['price'] ?? $room['RoomPrice']), 0, ',', '.')) ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-bolt"></i> Giá điện (đ/kWh)</label>
                        <input type="text" name="electric_price" id="electric_price" value="<?= htmlspecialchars(number_format((float)($_POST['electric_price'] ?? $room['ElectricPrice']), 0, ',', '.')) ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tint"></i> Giá nước (đ/m³)</label>
                        <input type="text" name="water_price" id="water_price" value="<?= htmlspecialchars(number_format((float)($_POST['water_price'] ?? $room['WaterPrice']), 0, ',', '.')) ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> Trạng thái *</label>
                        <?php $st = $_POST['status'] ?? $room['Status']; ?>
                        <select name="status">
                            <option value="Trống" <?= $st === 'Trống' ? 'selected' : '' ?>>Trống</option>
                            <option value="Đầy" <?= $st === 'Đầy' ? 'selected' : '' ?>>Đầy</option>
                            <option value="Bảo trì" <?= $st === 'Bảo trì' ? 'selected' : '' ?>>Bảo trì</option>
                        </select>
                    </div>

                    <!-- Ảnh -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-camera"></i> Ảnh phòng hiện tại</label>
                        <?php
                        $currentImg = (!empty($room['ImagePath']) && is_file('../../../' . $room['ImagePath']))
                            ? '../../../' . $room['ImagePath']
                            : '../../assets/img/no-image.jpg';
                        ?>
                        <img src="<?= htmlspecialchars(str_replace('../../../', '../../', $currentImg)) ?>" alt="Ảnh phòng" id="preview" style="max-width:300px;border-radius:12px;">
                    </div>

                    <div class="form-group full-width">
                        <label><i class="fas fa-sync-alt"></i> Cập nhật ảnh mới</label>
                        <input type="file" name="image" accept="image/*" onchange="previewImage(event)">
                    </div>

                    <!-- Tiện nghi -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-star"></i> Tiện nghi phòng</label>
                        <div class="amenities-wrap">
                            <?php foreach ($amenityConfig as $key => $cfg):
                                $checked = in_array($key, $currentAmenities, true) ? 'checked' : '';
                            ?>
                                <label class="amenity-chip">
                                    <input type="checkbox" name="amenities[]" value="<?= htmlspecialchars($key) ?>" <?= $checked ?>>
                                    <span><i class="fas fa-<?= htmlspecialchars($cfg['icon']) ?>"></i> <?= htmlspecialchars($cfg['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="muted">Chọn các tiện nghi thực tế của phòng.</small>
                    </div>

                    <!-- Mô tả -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-file-alt"></i> Mô tả phòng</label>
                        <textarea name="description" rows="5"><?= htmlspecialchars($_POST['description'] ?? $room['Description']) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Lưu Thay Đổi</button>
                    <a href="room_view.php?id=<?= (int)$room['RoomID'] ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Quay Lại</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        function previewImage(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sai định dạng',
                    text: 'Chỉ chấp nhận JPEG, PNG, WEBP'
                });
                e.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = ev => {
                document.getElementById('preview').src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>

</html>