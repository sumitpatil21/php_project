<?php
// Always start the session to check the user's login status
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Allow both logged-in and guest users to add items
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

// Check if user is admin (only if logged in)
if ($user_id && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Admin cannot add items to cart. Please use a regular user account.',
        'action' => 'admin_restricted'
    ]);
    exit();
}

// Include the database connection
require_once __DIR__ . '/../includes/db.php';

// Get product_id and quantity from the POST request
$product_id = $_POST['product_id'] ?? null;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

// Validate the input
if (empty($product_id) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
    exit();
}

try {
    // Check if the item is already in the cart (for both logged-in and guest users)
    if ($user_id) {
        // For logged-in users
        $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
    } else {
        // For guest users
        $stmt = $pdo->prepare("SELECT * FROM cart WHERE session_id = ? AND user_id IS NULL AND product_id = ?");
        $stmt->execute([$session_id, $product_id]);
    }
    $existing_item = $stmt->fetch();

    if ($existing_item) {
        // If it exists, update the quantity
        $new_quantity = $existing_item['quantity'] + $quantity;
        $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update_stmt->execute([$new_quantity, $existing_item['id']]);
    } else {
        // If it's a new item, insert it
        $insert_sql = "INSERT INTO cart (user_id, session_id, product_id, quantity) VALUES (?, ?, ?, ?)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$user_id, $session_id, $product_id, $quantity]);
    }

    // Get the new total cart count
    if ($user_id) {
        // For logged-in users
        $count_stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $count_stmt->execute([$user_id]);
    } else {
        // For guest users
        $count_stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ? AND user_id IS NULL");
        $count_stmt->execute([$session_id]);
    }
    $cart_count = $count_stmt->fetchColumn() ?? 0;

    // Send a success response
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart!',
        'cart_count' => $cart_count
    ]);

} catch (PDOException $e) {
    // Handle any database errors
    error_log(message: $e->getMessage()); // Log error for the admin
  echo json_encode(value: ['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>