# Wave 1 Rollout Checklist

Mục tiêu của checklist này là giúp bật Wave 1 trên môi trường local hoặc staging theo đúng thứ tự:

- migration database
- cấu hình `.env`
- khởi động service cần thiết
- test tay end-to-end cho từng tính năng

## 1. Pre-flight

- [ ] Xác nhận branch hiện tại đã chứa code Wave 1.
- [ ] Chạy `npm install` tại root project nếu máy chưa có `ws`.
- [ ] Xác nhận database đang dùng đúng schema `quanlyktx`.
- [ ] Chuẩn bị 2 tài khoản để test:
  - 1 tài khoản `Student`
  - 1 tài khoản `Manager` hoặc `Admin`
- [ ] Nếu test mass email thật:
  - cấu hình PHP `mail()` hoặc sendmail/SMTP của Laragon trước
  - nếu chưa có mail transport, vẫn có thể test log campaign nhưng email thật sẽ fail

## 2. Backup Database

- [ ] Backup database trước khi chạy migration.

Gợi ý:

```sql
-- Trong MySQL client
SHOW DATABASES;
USE quanlyktx;
```

Nếu dùng `mysqldump`, ví dụ:

```powershell
mysqldump -u root quanlyktx > backup-wave1-before.sql
```

## 3. Run Migration

File migration:

- [2026-03-31-wave1-foundation.sql](/c:/laragon/www/WEBQuanLyKyTucXa/database/migrations/2026-03-31-wave1-foundation.sql)

Checklist:

- [ ] Mở MySQL client hoặc phpMyAdmin.
- [ ] Chọn database `quanlyktx`.
- [ ] Chạy file migration Wave 1.

Ví dụ trong MySQL client:

```sql
USE quanlyktx;
SOURCE C:/laragon/www/WEBQuanLyKyTucXa/database/migrations/2026-03-31-wave1-foundation.sql;
```

## 4. Verify Migration

- [ ] Kiểm tra bảng `user_notifications`.
- [ ] Kiểm tra bảng `email_campaigns`.
- [ ] Kiểm tra bảng `email_campaign_recipients`.
- [ ] Kiểm tra bảng `gate_qr_tokens`.
- [ ] Kiểm tra bảng `gate_access_logs`.

Query kiểm tra nhanh:

```sql
USE quanlyktx;
SHOW TABLES LIKE 'user_notifications';
SHOW TABLES LIKE 'email_campaigns';
SHOW TABLES LIKE 'email_campaign_recipients';
SHOW TABLES LIKE 'gate_qr_tokens';
SHOW TABLES LIKE 'gate_access_logs';
```

Kiểm tra index QR chống trùng:

```sql
SHOW CREATE TABLE gate_qr_tokens;
```

Kỳ vọng:

- có `uniq_gate_qr_active_student`
- có `uniq_gate_qr_token_hash`

## 5. Update .env

Repo hiện đã có file `.env` ở root.

- [ ] Không thay file `.env` hiện tại bằng file mới.
- [ ] Giữ nguyên các key đang dùng cho Google OAuth.
- [ ] Thêm các key Wave 1 vào cùng file `.env`.

File mẫu:

- [.env.wave1.example](/c:/laragon/www/WEBQuanLyKyTucXa/.env.wave1.example)

Block cần thêm vào `.env`:

```ini
WAVE1_REALTIME_SECRET=replace-with-a-long-random-secret
WAVE1_REALTIME_PUBLISH_SECRET=replace-with-a-second-long-random-secret
REALTIME_WS_URL=ws://localhost:3000/ws
REALTIME_PUBLISH_URL=http://localhost:3000/api/realtime/publish
WAVE1_GATE_SECRET=replace-with-a-third-long-random-secret
```

Ghi chú:

- PHP API `realtime_token.php` và `push_notification.php` đã đọc root `.env`.
- `gate_qr_service.php` cũng đã được vá để fallback đọc root `.env`.
- Node server đọc `.env` qua `dotenv/config`.
- Sau khi đổi `.env`, phải restart Node server.

