<?php
// Always start the session to check the user's login status
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// --- THE CORE LOGIC: CHECK IF USER IS LOGGED IN ---
if (!isset($_SESSION['user_id'])) {
    // If user_id is NOT in the session, they are not logged in.
    // Send a specific error message and stop the script.
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to add items to your cart.',
        'action' => 'redirect_login' // This new key tells our JavaScript what to do
    ]);
    exit(); // Stop execution immediately
}

// --- If the user is logged in, proceed with the rest of the code ---

// Include the database connection
require_once __DIR__ . '/../includes/db.php';

// Get the logged-in user's ID from the session
$user_id = $_SESSION['user_id'];

// Get product_id and quantity from the POST request
$product_id = $_POST['product_id'] ?? null;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

// Validate the input
if (empty($product_id) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
    exit();
}

try {
    // Check if the item is already in the user's cart
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing_item = $stmt->fetch();

    if ($existing_item) {
        // If it exists, update the quantity
        $new_quantity = $existing_item['quantity'] + $quantity;
        $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update_stmt->execute([$new_quantity, $existing_item['id']]);
    } else {
        // If it's a new item, insert it
        $insert_stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert_stmt->execute([$user_id, $product_id, $quantity]);
    }

    // Get the new total cart count for the user
    $count_stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $cart_count = $count_stmt->fetchColumn() ?? 0;

    // Send a success response
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart!',
        'cart_count' => $cart_count
    ]);

} catch (PDOException $e) {
    // Handle any database errors
    error_log($e->getMessage()); // Log error for the admin
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>