<?php
include 'db_config.php';
require_login();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'));

$device_id = $_GET['device_id'] ?? ($data->device_id ?? null);
if (!$device_id) { http_response_code(400); exit(); }

// Security check
$stmt_check = $conn->prepare("SELECT id FROM devices WHERE user_id = ? AND device_id = ?");
$stmt_check->bind_param("is", $user_id, $device_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows == 0) { http_response_code(403); exit(); }

switch ($method) {
    case 'GET':
        $stmt = $conn->prepare("SELECT * FROM alert_rules WHERE user_id = ? AND device_id = ?");
        $stmt->bind_param("is", $user_id, $device_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rules = [];
        while($row = $result->fetch_assoc()) {
            $rules[] = $row;
        }
        echo json_encode($rules);
        break;

    case 'POST':
        // Dữ liệu cần thiết cho một luật
        $rule_name = $data->rule_name ?? '';
        $rule_type = $data->rule_type ?? '';
        $time_start = $data->time_start ?? '';
        $time_end = $data->time_end ?? '';
        $days_of_week = implode(',', $data->days_of_week ?? []);
        
        // Dữ liệu tùy chọn
        $geofence_id = ($rule_type == 'geofence_exit') ? ($data->geofence_id ?? null) : null;
        $min_speed = ($rule_type == 'no_movement') ? ($data->min_speed ?? 0) : null;
        $max_speed = ($rule_type == 'no_movement') ? ($data->max_speed ?? 1) : null;

        if (empty($rule_name) || empty($rule_type) || empty($time_start) || empty($time_end) || empty($days_of_week)) {
             http_response_code(400); echo json_encode(['message'=>'Thiếu thông tin']); exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO alert_rules (user_id, device_id, rule_name, rule_type, geofence_id, time_start, time_end, days_of_week, min_speed, max_speed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisssdd", $user_id, $device_id, $rule_name, $rule_type, $geofence_id, $time_start, $time_end, $days_of_week, $min_speed, $max_speed);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Đã tạo luật cảnh báo.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi tạo luật.']);
        }
        break;

    case 'DELETE':
        $rule_id = $data->id ?? 0;
        if ($rule_id <= 0) { http_response_code(400); exit(); }

        $stmt = $conn->prepare("DELETE FROM alert_rules WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $rule_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa luật.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi xóa luật.']);
        }
        break;
}
$conn->close();
?>