## 6. Start Required Services

- [ ] Đảm bảo Apache/Nginx + PHP đang chạy.
- [ ] Đảm bảo MySQL đang chạy.
- [ ] Khởi động Node server realtime/AI:

```powershell
npm start
```

Kỳ vọng:

- Node chạy tại `http://localhost:3000`
- WebSocket endpoint dùng `ws://localhost:3000/ws`
- Publish endpoint dùng `http://localhost:3000/api/realtime/publish`

## 7. Smoke Verification

- [ ] Chạy smoke test contract:

```powershell
npm run test:wave1
```

- [ ] Nếu vừa sửa PHP shell hoặc API, có thể lint thêm:

```powershell
C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l includes\admin_header.php
C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l includes\header.php
```

## 8. Manual Test Setup

- [ ] Mở 2 session trình duyệt riêng:
  - 1 cửa sổ thường cho `Manager/Admin`
  - 1 cửa sổ ẩn danh cho `Student`
- [ ] Trong cả 2 session, đăng nhập thành công.
- [ ] Mở DevTools Network nếu muốn theo dõi `realtime_token.php`, `push_notification.php`, `scan_gate_qr.php`.

## 9. Manual Test: Realtime Notification

Trang liên quan:

- [notification_center.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/staff/communications/notification_center.php)
- [notifications.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/user/notifications.php)

Checklist:

- [ ] Ở session `Manager/Admin`, mở trang gửi thông báo nội bộ.
- [ ] Gửi một thông báo tới `students`.
- [ ] Ở session `Student`, không reload trang.
- [ ] Quan sát biểu tượng chuông cập nhật ngay.
- [ ] Mở dropdown thông báo ở header user.
- [ ] Kiểm tra thông báo xuất hiện đúng tiêu đề/nội dung.
- [ ] Mở trang `Tất cả thông báo`.
- [ ] Kiểm tra thông báo tồn tại trong danh sách, đọc từ `user_notifications`.
- [ ] Quay lại session `Manager/Admin`, gửi một thông báo tới `staff_admin`.
- [ ] Ở session admin khác hoặc manager khác, xác nhận chuông cập nhật ngay.

Kỳ vọng:

- dropdown bell cập nhật không cần refresh
- dữ liệu vẫn còn sau khi refresh
- đánh dấu đã đọc làm giảm `unreadCount`

## 10. Manual Test: Mass Email

Trang liên quan:

- [mass_email.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/staff/communications/mass_email.php)

Checklist:

- [ ] Mở màn hình `Mass Email`.
- [ ] Chọn nhóm `students`.
- [ ] Nhập subject và body HTML đơn giản, ví dụ:

```html
<p>Test Wave 1 mass email</p>
```

- [ ] Bấm `Gửi ngay`.
- [ ] Kiểm tra trạng thái campaign vừa tạo.
- [ ] Mở chi tiết campaign.
- [ ] Kiểm tra số lượng recipient, trạng thái `sent` hoặc `failed`.
- [ ] Lặp lại với nhóm `staff_admin`.

Kỳ vọng:

- có row mới trong `email_campaigns`
- có row chi tiết trong `email_campaign_recipients`
- người gửi nhận thông báo nội bộ tổng hợp kết quả

Lưu ý:

- Nếu `mail()` chưa cấu hình, campaign vẫn được tạo nhưng recipient có thể `failed`.
- Trường hợp này là lỗi môi trường gửi mail, không phải lỗi logic campaign/log.

## 11. Manual Test: Report Preview + Export

Trang liên quan:

- [report_hub.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/staff/report/report_hub.php)
- [finance_report.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/admin/report/finance_report.php)

Checklist:

- [ ] Mở `Trung tâm báo cáo`.
- [ ] Test lần lượt:
  - `finance_summary`
  - `finance_detail`
  - `students_list`
  - `contracts_list`
  - `rooms_occupancy`
- [ ] Với mỗi report:
  - bấm `Xem trước`
  - xác nhận cột hiển thị đúng
  - xác nhận summary/meta có dữ liệu
