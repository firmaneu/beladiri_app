<?php
$conn = new mysqli('localhost', 'root', '', 'beladiri_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Change jenis_anggota to VARCHAR temporarily to test
$sql = "ALTER TABLE anggota MODIFY COLUMN jenis_anggota VARCHAR(20) NOT NULL DEFAULT 'murid'";
if ($conn->query($sql) === TRUE) {
    echo 'Kolom jenis_anggota berhasil diubah ke VARCHAR(20)';
} else {
    echo 'Error: ' . $conn->error;
}

$conn->close();
?>