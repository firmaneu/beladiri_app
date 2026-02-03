<?php
header('Content-Type: application/json');
include '../config/database.php';

$ranting_id = (int)$_GET['ranting_id'];
$tahun = (int)$_GET['tahun'];

if (!$ranting_id || !$tahun) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Get the highest number for this ranting and year
$sql = "SELECT MAX(CAST(SUBSTRING(no_anggota, -3) AS UNSIGNED)) as max_number
        FROM anggota
        WHERE ranting_awal_id = ? AND tahun_bergabung = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $ranting_id, $tahun);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$next_number = ($row['max_number'] ?? 0) + 1;

echo json_encode(['success' => true, 'next_number' => $next_number]);
?>