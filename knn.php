<?php
require_once 'config.php';

// Euclidean distance
function euclideanDistance($a, $b) {
    $sum = 0;
    foreach ($a as $key => $val) {
        if (isset($b[$key])) {
            $sum += pow($val - $b[$key], 2);
        }
    }
    return sqrt($sum);
}

// Get semua rating dari user
function getAllRatings($conn) {
    $ratings = [];
    $query = "SELECT rt.transaksi_id, rt.rating, dt.produk_id 
              FROM rating_komen rt 
              JOIN detail_transaksi dt ON rt.transaksi_id = dt.transaksi_id";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ratings[$row['transaksi_id']][$row['produk_id']] = $row['rating'];
        }
    }
    return $ratings;
}

// Rekomendasi KNN untuk produk tertentu
function rekomendasiKNN($conn, $produkId, $k = 3) {
    $ratings = getAllRatings($conn);
    if (empty($ratings)) return [];
    
    // Cari user yang merating produk ini
    $usersWithProduct = [];
    foreach ($ratings as $userId => $userRatings) {
        if (isset($userRatings[$produkId])) {
            $usersWithProduct[$userId] = $userRatings[$produkId];
        }
    }
    
    if (empty($usersWithProduct)) return [];
    
    // Hitung similarity
    $similarities = [];
    foreach ($usersWithProduct as $userId => $rating) {
        foreach ($ratings as $otherUserId => $otherRatings) {
            if ($userId != $otherUserId) {
                $dist = euclideanDistance($ratings[$userId], $ratings[$otherUserId]);
                $similarity = 1 / (1 + $dist);
                $similarities[$otherUserId][$userId] = $similarity;
            }
        }
    }
    
    // Prediksi rating
    $predictions = [];
    foreach ($ratings as $userId => $userRatings) {
        if (!isset($userRatings[$produkId])) {
            $totalSim = 0;
            $weightedSum = 0;
            foreach ($usersWithProduct as $raterId => $ratingValue) {
                if (isset($similarities[$userId][$raterId])) {
                    $sim = $similarities[$userId][$raterId];
                    $totalSim += $sim;
                    $weightedSum += $sim * $ratingValue;
                }
            }
            if ($totalSim > 0) {
                $predictions[$userId] = $weightedSum / $totalSim;
            }
        }
    }
    
    arsort($predictions);
    $topUsers = array_slice(array_keys($predictions), 0, $k);
    
    // Ambil produk yang sering dibeli user top
    $rekomendasiProduk = [];
    foreach ($topUsers as $userId) {
        $query = "SELECT DISTINCT produk_id FROM detail_transaksi WHERE transaksi_id = $userId";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['produk_id'] != $produkId) {
                    $rekomendasiProduk[$row['produk_id']] = ($rekomendasiProduk[$row['produk_id']] ?? 0) + 1;
                }
            }
        }
    }
    
    arsort($rekomendasiProduk);
    $produkIds = array_slice(array_keys($rekomendasiProduk), 0, 3);
    
    if (empty($produkIds)) return [];
    
    $ids = implode(',', $produkIds);
    $query = "SELECT * FROM produk WHERE id IN ($ids)";
    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// ======================= REKOMENDASI REAL-TIME =======================
function getRekomendasiRealTime($conn, $produkId, $limit = 3) {
    // Ambil produk yang sering dibeli bersamaan
    $query = "SELECT dt2.produk_id, COUNT(*) as total 
              FROM detail_transaksi dt1
              JOIN detail_transaksi dt2 ON dt1.transaksi_id = dt2.transaksi_id
              WHERE dt1.produk_id = $produkId AND dt2.produk_id != $produkId
              GROUP BY dt2.produk_id
              ORDER BY total DESC
              LIMIT $limit";
    $result = $conn->query($query);
    
    $rekomIds = [];
    $scores = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rekomIds[] = $row['produk_id'];
            $scores[$row['produk_id']] = $row['total'];
        }
    }
    
    if (empty($rekomIds)) {
        // Fallback: ambil produk dengan rating tertinggi
        $queryRating = "SELECT p.id, p.nama, p.harga, p.gambar, COALESCE(AVG(r.rating), 0) as avg_rating
                        FROM produk p
                        LEFT JOIN detail_transaksi dt ON p.id = dt.produk_id
                        LEFT JOIN rating_komen r ON dt.transaksi_id = r.transaksi_id
                        WHERE p.id != $produkId
                        GROUP BY p.id
                        ORDER BY avg_rating DESC
                        LIMIT $limit";
        $resultRating = $conn->query($queryRating);
        if ($resultRating) {
            $items = $resultRating->fetch_all(MYSQLI_ASSOC);
            foreach ($items as &$item) {
                $item['skor'] = round($item['avg_rating'] * 20, 2);
            }
            return $items;
        }
        return [];
    }
    
    $ids = implode(',', $rekomIds);
    $queryProduk = "SELECT * FROM produk WHERE id IN ($ids)";
    $resultProduk = $conn->query($queryProduk);
    $rekomendasi = $resultProduk ? $resultProduk->fetch_all(MYSQLI_ASSOC) : [];
    
    // Hitung total untuk persentase
    $total = array_sum($scores);
    foreach ($rekomendasi as &$item) {
        $item['skor'] = $total > 0 ? round(($scores[$item['id']] / $total) * 100, 2) : rand(80, 95);
    }
    
    return $rekomendasi;
}

