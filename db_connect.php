<?php
$envPath = __DIR__ . '/.env';
$envConfig = is_file($envPath) ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: []) : [];

$dbValue = static function (string $key, string $default = '') use ($envConfig): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }

    if (isset($envConfig[$key]) && $envConfig[$key] !== '') {
        return (string) $envConfig[$key];
    }

    return $default;
};

$servername = $dbValue('DB_HOST', '127.0.0.1');
$username = $dbValue('DB_USER', 'root');
$password = $dbValue('DB_PASS', '');
$database = $dbValue('DB_NAME', 'quanlyktx');
$port = (int) $dbValue('DB_PORT', '3306');

require_once __DIR__ . '/includes/log_helper.php';

$conn = new mysqli($servername, $username, $password, $database, $port);
if ($conn->connect_error) {
    die("❌ Lỗi kết nối: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


?>
