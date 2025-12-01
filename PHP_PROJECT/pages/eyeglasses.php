<?php
session_start();

// --- ADD THIS LINE ---
// This connects to the database so the header can get the cart count.
require_once __DIR__ . '/../includes/db.php';

// Initialize filter variables
$productFilter = isset($_GET['frame']) ? $_GET['frame'] : null;
$brand = isset($_GET['brand']) ? $_GET['brand'] : null;
$price = isset($_GET['price']) ? $_GET['price'] : null;
$gender = isset($_GET['gender']) ? $_GET['gender'] : null;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$searchData = isset($_GET['search']) ? $_GET['search'] : null;
$limit = 6;

// Mock data - replace with actual API call or database query
function fetchGogglesData($page, $limit, $productFilter, $brand, $gender, $price, $searchData)
{
    // Read data from db.json
    $jsonFile = 'db.json';

    if (!file_exists($jsonFile)) {
        return [
            'products' => [],
            'totalCount' => 0
        ];
    }

    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);

    if (!$data || !isset($data['products'])) {
        return [
            'products' => [],
            'totalCount' => 0
        ];
    }

    $products = $data['products'];

    // Filter products based on criteria - ONLY apply filters that are actually set
    $filteredProducts = array_filter($products, function ($product) use ($productFilter, $brand, $gender, $searchData) {
        $matches = true;

        // Only apply frame filter if it's specifically set
        if ($productFilter !== null && $productFilter !== '') {
            $productFrame = $product['frame'] ?? '';
            // Handle different frame type mappings
            $frameMatches = false;
            if ($productFilter === 'FullRim' && in_array($productFrame, ['FullRim', 'random', 'randome'])) {
                $frameMatches = true;
            } elseif ($productFilter === 'HalfRim' && $productFrame === 'HalfRim') {
                $frameMatches = true;
            } elseif ($productFilter === 'RimLess' && $productFrame === 'RimLess') {
                $frameMatches = true;
            } elseif ($productFilter === 'Aviator' && $productFrame === 'Aviator') {
                $frameMatches = true;
            } elseif ($productFrame === $productFilter) {
                $frameMatches = true;
            }
            if (!$frameMatches) {
                $matches = false;
            }
        }

        // Only apply brand filter if it's specifically set
        if ($brand !== null && $brand !== '') {
            $productBrand = $product['brand'] ?? '';
            // Clean brand names for comparison
            $cleanProductBrand = trim(str_replace(',', '', $productBrand));
            if (stripos($cleanProductBrand, $brand) === false) {
                $matches = false;
            }
        }

        // Only apply gender filter if it's specifically set
        if ($gender !== null && $gender !== '') {
            if (!isset($product['gender']) || $product['gender'] !== $gender) {
                $matches = false;
            }
        }

        // Only apply search filter if it's specifically set
        if ($searchData !== null && $searchData !== '') {
            $searchIn = ($product['brand'] ?? '') . ' ' . ($product['sizeCollection'] ?? '');
            if (stripos($searchIn, $searchData) === false) {
                $matches = false;
            }
        }

        return $matches;
    });

    // Sort by price if specified
    if ($price !== null && $price !== '') {
        usort($filteredProducts, function ($a, $b) use ($price) {
            $priceA = isset($a['price']) ? (int) $a['price'] : 0;
            $priceB = isset($b['price']) ? (int) $b['price'] : 0;

            if ($price === 'asc') {
                return $priceA - $priceB;
            } else {
                return $priceB - $priceA;
            }
        });
    }

    $totalCount = count($filteredProducts);
    $offset = ($page - 1) * $limit;
    $paginatedProducts = array_slice($filteredProducts, $offset, $limit);

    return [
        'products' => $paginatedProducts,
        'totalCount' => $totalCount
    ];
}

