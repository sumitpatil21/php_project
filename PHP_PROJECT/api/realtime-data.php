<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once __DIR__ . '/../includes/db.php';

try {
    $action = $_GET['action'] ?? 'stats';
    
    switch($action) {
        case 'stats':
            // Get real-time statistics
            $stats = [
                'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
                'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
                'active_sessions' => $pdo->query("SELECT COUNT(DISTINCT session_id) FROM cart WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn(),
                'recent_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
                'cart_items' => $pdo->query("SELECT COUNT(*) FROM cart")->fetchColumn(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'recent_activity':
            // Get recent user activities
            $activities = [];
            
            // Recent registrations
            $stmt = $pdo->prepare("SELECT username, created_at FROM users ORDER BY created_at DESC LIMIT 5");
            $stmt->execute();
            $recent_users = $stmt->fetchAll();
            
            foreach($recent_users as $user) {
                $activities[] = [
                    'type' => 'user_registration',
                    'message' => "New user '{$user['username']}' registered",
                    'time' => $user['created_at']
                ];
            }
            
            // Recent orders
            $stmt = $pdo->prepare("SELECT id, full_name, total_amount, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
            $stmt->execute();
            $recent_orders = $stmt->fetchAll();
            
            foreach($recent_orders as $order) {
                $activities[] = [
                    'type' => 'new_order',
                    'message' => "Order #{$order['id']} placed by {$order['full_name']} for ₹{$order['total_amount']}",
                    'time' => $order['created_at']
                ];
            }
            
            // Sort by time
            usort($activities, function($a, $b) {
                return strtotime($b['time']) - strtotime($a['time']);
            });
            
            echo json_encode(['success' => true, 'data' => array_slice($activities, 0, 10)]);
            break;
            
        case 'cart_updates':
            // Get real-time cart updates
            if(isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(quantity) as total_items FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $cart_data = $stmt->fetch();
                
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'cart_count' => $cart_data['total_items'] ?? 0,
                        'cart_items' => $cart_data['count'] ?? 0
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not logged in']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>