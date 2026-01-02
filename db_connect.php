<?php
// Cho phép các trang web khác (ví dụ: trang GeoView của bạn) gọi đến API này
header("Access-Control-Allow-Origin: *"); 
// Định dạng phản hồi luôn là JSON
header("Content-Type: application/json; charset=UTF-8");

// --- THAY ĐỔI CÁC THÔNG SỐ NÀY CHO PHÙ HỢP VỚI BẠN ---
$servername = "localhost";      // Thường là "localhost"
$username   = "uodybfyn_myuser";           // Tên người dùng database (mặc định của XAMPP là "root")
$password   = "map@2025";               // Mật khẩu database (mặc định của XAMPP là trống)
$dbname     = "uodybfyn_geoview_db";     // Tên database bạn đã tạo ở trên
// ----------------------------------------------------

// Tạo kết nối đến database
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra nếu có lỗi kết nối thì dừng chương trình và báo lỗi
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Thiết lập encoding UTF-8 để hiển thị tiếng Việt không bị lỗi font
$conn->set_charset("utf8mb4");
?>