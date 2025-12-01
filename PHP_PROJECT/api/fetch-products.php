<?php
// ============================================
// fetch-cart.php - Fetch cart items (JSON-based)
// ============================================
session_start();
header('Content-Type: application/json');

// Load products from JSON file
function getProducts() {
    $jsonFile = __DIR__ . '/db.json';
    
    if (!file_exists($jsonFile)) {
        return [];
    }
    
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);
    
    if (!isset($data['products'])) {
        return [];
    }
    
    // Create associative array with id as key
    $products = [];
    foreach ($data['products'] as $product) {
        $products[$product['id']] = $product;
    }
    
    return $products;
}

try {
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Load products
    $products = getProducts();
    
    // Build cart items with full product details
    $cart_items = [];
    $subtotal = 0;
    $total_quantity = 0;
    
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        if (isset($products[$product_id])) {
            $product = $products[$product_id];
            $item_total = $product['price'] * $quantity;
            
            $cart_items[] = [
                'id' => $product_id,
                'name' => $product['brand'] . ' - ' . $product['sizeCollection'],
                'brand' => $product['brand'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image_url' => $product['imageUrl'],
                'image_url2' => $product['imageUrl2'],
                'rating' => $product['rating'],
                'reviews' => $product['reviews'],
                'discount' => $product['discount'],
                'item_total' => $item_total
            ];
            
            $subtotal += $item_total;
            $total_quantity += $quantity;
        }
    }
    
    // Calculate shipping
    $shipping = $subtotal > 1000 ? 0 : 50; // Free shipping above ₹1000
    $total = $subtotal + $shipping;
    
    echo json_encode([
        'success' => true,
        'cart_items' => $cart_items,
        'summary' => [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'item_count' => count($cart_items),
            'total_quantity' => $total_quantity
        ]
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>