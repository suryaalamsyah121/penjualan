<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bengkel_knn';

// Buat koneksi tanpa database dulu
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

// Buat database jika belum ada
$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

// Buat semua tabel
$sql = "
-- Tabel users (admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Tabel kategori
CREATE TABLE IF NOT EXISTS kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL
);

-- Tabel produk
CREATE TABLE IF NOT EXISTS produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(50) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    harga INT NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    kategori_id INT NULL,
    gambar VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel transaksi
CREATE TABLE IF NOT EXISTS transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(50) NOT NULL UNIQUE,
    total_harga INT NOT NULL,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel detail transaksi
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    produk_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga_saat_beli INT NOT NULL
);

-- Tabel rating dan komentar
CREATE TABLE IF NOT EXISTS rating_komen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL UNIQUE,
    rating INT NOT NULL,
    komen TEXT,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel log rekomendasi KNN
CREATE TABLE IF NOT EXISTS log_rekomendasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produk_asal_id INT NOT NULL,
    produk_rekomendasi_id INT NOT NULL,
    skor DECIMAL(5,2),
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

// Jalankan SQL
if ($conn->multi_query($sql)) {
    do {
        // Bersihkan hasil
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) $result->free();
        }
    } while ($conn->next_result());
}

// Insert data default
$conn->query("INSERT IGNORE INTO users (username, password) VALUES ('admin', MD5('admin123'))");
$conn->query("INSERT IGNORE INTO kategori (nama) VALUES ('Mesin'), ('Kelistrikan'), ('Kaki-kaki'), ('Body'), ('Pelumas')");

// Cek apakah produk sudah ada
$cek = $conn->query("SELECT COUNT(*) as total FROM produk")->fetch_assoc()['total'];
if ($cek == 0) {
    $conn->query("INSERT INTO produk (barcode, nama, harga, stok, kategori_id, gambar) VALUES
    ('8991234567890', 'Kampas Rem Depan', 85000, 10, 3, 'default.png'),
    ('8991234567891', 'Oli Mesin 1L', 65000, 20, 1, 'default.png'),
    ('8991234567892', 'Busi Iridium', 120000, 15, 2, 'default.png'),
    ('8991234567893', 'Filter Udara', 45000, 8, 1, 'default.png'),
    ('8991234567894', 'Aki Kering', 350000, 5, 2, 'default.png')");
}

// Buat folder uploads
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

echo "<h2 style='color:green;'>✅ INSTALLASI BERHASIL!</h2>";
echo "<p>Database dan tabel telah dibuat.</p>";
echo "<p>Username Admin: <strong>admin</strong></p>";
echo "<p>Password Admin: <strong>admin123</strong></p>";
echo "<a href='index.php'>Klik disini untuk ke halaman utama</a>";
?>