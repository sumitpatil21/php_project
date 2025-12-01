<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/product.php'; // Needed to get product details

$all_products_from_json = getProducts();
$cart_items = []; // For displaying the summary
$subtotal = 0; // For displaying the summary
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();
$checkout_error = $_SESSION['checkout_error'] ?? null; // Get error from session
unset($_SESSION['checkout_error']); // Clear error after displaying

// --- Variables to control display ---
$order_placed_successfully = false;
$order_details = null; // To store current order info for success display
$order_history = []; // To store all orders for the user

// --- CHECK IF FORM WAS SUBMITTED ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Get and Validate Form Data ---
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');

    if (empty($full_name) || empty($phone) || empty($address_line1) || empty($city) || empty($pincode)) {
        $_SESSION['checkout_error'] = "Please fill in all shipping details.";
    } else {
        // --- Re-fetch Cart Items from DB (SERVER-SIDE) ---
        $items_for_json = [];
        $subtotal_server = 0;

        try {
            if ($user_id) {
                $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } else {
                $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE session_id = ? AND user_id IS NULL");
                $stmt->execute([$session_id]);
            }
            $items_in_db_cart_post = $stmt->fetchAll();

            if (empty($items_in_db_cart_post)) {
                $_SESSION['checkout_error'] = "Your cart is empty.";
            } else {
                // Prepare Items & Calculate Total (Same as before)
                 foreach ($items_in_db_cart_post as $item) { /* ... prepare $items_for_json ... */
                     $product_id = $item['product_id'];
                     $quantity = $item['quantity'];
                     if (isset($all_products_from_json[$product_id])) {
                         $product_details = $all_products_from_json[$product_id];
                         $items_for_json[] = [
                             'product_id' => $product_id,
                             'name' => $product_details['brand'] ?? 'Unknown Product',
                             'qty' => $quantity,
                             'price' => $product_details['price'] ?? 0
                         ];
                         $subtotal_server += ($product_details['price'] ?? 0) * $quantity;
                     }
                 }
                 $shipping_server = ($subtotal_server >= 1000 || $subtotal_server == 0) ? 0 : 50;
                 $total_server = $subtotal_server + $shipping_server;
                 $items_json_string = json_encode($items_for_json);
                 if ($items_json_string === false) { throw new Exception("Failed to encode items to JSON."); }


                // --- Start Database Transaction ---
                $pdo->beginTransaction();

                // 1. Insert into 'orders' table (Same as before)
                 $order_sql = "INSERT INTO orders (user_id, session_id, full_name, phone, address_line1, city, pincode, items_json, total_amount)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                 $order_stmt = $pdo->prepare($order_sql);
                 $order_stmt->execute([
                     $user_id, $user_id ? null : $session_id, $full_name, $phone, $address_line1, $city, $pincode, $items_json_string, $total_server
                 ]);
                 $order_id = $pdo->lastInsertId();

                // 2. Clear the cart (Same as before)
                 if ($user_id) { /* ... clear user cart ... */
                     $clear_cart_stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                     $clear_cart_stmt->execute([$user_id]);
                 } else { /* ... clear guest cart ... */
                     $clear_cart_stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL");
                     $clear_cart_stmt->execute([$session_id]);
                 }

                // --- Commit Transaction ---
                $pdo->commit();

                // --- Set flag and store details for success display ---
                $order_placed_successfully = true;
                $order_details = [ // Store details of the order just placed
                    'id' => $order_id,
                    'total_amount' => $total_server,
                    'order_status' => 'Pending',
                    'full_name' => $full_name,
                    'address_line1' => $address_line1,
                    'city' => $city,
                    'pincode' => $pincode,
                    'phone' => $phone,
                    'items' => $items_for_json
                ];
                $checkout_error = null; // Clear error

                // --- Fetch Order History (New!) ---
                if ($user_id) {
                    $history_stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                    $history_stmt->execute([$user_id]);
                    $order_history = $history_stmt->fetchAll();
                }
                 // Note: We are not fetching history for guest users in this example

            } // End cart empty check

        } catch (PDOException | Exception $e) { // Catch both types of errors
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("Order placement failed: " . $e->getMessage());
            $_SESSION['checkout_error'] = "Failed to place order. Please try again.";
            // Let the page reload and show the error
        }
    } // End validation check
} // End POST check

