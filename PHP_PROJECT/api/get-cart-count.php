<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

try {
    $cart_count = 0;
    
    if (isset($_SESSION['user_id'])) {
        // For logged-in users
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $cart_count = $result['total'] ?? 0;
    } else {
        // For guest users using session_id
        $session_id = session_id();
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ? AND user_id IS NULL");
        $stmt->execute([$session_id]);
        $result = $stmt->fetch();
        $cart_count = $result['total'] ?? 0;
    }
    
    echo json_encode(['success' => true, 'cart_count' => (int)$cart_count]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'cart_count' => 0]);
}
?>