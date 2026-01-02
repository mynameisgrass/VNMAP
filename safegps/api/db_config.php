<?php
// Bắt đầu session ở file config để đảm bảo nó được gọi ở mọi nơi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Thiết lập header mặc định
header("Content-Type: application/json; charset=UTF-8");

$servername = "localhost";
$username   = "uodybfyn_gps";     // THAY THẾ BẰNG USERNAME CỦA BẠN
$password   = "o49qJ4IksoY~21hc"; // THAY THẾ BẰNG MẬT KHẨU CỦA BẠN
$dbname     = "uodybfyn_tracker";     // THAY THẾ BẰNG TÊN DATABASE CỦA BẠN

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    http_response_code(500);
    // Ghi lỗi ra file log thay vì hiển thị cho người dùng
    error_log("Database connection failed: " . $conn->connect_error);
    // Trả về một thông báo lỗi chung
    die(json_encode(["status" => "error", "message" => "Lỗi hệ thống, không thể kết nối đến cơ sở dữ liệu."]));
}

// Đặt múi giờ mặc định cho tất cả các thao tác ngày giờ trong PHP và MySQL
date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn->query("SET time_zone = '+07:00'");
$conn->set_charset("utf8mb4");

// Hàm kiểm tra đăng nhập, dùng cho các API cần bảo vệ
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(["status" => "error", "message" => "Yêu cầu đăng nhập."]);
        exit();
    }
}
?>
