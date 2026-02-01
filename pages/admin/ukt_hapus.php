<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';

// Initialize permission manager
$permission_manager = new PermissionManager(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['pengurus_id'] ?? null,
    $_SESSION['ranting_id'] ?? null
);

// Check permission untuk action ini - hanya admin yang bisa hapus
if ($_SESSION['role'] != 'admin') {
    header("Location: ukt.php?msg=forbidden");
    exit();
}

$id = (int)$_GET['id'];

// Ambil data UKT
$ukt_result = $conn->query("SELECT * FROM ukt WHERE id = $id");

if ($ukt_result->num_rows == 0) {
    header("Location: ukt.php?msg=notfound");
    exit();
}

$ukt = $ukt_result->fetch_assoc();

// Delete semua peserta UKT terlebih dahulu
$conn->query("DELETE FROM ukt_peserta WHERE ukt_id = $id");

// Hapus data UKT
$delete_result = $conn->query("DELETE FROM ukt WHERE id = $id");

if ($delete_result) {
    header("Location: ukt.php?msg=deleted");
    exit();
} else {
    header("Location: ukt.php?msg=error");
    exit();
}
?>