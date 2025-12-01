<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Get product ID from URL
$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header('Location: eyeglasses.php');
    exit;
}

// Function to get product by ID
function getProductById($id) {
    $jsonPath = __DIR__ . '/db.json';
    if (!file_exists($jsonPath)) {
        return null;
    }
    
    $jsonData = file_get_contents($jsonPath);
    $data = json_decode($jsonData, true);
    
    if (!isset($data['products'])) {
        return null;
    }
    
    foreach ($data['products'] as $product) {
        if ($product['id'] === $id) {
            return $product;
        }
    }
    
    return null;
}

$product = getProductById($product_id);

if (!$product) {
    header('Location: eyeglasses.php');
    exit;
}

// Get related products (same brand or frame type)
function getRelatedProducts($currentProduct, $limit = 4) {
    $jsonPath = __DIR__ . '/db.json';
    if (!file_exists($jsonPath)) {
        return [];
    }
    
    $jsonData = file_get_contents($jsonPath);
    $data = json_decode($jsonData, true);
    
    if (!isset($data['products'])) {
        return [];
    }
    
    $related = [];
    foreach ($data['products'] as $product) {
        if ($product['id'] !== $currentProduct['id'] && 
            ($product['brand'] === $currentProduct['brand'] || 
             $product['frame'] === $currentProduct['frame'])) {
            $related[] = $product;
        }
    }
    
    return array_slice($related, 0, $limit);
}

