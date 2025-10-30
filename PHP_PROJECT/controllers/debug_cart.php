
<?php
session_start();
header('Content-Type: application/json');

// Debug information
$debug_info = [
    'session_id' => session_id(),
    'cart_contents' => $_SESSION['cart'] ?? 'No cart',
    'post_data' => $_POST,
    'server_info' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'Not set'
    ]
];

echo json_encode([
    'success' => true,
    'message' => 'Debug endpoint working',
    'debug' => $debug_info
]);
?>
