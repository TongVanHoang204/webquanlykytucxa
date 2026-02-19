<?php
session_start();
require_once "db_connect.php";


// ========================
// 1. Cấu hình Google OAuth (đọc từ .env)
// ========================
$env = parse_ini_file(__DIR__ . '/.env');
if (!$env) {
    die('❌ Không tìm thấy file .env hoặc file rỗng.');
}

define('GOOGLE_CLIENT_ID',     $env['GOOGLE_CLIENT_ID']);
define('GOOGLE_CLIENT_SECRET', $env['GOOGLE_CLIENT_SECRET']);
define('GOOGLE_REDIRECT_URI',  $env['GOOGLE_REDIRECT_URI'] ?? 'http://localhost/oauth_google.php');


// ========================
// Hàm gọi POST an toàn
// ========================
function googlePost(string $url, array $data): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ["Content-Type: application/x-www-form-urlencoded"],

        // ⚠️ Fix SSL lỗi trên Windows / Laragon
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $resp = curl_exec($ch);

    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);

        die("❌ LỖI cURL KHI GỌI GOOGLE TOKEN:\n" . $err);
    }

    curl_close($ch);
    return json_decode($resp, true);
}


// ========================
// 2. Nếu chưa có "code" → chuyển người dùng tới Google Login
// ========================
if (!isset($_GET['code'])) {

    $google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'email profile',
        'access_type'   => 'offline',
        'prompt'        => 'consent'
    ]);

    header("Location: $google_auth_url");
    exit;
}



// ========================
// 3. Đổi "code" thành access_token
// ========================
$tokenData = googlePost("https://oauth2.googleapis.com/token", [
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code'
]);

// Nếu Google trả lỗi → in ra để biết
if (isset($tokenData['error'])) {
    echo "<pre>";
    echo "❌ GOOGLE TOKEN ERROR:\n";
    print_r($tokenData);
    echo "</pre>";
    exit;
}

if (empty($tokenData['access_token'])) {
    echo "<pre>";
    echo "❌ Không có access_token từ Google:\n";
    print_r($tokenData);
    echo "</pre>";
    exit;
}

$accessToken = $tokenData['access_token'];



// ========================
// 4. Lấy thông tin user Google
// ========================
$ch = curl_init("https://www.googleapis.com/oauth2/v2/userinfo");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],

    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$user_info = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$user_info || empty($user_info['email'])) {
    die("❌ Không lấy được email từ Google.");
}

$email = $user_info['email'];
$name  = $user_info['name'] ?? "Google User";



// ========================
// 5. Kiểm tra tài khoản
// ========================
$stmt = $conn->prepare("SELECT UserID, FullName, Role FROM Users WHERE Email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    // Đã có tài khoản → login
    $_SESSION['UserID']   = $row['UserID'];
    $_SESSION['FullName'] = $row['FullName'];
    $_SESSION['Role']     = $row['Role'];

} else {

    // Tạo user mới
    $role = "Student";
    $username = explode("@", $email)[0] . rand(100,999);

    $stmt = $conn->prepare("
        INSERT INTO Users (Username, FullName, Email, Role, PasswordHash)
        VALUES (?, ?, ?, ?, '')
    ");
    $stmt->bind_param("ssss", $username, $name, $email, $role);
    $stmt->execute();

    $newID = $stmt->insert_id;

    $_SESSION['UserID']   = $newID;
    $_SESSION['FullName'] = $name;
    $_SESSION['Role']     = $role;
}



// ========================
// 6. Chuyển hướng sau khi login
// ========================
header("Location: index.php");
exit;
