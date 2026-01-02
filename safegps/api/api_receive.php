<?php
// File này không sử dụng session nên không cần include db_config.php ngay từ đầu
// để tránh gánh nặng không cần thiết cho mỗi request từ ESP32.

$servername = "localhost";
$username   = "your_db_user";
$password   = "your_db_password";
$dbname     = "your_db_name";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    error_log("Receive API DB connection failed: " . $conn->connect_error);
    exit();
}
$conn->query("SET time_zone = '+07:00'");
$conn->set_charset("utf8mb4");

// --- HELPER FUNCTIONS ---
function isPointInPolygon($point, $polygon) {
    $x = $point[1]; $y = $point[0];
    $intersections = 0;
    $vertices_count = count($polygon);
    for ($i = 0, $j = $vertices_count - 1; $i < $vertices_count; $j = $i++) {
        $vx_i = $polygon[$i][1]; $vy_i = $polygon[$i][0];
        $vx_j = $polygon[$j][1]; $vy_j = $polygon[$j][0];
        if ((($vy_i > $y) != ($vy_j > $y)) && ($x < ($vx_j - $vx_i) * ($y - $vy_i) / ($vy_j - $vy_i) + $vx_i)) {
            $intersections++;
        }
    }
    return ($intersections % 2) != 0;
}

function send_alert_email($to_email, $subject, $message) {
    $from_email = "noreply@" . ($_SERVER['SERVER_NAME'] ?? 'yourdomain.com');
    $headers = "From: SafeGPS Alerts <" . $from_email . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    mail($to_email, $subject, $message, $headers);
}

// --- MAIN PROCESSING ---
header("Content-Type: application/json; charset=UTF-8");
$json = file_get_contents('php://input');
$data = json_decode($json);

if (empty($data->device_id) || !isset($data->lat) || !isset($data->lng)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid data"]);
    exit();
}

// 1. Authenticate device (firmware cũ: chỉ cần device_id, firmware mới nên có api_key)
$stmt_auth = $conn->prepare("SELECT id, user_id, geofence_breach_count FROM devices WHERE device_id = ?");
$stmt_auth->bind_param("s", $data->device_id);
$stmt_auth->execute();
$device_result = $stmt_auth->get_result();

if ($device_result->num_rows == 0) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Device not registered"]);
    exit();
}
$device_info = $device_result->fetch_assoc();
$user_id = $device_info['user_id'];
$breach_count = (int)$device_info['geofence_breach_count'];

// 2. Log GPS history
$stmt_log = $conn->prepare("INSERT INTO gps_history (device_id, latitude, longitude, speed, altitude, reading_time) VALUES (?, ?, ?, ?, ?, NOW())");
$speed = isset($data->speed) ? (float)$data->speed : 0;
$altitude = isset($data->altitude) ? (float)$data->altitude : 0;
$stmt_log->bind_param("sdddd", $data->device_id, $data->lat, $data->lng, $speed, $altitude);
$stmt_log->execute();
$stmt_log->close();

// 3. Process alert rules
$current_time = date('H:i:s');
$current_day_of_week = date('N'); // 1 (Mon) -> 7 (Sun)

$stmt_rules = $conn->prepare("SELECT r.*, g.area_data, u.email FROM alert_rules r LEFT JOIN geofences g ON r.geofence_id = g.id JOIN users u ON r.user_id = u.id WHERE r.device_id = ? AND r.is_active = TRUE AND FIND_IN_SET(?, r.days_of_week) AND ? BETWEEN r.time_start AND r.time_end");
$stmt_rules->bind_param("sis", $data->device_id, $current_day_of_week, $current_time);
$stmt_rules->execute();
$rules = $stmt_rules->get_result();

while ($rule = $rules->fetch_assoc()) {
    $rule_triggered = false;
    $subject = '';
    $message = '';

    // A. Geofence Exit Rule
    if ($rule['rule_type'] == 'geofence_exit' && !empty($rule['area_data'])) {
        $polygon = json_decode($rule['area_data']);
        $point = [$data->lat, $data->lng];
        $is_inside = isPointInPolygon($point, $polygon);

        if (!$is_inside) {
            $breach_count++;
            if ($breach_count == 10) { // Threshold reached
                $last_triggered = strtotime($rule['last_triggered_at'] ?? '2000-01-01');
                if (time() - $last_triggered > 1800) { // Spam guard: 30 minutes
                    $rule_triggered = true;
                    $subject = "[Cảnh báo] Thiết bị đã rời khỏi vùng an toàn";
                    $message = "Thiết bị '{$data->device_id}' đã ở bên ngoài vùng '{$rule['rule_name']}' liên tục. Vị trí cuối: {$data->lat}, {$data->lng}.";
                }
            }
        } else {
            $breach_count = 0; // Reset counter if back inside
        }
        $stmt_update_breach = $conn->prepare("UPDATE devices SET geofence_breach_count = ? WHERE device_id = ?");
        $stmt_update_breach->bind_param("is", $breach_count, $data->device_id);
        $stmt_update_breach->execute();
        $stmt_update_breach->close();
    }
    
    // B. No Movement Rule
    if ($rule['rule_type'] == 'no_movement') {
        if ($speed >= $rule['min_speed'] && $speed <= $rule['max_speed']) {
            $last_triggered = strtotime($rule['last_triggered_at'] ?? '2000-01-01');
            if (time() - $last_triggered > 1800) { // Spam guard: 30 minutes
                 $rule_triggered = true;
                 $subject = "[Cảnh báo] Thiết bị không di chuyển";
                 $message = "Thiết bị '{$data->device_id}' đang không di chuyển (vận tốc {$speed} km/h) trong khung giờ giám sát '{$rule['rule_name']}'. Vị trí: {$data->lat}, {$data->lng}.";
            }
        }
    }

    if ($rule_triggered) {
        send_alert_email($rule['email'], $subject, $message);
        $stmt_update_trigger = $conn->prepare("UPDATE alert_rules SET last_triggered_at = NOW() WHERE id = ?");
        $stmt_update_trigger->bind_param("i", $rule['id']);
        $stmt_update_trigger->execute();
        $stmt_update_trigger->close();
    }
}
$stmt_rules->close();

echo json_encode(["status" => "success", "message" => "Data received"]);
$conn->close();
?>
