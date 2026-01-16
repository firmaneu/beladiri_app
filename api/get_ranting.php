<?php
header('Content-Type: application/json');

include '../config/database.php';

$pengkot_id = isset($_GET['pengkot_id']) ? (int)$_GET['pengkot_id'] : 0;

if ($pengkot_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pengkot_id']);
    exit();
}

// Query ranting yang berada di bawah pengkot yang dipilih
$result = $conn->query("SELECT id, nama_ranting FROM ranting WHERE pengurus_kota_id = $pengkot_id ORDER BY nama_ranting");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>