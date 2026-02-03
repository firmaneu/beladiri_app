<?php
$conn = new mysqli('localhost', 'root', '', 'beladiri_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// First, check if column exists and modify its length
$sql = "ALTER TABLE ranting MODIFY COLUMN kode_ranting VARCHAR(50) DEFAULT NULL";
if ($conn->query($sql) === TRUE) {
    echo 'Kolom kode_ranting berhasil diperpanjang menjadi VARCHAR(50)';
} else {
    echo 'Error: ' . $conn->error;
}

$conn->close();
?>