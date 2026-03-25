<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';

// Chỉ Admin & Manager được truy cập
requireRole(['Admin', 'Manager']);

/* ===================== CSRF token ===================== */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

/* ===================== Biến & trạng thái ===================== */
$errors = [];
$okMsg  = null;

// Giữ lại giá trị form khi có lỗi
$old = [
    'BuildingName' => '',
    'Description'  => '',
    'Floors'       => ''
];

/* ===================== Xử lý submit ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        $errors[] = 'Yêu cầu không hợp lệ (CSRF). Vui lòng thử lại.';
    }

    // Lấy dữ liệu & làm sạch
    $old['BuildingName'] = trim($_POST['BuildingName'] ?? '');
    $old['Description']  = trim($_POST['Description'] ?? '');
    $old['Floors']       = trim($_POST['Floors'] ?? '');

    // Validate
    if ($old['BuildingName'] === '') {
        $errors[] = 'Vui lòng nhập tên tòa nhà.';
    } elseif (mb_strlen($old['BuildingName']) > 100) {
        $errors[] = 'Tên tòa nhà quá dài (tối đa 100 ký tự).';
    }

    if ($old['Description'] !== '' && mb_strlen($old['Description']) > 500) {
        $errors[] = 'Mô tả quá dài (tối đa 500 ký tự).';
    }

    if ($old['Floors'] === '' || !ctype_digit($old['Floors'])) {
        $errors[] = 'Số tầng phải là số nguyên dương.';
    } else {
        $floors = (int)$old['Floors'];
        if ($floors < 1 || $floors > 200) {
            $errors[] = 'Số tầng phải trong khoảng 1–200.';
        }
    }

    // Kiểm tra trùng tên tòa nhà
    if (empty($errors)) {
        $sqlCheck = "SELECT 1 FROM Buildings WHERE BuildingName = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param('s', $old['BuildingName']);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $errors[] = 'Tên tòa nhà đã tồn tại. Vui lòng chọn tên khác.';
        }
        $stmtCheck->close();
    }

    // Insert
    if (empty($errors)) {
        $sql = "INSERT INTO Buildings (BuildingName, Description, Floors) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $desc = $old['Description'] === '' ? null : $old['Description'];
        $floors = (int)$old['Floors'];
        $stmt->bind_param('ssi', $old['BuildingName'], $desc, $floors);

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            addLog(
                $conn,
                $_SESSION['UserID'] ?? null,
                'Create building',
                'Buildings',
                "Tạo tòa nhà ID={$newId} - Tên: {$old['BuildingName']}",
                'activity'
            );
            $okMsg = "Tạo tòa nhà thành công (ID #{$newId}).";
            // Xóa giá trị cũ sau khi thành công
            $old = ['BuildingName' => '', 'Description' => '', 'Floors' => ''];
        } else {
            $errors[] = 'Lỗi khi lưu dữ liệu. Vui lòng thử lại.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Tạo tòa nhà mới | Hệ thống Ký túc xá</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
  <link rel="stylesheet" href="/assets/css/staff/buildings/building_create.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
<div class="building-create-container">
    <!-- Header Section -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-title">
                <div class="title-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <h1>Tạo tòa nhà mới</h1>
                    <p>Thêm tòa nhà mới vào hệ thống ký túc xá</p>
                </div>
            </div>
            <div class="header-actions">
                <a class="btn btn-secondary" href="building_list.php">
                    <i class="fas fa-list"></i>
                    <span>Danh sách tòa nhà</span>
                </a>
                <a class="btn btn-ghost" href="../../dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Trang quản trị</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h4>Có lỗi xảy ra</h4>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($okMsg)): ?>
        <div class="alert alert-success">
            <div class="alert-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="alert-content">
                <h4>Thành công!</h4>
                <p><?= htmlspecialchars($okMsg) ?></p>
            </div>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Main Form -->
    <div class="form-container">
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Thông tin tòa nhà</h2>
                <p>Nhập đầy đủ thông tin về tòa nhà mới</p>
            </div>

            <form method="post" action="" class="building-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-grid">
                    <!-- Building Name -->
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
                                placeholder="Ví dụ: Tòa A, Nhà B, Khu C..."
                                maxlength="100"
                                required
                                value="<?= htmlspecialchars($old['BuildingName']) ?>"
                            >
                        </div>
                        <div class="input-help">Tối đa 100 ký tự. Tên phải là duy nhất trong hệ thống.</div>
                    </div>

                    <!-- Number of Floors -->
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
                                placeholder="Ví dụ: 5"
                                value="<?= htmlspecialchars($old['Floors']) ?>"
                            >
                        </div>
                        <div class="input-help">Số tầng từ 1 đến 200.</div>
                    </div>

                    <!-- Description -->
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
                                placeholder="Ghi chú thêm về tòa nhà: vị trí, đặc điểm, tiện ích, ghi chú hạ tầng..."
                                rows="4"
                            ><?= htmlspecialchars($old['Description']) ?></textarea>
                        </div>
                        <div class="textarea-footer">
                            <span class="char-count" id="charCount">0/500 ký tự</span>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus-circle"></i>
                        <span>Tạo tòa nhà</span>
                    </button>
                    <a href="building_list.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i>
                        <span>Hủy bỏ</span>
                    </a>
                    <button type="reset" class="btn btn-ghost btn-lg">
                        <i class="fas fa-undo"></i>
                        <span>Đặt lại</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Tips Sidebar -->
        <div class="tips-sidebar">
            <div class="tips-card">
                <div class="tips-header">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Mẹo nhập liệu</h3>
                </div>
                <div class="tips-content">
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="tip-text">
                            <strong>Tên tòa nhà</strong>
                            <p>Nên đặt tên ngắn gọn, dễ nhớ và duy nhất trong hệ thống</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="tip-text">
                            <strong>Số tầng</strong>
                            <p>Xác nhận chính xác số tầng để hệ thống tạo phòng tự động</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                        <div class="tip-text">
                            <strong>Mô tả</strong>
                            <p>Ghi chú các thông tin hữu ích về vị trí và đặc điểm tòa nhà</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-header">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Thống kê nhanh</h3>
                </div>
                <div class="stats-content">
                    <div class="stat-item">
                        <span class="stat-label">Tổng tòa nhà</span>
                        <span class="stat-value"><?= getTotalBuildings($conn) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Tổng phòng</span>
                        <span class="stat-value"><?= getTotalRooms($conn) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Phòng trống</span>
                        <span class="stat-value"><?= getAvailableRooms($conn) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counter for description
    const descriptionTextarea = document.getElementById('Description');
    const charCount = document.getElementById('charCount');
    
    if (descriptionTextarea && charCount) {
        // Update character count
        function updateCharCount() {
            const length = descriptionTextarea.value.length;
            charCount.textContent = `${length}/500 ký tự`;
            
            if (length > 450) {
                charCount.style.color = '#ef4444';
            } else if (length > 400) {
                charCount.style.color = '#f59e0b';
            } else {
                charCount.style.color = '#64748b';
            }
        }
        
        // Initial update
        updateCharCount();
        
        // Update on input
        descriptionTextarea.addEventListener('input', updateCharCount);
    }
    
    // Form validation enhancement
    const form = document.querySelector('.building-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const buildingName = document.getElementById('BuildingName').value.trim();
            const floors = document.getElementById('Floors').value;
            
            if (!buildingName) {
                e.preventDefault();
                showError('Vui lòng nhập tên tòa nhà');
                return;
            }
            
            if (!floors || floors < 1 || floors > 200) {
                e.preventDefault();
                showError('Số tầng phải từ 1 đến 200');
                return;
            }
        });
    }
    
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi nhập liệu',
            text: message,
            confirmButtonColor: '#ef4444'
        });
    }
    
    // Add loading state to submit button
    const submitBtn = form?.querySelector('button[type="submit"]');
    if (submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Đang xử lý...</span>';
            submitBtn.disabled = true;
        });
    }
});

// Helper functions for statistics (you'll need to implement these in PHP)
<?php
function getTotalBuildings($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM Buildings");
    return $result ? $result->fetch_assoc()['total'] : 0;
}

function getTotalRooms($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM Rooms");
    return $result ? $result->fetch_assoc()['total'] : 0;
}

function getAvailableRooms($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM Rooms WHERE Status = 'Trống'");
    return $result ? $result->fetch_assoc()['total'] : 0;
}
?>
</script>

</body>
</html>
