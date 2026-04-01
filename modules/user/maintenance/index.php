<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

// -------------------- LẤY THÔNG TIN SINH VIÊN VÀ PHÒNG --------------------
$userId = $_SESSION['UserID'] ?? 0;
$student = null;
$studentId = 0;
$studentName = 'Sinh viên';
$roomId = 0;
$roomName = '';

if ($userId > 0) {
    // Lấy StudentID
    $stmt = $conn->prepare("SELECT StudentID, FullName FROM Students WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $studentId = (int)$student['StudentID'];
        $studentName = $student['FullName'];
    }
    $stmt->close();

    // Lấy phòng đang ở (qua hợp đồng hiệu lực)
    if ($studentId > 0) {
        $stmtRoom = $conn->prepare("
            SELECT r.RoomID, r.RoomNumber, b.BuildingName 
            FROM Contracts c
            JOIN Rooms r ON c.RoomID = r.RoomID
            JOIN Buildings b ON r.BuildingID = b.BuildingID
            WHERE c.StudentID = ? AND c.Status = 'Hiệu lực'
            LIMIT 1
        ");
        $stmtRoom->bind_param("i", $studentId);
        $stmtRoom->execute();
        $resRoom = $stmtRoom->get_result();
        if ($resRoom && $resRoom->num_rows > 0) {
            $roomData = $resRoom->fetch_assoc();
            $roomId = (int)$roomData['RoomID'];
            $roomName = $roomData['BuildingName'] . ' - ' . $roomData['RoomNumber'];
        }
        $stmtRoom->close();
    }
}

// -------------------- XỬ LÝ GỬI YÊU CẦU BẢO TRÌ --------------------
$success = false;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $studentId > 0) {
    if ($roomId === 0) {
        $errorMsg = "⚠️ Bạn hiện không thuộc phòng nào nên không thể gửi báo cáo sự cố phòng.";
    } else {
        $title     = trim($_POST['title'] ?? '');
        $content   = trim($_POST['content'] ?? '');
        $priority  = $_POST['priority'] ?? 'Bình thường';
        $imagePath = null;
        $status    = 'Chờ xử lý';

        $validPriorities = ['Thấp', 'Bình thường', 'Cao', 'Khẩn cấp'];
        if (!in_array($priority, $validPriorities)) {
            $priority = 'Bình thường';
        }

        if ($title === '' || $content === '') {
            $errorMsg = "⚠️ Vui lòng nhập đầy đủ tiêu đề và chi tiết sự cố.";
        }

        // Upload ảnh (nếu có)
        if (empty($errorMsg) && !empty($_FILES['image']['name'])) {
            $uploadDir = '../../assets/uploads/maintenance/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
            $targetFile = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imagePath = 'assets/uploads/maintenance/' . $filename;
            } else {
                $errorMsg = "⚠️ Không thể tải ảnh lên hệ thống.";
            }
        }

        // Lưu vào DB
        if (empty($errorMsg)) {
            $sql = "INSERT INTO maintenancerequests (StudentID, RoomID, Title, Description, ImagePath, Status, Priority, CreatedAt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $errorMsg = "❌ Lỗi hệ thống: " . $conn->error;
            } else {
                $stmt->bind_param("iisssss", $studentId, $roomId, $title, $content, $imagePath, $status, $priority);

                if ($stmt->execute()) {
                    $success = true;
                    $requestId = $stmt->insert_id;

                    // Tạo thông báo cho Staff (Notification system giả định)
                    $notiTitle = "Báo cáo sự cố mới từ phòng $roomName";
                    $notiMsg = mb_substr($content, 0, 100, 'UTF-8') . '...';
                    $notiSql = "INSERT INTO Notifications (StudentID, Title, Message, CreatedAt, IsRead) VALUES (?, ?, ?, NOW(), 0)";
                    $notiStmt = $conn->prepare($notiSql);
                    if ($notiStmt) {
                        // Lưu ý: Notifications hiện tại link tới StudentID. 
                        // Bạn có AdminNotification nhưng Student gửi thì Admin nhận.
                        // Nếu dùng bảng Notifications chung, có thể gửi cho chính mình hoặc lưu log.
                        $notiStmt->bind_param("iss", $studentId, $notiTitle, $notiMsg);
                        $notiStmt->execute();
                        $notiStmt->close();
                    }

                    // Log activity
                    addLog(
                        $conn,
                        $_SESSION['UserID'] ?? null,
                        'Create maintenance request',
                        'Maintenance',
                        "Sinh viên tạo báo cáo xử cố ID={$requestId} phòng {$roomName}",
                        'activity'
                    );
                } else {
                    $errorMsg = "❌ Lỗi thực thi SQL: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Xử lý Xóa/Hủy yêu cầu nếu trạng thái là Chờ xử lý
if (isset($_GET['cancel_id']) && $studentId > 0) {
    $cancelId = (int)$_GET['cancel_id'];
    $stmtDel = $conn->prepare("UPDATE maintenancerequests SET Status = 'Đã hủy' WHERE RequestID = ? AND StudentID = ? AND Status = 'Chờ xử lý'");
    $stmtDel->bind_param("ii", $cancelId, $studentId);
    if ($stmtDel->execute() && $stmtDel->affected_rows > 0) {
        header("Location: index.php?msg=canceled");
        exit;
    }
    $stmtDel->close();
}


// -------------------- LẤY DANH SÁCH LỊCH SỬ --------------------
$requests = false;
if ($studentId > 0) {
    $requests = $conn->query("
        SELECT RequestID, Title, Description, ImagePath, Status, Priority, CreatedAt, UpdatedAt
        FROM maintenancerequests
        WHERE StudentID = $studentId
        ORDER BY CreatedAt DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo trì & Sửa chữa - Ký túc xá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/maintenance.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .priority {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #eee;
        }
        .priority.khẩn-cấp { background: #fee2e2; color: #b91c1c; }
        .priority.cao { background: #fef3c7; color: #b45309; }
        .priority.bình-thường { background: #e0f2fe; color: #0369a1; }
        .priority.thấp { background: #f3f4f6; color: #374151; }
        
        .status.chờ-xử-lý { background: #fef3c7; color: #92400e; }
        .status.đang-xử-lý { background: #dbeafe; color: #1e40af; }
        .status.đã-hoàn-thành { background: #d1fae5; color: #065f46; }
        .status.đã-hủy { background: #f3f4f6; color: #6b7280; text-decoration: line-through; }
        
        .btn-cancel {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .btn-cancel:hover { text-decoration: underline; }
    </style>
</head>

<body>
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-tools"></i> Báo cáo sự cố phòng ở</h2>
            <p>Phòng hiện tại: <strong><?= $roomName ? htmlspecialchars($roomName) : 'Chưa có phòng' ?></strong>. Vui lòng gửi yêu cầu nếu thiết bị trong phòng gặp vấn đề.</p>
        </div>

        <?php if ($roomId > 0): ?>
        <!-- Form gửi báo cáo -->
        <div class="maintenance-form">
            <h3><i class="fas fa-hammer"></i> Tạo yêu cầu sửa chữa</h3>
            <form method="POST" enctype="multipart/form-data" class="form-maintenance" id="maintenanceForm">
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Vấn đề gặp phải (Tóm tắt)</label>
                    <input type="text" id="title" name="title" required
                        placeholder="Ví dụ: Đèn tuýp bị cháy, Quạt trần kêu to...">
                </div>

                <div class="form-group">
                    <label for="priority"><i class="fas fa-exclamation-circle"></i> Mức độ nghiêm trọng</label>
                    <select id="priority" name="priority" style="padding: 1rem; border: 2px solid var(--gray-light); border-radius: var(--border-radius); font-size: 1rem;">
                        <option value="Thấp">Thấp</option>
                        <option value="Bình thường" selected>Bình thường</option>
                        <option value="Cao">Cao</option>
                        <option value="Khẩn cấp">Khẩn cấp (Cần xử lý ngay)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="content"><i class="fas fa-align-left"></i> Mô tả chi tiết</label>
                    <textarea id="content" name="content" rows="4" required
                        placeholder="Mô tả cụ thể vị trí và tình trạng hỏng hóc..."></textarea>
                </div>

                <div class="form-group">
                    <label for="image"><i class="fas fa-image"></i> Ảnh sự cố hiện tại</label>
                    <div class="file-upload">
                        <input type="file" id="image" name="image" accept="image/*">
                        <label for="image" class="file-upload-label">
                            <i class="fas fa-upload"></i> Chọn ảnh (Tùy chọn)
                        </label>
                    </div>
                    <div class="file-name" id="fileName">Chưa có file nào được chọn</div>
                </div>

                <button type="submit" class="btn btn-rgb mt-2">
                    <i class="fas fa-paper-plane"></i> Gửi Yêu Cầu
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bed fa-3x" style="color:var(--gray);"></i>
            <p style="margin-top:1rem;">Bạn chưa được phân phòng, không thể gửi yêu cầu bảo trì.</p>
        </div>
        <?php endif; ?>

        <hr>

        <!-- Lịch sử yêu cầu -->
        <div class="maintenance-history">
            <h3><i class="fas fa-clipboard-list"></i> Yêu cầu của bạn</h3>

            <?php if ($requests && $requests->num_rows > 0): ?>
                <div class="maintenance-list">
                    <?php while ($req = $requests->fetch_assoc()):
                        $statusClass = strtolower(str_replace(' ', '-', $req['Status']));
                        $priorityClass = strtolower(str_replace(' ', '-', $req['Priority']));
                    ?>
                        <div class="maintenance-card">
                            <div class="maintenance-header">
                                <h4><?= htmlspecialchars($req['Title']) ?></h4>
                                <div style="display:flex; gap: 10px; flex-wrap:wrap;">
                                    <span class="priority <?= $priorityClass ?>"><i class="fas fa-flag"></i> Mức: <?= htmlspecialchars($req['Priority']) ?></span>
                                    <span class="status <?= $statusClass ?>">
                                        <i class="fas <?= $req['Status'] === 'Đã hoàn thành' ? 'fa-check' : ($req['Status'] === 'Đang xử lý' ? 'fa-spinner' : 'fa-clock') ?>"></i>
                                        <?= htmlspecialchars($req['Status']) ?>
                                    </span>
                                </div>
                            </div>

                            <p><?= nl2br(htmlspecialchars($req['Description'])) ?></p>

                            <?php if (!empty($req['ImagePath'])): ?>
                                <div class="maintenance-image">
                                    <img src="<?= $base ?><?= htmlspecialchars($req['ImagePath']) ?>"
                                         class="maintenance-img"
                                         style="width:120px; border-radius:8px; cursor:pointer;"
                                         onclick="openImageModal(this.src)">
                                </div>
                            <?php endif; ?>

                            <div class="maintenance-footer" style="margin-top: 15px; display:flex; justify-content:space-between; align-items:center;">
                                <small>
                                    <i class="far fa-clock"></i>
                                    Gửi: <?= date('H:i d/m/Y', strtotime($req['CreatedAt'])) ?>
                                    <?php if($req['UpdatedAt'] != $req['CreatedAt']): ?>
                                    - Cập nhật: <?= date('d/m/Y', strtotime($req['UpdatedAt'])) ?>
                                    <?php endif; ?>
                                </small>
                                
                                <?php if ($req['Status'] === 'Chờ xử lý'): ?>
                                    <button class="btn-cancel" onclick="confirmCancel(<?= $req['RequestID'] ?>)"><i class="fas fa-times"></i> Hủy yêu cầu</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fa-3x" style="color:var(--gray-light);"></i>
                    <p style="margin-top:1rem; color:var(--gray);">Bạn chưa gửi yêu cầu sửa chữa nào.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Modal xem ảnh -->
    <div id="imageModal" class="modal" style="display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8);">
        <span class="close" style="position:absolute; top:20px; right:30px; color:#fff; font-size:40px; cursor:pointer;">&times;</span>
        <img class="modal-content" id="modalImage" style="margin:auto; display:block; max-width:90%; max-height:90%; top:50%; position:relative; transform:translateY(-50%);">
    </div>

    <script>
        document.getElementById('image')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Chưa có file nào được chọn';
            document.getElementById('fileName').textContent = fileName;
        });

        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        const closeBtn = document.querySelector('.close');

        function openImageModal(src) {
            modal.style.display = 'block';
            modalImg.src = src;
        }

        if (closeBtn) closeBtn.onclick = () => modal.style.display = 'none';
        window.onclick = function(event) {
            if (event.target === modal) modal.style.display = 'none';
        };

        function confirmCancel(id) {
            Swal.fire({
                title: 'Hủy yêu cầu?',
                text: "Bạn có chắc chắn muốn hủy báo cáo này?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Có, Hủy!',
                cancelButtonText: 'Đóng'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `index.php?cancel_id=${id}`;
                }
            })
        }
    </script>

    <?php if ($success): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: 'Đã gửi yêu cầu sửa chữa tới Ban quản lý.',
                confirmButtonColor: '#4361ee'
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>
    <?php elseif (!empty($errorMsg)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                text: '<?= addslashes($errorMsg) ?>',
                confirmButtonColor: '#e63946'
            });
        </script>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'canceled'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Đã hủy',
                text: 'Yêu cầu của bạn đã được hủy thành công.',
                timer: 2000,
                showConfirmButton: false
            });
        </script>
    <?php endif; ?>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>
