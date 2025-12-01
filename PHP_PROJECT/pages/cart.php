<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/product.php';

$all_products_from_json = getProducts();
$cart_items = [];
$subtotal = 0;
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

if ($user_id) {
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
} else {
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE session_id = ? AND user_id IS NULL");
    $stmt->execute(params: [$session_id]);
}
$items_in_db_cart = $stmt->fetchAll();

foreach ($items_in_db_cart as $item) {
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Shopping Cart</title>
    <link rel="stylesheet" href="../assets/css/eyeglasses.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --text-color: #333;
            --border-color: #e9ecef;
            --background-color: #f8f9fa;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            color: var(--text-color);
        }

        .cart-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 30px;
            text-align: center;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            align-items: flex-start;
        }

        .cart-items {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 8px;
        }

        .cart-item-details {
            flex-grow: 1;
        }

        .cart-item-details h4 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
        }

        .cart-item-details .price {
            font-weight: 600;
            color: var(--primary-color);
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid var(--border-color);
            border-radius: 20px;
        }

        .quantity-controls button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            font-size: 1rem;
        }

        .quantity-controls span {
            padding: 0 10px;
            font-weight: 500;
        }

        .item-total-price {
            font-weight: bold;
            font-size: 1.1rem;
            min-width: 80px;
            text-align: right;
        }

        .remove-item-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
        }

        /* Add these new styles to your existing <style> block */

        .order-summary {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
            position: sticky;
            top: 20px;
        }

        .order-summary h3 {
            margin-top: 0;
            font-size: 1.5rem;
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: #555;
            /* Lighter text for non-total rows */
        }

        /* New: Discount Code Form */
        .discount-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .discount-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .discount-btn {
            padding: 0 15px;
            border: none;
            border-radius: 4px;
            background: #6c757d;
            /* Secondary button color */
            color: white;
            cursor: pointer;
            font-weight: 500;
        }

        .discount-btn:hover {
            background: #5a6268;
        }

        /* New: Horizontal line divider */
        .summary-divider {
            border: 0;
            border-top: 1px solid var(--border-color);
            margin: 20px 0;
        }

        /* Modified: Bolder and larger Total */
        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }

        /* Modified: Checkout Button (re-paste to ensure it's correct) */
        .checkout-button {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background-color: var(--primary-color);
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            text-align: center;
            box-sizing: border-box;
        }

        /* New: Secure Checkout Badge */
        .secure-checkout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #666;
        }

        .secure-checkout i {
            color: #28a745;
            /* Green lock */
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 8px;
        }

        .empty-cart i {
            font-size: 4rem;
            color: #ccc;
        }

        .empty-cart p {
            font-size: 1.2rem;
            margin: 20px 0;
        }

        .continue-shopping-btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
        }

        @media (max-width: 992px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <div class="cart-container">
        <h1>Your Shopping Cart</h1>
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Your cart is currently empty.</p>
                <a href="eyeglasses.php" class="continue-shopping-btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="cart-item-image">
                                <img src="<?php echo htmlspecialchars($item['product']['imageUrl']); ?>"
                                    alt="<?php echo htmlspecialchars($item['product']['brand']); ?>">
                            </div>
                            <div class="cart-item-details">
                                <h4><?php echo htmlspecialchars($item['product']['brand']); ?></h4>
                                <p class="price">₹<?php echo number_format($item['product']['price']); ?></p>
                            </div>
                            <div class="quantity-controls">
                                <button
                                    onclick="handleCartAction('<?php echo htmlspecialchars($item['product']['id']); ?>', 'decrease')"
                                    title="Decrease quantity">-</button>
                                <span><?php echo $item['quantity']; ?></span>
                                <button
                                    onclick="handleCartAction('<?php echo htmlspecialchars($item['product']['id']); ?>', 'increase')"
                                    title="Increase quantity">+</button>
                            </div>
                            <p class="item-total-price">
                                ₹<?php echo number_format($item['product']['price'] * $item['quantity']); ?></p>
                            <button class="remove-item-btn"
                                onclick="handleCartAction('<?php echo htmlspecialchars($item['product']['id']); ?>', 'remove')"
                                title="Remove item">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-summary">
                    <h3>Order Summary</h3>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>₹<?php echo number_format($subtotal); ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?php echo $shipping == 0 ? 'FREE' : '₹' . number_format($shipping); ?></span>
                    </div>

                    <form class="discount-form">
                        <input type="text" placeholder="Gift card or discount code" class="discount-input">
                        <button type="submit" class="discount-btn">Apply</button>
                    </form>

                    <hr class="summary-divider">

                    <div class="summary-row total">
                        <span>Total</span>
                        <span>₹<?php echo number_format($total); ?></span>
                    </div>

                    <a href="/PHP_PROJECT/pages/order.php" class="checkout-button">Proceed to Checkout</a>

                    <div class="secure-checkout">
                        <i class="fas fa-lock"></i>
                        <span>Secure Checkout</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <script>
            async function handleCartAction(productId, action) {

                // This is the relative path that should work based on your folder structure
                const url = '../controllers/update-cart.php';

                // *** NEW DEBUGGING LINE ***
                // This will print the path to your browser console
                console.log('Attempting to fetch:', url);

                try {
                    const response = await fetch(url, { // Use the 'url' variable
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            action: action
                        })
                    });

                    if (!response.ok) {
                        // This will throw the 404 error if the path is still wrong
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const result = await response.json();

                    if (result.success) {
                        // Update cart count in header immediately
                        if (typeof updateCartCount === 'function') {
                            fetchCartCount();
                        }
                        window.location.reload();
                    } else {
                        alert('Error: ' + (result.message || 'Could not update cart.'));
                    }

                } catch (error) {
                    console.error('Failed to update cart:', error);
                    alert(error.message); // This will show the "HTTP error! Status: 404" message
                }
            }
        </script>
</body>

</html>