<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// --- Authentication Check ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/signin.php'); // Redirect to login if not logged in
    exit;
}

// --- Fetch User's Orders ---
$orders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user orders: " . $e->getMessage());
    // You could display an error message here
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Basic styles for the orders page */
        .orders-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 25px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 15px;
        }
        .order-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden; /* Ensures contained borders */
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8f9fa;
            padding: 15px;
            font-size: 0.9rem;
            color: #555;
            border-bottom: 1px solid #e9ecef;
        }
        .order-header span { margin: 0 10px; }
        .order-body {
            padding: 20px;
            display: flex;
            flex-direction: column; /* Stack items vertically */
            gap: 15px; /* Space between item summary and details */
        }
        .order-details-summary {
             display: flex;
             justify-content: space-between;
             align-items: center;
        }
        .order-items-preview {
            font-size: 0.95rem;
            color: #333;
        }
         .order-items-preview span {
            display: block; /* Each item on a new line */
            margin-bottom: 5px;
        }
        .order-total {
            font-weight: bold;
            font-size: 1.1rem;
            text-align: right; /* Align total to the right */
        }
        .order-actions {
            margin-top: 10px; /* Space above the button */
            text-align: right;
        }
         .view-details-btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }
        .view-details-btn:hover {
            background-color: #0056b3;
        }
        .no-orders {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Basic item display within order */
         .item-in-order {
             display: flex;
             align-items: center;
             gap: 10px;
             font-size: 0.9rem;
             padding-bottom: 10px;
             border-bottom: 1px dashed #eee;
         }
         .item-in-order:last-child {
             border-bottom: none;
             padding-bottom: 0;
         }
         .item-in-order img {
             width: 40px;
             height: 40px;
             object-fit: contain;
             border: 1px solid #eee;
             border-radius: 4px;
         }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="orders-container">
        <h1>My Orders</h1>

        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <p>You haven't placed any orders yet.</p>
                <a href="eyeglasses.php" class="view-details-btn" style="background-color: #28a745;">Start Shopping</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-item">
                    <div class="order-header">
                        <span>Order Placed: <?php echo date("M d, Y", strtotime($order['created_at'])); ?></span>
                        <span>Total: ₹<?php echo number_format($order['total_amount']); ?></span>
                        <span>Order ID: #<?php echo htmlspecialchars($order['id']); ?></span>
                        <span>Status: <?php echo htmlspecialchars($order['order_status']); ?></span>
                    </div>
                    <div class="order-body">
                        <div class="order-details-summary">
                            <div class="order-items-preview">
                                <?php
                                    // Decode items JSON
                                    $items = json_decode($order['items_json'], true);
                                    if (is_array($items) && !empty($items)) {
                                        // Display first few items or a summary
                                        $item_count = count($items);
                                        $display_limit = 2; // Show details for max 2 items
                                        $counter = 0;
                                        foreach ($items as $item) {
                                            if ($counter < $display_limit) {
                                                echo '<span>' . htmlspecialchars($item['name']) . ' (Qty: ' . $item['qty'] . ')</span>';
                                            }
                                            $counter++;
                                        }
                                        if ($item_count > $display_limit) {
                                            echo '<span>+ ' . ($item_count - $display_limit) . ' more item(s)</span>';
                                        }
                                    } else {
                                        echo '<span>Item details unavailable.</span>';
                                    }
                                ?>
                            </div>
                            <div class="order-actions">
                                <span class="order-total">Total: ₹<?php echo number_format($order['total_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>