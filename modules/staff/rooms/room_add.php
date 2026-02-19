<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);
require_once '../../../includes/log_helper.php';

// Xử lý POST (validate, upload, insert, ghi log...)


// =============== CSRF token (tạo 1 lần và giữ nguyên tới khi submit thành công) ===============
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

// =============== Config validate ===============
$allowedAmenities = ['wifi', 'aircon', 'fridge', 'tv', 'bathroom', 'balcony'];
$allowedRoomTypes = ['Nam', 'Nữ', 'Khác'];
$MAX_GALLERY = 10;
$MAX_PRICE   = 20000000; // 20 triệu
$errors = [];
$error  = ''; // HTML error list sẽ gán sau

// =============== Xử lý POST ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: so sánh an toàn, không tạo token mới giữa chừng
    $postedToken  = (string)($_POST['_csrf'] ?? '');
    $sessionToken = (string)($csrf ?? '');
    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $errors[] = "❌ Form không hợp lệ. Vui lòng tải lại trang.";
    }

    // Lấy dữ liệu
    $building   = trim($_POST['building'] ?? '');
    $numberRaw  = trim($_POST['room_number'] ?? '');
    $number     = strtoupper(preg_replace('/\s+/', '', $numberRaw));
    $type       = $_POST['room_type'] ?? 'Nam';
    $capacity   = (int)($_POST['capacity'] ?? 4);
    // Parse giá an toàn: loại bỏ mọi ký tự không phải số
    $price      = (int)preg_replace('/\D/', '', $_POST['price'] ?? '0');
    $description = $_POST['description'] ?? '';
    $amenities  = isset($_POST['amenities']) ? array_intersect((array)$_POST['amenities'], $allowedAmenities) : [];

    // Validate
    if ($building === '') $errors[] = "⚠️ Vui lòng chọn tòa nhà.";
    if ($number === '')   $errors[] = "⚠️ Vui lòng nhập số phòng.";

    if ($number !== '' && !preg_match('/^[A-Z0-9\-]{1,10}$/', $number)) {
        $errors[] = "⚠️ Số phòng chỉ cho phép A–Z, 0–9, dấu '-' và tối đa 10 ký tự.";
    }
    if (!in_array($type, $allowedRoomTypes, true)) $errors[] = "⚠️ Loại phòng không hợp lệ.";
    if ($capacity < 1 || $capacity > 8)           $errors[] = "⚠️ Sức chứa phải từ 1–8.";
    if ($price <= 0 || $price > $MAX_PRICE)       $errors[] = "⚠️ Giá phòng phải > 0 và ≤ " . number_format($MAX_PRICE);

    $amenitiesJson = !empty($amenities) ? json_encode($amenities, JSON_UNESCAPED_UNICODE) : null;

    // =============== Upload ảnh ===============
    // Đường dẫn vật lý đúng từ modules/staff/rooms/ lên thư mục /assets/img/rooms/
    $uploadDir = '../assets/img/rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    function uploadStrict($file, $dir, $prefix)
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return [null, "Lỗi upload ảnh."];
        if (!in_array($file['type'] ?? '', $allowed, true)) return [null, "Ảnh không hợp lệ!"];
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) return [null, "Ảnh quá 5MB!"];

        $info = @getimagesize($file['tmp_name']);
        if (!$info) return [null, "File không phải ảnh hợp lệ!"];

        $ext = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
        $new = $prefix . "_" . bin2hex(random_bytes(6)) . "." . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $new)) return [null, "Không thể lưu ảnh!"];

        // Đường dẫn để lưu DB (tính từ web root)
        return ['assets/img/rooms/' . $new, null];
    }

    $mainImagePath = null;
    $galleryPaths  = [];

    // Ảnh chính
    if (!empty($_FILES['image']['name'] ?? '')) {
        [$p, $e] = uploadStrict($_FILES['image'], $uploadDir, 'main');
        if ($e) $errors[] = "Ảnh chính: $e";
        else $mainImagePath = $p;
    }

    // Gallery
    $galleryCount = 0;
    if (!empty($_FILES['gallery']['name']) && is_array($_FILES['gallery']['name'])) {
        foreach ($_FILES['gallery']['error'] as $k => $err) {
            if ($err !== UPLOAD_ERR_NO_FILE) $galleryCount++;
        }
        if ($galleryCount > $MAX_GALLERY) $errors[] = "⚠️ Tối đa $MAX_GALLERY ảnh gallery!";

        if (empty($errors)) {
            foreach ($_FILES['gallery']['tmp_name'] as $k => $tmp) {
                if ($_FILES['gallery']['error'][$k] === UPLOAD_ERR_OK) {
                    $file = [
                        'name'     => $_FILES['gallery']['name'][$k],
                        'type'     => $_FILES['gallery']['type'][$k] ?? '',
                        'tmp_name' => $tmp,
                        'error'    => $_FILES['gallery']['error'][$k],
                        'size'     => $_FILES['gallery']['size'][$k] ?? 0,
                    ];
                    [$p, $e] = uploadStrict($file, $uploadDir, 'gallery');
                    if ($e) {
                        $errors[] = "Gallery: $e";
                        break;
                    }
                    if ($p) $galleryPaths[] = $p;
                }
            }
        }
    }

    // =============== DB ===============
    if (empty($errors)) {
        // Tìm BuildingID theo BuildingName (nếu form submit tên)
        $buildingID = null;
        $b = $conn->prepare("SELECT BuildingID FROM Buildings WHERE BuildingName = ? LIMIT 1");
        $b->bind_param("s", $building);
        $b->execute();
        $b->bind_result($buildingID);
        $b->fetch();
        $b->close();

        if (empty($buildingID)) {
            $errors[] = "❌ Không tìm thấy tòa $building.";
        } else {
            // Check duplicate
            $c = $conn->prepare("SELECT RoomID FROM Rooms WHERE RoomNumber = ? AND BuildingID = ?");
            $c->bind_param("si", $number, $buildingID);
            $c->execute();
            $c->store_result();
            if ($c->num_rows > 0) $errors[] = "⚠️ Phòng $number đã tồn tại trong tòa $building!";
            $c->close();
        }

        if (empty($errors)) {
            $galleryJson = !empty($galleryPaths) ? json_encode($galleryPaths, JSON_UNESCAPED_UNICODE) : null;

            $stmt = $conn->prepare("
                INSERT INTO Rooms (BuildingID, RoomNumber, RoomType, Capacity, RoomPrice, Status, CurrentOccupants, ImagePath, Gallery, Description, Amenities)
                VALUES (?, ?, ?, ?, ?, 'Trống', 0, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "issidssss",
                $buildingID,
                $number,
                $type,
                $capacity,
                $price,
                $mainImagePath,
                $galleryJson,
                $description,
                $amenitiesJson
            );

            if ($stmt->execute()) {
                // Lấy ID phòng mới thêm để ghi log
                $newRoomId = $stmt->insert_id ?: $conn->insert_id;

                // Ghi log tạo phòng (nhớ đã include log_helper.php ở đâu đó)
                addLog(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'Create room',
                    'Rooms',
                    "Thêm phòng mới ID={$newRoomId} - Số phòng: {$number} (form: {$numberRaw})",
                    'activity'
                );

                // Dọn CSRF token để tránh double-submit
                unset($_SESSION['_csrf']);
                $_SESSION['message'] = "✅ Thêm phòng <b>{$numberRaw}</b> thành công!";
                $_SESSION['message_type'] = "success";

                // Không có output nào trước đó -> redirect an toàn
                header("Location: rooms.php");
                exit;
            } else {
                $errors[] = "❌ Lỗi MySQL: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Chuẩn bị HTML lỗi (nếu có) để hiển thị trong form
    if (!empty($errors)) {
        $error = "<ul style='margin-left:15px;'>";
        foreach ($errors as $e) $error .= "<li>{$e}</li>";
        $error .= "</ul>";
    }
}

// =============== DỮ LIỆU DÙNG CHO FORM (GET hoặc có lỗi) ===============
$buildings = $conn->query("SELECT BuildingName FROM Buildings ORDER BY BuildingName");
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
        <!-- Header -->
        <div class="form-header">
            <h2><i class="fas fa-plus-circle"></i> Thêm Phòng Mới</h2>
            <p class="subtitle">Thêm thông tin phòng mới vào hệ thống ký túc xá</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="roomForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-grid">
                <!-- Thông tin cơ bản -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Thông Tin Cơ Bản</h3>
                    </div>

                    <div class="form-group">
                        <label for="building"><i class="fas fa-building"></i> Tòa nhà *</label>
                        <select name="building" id="building" required>
                            <option value=""> Chọn tòa nhà </option>
                            <?php while ($b = $buildings->fetch_assoc()): ?>
                                <option
                                    value="<?= htmlspecialchars($b['BuildingName']) ?>"
                                    <?= (($_POST['building'] ?? '') === $b['BuildingName']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['BuildingName']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="room_number"><i class="fas fa-door-open"></i> Số phòng *</label>
                        <input
                            type="text"
                            name="room_number"
                            id="room_number"
                            required
                            placeholder="VD: 101, A201..."
                            value="<?= htmlspecialchars($_POST['room_number'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="room_type"><i class="fas fa-venus-mars"></i> Loại phòng *</label>
                        <select name="room_type" id="room_type" required>
                            <option value="Nam" <?= (($_POST['room_type'] ?? '') === 'Nam')  ? 'selected' : '' ?>>Phòng Nam</option>
                            <option value="Nữ" <?= (($_POST['room_type'] ?? '') === 'Nữ')   ? 'selected' : '' ?>>Phòng Nữ</option>
                            <option value="Khác" <?= (($_POST['room_type'] ?? '') === 'Khác') ? 'selected' : '' ?>>Phòng Khác</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="capacity"><i class="fas fa-users"></i> Sức chứa *</label>
                        <input
                            type="number"
                            name="capacity"
                            id="capacity"
                            min="1" max="8" required
                            value="<?= htmlspecialchars($_POST['capacity'] ?? '4') ?>">
                        <small style="color: var(--gray); margin-top: 5px; display: block;">Số người tối đa có thể ở trong phòng</small>
                    </div>

                    <div class="form-group">
                        <label for="price"><i class="fas fa-tag"></i> Giá phòng (VNĐ) *</label>
                        <input
                            type="text"
                            name="price"
                            id="price"
                            required
                            placeholder="VD: 1.500.000"
                            value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                    </div>
                </div>

                <!-- Hình ảnh & Mô tả -->
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
                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            placeholder="Mô tả chi tiết về phòng, tiện nghi, view..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Tiện nghi phòng</label>
                        <div class="features-grid">
                            <div class="feature-checkbox">
                                <input type="checkbox" name="amenities[]" value="wifi" id="wifi"
                                    <?= in_array('wifi', $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
                                <label for="wifi">WiFi</label>
                            </div>
                            <div class="feature-checkbox">
                                <input type="checkbox" name="amenities[]" value="aircon" id="aircon"
                                    <?= in_array('aircon', $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
                                <label for="aircon">Máy lạnh</label>
                            </div>
                            <div class="feature-checkbox">
                                <input type="checkbox" name="amenities[]" value="fridge" id="fridge"
                                    <?= in_array('fridge', $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
                                <label for="fridge">Tủ lạnh</label>
                            </div>
                            <div class="feature-checkbox">
                                <input type="checkbox" name="amenities[]" value="tv" id="tv"
                                    <?= in_array('tv', $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
                                <label for="tv">TV</label>
                            </div>
                            <div class="feature-checkbox">
                                <input type="checkbox" name="amenities[]" value="bathroom" id="bathroom"
                                    <?= in_array('bathroom', $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
                                <label for="bathroom">WC khép kín</label>
                            </div>
                            <div class="feature-checkbox">
                                <input type="checkbox" name="amenities[]" value="balcony" id="balcony"
                                    <?= in_array('balcony', $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
                                <label for="balcony">Ban công</label>
                            </div>
                            <div class="feature-checkbox">
                                <input type="checkbox" name="amenities[]" value="windown" id="windown"
                                    <?= in_array('windown', $_POST['amenities'] ?? []) ? 'checked' : '' ?>>
                                <label for="windown">Cửa sổ</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
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
        // Xem trước ảnh chính
        function previewMainImage(event) {
            const container = document.getElementById('mainPreviewGrid');
            container.innerHTML = '';
            const file = event.target.files[0];
            if (file) {
                if (validateImage(file)) {
                    const previewItem = createPreviewItem(file, true);
                    container.appendChild(previewItem);
                } else {
                    event.target.value = '';
                    alert('Ảnh không hợp lệ! Vui lòng chọn ảnh JPEG, PNG, WEBP dưới 5MB.');
                }
            }
        }

        // Xem trước ảnh gallery
        function previewGallery(event) {
            const container = document.getElementById('galleryPreviewGrid');
            const files = [...event.target.files];
            container.innerHTML = '';
            files.forEach(file => {
                if (validateImage(file)) {
                    const previewItem = createPreviewItem(file, false);
                    container.appendChild(previewItem);
                }
            });
        }

        // Tạo preview item
        function createPreviewItem(file, isMain) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);

            const removeBtn = document.createElement('button');
            removeBtn.className = 'preview-remove';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.onclick = function() {
                previewItem.remove();
                updateFileInput(isMain ? 'image' : 'gallery[]');
            };

            previewItem.appendChild(img);
            previewItem.appendChild(removeBtn);
            return previewItem;
        }

        // Cập nhật file input sau khi xóa preview (tối giản cho demo)
        function updateFileInput(inputName) {
            console.log('Update file input for:', inputName);
        }

        // Validate image
        function validateImage(file) {
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (!allowedTypes.includes(file.type)) return false;
            if (file.size > maxSize) return false;
            return true;
        }

        // Form validation + chống double click
        document.getElementById('roomForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Đang xử lý...';

            const building = document.getElementById('building').value.trim();
            const roomNumber = document.getElementById('room_number').value.trim();
            const price = document.getElementById('price').value.trim();

            if (!building || !roomNumber || !price) {
                e.preventDefault();
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Thêm Phòng';
                alert('Vui lòng điền đầy đủ thông tin bắt buộc!');
            }
        });

        // Auto-format price (VN)
        document.getElementById('price').addEventListener('input', function() {
            const digits = this.value.replace(/\D/g, '');
            if (digits) this.value = parseInt(digits).toLocaleString('vi-VN');
            else this.value = '';
        });
    </script>
</body>

</html>