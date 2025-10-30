<?php
/** * update-cart.php
 * Handles all cart actions: increasing, decreasing, and removing items.
 * Works for both logged-in users and guest users (using session_id).
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Get the action and product ID from the JSON input
$input = json_decode(file_get_contents('php://input'), true);
$product_id = $input['product_id'] ?? null;
$action = $input['action'] ?? null; // 'increase', 'decrease', or 'remove'

if (empty($product_id) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing product ID or action.']);
    exit();
}

// Determine if the user is a guest or logged in
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

// --- Build the correct SQL WHERE clause and parameters ---
$where_clause = "AND product_id = ?";
$params = [];

if ($user_id) {
    $where_clause = "user_id = ? " . $where_clause;
    $params[] = $user_id;
} else {
    $where_clause = "session_id = ? AND user_id IS NULL " . $where_clause;
    $params[] = $session_id;
}
$params[] = $product_id;


try {
    switch ($action) {
        case 'increase':
            $sql = "UPDATE cart SET quantity = quantity + 1 WHERE $where_clause";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            break;

        case 'decrease':
            // Use GREATEST(1, ...) to prevent quantity from going below 1
            $sql = "UPDATE cart SET quantity = GREATEST(1, quantity - 1) WHERE $where_clause";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            break;

        case 'remove':
            $sql = "DELETE FROM cart WHERE $where_clause";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            exit();
    }

    echo json_encode(['success' => true, 'message' => 'Cart updated.']);

} catch (PDOException $e) {
    error_log($e->getMessage()); // Log the actual error for your reference
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}

exit();
?>