<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

/* ============== 1) NHẬN THAM SỐ LỌC (GIỐNG student_list.php) ============== */
$keyword = trim($_GET['search'] ?? '');
$faculty = $_GET['faculty'] ?? 'all';       // FacultyID hoặc 'all'
$inDorm  = trim($_GET['in_dorm'] ?? 'all'); // all|1|0
$gender  = trim($_GET['gender'] ?? 'all');  // all|Nam|Nữ

/* ============== 2) BUILD WHERE ============== */
$where = " WHERE 1=1 ";

if ($keyword !== '') {
    $kw = $conn->real_escape_string($keyword);
    $where .= " AND (
        s.FullName    LIKE '%$kw%' OR 
        s.StudentCode LIKE '%$kw%' OR 
        s.Phone       LIKE '%$kw%'
    ) ";
}

/* Lọc theo Khoa (FacultyID) */
if ($faculty !== 'all') {
    $fid = (int)$faculty;
    if ($fid > 0) {
        $where .= " AND s.FacultyID = $fid ";
    }
}

/* Lọc giới tính */
if ($gender === 'Nam' || $gender === 'Nữ') {
    $gd = $conn->real_escape_string($gender);
    $where .= " AND s.Gender = '$gd' ";
}

/* Lọc theo tình trạng KTX (dựa HĐ hiệu lực) */
if ($inDorm === '1') {
    $where .= " AND EXISTS (
        SELECT 1 FROM Contracts c 
        WHERE c.StudentID = s.StudentID 
          AND c.Status = 'Hiệu lực'
    ) ";
} elseif ($inDorm === '0') {
    $where .= " AND NOT EXISTS (
        SELECT 1 FROM Contracts c 
        WHERE c.StudentID = s.StudentID 
          AND c.Status = 'Hiệu lực'
    ) ";
}

/* ============== 3) TRUY VẤN DỮ LIỆU XUẤT ============== */
/*
 * - FacultyName từ Faculties
 * - InDormLive: Có/Không HĐ hiệu lực
 * - HĐ hiệu lực mới nhất để lấy Tòa/Phòng + thời gian
 */
$sql = "
SELECT 
    s.StudentCode,
    s.FullName,
    s.Gender,
    f.FacultyName,
    s.ClassName,
    s.CourseYear,
    s.Email,
    s.Phone,

    CASE 
        WHEN EXISTS (
            SELECT 1 
            FROM Contracts c0
            WHERE c0.StudentID = s.StudentID AND c0.Status = 'Hiệu lực'
        ) THEN 'Đang ở KTX'
        ELSE 'Chưa ở KTX'
    END AS TrangThaiKTX,

    b.BuildingName,
    r.RoomNumber,
    c.ContractID,
    c.StartDate,
    c.EndDate,

    s.Hometown,
    s.Address,
    s.BirthDate,
    s.CitizenID,
    s.CreatedAt
FROM Students s
LEFT JOIN Faculties f 
    ON f.FacultyID = s.FacultyID
LEFT JOIN Contracts c
    ON c.ContractID = (
        SELECT c2.ContractID
        FROM Contracts c2
        WHERE c2.StudentID = s.StudentID
          AND c2.Status = 'Hiệu lực'
        ORDER BY c2.StartDate DESC, c2.ContractID DESC
        LIMIT 1
    )
LEFT JOIN Rooms r 
    ON r.RoomID = c.RoomID
LEFT JOIN Buildings b 
    ON b.BuildingID = r.BuildingID
$where
ORDER BY s.FullName ASC, s.StudentCode ASC
";

$res = $conn->query($sql);
if (!$res) {
    die('Lỗi truy vấn: ' . $conn->error);
}

/* ============== 4) HEADER TẢI FILE EXCEL ============== */
$filename = 'Danh_sach_sinh_vien_' . date('d-m-Y') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

/* Đảm bảo Excel đọc UTF-8 đúng */
echo "\xEF\xBB\xBF";

/* ============== 5) IN BẢNG HTML ============== */
?>
<table border="1">
    <thead>
        <tr>
            <th>MSSV</th>
            <th>Họ tên</th>
            <th>Giới tính</th>
            <th>Khoa</th>
            <th>Lớp</th>
            <th>Khóa</th>
            <th>Email</th>
            <th>Số điện thoại</th>
            <th>Trạng thái KTX</th>
            <th>Tòa</th>
            <th>Phòng</th>
            <th>Mã HĐ</th>
            <th>HĐ bắt đầu</th>
            <th>HĐ kết thúc</th>
            <th>Ngày sinh</th>
            <th>CMND/CCCD</th>
            <th>Quê quán</th>
            <th>Địa chỉ</th>
            <th>Ngày tạo hồ sơ</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = $res->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['StudentCode']) ?></td>
            <td><?= htmlspecialchars($row['FullName']) ?></td>
            <td><?= htmlspecialchars($row['Gender']) ?></td>
            <td><?= htmlspecialchars($row['FacultyName'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['ClassName'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['CourseYear'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['Email'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['Phone'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['TrangThaiKTX']) ?></td>
            <td><?= htmlspecialchars($row['BuildingName'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['RoomNumber'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['ContractID'] ?? '') ?></td>
            <td><?= $row['StartDate'] ? date('d/m/Y', strtotime($row['StartDate'])) : '' ?></td>
            <td><?= $row['EndDate']   ? date('d/m/Y', strtotime($row['EndDate']))   : '' ?></td>
            <td><?= $row['BirthDate'] ? date('d/m/Y', strtotime($row['BirthDate'])) : '' ?></td>
            <td><?= htmlspecialchars($row['CitizenID'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['Hometown'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['Address'] ?? '') ?></td>
            <td><?= $row['CreatedAt'] ? date('d/m/Y H:i', strtotime($row['CreatedAt'])) : '' ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
