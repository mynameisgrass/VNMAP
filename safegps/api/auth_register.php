<?php
include 'db_config.php';
$data = json_decode(file_get_contents('php://input'));
$email = $data->email ?? '';
$password = $data->password ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Vui lòng nhập email hợp lệ."]);
    exit();
}
if (empty($password) || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Mật khẩu phải có ít nhất 6 ký tự."]);
    exit();
}

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(["status" => "error", "message" => "Email này đã được đăng ký."]);
    $stmt->close();
    $conn->close();
    exit();
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $password_hash);
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Đăng ký thành công, vui lòng đăng nhập."]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Đã có lỗi xảy ra, vui lòng thử lại."]);
}
$stmt->close();
$conn->close();
?>
