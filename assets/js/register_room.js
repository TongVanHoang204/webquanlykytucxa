// Update payment method when radio buttons change
document.querySelectorAll('input[type="radio"][name^="payment_method_"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const roomId = this.name.split('_')[2];
        document.getElementById(`payment_method_${roomId}`).value = this.value;
    });
});

function confirmRegistration(e, roomId, buildingName, roomNumber, roomPrice) {
    e.preventDefault();
    const form = e.target.closest('form');
    const paymentMethod = document.getElementById(`payment_method_${roomId}`).value;

    Swal.fire({
        title: 'Xác nhận Đăng ký & Thanh toán',
        html: `
                    <div style="text-align: left; line-height: 1.8;">
                        <p><strong>🏢 Tòa nhà:</strong> ${buildingName}</p>
                        <p><strong>🚪 Phòng:</strong> ${roomNumber}</p>
                        <p><strong>💰 Tiền phòng:</strong> ${roomPrice.toLocaleString('vi-VN')}₫/tháng</p>
                        <p><strong>💵 Tiền cọc:</strong> 500,000₫</p>
                        <p><strong>🏦 Phương thức:</strong> ${paymentMethod}</p>
                        <hr style="margin: 1rem 0;">
                        <p><strong>💳 Tổng thanh toán ban đầu:</strong> <span style="color: #e74c3c; font-weight: bold;">${(500000).toLocaleString('vi-VN')}₫</span></p>
                        
                        <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                            <p style="color: #856404; margin: 0;">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Lưu ý quan trọng:</strong> Bạn cần chờ nhân viên xác nhận thanh toán trước khi được xếp phòng chính thức.
                            </p>
                        </div>
                    </div>
                `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Xác nhận Thanh toán',
        cancelButtonText: 'Hủy bỏ',
        confirmButtonColor: '#2ecc71',
        cancelButtonColor: '#e74c3c',
        width: 500
    }).then((result) => {
        if (result.isConfirmed) {
            // Add loading state
            const button = form.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            button.classList.add('btn-loading');

            setTimeout(() => {
                form.submit();
            }, 1000);
        }
    });

    return false;
}