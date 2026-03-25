<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';

requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

function fetchRoom(mysqli $conn, int $roomId): ?array
{
    $stmt = $conn->prepare(
        "SELECT r.*, b.BuildingName
         FROM Rooms r
         JOIN Buildings b ON b.BuildingID = r.BuildingID
         WHERE r.RoomID = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $room ?: null;
}

$roomId = (int)($_GET['id'] ?? 0);
if ($roomId <= 0) {
    $_SESSION['message'] = 'Phòng không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

$room = fetchRoom($conn, $roomId);
if ($room === null) {
    $_SESSION['message'] = 'Không tìm thấy phòng hoặc phòng đã bị xóa.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

$buildingOptions = [];
$buildingRes = $conn->query('SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName ASC');
if ($buildingRes instanceof mysqli_result) {
    while ($building = $buildingRes->fetch_assoc()) {
        $buildingOptions[] = $building;
    }
}

$amenityConfig = [
    'wifi' => ['label' => 'Wi-Fi', 'icon' => 'wifi'],
    'aircon' => ['label' => 'Điều hòa', 'icon' => 'snowflake'],
    'fridge' => ['label' => 'Tủ lạnh', 'icon' => 'snowflake'],
    'tv' => ['label' => 'Tivi', 'icon' => 'tv'],
    'bathroom' => ['label' => 'Phòng tắm riêng', 'icon' => 'bath'],
    'balcony' => ['label' => 'Ban công', 'icon' => 'door-open'],
    'washing' => ['label' => 'Máy giặt', 'icon' => 'soap'],
    'windown' => ['label' => 'Cửa sổ', 'icon' => 'border-none'],
];

$currentAmenities = [];
if (!empty($room['Amenities'])) {
    $decodedAmenities = json_decode((string)$room['Amenities'], true);
    if (is_array($decodedAmenities)) {
        foreach ($decodedAmenities as $amenity) {
            if (is_string($amenity)) {
                $key = strtolower(trim($amenity));
                if ($key !== '') {
                    $currentAmenities[] = $key;
                }
            }
        }
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['_csrf']) || !hash_equals($csrf, (string)$_POST['_csrf'])) {
        $error = 'Form không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $buildingId = (int)($_POST['building_id'] ?? 0);
        $roomNumberRaw = trim((string)($_POST['room_number'] ?? ''));
        $roomNumber = strtoupper(preg_replace('/\s+/', '', $roomNumberRaw) ?? '');
        $roomType = (string)($_POST['room_type'] ?? 'Nam');
        $capacity = (int)($_POST['capacity'] ?? 4);
        $priceRaw = (string)($_POST['price'] ?? '0');
        $price = (int)preg_replace('/\D+/', '', $priceRaw);
        $status = (string)($_POST['status'] ?? 'Trống');
        $description = trim((string)($_POST['description'] ?? ''));

        $selectedAmenities = array_values(array_unique((array)($_POST['amenities'] ?? [])));
        $amenitiesClean = [];
        foreach ($selectedAmenities as $amenity) {
            $key = strtolower(trim((string)$amenity));
            if ($key !== '' && isset($amenityConfig[$key])) {
                $amenitiesClean[] = $key;
            }
        }
        $amenitiesJson = json_encode($amenitiesClean, JSON_UNESCAPED_UNICODE);

        $newBuildingName = '';
        if ($buildingId <= 0) {
            $error = 'Vui lòng chọn tòa nhà.';
        } else {
            $buildingStmt = $conn->prepare('SELECT BuildingName FROM Buildings WHERE BuildingID = ? LIMIT 1');
            $buildingStmt->bind_param('i', $buildingId);
            $buildingStmt->execute();
            $buildingStmt->bind_result($newBuildingName);
            $buildingStmt->fetch();
            $buildingStmt->close();

            if ($newBuildingName === '') {
                $error = 'Không tìm thấy tòa nhà đã chọn.';
            }
        }

        if ($error === '' && $roomNumber === '') {
            $error = 'Vui lòng nhập số phòng.';
        } elseif ($error === '' && !preg_match('/^[A-Z0-9-]{1,10}$/', $roomNumber)) {
            $error = 'Số phòng chỉ cho phép A-Z, 0-9, dấu gạch ngang và tối đa 10 ký tự.';
        } elseif ($error === '' && !in_array($roomType, ['Nam', 'Nữ', 'Khác'], true)) {
            $error = 'Loại phòng không hợp lệ.';
        } elseif ($error === '' && ($capacity < 1 || $capacity > 8)) {
            $error = 'Sức chứa phải trong khoảng 1 đến 8.';
        } elseif ($error === '' && $price <= 0) {
            $error = 'Giá phòng phải lớn hơn 0.';
        } elseif ($error === '' && !in_array($status, ['Trống', 'Đầy', 'Bảo trì'], true)) {
            $error = 'Trạng thái chỉ được là Trống, Đầy hoặc Bảo trì.';
        }

        if ($error === '') {
            $duplicateStmt = $conn->prepare(
                'SELECT RoomID FROM Rooms WHERE BuildingID = ? AND RoomNumber = ? AND RoomID <> ? LIMIT 1'
            );
            $duplicateStmt->bind_param('isi', $buildingId, $roomNumber, $roomId);
            $duplicateStmt->execute();
            $duplicateStmt->store_result();
            if ($duplicateStmt->num_rows > 0) {
                $error = "Phòng {$roomNumber} đã tồn tại trong tòa {$newBuildingName}.";
            }
            $duplicateStmt->close();
        }

        $imagePath = (string)$room['ImagePath'];
        if ($error === '' && !empty($_FILES['image']['name'])) {
            $uploadDirFs = '../../../assets/img/rooms/';
            $uploadDirDb = 'assets/img/rooms/';
            if (!is_dir($uploadDirFs)) {
                mkdir($uploadDirFs, 0777, true);
            }

            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error = 'Lỗi upload ảnh.';
            } elseif (!in_array((string)($file['type'] ?? ''), $allowedTypes, true)) {
                $error = 'Chỉ chấp nhận JPEG, PNG hoặc WEBP.';
            } elseif ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
                $error = 'Ảnh vượt quá 5MB.';
            } else {
                $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
                $newFilename = 'room_' . $roomId . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                if (move_uploaded_file((string)$file['tmp_name'], $uploadDirFs . $newFilename)) {
                    if ($imagePath !== '') {
                        $oldImageFs = '../../../' . $imagePath;
                        if (is_file($oldImageFs)) {
                            @unlink($oldImageFs);
                        }
                    }
                    $imagePath = $uploadDirDb . $newFilename;
                } else {
                    $error = 'Không thể lưu ảnh mới.';
                }
            }
        }

        if ($error === '') {
            $updateStmt = $conn->prepare(
                "UPDATE Rooms
                 SET BuildingID = ?, RoomNumber = ?, RoomType = ?, Capacity = ?, RoomPrice = ?, Status = ?,
                     ImagePath = ?, Amenities = ?, Description = ?, UpdatedAt = NOW()
                 WHERE RoomID = ?"
            );

            if (!$updateStmt) {
                $error = 'Lỗi MySQL (prepare): ' . $conn->error;
            } else {
                $updateStmt->bind_param(
                    'issidssssi',
                    $buildingId,
                    $roomNumber,
                    $roomType,
                    $capacity,
                    $price,
                    $status,
                    $imagePath,
                    $amenitiesJson,
                    $description,
                    $roomId
                );

                if ($updateStmt->execute()) {
                    $oldBuildingName = (string)$room['BuildingName'];
                    $oldRoomNumber = (string)$room['RoomNumber'];
                    $updateStmt->close();

                    logRoomAction(
                        $conn,
                        $_SESSION['UserID'] ?? null,
                        'update',
                        "Cập nhật phòng ID={$roomId}: {$oldBuildingName}-{$oldRoomNumber} -> {$newBuildingName}-{$roomNumber}",
                        'history'
                    );

                    unset($_SESSION['_csrf']);
                    $_SESSION['message'] = 'Cập nhật thông tin phòng thành công.';
                    $_SESSION['message_type'] = 'success';
                    header('Location: room_view.php?id=' . $roomId);
                    exit;
                }

                $error = 'Lỗi MySQL: ' . $updateStmt->error;
                $updateStmt->close();
            }
        }

        $room = fetchRoom($conn, $roomId);
        if ($room !== null) {
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
    <title>Chỉnh sửa Phòng <?= htmlspecialchars((string)$room['RoomNumber'], ENT_QUOTES, 'UTF-8') ?> - Hệ Thống Ký Túc Xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_room_edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-edit"></i> Chỉnh Sửa Phòng</h2>
            <p class="subtitle">Cập nhật phòng <?= htmlspecialchars((string)$room['RoomNumber'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$room['BuildingName'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-content">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="building_id"><i class="fas fa-building"></i> Tòa nhà *</label>
                        <select name="building_id" id="building_id" required>
                            <?php foreach ($buildingOptions as $building): ?>
                                <option value="<?= (int)$building['BuildingID'] ?>" <?= (int)($_POST['building_id'] ?? $room['BuildingID']) === (int)$building['BuildingID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$building['BuildingName'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="room_number"><i class="fas fa-door-open"></i> Số phòng *</label>
                        <input type="text" id="room_number" name="room_number" value="<?= htmlspecialchars((string)($_POST['room_number'] ?? $room['RoomNumber']), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <?php $roomTypeValue = (string)($_POST['room_type'] ?? $room['RoomType']); ?>
                        <label for="room_type"><i class="fas fa-venus-mars"></i> Loại phòng *</label>
                        <select name="room_type" id="room_type">
                            <option value="Nam" <?= $roomTypeValue === 'Nam' ? 'selected' : '' ?>>Phòng Nam</option>
                            <option value="Nữ" <?= $roomTypeValue === 'Nữ' ? 'selected' : '' ?>>Phòng Nữ</option>
                            <option value="Khác" <?= $roomTypeValue === 'Khác' ? 'selected' : '' ?>>Phòng Khác</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="capacity"><i class="fas fa-users"></i> Sức chứa *</label>
                        <input type="number" id="capacity" name="capacity" min="1" max="8" value="<?= htmlspecialchars((string)($_POST['capacity'] ?? $room['Capacity']), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label for="price"><i class="fas fa-tag"></i> Giá phòng (VNĐ) *</label>
                        <input type="text" name="price" id="price" value="<?= htmlspecialchars(number_format((int)($_POST['price'] ?? $room['RoomPrice']), 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <?php $statusValue = (string)($_POST['status'] ?? $room['Status']); ?>
                        <label for="status"><i class="fas fa-info-circle"></i> Trạng thái *</label>
                        <select name="status" id="status">
                            <option value="Trống" <?= $statusValue === 'Trống' ? 'selected' : '' ?>>Trống</option>
                            <option value="Đầy" <?= $statusValue === 'Đầy' ? 'selected' : '' ?>>Đầy</option>
                            <option value="Bảo trì" <?= $statusValue === 'Bảo trì' ? 'selected' : '' ?>>Bảo trì</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label><i class="fas fa-camera"></i> Ảnh phòng hiện tại</label>
                        <?php
                        $currentImage = (!empty($room['ImagePath']) && is_file('../../../' . $room['ImagePath']))
                            ? '../../../' . $room['ImagePath']
                            : '../../../assets/img/no-image.png';
                        ?>
                        <img src="<?= htmlspecialchars($currentImage, ENT_QUOTES, 'UTF-8') ?>" alt="Ảnh phòng" id="preview" style="max-width:300px;border-radius:12px;">
                    </div>

                    <div class="form-group full-width">
                        <label for="image"><i class="fas fa-sync-alt"></i> Cập nhật ảnh mới</label>
                        <input type="file" name="image" id="image" accept="image/*" onchange="previewImage(event)">
                    </div>

                    <div class="form-group full-width">
                        <label><i class="fas fa-star"></i> Tiện nghi phòng</label>
                        <div class="amenities-wrap">
                            <?php foreach ($amenityConfig as $key => $config): ?>
                                <label class="amenity-chip">
                                    <input type="checkbox" name="amenities[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($key, $currentAmenities, true) ? 'checked' : '' ?>>
                                    <span><i class="fas fa-<?= htmlspecialchars((string)$config['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string)$config['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="muted">Chọn các tiện nghi thực tế của phòng.</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="description"><i class="fas fa-file-alt"></i> Mô tả phòng</label>
                        <textarea name="description" id="description" rows="5"><?= htmlspecialchars((string)($_POST['description'] ?? $room['Description']), ENT_QUOTES, 'UTF-8') ?></textarea>
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
        function previewImage(event) {
            const file = event.target.files[0];
            if (!file) {
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sai định dạng',
                    text: 'Chỉ chấp nhận JPEG, PNG hoặc WEBP'
                });
                event.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (loadEvent) => {
                document.getElementById('preview').src = loadEvent.target.result;
            };
            reader.readAsDataURL(file);
        }

        document.getElementById('price').addEventListener('input', function() {
            const digits = this.value.replace(/\D/g, '');
            this.value = digits ? parseInt(digits, 10).toLocaleString('vi-VN') : '';
        });
    </script>
</body>
</html>
