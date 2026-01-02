<?php
include 'db_config.php';
require_login(); // Yêu cầu đăng nhập

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'));

switch ($method) {
    case 'GET':
        $stmt = $conn->prepare("SELECT device_id, device_disp, api_key FROM devices WHERE user_id = ? ORDER BY device_disp ASC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $devices = [];
        while($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
        echo json_encode($devices);
        break;

    case 'POST': // Thêm mới thiết bị
        $device_id = $data->device_id ?? '';
        $device_disp = $data->device_disp ?? '';
        if (empty($device_id) || empty($device_disp)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đủ thông tin.']);
            exit();
        }
        // Tạo API Key ngẫu nhiên, bảo mật
        $api_key = bin2hex(random_bytes(32)); 
        
        $stmt = $conn->prepare("INSERT INTO devices (user_id, device_id, device_disp, api_key) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $device_id, $device_disp, $api_key);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Thêm thiết bị thành công.', 'api_key' => $api_key]);
        } else {
            http_response_code(409); // Conflict if device_id is not unique
            echo json_encode(['status' => 'error', 'message' => 'Device ID đã tồn tại.']);
        }
        break;

    case 'PUT': // Cập nhật tên thiết bị
        $device_id = $data->device_id ?? '';
        $device_disp = $data->device_disp ?? '';
        if (empty($device_id) || empty($device_disp)) { /* ... validation ... */ exit(); }

        $stmt = $conn->prepare("UPDATE devices SET device_disp = ? WHERE device_id = ? AND user_id = ?");
        $stmt->bind_param("ssi", $device_disp, $device_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi cập nhật.']);
        }
        break;

    case 'DELETE': // Xóa thiết bị
        $device_id = $data->device_id ?? '';
        if (empty($device_id)) { /* ... validation ... */ exit(); }
        
        // Cần xóa cả trong các bảng liên quan: geofences, alert_rules, gps_history
        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("DELETE FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt1->bind_param("si", $device_id, $user_id);
            $stmt1->execute();

            $stmt2 = $conn->prepare("DELETE FROM geofences WHERE device_id = ? AND user_id = ?");
            $stmt2->bind_param("si", $device_id, $user_id);
            $stmt2->execute();
            
            $stmt3 = $conn->prepare("DELETE FROM alert_rules WHERE device_id = ? AND user_id = ?");
            $stmt3->bind_param("si", $device_id, $user_id);
            $stmt3->execute();

            // Tùy chọn: Xóa cả lịch sử di chuyển
            // $stmt4 = $conn->prepare("DELETE FROM gps_history WHERE device_id = ?");
            // $stmt4->bind_param("s", $device_id);
            // $stmt4->execute();
            
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa thiết bị và các dữ liệu liên quan.']);
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa dữ liệu.']);
        }
        break;
}

$conn->close();
?>