// --- If NOT a successful POST, fetch cart items for display ---
if (!$order_placed_successfully) {
    if ($user_id) { /* ... fetch user cart ... */
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } else { /* ... fetch guest cart ... */
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE session_id = ? AND user_id IS NULL");
        $stmt->execute([$session_id]);
    }
    $items_in_db_cart = $stmt->fetchAll();

    if (empty($items_in_db_cart) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: eyeglasses.php');
        exit;
    }

    foreach ($items_in_db_cart as $item) { /* ... calculate $subtotal, $total, $cart_items ... */
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        if (isset($all_products_from_json[$product_id])) {
            $product_details = $all_products_from_json[$product_id];
            $cart_items[] = ['product' => $product_details, 'quantity' => $quantity];
            $subtotal += ($product_details['price'] ?? 0) * $quantity;
        }
    }
    $shipping = ($subtotal >= 1000 || $subtotal == 0) ? 0 : 50;
    $total = $subtotal + $shipping;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $order_placed_successfully ? 'Order Confirmed!' : 'Checkout'; ?></title>
       <link rel="stylesheet" href="../assets/css/eyeglasses.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Optional: Add custom styles if needed, Tailwind handles most */
        :root {
            --primary-color: #007bff;
            --error-color: #dc3545;
        }
        .error-message {
            background-color: #f8d7da;
            color: var(--error-color);
            border: 1px solid #f5c6cb;
            padding: 1rem; /* Use Tailwind units if preferred */
            border-radius: 0.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
         /* Success message styles */
        .success-container { text-align: center; }
        .success-icon { font-size: 4rem; color: #28a745; margin-bottom: 1rem; }
        .order-details { text-align: left; margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1.5rem; }
        .order-details h3 { font-size: 1.25rem; margin-bottom: 1rem; font-weight: 600; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .items-summary .item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb; font-size: 0.9rem; }
        .items-summary .item:last-child { border-bottom: none; }
        .item-name { flex-grow: 1; margin-right: 1rem; }
        .item-qty { color: #6b7280; margin-right: 1rem; }
        .continue-shopping-btn { display: inline-block; margin-top: 2rem; padding: 0.75rem 1.5rem; background-color: var(--primary-color); color: #fff; text-decoration: none; border-radius: 0.375rem; font-weight: 500; }

        /* Order History Styles */
        .order-history-section { margin-top: 3rem; border-top: 1px solid #e5e7eb; padding-top: 2rem; }
        .order-history-item { background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 1rem; padding: 1rem; }
        .order-history-header { display: flex; justify-content: space-between; font-size: 0.9rem; color: #6b7280; margin-bottom: 0.5rem; }
        .order-history-total { font-weight: 600; color: #111827; }

    </style>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

        <?php if (!empty($checkout_error)): // Check if error is not empty ?>
            <div class="error-message">
                <?php echo htmlspecialchars($checkout_error); ?>
            </div>
        <?php endif; ?>

        <?php if ($order_placed_successfully): ?>
            <div class="bg-white p-6 md:p-8 rounded-lg shadow-md">
                <div class="success-container">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold text-green-600">Order Confirmed!</h1>
                    <p class="text-gray-600">Thank you for your purchase. Your order has been placed successfully.</p>
                    <p class="text-gray-600">Your Order ID is: <strong class="text-gray-800">#<?php echo htmlspecialchars($order_details['id']); ?></strong></p>

                    <div class="order-details">
                         <h3>Order Summary (Order #<?php echo htmlspecialchars($order_details['id']); ?>)</h3>
                         <div class="detail-row">
                            <span>Order Total:</span>
                            <strong class="text-gray-800">₹<?php echo number_format($order_details['total_amount']); ?></strong>
                         </div>
                         <div class="detail-row">
                             <span>Order Status:</span>
                             <strong class="text-gray-800"><?php echo htmlspecialchars($order_details['order_status']); ?></strong>
                         </div>
                         <h3 class="mt-4">Shipping To:</h3>
                         <p class="text-gray-700 leading-relaxed">
                             <?php echo htmlspecialchars($order_details['full_name']); ?><br>
                             <?php echo htmlspecialchars($order_details['address_line1']); ?><br>
                             <?php echo htmlspecialchars($order_details['city']); ?>, <?php echo htmlspecialchars($order_details['pincode']); ?><br>
                             Phone: <?php echo htmlspecialchars($order_details['phone']); ?>
                         </p>
                         <h3 class="mt-4">Items Ordered:</h3>
                         <div class="items-summary">
                             <?php foreach ($order_details['items'] as $item): ?>
                                 <div class="item">
                                     <span class="item-name text-gray-800"><?php echo htmlspecialchars($item['name']); ?></span>
                                     <span class="item-qty">Qty: <?php echo $item['qty']; ?></span>
                                     <span class="item-price font-medium text-gray-900">₹<?php echo number_format($item['price'] * $item['qty']); ?></span>
                                 </div>
                             <?php endforeach; ?>
                         </div>
                    </div>
                    <a href="eyeglasses.php" class="continue-shopping-btn">Continue Shopping</a>
                </div>

                <?php if (!empty($order_history) && $user_id): ?>
                    <div class="order-history-section">
                        <h2 class="text-xl md:text-2xl font-semibold mb-4 text-gray-800">Your Order History</h2>
                        <?php foreach ($order_history as $order): ?>
                            <div class="order-history-item">
                                <div class="order-history-header">
                                    <span>Order #<?php echo htmlspecialchars($order['id']); ?></span>
                                    <span><?php echo date("M d, Y", strtotime($order['created_at'])); ?></span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                     <span class="text-sm text-gray-600">Status: <?php echo htmlspecialchars($order['order_status']); ?></span>
                                    <span class="order-history-total">Total: ₹<?php echo number_format($order['total_amount']); ?></span>
                                </div>
                                </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                 <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-lg shadow-md">
                     <form action="" method="POST">
                         <div class="mb-8">
                             <h2 class="text-xl font-semibold border-b pb-4 mb-6 text-gray-800">Shipping Address</h2>
                             <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                 <div class="sm:col-span-2">
                                     <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                     <input type="text" id="full_name" name="full_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                 </div>
                                 <div class="sm:col-span-2">
                                     <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                     <input type="tel" id="phone" name="phone" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                 </div>
                                 <div class="sm:col-span-2">
                                     <label for="address_line1" class="block text-sm font-medium text-gray-700">Address</label>
                                     <input type="text" id="address_line1" name="address_line1" placeholder="Street Address" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                 </div>
                                 <div>
                                     <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                                     <input type="text" id="city" name="city" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                 </div>
                                 <div>
                                     <label for="pincode" class="block text-sm font-medium text-gray-700">Pincode</label>
                                     <input type="text" id="pincode" name="pincode" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                 </div>
                             </div>
                         </div>

                         <div class="mb-8">
                             <h2 class="text-xl font-semibold border-b pb-4 mb-6 text-gray-800">Payment Details</h2>
                             <p class="text-sm text-gray-600 mb-4">This is a test checkout. No real payment will be processed.</p>
                             <div class="space-y-4">
                                 <div>
                                     <label for="card_number" class="block text-sm font-medium text-gray-700">Card Number</label>
                                     <input type="text" id="card_number" name="card_number" value="4242 4242 4242 4242" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                 </div>
                                 <div class="grid grid-cols-2 gap-6">
                                     <div>
                                         <label for="expiry" class="block text-sm font-medium text-gray-700">Expiry (MM/YY)</label>
                                         <input type="text" id="expiry" name="expiry" value="12/28" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                     </div>
                                     <div>
                                         <label for="cvc" class="block text-sm font-medium text-gray-700">CVC</label>
                                         <input type="text" id="cvc" name="cvc" value="123" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                     </div>
                                 </div>
                             </div>
                         </div>

                         <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition duration-300">
                             Place Order
                         </button>
                     </form>
                 </div>

                 <div class="lg:col-span-1">
                     <div class="order-summary bg-white p-6 md:p-8 rounded-lg shadow-md sticky top-8">
                         <h3 class="text-xl font-semibold border-b pb-4 mb-6 text-gray-800">Order Summary</h3>
                         <div class="summary-items space-y-3 mb-6">
                            <?php if (empty($cart_items)): ?>
                                <p class="text-gray-500 text-center py-4">Your cart is empty.</p>
                             <?php else: ?>
                                 <?php foreach ($cart_items as $item): ?>
                                     <div class="flex items-center gap-4">
                                         <img src="<?php echo htmlspecialchars($item['product']['imageUrl']); ?>" alt="" class="w-16 h-16 object-contain rounded border">
                                         <div class="flex-grow">
                                             <h4 class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($item['product']['brand']); ?></h4>
                                             <p class="text-xs text-gray-500">Qty: <?php echo $item['quantity']; ?></p>
                                         </div>
                                         <span class="text-sm font-semibold text-gray-900">₹<?php echo number_format(($item['product']['price'] ?? 0) * $item['quantity']); ?></span>
                                     </div>
                                 <?php endforeach; ?>
                             <?php endif; ?>
                         </div>
                         <div class="space-y-2 border-t pt-4">
                             <div class="flex justify-between text-sm text-gray-600">
                                 <span>Subtotal</span>
                                 <span>₹<?php echo number_format($subtotal); ?></span>
                             </div>
                             <div class="flex justify-between text-sm text-gray-600">
                                 <span>Shipping</span>
                                 <span><?php echo $shipping == 0 ? 'FREE' : '₹' . number_format($shipping); ?></span>
                             </div>
                             <div class="flex justify-between text-lg font-bold text-gray-900 border-t pt-2 mt-2">
                                 <span>Total</span>
                                 <span>₹<?php echo number_format($total); ?></span>
                             </div>
                         </div>
                     </div>
                 </div>
            </div> <?php endif; // End check for order_placed_successfully ?>

    </div> </body>
</html>