$relatedProducts = getRelatedProducts($product);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['brand']); ?> - Product Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/product-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="../assets/js/smooth-performance.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="product-details-container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="../index.php">Home</a>
            <span>/</span>
            <a href="eyeglasses.php">Eyeglasses</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($product['brand']); ?></span>
        </nav>

        <!-- Product Details Section -->
        <div class="product-details">
            <!-- Product Images -->
            <div class="product-images">
                <div class="main-image">
                    <img id="mainImage" src="<?php echo htmlspecialchars($product['imageUrl']); ?>" 
                         alt="<?php echo htmlspecialchars($product['brand']); ?>">
                    <div class="image-overlay">
                        <button class="zoom-btn" onclick="openImageModal()">
                            <i class="fas fa-search-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="thumbnail-images">
                    <img class="thumbnail active" 
                         src="<?php echo htmlspecialchars($product['imageUrl']); ?>" 
                         onclick="changeMainImage(this.src)">
                    <?php if (isset($product['imageUrl2']) && $product['imageUrl2']): ?>
                    <img class="thumbnail" 
                         src="<?php echo htmlspecialchars($product['imageUrl2']); ?>" 
                         onclick="changeMainImage(this.src)">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="product-info">
                <div class="product-header">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['brand']); ?></h1>
                    <div class="product-rating">
                        <?php if (isset($product['rating']) && $product['rating']): ?>
                        <div class="rating-stars">
                            <?php 
                            $rating = floatval($product['rating']);
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'filled' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-text"><?php echo $rating; ?> (<?php echo $product['reviews']; ?> reviews)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="product-price">
                    <span class="current-price">₹<?php echo number_format($product['price']); ?></span>
                    <?php if (isset($product['originalPrice']) && $product['originalPrice'] > $product['price']): ?>
                    <span class="original-price">₹<?php echo number_format($product['originalPrice']); ?></span>
                    <span class="discount"><?php echo round((($product['originalPrice'] - $product['price']) / $product['originalPrice']) * 100); ?>% OFF</span>
                    <?php endif; ?>
                </div>

                <div class="product-features">
                    <div class="feature-item">
                        <i class="fas fa-glasses"></i>
                        <span><?php echo htmlspecialchars($product['sizeCollection'] ?? 'Standard Size'); ?></span>
                    </div>
                    <?php if (isset($product['frame'])): ?>
                    <div class="feature-item">
                        <i class="fas fa-shapes"></i>
                        <span><?php echo htmlspecialchars($product['frame']); ?> Frame</span>
                    </div>
                    <?php endif; ?>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>1 Year Warranty</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Free Shipping</span>
                    </div>
                </div>

                <?php if (isset($product['imageUrl2']) && $product['imageUrl2']): ?>
                <!-- Color Options -->
                <div class="color-selection">
                    <h3>Available Colors</h3>
                    <div class="color-options">
                        <div class="color-option active" data-color="black">
                            <span class="color-dot black"></span>
                            <span class="color-name">Black</span>
                        </div>
                        <div class="color-option" data-color="blue">
                            <span class="color-dot blue"></span>
                            <span class="color-name">Blue</span>
                        </div>
                        <?php if (!empty($product['discount'])): ?>
                        <div class="more-colors">
                            <span><?php echo $product['discount']; ?> more colors</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Size Guide -->
                <div class="size-guide">
                    <h3>Size Information</h3>
                    <div class="size-info">
                        <div class="size-item">
                            <span class="size-label">Frame Width:</span>
                            <span class="size-value">140mm</span>
                        </div>
                        <div class="size-item">
                            <span class="size-label">Lens Width:</span>
                            <span class="size-value">52mm</span>
                        </div>
                        <div class="size-item">
                            <span class="size-label">Bridge:</span>
                            <span class="size-value">18mm</span>
                        </div>
                        <div class="size-item">
                            <span class="size-label">Temple:</span>
                            <span class="size-value">145mm</span>
                        </div>
                    </div>
                    <button class="size-guide-btn" onclick="openSizeGuide()">
                        <i class="fas fa-ruler"></i>
                        Size Guide
                    </button>
                </div>

                <!-- Lens Options -->
                <div class="lens-options">
                    <h3>Lens Options</h3>
                    <div class="lens-grid">
                        <div class="lens-option active" data-lens="zero-power">
                            <div class="lens-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="lens-info">
                                <h4>Zero Power</h4>
                                <p>Blue Light Protection</p>
                                <span class="lens-price">FREE</span>
                            </div>
                        </div>
                        <div class="lens-option" data-lens="single-vision">
                            <div class="lens-icon">
                                <i class="fas fa-glasses"></i>
                            </div>
                            <div class="lens-info">
                                <h4>Single Vision</h4>
                                <p>Distance or Reading</p>
                                <span class="lens-price">+₹1,000</span>
                            </div>
                        </div>
                        <div class="lens-option" data-lens="progressive">
                            <div class="lens-icon">
                                <i class="fas fa-low-vision"></i>
                            </div>
                            <div class="lens-info">
                                <h4>Progressive</h4>
                                <p>Distance + Reading</p>
                                <span class="lens-price">+₹3,000</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="product-actions">
                    <button class="btn-primary add-to-cart" onclick="addToCart('<?php echo $product['id']; ?>', event)">
                        <i class="fas fa-shopping-cart"></i>
                        Add to Cart
                    </button>
                    <button class="btn-secondary try-on" onclick="openTryOn()">
                        <i class="fas fa-camera"></i>
                        3D Try On
                    </button>
                    <button class="btn-outline wishlist" onclick="toggleWishlist('<?php echo $product['id']; ?>')">
                        <i class="far fa-heart"></i>
                        Wishlist
                    </button>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <button class="quick-action" onclick="shareProduct()" title="Share Product">
                        <i class="fas fa-share-alt"></i>
                        <span>Share</span>
                    </button>
                    <button class="quick-action" onclick="compareProduct()" title="Add to Compare">
                        <i class="fas fa-balance-scale"></i>
                        <span>Compare</span>
                    </button>
                    <button class="quick-action" onclick="askQuestion()" title="Ask a Question">
                        <i class="fas fa-question-circle"></i>
                        <span>Ask</span>
                    </button>
                </div>

                <!-- Offers -->
                <div class="offers-section">
                    <h3>Special Offers</h3>
                    <div class="offer-item">
                        <i class="fas fa-gift"></i>
                        <span>Get FREE BLU Screen Lenses worth ₹1,500</span>
                    </div>
                    <div class="offer-item">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Free Home Trial - Try 5 frames for free</span>
                    </div>
                    <div class="offer-item">
                        <i class="fas fa-undo"></i>
                        <span>15-day hassle-free returns</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Tabs -->
        <div class="product-tabs">
            <div class="tab-headers">
                <button class="tab-header active" data-tab="description">Description</button>
                <button class="tab-header" data-tab="specifications">Specifications</button>
                <button class="tab-header" data-tab="reviews">Reviews</button>
                <button class="tab-header" data-tab="shipping">Shipping & Returns</button>
            </div>
            
            <div class="tab-content">
                <div class="tab-pane active" id="description">
                    <h3>Product Description</h3>
                    <p>Experience premium eyewear with <?php echo htmlspecialchars($product['brand']); ?>. Crafted with precision and designed for comfort, these glasses offer the perfect blend of style and functionality.</p>
                    <ul>
                        <li>Premium quality materials</li>
                        <li>Lightweight and comfortable design</li>
                        <li>Durable construction</li>
                        <li>Stylish and modern look</li>
                        <li>Suitable for daily wear</li>
                    </ul>
                </div>
                
                <div class="tab-pane" id="specifications">
                    <h3>Specifications</h3>
                    <table class="specs-table">
                        <tr>
                            <td>Brand</td>
                            <td><?php echo htmlspecialchars($product['brand']); ?></td>
                        </tr>
                        <tr>
                            <td>Frame Type</td>
                            <td><?php echo htmlspecialchars($product['frame'] ?? 'Full Rim'); ?></td>
                        </tr>
                        <tr>
                            <td>Size</td>
                            <td><?php echo htmlspecialchars($product['sizeCollection'] ?? 'Medium'); ?></td>
                        </tr>
                        <tr>
                            <td>Material</td>
                            <td>Premium Acetate</td>
                        </tr>
                        <tr>
                            <td>Weight</td>
                            <td>18g (approx.)</td>
                        </tr>
                        <tr>
                            <td>Warranty</td>
                            <td>1 Year Manufacturing Warranty</td>
                        </tr>
                    </table>
                </div>
                
                <div class="tab-pane" id="reviews">
                    <h3>Customer Reviews</h3>
                    <?php if (isset($product['rating']) && $product['rating']): ?>
                    <div class="reviews-summary">
                        <div class="rating-overview">
                            <span class="avg-rating"><?php echo $product['rating']; ?></span>
                            <div class="rating-stars">
                                <?php 
                                $rating = floatval($product['rating']);
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <i class="fas fa-star <?php echo $i <= $rating ? 'filled' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="total-reviews"><?php echo $product['reviews']; ?> reviews</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="sample-reviews">
                        <div class="review-item">
                            <div class="reviewer-info">
                                <strong>Rahul S.</strong>
                                <div class="review-rating">
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star filled"></i>
                                </div>
                            </div>
                            <p>"Excellent quality and very comfortable. The design is modern and stylish."</p>
                        </div>
                        
                        <div class="review-item">
                            <div class="reviewer-info">
                                <strong>Priya M.</strong>
                                <div class="review-rating">
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star filled"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                            <p>"Great value for money. Fast delivery and good packaging."</p>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane" id="shipping">
                    <h3>Shipping & Returns</h3>
                    <div class="shipping-info">
                        <div class="info-section">
                            <h4><i class="fas fa-shipping-fast"></i> Shipping Information</h4>
                            <ul>
                                <li>Free shipping on all orders</li>
                                <li>Standard delivery: 3-5 business days</li>
                                <li>Express delivery: 1-2 business days (₹99)</li>
                                <li>Same day delivery available in select cities</li>
                            </ul>
                        </div>
                        
                        <div class="info-section">
                            <h4><i class="fas fa-undo"></i> Returns & Exchange</h4>
                            <ul>
                                <li>15-day hassle-free returns</li>
                                <li>Free return pickup</li>
                                <li>Exchange available for size/color</li>
                                <li>Refund processed within 7-10 business days</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="related-products">
            <h2>You May Also Like</h2>
            <div class="related-grid">
                <?php foreach ($relatedProducts as $relatedProduct): ?>
                <div class="related-card">
                    <a href="product-details.php?id=<?php echo $relatedProduct['id']; ?>">
                        <img src="<?php echo htmlspecialchars($relatedProduct['imageUrl']); ?>" 
                             alt="<?php echo htmlspecialchars($relatedProduct['brand']); ?>">
                        <h4><?php echo htmlspecialchars($relatedProduct['brand']); ?></h4>
                        <p class="related-price">₹<?php echo number_format($relatedProduct['price']); ?></p>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="" alt="Product Image">
            <div class="modal-controls">
                <button class="modal-btn" onclick="zoomIn()">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="modal-btn" onclick="zoomOut()">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="modal-btn" onclick="resetZoom()">
                    <i class="fas fa-expand-arrows-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Size Guide Modal -->
    <div id="sizeGuideModal" class="size-guide-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeSizeGuide()">&times;</span>
            <h2>Frame Size Guide</h2>
            <div class="size-guide-content">
                <img src="https://static5.lenskart.com/media/uploads/size-guide.jpg" alt="Size Guide" class="size-guide-image">
                <div class="size-measurements">
                    <h3>How to Measure</h3>
                    <div class="measurement-item">
                        <strong>Frame Width (A):</strong> Total width of the frame
                    </div>
                    <div class="measurement-item">
                        <strong>Lens Width (B):</strong> Width of each lens
                    </div>
                    <div class="measurement-item">
                        <strong>Bridge (C):</strong> Distance between lenses
                    </div>
                    <div class="measurement-item">
                        <strong>Temple Length (D):</strong> Length of the temple arm
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Try On Modal -->
    <div id="tryOnModal" class="try-on-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeTryOn()">&times;</span>
            <h2>3D Virtual Try-On</h2>
            <div class="try-on-content">
                <div class="camera-container">
                    <video id="cameraFeed" autoplay muted></video>
                    <canvas id="tryOnCanvas"></canvas>
                </div>
                <div class="try-on-controls">
                    <button class="try-on-btn" onclick="startCamera()">
                        <i class="fas fa-camera"></i>
                        Start Camera
                    </button>
                    <button class="try-on-btn" onclick="capturePhoto()">
                        <i class="fas fa-camera-retro"></i>
                        Capture
                    </button>
                    <button class="try-on-btn" onclick="switchCamera()">
                        <i class="fas fa-sync-alt"></i>
                        Switch Camera
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Image functionality
        function changeMainImage(src) {
            document.getElementById('mainImage').src = src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function openImageModal() {
            const mainImage = document.getElementById('mainImage');
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            modalImage.src = mainImage.src;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Tab functionality
        document.querySelectorAll('.tab-header').forEach(header => {
            header.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                // Remove active class from all headers and panes
                document.querySelectorAll('.tab-header').forEach(h => h.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                // Add active class to clicked header and corresponding pane
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Color selection with image change
        const colorImages = {
            'black': '<?php echo htmlspecialchars($product['imageUrl']); ?>',
            'blue': '<?php echo isset($product['imageUrl2']) ? htmlspecialchars($product['imageUrl2']) : htmlspecialchars($product['imageUrl']); ?>',
            'gray': '<?php echo htmlspecialchars($product['imageUrl']); ?>',
            'brown': '<?php echo htmlspecialchars($product['imageUrl']); ?>'
        };
        
        function changeProductColor(color) {
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector(`[data-color="${color}"]`).classList.add('active');
            
            // Change main image if color variant exists
            if (colorImages[color]) {
                document.getElementById('mainImage').src = colorImages[color];
            }
        }
        
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                const color = this.dataset.color;
                changeProductColor(color);
            });
        });

        // Lens selection with price update
        document.querySelectorAll('.lens-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.lens-option').forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                
                const lensType = this.dataset.lens;
                const basePrice = <?php echo $product['price']; ?>;
                let finalPrice = basePrice;
                
                if (lensType === 'single-vision') {
                    finalPrice += 1000;
                } else if (lensType === 'progressive') {
                    finalPrice += 3000;
                }
                
                document.querySelector('.current-price').textContent = '₹' + finalPrice.toLocaleString();
                showNotification(`Selected ${lensType.replace('-', ' ')} lens`, 'success');
            });
        });

        // Add to cart functionality
        async function addToCart(productId, event) {
            const button = event.target.closest('.add-to-cart');
            if (!button || button.disabled) return;

            const originalHTML = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

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
                        if (result.action === 'redirect_login') {
                            showNotification('ⓘ ' + result.message, 'info');
                            setTimeout(() => {
                                window.location.href = '../auth/signin.php';
                            }, 2000);
                            return;
                        }
                        
                        if (result.action === 'admin_restricted') {
                            showNotification('⚠️ ' + result.message, 'warning');
                            return;
                        }

                        throw new Error(result.message || 'Failed to add item to cart.');
                    }

                    button.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
                    
                    // Trigger custom event for header to update cart count
                    window.dispatchEvent(new CustomEvent('cartUpdated', {
                        detail: { count: result.cart_count || 0 }
                    }));
                    
                    showNotification('✓ ' + (result.message || 'Added to cart'), 'success');

                } catch (jsonError) {
                    console.error("Server responded with an error instead of JSON.");
                    console.log(responseText);
                    throw new Error('Server returned an invalid response.');
                }

            } catch (err) {
                console.error('addToCart error:', err.message);
                button.innerHTML = '<i class="fas fa-times"></i> Failed!';
                if (!err.message.includes('log in')) {
                    showNotification('✗ ' + err.message, 'error');
                }
            } finally {
                const shouldReset = !document.querySelector('.notification-info');
                if (shouldReset) {
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }, 2000);
                }
            }
        }

        // Notification function
        function showNotification(message, type = 'info') {
            document.querySelectorAll('.custom-notification').forEach(el => el.remove());
            const notification = document.createElement('div');
            notification.className = `custom-notification notification-${type}`;
            let bgColor = '#6c757d';
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

        // Inject notification styles
        (function injectNotifStyles(){
            if (document.getElementById('notif-animations')) return;
            const s = document.createElement('style');
            s.id = 'notif-animations';
            s.textContent = `@keyframes slideInRight{from{transform:translateX(100%)}to{transform:translateX(0)}}@keyframes slideOutRight{from{transform:translateX(0)}to{transform:translateX(100%)}}`;
            document.head.appendChild(s);
        })();

        // Enhanced modal functionality
        let zoomLevel = 1;
        let currentCamera = 'user';
        let stream = null;

        function zoomIn() {
            zoomLevel = Math.min(zoomLevel + 0.2, 3);
            document.getElementById('modalImage').style.transform = `scale(${zoomLevel})`;
        }

        function zoomOut() {
            zoomLevel = Math.max(zoomLevel - 0.2, 0.5);
            document.getElementById('modalImage').style.transform = `scale(${zoomLevel})`;
        }

        function resetZoom() {
            zoomLevel = 1;
            document.getElementById('modalImage').style.transform = 'scale(1)';
        }

        function openSizeGuide() {
            document.getElementById('sizeGuideModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeSizeGuide() {
            document.getElementById('sizeGuideModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openTryOn() {
            if (!navigator.mediaDevices) {
                showNotification('Camera not supported', 'error');
                return;
            }
            document.getElementById('tryOnModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            showNotification('Try-On activated!', 'info');
        }

        function closeTryOn() {
            document.getElementById('tryOnModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        }

        async function startCamera() {
            try {
                const video = document.getElementById('cameraFeed');
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: currentCamera }
                });
                video.srcObject = stream;
            } catch (err) {
                showNotification('Camera access denied or not available', 'error');
            }
        }

        function switchCamera() {
            currentCamera = currentCamera === 'user' ? 'environment' : 'user';
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                startCamera();
            }
        }

        function capturePhoto() {
            const video = document.getElementById('cameraFeed');
            const canvas = document.getElementById('tryOnCanvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0);
            
            // Here you would add AR overlay logic
            showNotification('Photo captured! AR overlay coming soon.', 'info');
        }

        function shareProduct() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($product['brand']); ?>',
                    text: 'Check out these amazing eyeglasses!',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href);
                showNotification('Product link copied to clipboard!', 'success');
            }
        }

        function compareProduct() {
            // Add to comparison list (localStorage)
            let compareList = JSON.parse(localStorage.getItem('compareList') || '[]');
            const productId = '<?php echo $product['id']; ?>';
            
            if (!compareList.includes(productId)) {
                compareList.push(productId);
                localStorage.setItem('compareList', JSON.stringify(compareList));
                showNotification('Added to comparison list!', 'success');
            } else {
                showNotification('Already in comparison list!', 'info');
            }
        }

        function askQuestion() {
            // Open a simple question modal or redirect to contact
            const question = prompt('What would you like to know about this product?');
            if (question) {
                showNotification('Thank you! We\'ll get back to you soon.', 'success');
                // Here you would send the question to your backend
            }
        }

        function toggleWishlist(productId) {
            // Toggle wishlist (localStorage for demo)
            let wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
            const button = document.querySelector('.wishlist');
            const icon = button.querySelector('i');
            
            if (wishlist.includes(productId)) {
                wishlist = wishlist.filter(id => id !== productId);
                icon.className = 'far fa-heart';
                showNotification('Removed from wishlist', 'info');
            } else {
                wishlist.push(productId);
                icon.className = 'fas fa-heart';
                showNotification('Added to wishlist!', 'success');
            }
            
            localStorage.setItem('wishlist', JSON.stringify(wishlist));
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const imageModal = document.getElementById('imageModal');
            const sizeModal = document.getElementById('sizeGuideModal');
            const tryOnModal = document.getElementById('tryOnModal');
            
            if (event.target === imageModal) {
                closeImageModal();
            } else if (event.target === sizeModal) {
                closeSizeGuide();
            } else if (event.target === tryOnModal) {
                closeTryOn();
            }
        }

        // Initialize wishlist state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
            const productId = '<?php echo $product['id']; ?>';
            
            if (wishlist.includes(productId)) {
                document.querySelector('.wishlist i').className = 'fas fa-heart';
            }
        });

        // Enhanced page interactions
        window.addEventListener('load', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Add intersection observer for animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });
            
            // Observe elements for animation
            document.querySelectorAll('.related-card, .feature-item, .offer-item').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'all 0.6s ease';
                observer.observe(el);
            });
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
                closeSizeGuide();
                closeTryOn();
            }
        });
    </script>

    <!-- Additional CSS for new features -->
    <style>
        .color-name {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            text-align: center;
        }
        
        .size-guide {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .size-guide h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .size-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .size-item {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }
        
        .size-label {
            color: #666;
        }
        
        .size-value {
            font-weight: 600;
            color: #333;
        }
        
        .size-guide-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .size-guide-btn:hover {
            background: #0056b3;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 12px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            font-size: 12px;
            color: #666;
        }
        
        .quick-action:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .quick-action i {
            font-size: 16px;
            color: #007bff;
        }
        
        .modal-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }
        
        .modal-btn {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        
        .size-guide-modal,
        .try-on-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }
        
        .size-guide-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .size-guide-image {
            width: 100%;
            border-radius: 8px;
        }
        
        .measurement-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .try-on-content {
            text-align: center;
        }
        
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto 20px;
        }
        
        #cameraFeed {
            width: 100%;
            border-radius: 8px;
        }
        
        #tryOnCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .try-on-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .try-on-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .try-on-btn:hover {
            background: #0056b3;
        }
        
        @media (max-width: 768px) {
            .size-guide-content {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-wrap: wrap;
            }
            
            .quick-action {
                min-width: 80px;
            }
        }
    </style>
</body>
</html>