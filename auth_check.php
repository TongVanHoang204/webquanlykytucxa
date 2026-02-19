<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * ===========================================================
 *  HỆ THỐNG KIỂM TRA XÁC THỰC / PHÂN QUYỀN CHO TOÀN BỘ WEBSITE
 * ===========================================================
 * 
 * Các hàm:
 *  - requireLogin() : bắt buộc đăng nhập
 *  - requireRole()  : kiểm tra vai trò (Admin / Manager / Student)
 *  - isLoggedIn()   : kiểm tra nhanh trạng thái đăng nhập
 * 
 * Gọi dùng:
 *  require_once '../../includes/auth_check.php';
 *  requireLogin();
 *  requireRole(['Admin', 'Manager']);
 */

/**
 * Kiểm tra người dùng đã đăng nhập chưa.
 * Nếu chưa → chuyển hướng về trang login.
 */
function requireLogin(): void {
    if (empty($_SESSION['UserID'])) {
        header("Location: /login.php");
        exit;
    }
}


/**
 * Kiểm tra vai trò người dùng (Admin, Manager, Student)
 * @param array $roles Danh sách vai trò hợp lệ
 */
function requireRole(array $roles): void {
    if (empty($_SESSION['Role']) || !in_array($_SESSION['Role'], $roles, true)) {
        http_response_code(403);
        echo "<!DOCTYPE html><html><body style='font-family: sans-serif; text-align:center; padding:50px;'>
                <h2 style='color:#d00;'>🚫 Truy cập bị từ chối</h2>
                <p>Bạn không có quyền truy cập trang này.</p>
                <a href='javascript:history.back()' style='color:#2563eb;'>← Quay lại</a>
              </body></html>";
        exit;
    }
}

/**
 * Kiểm tra xem người dùng đã đăng nhập chưa (trả về true/false)
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['UserID']);
}

/**
 * Lấy tên vai trò hiện tại
 */
function currentRole(): ?string {
    return $_SESSION['Role'] ?? null;
}

/**
 * Lấy ID người dùng hiện tại
 */
function currentUserID(): ?int {
    return $_SESSION['UserID'] ?? null;
}

