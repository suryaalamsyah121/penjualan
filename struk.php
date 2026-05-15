<?php
require_once 'config.php';
$id = $_GET['id'] ?? 0;
$trans = $conn->query("SELECT * FROM transaksi WHERE id=$id");
$tr = $trans->fetch_assoc();
if(!$tr) die("Transaksi tidak ditemukan");

$detail = $conn->query("SELECT dt.*, p.nama FROM detail_transaksi dt JOIN produk p ON dt.produk_id=p.id WHERE dt.transaksi_id=$id");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Struk Pembelian</title>
    <style>
        body { font-family: monospace; width: 300px; margin: auto; padding: 20px; background: #0f0f1a; color: #e0e0e0; }
        .struk { border: 1px dashed #e94560; padding: 15px; background: #1a1a2e; border-radius: 10px; }
        .header { text-align: center; border-bottom: 1px dotted #e94560; margin-bottom: 10px; }
        .item { display: flex; justify-content: space-between; font-size: 12px; margin: 5px 0; }
        .total { border-top: 1px solid #e94560; margin-top: 10px; padding-top: 5px; font-weight: bold; }
        button { margin-top: 20px; padding: 10px; width: 100%; background: #e94560; color: white; border: none; border-radius: 8px; cursor: pointer; }
        @media print { button { display: none; } body { background: white; color: black; } .struk { border: none; } }
    </style>
</head>
<body>
<div class="struk">
    <div class="header">
        <h3>BENGKEL KNN SPAREPART</h3>
        <p><?= date('d/m/Y H:i:s', strtotime($tr['tanggal'])) ?></p>
        <p>No: <?= $tr['kode_transaksi'] ?></p>
    </div>
    <div>
        <?php while($item = $detail->fetch_assoc()): ?>
        <div class="item">
            <span><?= $item['nama'] ?> x<?= $item['jumlah'] ?></span>
            <span>Rp <?= number_format($item['harga_saat_beli'] * $item['jumlah'], 0, ',', '.') ?></span>
        </div>
        <?php endwhile; ?>
    </div>
    <div class="total">
        <div class="item">
            <span>TOTAL</span>
            <span>Rp <?= number_format($tr['total_harga'], 0, ',', '.') ?></span>
        </div>
    </div>
    <div style="text-align:center; margin-top:15px; font-size:10px;">
        Terima kasih! ⭐ Sistem Rekomendasi KNN
    </div>
</div>
<button onclick="window.print()">🖨 Cetak Struk</button>
<script>window.onload = () => setTimeout(() => window.print(), 500);</script>
</body>
</html>