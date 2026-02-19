<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo "<script>alert('Thiếu ID hợp đồng!'); window.location='contract_list.php';</script>";
    exit;
}

// 🔍 Lấy thông tin hợp đồng
$sql = "
    SELECT 
        c.*, 
        s.FullName, s.StudentCode, s.Email, s.Phone, s.Gender,
        r.RoomNumber, b.BuildingName
    FROM Contracts c
    JOIN Students s ON c.StudentID = s.StudentID
    JOIN Rooms r ON c.RoomID = r.RoomID
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE c.ContractID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$contract = $result->fetch_assoc();

if (!$contract) {
    echo "<script>alert('Không tìm thấy hợp đồng!'); window.location='contract_list.php';</script>";
    exit;
}

function formatMoney($n)
{
    return number_format($n, 0, ',', '.') . ' ₫';
}
function getStatusClass($status)
{
    switch ($status) {
        case 'Hiệu lực':
            return 'active';
        case 'Hết hạn':
            return 'expired';
        case 'Đã hủy':
            return 'canceled';
        default:
            return '';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết Hợp đồng #<?= $contract['ContractID'] ?> | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/contract/staff_contract_detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="contract-detail-container">
        <div class="header">
            <h2><i class="fas fa-file-contract"></i> Hợp đồng #<?= $contract['ContractID'] ?></h2>
            <a href="contract_list.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại danh sách</a>
        </div>

        <div class="contract-meta">
            <div class="meta-card">
                <div class="meta-label">Trạng thái hợp đồng</div>
                <div class="meta-value">
                    <span class="status-badge <?= getStatusClass($contract['Status']) ?>"><?= $contract['Status'] ?></span>
                </div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Ngày bắt đầu</div>
                <div class="meta-value"><?= date('d/m/Y', strtotime($contract['StartDate'])) ?></div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Ngày kết thúc</div>
                <div class="meta-value"><?= date('d/m/Y', strtotime($contract['EndDate'])) ?></div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Tiền cọc</div>
                <div class="meta-value"><?= formatMoney($contract['Deposit']) ?></div>
            </div>
        </div>

        <div class="contract-info">
            <div class="info-card">
                <h3><i class="fas fa-user-graduate"></i> Thông tin sinh viên</h3>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-user"></i></div>
                    <div class="info-content">
                        <div class="info-label">Họ tên</div>
                        <div class="info-value"><?= htmlspecialchars($contract['FullName']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-id-card"></i></div>
                    <div class="info-content">
                        <div class="info-label">MSSV</div>
                        <div class="info-value"><?= htmlspecialchars($contract['StudentCode']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-envelope"></i></div>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($contract['Email']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-phone"></i></div>
                    <div class="info-content">
                        <div class="info-label">Số điện thoại</div>
                        <div class="info-value"><?= htmlspecialchars($contract['Phone']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-venus-mars"></i></div>
                    <div class="info-content">
                        <div class="info-label">Giới tính</div>
                        <div class="info-value"><?= htmlspecialchars($contract['Gender']) ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-building"></i> Thông tin phòng</h3>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-hotel"></i></div>
                    <div class="info-content">
                        <div class="info-label">Tòa nhà</div>
                        <div class="info-value"><?= htmlspecialchars($contract['BuildingName']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-door-open"></i></div>
                    <div class="info-content">
                        <div class="info-label">Số phòng</div>
                        <div class="info-value"><?= htmlspecialchars($contract['RoomNumber']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-calendar"></i></div>
                    <div class="info-content">
                        <div class="info-label">Ngày tạo hợp đồng</div>
                        <div class="info-value"><?= date('d/m/Y', strtotime($contract['CreatedAt'])) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-clock"></i></div>
                    <div class="info-content">
                        <div class="info-label">Cập nhật gần nhất</div>
                        <div class="info-value"><?= date('d/m/Y', strtotime($contract['UpdatedAt'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="actions">
            <?php if ($contract['Status'] === 'Hiệu lực'): ?>
                <button class="btn btn-renew" onclick="renewContract(<?= $contract['ContractID'] ?>)">
                    <i class="fas fa-sync-alt"></i> Gia hạn hợp đồng
                </button>
                <button class="btn btn-cancel" onclick="cancelContract(<?= $contract['ContractID'] ?>)">
                    <i class="fas fa-ban"></i> Hủy hợp đồng
                </button>
            <?php endif; ?>

            <?php if ($_SESSION['Role'] === 'Admin'): ?>
                <button class="btn btn-delete" onclick="deleteContract(<?= $contract['ContractID'] ?>)">
                    <i class="fas fa-trash-alt"></i> Xóa hợp đồng
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 🔁 Gia hạn hợp đồng
        function renewContract(id) {
            Swal.fire({
                title: 'Gia hạn hợp đồng',
                html: `
                <p>Vui lòng chọn ngày kết thúc mới cho hợp đồng:</p>
                <input type="date" id="newEndDate" class="swal2-input" placeholder="Chọn ngày kết thúc mới">
            `,
                confirmButtonText: 'Xác nhận gia hạn',
                confirmButtonColor: '#2ecc71',
                showCancelButton: true,
                cancelButtonText: 'Hủy bỏ',
                preConfirm: () => {
                    const newDate = document.getElementById('newEndDate').value;
                    if (!newDate) {
                        Swal.showValidationMessage('Vui lòng chọn ngày kết thúc mới!');
                        return false;
                    }
                    return newDate;
                }
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('contract_update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                id,
                                action: 'renew',
                                newEndDate: result.value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    title: '✅ Thành công',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    title: '❌ Lỗi',
                                    text: data.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: '❌ Lỗi',
                                text: 'Đã xảy ra lỗi khi xử lý yêu cầu',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        // ❌ Hủy hợp đồng
        function cancelContract(id) {
            Swal.fire({
                title: 'Xác nhận hủy hợp đồng?',
                text: 'Hợp đồng sẽ bị đánh dấu là "Đã hủy" và không thể khôi phục.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f39c12',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Xác nhận hủy',
                cancelButtonText: 'Hủy bỏ'
            }).then(res => {
                if (res.isConfirmed) {
                    fetch('contract_update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                id,
                                action: 'cancel'
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    title: '✅ Thành công',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    title: '❌ Lỗi',
                                    text: data.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: '❌ Lỗi',
                                text: 'Đã xảy ra lỗi khi xử lý yêu cầu',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        // 🗑️ Xóa hợp đồng (chỉ Admin)
        function deleteContract(id) {
            Swal.fire({
                title: 'Xóa hợp đồng vĩnh viễn?',
                text: 'Hành động này không thể hoàn tác! Tất cả dữ liệu liên quan đến hợp đồng này sẽ bị xóa.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Xóa vĩnh viễn',
                cancelButtonText: 'Hủy bỏ'
            }).then(res => {
                if (res.isConfirmed) {
                    fetch('contract_delete_api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                id
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    title: '✅ Đã xóa',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => location.href = 'contract_list.php');
                            } else {
                                Swal.fire({
                                    title: '❌ Lỗi',
                                    text: data.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: '❌ Lỗi',
                                text: 'Đã xảy ra lỗi khi xử lý yêu cầu',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        }

        // Thêm hiệu ứng loading khi click các nút
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                    this.disabled = true;

                    // Khôi phục trạng thái sau 3 giây nếu có lỗi
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 3000);
                });
            });
        });
    </script>
</body>

</html>