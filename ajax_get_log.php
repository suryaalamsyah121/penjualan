<?php
require_once 'config.php';
require_once 'knn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged'])) {
    echo json_encode([]);
    exit;
}

$logs = getLatestLogRekomendasi($conn, 50);

// Format tanggal untuk tampilan
foreach ($logs as &$log) {
    $log['tanggal'] = date('d/m/Y H:i:s', strtotime($log['tanggal']));
}

echo json_encode($logs);
?>