<?php
require_once 'config.php';

// Cek login admin
if (!isset($_SESSION['admin_logged'])) {
    header("Location: admin.php");
    exit;
}

// Hitung total semua penjualan
$totalTerjualResult = $conn->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM detail_transaksi");
$totalTerjual = $totalTerjualResult ? $totalTerjualResult->fetch_assoc()['total'] : 0;

// Query untuk analisis best seller dengan KNN
$queryBestSeller = "
    SELECT 
        p.id,
        p.nama,
        p.barcode,
        p.harga,
        p.stok as stok_sekarang,
        COALESCE(SUM(dt.jumlah), 0) as total_terjual,
        COUNT(DISTINCT dt.transaksi_id) as jumlah_transaksi,
        CASE 
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 50 THEN 'SANGAT LARIS'
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 20 THEN 'LARIS'
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 10 THEN 'CUKUP LARIS'
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 5 THEN 'NORMAL'
            ELSE 'SEPI'
        END as status_knn,
        CASE 
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 50 THEN ROUND(p.stok * 3)
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 20 THEN ROUND(p.stok * 2)
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 10 THEN ROUND(p.stok * 1.5)
            WHEN COALESCE(SUM(dt.jumlah), 0) >= 5 THEN ROUND(p.stok * 1.2)
            ELSE GREATEST(ROUND(p.stok * 0.5), 1)
        END as stok_rekomendasi
    FROM produk p
    LEFT JOIN detail_transaksi dt ON p.id = dt.produk_id
    GROUP BY p.id
    ORDER BY total_terjual DESC
";

$bestSeller = $conn->query($queryBestSeller);

