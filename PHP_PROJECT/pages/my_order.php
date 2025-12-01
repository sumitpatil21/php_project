<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/product.php';

// --- Authentication Check ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/signin.php');
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
}

// Get all products for reference
$all_products = getProducts();
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
        body {
            background: #fafafa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .orders-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .page-header {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .page-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0;
        }
        
        .order-card {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e8e8e8;
        }
        
        .order-info {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .order-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .order-info-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .order-info-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }
        
        .order-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .order-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-status.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .order-body {
            padding: 20px;
        }
        
        .order-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #fafafa;
            border-radius: 6px;
        }
        
        .order-item img {
            width: 120px;
            height: auto;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #e8e8e8;
            padding: 5px;
            background: #fff;
        }
        
        .order-item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .order-item-name {
            font-size: 15px;
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .order-item-qty {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .order-item-price {
            font-size: 16px;
            color: #007bff;
            font-weight: 700;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e8e8e8;
        }
        
        .order-total {
            font-size: 18px;
            color: #333;
        }
        
        .order-total-amount {
            font-size: 24px;
            color: #007bff;
            font-weight: 700;
            margin-left: 10px;
        }
        
        .view-details-btn {
            padding: 10px 24px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .view-details-btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.3);
        }
        
        .no-orders {
            background: #fff;
            text-align: center;
            padding: 60px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .no-orders i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-orders p {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .order-info {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .order-item {
                flex-direction: column;
            }
            
            .order-item img {
                width: 100%;
                max-width: 200px;
                margin: 0 auto;
            }
            
            .order-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="orders-container">
        <div class="page-header">
            <h1><i class="fas fa-shopping-bag"></i> My Orders</h1>
        </div>

        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <i class="fas fa-shopping-bag"></i>
                <p>You haven't placed any orders yet.</p>
                <a href="eyeglasses.php" class="view-details-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): 
                $items = json_decode($order['items_json'], true);
            ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-info-item">
                                <span class="order-info-label">Order ID</span>
                                <span class="order-info-value">#<?php echo htmlspecialchars($order['id']); ?></span>
                            </div>
                            <div class="order-info-item">
                                <span class="order-info-label">Order Date</span>
                                <span class="order-info-value"><?php echo date("M d, Y", strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="order-info-item">
                                <span class="order-info-label">Total Amount</span>
                                <span class="order-info-value">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                        <div class="order-status <?php echo strtolower($order['order_status']); ?>">
                            <?php echo htmlspecialchars($order['order_status']); ?>
                        </div>
                    </div>
                    <div class="order-body">
                        <div class="order-items">
                            <?php if (is_array($items) && !empty($items)): ?>
                                <?php foreach ($items as $item): 
                                    $product_id = $item['product_id'] ?? null;
                                    $product_image = 'https://via.placeholder.com/120';
                                    $product_name = $item['name'] ?? 'Unknown Product';
                                    
                                    if ($product_id && isset($all_products[$product_id])) {
                                        $product_image = $all_products[$product_id]['imageUrl'] ?? $product_image;
                                        $product_name = $all_products[$product_id]['brand'] ?? $product_name;
                                    }
                                ?>
                                    <div class="order-item">
                                        <img src="<?php echo htmlspecialchars($product_image); ?>" alt="<?php echo htmlspecialchars($product_name); ?>">
                                        <div class="order-item-details">
                                            <div class="order-item-name"><?php echo htmlspecialchars($product_name); ?></div>
                                            <div class="order-item-qty">Quantity: <?php echo $item['qty'] ?? 1; ?></div>
                                            <div class="order-item-price">₹<?php echo number_format($item['price'] ?? 0, 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Item details unavailable.</p>
                            <?php endif; ?>
                        </div>
                        <div class="order-footer">
                            <div class="order-total">
                                Total: <span class="order-total-amount">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="view-details-btn">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>