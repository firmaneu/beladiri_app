<?php
$conn = new mysqli('localhost', 'root', '', 'beladiri_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$sql = 'ALTER TABLE ranting ADD COLUMN kode_ranting VARCHAR(20) DEFAULT NULL AFTER id';
if ($conn->query($sql) === TRUE) {
    echo 'Kolom kode_ranting berhasil ditambahkan';
} else {
    echo 'Error: ' . $conn->error;
}

$conn->close();
?>