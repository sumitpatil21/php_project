[file name]: diagnose.php
[file content begin]
<?php
session_start();

echo "<pre>";
echo "=== CART DIAGNOSIS ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";
echo "POST Data: " . print_r($_POST, true) . "\n";
echo "GET Data: " . print_r($_GET, true) . "\n";

// Test JSON output
header('Content-Type: application/json');
echo json_encode([
    'diagnosis' => 'success',
    'session_working' => isset($_SESSION['cart']),
    'cart_count' => isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0,
    'server_time' => date('Y-m-d H:i:s')
]);
?>
[file content end]