<?php
require_once 'config.php';
require_once 'knn.php';

if(!isset($_SESSION['admin_logged'])) die("Akses ditolak");

$type = $_GET['type'] ?? 'transaksi';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=laporan_$type.xls");

if($type == 'transaksi') {
    echo "No Transaksi\tTanggal\tTotal\tRating\tKomentar\n";
    $data = $conn->query("
        SELECT t.kode_transaksi, t.tanggal, t.total_harga, r.rating, r.komen
        FROM transaksi t
        LEFT JOIN rating_komen r ON t.id = r.transaksi_id
        ORDER BY t.tanggal DESC
    ");
    while($row = $data->fetch_assoc()) {
        echo "{$row['kode_transaksi']}\t{$row['tanggal']}\t{$row['total_harga']}\t{$row['rating']}\t{$row['komen']}\n";
    }
} 
elseif($type == 'produk') {
    echo "ID\tNama Produk\tHarga\tStok\tTotal Terjual\n";
    $data = $conn->query("
        SELECT p.id, p.nama, p.harga, p.stok, COALESCE(SUM(dt.jumlah),0) as terjual
        FROM produk p
        LEFT JOIN detail_transaksi dt ON p.id = dt.produk_id
        GROUP BY p.id
    ");
    while($row = $data->fetch_assoc()) {
        echo "{$row['id']}\t{$row['nama']}\t{$row['harga']}\t{$row['stok']}\t{$row['terjual']}\n";
    }
}
elseif($type == 'rekomendasi') {
    echo "Produk Asal\tProduk Rekomendasi\tSkor Rekomendasi\n";
    $log = $conn->query("
        SELECT p1.nama as asal, p2.nama as rekom, l.skor
        FROM log_rekomendasi l
        JOIN produk p1 ON l.produk_asal_id = p1.id
        JOIN produk p2 ON l.produk_rekomendasi_id = p2.id
        ORDER BY l.tanggal DESC LIMIT 50
    ");
    while($row = $log->fetch_assoc()) {
        echo "{$row['asal']}\t{$row['rekom']}\t{$row['skor']}%\n";
    }
}
?>