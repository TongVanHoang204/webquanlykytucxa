<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/auth_check.php';
requireRole(['Admin','Manager']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=finance_export_'.date('Ymd_His').'.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 cho Excel

// Summary theo tháng?
if (!empty($_GET['summary'])) {
  $year = intval($_GET['year'] ?? date('Y'));
  fputcsv($out, ["BÁO CÁO THEO THÁNG NĂM $year"]);
  fputcsv($out, ['Tháng','Đã thu','Chưa thu']);

  $sql = "
    SELECT i.Month,
      SUM(CASE WHEN i.Status='Đã thanh toán' THEN i.TotalAmount ELSE 0 END) AS paid,
      SUM(CASE WHEN i.Status IN ('Chưa thanh toán','Quá hạn') THEN i.TotalAmount ELSE 0 END) AS unpaid
    FROM Invoices i
    WHERE i.Year=?
    GROUP BY i.Month
    ORDER BY i.Month
  ";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $year);
  $st->execute();
  $rs = $st->get_result();
  while($r=$rs->fetch_assoc()){
    fputcsv($out, [$r['Month'], $r['paid'], $r['unpaid']]);
  }
  exit;
}

// Chi tiết theo bộ lọc
$range  = $_GET['range']  ?? 'year';
$status = $_GET['status'] ?? 'all';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-t');
$year   = intval($_GET['year'] ?? date('Y'));
$month  = $_GET['month']  ?? date('Y-m');

if ($range==='month'){
  $from = date('Y-m-01', strtotime($month.'-01'));
  $to   = date('Y-m-t',  strtotime($month.'-01'));
} elseif($range==='year'){
  $from = "$year-01-01";
  $to   = "$year-12-31";
}

$where = "i.CreatedAt BETWEEN ? AND ?";
$params = [$from." 00:00:00", $to." 23:59:59"];
$types  = "ss";
if ($status !== 'all'){
  $where .= " AND i.Status=?";
  $params[] = $status; $types .= "s";
}

fputcsv($out, ["BÁO CÁO TÀI CHÍNH ($from → $to)"]);
fputcsv($out, ['InvoiceID','Sinh viên','MSSV','Tòa','Phòng','Tổng tiền','Trạng thái','Ngày tạo','Hạn','Thanh toán','Tháng','Năm']);

$sql = "
  SELECT i.InvoiceID, i.Month, i.Year, i.TotalAmount, i.Status,
         i.CreatedAt, i.DueDate, i.PaidAt,
         s.FullName, s.StudentCode, r.RoomNumber, b.BuildingName
  FROM Invoices i
  JOIN Contracts c ON i.ContractID=c.ContractID
  JOIN Students s ON c.StudentID=s.StudentID
  JOIN Rooms r ON c.RoomID=r.RoomID
  JOIN Buildings b ON r.BuildingID=b.BuildingID
  WHERE $where
  ORDER BY i.Year DESC, i.Month DESC, i.InvoiceID DESC
";
$st = $conn->prepare($sql);
$st->bind_param($types, ...$params);
$st->execute();
$rs = $st->get_result();
while($row=$rs->fetch_assoc()){
  fputcsv($out, [
    $row['InvoiceID'],
    $row['FullName'],
    $row['StudentCode'],
    $row['BuildingName'],
    $row['RoomNumber'],
    $row['TotalAmount'],
    $row['Status'],
    $row['CreatedAt'],
    $row['DueDate'],
    $row['PaidAt'],
    $row['Month'],
    $row['Year'],
  ]);
}
