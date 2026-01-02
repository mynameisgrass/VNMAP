<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

require 'db_connect.php';

// IMPORTANT: Force UTF-8 to handle Vietnamese characters
$conn->set_charset("utf8mb4");

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Thiếu ID tỉnh thành.']);
    exit;
}

// UPDATED: Table name is now 'provinces'
$sql = "UPDATE provinces SET 
    Vi_tri_dia_ly = ?, 
    Dieu_kien_tu_nhien = ?,
    Dan_cu_va_xa_hoi = ?,
    Kinh_te = ?,
    Lich_su_hinh_thanh = ?,
    Van_hoa_du_lich = ?,
    Thong_tin_truoc_sat_nhap = ?,
    Thong_tin_sau_sat_nhap = ?,
    Thong_tin_chi_tiet_xa_phuong = ?,
    nguon = ?
    WHERE id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
    exit;
}

// Bind parameters (10 strings, 1 integer)
$stmt->bind_param("ssssssssssi", 
    $data['Vi_tri_dia_ly'],
    $data['Dieu_kien_tu_nhien'],
    $data['Dan_cu_va_xa_hoi'],
    $data['Kinh_te'],
    $data['Lich_su_hinh_thanh'],
    $data['Van_hoa_du_lich'],
    $data['Thong_tin_truoc_sat_nhap'],
    $data['Thong_tin_sau_sat_nhap'],
    $data['Thong_tin_chi_tiet_xa_phuong'],
    $data['nguon'],
    $data['id']
);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Cập nhật dữ liệu thành công!']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Cập nhật thất bại: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>