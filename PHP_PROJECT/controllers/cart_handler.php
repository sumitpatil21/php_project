<?php
session_start();
header('Content-Type: application/json');

function getProductsById() {
    // Correctly locate db.json in the 'pages' folder
    $jsonPath = __DIR__ . '/../pages/db.json';
    if (!file_exists($jsonPath)) { return []; }
    $jsonData = file_get_contents($jsonPath);
    $data = json_decode($jsonData, true);
    if (!isset($data['products'])) { return []; }
    return array_column($data['products'], null, 'id');
}

$response = ['success' => false, 'message' => 'Invalid Request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    if (!empty($product_id)) {
        $products = getProductsById();
        if (isset($products[$product_id])) {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            $_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;
            $response['success'] = true;
            $response['message'] = $products[$product_id]['brand'] . ' was added to cart.';
            $response['cart_count'] = array_sum($_SESSION['cart']);
        } else {
            $response['message'] = 'Invalid Product ID.';
        }
    } else {
        $response['message'] = 'Product ID not provided.';
    }
}

echo json_encode($response);
exit;