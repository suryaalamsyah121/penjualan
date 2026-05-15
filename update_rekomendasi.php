<?php
require_once 'config.php';
require_once 'knn.php';

// Hanya admin yang bisa akses
if (!isset($_SESSION['admin_logged'])) {
    die("Akses ditolak. <a href='admin.php'>Login sebagai admin</a>");
}

$action = $_GET['action'] ?? '';

if ($action == 'update') {
    // Hapus log lama
    $conn->query("TRUNCATE TABLE log_rekomendasi");
    
    // Update semua rekomendasi
    $updated = batchUpdateRekomendasi($conn);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Update Rekomendasi KNN</title>
        <style>
            body { font-family: Arial; background: #0f0f1a; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; }
            .box { background: #1a1a2e; padding: 30px; border-radius: 20px; text-align: center; border: 1px solid #4ade80; }
            .success { color: #4ade80; }
            button { background: #e94560; border: none; padding: 12px 24px; border-radius: 10px; color: white; font-size: 16px; cursor: pointer; margin-top: 20px; }
            a { color: #e94560; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='box'>
            <h2 class='success'>✅ Update Rekomendasi KNN Selesai!</h2>
            <p>Total rekomendasi yang dihasilkan: <strong>$updated</strong></p>
            <p>Waktu update: " . date('Y-m-d H:i:s') . "</p>
            <button onclick=\"location.href='admin.php'\">🔙 Kembali ke Dashboard</button>
        </div>
    </body>
    </html>";
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Update Rekomendasi KNN</title>
        <style>
            body { font-family: Arial; background: #0f0f1a; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; }
            .box { background: #1a1a2e; padding: 30px; border-radius: 20px; text-align: center; border: 1px solid #e94560; max-width: 500px; }
            button { background: #e94560; border: none; padding: 12px 24px; border-radius: 10px; color: white; font-size: 16px; cursor: pointer; margin-top: 20px; }
            button:hover { background: #ff6b8a; }
            ul { text-align: left; margin-top: 20px; color: #aaa; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>🔄 Update Rekomendasi KNN</h2>
            <p>Fitur ini akan menghitung ulang semua rekomendasi produk berdasarkan data rating terbaru.</p>
            <ul>
                <li>✓ Menghapus log rekomendasi lama</li>
                <li>✓ Menghitung ulang rekomendasi untuk semua produk</li>
                <li>✓ Menyimpan hasil terbaru ke database</li>
            </ul>
            <button onclick="location.href='?action=update'">🚀 Jalankan Update Sekarang</button>
            <p style="margin-top: 20px;"><a href="admin.php">← Kembali ke Dashboard</a></p>
        </div>
    </body>
    </html>
    <?php
}
?>