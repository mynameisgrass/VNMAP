<?php
include 'db_config.php';
require_login();

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'));

$device_id = $_GET['device_id'] ?? ($data->device_id ?? null);
if (!$device_id) { http_response_code(400); exit(); }

// Security check: Make sure user owns this device
$stmt_check = $conn->prepare("SELECT id FROM devices WHERE user_id = ? AND device_id = ?");
$stmt_check->bind_param("is", $user_id, $device_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows == 0) { http_response_code(403); exit(); }

switch ($method) {
    case 'GET':
        $stmt = $conn->prepare("SELECT id, name, area_data FROM geofences WHERE user_id = ? AND device_id = ?");
        $stmt->bind_param("is", $user_id, $device_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $geofences = [];
        while($row = $result->fetch_assoc()) {
            $row['area_data'] = json_decode($row['area_data']); // Convert JSON string to array
            $geofences[] = $row;
        }
        echo json_encode($geofences);
        break;

    case 'POST':
        $name = $data->name ?? '';
        $area_data_json = json_encode($data->area_data ?? []);
        if (empty($name) || empty($data->area_data)) { /* ... validation ... */ exit(); }

        $stmt = $conn->prepare("INSERT INTO geofences (user_id, device_id, name, area_data) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $device_id, $name, $area_data_json);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Đã tạo vùng an toàn.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi tạo vùng.']);
        }
        break;

    case 'DELETE':
        $geofence_id = $data->id ?? 0;
        if ($geofence_id <= 0) { /* ... validation ... */ exit(); }
        
        // Also delete associated rules
        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("DELETE FROM alert_rules WHERE geofence_id = ? AND user_id = ?");
            $stmt1->bind_param("ii", $geofence_id, $user_id);
            $stmt1->execute();

            $stmt2 = $conn->prepare("DELETE FROM geofences WHERE id = ? AND user_id = ?");
            $stmt2->bind_param("ii", $geofence_id, $user_id);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa vùng và các luật liên quan.']);
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi xóa vùng.']);
        }
        break;
}
$conn->close();
?>