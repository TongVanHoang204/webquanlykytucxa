$stmt->bind_param("iiiss", $studentID, $roomID, $deposit, $method, $transactionCode);
$stmt->execute();
$stmt->close();

// ✅ Giả lập “tự động nhận tiền thành công”
$conn->query("UPDATE Payments SET Status='Đã thanh toán', PaidAt=NOW() WHERE TransactionCode='$transactionCode'");

// 🏠 Tạo hợp đồng ngay
$conn->query("
    INSERT INTO Contracts (StudentID, RoomID, StartDate, EndDate, Deposit, Status)
    VALUES ($studentID, $roomID, NOW(), DATE_ADD(NOW(), INTERVAL 12 MONTH), $deposit, 'Hiệu lực')
");

// Cập nhật số người ở và trạng thái phòng
$conn->query("UPDATE Rooms SET CurrentOccupants = CurrentOccupants + 1 WHERE RoomID = $roomID");
$conn->query("UPDATE Rooms SET Status = CASE WHEN CurrentOccupants >= Capacity THEN 'Đầy' ELSE 'Trống' END WHERE RoomID = $roomID");

// ✅ Hiển thị popup thành công
echo "
<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
<script>
Swal.fire({
    icon: 'success',
    title: 'Thanh toán thành công!',
    html: `
        <p>💰 Đã thanh toán tiền cọc: <b>".number_format($deposit,0,',','.')."₫</b></p>
        <p>🏠 Hợp đồng đã được tạo cho bạn.</p>
    `,
    confirmButtonColor: '#2ecc71',
    confirmButtonText: 'Về Dashboard',
    width: 500
}).then(() => window.location.href = '../dashboard.php');
</script>";
exit;
