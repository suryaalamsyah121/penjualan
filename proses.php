<?php
require_once 'config.php';
require_once 'knn.php';

header('Content-Type: application/json');
error_reporting(0); // Matikan error reporting biar tidak mengganggu JSON

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

// ======================= 1. CARI PRODUK =======================
if ($action == 'cari_barcode') {
    $barcode = mysqli_real_escape_string($conn, $_GET['barcode']);
    $result = $conn->query("SELECT id, nama, harga FROM produk WHERE barcode = '$barcode'");
    if ($result && $result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(null);
    }
    exit;
}

// ======================= 2. GET PRODUK BY KATEGORI =======================
if ($action == 'get_produk') {
    $kategori = $_GET['kategori'] ?? 'semua';
    if ($kategori == 'semua') {
        $query = "SELECT p.*, k.nama as kategori_nama FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id ORDER BY p.id DESC";
    } else {
        $kategori = (int)$kategori;
        $query = "SELECT p.*, k.nama as kategori_nama FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE p.kategori_id = $kategori ORDER BY p.id DESC";
    }
    $result = $conn->query($query);
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode($data);
    exit;
}

// ======================= 3. PROSES CHECKOUT =======================
if ($action == 'checkout') {
    $cart = $input['cart'] ?? [];
    
    if (empty($cart)) {
        echo json_encode(['status' => 'error', 'message' => 'Keranjang kosong']);
        exit;
    }
    
    $kode_transaksi = 'TRX' . time() . rand(100, 999);
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['harga'] * $item['qty'];
    }
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO transaksi (kode_transaksi, total_harga) VALUES (?, ?)");
        $stmt->bind_param("si", $kode_transaksi, $total);
        $stmt->execute();
        $transaksi_id = $conn->insert_id;
        
        $stmt2 = $conn->prepare("INSERT INTO detail_transaksi (transaksi_id, produk_id, jumlah, harga_saat_beli) VALUES (?, ?, ?, ?)");
        foreach ($cart as $item) {
            $stmt2->bind_param("iiii", $transaksi_id, $item['id'], $item['qty'], $item['harga']);
            $stmt2->execute();
            $conn->query("UPDATE produk SET stok = stok - {$item['qty']} WHERE id = {$item['id']}");
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'transaksi_id' => $transaksi_id]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ======================= 4. SIMPAN RATING =======================
if ($action == 'rating') {
    $transaksi_id = (int)($input['transaksi_id'] ?? 0);
    $rating = (int)($input['rating'] ?? 0);
    $komen = mysqli_real_escape_string($conn, $input['komen'] ?? '');
    
    if ($transaksi_id == 0 || $rating == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    // Cek sudah pernah rating
    $cek = $conn->query("SELECT id FROM rating_komen WHERE transaksi_id = $transaksi_id");
    if ($cek && $cek->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sudah pernah rating']);
        exit;
    }
    
    $query = "INSERT INTO rating_komen (transaksi_id, rating, komen) VALUES ($transaksi_id, $rating, '$komen')";
    if ($conn->query($query)) {
        // Update rekomendasi setelah rating
        if (function_exists('updateAllRekomendasiKNN')) {
            updateAllRekomendasiKNN($conn);
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}


// ======================= 5. ADMIN UPDATE PRODUK =======================
if ($action == 'update_produk' && isset($_SESSION['admin_logged'])) {
    $id = (int)$input['id'];
    $nama = mysqli_real_escape_string($conn, $input['nama']);
    $harga = (int)$input['harga'];
    $stok = (int)$input['stok'];
    
    $query = "UPDATE produk SET nama='$nama', harga=$harga, stok=$stok WHERE id=$id";
    if ($conn->query($query)) {
        echo json_encode(['status' => 'success', 'message' => 'Produk berhasil diupdate']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ======================= 6. ADMIN HAPUS PRODUK =======================
if ($action == 'hapus_produk' && isset($_SESSION['admin_logged'])) {
    $id = (int)$input['id'];
    
    // Cek apakah produk pernah dibeli
    $cek = $conn->query("SELECT id FROM detail_transaksi WHERE produk_id = $id LIMIT 1");
    if ($cek && $cek->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Produk sudah pernah dibeli, tidak bisa dihapus!']);
        exit;
    }
    
    if ($conn->query("DELETE FROM produk WHERE id=$id")) {
        echo json_encode(['status' => 'success', 'message' => 'Produk berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ======================= 7. ADMIN TAMBAH PRODUK =======================
if ($action == 'tambah_produk' && isset($_SESSION['admin_logged'])) {
    $barcode = mysqli_real_escape_string($conn, $input['barcode']);
    $nama = mysqli_real_escape_string($conn, $input['nama']);
    $harga = (int)$input['harga'];
    $stok = (int)$input['stok'];
    $kategori_id = (int)$input['kategori_id'];
    $gambar = 'default.png';
    
    // Cek barcode duplikat
    $cek = $conn->query("SELECT id FROM produk WHERE barcode='$barcode'");
    if ($cek && $cek->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Barcode sudah terdaftar!']);
        exit;
    }
    
    $query = "INSERT INTO produk (barcode, nama, harga, stok, kategori_id, gambar) 
              VALUES ('$barcode', '$nama', $harga, $stok, $kategori_id, '$gambar')";
    if ($conn->query($query)) {
        echo json_encode(['status' => 'success', 'message' => 'Produk berhasil ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ======================= 8. ADMIN HAPUS KOMEN =======================
if ($action == 'hapus_komen' && isset($_SESSION['admin_logged'])) {
    $id = (int)$input['id'];
    if ($conn->query("DELETE FROM rating_komen WHERE id=$id")) {
        echo json_encode(['status' => 'success', 'message' => 'Komentar berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ======================= 9. ADMIN HAPUS LOG REKOMENDASI =======================
if ($action == 'clear_log_rekomendasi' && isset($_SESSION['admin_logged'])) {
    if ($conn->query("TRUNCATE TABLE log_rekomendasi")) {
        echo json_encode(['status' => 'success', 'message' => 'Log rekomendasi berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ======================= 10. UPDATE STOK BERDASARKAN REKOMENDASI KNN =======================
if ($action == 'update_stok_knn' && isset($_SESSION['admin_logged'])) {
    $id = (int)$input['id'];
    $stok = (int)$input['stok'];
    
    $query = "UPDATE produk SET stok = $stok WHERE id = $id";
    if ($conn->query($query)) {
        echo json_encode(['status' => 'success', 'message' => 'Stok berhasil diupdate sesuai rekomendasi KNN']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}



// Default response
echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal: ' . $action]);
?>