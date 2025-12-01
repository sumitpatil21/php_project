<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';
$full_name = $isLoggedIn ? $_SESSION['full_name'] : '';

// Get cart count for both logged-in and guest users
$cart_count = 0;
if (isset($pdo)) {
    try {
        if ($isLoggedIn) {
            // For logged-in users, get from database
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            $cart_count = $result['total'] ?? 0;
        } else {
            // For guest users, get from database using session_id
            $session_id = session_id();
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ? AND user_id IS NULL");
            $stmt->execute([$session_id]);
            $result = $stmt->fetch();
            $cart_count = $result['total'] ?? 0;
        }
    } catch(PDOException $e) {
        // Handle error silently
        $cart_count = 0;
    }
}

// Determine the correct base URL based on current directory
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
$base_url = ($current_dir === 'auth' || $current_dir === 'pages') ? '../' : './';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lenskart - Buy Eyeglasses, Sunglasses & Contact Lenses Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Header Styles */
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Top Bar */
        .top-bar {
            background: #f8f9fa;
            font-size: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .top-bar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
        }
        
        .left-links, .right-links {
            display: flex;
            gap: 15px;
        }
        
        .top-bar a {
            color: #666;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .top-bar a:hover {
            color: #007bff;
        }
        
        /* Main Bar */
        .main-bar {
            padding: 15px 0;
        }
        
        .main-bar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo img {
            height: 40px;
        }
        
        .phone-number {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
        }
        
        .phone-number i {
            margin-right: 5px;
            color: #28a745;
        }
        
        /* Search Bar */
        .search-bar {
            flex: 1;
            max-width: 580px;
    
        }
        
        .search-form {
            display: flex;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
        }
        
        .search-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        
        .search-btn {
            position: absolute;
            right: 0;
            top: 10;
            bottom: 0;
            height: 38.7px;
            border: none;
            border-radius: 0 4px 4px 0;
            padding: 10 15px;
            color: white;
            cursor: pointer;
        }
        
        .search-btn:hover {
            background: #0056b3;
        }
        
        /* Account Links */
        .account-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .account-link {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            white-space: nowrap;
        }
        
        .account-link:hover {
            color: #007bff;
        }
        
        .account-link i {
            font-size: 16px;
        }
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 4px;
            padding: 10px;
            top: 100%;
            right: 0;
            border: 1px solid #ddd;
        }
        
        .dropdown-content p {
            margin: 0 0 10px 0;
            padding: 5px;
            border-bottom: 1px solid #eee;
            font-weight: bold;
            color: #333;
        }
        
        .dropdown-content a {
            display: block;
            padding: 8px 12px;
            text-decoration: none;
            color: #333;
            border-radius: 3px;
            transition: background-color 0.3s;
        }
        
        .dropdown-content a:hover {
            background: #f5f5f5;
        }
        
        .user-dropdown:hover .dropdown-content {
            display: block;
        }
        
        /* Cart Count */
       /* Replace your old .cart-count rule with this one */
/* ADD THESE TWO NEW RULES */
.account-link.cart-icon {
    position: relative; /* This makes it the parent */
}

