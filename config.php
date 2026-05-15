<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bengkel_knn2';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

session_start();

// Folder upload gambar
define('UPLOAD_DIR', 'uploads/');
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
?>