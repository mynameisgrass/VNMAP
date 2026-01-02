<?php
// Allow cross-origin requests (optional but good for local testing)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require 'db_connect.php';

// IMPORTANT: Force UTF-8 to handle Vietnamese characters properly
$conn->set_charset("utf8mb4");

$action = isset($_GET['action']) ? $_GET['action'] : '';

// =================================================================
// CASE 1: Get List of All Provinces (For the Dropdown)
// =================================================================
if ($action == 'get_all_provinces') {
    // 'ten_tinh' no longer exists, we select 'Tong_quan_tinh_thanh_pho'
    // 'ma_tinh' no longer exists as a column, we use 'id'
    $sql = "SELECT id, Tong_quan_tinh_thanh_pho FROM provinces ORDER BY id ASC";
    
    $result = $conn->query($sql);
    $provinces = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $provinces[] = [
                // Map 'id' to the value the dropdown needs
                "id" => $row['id'], 
                // Map the merged name string (e.g., "Hà Nội (Mã tỉnh: 1)")
                "Tong_quan_tinh_thanh_pho" => $row['Tong_quan_tinh_thanh_pho']
            ];
        }
    }
    
    // Return JSON
    echo json_encode(["Province List" => $provinces]);

// =================================================================
// CASE 2: Get Details of ONE Province (For the Form)
// =================================================================
} elseif ($action == 'get_province_details') {
    
    // We must use 'id' now, because 'ma_tinh' column is gone.
    // Check if 'id' is passed, OR if 'ma_tinh' is passed (for backward compatibility)
    $id = 0;
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
    } elseif (isset($_GET['ma_tinh'])) {
        $id = intval($_GET['ma_tinh']); // Assuming id matches ma_tinh in your data
    }

    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM provinces WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = $result->fetch_assoc();
        
        echo json_encode($details);
        $stmt->close();
    } else {
        echo json_encode(["error" => "Missing ID"]);
    }
}

$conn->close();
?>