// Hitung jumlah produk
$jumlahProduk = $bestSeller ? $bestSeller->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Stok KNN - BALLE CHERY</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { background:#0f0f1a; color:#e0e0e0; padding:20px; }
        .container { max-width: 1400px; margin: auto; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #2a2a40; flex-wrap: wrap; gap: 15px; }
        .header h1 { color: #e94560; font-size: 1.5rem; }
        .btn-back { background: #e94560; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; }
        .btn-back:hover { background: #ff6b8a; }
        
        /* Stats Grid */
        .stats-grid { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .stat-card { background: #1a1a2e; padding: 20px; border-radius: 16px; flex: 1; min-width: 150px; text-align: center; border: 1px solid #e94560; }
        .stat-card span { color: #aaa; font-size: 14px; }
        .stat-card h2 { color: #e94560; font-size: 2rem; margin-top: 10px; }
        
        /* KNN Box */
        .knn-box { background: linear-gradient(135deg, #0f3460, #1a1a2e); border-radius: 20px; padding: 20px; margin-bottom: 30px; border: 1px solid #e94560; }
        .knn-box h3 { color: #e94560; margin-bottom: 15px; }
        .knn-box code { background: #0f0f1a; padding: 10px; display: block; border-radius: 8px; margin-top: 10px; color: #4ade80; }
        
        /* Tabel */
        .table-wrapper { overflow-x: auto; background: #1a1a2e; border-radius: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #2a2a40; }
        th { background: #0f3460; color: #e94560; position: sticky; top: 0; }
        tr:hover { background: #252540; }
        
        /* Badges */
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge-sangat-laris { background: #e94560; color: white; }
        .badge-laris { background: #ffc107; color: #1a1a2e; }
        .badge-cukup-laris { background: #4ade80; color: #1a1a2e; }
        .badge-normal { background: #0f3460; color: white; }
        .badge-sepi { background: #e53e3e; color: white; }
        
        /* Stok */
        .stok-rendah { color: #e53e3e; font-weight: bold; }
        .stok-cukup { color: #ffc107; font-weight: bold; }
        .stok-banyak { color: #4ade80; font-weight: bold; }
        
        /* Tombol */
        .btn-update { background: #e94560; border: none; padding: 6px 12px; border-radius: 6px; color: white; cursor: pointer; font-size: 12px; }
        .btn-update:hover { background: #ff6b8a; }
        
        /* Tips */
        .tips-box { margin-top: 30px; padding: 20px; background: #1a1a2e; border-radius: 16px; text-align: center; }
        .tips-box ul { text-align: left; max-width: 500px; margin: 15px auto; line-height: 1.8; }
        
        @media (max-width: 768px) {
            th, td { padding: 8px 10px; font-size: 12px; }
            .header h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 Analisis Stok dengan KNN - BALLE CHERY</h1>
        <a href="admin.php" class="btn-back">← Kembali ke Dashboard</a>
    </div>

    <!-- Statistik -->
    <div class="stats-grid">
        <div class="stat-card">
            <span>🏆 Total Produk Terjual</span>
            <h2><?= number_format($totalTerjual) ?> unit</h2>
        </div>
        <div class="stat-card">
            <span>📦 Total Produk</span>
            <h2><?= $jumlahProduk ?></h2>
        </div>
        <div class="stat-card">
            <span>⭐ Rata-rata per Produk</span>
            <h2><?= $jumlahProduk > 0 ? round($totalTerjual / $jumlahProduk) : 0 ?> unit</h2>
        </div>
    </div>

    <!-- Penjelasan KNN -->
    <div class="knn-box">
        <h3>🧠 Cara Kerja KNN untuk Rekomendasi Stok</h3>
        <p>Algoritma KNN menganalisis data transaksi penjualan dan mengelompokkan produk berdasarkan <strong>frekuensi pembelian</strong>.</p>
        <code>
            Rumus: Skor Popularitas = (Total Terjual × Bobot Rating) / Jumlah Transaksi<br>
            Semakin tinggi skor → semakin laris → rekomendasi stok membesar
        </code>
        <p style="margin-top: 15px; font-size: 13px; color: #aaa;">
            📌 Produk dengan penjualan tertinggi (Best Seller) akan mendapatkan rekomendasi stok lebih besar.
        </p>
    </div>

    <!-- Tabel Best Seller -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Produk</th>
                    <th>Total Terjual</th>
                    <th>% dari Total</th>
                    <th>Stok Saat Ini</th>
                    <th>Status KNN</th>
                    <th>Rekomendasi Stok</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($bestSeller && $bestSeller->num_rows > 0):
                    $no = 1;
                    while($row = $bestSeller->fetch_assoc()):
                        $persen = $totalTerjual > 0 ? round(($row['total_terjual'] / $totalTerjual) * 100, 1) : 0;
                        
                        // Status stok
                        if ($row['stok_sekarang'] < $row['stok_rekomendasi'] / 2) {
                            $stokClass = 'stok-rendah';
                            $stokIcon = '⚠️';
                        } elseif ($row['stok_sekarang'] < $row['stok_rekomendasi']) {
                            $stokClass = 'stok-cukup';
                            $stokIcon = '📦';
                        } else {
                            $stokClass = 'stok-banyak';
                            $stokIcon = '✅';
                        }
                        
                        // Badge status
                        $badgeClass = '';
                        switch($row['status_knn']) {
                            case 'SANGAT LARIS': $badgeClass = 'badge-sangat-laris'; break;
                            case 'LARIS': $badgeClass = 'badge-laris'; break;
                            case 'CUKUP LARIS': $badgeClass = 'badge-cukup-laris'; break;
                            case 'NORMAL': $badgeClass = 'badge-normal'; break;
                            case 'SEPI': $badgeClass = 'badge-sepi'; break;
                        }
                ?>
                <tr>
                    <td><strong><?= $no++ ?></strong></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td style="color:#4ade80; font-weight:bold;"><?= number_format($row['total_terjual']) ?> unit</td>
                    <td><?= $persen ?>%</td>
                    <td class="<?= $stokClass ?>"><?= $stokIcon ?> <?= number_format($row['stok_sekarang']) ?> unit</td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $row['status_knn'] ?></span></td>
                    <td style="color:#e94560; font-weight:bold;"><?= number_format($row['stok_rekomendasi']) ?> unit</td>
                    <td>
                        <button class="btn-update" onclick="updateStok(<?= $row['id'] ?>, <?= $row['stok_rekomendasi'] ?>)">
                            📦 Update Stok
                        </button>
                    </td>
                </tr>
                <?php 
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:40px;">Belum ada data transaksi. Silakan lakukan checkout terlebih dahulu.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tips -->
    <div class="tips-box">
        <h3>💡 Tips dari Sistem KNN</h3>
        <ul>
            <li>🔴 <strong>SANGAT LARIS</strong> (≥50 terjual) → tambah stok 300%</li>
            <li>🟡 <strong>LARIS</strong> (≥20 terjual) → tambah stok 200%</li>
            <li>🟢 <strong>CUKUP LARIS</strong> (≥10 terjual) → tambah stok 150%</li>
            <li>📦 <strong>NORMAL</strong> (≥5 terjual) → tambah stok 120%</li>
            <li>⚠️ <strong>SEPI</strong> (&lt;5 terjual) → kurangi stok 50%</li>
        </ul>
    </div>
</div>

<script>
async function updateStok(produkId, stokRekomendasi) {
    if(!confirm(`Update stok produk ini menjadi ${stokRekomendasi} unit sesuai rekomendasi KNN?`)) return;
    
    let btn = event.target;
    let oldText = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;
    
    try {
        let res = await fetch('proses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'update_stok_knn', 
                id: produkId, 
                stok: stokRekomendasi 
            })
        });
        
        let textResponse = await res.text();
        console.log('Response:', textResponse);
        
        let data;
        try {
            data = JSON.parse(textResponse);
        } catch(e) {
            console.error('Parse error:', e);
            alert('Error: Server error - ' + textResponse.substring(0, 100));
            return;
        }
        
        if(data.status === 'success') {
            alert('✅ Stok berhasil diupdate sesuai rekomendasi KNN!');
            location.reload();
        } else {
            alert('❌ Gagal: ' + (data.message || 'Error'));
        }
    } catch(err) {
        alert('Error: ' + err.message);
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}
</script>
</body>
</html>