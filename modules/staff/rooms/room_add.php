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

$allowedAmenities = ['wifi', 'aircon', 'fridge', 'tv', 'bathroom', 'balcony', 'windown'];
$allowedRoomTypes = ['Nam', 'Nữ', 'Khác'];
$maxGallery = 10;
$maxPrice = 20000000;

$errors = [];
$error = '';

function uploadStrict(array $file, string $dir, string $prefix): array
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, 'Lỗi upload ảnh.'];
    }

    if (!in_array((string)($file['type'] ?? ''), $allowedTypes, true)) {
        return [null, 'Ảnh không hợp lệ.'];
    }

    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [null, 'Ảnh vượt quá 5MB.'];
    }

    if (!@getimagesize((string)$file['tmp_name'])) {
        return [null, 'File tải lên không phải ảnh hợp lệ.'];
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $extension = $extension !== '' ? $extension : 'jpg';
    $filename = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

    if (!move_uploaded_file((string)$file['tmp_name'], $dir . $filename)) {
        return [null, 'Không thể lưu ảnh.'];
    }

    return ['assets/img/rooms/' . $filename, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['_csrf'] ?? '');
    if ($postedToken === '' || !hash_equals($csrf, $postedToken)) {
        $errors[] = 'Form không hợp lệ. Vui lòng tải lại trang.';
    }

    $buildingId = (int)($_POST['building_id'] ?? 0);
    $numberRaw = trim((string)($_POST['room_number'] ?? ''));
    $number = strtoupper(preg_replace('/\s+/', '', $numberRaw) ?? '');
    $type = (string)($_POST['room_type'] ?? 'Nam');
    $capacity = (int)($_POST['capacity'] ?? 4);
    $price = (int)preg_replace('/\D/', '', (string)($_POST['price'] ?? '0'));
    $description = trim((string)($_POST['description'] ?? ''));
    $amenities = array_values(array_intersect((array)($_POST['amenities'] ?? []), $allowedAmenities));

    if ($buildingId <= 0) {
        $errors[] = 'Vui lòng chọn tòa nhà.';
    }

    if ($number === '') {
        $errors[] = 'Vui lòng nhập số phòng.';
    } elseif (!preg_match('/^[A-Z0-9-]{1,10}$/', $number)) {
        $errors[] = 'Số phòng chỉ cho phép A-Z, 0-9, dấu gạch ngang và tối đa 10 ký tự.';
    }

    if (!in_array($type, $allowedRoomTypes, true)) {
        $errors[] = 'Loại phòng không hợp lệ.';
    }

    if ($capacity < 1 || $capacity > 8) {
        $errors[] = 'Sức chứa phải trong khoảng 1 đến 8.';
    }

    if ($price <= 0 || $price > $maxPrice) {
        $errors[] = 'Giá phòng phải lớn hơn 0 và không vượt quá ' . number_format($maxPrice, 0, ',', '.');
    }

    $buildingName = '';
    if (!$errors) {
        $buildingStmt = $conn->prepare('SELECT BuildingName FROM Buildings WHERE BuildingID = ? LIMIT 1');
        $buildingStmt->bind_param('i', $buildingId);
        $buildingStmt->execute();
        $buildingStmt->bind_result($buildingName);
        $buildingStmt->fetch();
        $buildingStmt->close();

        if ($buildingName === '') {
            $errors[] = 'Không tìm thấy tòa nhà đã chọn.';
        }
    }

    if (!$errors) {
        $duplicateStmt = $conn->prepare('SELECT RoomID FROM Rooms WHERE BuildingID = ? AND RoomNumber = ? LIMIT 1');
        $duplicateStmt->bind_param('is', $buildingId, $number);
        $duplicateStmt->execute();
        $duplicateStmt->store_result();
        if ($duplicateStmt->num_rows > 0) {
            $errors[] = "Phòng {$number} đã tồn tại trong tòa {$buildingName}.";
        }
        $duplicateStmt->close();
    }

    $uploadDir = '../../../assets/img/rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $mainImagePath = null;
    $galleryPaths = [];

    if (!empty($_FILES['image']['name'])) {
        [$mainImagePath, $uploadError] = uploadStrict($_FILES['image'], $uploadDir, 'main');
        if ($uploadError !== null) {
            $errors[] = 'Ảnh chính: ' . $uploadError;
        }
    }

    if (!empty($_FILES['gallery']['name']) && is_array($_FILES['gallery']['name'])) {
        $selectedFiles = 0;
        foreach ((array)$_FILES['gallery']['error'] as $galleryError) {
            if ((int)$galleryError !== UPLOAD_ERR_NO_FILE) {
                $selectedFiles++;
            }
        }

        if ($selectedFiles > $maxGallery) {
            $errors[] = "Tối đa {$maxGallery} ảnh thư viện.";
        }

        if (!$errors) {
            foreach ((array)$_FILES['gallery']['tmp_name'] as $index => $tmpName) {
                if ((int)$_FILES['gallery']['error'][$index] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $galleryFile = [
                    'name' => $_FILES['gallery']['name'][$index] ?? '',
                    'type' => $_FILES['gallery']['type'][$index] ?? '',
                    'tmp_name' => $tmpName,
                    'error' => $_FILES['gallery']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['gallery']['size'][$index] ?? 0,
                ];

                [$path, $uploadError] = uploadStrict($galleryFile, $uploadDir, 'gallery');
                if ($uploadError !== null) {
                    $errors[] = 'Thư viện ảnh: ' . $uploadError;
                    break;
                }

                if ($path !== null) {
                    $galleryPaths[] = $path;
                }
            }
        }
    }

    if (!$errors) {
        $amenitiesJson = $amenities ? json_encode($amenities, JSON_UNESCAPED_UNICODE) : null;
        $galleryJson = $galleryPaths ? json_encode($galleryPaths, JSON_UNESCAPED_UNICODE) : null;

        $insertStmt = $conn->prepare(
            "INSERT INTO Rooms (
                BuildingID, RoomNumber, RoomType, Capacity, RoomPrice, Status,
                CurrentOccupants, ImagePath, Gallery, Description, Amenities
            ) VALUES (?, ?, ?, ?, ?, 'Trống', 0, ?, ?, ?, ?)"
        );

        $insertStmt->bind_param(
            'issidssss',
            $buildingId,
            $number,
            $type,
            $capacity,
            $price,
            $mainImagePath,
            $galleryJson,
            $description,
            $amenitiesJson
        );

        if ($insertStmt->execute()) {
            $newRoomId = (int)($insertStmt->insert_id ?: $conn->insert_id);
            $insertStmt->close();

            logRoomAction(
                $conn,
                $_SESSION['UserID'] ?? null,
                'create',
                "Thêm phòng mới ID={$newRoomId} - Số phòng: {$number} (form: {$numberRaw})",
                'activity'
            );

            unset($_SESSION['_csrf']);
            $_SESSION['message'] = "Thêm phòng <b>{$numberRaw}</b> thành công.";
            $_SESSION['message_type'] = 'success';
            header('Location: rooms.php');
            exit;
        }

        $errors[] = 'Lỗi MySQL: ' . $insertStmt->error;
        $insertStmt->close();
    }

    if ($errors) {
        $error = "<ul style='margin-left:15px;'>";
        foreach ($errors as $message) {
            $error .= '<li>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $error .= '</ul>';
    }
}

$buildings = $conn->query('SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName');
require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Phòng Mới - Hệ Thống Ký Túc Xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_room_add.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-plus-circle"></i> Thêm Phòng Mới</h2>
            <p class="subtitle">Thêm thông tin phòng mới vào hệ thống ký túc xá</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="roomForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-grid">
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Thông Tin Cơ Bản</h3>
                    </div>

                    <div class="form-group">
                        <label for="building_id"><i class="fas fa-building"></i> Tòa nhà *</label>
                        <select name="building_id" id="building_id" required>
                            <option value=""> Chọn tòa nhà </option>
                            <?php if ($buildings instanceof mysqli_result): ?>
                                <?php while ($building = $buildings->fetch_assoc()): ?>
                                    <option
                                        value="<?= (int)$building['BuildingID'] ?>"
                                        <?= (int)($_POST['building_id'] ?? 0) === (int)$building['BuildingID'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($building['BuildingName'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="room_number"><i class="fas fa-door-open"></i> Số phòng *</label>
                        <input type="text" name="room_number" id="room_number" required placeholder="VD: 101, A201..." value="<?= htmlspecialchars((string)($_POST['room_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label for="room_type"><i class="fas fa-venus-mars"></i> Loại phòng *</label>
                        <select name="room_type" id="room_type" required>
                            <option value="Nam" <?= (($_POST['room_type'] ?? '') === 'Nam') ? 'selected' : '' ?>>Phòng Nam</option>
                            <option value="Nữ" <?= (($_POST['room_type'] ?? '') === 'Nữ') ? 'selected' : '' ?>>Phòng Nữ</option>
                            <option value="Khác" <?= (($_POST['room_type'] ?? '') === 'Khác') ? 'selected' : '' ?>>Phòng Khác</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="capacity"><i class="fas fa-users"></i> Sức chứa *</label>
                        <input type="number" name="capacity" id="capacity" min="1" max="8" required value="<?= htmlspecialchars((string)($_POST['capacity'] ?? '4'), ENT_QUOTES, 'UTF-8') ?>">
                        <small style="color: var(--gray); margin-top: 5px; display: block;">Số người tối đa có thể ở trong phòng</small>
                    </div>

                    <div class="form-group">
                        <label for="price"><i class="fas fa-tag"></i> Giá phòng (VNĐ) *</label>
                        <input type="text" name="price" id="price" required placeholder="VD: 1.500.000" value="<?= htmlspecialchars((string)($_POST['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-images"></i>
                        <h3>Hình Ảnh & Mô Tả</h3>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Ảnh chính phòng</label>
                        <div class="file-upload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="upload-text">Tải lên ảnh chính</div>
                            <div class="upload-subtext">JPEG, PNG, WEBP (Tối đa 5MB)</div>
                            <input type="file" name="image" accept="image/*" onchange="previewMainImage(event)">
                        </div>
                        <div id="mainPreview" class="preview-container">
                            <div class="preview-grid" id="mainPreviewGrid"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-images"></i> Thư viện ảnh</label>
                        <div class="file-upload">
                            <i class="fas fa-layer-group"></i>
                            <div class="upload-text">Tải lên nhiều ảnh</div>
                            <div class="upload-subtext">Chọn nhiều ảnh (Tối đa 5MB/ảnh)</div>
                            <input type="file" name="gallery[]" accept="image/*" multiple onchange="previewGallery(event)">
                        </div>
                        <div id="galleryPreview" class="preview-container">
                            <div class="preview-grid" id="galleryPreviewGrid"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description"><i class="fas fa-file-alt"></i> Mô tả phòng</label>
                        <textarea name="description" id="description" rows="4" placeholder="Mô tả chi tiết về phòng, tiện nghi, view..."><?= htmlspecialchars((string)($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Tiện nghi phòng</label>
                        <div class="features-grid">
                            <?php foreach ($allowedAmenities as $amenity): ?>
                                <?php
                                $labels = [
                                    'wifi' => 'WiFi',
                                    'aircon' => 'Máy lạnh',
                                    'fridge' => 'Tủ lạnh',
                                    'tv' => 'TV',
                                    'bathroom' => 'WC khép kín',
                                    'balcony' => 'Ban công',
                                    'windown' => 'Cửa sổ',
                                ];
                                ?>
                                <div class="feature-checkbox">
                                    <input
                                        type="checkbox"
                                        name="amenities[]"
                                        value="<?= htmlspecialchars($amenity, ENT_QUOTES, 'UTF-8') ?>"
                                        id="<?= htmlspecialchars($amenity, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= in_array($amenity, (array)($_POST['amenities'] ?? []), true) ? 'checked' : '' ?>
                                    >
                                    <label for="<?= htmlspecialchars($amenity, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labels[$amenity], ENT_QUOTES, 'UTF-8') ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-save"></i> Thêm Phòng
                </button>
                <a href="rooms.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Quay Lại
                </a>
            </div>
        </form>
    </div>

    <script>
        function validateImage(file) {
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            const maxSize = 5 * 1024 * 1024;
            return allowedTypes.includes(file.type) && file.size <= maxSize;
        }

        function createPreviewItem(file) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);

            previewItem.appendChild(img);
            return previewItem;
        }

        function previewMainImage(event) {
            const container = document.getElementById('mainPreviewGrid');
            container.innerHTML = '';
            const file = event.target.files[0];
            if (!file) {
                return;
            }

            if (!validateImage(file)) {
                event.target.value = '';
                alert('Ảnh không hợp lệ. Vui lòng chọn ảnh JPEG, PNG, WEBP hoặc GIF dưới 5MB.');
                return;
            }

            container.appendChild(createPreviewItem(file));
        }

        function previewGallery(event) {
            const container = document.getElementById('galleryPreviewGrid');
            container.innerHTML = '';

            [...event.target.files].forEach((file) => {
                if (validateImage(file)) {
                    container.appendChild(createPreviewItem(file));
                }
            });
        }

        document.getElementById('roomForm').addEventListener('submit', function(event) {
            const submitBtn = document.getElementById('submitBtn');
            const building = document.getElementById('building_id').value.trim();
            const roomNumber = document.getElementById('room_number').value.trim();
            const price = document.getElementById('price').value.trim();

            if (!building || !roomNumber || !price) {
                event.preventDefault();
                alert('Vui lòng điền đầy đủ thông tin bắt buộc.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Đang xử lý...';
        });

        document.getElementById('price').addEventListener('input', function() {
            const digits = this.value.replace(/\D/g, '');
            this.value = digits ? parseInt(digits, 10).toLocaleString('vi-VN') : '';
        });
    </script>
</body>
</html>
