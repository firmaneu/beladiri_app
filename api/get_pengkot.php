<?php
header('Content-Type: application/json');

include '../config/database.php';

$pengprov_id = isset($_GET['pengprov_id']) ? (int)$_GET['pengprov_id'] : 0;

if ($pengprov_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pengprov_id']);
    exit();
}

// Query pengkot yang berada di bawah pengprov yang dipilih
$stmt = $conn->prepare("SELECT id, nama_pengurus FROM pengurus WHERE jenis_pengurus = 'kota' AND pengurus_induk_id = ? ORDER BY nama_pengurus");
$stmt->bind_param("i", $pengprov_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>
