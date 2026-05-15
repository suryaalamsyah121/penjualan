<?php
// File ini bisa dijalankan otomatis setiap hari via cron job
// Atau diakses manual untuk update berkala

require_once 'config.php';
require_once 'knn.php';

// Tidak perlu login, bisa dijalankan via cron
$conn->query("TRUNCATE TABLE log_rekomendasi");
$updated = batchUpdateRekomendasi($conn);

// Catat log
$log = date('Y-m-d H:i:s') . " - Update rekomendasi KNN selesai. Total: $updated rekomendasi\n";
file_put_contents('knn_update_log.txt', $log, FILE_APPEND);

echo "Update selesai pada " . date('Y-m-d H:i:s') . "\n";
echo "Total rekomendasi: $updated\n";
?>