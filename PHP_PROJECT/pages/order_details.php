<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Authentication check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/signin.php');
    exit;
}

// Get order ID from URL
$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header('Location: my_order.php');
    exit;
}

// Fetch order details
$order = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = "Order not found or access denied.";
        header('Location: my_order.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    $_SESSION['error'] = "Could not fetch order details.";
    header('Location: my_order.php');
    exit;
}

// Decode items JSON
$items = json_decode($order['items_json'], true);
if (!is_array($items)) {
    $items = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - #<?php echo htmlspecialchars($order['id']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-details-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .order-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }
        .info-card h3 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .info-card p {
            margin: 0;
            font-weight: 600;
            color: #212529;
        }
        .items-section {
            margin-bottom: 30px;
        }
        .item-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .item-details {
            flex-grow: 1;
            margin-left: 15px;
        }
        .item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .item-price {
            color: #28a745;
            font-weight: 600;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background-color: #5a6268;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="order-details-container">
        <a href="my_order.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to My Orders
        </a>

        <div class="order-header">
            <h1>Order Details</h1>
            <p>Order ID: #<?php echo htmlspecialchars($order['id']); ?></p>
        </div>

        <div class="order-info">
            <div class="info-card">
                <h3>Order Date</h3>
                <p><?php echo date("M d, Y g:i A", strtotime($order['created_at'])); ?></p>
            </div>
            <div class="info-card">
                <h3>Order Status</h3>
                <p>
                    <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                        <?php echo htmlspecialchars($order['order_status']); ?>
                    </span>
                </p>
            </div>
            <div class="info-card">
                <h3>Total Amount</h3>
                <p>₹<?php echo number_format($order['total_amount']); ?></p>
            </div>
        </div>

        <div class="info-card" style="margin-bottom: 30px;">
            <h3>Shipping Address</h3>
            <p>
                <?php echo htmlspecialchars($order['full_name']); ?><br>
                <?php echo htmlspecialchars($order['address_line1']); ?><br>
                <?php echo htmlspecialchars($order['city']); ?>, <?php echo htmlspecialchars($order['pincode']); ?><br>
                Phone: <?php echo htmlspecialchars($order['phone']); ?>
            </p>
        </div>

        <div class="items-section">
            <h2>Items Ordered</h2>
            <?php if (empty($items)): ?>
                <p>No items found for this order.</p>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="item-card">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div>Quantity: <?php echo $item['qty']; ?></div>
                            <div>Price per item: ₹<?php echo number_format($item['price']); ?></div>
                        </div>
                        <div class="item-price">
                            ₹<?php echo number_format($item['price'] * $item['qty']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>