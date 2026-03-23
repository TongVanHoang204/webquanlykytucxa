<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

/*
Visual thesis: a calm editorial homepage built from real dorm photography, warm wood tones,
and deep navy surfaces so the KTX feels credible, modern, and livable.
Content plan: hero, live room availability, daily-living detail with announcements, final CTA.
Interaction thesis:
- Stagger the hero copy for immediate presence.
- Add a restrained scroll-linked shift to the hero visual plane.
- Reveal sections and room rows progressively to keep the page feeling curated.
*/

function homepage_announcement_meta(string $title): array
{
    $normalized = mb_strtolower($title, 'UTF-8');

    if (str_contains($normalized, 'khẩn cấp') || str_contains($normalized, 'cảnh báo')) {
        return ['label' => 'Khẩn cấp', 'icon' => 'fa-triangle-exclamation'];
    }

    if (str_contains($normalized, 'sự kiện') || str_contains($normalized, 'hoạt động')) {
        return ['label' => 'Sự kiện', 'icon' => 'fa-calendar-days'];
    }

    if (str_contains($normalized, 'bảo trì') || str_contains($normalized, 'sửa chữa') || str_contains($normalized, 'cúp điện')) {
        return ['label' => 'Bảo trì', 'icon' => 'fa-screwdriver-wrench'];
    }

    return ['label' => 'Cập nhật', 'icon' => 'fa-bullhorn'];
}

$userId = $_SESSION['UserID'] ?? null;
$sessionRole = $_SESSION['Role'] ?? 'Guest';
$studentOverview = null;
$userHasRoom = false;

if ($userId) {
    $studentStmt = $conn->prepare("
        SELECT s.StudentID, s.FullName, s.StudentCode,
               b.BuildingName, r.RoomNumber, r.RoomType,
               c.StartDate, c.EndDate
        FROM Users u
        LEFT JOIN Students s ON s.UserID = u.UserID
        LEFT JOIN Contracts c ON c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
        LEFT JOIN Rooms r ON r.RoomID = c.RoomID
        LEFT JOIN Buildings b ON b.BuildingID = r.BuildingID
        WHERE u.UserID = ?
        LIMIT 1
    ");

    $studentStmt->bind_param("i", $userId);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $studentOverview = $studentResult ? $studentResult->fetch_assoc() : null;
    $studentStmt->close();

    $userHasRoom = !empty($studentOverview['RoomNumber']);
}

$searchBuilding = trim((string)($_GET['building'] ?? ''));
$searchRoomType = trim((string)($_GET['room_type'] ?? ''));
$searchMaxPrice = trim((string)($_GET['max_price'] ?? ''));

$buildings = [];
$buildingsQuery = $conn->query("SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName ASC");
if ($buildingsQuery) {
    while ($building = $buildingsQuery->fetch_assoc()) {
        $buildings[] = $building;
    }
}

$siteStats = [
    'OpenRooms' => 0,
    'TotalBuildings' => 0,
    'ActiveResidents' => 0,
];

$statsQuery = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM Rooms WHERE Status = 'Trống') AS OpenRooms,
        (SELECT COUNT(*) FROM Buildings) AS TotalBuildings,
        (SELECT COUNT(*) FROM Contracts WHERE Status = 'Hiệu lực') AS ActiveResidents
");

if ($statsQuery && ($statsRow = $statsQuery->fetch_assoc())) {
    $siteStats = array_merge($siteStats, $statsRow);
}

$roomLimit = $userId ? 5 : 4;
$whereClauses = ["r.Status = 'Trống'"];
$roomParams = [];
$roomTypes = '';

if ($searchBuilding !== '') {
    $whereClauses[] = "b.BuildingID = ?";
    $roomParams[] = (int)$searchBuilding;
    $roomTypes .= 'i';
}

if ($searchRoomType !== '') {
    $whereClauses[] = "r.RoomType = ?";
    $roomParams[] = $searchRoomType;
    $roomTypes .= 's';
}

if ($searchMaxPrice !== '' && is_numeric($searchMaxPrice) && (float)$searchMaxPrice > 0) {
    $whereClauses[] = "r.RoomPrice <= ?";
    $roomParams[] = (float)$searchMaxPrice;
    $roomTypes .= 'd';
}

