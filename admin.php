<?php
require_once 'config.php';

// Login check
if(isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = md5($_POST['password']);
    $res = $conn->query("SELECT * FROM users WHERE username='$user' AND password='$pass'");
    if($res->num_rows) {
        $_SESSION['admin_logged'] = true;
    } else {
        $error = "Login gagal!";
    }
}

if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
}

if(!isset($_SESSION['admin_logged'])):
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { background:#0f0f1a; display:flex; justify-content:center; align-items:center; min-height:100vh; }
        .login-box { background:#1a1a2e; padding:40px; border-radius:24px; width:400px; border:1px solid #e94560; }
        .login-box h2 { color:#e94560; margin-bottom:20px; text-align:center; }
        input, button { width:100%; padding:12px; margin:10px 0; border-radius:10px; border:none; }
        input { background:#0f0f1a; border:1px solid #2a2a40; color:white; }
        button { background:#e94560; color:white; font-weight:bold; cursor:pointer; }
    </style>
</head>
<body>
<div class="login-box">
    <h2>🔧 Admin Bengkel BALLE CHERRY</h2>
    <?php if(isset($error)) echo "<p style='color:#e94560'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
    <p style="text-align:center; margin-top:16px; color:#888;">Default: admin / admin123</p>
</div>
</body>
</html>
<?php
else:
// Ambil data untuk dashboard
$totalProduk = $conn->query("SELECT COUNT(*) as total FROM produk")->fetch_assoc()['total'];
$totalTransaksi = $conn->query("SELECT COUNT(*) as total FROM transaksi")->fetch_assoc()['total'];
$totalPendapatan = $conn->query("SELECT SUM(total_harga) as total FROM transaksi")->fetch_assoc()['total'];
$rataRating = $conn->query("SELECT AVG(rating) as rata FROM rating_komen")->fetch_assoc()['rata'];

// Ambil data grafik
$chartData = [];
$penjualanBulan = $conn->query("
    SELECT DATE_FORMAT(tanggal, '%Y-%m') as bulan, SUM(total_harga) as total
    FROM transaksi
    GROUP BY bulan
    ORDER BY bulan DESC LIMIT 6
");
while($row = $penjualanBulan->fetch_assoc()) {
    $chartData['bulan'][] = $row['bulan'];
    $chartData['total'][] = $row['total'];
}
$chartData['bulan'] = array_reverse($chartData['bulan'] ?? []);
$chartData['total'] = array_reverse($chartData['total'] ?? []);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - BALLE CHERRY</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { background:#0f0f1a; color:#e0e0e0; }
        .admin-container { padding: 20px; max-width: 1400px; margin: auto; }
        .admin-card { background: #1a1a2e; border-radius: 20px; padding: 20px; margin-bottom: 30px; border: 1px solid #2a2a40; }
        .stats-grid { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { background: linear-gradient(135deg, #0f3460, #1a1a2e); flex: 1; padding: 20px; border-radius: 20px; text-align: center; border: 1px solid #e94560; }
        .stat-card h3 { font-size: 2rem; margin: 10px 0; color: #e94560; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #2a2a40; }
        th { background: #0f3460; color: #e94560; }
        .btn { padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; margin: 2px; }
        .btn-edit { background: #e94560; color: white; }
        .btn-delete { background: #e53e3e; color: white; }
        .btn-add { background: #e94560; color: white; padding: 10px 20px; }
        .btn-export { background: #4ade80; color: #1a1a2e; padding: 10px 15px; margin-right: 10px; text-decoration: none; border-radius: 8px; display: inline-block; }
        .form-group { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .form-group input, .form-group select { padding: 10px; border-radius: 8px; border: 1px solid #2a2a40; background: #0f0f1a; color: white; }
        .form-group input[type="file"] { background: transparent; }
        .produk-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        canvas { max-height: 300px; width: 100% !important; }
        .split-view { display: flex; gap: 20px; flex-wrap: wrap; }
        .split-left { flex: 1; min-width: 300px; }
        .split-right { flex: 1; min-width: 300px; }
        .loading { opacity: 0.6; pointer-events: none; }
    </style>
</head>
<body>
<div class="admin-container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
        <h1>🔧 Dashboard Admin - BALLE CHERRY</h1>
        <a href="analisis_stok.php" style="background:#0f3460; color:white; padding:8px 16px; border-radius:8px; text-decoration:none; margin-left:15px;">
    📊 Analisis Stok KNN
</a>
        <a href="?logout=1" style="background:#e53e3e; color:white; padding:10px 20px; border-radius:10px; text-decoration:none;">Logout</a>
    </div>
   

    <!-- STATISTIK -->
    <div class="stats-grid">
        <div class="stat-card"><span>📦 Total Produk</span><h3><?= $totalProduk ?></h3></div>
        <div class="stat-card"><span>🧾 Transaksi</span><h3><?= $totalTransaksi ?></h3></div>
        <div class="stat-card"><span>💰 Pendapatan</span><h3>Rp <?= number_format($totalPendapatan,0,',','.') ?></h3></div>
        <div class="stat-card"><span>⭐ Rata Rating</span><h3><?= round($rataRating ?: 0, 1) ?> / 5</h3></div>
    </div>

    <!-- GRAFIK -->
    <div class="admin-card">
        <h3>📈 Grafik Penjualan (6 Bulan Terakhir)</h3>
        <canvas id="salesChart"></canvas>
    </div>

    <!-- EXPORT -->
    <div class="admin-card">
        <h3>📎 Export Laporan</h3>
        <a href="export_excel.php?type=transaksi" class="btn-export">📊 Export Transaksi</a>
        <a href="export_excel.php?type=produk" class="btn-export">🔧 Export Produk</a>
        <a href="export_excel.php?type=rekomendasi" class="btn-export">⭐ Export Rekomendasi KNN</a>
    </div>

    <!-- SPLIT VIEW -->
    <div class="split-view">
        <!-- KIRI: PRODUK -->
        <div class="split-left">
            <div class="admin-card">
                <h3>➕ Tambah Produk Baru</h3>
                <form id="formTambahProduk" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="text" name="barcode" placeholder="Barcode" required style="flex:1">
                        <input type="text" name="nama" placeholder="Nama Produk" required style="flex:2">
                    </div>
                    <div class="form-group">
                        <input type="number" name="harga" placeholder="Harga" required style="flex:1">
                        <input type="number" name="stok" placeholder="Stok" required style="flex:1">
                        <select name="kategori_id" required style="flex:1">
                            <option value="">Pilih Kategori</option>
                            <?php 
                            $kat = $conn->query("SELECT * FROM kategori");
                            while($k = $kat->fetch_assoc()):
                            ?>
                            <option value="<?= $k['id'] ?>"><?= $k['nama'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="file" name="gambar" accept="image/*">
                        <button type="submit" class="btn-add">Simpan Produk</button>
                    </div>
                </form>
            </div>

            <div class="admin-card">
                <h3>📦 Daftar Produk</h3>
                <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                    <table style="width:100%;">
                        <thead style="position: sticky; top: 0;">
                            <tr style="background:#0f3460;">
                                <th>ID</th><th>Gambar</th><th>Barcode</th><th>Nama</th><th>Harga</th><th>Stok</th><th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tabel-produk-body">
                            <?php
                            $produk = $conn->query("SELECT p.*, k.nama as kategori_nama FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id ORDER BY p.id DESC");
                            while($p = $produk->fetch_assoc()):
                            ?>
                            <tr id="produk-row-<?= $p['id'] ?>">
                                <td><?= $p['id'] ?></td>
                                <td><img src="uploads/<?= $p['gambar'] ?>" onerror="this.src='https://via.placeholder.com/50x50'" style="width:40px; height:40px; object-fit:cover; border-radius:5px;"></td>
                                <td><?= htmlspecialchars($p['barcode']) ?></td>
                                <td><input type="text" id="nama-<?= $p['id'] ?>" value="<?= htmlspecialchars($p['nama']) ?>" style="background:#0f0f1a; color:white; border:1px solid #2a2a40; padding:5px; border-radius:5px; width:120px;"></td>
                                <td><input type="number" id="harga-<?= $p['id'] ?>" value="<?= $p['harga'] ?>" style="width:100px; background:#0f0f1a; color:white; border:1px solid #2a2a40; padding:5px; border-radius:5px;"></td>
                                <td><input type="number" id="stok-<?= $p['id'] ?>" value="<?= $p['stok'] ?>" style="width:70px; background:#0f0f1a; color:white; border:1px solid #2a2a40; padding:5px; border-radius:5px;"></td>
                                <td>
                                    <button class="btn btn-edit" onclick="updateProduk(<?= $p['id'] ?>)">Update</button>
                                    <button class="btn btn-delete" onclick="hapusProduk(<?= $p['id'] ?>)">Hapus</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- KANAN: RATING & LOG -->
        <div class="split-right">
            <div class="admin-card">
                <h3>💬 Rating & Komentar Pelanggan</h3>
                <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                    <table style="width:100%;">
                        <thead style="position: sticky; top: 0;">
                            <tr style="background:#0f3460;">
                                <th>ID Transaksi</th><th>Rating</th><th>Komentar</th><th>Tanggal</th><th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tabel-komen-body">
                            <?php
                            $komen = $conn->query("SELECT * FROM rating_komen ORDER BY tanggal DESC");
                            while($k = $komen->fetch_assoc()):
                                $stars = '';
                                for($i=1; $i<=5; $i++) {
                                    $stars .= $i <= $k['rating'] ? '<span style="color:#ffc107;">★</span>' : '<span style="color:#555;">☆</span>';
                                }
                            ?>
                            <tr id="komen-row-<?= $k['id'] ?>">
                                <td><?= $k['transaksi_id'] ?></td>
                                <td><?= $stars ?> (<?= $k['rating'] ?>/5)</td>
                                <td style="max-width:200px; word-wrap:break-word;"><?= htmlspecialchars($k['komen']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($k['tanggal'])) ?></td>
                                <td><button class="btn btn-delete" onclick="hapusKomen(<?= $k['id'] ?>)">Hapus</button></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-card">
                <h3>📊 Log Rekomendasi KNN</h3>
                <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                    <table style="width:100%;">
                        <thead style="position: sticky; top: 0;">
                            <tr style="background:#0f3460;">
                                <th>Produk Asal</th><th>Rekomendasi</th><th>Skor</th><th>Tanggal</th>
                             </tr>
                        </thead>
                        <tbody id="log-rekomendasi-body">
                            <?php
                            $log = $conn->query("
                                SELECT l.*, p1.nama as produk_asal, p2.nama as produk_rekom 
                                FROM log_rekomendasi l 
                                JOIN produk p1 ON l.produk_asal_id = p1.id 
                                JOIN produk p2 ON l.produk_rekomendasi_id = p2.id 
                                ORDER BY l.tanggal DESC LIMIT 30
                            ");
                            while($l = $log->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($l['produk_asal']) ?></td>
                                <td><?= htmlspecialchars($l['produk_rekom']) ?></td>
                                <td style="color:#4ade80;"><?= $l['skor'] ?>%</td>
                                <td><?= date('d/m/Y H:i', strtotime($l['tanggal'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:15px;">
                    <button onclick="refreshLogRekomendasi()" class="btn btn-edit">🔄 Refresh Log</button>
                    <button onclick="clearAllLogRekomendasi()" class="btn btn-delete">🗑 Hapus Semua Log</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Grafik Penjualan
const ctx = document.getElementById('salesChart')?.getContext('2d');
if(ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartData['bulan']) ?>,
            datasets: [{
                label: 'Total Penjualan (Rp)',
                data: <?= json_encode($chartData['total']) ?>,
                borderColor: '#e94560',
                backgroundColor: 'rgba(233,69,96,0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: { responsive: true }
    });
}

// ======================= PRODUK =======================
// Update produk
async function updateProduk(id) {
    let nama = document.getElementById(`nama-${id}`).value;
    let harga = document.getElementById(`harga-${id}`).value;
    let stok = document.getElementById(`stok-${id}`).value;
    
    if(!nama || !harga) {
        alert('Nama dan harga harus diisi!');
        return;
    }
    
    let btn = event.target;
    let oldText = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;
    
    try {
        let res = await fetch('proses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_produk', id, nama, harga, stok })
        });
        let data = await res.json();
        if(data.status === 'success') {
            alert('✅ Produk berhasil diupdate!');
        } else {
            alert('❌ Gagal update: ' + (data.message || 'Error'));
        }
    } catch(err) {
        alert('Error: ' + err.message);
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}

// Hapus produk
async function hapusProduk(id) {
    if(!confirm('Yakin ingin menghapus produk ini? Data transaksi yang terkait akan terpengaruh.')) return;
    
    let btn = event.target;
    let oldText = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;
    
    try {
        let res = await fetch('proses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'hapus_produk', id })
        });
        let data = await res.json();
        if(data.status === 'success') {
            document.getElementById(`produk-row-${id}`).remove();
            alert('✅ Produk berhasil dihapus!');
        } else {
            alert('❌ Gagal hapus: ' + (data.message || 'Error'));
        }
    } catch(err) {
        alert('Error: ' + err.message);
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}

// Tambah produk via form
$('#formTambahProduk').on('submit', async function(e) {
    e.preventDefault();
    
    let formData = new FormData(this);
    formData.append('action', 'tambah_produk');
    
    let btn = $(this).find('button[type="submit"]');
    let oldText = btn.html();
    btn.html('⏳ Menyimpan...').prop('disabled', true);
    
    try {
        let res = await fetch('upload_gambar.php', {
            method: 'POST',
            body: formData
        });
        let data = await res.json();
        if(data.status === 'success') {
            alert('✅ Produk berhasil ditambahkan!');
            location.reload();
        } else {
            alert('❌ Gagal: ' + (data.message || 'Error'));
        }
    } catch(err) {
        alert('Error: ' + err.message);
    } finally {
        btn.html(oldText).prop('disabled', false);
    }
});

// ======================= KOMENTAR =======================
// Hapus komen
async function hapusKomen(id) {
    if(!confirm('Yakin ingin menghapus komentar ini?')) return;
    
    let btn = event.target;
    let oldText = btn.innerHTML;
    btn.innerHTML = '⏳...';
    btn.disabled = true;
    
    try {
        let res = await fetch('proses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'hapus_komen', id })
        });
        let data = await res.json();
        if(data.status === 'success') {
            document.getElementById(`komen-row-${id}`).remove();
            alert('✅ Komentar berhasil dihapus!');
        } else {
            alert('❌ Gagal hapus: ' + (data.message || 'Error'));
        }
    } catch(err) {
        alert('Error: ' + err.message);
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}

// ======================= LOG REKOMENDASI =======================
async function refreshLogRekomendasi() {
    try {
        let res = await fetch('ajax_get_log.php');
        let data = await res.json();
        let tbody = document.getElementById('log-rekomendasi-body');
        if(!tbody) return;
        
        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Belum ada log rekomendasi</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.map(log => `
            <tr>
                <td>${escapeHtml(log.produk_asal)}</td>
                <td>${escapeHtml(log.produk_rekom)}</td>
                <td style="color:#4ade80;">${log.skor}%</td>
                <td>${log.tanggal}</td>
            </tr>
        `).join('');
    } catch(err) {
        console.error(err);
        alert('Gagal refresh log');
    }
}

async function clearAllLogRekomendasi() {
    if(!confirm('Yakin ingin menghapus semua log rekomendasi KNN?')) return;
    
    try {
        let res = await fetch('proses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear_log_rekomendasi' })
        });
        let data = await res.json();
        if(data.status === 'success') {
            alert('✅ Semua log rekomendasi dihapus!');
            refreshLogRekomendasi();
        } else {
            alert('Gagal hapus log');
        }
    } catch(err) {
        alert('Error: ' + err.message);
    }
}

function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}
</script>
</body>
</html>
<?php endif; ?>