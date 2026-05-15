<?php
require_once 'config.php';
require_once 'knn.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$cart = $input['cart'] ?? [];

$rekomendasi = rekomendasiFromCart($conn, $cart);

echo json_encode($rekomendasi);
?>