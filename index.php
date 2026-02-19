<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// --- Lấy thông tin người dùng hiện tại ---
$userId = $_SESSION['UserID'] ?? null;
$hasRoom = false;
$info = null;

if ($userId) {
    $stmt = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.StudentCode, 
               b.BuildingName, r.RoomNumber, r.RoomType, 
               c.StartDate, c.EndDate
        FROM Users u
        JOIN Students s ON s.UserID = u.UserID
        LEFT JOIN Contracts c ON c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
        LEFT JOIN Rooms r ON r.RoomID = c.RoomID
        LEFT JOIN Buildings b ON b.BuildingID = r.BuildingID
        WHERE u.UserID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $info = $result->fetch_assoc();
    $stmt->close();

    $hasRoom = !empty($info['RoomNumber']);
}

// --- Xử lý tìm kiếm phòng ---
$searchBuilding = $_GET['building'] ?? '';
$searchRoomType = $_GET['room_type'] ?? '';
$searchMaxPrice = $_GET['max_price'] ?? '';

// Lấy danh sách tòa nhà cho dropdown
$buildingsQuery = $conn->query("SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName ASC");
$buildings = [];
while ($b = $buildingsQuery->fetch_assoc()) {
    $buildings[] = $b;
}
include 'includes/header.php';
?>

    <main>

        <?php if (!$userId): ?>
            <!-- ===========================================================
         🔹 GIAO DIỆN KHÁCH — CHƯA ĐĂNG NHẬP
    ============================================================ -->

            <section class="hero rgb-hero">
                <div class="hero-content">
                    <h2 class="rgb-text">Chào mừng đến với Ký túc xá Sinh viên</h2>
                    <p>Môi trường sống lý tưởng với đầy đủ tiện nghi, an ninh và cộng đồng sinh viên năng động</p>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn btn-rgb">Đăng nhập ngay</a>
                        <a href="#room-explorer" class="btn btn-rgb-outline">Tham khảo phòng ngay</a>
                    </div>
                </div>
            </section>


            <!-- ===========================================================
         🔹 DANH SÁCH PHÒNG TRỐNG (KHÁCH)
    ============================================================ -->
            <section id="room-explorer" class="available-rooms">
                <div class="section-title">
                    <h2>🏠 Phòng Trống Hiện Có</h2>
                    <p>Khám phá các phòng còn trống trong ký túc xá</p>
                </div>

                <!-- Form tìm kiếm phòng -->
                <div class="search-form-container">
                    <form method="GET" action="" class="room-search-form" id="roomFilterForm">
                        <div class="search-filters">
                            <div class="filter-item">
                                <label for="building"><i class="fas fa-building"></i> Tòa nhà</label>
                                <select name="building" id="building">
                                    <option value=""> Tất cả tòa nhà </option>
                                    <?php foreach ($buildings as $building): ?>
                                        <option value="<?= $building['BuildingID'] ?>"
                                            <?= $searchBuilding == $building['BuildingID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($building['BuildingName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label for="room_type"><i class="fas fa-venus-mars"></i> Loại phòng</label>
                                <select name="room_type" id="room_type">
                                    <option value=""> Tất cả </option>
                                    <option value="Nam" <?= $searchRoomType == 'Nam' ? 'selected' : '' ?>>♂️ Nam</option>
                                    <option value="Nữ" <?= $searchRoomType == 'Nữ' ? 'selected' : '' ?>>♀️ Nữ</option>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label for="max_price_range">
                                    <i class="fas fa-money-bill-wave"></i> Giá tối đa:
                                    <span id="max_price_text">
                                        <?= $searchMaxPrice ? number_format($searchMaxPrice) . ' đ' : 'Không giới hạn' ?>
                                    </span>
                                </label>

                                <input type="range"
                                    id="max_price_range"
                                    name="max_price"
                                    min="0" max="3000000" step="50000"
                                    value="<?= $searchMaxPrice ?: 0 ?>">
                            </div>

                            <!-- Có thể giữ nút Đặt lại nếu thích -->
                            <div class="filter-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Đặt lại
                                </a>
                            </div>

                            <!-- Submit ẩn cho trình duyệt cũ / enter -->
                            <button type="submit" style="display:none"></button>
                        </div>
                    </form>
                </div>

                <div class="rooms-grid">
                    <?php
                    // Xây dựng câu query với điều kiện tìm kiếm
                    $whereClauses = ["r.Status = 'Trống'"];
                    $params = [];
                    $types = "";

                    if (!empty($searchBuilding)) {
                        $whereClauses[] = "b.BuildingID = ?";
                        $params[] = $searchBuilding;
                        $types .= "i";
                    }

                    if (!empty($searchRoomType)) {
                        $whereClauses[] = "r.RoomType = ?";
                        $params[] = $searchRoomType;
                        $types .= "s";
                    }

                    if (!empty($searchMaxPrice)) {
                        $whereClauses[] = "r.RoomPrice <= ?";
                        $params[] = $searchMaxPrice;
                        $types .= "d";
                    }

                    $whereSQL = implode(" AND ", $whereClauses);

                    $searchSQL = "
                        SELECT r.*, b.BuildingName,
                               (SELECT COUNT(*) FROM Contracts c WHERE c.RoomID = r.RoomID AND c.Status = 'Hiệu lực') AS CurrentOccupants,
                               (r.Capacity - (SELECT COUNT(*) FROM Contracts c WHERE c.RoomID = r.RoomID AND c.Status = 'Hiệu lực')) AS AvailableSlots
                        FROM Rooms r
                        JOIN Buildings b ON b.BuildingID = r.BuildingID
                        WHERE $whereSQL
                        ORDER BY r.RoomPrice ASC
                        LIMIT 20
                    ";

                    if (!empty($params)) {
                        $stmt = $conn->prepare($searchSQL);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $availableRooms = $stmt->get_result();
                    } else {
                        $availableRooms = $conn->query($searchSQL);
                    }

                    if ($availableRooms->num_rows > 0):
                        while ($room = $availableRooms->fetch_assoc()):
                            $roomTypeIcon = $room['RoomType'] == 'Nam' ? '♂️' : ($room['RoomType'] == 'Nữ' ? '♀️' : '⚧');

                            $roomImage = !empty($room['ImagePath'])
                                ? $room['ImagePath']
                                : 'assets/img/room-default.jpg';
                    ?>
                            <div class="room-card">
                                <div class="room-header">
                                    <span class="room-badge"><?= $roomTypeIcon ?> <?= $room['RoomType'] ?></span>
                                    <span class="price-tag"><?= number_format($room['RoomPrice']) ?> đ/tháng</span>
                                </div>

                                <div class="room-body">

                                    <img src="<?= htmlspecialchars($roomImage) ?>"
                                        onerror="this.onerror=null;this.src='assets/img/room-default.jpg';"
                                        alt="Phòng <?= htmlspecialchars($room['RoomNumber']) ?>"
                                        class="room-thumb">

                                    <h3>Phòng <?= htmlspecialchars($room['RoomNumber']) ?></h3>
                                    <p class="building-name">🏢 <?= htmlspecialchars($room['BuildingName']) ?></p>

                                    <div class="room-details">
                                        <div class="detail-item">
                                            <i class="fas fa-users"></i>
                                            <span>Còn <?= (int)$room['AvailableSlots'] ?>/<?= (int)$room['Capacity'] ?> chỗ</span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-user-check"></i>
                                            <span>Đã có <?= (int)$room['CurrentOccupants'] ?> sinh viên ở</span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-expand"></i>
                                            <span><?= intval($room['Capacity']) * 4 ?> m²</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="room-footer">
                                    <?php if (!$hasRoom): ?>
                                        <a href="modules/user/rooms/room_detail.php?id=<?= $room['RoomID'] ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i> Xem chi tiết
                                        </a>

                                        <a href="modules/user/rooms/register_room.php" class="btn btn-rgb">
                                            <i class="fas fa-edit"></i> Đăng ký phòng
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="fas fa-check"></i> Bạn đã có phòng
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php endwhile;
                    else: ?>

                        <div class="no-rooms center-block">
                            <i class="fas fa-bed fa-3x"></i>
                            <h3>Hiện không có phòng trống</h3>
                        </div>

                    <?php endif; ?>
                </div>
            </section>


            <!-- Dịch vụ -->
            <section class="features">
                <div class="section-title">
                    <h2>Dịch vụ của chúng tôi</h2>
                </div>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-home"></i></div>
                        <h3>Phòng ở tiện nghi</h3>
                        <p>Phòng nội trú sạch đẹp, nội thất đầy đủ.</p>
                        <a href="login.php" class="learn-more">Đăng ký phòng →</a>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-file-invoice"></i></div>
                        <h3>Hóa đơn rõ ràng</h3>
                        <p>Dễ dàng kiểm tra điện nước, thanh toán minh bạch.</p>
                        <a href="login.php" class="learn-more">Xem hóa đơn →</a>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-comments"></i></div>
                        <h3>Phản ánh & Góp ý</h3>
                        <p>Hệ thống phản ánh nhanh chóng.</p>
                        <a href="login.php" class="learn-more">Gửi phản ánh →</a>
                    </div>
                </div>
            </section>

        <?php else: ?>

            <!-- ===========================================================
         🔹 GIAO DIỆN SINH VIÊN — ĐÃ ĐĂNG NHẬP
    ============================================================ -->

            <section class="dashboard">
                <div class="section-title">
                    <?php
                    $fullName = $info['FullName'] ?? ($_SESSION['FullName'] ?? 'Bạn');
                    ?>
                    <h2>👋 Xin chào, <?= htmlspecialchars($fullName) ?></h2>
                </div>

                <div class="student-info">
                    <?php if ($hasRoom): ?>
                        <div class="info-grid">
                            <div class="info-item"><i class="fas fa-id-card"></i>
                                <div><strong>Mã sinh viên</strong>
                                    <p><?= $info['StudentCode'] ?></p>
                                </div>
                            </div>

                            <div class="info-item"><i class="fas fa-door-open"></i>
                                <div><strong>Phòng hiện tại</strong>
                                    <p><?= $info['RoomNumber'] ?> (<?= $info['RoomType'] ?>)</p>
                                </div>
                            </div>

                            <div class="info-item"><i class="fas fa-building"></i>
                                <div><strong>Tòa nhà</strong>
                                    <p><?= $info['BuildingName'] ?></p>
                                </div>
                            </div>

                            <div class="info-item"><i class="fas fa-calendar-alt"></i>
                                <div><strong>Thời hạn hợp đồng</strong>
                                    <p><?= date('d/m/Y', strtotime($info['StartDate'])) ?> → <?= date('d/m/Y', strtotime($info['EndDate'])) ?></p>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="no-contract">
                            <i class="fas fa-home fa-3x"></i>
                            <h3>Bạn chưa có hợp đồng phòng</h3>
                            <p>Đăng ký phòng để bắt đầu sinh sống tại KTX</p>
                            <a href="modules/user/rooms/register_room.php" class="btn btn-rgb">Đăng ký phòng ngay</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>


            <!-- ===========================================================
         🔹 PHÒNG TRỐNG CHO SINH VIÊN ĐÃ ĐĂNG NHẬP
    ============================================================ -->
            <section class="available-rooms">
                <div class="section-title">
                    <h2>🚀 Phòng Trống Hiện Có</h2>
                </div>

                <!-- Form tìm kiếm phòng -->
                <div class="search-form-container">
                    <form method="GET" action="" class="room-search-form" id="roomFilterForm">
                        <div class="search-filters">
                            <div class="filter-item">
                                <label for="building"><i class="fas fa-building"></i> Tòa nhà</label>
                                <select name="building" id="building">
                                    <option value=""> Tất cả tòa nhà </option>
                                    <?php foreach ($buildings as $building): ?>
                                        <option value="<?= $building['BuildingID'] ?>"
                                            <?= $searchBuilding == $building['BuildingID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($building['BuildingName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label for="room_type"><i class="fas fa-venus-mars"></i> Loại phòng</label>
                                <select name="room_type" id="room_type">
                                    <option value=""> Tất cả </option>
                                    <option value="Nam" <?= $searchRoomType == 'Nam' ? 'selected' : '' ?>>♂️ Nam</option>
                                    <option value="Nữ" <?= $searchRoomType == 'Nữ' ? 'selected' : '' ?>>♀️ Nữ</option>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label for="max_price_range">
                                    <i class="fas fa-money-bill-wave"></i> Giá tối đa:
                                    <span id="max_price_text">
                                        <?= $searchMaxPrice ? number_format($searchMaxPrice) . ' đ' : 'Không giới hạn' ?>
                                    </span>
                                </label>

                                <input type="range"
                                    id="max_price_range"
                                    name="max_price"
                                    min="0" max="3000000" step="50000"
                                    value="<?= $searchMaxPrice ?: 0 ?>">
                            </div>

                            <!-- Có thể giữ nút Đặt lại nếu thích -->
                            <div class="filter-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Đặt lại
                                </a>
                            </div>

                            <!-- Submit ẩn cho trình duyệt cũ / enter -->
                            <button type="submit" style="display:none"></button>
                        </div>
                    </form>
                </div>


                <div class="rooms-grid">

                    <?php
                    // Xây dựng câu query với điều kiện tìm kiếm cho sinh viên
                    $whereClauses2 = ["r.Status = 'Trống'"];
                    $params2 = [];
                    $types2 = "";

                    if (!empty($searchBuilding)) {
                        $whereClauses2[] = "b.BuildingID = ?";
                        $params2[] = $searchBuilding;
                        $types2 .= "i";
                    }

                    if (!empty($searchRoomType)) {
                        $whereClauses2[] = "r.RoomType = ?";
                        $params2[] = $searchRoomType;
                        $types2 .= "s";
                    }

                    if (!empty($searchMaxPrice)) {
                        $whereClauses2[] = "r.RoomPrice <= ?";
                        $params2[] = $searchMaxPrice;
                        $types2 .= "d";
                    }

                    $whereSQL2 = implode(" AND ", $whereClauses2);

                    $searchSQL2 = "
                        SELECT 
                            r.*, 
                            b.BuildingName,
                            (
                                SELECT COUNT(*) 
                                FROM Contracts c 
                                WHERE c.RoomID = r.RoomID AND c.Status = 'Hiệu lực'
                            ) AS CurrentOccupants,
                            (
                                r.Capacity -
                                (
                                    SELECT COUNT(*) 
                                    FROM Contracts c 
                                    WHERE c.RoomID = r.RoomID AND c.Status = 'Hiệu lực'
                                )
                            ) AS AvailableSlots
                        FROM Rooms r
                        JOIN Buildings b ON b.BuildingID = r.BuildingID
                        WHERE $whereSQL2
                        ORDER BY r.RoomPrice ASC
                        LIMIT 6
                    ";

                    if (!empty($params2)) {
                        $stmt = $conn->prepare($searchSQL2);
                        $stmt->bind_param($types2, ...$params2);
                        $stmt->execute();
                        $availableRooms = $stmt->get_result();
                    } else {
                        $availableRooms = $conn->query($searchSQL2);
                    }

                    if ($availableRooms->num_rows > 0):
                        while ($room = $availableRooms->fetch_assoc()):
                            $roomTypeIcon = $room['RoomType'] == 'Nam' ? '♂️' : ($room['RoomType'] == 'Nữ' ? '♀️' : '⚧');

                            $roomImage = !empty($room['ImagePath'])
                                ? $room['ImagePath']
                                : 'assets/img/room-default.jpg';
                    ?>
                            <div class="room-card">
                                <div class="room-header">
                                    <span class="room-badge"><?= $roomTypeIcon ?> <?= $room['RoomType'] ?></span>
                                    <span class="price-tag"><?= number_format($room['RoomPrice']) ?> đ/tháng</span>
                                </div>

                                <div class="room-body">
                                    <img src="<?= htmlspecialchars($roomImage) ?>"
                                        onerror="this.onerror=null;this.src='assets/img/room-default.jpg';"
                                        alt="Phòng <?= $room['RoomNumber'] ?>"
                                        class="room-thumb">

                                    <h3>Phòng <?= $room['RoomNumber'] ?></h3>
                                    <p class="building-name">🏢 <?= $room['BuildingName'] ?></p>

                                    <div class="room-details">
                                        <div class="detail-item">
                                            <i class="fas fa-users"></i>
                                            <span><?= $room['AvailableSlots'] ?>/<?= $room['Capacity'] ?> chỗ</span>
                                        </div>

                                        <div class="detail-item">
                                            <i class="fas fa-user-check"></i>
                                            <span>Đã có <?= (int)$room['CurrentOccupants'] ?> sinh viên ở</span>
                                        </div>

                                        <div class="detail-item">
                                            <i class="fas fa-expand"></i>
                                            <span><?= $room['Capacity'] * 4 ?> m²</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="room-footer">
                                    <?php if (!$hasRoom): ?>
                                        <a href="modules/user/rooms/room_detail.php?id=<?= $room['RoomID'] ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i> Xem chi tiết
                                        </a>

                                        <a href="modules/user/rooms/register_room.php" class="btn btn-rgb">
                                            <i class="fas fa-edit"></i> Đăng ký phòng
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="fas fa-check"></i> Bạn đã có phòng
                                        </button>
                                    <?php endif; ?>
                                </div>

                            </div>

                        <?php endwhile;
                    else: ?>

                        <div class="no-rooms center-block">
                            <i class="fas fa-bed fa-3x"></i>
                            <h3>Hiện không có phòng trống</h3>
                        </div>

                    <?php endif; ?>
                </div>
            </section>

        <?php endif; ?>

        <!-- ===========================================================
         🔹 THÔNG BÁO NỔI BẬT
    ============================================================ -->
        <section class="announcements-section">
            <div class="section-title">
                <h2><i class="fas fa-bullhorn"></i> Thông báo nổi bật</h2>
                <p>Cập nhật tin tức mới nhất từ ban quản lý ký túc xá</p>
            </div>

            <div class="announcements-grid">
                <?php
                // Lấy 3 thông báo mới nhất từ bảng announcements
                $announcementsQuery = $conn->query("
                SELECT AnnouncementID, Title, Content, DatePosted, PostedBy
                FROM announcements
                WHERE DatePosted <= NOW()
                ORDER BY DatePosted DESC
                LIMIT 3
            ");

                if ($announcementsQuery && $announcementsQuery->num_rows > 0):
                    while ($announcement = $announcementsQuery->fetch_assoc()):
                        // Tự động phân loại category dựa vào title
                        $title = $announcement['Title'];
                        if (stripos($title, 'khẩn cấp') !== false || stripos($title, 'cảnh báo') !== false) {
                            $category = 'Khẩn cấp';
                            $categoryIcon = 'fa-exclamation-triangle';
                            $categoryColor = '#e63946';
                        } elseif (stripos($title, 'sự kiện') !== false || stripos($title, 'hoạt động') !== false) {
                            $category = 'Sự kiện';
                            $categoryIcon = 'fa-calendar-star';
                            $categoryColor = '#f4a261';
                        } elseif (stripos($title, 'bảo trì') !== false || stripos($title, 'sửa chữa') !== false || stripos($title, 'cúp điện') !== false) {
                            $category = 'Bảo trì';
                            $categoryIcon = 'fa-wrench';
                            $categoryColor = '#7209b7';
                        } else {
                            $category = 'Thông báo chung';
                            $categoryIcon = 'fa-info-circle';
                            $categoryColor = '#4895ef';
                        }
                ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <span class="announcement-category" style="background: <?= $categoryColor ?>15; color: <?= $categoryColor ?>;">
                                    <i class="fas <?= $categoryIcon ?>"></i>
                                    <?= htmlspecialchars($category) ?>
                                </span>
                                <span class="announcement-date">
                                    <i class="far fa-clock"></i>
                                    <?= date('d/m/Y', strtotime($announcement['DatePosted'])) ?>
                                </span>
                            </div>
                            <h3><?= htmlspecialchars($announcement['Title']) ?></h3>
                            <p><?= htmlspecialchars(mb_substr($announcement['Content'], 0, 120)) ?>...</p>
                            <a href="/modules/user/accesslogs.php?view=<?= (int)$announcement['AnnouncementID'] ?>" class="read-more">
                                Xem chi tiết <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php
                    endwhile;
                else:
                    ?>
                    <div class="no-announcements">
                        <i class="fas fa-info-circle fa-2x"></i>
                        <p>Hiện chưa có thông báo mới</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ===========================================================
         🔹 FAQ ACCORDION
    ============================================================ -->
        <section class="faq-section">
            <div class="section-title">
                <h2><i class="fas fa-question-circle"></i> Câu hỏi thường gặp</h2>
                <p>Giải đáp các thắc mắc về ký túc xá</p>
            </div>

            <div class="faq-container">
                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span><i class="fas fa-user-plus"></i> Làm thế nào để đăng ký phòng ở KTX?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <p><strong>Bước 1:</strong> Đăng nhập vào hệ thống bằng tài khoản sinh viên</p>
                        <p><strong>Bước 2:</strong> Vào mục "Đăng ký phòng" hoặc chọn phòng trống trên trang chủ</p>
                        <p><strong>Bước 3:</strong> Điền đầy đủ thông tin và chọn phòng phù hợp</p>
                        <p><strong>Bước 4:</strong> Chờ ban quản lý duyệt yêu cầu (1-3 ngày làm việc)</p>
                        <p><strong>Bước 5:</strong> Sau khi được duyệt, thanh toán tiền cọc và nhận phòng</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span><i class="fas fa-money-bill-wave"></i> Chính sách thanh toán và hoàn trả như thế nào?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <p><strong>Tiền phòng:</strong> Thanh toán theo tháng, hạn chót ngày 5 hàng tháng</p>
                        <p><strong>Tiền cọc:</strong> Bằng 1 tháng tiền phòng, hoàn trả khi kết thúc hợp đồng</p>
                        <p><strong>Điện nước:</strong> Tính theo đồng hồ thực tế, thanh toán cùng tiền phòng</p>
                        <p><strong>Phương thức:</strong> VNPay, MoMo, Chuyển khoản ngân hàng</p>
                        <p><strong>Hoàn trả:</strong> Tiền cọc được hoàn trong vòng 7 ngày sau khi trả phòng (trừ các khoản phạt nếu có)</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span><i class="fas fa-file-contract"></i> Nội quy KTX có những điều gì?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <p><strong>Giờ giấc:</strong> Đóng cửa lúc 23:00, mở cửa 5:00 sáng</p>
                        <p><strong>Khách thăm:</strong> Đăng ký trước, không qua đêm</p>
                        <p><strong>Vệ sinh:</strong> Giữ gìn vệ sinh phòng ở và khu vực chung</p>
                        <p><strong>Cấm:</strong> Sử dụng chất kích thích, gây ồn, nuôi động vật, nấu ăn trong phòng</p>
                        <p><strong>Tài sản:</strong> Bảo quản tài sản KTX, bồi thường nếu làm hư hỏng</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span><i class="fas fa-tools"></i> Phòng có những tiện nghi gì?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <p><strong>Nội thất cơ bản:</strong> Giường, tủ quần áo, bàn học, ghế</p>
                        <p><strong>Điện tử:</strong> Quạt trần/điều hòa (tùy loại phòng), ổ cắm điện</p>
                        <p><strong>Vệ sinh:</strong> Nhà vệ sinh riêng/chung (tùy loại phòng)</p>
                        <p><strong>Internet:</strong> WiFi miễn phí tốc độ cao</p>
                        <p><strong>Tiện ích chung:</strong> Máy giặt, bàn ping pong, phòng tập gym, khu BBQ</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span><i class="fas fa-headset"></i> Liên hệ hỗ trợ khi cần?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <p><strong>Hotline:</strong> 1900-xxxx-xxx (24/7)</p>
                        <p><strong>Email:</strong> ktx@university.edu.vn</p>
                        <p><strong>Văn phòng:</strong> Tòa A - Tầng 1 (8:00 - 17:00 T2-T6)</p>
                        <p><strong>Online:</strong> Gửi phản ánh qua hệ thống hoặc chat với admin</p>
                        <p><strong>Khẩn cấp:</strong> Liên hệ bảo vệ tại cổng hoặc gọi 113</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button">
                        <span><i class="fas fa-sign-out-alt"></i> Quy trình trả phòng như thế nào?</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <p><strong>Bước 1:</strong> Thông báo trước ít nhất 15 ngày</p>
                        <p><strong>Bước 2:</strong> Thanh toán hết các khoản nợ (tiền phòng, điện, nước)</p>
                        <p><strong>Bước 3:</strong> Dọn dẹp phòng, trả chìa khóa</p>
                        <p><strong>Bước 4:</strong> Ban quản lý kiểm tra tình trạng phòng</p>
                        <p><strong>Bước 5:</strong> Nhận lại tiền cọc (nếu không có hư hỏng)</p>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <?php include 'includes/footer.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('roomFilterForm');
        if (!form) return;

        const building = document.getElementById('building');
        const roomType = document.getElementById('room_type');
        const maxPrice = document.getElementById('max_price_range');
        const maxPriceText = document.getElementById('max_price_text');

        function submitForm() {
            form.submit();
        }

        if (building) {
            building.addEventListener('change', submitForm);
        }

        if (roomType) {
            roomType.addEventListener('change', submitForm);
        }

        if (maxPrice) {
            // cập nhật text + submit
            maxPrice.addEventListener('change', function() {
                const v = Number(this.value);
                if (maxPriceText) {
                    maxPriceText.textContent = v > 0 ?
                        v.toLocaleString('vi-VN') + ' đ' :
                        'Không giới hạn';
                }
                submitForm();
            });

            // chỉ cập nhật text khi đang kéo, không submit
            maxPrice.addEventListener('input', function() {
                const v = Number(this.value);
                if (maxPriceText) {
                    maxPriceText.textContent = v > 0 ?
                        v.toLocaleString('vi-VN') + ' đ' :
                        'Không giới hạn';
                }
            });
        }

        // ===== FAQ ACCORDION =====
        const faqItems = document.querySelectorAll('.faq-item');

        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            if (!question) return;

            question.addEventListener('click', () => {
                const isActive = item.classList.contains('active');

                // Đóng tất cả FAQ khác
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });

                // Toggle FAQ hiện tại
                item.classList.toggle('active', !isActive);
            });
        });
    });
</script>
</body>
</html>