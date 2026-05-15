<?php
echo "<h1>Test Server</h1>";
echo "<p>PHP berjalan dengan baik</p>";
echo "<p>Waktu server: " . date('Y-m-d H:i:s') . "</p>";

// Cek koneksi database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'bengkel_knn2';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo "<p style='color:red'>Database error: " . $conn->connect_error . "</p>";
    echo "<p>Silakan jalankan <a href='install.php'>install.php</a> terlebih dahulu</p>";
} else {
    echo "<p style='color:green'>Database connected successfully!</p>";
}
?>