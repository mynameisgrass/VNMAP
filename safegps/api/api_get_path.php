<?php
include 'db_config.php';
require_login();

$user_id = $_SESSION['user_id'];
$device_id = $_GET['device_id'] ?? '';
$range = $_GET['range'] ?? 'today';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

if (empty($device_id)) { http_response_code(400); echo json_encode(['message' => 'Thiếu device_id']); exit(); }

// Security Check: User must own the device
$stmt_check = $conn->prepare("SELECT id FROM devices WHERE user_id = ? AND device_id = ?");
$stmt_check->bind_param("is", $user_id, $device_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows == 0) { http_response_code(403); echo json_encode(['message' => 'Không có quyền truy cập']); exit(); }
$stmt_check->close();

$sql = "SELECT latitude, longitude, speed, altitude, reading_time FROM gps_history WHERE device_id = ?";
switch ($range) {
    case 'yesterday':
        $sql .= " AND DATE(reading_time) = CURDATE() - INTERVAL 1 DAY";
        break;
    case 'last7days':
        $sql .= " AND reading_time >= CURDATE() - INTERVAL 7 DAY";
        break;
    case 'today':
    default:
        $sql .= " AND DATE(reading_time) = CURDATE()";
        break;
}
$sql .= " ORDER BY reading_time " . ($limit ? "DESC" : "ASC");
if ($limit) {
    $sql .= " LIMIT " . $limit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $device_id);
$stmt->execute();
$result = $stmt->get_result();

$path_data = [];
while ($row = $result->fetch_assoc()) {
    $path_data[] = [
        'lat' => (float)$row['latitude'],
        'lng' => (float)$row['longitude'],
        'speed' => $row['speed'] ? round($row['speed'], 2) : 0,
        'altitude' => $row['altitude'] ? round($row['altitude'], 2) : 0,
        'ts' => $row['reading_time']
    ];
}

// Nếu có limit (dùng cho auto-refresh), kết quả đang bị ngược, cần đảo lại
if ($limit) {
    $path_data = array_reverse($path_data);
}

echo json_encode($path_data);
$stmt->close();
$conn->close();
?>
