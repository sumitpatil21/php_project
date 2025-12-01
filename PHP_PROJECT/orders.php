<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: auth.php');
    exit();
}

// Handle status update
if ($_POST['action'] == 'update_status') {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Fetch orders
$orders = $conn->query("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Management</title>
    <style>
        .order-card { border: 1px solid #ddd; margin: 10px; padding: 15px; border-radius: 5px; }
        .status-select { padding: 5px; margin: 5px; }
        .update-btn { background: #007bff; color: white; padding: 5px 10px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h2>Order Management</h2>
    <a href="admin_panel.php">Back to Dashboard</a>
    
    <?php while ($order = $orders->fetch_assoc()): ?>
    <div class="order-card">
        <h4>Order #<?= $order['id'] ?> - <?= $order['username'] ?></h4>
        <p>Total: ₹<?= $order['total_amount'] ?></p>
        <p>Date: <?= $order['created_at'] ?></p>
        
        <select class="status-select" id="status_<?= $order['id'] ?>">
            <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
            <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
            <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
        
        <button class="update-btn" onclick="updateStatus(<?= $order['id'] ?>)">Update Status</button>
    </div>
    <?php endwhile; ?>

    <script>
    function updateStatus(orderId) {
        const status = document.getElementById('status_' + orderId).value;
        
        fetch('orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update_status&order_id=${orderId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status updated successfully!');
            } else {
                alert('Error updating status');
            }
        });
    }
    </script>
</body>
</html>