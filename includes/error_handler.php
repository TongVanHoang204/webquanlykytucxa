<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/*
 |==============================================================
 |   ERROR HANDLER TỰ ĐỘNG
 |==============================================================
 |  - Ghi lỗi vào database (LogType = 'system')
 |  - Ghi lại IP, User-Agent
 |  - Không để lộ lỗi thật cho người dùng
 |  - Xuất thông báo đẹp
*/

require_once __DIR__ . '/../db_connect.php';     // đi lên để lấy db_connect.php
require_once __DIR__ . '/log_helper.php';        // cùng thư mục


// Ensure $conn is assigned from db_connect.php
if (!isset($conn)) {
    global $conn;
}

/* --------------------------------------------------------------
   PHÁT HIỆN LỖI PHP
--------------------------------------------------------------- */
set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($conn) {

    $userId = $_SESSION['UserID'] ?? 0;

    $msg = "[PHP ERROR] $errstr in $errfile at line $errline";

    // Ghi vào logs hệ thống
    addLog($conn, $userId, 'PHP Error', 'System', $msg, 'system');

    error_display($msg);
});


/* --------------------------------------------------------------
   PHÁT HIỆN FATAL ERROR / EXCEPTION
--------------------------------------------------------------- */
register_shutdown_function(function () use ($conn) {

    $error = error_get_last();

    if ($error !== null) {
        $userId = $_SESSION['UserID'] ?? 0;

        $msg = "[FATAL ERROR] {$error['message']} in {$error['file']} at line {$error['line']}";

        addLog($conn, $userId, 'Fatal Error', 'System', $msg, 'system');

        error_display($msg);
    }
});


/* --------------------------------------------------------------
   CUSTOM EXCEPTION HANDLER
--------------------------------------------------------------- */
set_exception_handler(function ($ex) use ($conn) {

    $userId = $_SESSION['UserID'] ?? 0;

    $msg = "[EXCEPTION] " . $ex->getMessage() . " in " . $ex->getFile() . " at line " . $ex->getLine();

    addLog($conn, $userId, 'Exception', 'System', $msg, 'system');

    error_display($msg);
});



/* --------------------------------------------------------------
   HÀM HIỂN THỊ LỖI ĐẸP – ĐƯỢC BẢO VỆ 
--------------------------------------------------------------- */
function error_display($message)
{
    http_response_code(500);

    // Ẩn thông tin nội bộ, chỉ hiển thị chung chung
    echo "
        <div style='
            padding:20px;
            margin:40px auto;
            width:600px;
            background:#ffecec;
            color:#c00;
            border-left:5px solid #e00;
            font-family:Arial;
            border-radius:8px;
        '>
            <h2>⚠️ Đã xảy ra lỗi hệ thống!</h2>
            <p>Vui lòng thử lại hoặc liên hệ quản trị viên.</p>
            <hr>
            <small style='color:#777'>
                Chi tiết lỗi đã được ghi lại vào hệ thống.
            </small>
        </div>
    ";

    exit;
}


?>