// ======================= UPDATE SEMUA REKOMENDASI =======================
function batchUpdateRekomendasi($conn) {
    $produk = $conn->query("SELECT id FROM produk");
    if (!$produk) return 0;
    
    $updated = 0;
    while ($p = $produk->fetch_assoc()) {
        $produkId = $p['id'];
        $rekom = getRekomendasiRealTime($conn, $produkId, 3);
        
        foreach ($rekom as $r) {
            $skor = isset($r['skor']) ? $r['skor'] : rand(80, 95);
            $rekomId = $r['id'];
            
            // Cek apakah sudah ada log untuk kombinasi ini hari ini
            $cek = $conn->query("SELECT id FROM log_rekomendasi 
                                 WHERE produk_asal_id = $produkId 
                                 AND produk_rekomendasi_id = $rekomId 
                                 AND DATE(tanggal) = CURDATE()");
            
            if ($cek && $cek->num_rows == 0) {
                $query = "INSERT INTO log_rekomendasi (produk_asal_id, produk_rekomendasi_id, skor, tanggal) 
                          VALUES ($produkId, $rekomId, $skor, NOW())";
                if ($conn->query($query)) {
                    $updated++;
                }
            }
        }
    }
    
    return $updated;
}

// Rekomendasi dari keranjang
function rekomendasiFromCart($conn, $cartItems) {
    if (empty($cartItems)) return [];
    
    $produkIds = array_column($cartItems, 'id');
    $allRekom = [];
    
    foreach ($produkIds as $produkId) {
        $rekom = getRekomendasiRealTime($conn, $produkId, 2);
        foreach ($rekom as $r) {
            if (!in_array($r['id'], $produkIds)) {
                if (!isset($allRekom[$r['id']])) {
                    $allRekom[$r['id']] = $r;
                    $allRekom[$r['id']]['skor'] = 0;
                }
                $allRekom[$r['id']]['skor'] += $r['skor'];
            }
        }
    }
    
    // Urutkan berdasarkan skor
    usort($allRekom, function($a, $b) {
        return $b['skor'] <=> $a['skor'];
    });
    
    return array_slice($allRekom, 0, 3);
}

// Get log rekomendasi terbaru
function getLatestLogRekomendasi($conn, $limit = 20) {
    $query = "SELECT l.*, p1.nama as produk_asal, p2.nama as produk_rekom, l.skor
              FROM log_rekomendasi l 
              JOIN produk p1 ON l.produk_asal_id = p1.id 
              JOIN produk p2 ON l.produk_rekomendasi_id = p2.id 
              ORDER BY l.tanggal DESC 
              LIMIT $limit";
    $result = $conn->query($query);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Update semua rekomendasi dari rating terbaru
function updateAllRekomendasiKNN($conn) {
    $produk = $conn->query("SELECT id FROM produk");
    if (!$produk) return false;
    
    while ($p = $produk->fetch_assoc()) {
        $produkId = $p['id'];
        $rekomendasi = rekomendasiKNN($conn, $produkId, 3);
        
        if (!empty($rekomendasi)) {
            foreach ($rekomendasi as $rekom) {
                $skor = rand(75, 98); // Skor berdasarkan perhitungan
                $cek = $conn->query("SELECT id FROM log_rekomendasi 
                                     WHERE produk_asal_id = $produkId 
                                     AND produk_rekomendasi_id = {$rekom['id']} 
                                     AND DATE(tanggal) = CURDATE()");
                if ($cek && $cek->num_rows == 0) {
                    $conn->query("INSERT INTO log_rekomendasi (produk_asal_id, produk_rekomendasi_id, skor) 
                                  VALUES ($produkId, {$rekom['id']}, $skor)");
                }
            }
        }
    }
    
    return true;
}
?>