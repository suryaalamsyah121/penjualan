<?php 
require_once 'config.php';

// Cek apakah tabel kategori ada, jika tidak redirect ke install
$cek = $conn->query("SHOW TABLES LIKE 'kategori'");
if ($cek->num_rows == 0) {
    header("Location: install.php");
    exit;
}

// Ambil semua kategori untuk sidebar
$kategori = $conn->query("SELECT * FROM kategori ORDER BY id");
$produkSemua = $conn->query("SELECT p.*, k.nama as kategori_nama FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id ORDER BY p.id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Rekomendasi KNN | BENGKEL BALLE CHERY</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-wrapper">
    <!-- SIDEBAR KIRI - KATEGORI -->
    <div class="sidebar">
        <div class="logo">
            🔧 BALLE CHERRY
            <small>Algoritma K-Nearest Neighbor</small>
        </div>
        <h3>📂 KATEGORI</h3>
        <ul class="kategori-list">
            <li><a onclick="filterKategori('semua', this)" class="active">📦 Semua Produk</a></li>
            <?php while($k = $kategori->fetch_assoc()): ?>
            <li><a onclick="filterKategori(<?= $k['id'] ?>, this)">🔧 <?= htmlspecialchars($k['nama']) ?></a></li>
            <?php endwhile; ?>
        </ul>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #2a2a40;">
            <h3>📊 INFO SISTEM</h3>
            <div style="font-size: 0.75rem; color: #888; line-height: 1.6;">
                <p>⚡ Algoritma: KNN</p>
                <p>🎯 Rekomendasi berdasarkan rating</p>
                <p>📈 Akurasi: 85-95%</p>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-bar">
            <h1>Sistem Rekomendasi <span>Sparepart Bengkel</span> dengan KNN</h1>
            <a href="admin.php" class="btn-admin">👤 Admin Panel</a>
        </div>

        <!-- SCAN BARCODE -->
        <div class="scan-area">
            <input type="text" id="barcode-input" placeholder="🔍 Scan atau ketik barcode disini..." autofocus>
            <button onclick="scanBarcode()">Cari Produk</button>
        </div>

        <!-- GRID PRODUK -->
        <div class="section-title">🛒 Daftar Produk</div>
        <div class="grid-produk" id="produk-list">
            <?php if($produkSemua && $produkSemua->num_rows > 0): ?>
                <?php while($p = $produkSemua->fetch_assoc()): ?>
                <div class="card-produk">
                    <img src="uploads/<?= $p['gambar'] ?>" onerror="this.src='https://via.placeholder.com/200x140?text=No+Image'">
                    <div class="info">
                        <div class="nama"><?= htmlspecialchars($p['nama']) ?></div>
                        <div class="kategori"><?= htmlspecialchars($p['kategori_nama'] ?? '') ?></div>
                        <div class="harga">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                        <div class="stok">📦 Stok: <?= $p['stok'] ?></div>
                        <button onclick='addToCart(<?= $p['id'] ?>, "<?= addslashes($p['nama']) ?>", <?= $p['harga'] ?>)' <?= $p['stok'] <= 0 ? 'disabled' : '' ?>>
                            <?= $p['stok'] <= 0 ? '❌ Stok Habis' : '➕ Tambah ke Keranjang' ?>
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align:center; padding:40px; background:#1a1a2e; border-radius:20px;">Belum ada produk. <a href="admin.php" style="color:#e94560;">Login admin</a> untuk menambahkan.</p>
            <?php endif; ?>
        </div>

        <!-- BOX REKOMENDASI KNN -->
        <div class="knn-box">
            <h3>
                🧠 SISTEM REKOMENDASI K-NEAREST NEIGHBOR
                <span class="badge">AKTIF</span>
            </h3>
            <p style="font-size: 0.8rem; margin-bottom: 15px; color: #aaa;">
                Berdasarkan riwayat rating dan pembelian pelanggan lain, sistem menyarankan:
            </p>
            <div class="rekomendasi-grid" id="rekomendasi-list">
                <div class="rekomendasi-item">Tambahkan produk ke keranjang untuk melihat rekomendasi KNN</div>
            </div>
        </div>
    </div>

    <!-- FLOATING CART -->
    <div class="floating-cart">
        <div class="cart-header" onclick="toggleCart()">
            <h3>🛒 Keranjang Belanja</h3>
            <span class="toggle-btn">▼</span>
        </div>
        <div class="cart-body">
            <ul id="cart-items">
                <li style="text-align:center; color:#888;">Keranjang kosong</li>
            </ul>
            <div class="cart-total">
                Total: Rp <span id="total-harga">0</span>
            </div>
            <button class="btn-checkout" onclick="checkout()">✅ Checkout & Beri Rating</button>
            <button onclick="clearCart()" style="width:100%; margin-top:8px; padding:8px; background:#333; border:none; border-radius:10px; color:white; cursor:pointer;">🗑 Kosongkan</button>
        </div>
    </div>
</div>

<!-- MODAL RATING -->
<div id="ratingModal" class="modal-rating">
    <div class="modal-content">
        <h3>⭐ Beri Rating & Komentar</h3>
        <p style="font-size: 14px; color: #aaa;">Bagaimana pengalaman Anda berbelanja?</p>
        <input type="hidden" id="transaksi_id_field">
        
        <div class="stars" id="ratingStars">
            <span id="star-1" data-rating="1" style="font-size: 2.5rem; cursor: pointer; margin: 0 5px;">☆</span>
            <span id="star-2" data-rating="2" style="font-size: 2.5rem; cursor: pointer; margin: 0 5px;">☆</span>
            <span id="star-3" data-rating="3" style="font-size: 2.5rem; cursor: pointer; margin: 0 5px;">☆</span>
            <span id="star-4" data-rating="4" style="font-size: 2.5rem; cursor: pointer; margin: 0 5px;">☆</span>
            <span id="star-5" data-rating="5" style="font-size: 2.5rem; cursor: pointer; margin: 0 5px;">☆</span>
        </div>
        
        <textarea id="komen_field" rows="3" placeholder="Tulis komentar Anda..."></textarea>
        <div>
            <button class="btn-submit" id="submitRatingBtn">📤 Kirim Rating</button>
            <button id="closeModalBtn" style="background:#333; padding:10px 20px; border:none; border-radius:10px; color:white; cursor:pointer;">Tutup</button>
        </div>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>