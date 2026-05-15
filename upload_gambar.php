<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action == 'tambah_produk') {
    $barcode = $conn->real_escape_string($_POST['barcode']);
    $nama = $conn->real_escape_string($_POST['nama']);
    $harga = (int)$_POST['harga'];
    $stok = (int)$_POST['stok'];
    $kategori_id = (int)$_POST['kategori_id'];
    
    // Cek duplikat
    $cek = $conn->query("SELECT id FROM produk WHERE barcode = '$barcode'");
    if ($cek->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Barcode sudah terdaftar']);
        exit;
    }
    
    // Upload gambar
    $gambar = 'default.png';
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['gambar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newFilename = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = UPLOAD_DIR . $newFilename;
            
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target)) {
                $gambar = $newFilename;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal upload gambar']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Format gambar tidak didukung']);
            exit;
        }
    }
    
    $query = "INSERT INTO produk (barcode, nama, harga, stok, kategori_id, gambar) 
              VALUES ('$barcode', '$nama', $harga, $stok, $kategori_id, '$gambar')";
    
    if ($conn->query($query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal']);
?>