- [ ] Export `Excel`
- [ ] Mở file `.xlsx` và so với preview
- [ ] Export `PDF`
- [ ] Mở file `.pdf` và so với preview
- [ ] Mở báo cáo tài chính cũ và thử link sang `Trung tâm export mới`

Kỳ vọng:

- preview và file export dùng cùng dataset
- thứ tự dòng giữa preview, xlsx, pdf không bị lệch
- PDF render được tiếng Việt ở mức chấp nhận được trên máy local

## 12. Manual Test: QR Gate Entry/Exit

Trang liên quan:

- [access_qr.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/user/access_qr.php)
- [gate_scanner.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/staff/access/gate_scanner.php)
- [gate_logs.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/staff/access/gate_logs.php)
- [gate_history.php](/c:/laragon/www/WEBQuanLyKyTucXa/modules/user/gate_history.php)

Checklist chuẩn:

- [ ] Ở session `Student`, mở trang `Mã QR cổng`.
- [ ] Xác nhận QR và token dự phòng được tạo.
- [ ] Kiểm tra countdown đang giảm.
- [ ] Ở session `Manager/Admin`, mở trang `Quét QR cổng`.
- [ ] Quét QR bằng camera hoặc dán token dự phòng.
- [ ] Xác nhận kết quả `accepted`.
- [ ] Mở `Nhật ký cổng`, xác nhận có log mới.
- [ ] Quay lại session `Student`, mở `Lịch sử ra/vào`, xác nhận có log mới.
- [ ] Kiểm tra student nhận thông báo nội bộ sau khi quét thành công.

Checklist lỗi biên:

- [ ] Dùng lại token vừa quét, kỳ vọng bị từ chối.
- [ ] Đợi token hết hạn rồi quét lại, kỳ vọng bị từ chối.
- [ ] Quét liên tiếp cùng token/cùng hướng trong vài giây, kỳ vọng bị chặn `duplicate_scan_window`.
- [ ] Để trống token và bấm quét thủ công, kỳ vọng báo lỗi input.

Kỳ vọng:

- token hợp lệ chỉ dùng được một lần
- log rejected được ghi khi token sai/hết hạn/trùng
- page `gate_logs.php` và `gate_history.php` đều thấy dữ liệu tương ứng

## 13. Regression Checklist

- [ ] Bell dropdown của admin vẫn mở/đóng bình thường.
- [ ] Bell dropdown của user vẫn mở/đóng bình thường.
- [ ] Theme toggle không bị hỏng.
- [ ] Mobile drawer ở cả admin shell và user shell vẫn mở được.
- [ ] Dashboard admin mở được sau khi thêm card Wave 1.
- [ ] Dashboard manager mở được sau khi thêm link Wave 1.
- [ ] `finance_report.php` cũ vẫn chạy.

## 14. Nếu Có Lỗi

Kiểm tra nhanh theo thứ tự:

- [ ] Node server có đang chạy không.
- [ ] `.env` đã có đủ key Wave 1 chưa.
- [ ] Sau khi sửa `.env` đã restart `npm start` chưa.
- [ ] Migration đã chạy đúng DB `quanlyktx` chưa.
- [ ] Tài khoản test có đúng role không.
- [ ] Student test có `StudentID`, `IsInDorm = 1`, và có dữ liệu liên quan không.
- [ ] `accesscards` có `CardID` active cho student test không.
- [ ] PHP mail transport đã cấu hình chưa nếu đang test gửi mail thật.

## 15. Done Criteria

Chỉ coi Wave 1 sẵn sàng khi tất cả điều sau đều đạt:

- [ ] Migration chạy thành công.
- [ ] `.env` đã thêm key Wave 1.
- [ ] Node server chạy ổn với websocket/publish endpoint.
- [ ] `npm run test:wave1` pass.
- [ ] Realtime notification hoạt động không cần refresh.
- [ ] Mass email tạo campaign + recipient log đúng.
- [ ] Report preview/export hoạt động cho cả Excel và PDF.
- [ ] QR gate issue/scan/history hoạt động ở cả student và manager/admin.