$result = fetchGogglesData($page, $limit, $productFilter, $brand, $gender, $price, $searchData);
$gogglesData = $result['products'];
$totalCount = $result['totalCount'];
$totalPages = ceil($totalCount / $limit);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eyeglasses - Lenskart</title>
    <link rel="stylesheet" href="../assets/css/eyeglasses.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="../assets/js/smooth-performance.js"></script>
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <div>
        <img src="https://static5.lenskart.com/media/uploads/plp-free-lenses-desk.png" alt="Free Lenses Banner"
            style="width: 100%; height: auto;">

        <div class="ShowBtn">
            <i class="fas fa-bars" id="openFilter" onclick="toggleFilter()"></i>
            <i class="fas fa-times-circle" id="closeFilter" onclick="toggleFilter()" style="display: none;"></i>
        </div>

        <div class="EyeGlassesSection">
            <div class="EyeGlassesSection-Left" id="filterSection">
                <div class="filter-section">
                    <form method="GET" action="" id="filterForm">
                        <input type="hidden" name="page" value="1">
                        <?php if ($searchData): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchData); ?>">
                        <?php endif; ?>

                        <div class="filter-group">
                            <h3>AGE GROUP</h3>
                            <label>
                                <input type="radio" name="frame" value="Aviator" <?php echo $productFilter === 'Aviator' ? 'checked' : ''; ?> onchange="submitFilter()">
                                2-5 yrs(21)
                            </label>
                            <label>
                                <input type="radio" name="frame" value="HalfRim" <?php echo $productFilter === 'HalfRim' ? 'checked' : ''; ?> onchange="submitFilter()">
                                5-8 yrs(40)
                            </label>
                            <label>
                                <input type="radio" name="frame" value="FullRim" <?php echo $productFilter === 'FullRim' ? 'checked' : ''; ?> onchange="submitFilter()">
                                8-12 yrs(53)
                            </label>
                        </div>

                        <div class="filter-group">
                            <h3>FRAME TYPE</h3>
                            <div class="frame-options">
                                <div class="frame-option" onclick="setFrameFilter('FullRim')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/FullRim.png"
                                        alt="Full Rim">
                                    <p>Full Rim</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('RimLess')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Rimless.png"
                                        alt="Rimless">
                                    <p>Rimless</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('HalfRim')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/HalfRim.png"
                                        alt="Half Rim">
                                    <p>Half Rim</p>
                                </div>
                            </div>
                        </div>

                        <div class="filter-group">
                            <h3>FRAME SHAPE</h3>
                            <div class="frame-options">
                                <div class="frame-option" onclick="setFrameFilter('Rectangle')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Rectangle.png"
                                        alt="Rectangle">
                                    <p>Rectangle</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Square')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Square.png"
                                        alt="Square">
                                    <p>Square</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Round')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Round.png"
                                        alt="Round">
                                    <p>Round</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('CatEye')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/CatEye.png"
                                        alt="Cat Eye">
                                    <p>Cat Eye</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Geometric')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Geometric.png"
                                        alt="Geometric">
                                    <p>Geometric</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Aviator')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Aviator.png"
                                        alt="Aviator">
                                    <p>Aviator</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Wayfarer')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Wayfarer.png"
                                        alt="Wayfarer">
                                    <p>Wayfarer</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Hexagonal')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Hexagonal.png"
                                        alt="Hexagonal">
                                    <p>Hexagonal</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Oval')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Oval.png"
                                        alt="Oval">
                                    <p>Oval</p>
                                </div>
                                <div class="frame-option" onclick="setFrameFilter('Clubmaster')">
                                    <img src="https://static.lenskart.com/images/cust_mailer/Eyeglass/Clubmaster.png"
                                        alt="Clubmaster">
                                    <p>Clubmaster</p>
                                </div>
                            </div>
                        </div>

                        <div class="filter-menu">
                            <select class="filter-item" name="brand" onchange="submitFilter()">
                                <option value="">BRANDS</option>
                                <option value="John Jacobs" <?php echo $brand === 'John Jacobs' ? 'selected' : ''; ?>>John
                                    Jacobs(841)</option>
                                <option value="Lenskart Air" <?php echo $brand === 'Lenskart Air' ? 'selected' : ''; ?>>
                                    Lenskart Air(516)</option>
                                <option value="Vincent Chase" <?php echo $brand === 'Vincent Chase' ? 'selected' : ''; ?>>
                                    Vincent Chase(501)</option>
                            </select>

                            <select class="filter-item">
                                <option>FRAME SIZE</option>
                                <option value="1">Extra Narrow(123)</option>
                                <option value="2">Narrow(524)</option>
                                <option value="3">Extra Wide(244)</option>
                            </select>

                            <select class="filter-item" name="price" onchange="submitFilter()">
                                <option value="">PRICE</option>
                                <option value="asc" <?php echo $price === 'asc' ? 'selected' : ''; ?>>Low To High</option>
                                <option value="desc" <?php echo $price === 'desc' ? 'selected' : ''; ?>>High To Low
                                </option>
                            </select>

                            <select class="filter-item" name="gender" onchange="submitFilter()">
                                <option value="">GENDER</option>
                                <option value="Kids" <?php echo $gender === 'Kids' ? 'selected' : ''; ?>>Kids</option>
                                <option value="Mans" <?php echo $gender === 'Mans' ? 'selected' : ''; ?>>Mans</option>
                                <option value="Females" <?php echo $gender === 'Females' ? 'selected' : ''; ?>>Females
                                </option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="EyeGlassesSection-Right page-transition">
                <?php if (empty($gogglesData)): ?>
                    <div style="text-align: center; padding: 50px;">
                        <h3>No products found</h3>
                        <p>Try adjusting your filters or search terms.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($gogglesData as $product): ?>
                        <div class="card">
                            <div class="card-header">
                               
                                <a href="product-details.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo $product['imageUrl']; ?>" alt="<?php echo $product['brand']; ?>"
                                        class="product-image1" loading="lazy">
                                    <img src="<?php echo isset($product['imageUrl2']) ? $product['imageUrl2'] : $product['imageUrl']; ?>"
                                        alt="<?php echo $product['brand']; ?>" class="product-image2" loading="lazy">
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="rating">
                                    <span class="rating-value"><?php echo $product['rating']; ?></span>
                                    <span class="rating-count">(<?php echo $product['reviews']; ?>)</span>
                                </div>
                                <h2 class="product-title"><?php echo $product['brand']; ?></h2>
                                <div class="ColorAndPrizeSize">
                                    <div>
                                        <p class="product-size"><?php echo $product['sizeCollection']; ?></p>
                                        <p class="product-price">₹<?php echo number_format($product['price']); ?></p>
                                    </div>
                                    <div>
                                        <div class="color-options">
                                            <span class="color-dot black"></span>
                                            <span class="color-dot blue"></span>
                                            <span class="color-dot gray"></span>
                                            <?php if (!empty($product['discount'])): ?>
                                                <span class="color-dot more"><?php echo $product['discount']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="offer">Get FREE BLU Screen Lenses</p>
                            </div>
                            <div class="card-actions">
                                <button class="add-to-cart-btn" onclick="addToCart('<?php echo $product['id']; ?>', event)"
                                    data-product-id="<?php echo $product['id']; ?>">
                                    <span class="btn-content">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span class="btn-text">Add to Cart</span>
                                    </span>
                                    <span class="btn-loading" style="display: none;">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <span>Adding...</span>
                                    </span>
                                    <span class="btn-success" style="display: none;">
                                        <i class="fas fa-check"></i>
                                        <span>Added!</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="botton-page-button">
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                        class="pagination-button">Previous</a>
                <?php else: ?>
                    <button class="pagination-button" disabled>Previous</button>
                <?php endif; ?>

                <span class="page-number">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                        class="pagination-button">Next</a>
                <?php else: ?>
                    <button class="pagination-button" disabled>Next</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function toggleFilter() {
            const filterSection = document.getElementById('filterSection');
            const openBtn = document.getElementById('openFilter');
            const closeBtn = document.getElementById('closeFilter');
            const overlay = document.querySelector('.filter-overlay') || createOverlay();
            
            if (filterSection.style.transform === 'translateX(0px)') {
                filterSection.style.transform = 'translateX(-400px)';
                openBtn.style.display = 'block';
                closeBtn.style.display = 'none';
                overlay.style.display = 'none';
                document.body.style.overflow = 'auto';
            } else {
                filterSection.style.transform = 'translateX(0px)';
                openBtn.style.display = 'none';
                closeBtn.style.display = 'block';
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        function createOverlay() {
            const overlay = document.createElement('div');
            overlay.className = 'filter-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 998;
                display: none;
                transition: opacity 0.3s ease;
            `;
            overlay.addEventListener('click', toggleFilter);
            document.body.appendChild(overlay);
            return overlay;
        }
        
       async function addToCart(productId, event) {
    const button = event.target.closest('.add-to-cart-btn');
    if (!button || button.disabled) return;

    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `<span class="btn-loading"><i class="fas fa-spinner fa-spin"></i><span>Adding...</span></span>`;

    try {
        const params = new URLSearchParams();
        params.append('product_id', productId);
        params.append('quantity', '1');

        const response = await fetch('../admin/add-to-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        });

        const responseText = await response.text();
        
        try {
            const result = JSON.parse(responseText);

            if (!response.ok || !result.success) {
                // --- NEW LOGIC TO HANDLE REDIRECT ---
                if (result.action === 'redirect_login') {
                    showNotification('ⓘ ' + result.message, 'info');
                    // Wait 2 seconds for user to read the message, then redirect
                    setTimeout(() => {
                        window.location.href = '../auth/signin.php'; // Redirect to your login page
                    }, 2000);
                    return; // Stop further execution
                }
                
                // --- ADMIN RESTRICTION LOGIC ---
                if (result.action === 'admin_restricted') {
                    showNotification('⚠️ ' + result.message, 'warning');
                    return; // Stop further execution
                }
                // --- END OF NEW LOGIC ---

                // This handles other general errors
                throw new Error(result.message || 'Failed to add item to cart.');
            }

            // --- SUCCESS ---
            button.innerHTML = `<span class="btn-success"><i class="fas fa-check"></i><span>Added!</span></span>`;
            updateCartCount(result.cart_count || 0);
            
            // Trigger custom event for header to update cart count
            window.dispatchEvent(new CustomEvent('cartUpdated', {
                detail: { count: result.cart_count || 0 }
            }));
            
            showNotification('✓ ' + (result.message || 'Added to cart'), 'success');

        } catch (jsonError) {
            console.error("⛔ FATAL: The server responded with an error instead of JSON.");
            console.error("Below is the exact error message from the PHP script:");
            console.log(responseText);
            throw new Error('Server returned an invalid response. Check console.');
        }

    } catch (err) {
        // --- FAILURE ---
        console.error('addToCart error:', err.message);
        button.innerHTML = `<span class="btn-success" style="background-color: #dc3545;"><i class="fas fa-times"></i><span>Failed!</span></span>`;
        // Don't show a notification if we are already redirecting
        if (!err.message.includes('log in')) {
            showNotification('✗ ' + err.message, 'error');
        }
    } finally {
        // Reset button only if we are not redirecting
        const shouldReset = !document.querySelector('.notification-info');
        if (shouldReset) {
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 2000);
        }
    }
}
        // Helper functions (updateCartCount, showNotification, etc.) remain the same
        function updateCartCount(count) {
            const cartCounts = document.querySelectorAll('.cart-count');
            cartCounts.forEach(el => {
                el.textContent = count;
                el.style.display = count > 0 ? 'flex' : 'none';
            });
        }

        function showNotification(message, type = 'info') {
            document.querySelectorAll('.custom-notification').forEach(el => el.remove());
            const notification = document.createElement('div');
            notification.className = `custom-notification notification-${type}`;
            let bgColor = '#6c757d'; // default info color
            if (type === 'success') bgColor = '#28a745';
            else if (type === 'error') bgColor = '#dc3545';
            else if (type === 'warning') bgColor = '#ffc107';
            else if (type === 'info') bgColor = '#17a2b8';
            
            notification.innerHTML = `<div style="padding:12px 16px;border-radius:8px;background:${bgColor};color:${type==='warning'?'#000':'#fff'};box-shadow:0 4px 12px rgba(0,0,0,0.15);">${message}</div>`;
            notification.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;animation:slideInRight .3s ease;';
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.animation = 'slideOutRight .3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        (function injectNotifStyles(){
            if (document.getElementById('notif-animations')) return;
            const s = document.createElement('style');
            s.id = 'notif-animations';
            s.textContent = `@keyframes slideInRight{from{transform:translateX(100%)}to{transform:translateX(0)}}@keyframes slideOutRight{from{transform:translateX(0)}to{transform:translateX(100%)}}`;
            document.head.appendChild(s);
        })();

        function submitFilter() { 
            // Add loading state
            const form = document.getElementById('filterForm');
            if (form) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Loading...';
                }
                form.submit();
            }
        }
        
        function setFrameFilter(frameType) {
            const form = document.getElementById('filterForm');
            let input = form.querySelector('input[name="frame"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'frame';
                form.appendChild(input);
            }
            input.value = frameType;
            
            // Add visual feedback
            const frameOptions = document.querySelectorAll('.frame-option');
            frameOptions.forEach(option => option.classList.remove('selected'));
            event.target.closest('.frame-option').classList.add('selected');
            
            // Debounce the submission
            clearTimeout(window.filterTimeout);
            window.filterTimeout = setTimeout(submitFilter, 300);
        }
        
        // Add smooth scroll to top after filter
        window.addEventListener('load', function() {
            if (window.location.search) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
        
        // Add loading states for filter changes
        document.addEventListener('DOMContentLoaded', function() {
            // Add page load animation
            const pageContent = document.querySelector('.page-transition');
            if (pageContent) {
                setTimeout(() => {
                    pageContent.classList.add('loaded');
                }, 100);
            }
            
            const filterSelects = document.querySelectorAll('.filter-item');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.style.opacity = '0.7';
                    setTimeout(() => {
                        this.style.opacity = '1';
                    }, 300);
                });
            });
            
            // Add stagger animation to cards
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 150 + (index * 50));
            });
        });
    </script>
</body>

</html>