.cart-count {
    background: #ff4444;
    color: white;
    border-radius: 50%;
    padding: 2px 5px;
    font-size: 10px;
    font-weight: bold;
    min-width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    
    /* This positions the badge at the top-right */
    position: absolute;
    top: -6px;
    right: -8px;
}
        /* Bottom Navigation Bar */
        .bottom-bar {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 12px 0;
        }
        
        .bottom-bar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .main-navigation {
            display: flex;
            gap: 30px;
        }
        
        .nav-link {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .nav-link:hover {
            color: #007bff;
        }
        
        .brand-logos {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .brand-logo {
            height: 24px;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .brand-logo:hover {
            opacity: 1;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .left-links {
                display: none;
            }
            
            .main-bar-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .search-bar {
                order: 1;
                max-width: none;
                width: 100%;
            }
            
            .logo-section {
                order: 0;
            }
            
            .account-links {
                order: 2;
                gap: 15px;
            }
            
            .bottom-bar-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .main-navigation {
                flex-wrap: wrap;
                justify-content: center;
                gap: 30px;
                flex: 1;
                display: flex;
            }
            
            .phone-number {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .account-links {
                flex-wrap: wrap;
                justify-content: center;
            }
  

        .nav-link-right {
        margin-left: auto; /* This pushes the link to the right */
            }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="container">
                <div class="top-bar-content">
                    <div class="left-links">
                        <a href="">Do More, Be More</a>
                        <a href="">Try3D</a>
                        <a href="">Store Locator</a>
                        <a href="">Singapore</a>
                        <a href="">UAE</a>
                        <a href="">John Jacobs</a>
                        <a href="">Aqualens</a>
                        <a href="">Partner With Us</a>
                    </div>
                    <div class="right-links">
                        <a href="">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Bar -->
        <div class="main-bar">
            <div class="container">
                <div class="main-bar-content">
                    <div class="logo-section">
                        <a href="/PHP_PROJECT/index.php" class="logo">
                            <img src="https://static.lenskart.com/media/desktop/img/site-images/main_logo.svg" alt="Lenskart Logo">
                        </a>
                        <span class="phone-number">
                            <i class="fas fa-phone"></i>
                            1800-202-4444
                        </span>
                    </div>

                    <div class="search-bar">
                        <form class="search-form" action="/PHP_PROJECT/pages/eyeglasses.php" method="GET">
                            <input type="text" name="search" placeholder="What are you looking for?" class="search-input" 
                                   value="<?php echo htmlspecialchars($searchData ?? ''); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>

                    <div class="account-links">
                        <?php if ($isLoggedIn): ?>
                            <div class="user-dropdown">
                                <a href="#" class="account-link">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($username); ?>
                                    <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px;"></i>
                                </a>
                                <div class="dropdown-content">
                                    <p>Hello, <?php echo htmlspecialchars($full_name); ?>!</p>
                                    <a href="/PHP_PROJECT/pages/profile.php">
                                        <i class="fas fa-user"></i> My Profile
                                    </a>
                                    <a href="/PHP_PROJECT/pages/my_order.php">
                                        <i class="fas fa-shopping-bag"></i> My Orders
                                    </a>
                                    <a href="/PHP_PROJECT/controllers/auth.php?action=logout">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="/PHP_PROJECT/auth/signup.php" class="account-link">
                                <i class="fas fa-user-plus"></i>
                                Sign up
                            </a>
                            <a href="/PHP_PROJECT/auth/signin.php" class="account-link">
                                <i class="fas fa-user"></i>
                                Sign In
                            </a>
                        <?php endif; ?>
                    
                       <?php if ($isLoggedIn && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                            <a href="/PHP_PROJECT/admin/admin_panel.php" class="account-link">
                                <i class="fas fa-cog"></i> 
                                Admin Panel 
                                </a>
                        <?php endif; ?>
                        <!--  endif;-->
        
                
                        
                        <a href="/PHP_PROJECT/pages/cart.php" class="account-link cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            Cart
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-count"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="bottom-bar">
            <div class="container">
                <div class="bottom-bar-content">
                    <nav class="main-navigation">
                        <a href="/PHP_PROJECT/pages/eyeglasses.php" class="nav-link">EYEGLASSES</a>
                        <a href="/PHP_PROJECT/pages/eyeglasses.php?page=2" class="nav-link">SCREEN GLASSES</a>
                        <a href="/PHP_PROJECT/pages/eyeglasses.php?page=3" class="nav-link">KIDS GLASSES</a>
                        <a href="/PHP_PROJECT/pages/eyeglasses.php?page=4" class="nav-link">CONTACT LENSES</a>
                        <a href="/PHP_PROJECT/pages/eyeglasses.php?page=5" class="nav-link">SUNGLASSES</a>
                        <a href="/PHP_PROJECT/pages/eyeglasses.php?page=6" class="nav-link">HOME EYE-TEST</a>
                        <a href="/PHP_PROJECT/pages/eyeglasses.php?page=7" class="nav-link">STORE LOCATOR</a>
                    </nav>

                    <div class="brand-logos">
                        <img src="https://static1.lenskart.com/media/desktop/img/May22/3dtryon1.png" alt="3D Try On" class="brand-logo">
                        <img src="https://static1.lenskart.com/media/desktop/img/Mar22/13-Mar/blulogo.png" alt="Blue Light" class="brand-logo">
                        <img src="https://static5.lenskart.com/media/uploads/gold_max_logo_dc.png" alt="Gold Max" class="brand-logo">
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <script>
        // Realtime cart count update function
        function updateCartCount(count) {
            const cartCounts = document.querySelectorAll('.cart-count');
            const cartLinks = document.querySelectorAll('.cart-icon');
            
            cartCounts.forEach(el => {
                el.textContent = count;
                el.style.display = count > 0 ? 'flex' : 'none';
            });
            
            // If no cart count element exists and count > 0, create one
            if (count > 0 && cartCounts.length === 0) {
                cartLinks.forEach(link => {
                    if (!link.querySelector('.cart-count')) {
                        const countSpan = document.createElement('span');
                        countSpan.className = 'cart-count';
                        countSpan.textContent = count;
                        link.appendChild(countSpan);
                    }
                });
            }
        }
        
        // Function to fetch current cart count
        async function fetchCartCount() {
            try {
                const response = await fetch('/PHP_PROJECT/api/get-cart-count.php');
                const result = await response.json();
                if (result.success) {
                    updateCartCount(result.cart_count);
                }
            } catch (error) {
                console.log('Could not fetch cart count:', error);
            }
        }
        
        // Update cart count every 2 seconds for better responsiveness
        setInterval(fetchCartCount, 2000);
        
        // Also update when page loads
        document.addEventListener('DOMContentLoaded', fetchCartCount);
        
        // Listen for custom cart update events
        window.addEventListener('cartUpdated', function(event) {
            if (event.detail && typeof event.detail.count !== 'undefined') {
                updateCartCount(event.detail.count);
            } else {
                fetchCartCount();
            }
        });
    </script>