<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('wantsJsonResponse')) {
    function wantsJsonResponse(): bool
    {
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        return $xhr || strpos($accept, 'application/json') !== false;
    }
}

if (!function_exists('sendRequestError')) {
    function sendRequestError(string $message, int $status = 400): void
    {
        http_response_code($status);

        if (wantsJsonResponse()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Lỗi yêu cầu</title></head><body style='font-family:sans-serif;text-align:center;padding:48px;'>";
        echo "<h2 style='color:#b91c1c;margin-bottom:12px;'>Yêu cầu bị từ chối</h2>";
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        echo "<p style='margin-top:20px;'><a href='javascript:history.back()'>Quay lại</a></p>";
        echo '</body></html>';
        exit;
    }
}

if (!function_exists('ensureCsrfToken')) {
    function ensureCsrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['_csrf'];
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        return ensureCsrfToken();
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        if (!empty($_SESSION['UserID'])) {
            return;
        }

        if (wantsJsonResponse()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'Bạn chưa đăng nhập.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: /login.php');
        exit;
    }
}

if (!function_exists('requireRole')) {
    function requireRole(array $roles = []): void
    {
        requireLogin();

        if ($roles && !in_array($_SESSION['Role'] ?? null, $roles, true)) {
            sendRequestError('Bạn không có quyền truy cập tài nguyên này.', 403);
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        requireRole(['Admin']);
    }
}

if (!function_exists('requirePost')) {
    function requirePost(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            sendRequestError('Phương thức không hợp lệ.', 405);
        }
    }
}

if (!function_exists('requireCsrf')) {
    function requireCsrf(?string $token = null): void
    {
        $sessionToken = (string)($_SESSION['_csrf'] ?? '');
        $providedToken = $token;

        if ($providedToken === null) {
            $providedToken = (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        }

        if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
            sendRequestError('CSRF token không hợp lệ. Vui lòng tải lại trang.', 400);
        }
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return !empty($_SESSION['UserID']);
    }
}

if (!function_exists('currentRole')) {
    function currentRole(): ?string
    {
        return $_SESSION['Role'] ?? null;
    }
}

if (!function_exists('currentUserID')) {
    function currentUserID(): ?int
    {
        return isset($_SESSION['UserID']) ? (int)$_SESSION['UserID'] : null;
    }
}
