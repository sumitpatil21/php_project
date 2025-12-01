<?php
/**
 * Reads product data from db.json and returns it as an array indexed by product ID.
 */
function getProducts() {
    // Correct path from 'controllers' folder up to root, then down to 'pages'
    $jsonFile = __DIR__ . '/../pages/db.json';
    
    if (!file_exists($jsonFile)) {
        return [];
    }
    
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);
    
    if (!isset($data['products']) || !is_array($data['products'])) {
        return [];
    }
    
    // Create an associative array with the product 'id' as the key
    $productsById = [];
    foreach ($data['products'] as $product) {
        if (isset($product['id'])) {
            $productsById[$product['id']] = $product;
        }
    }
    
    return $productsById;
}
?>