<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: ../auth/signin.php');
    exit;
}

// Get user orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Get all products for reference
require_once __DIR__ . '/../controllers/product.php';
$all_products = getProducts();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $address, $user_id]);
    
    $success_message = "Profile updated successfully!";
    
    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Lenskart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fafafa;
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0;
            background: transparent;
        }
        
        .profile-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
        }
        
        .profile-sidebar {
            background: #fff;
            border-radius: 8px;
            padding: 0;
            height: fit-content;
        }
        
        .profile-header {
            text-align: center;
            padding: 30px 20px;
            background: #fff;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #e8e8e8;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 12px;
        }
        
        .profile-header h1 {
            font-size: 20px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .profile-header p {
            font-size: 13px;
            color: #666;
        }
        
        .sidebar-menu {
            padding: 0;
        }
        
        .menu-item {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            color: #666;
            font-size: 14px;
        }
        
        .menu-item:hover {
            background: #f8f9fa;
            color: #007bff;
        }
        
        .menu-item.active {
            background: #e7f1ff;
            border-left-color: #007bff;
            color: #007bff;
            font-weight: 600;
        }
        
        .menu-item i {
            width: 20px;
            font-size: 16px;
        }
        
        .profile-content {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
        
        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .order-card {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 15px;
        }
        
        .order-header h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-date {
            font-size: 13px;
            color: #888;
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
        
        .order-status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-items {
            margin-bottom: 15px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item img {
            width: 120px;
            height: auto;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #f0f0f0;
            padding: 5px;
        }
        
        .order-item-details h4 {
            font-size: 15px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-item-details p {
            font-size: 13px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .order-item-price {
            font-weight: 600;
            color: #007bff;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .order-address {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }
        
        .order-total {
            text-align: right;
        }
        
        .total-price {
            display: block;
            font-size: 20px;
            color: #007bff;
            font-weight: 700;
            margin-top: 5px;
        }
        
        .info-grid {
            display: grid;
            gap: 20px;
        }
        
        .info-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
        }
        
        .info-card h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card h3 i {
            color: #007bff;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #888;
            font-size: 14px;
        }
        
        .info-value {
            color: #333;
            font-size: 14px;
        }
        
        @media (max-width: 968px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                order: 2;
            }
            
            .profile-content {
                order: 1;
            }
            
            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .menu-item {
                border-left: none;
                border-bottom: 3px solid transparent;
                justify-content: center;
            }
            
            .menu-item.active {
                border-left: none;
                border-bottom-color: #007bff;
            }
        }
        
        @media (max-width: 576px) {
            .profile-container {
                margin: 10px;
            }
            
            .profile-content {
                padding: 20px 15px;
            }
            
            .info-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="profile-container">
        <?php if (isset($success_message)): ?>
            <div class="success-message" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-layout">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h1><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h1>
                    <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                </div>
                
                <div class="sidebar-menu">
                    <div class="menu-item active" onclick="showSection('profile')">
                        <i class="fas fa-user-circle"></i>
                        <span>My Profile</span>
                    </div>
                    <div class="menu-item" onclick="showSection('edit')">
                        <i class="fas fa-edit"></i>
                        <span>Edit Profile</span>
                    </div>
                    <div class="menu-item" onclick="showSection('orders')">
                        <i class="fas fa-shopping-bag"></i>
                        <span>My Orders</span>
                    </div>
                    <a href="../auth/logout.php" class="menu-item" style="text-decoration: none; color: inherit;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-content">
                <!-- Profile View Section -->
                <div id="profile-section" class="content-section active">
                    <h2 class="section-title">Personal Information</h2>
                    <div class="info-grid">
                        <div class="info-card">
                            <h3><i class="fas fa-user"></i> Basic Details</h3>
                            <div class="info-row">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['name'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Profile Section -->
                <div id="edit-section" class="content-section">
                    <h2 class="section-title">Edit Profile</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
                
                <!-- Orders Section -->
                <div id="orders-section" class="content-section">
                    <h2 class="section-title">My Orders</h2>
                    <?php if (empty($orders)): ?>
                        <div class="info-card">
                            <p style="text-align: center; color: #888; padding: 40px 0;">
                                <i class="fas fa-shopping-bag" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                No orders yet. Start shopping!
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): 
                            $items = json_decode($order['items_json'], true);
                        ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div>
                                        <h3>Order #<?php echo $order['id']; ?></h3>
                                        <p class="order-date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                                    </div>
                                    <div class="order-status <?php echo strtolower($order['order_status']); ?>">
                                        <?php echo htmlspecialchars($order['order_status']); ?>
                                    </div>
                                </div>
                                <div class="order-items">
                                    <?php foreach ($items as $item): 
                                        $product_id = $item['product_id'] ?? null;
                                        $product_image = 'https://via.placeholder.com/80';
                                        $product_name = $item['name'] ?? 'Unknown Product';
                                        
                                        if ($product_id && isset($all_products[$product_id])) {
                                            $product_image = $all_products[$product_id]['imageUrl'] ?? $product_image;
                                            $product_name = $all_products[$product_id]['brand'] ?? $product_name;
                                        }
                                    ?>
                                        <div class="order-item">
                                            <img src="<?php echo htmlspecialchars($product_image); ?>" alt="<?php echo htmlspecialchars($product_name); ?>">
                                            <div class="order-item-details">
                                                <h4><?php echo htmlspecialchars($product_name); ?></h4>
                                                <p>Quantity: <?php echo $item['qty'] ?? $item['quantity'] ?? 1; ?></p>
                                                <p class="order-item-price">₹<?php echo number_format($item['price'] ?? 0, 2); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="order-footer">
                                    <div class="order-address">
                                        <strong>Delivery Address:</strong><br>
                                        <?php echo htmlspecialchars($order['full_name']); ?><br>
                                        <?php echo htmlspecialchars($order['address_line1']); ?><br>
                                        <?php echo htmlspecialchars($order['city']); ?> - <?php echo htmlspecialchars($order['pincode']); ?><br>
                                        Phone: <?php echo htmlspecialchars($order['phone']); ?>
                                    </div>
                                    <div class="order-total">
                                        <strong>Total Amount:</strong>
                                        <span class="total-price">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Add active class to clicked menu item
            event.target.closest('.menu-item').classList.add('active');
        }
    </script>
</body>
</html>