$whereSql = implode(' AND ', $whereClauses);
$roomSql = "
    SELECT
        r.RoomID,
        r.RoomNumber,
        r.RoomType,
        r.RoomPrice,
        r.Capacity,
        r.ImagePath,
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
    WHERE {$whereSql}
    ORDER BY r.RoomPrice ASC, r.RoomNumber ASC
    LIMIT " . (int)$roomLimit;

$featuredRooms = [];
$roomResult = null;

if ($roomParams !== []) {
    $roomStmt = $conn->prepare($roomSql);
    $roomStmt->bind_param($roomTypes, ...$roomParams);
    $roomStmt->execute();
    $roomResult = $roomStmt->get_result();
} else {
    $roomResult = $conn->query($roomSql);
}

if ($roomResult instanceof mysqli_result) {
    while ($room = $roomResult->fetch_assoc()) {
        $featuredRooms[] = $room;
    }
}

if (isset($roomStmt) && $roomStmt instanceof mysqli_stmt) {
    $roomStmt->close();
}

$announcements = [];
$announcementsQuery = $conn->query("
    SELECT AnnouncementID, Title, Content, DatePosted, PostedBy
    FROM Announcements
    WHERE DatePosted <= NOW()
    ORDER BY DatePosted DESC
    LIMIT 3
");

if ($announcementsQuery) {
    while ($announcement = $announcementsQuery->fetch_assoc()) {
        $announcements[] = $announcement;
    }
}

$livingPoints = [
    [
        'icon' => 'fa-bed',
        'title' => 'Xem phòng bằng dữ liệu thật',
        'copy' => 'Ảnh phòng, số chỗ còn và mức giá được cập nhật trực tiếp ngay trên trang chủ.',
    ],
    [
        'icon' => 'fa-file-invoice-dollar',
        'title' => 'Theo dõi hợp đồng và chi phí',
        'copy' => 'Thời hạn hợp đồng, hóa đơn điện nước và các khoản phải trả nằm trong cùng một luồng.',
    ],
    [
        'icon' => 'fa-comments',
        'title' => 'Nhận thông báo và gửi phản ánh',
        'copy' => 'Ban quản lý, bảng tin và hỗ trợ sinh viên được gom về một bề mặt nhất quán.',
    ],
];

$faqItems = [
    [
        'question' => 'Làm sao để đăng ký phòng ở KTX?',
        'answer' => 'Đăng nhập, chọn phòng phù hợp, gửi yêu cầu và chờ ban quản lý xác nhận theo lịch xét duyệt.',
    ],
    [
        'question' => 'Chi phí hàng tháng gồm những gì?',
        'answer' => 'Tiền phòng là cố định, điện nước được cộng theo mức sử dụng thực tế trên hóa đơn của bạn.',
    ],
    [
        'question' => 'Cần hỗ trợ gấp thì liên hệ ở đâu?',
        'answer' => 'Bạn có thể gọi hotline, gửi phản ánh trong hệ thống hoặc đến văn phòng quản lý tại tòa A.',
    ],
];

$pageMode = 'guest';
if ($userId && in_array($sessionRole, ['Admin', 'Manager'], true)) {
    $pageMode = 'staff';
} elseif ($userId && $userHasRoom) {
    $pageMode = 'resident';
} elseif ($userId) {
    $pageMode = 'applicant';
}

$displayName = $studentOverview['FullName'] ?? ($_SESSION['FullName'] ?? 'Bạn');
$contractWindow = 'Chưa có hợp đồng hiệu lực';
if (!empty($studentOverview['StartDate']) && !empty($studentOverview['EndDate'])) {
    $contractWindow = date('d/m/Y', strtotime($studentOverview['StartDate'])) . ' - ' . date('d/m/Y', strtotime($studentOverview['EndDate']));
}

$heroEyebrow = 'Hệ thống nội trú cho sinh viên';
$heroTitle = 'Đăng ký phòng bằng hình ảnh thật, giá rõ ràng và cập nhật trực tiếp từ ban quản lý.';
$heroBody = 'Trang chủ được tổ chức như một mặt bằng tuyển chọn: xem phòng còn chỗ, đọc thông báo mới và bắt đầu thủ tục nội trú mà không cần đi qua nhiều màn hình.';
$heroPrimaryHref = 'login.php';
$heroPrimaryLabel = 'Đăng nhập để bắt đầu';
$heroSecondaryHref = '#room-availability';
$heroSecondaryLabel = 'Xem phòng đang mở';
$heroHighlights = [
    ['label' => 'Phòng đang mở', 'value' => number_format((int)$siteStats['OpenRooms'])],
    ['label' => 'Tòa nhà', 'value' => number_format((int)$siteStats['TotalBuildings'])],
    ['label' => 'Đang nội trú', 'value' => number_format((int)$siteStats['ActiveResidents'])],
];
$roomSectionTitle = 'Phòng còn chỗ trong hệ thống';
$roomSectionBody = 'Lọc nhanh theo tòa nhà, loại phòng và mức giá để xem những lựa chọn đang còn suất.';
$ctaTitle = 'Sẵn sàng chọn chỗ ở cho học kỳ tới?';
$ctaBody = 'Đăng nhập để nộp yêu cầu, theo dõi duyệt phòng và nhận thông báo từ ban quản lý trong cùng một luồng.';
$ctaPrimaryHref = 'login.php';
$ctaPrimaryLabel = 'Đăng nhập';
$ctaSecondaryHref = '#living-flow';
$ctaSecondaryLabel = 'Xem quy trình';

if ($pageMode === 'resident') {
    $heroEyebrow = 'Xin chào, ' . $displayName;
    $heroTitle = 'Phòng ở, hợp đồng và cập nhật nội trú của bạn đang được theo dõi trên cùng một bề mặt.';
    $heroBody = 'Trang chủ chuyển từ giới thiệu sang vận hành: bạn có thể xem tình trạng hợp đồng hiện tại, tham khảo phòng còn chỗ và theo dõi các thông báo mới nhất.';
    $heroPrimaryHref = 'modules/user/rooms/rooms.php';
    $heroPrimaryLabel = 'Xem phòng đang ở';
    $heroSecondaryHref = 'modules/user/dashboard.php';
    $heroSecondaryLabel = 'Đến trang sinh viên';
    $heroHighlights = [
        ['label' => 'Mã sinh viên', 'value' => $studentOverview['StudentCode'] ?: 'Chưa cập nhật'],
        ['label' => 'Phòng hiện tại', 'value' => trim(($studentOverview['BuildingName'] ?? '') . ' - ' . ($studentOverview['RoomNumber'] ?? ''))],
        ['label' => 'Hiệu lực', 'value' => $contractWindow],
    ];
    $roomSectionTitle = 'Tình trạng phòng còn chỗ';
    $roomSectionBody = 'Bạn đã có hợp đồng hiệu lực, nhưng vẫn có thể theo dõi những phòng còn trống và cập nhật mới trong toàn khu.';
    $ctaTitle = 'Cần theo dõi thêm thông tin nội trú?';
    $ctaBody = 'Mọi cập nhật về bảng tin, phản ánh và hồ sơ sinh viên đều có sẵn ngay trong hệ thống.';
    $ctaPrimaryHref = 'modules/user/notifications.php';
    $ctaPrimaryLabel = 'Xem thông báo';
    $ctaSecondaryHref = 'modules/user/UserProfile/profile.php';
    $ctaSecondaryLabel = 'Mở hồ sơ';
} elseif ($pageMode === 'applicant') {
    $heroEyebrow = 'Xin chào, ' . $displayName;
    $heroTitle = 'Phòng còn chỗ, mức giá và quy trình xét duyệt đang hiển thị ngay tại trang đầu.';
    $heroBody = 'Bạn đang ở bước chọn chỗ ở. Dùng bộ lọc để so sánh các lựa chọn mở, sau đó gửi yêu cầu và theo dõi phản hồi từ ban quản lý.';
    $heroPrimaryHref = 'modules/user/rooms/register_room.php';
    $heroPrimaryLabel = 'Gửi yêu cầu nhận phòng';
    $heroSecondaryHref = 'modules/user/dashboard.php';
    $heroSecondaryLabel = 'Đến trang sinh viên';
    $heroHighlights = [
        ['label' => 'Phòng đang mở', 'value' => number_format((int)$siteStats['OpenRooms'])],
        ['label' => 'Tòa nhà', 'value' => number_format((int)$siteStats['TotalBuildings'])],
        ['label' => 'Duyệt hồ sơ', 'value' => '1 - 3 ngày'],
    ];
    $roomSectionTitle = 'Phòng còn chỗ để bạn gửi yêu cầu';
    $roomSectionBody = 'So sánh nhanh theo tòa nhà, loại phòng và ngân sách trước khi nộp yêu cầu đăng ký.';
    $ctaTitle = 'Muốn chốt phòng trong hôm nay?';
    $ctaBody = 'Đi tiếp từ trang này để gửi yêu cầu, theo dõi kết quả duyệt và xem thông báo liên quan đến hồ sơ.';
    $ctaPrimaryHref = 'modules/user/rooms/register_room.php';
    $ctaPrimaryLabel = 'Đăng ký phòng';
    $ctaSecondaryHref = 'modules/user/room_requests/request_list.php';
    $ctaSecondaryLabel = 'Xem yêu cầu của tôi';
} elseif ($pageMode === 'staff') {
    $heroEyebrow = 'Không gian điều hành nội trú';
    $heroTitle = 'Từ trang đầu, bạn có thể nắm nhanh tình trạng phòng, thông báo và luồng vận hành toàn khu.';
    $heroBody = 'Homepage vẫn giữ vai trò giới thiệu công khai, nhưng với tài khoản quản lý nó đồng thời là điểm vào để rà soát tình trạng phòng và chuyển sang bảng điều hành.';
    $heroPrimaryHref = 'modules/staff/dashboard.php';
    $heroPrimaryLabel = 'Mở bảng quản trị';
    $heroSecondaryHref = '#room-availability';
    $heroSecondaryLabel = 'Xem tình trạng phòng';
    $heroHighlights = [
        ['label' => 'Vai trò', 'value' => $sessionRole],
        ['label' => 'Phòng đang mở', 'value' => number_format((int)$siteStats['OpenRooms'])],
        ['label' => 'Tòa nhà', 'value' => number_format((int)$siteStats['TotalBuildings'])],
    ];
    $roomSectionTitle = 'Tình trạng phòng còn chỗ';
    $roomSectionBody = 'Dữ liệu công khai được giữ ở dạng dễ quét để quản trị viên cũng có thể kiểm tra nhanh ngay tại trang chủ.';
    $ctaTitle = 'Tiếp tục vào bề mặt điều hành?';
    $ctaBody = 'Bảng quản trị dành cho bạn vẫn là nơi xử lý nghiệp vụ, nhưng trang này giúp nắm nhanh tình hình trước khi chuyển tiếp.';
    $ctaPrimaryHref = 'modules/staff/dashboard.php';
    $ctaPrimaryLabel = 'Đến dashboard';
    $ctaSecondaryHref = 'modules/user/accesslogs.php';
    $ctaSecondaryLabel = 'Mở bảng tin';
}

$pageTitle = 'Ký túc xá Sinh viên | Phòng ở, thông báo và nội trú';
$pageBodyClass = 'homepage';
$pageStylesheets = ['assets/css/homepage.css'];

include 'includes/header.php';
?>

<main class="homepage-main">
    <section class="hero-stage">
        <div class="hero-stage__veil"></div>
        <div class="hero-stage__grid">
            <div class="hero-stage__copy">
                <p class="hero-kicker"><?= htmlspecialchars($heroEyebrow) ?></p>
                <p class="hero-brand">Ký túc xá Sinh viên</p>
                <h1 class="hero-title"><?= htmlspecialchars($heroTitle) ?></h1>
                <p class="hero-body"><?= htmlspecialchars($heroBody) ?></p>

                <div class="hero-actions">
                    <a href="<?= htmlspecialchars($heroPrimaryHref) ?>" class="hero-button hero-button--primary">
                        <?= htmlspecialchars($heroPrimaryLabel) ?>
                    </a>
                    <a href="<?= htmlspecialchars($heroSecondaryHref) ?>" class="hero-button hero-button--secondary">
                        <?= htmlspecialchars($heroSecondaryLabel) ?>
                    </a>
                </div>
            </div>

            <div class="hero-stage__rail">
                <?php foreach ($heroHighlights as $highlight): ?>
                    <div class="hero-highlight">
                        <span class="hero-highlight__label"><?= htmlspecialchars($highlight['label']) ?></span>
                        <strong class="hero-highlight__value"><?= htmlspecialchars($highlight['value']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="room-availability" class="availability-section">
        <div class="section-shell">
            <div class="section-heading" data-reveal>
                <p class="section-heading__eyebrow">Dữ liệu phòng đang mở</p>
                <h2><?= htmlspecialchars($roomSectionTitle) ?></h2>
                <p><?= htmlspecialchars($roomSectionBody) ?></p>
            </div>

            <form method="GET" action="" class="availability-filter" id="roomFilterForm" data-reveal>
                <div class="availability-filter__group">
                    <label for="building">Tòa nhà</label>
                    <select name="building" id="building">
                        <option value="">Tất cả tòa nhà</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?= (int)$building['BuildingID'] ?>" <?= $searchBuilding === (string)$building['BuildingID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($building['BuildingName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="availability-filter__group">
                    <label for="room_type">Loại phòng</label>
                    <select name="room_type" id="room_type">
                        <option value="">Tất cả</option>
                        <option value="Nam" <?= $searchRoomType === 'Nam' ? 'selected' : '' ?>>Nam</option>
                        <option value="Nữ" <?= $searchRoomType === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                    </select>
                </div>

                <div class="availability-filter__group availability-filter__group--range">
                    <label for="max_price_range">
                        Mức giá tối đa
                        <span id="max_price_text">
                            <?= ($searchMaxPrice !== '' && is_numeric($searchMaxPrice) && (float)$searchMaxPrice > 0)
                                ? number_format((float)$searchMaxPrice) . ' đ'
                                : 'Không giới hạn' ?>
                        </span>
                    </label>
                    <input
                        type="range"
                        id="max_price_range"
                        name="max_price"
                        min="0"
                        max="3000000"
                        step="50000"
                        value="<?= ($searchMaxPrice !== '' && is_numeric($searchMaxPrice)) ? (float)$searchMaxPrice : 0 ?>">
                </div>

                <div class="availability-filter__actions">
                    <a href="<?= htmlspecialchars($base) ?>index.php" class="hero-button hero-button--ghost">Đặt lại bộ lọc</a>
                </div>

                <button type="submit" class="visually-hidden">Lọc phòng</button>
            </form>

            <div class="room-list">
                <?php if ($featuredRooms !== []): ?>
                    <?php foreach ($featuredRooms as $room): ?>
                        <?php
                        $roomImage = !empty($room['ImagePath']) ? $room['ImagePath'] : 'assets/img/room-default.jpg';
                        $roomPrice = number_format((float)$room['RoomPrice']) . ' đ / tháng';
                        $roomArea = ((int)$room['Capacity'] * 4) . ' m²';
                        $availableSlots = max(0, (int)$room['AvailableSlots']);
                        $detailHref = $base . 'modules/user/rooms/room_detail.php?id=' . (int)$room['RoomID'];
                        ?>
                        <article class="room-row" data-reveal>
                            <a href="<?= htmlspecialchars($detailHref) ?>" class="room-row__image" aria-label="Xem chi tiết phòng <?= htmlspecialchars($room['RoomNumber']) ?>">
                                <img
                                    src="<?= htmlspecialchars($roomImage) ?>"
                                    alt="Phòng <?= htmlspecialchars($room['RoomNumber']) ?>"
                                    onerror="this.onerror=null;this.src='assets/img/room-default.jpg';">
                            </a>

                            <div class="room-row__content">
                                <div class="room-row__topline">
                                    <span class="room-row__type"><?= htmlspecialchars($room['RoomType']) ?></span>
                                    <span class="room-row__price"><?= htmlspecialchars($roomPrice) ?></span>
                                </div>

                                <h3>Phòng <?= htmlspecialchars($room['RoomNumber']) ?></h3>
                                <p class="room-row__location"><?= htmlspecialchars($room['BuildingName']) ?></p>

                                <dl class="room-row__meta">
                                    <div>
                                        <dt>Còn chỗ</dt>
                                        <dd><?= $availableSlots ?>/<?= (int)$room['Capacity'] ?></dd>
                                    </div>
                                    <div>
                                        <dt>Đang ở</dt>
                                        <dd><?= (int)$room['CurrentOccupants'] ?> sinh viên</dd>
                                    </div>
                                    <div>
                                        <dt>Diện tích</dt>
                                        <dd><?= htmlspecialchars($roomArea) ?></dd>
                                    </div>
                                </dl>

                                <div class="room-row__actions">
                                    <a href="<?= htmlspecialchars($detailHref) ?>" class="text-link">Xem chi tiết</a>

                                    <?php if ($pageMode === 'guest'): ?>
                                        <a href="<?= htmlspecialchars($base) ?>login.php" class="hero-button hero-button--primary">Đăng nhập để đăng ký</a>
                                    <?php elseif ($pageMode === 'applicant'): ?>
                                        <a href="<?= htmlspecialchars($base) ?>modules/user/rooms/register_room.php" class="hero-button hero-button--primary">Gửi yêu cầu</a>
                                    <?php elseif ($pageMode === 'staff'): ?>
                                        <a href="<?= htmlspecialchars($base) ?>modules/staff/rooms/rooms.php" class="hero-button hero-button--primary">Quản lý phòng</a>
                                    <?php else: ?>
                                        <span class="room-row__status">Bạn đã có hợp đồng hiệu lực</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" data-reveal>
                        <p class="empty-state__eyebrow">Không tìm thấy kết quả phù hợp</p>
                        <h3>Hiện chưa có phòng trống theo bộ lọc bạn chọn.</h3>
                        <p>Thử nới mức giá hoặc đổi tòa nhà để xem thêm lựa chọn đang mở.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="living-flow" class="living-section">
        <div class="section-shell living-section__grid">
            <figure class="living-section__image" data-reveal>
                <img src="assets/img/rooms/room_1_d403f0bc.webp" alt="Không gian phòng ở ký túc xá">
                <figcaption>Một bề mặt nội trú tốt phải cho thấy được không gian ở thật, không chỉ mô tả bằng lời.</figcaption>
            </figure>

            <div class="living-section__content">
                <div class="section-heading" data-reveal>
                    <p class="section-heading__eyebrow">Vận hành hằng ngày</p>
                    <h2>Ở trong một hệ thống rõ ràng, không phải một chuỗi thủ tục rời rạc.</h2>
                    <p>Trang chủ không dừng ở mức giới thiệu. Nó dẫn thẳng đến phòng đang mở, luồng nội trú và những cập nhật mới nhất từ ban quản lý.</p>
                </div>

                <div class="living-points" data-reveal>
                    <?php foreach ($livingPoints as $point): ?>
                        <article class="living-point">
                            <div class="living-point__icon">
                                <i class="fas <?= htmlspecialchars($point['icon']) ?>"></i>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($point['title']) ?></h3>
                                <p><?= htmlspecialchars($point['copy']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="announcement-feed" data-reveal>
                    <div class="announcement-feed__head">
                        <div>
                            <p class="section-heading__eyebrow">Thông báo mới nhất</p>
                            <h3>Bảng tin nổi bật</h3>
                        </div>
                        <a href="<?= htmlspecialchars($base) ?>modules/user/accesslogs.php" class="text-link">Xem tất cả</a>
                    </div>

                    <?php if ($announcements !== []): ?>
                        <div class="announcement-feed__list">
                            <?php foreach ($announcements as $announcement): ?>
                                <?php $meta = homepage_announcement_meta($announcement['Title']); ?>
                                <article class="announcement-row">
                                    <div class="announcement-row__meta">
                                        <span class="announcement-row__tag">
                                            <i class="fas <?= htmlspecialchars($meta['icon']) ?>"></i>
                                            <?= htmlspecialchars($meta['label']) ?>
                                        </span>
                                        <span><?= date('d/m/Y', strtotime($announcement['DatePosted'])) ?></span>
                                    </div>
                                    <h4><?= htmlspecialchars($announcement['Title']) ?></h4>
                                    <p><?= htmlspecialchars(mb_strimwidth($announcement['Content'], 0, 140, '...')) ?></p>
                                    <a href="<?= htmlspecialchars($base) ?>modules/user/accesslogs.php?view=<?= (int)$announcement['AnnouncementID'] ?>" class="text-link">
                                        Mở chi tiết
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="announcement-feed__empty">Hiện chưa có thông báo mới.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="section-shell final-cta__grid">
            <div class="final-cta__lead" data-reveal>
                <p class="section-heading__eyebrow">Bước tiếp theo</p>
                <h2><?= htmlspecialchars($ctaTitle) ?></h2>
                <p><?= htmlspecialchars($ctaBody) ?></p>

                <div class="hero-actions">
                    <a href="<?= htmlspecialchars($ctaPrimaryHref) ?>" class="hero-button hero-button--primary">
                        <?= htmlspecialchars($ctaPrimaryLabel) ?>
                    </a>
                    <a href="<?= htmlspecialchars($ctaSecondaryHref) ?>" class="hero-button hero-button--secondary">
                        <?= htmlspecialchars($ctaSecondaryLabel) ?>
                    </a>
                </div>

                <div class="support-lines">
                    <div>
                        <span>Hotline</span>
                        <strong>1900 1234</strong>
                    </div>
                    <div>
                        <span>Email</span>
                        <strong>admin@ktx.edu.vn</strong>
                    </div>
                    <div>
                        <span>Văn phòng</span>
                        <strong>Tòa A, tầng 1</strong>
                    </div>
                </div>
            </div>

            <div class="faq-panel" data-reveal>
                <p class="section-heading__eyebrow">Câu hỏi thường gặp</p>
                <div class="faq-list">
                    <?php foreach ($faqItems as $faq): ?>
                        <div class="faq-item">
                            <button class="faq-question" type="button" aria-expanded="false">
                                <span><?= htmlspecialchars($faq['question']) ?></span>
                                <i class="fas fa-plus faq-icon"></i>
                            </button>
                            <div class="faq-answer">
                                <p><?= htmlspecialchars($faq['answer']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('roomFilterForm');
    const building = document.getElementById('building');
    const roomType = document.getElementById('room_type');
    const maxPrice = document.getElementById('max_price_range');
    const maxPriceText = document.getElementById('max_price_text');
    const revealItems = document.querySelectorAll('[data-reveal]');
    const faqItems = document.querySelectorAll('.faq-item');
    const heroStage = document.querySelector('.hero-stage');

    function submitForm() {
        if (form) {
            form.submit();
        }
    }

    if (building) {
        building.addEventListener('change', submitForm);
    }

    if (roomType) {
        roomType.addEventListener('change', submitForm);
    }

    if (maxPrice) {
        maxPrice.addEventListener('input', function () {
            const value = Number(this.value);
            if (maxPriceText) {
                maxPriceText.textContent = value > 0 ? value.toLocaleString('vi-VN') + ' đ' : 'Không giới hạn';
            }
        });

        maxPrice.addEventListener('change', function () {
            const value = Number(this.value);
            if (maxPriceText) {
                maxPriceText.textContent = value > 0 ? value.toLocaleString('vi-VN') + ' đ' : 'Không giới hạn';
            }
            submitForm();
        });
    }

    if ('IntersectionObserver' in window && revealItems.length > 0) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.16 });

        revealItems.forEach(function (item, index) {
            item.style.setProperty('--reveal-delay', (index * 70) + 'ms');
            observer.observe(item);
        });
    } else {
        revealItems.forEach(function (item) {
            item.classList.add('is-visible');
        });
    }

    function updateHeroShift() {
        if (!heroStage) {
            return;
        }

        const shift = Math.min(window.scrollY * 0.18, 72);
        heroStage.style.setProperty('--hero-shift', shift + 'px');
    }

    updateHeroShift();
    window.addEventListener('scroll', updateHeroShift, { passive: true });

    faqItems.forEach(function (item) {
        const button = item.querySelector('.faq-question');
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            const isOpen = item.classList.contains('active');

            faqItems.forEach(function (otherItem) {
                const otherButton = otherItem.querySelector('.faq-question');
                otherItem.classList.remove('active');
                if (otherButton) {
                    otherButton.setAttribute('aria-expanded', 'false');
                }
            });

            item.classList.toggle('active', !isOpen);
            button.setAttribute('aria-expanded', String(!isOpen));
        });
    });
});
</script>
</body>
</html>
