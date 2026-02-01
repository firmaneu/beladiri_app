<?php
// File: api/get_penyelenggara.php
// API untuk mendapatkan daftar penyelenggara berdasarkan jenis pengurus

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include '../config/database.php';

$jenis_pengurus = $_GET['jenis_pengurus'] ?? '';

if (empty($jenis_pengurus)) {
    http_response_code(400);
    echo json_encode(['error' => 'Jenis pengurus tidak diberikan']);
    exit();
}

// Validasi jenis pengurus
$allowed_types = ['pusat', 'provinsi', 'kota'];
if (!in_array($jenis_pengurus, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Jenis pengurus tidak valid']);
    exit();
}

// Query untuk mendapatkan daftar pengurus berdasarkan jenis
$sql = "SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = ? ORDER BY nama_pengurus ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $jenis_pengurus);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => (int)$row['id'],
        'nama' => $row['nama_pengurus']
    ];
}

echo json_encode(['success' => true, 'data' => $data]);
$stmt->close();
?>