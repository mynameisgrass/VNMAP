<?php
include 'db_config.php';

$data = json_decode(file_get_contents('php://input'));
$email = $data->email ?? '';
$password = $data->password ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Vui lòng nhập email và mật khẩu hợp lệ."]);
    exit();
}

$stmt = $conn->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password_hash'])) {
        // Tạo lại session ID để tăng cường bảo mật
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        echo json_encode(["status" => "success", "message" => "Đăng nhập thành công!"]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Email hoặc mật khẩu không chính xác."]);
    }
} else {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Email hoặc mật khẩu không chính xác."]);
}
$stmt->close();
$conn->close();
?>
