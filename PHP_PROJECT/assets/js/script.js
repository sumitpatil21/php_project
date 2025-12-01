
// DOM Content Loaded
document.addEventListener("DOMContentLoaded", () => {
    // Check authentication status
    checkAuthStatus();

    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById("mobileMenuBtn");
    const mainNavigation = document.querySelector(".main-navigation");

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener("click", function () {
            mainNavigation.classList.toggle("active");
            this.classList.toggle("active");
        });
    }

    // Sign In Modal
    const signInBtn = document.getElementById("signInBtn");
    const signInModal = document.getElementById("signInModal");
    const closeModal = document.querySelector(".close");

    if (signInBtn && signInModal) {
        signInBtn.addEventListener("click", (e) => {
            e.preventDefault();
            signInModal.style.display = "block";
        });
    }

    if (closeModal) {
        closeModal.addEventListener("click", () => {
            signInModal.style.display = "none";
        });
    }

    // Sign Up Modal
    const signUpBtn = document.getElementById("signUpBtn");
    const signUpModal = document.getElementById("signUpModal");
    const closeSignUp = document.getElementById("closeSignUp");

    if (signUpBtn && signUpModal) {
        signUpBtn.addEventListener("click", (e) => {
            e.preventDefault();
            signUpModal.style.display = "block";
        });
    }

    if (closeSignUp) {
        closeSignUp.addEventListener("click", () => {
            signUpModal.style.display = "none";
        });
    }

    // Close modals when clicking outside
    window.addEventListener("click", (e) => {
        if (e.target === signInModal) {
            signInModal.style.display = "none";
        }
        if (e.target === signUpModal) {
            signUpModal.style.display = "none";
        }
    });

    // Search Functionality
    const searchInput = document.getElementById("searchInput");
    const searchForm = document.querySelector(".search-form");

    if (searchForm) {
        searchForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const searchTerm = searchInput.value.trim();
            if (searchTerm) {
                // Redirect to search results page
                window.location.href = `/PHP_PROJECT/pages/eyeglasses.php?search=${encodeURIComponent(searchTerm)}`;
            }
        });
    }

    // Enhanced addToCart function with proper file paths
    async function addToCart(productId, event) {
        const button = document.querySelector(`.add-to-cart-btn[data-product-id="${productId}"]`);
        if (!button) return;
        
        const btnContent = button.querySelector('.btn-content');
        const btnLoading = button.querySelector('.btn-loading');
        const btnSuccess = button.querySelector('.btn-success');
        const quickControls = button.closest('.card-actions').querySelector('.quick-controls');
        
        // Create ripple effect
        createRipple(button, event);
        
        // Show loading state
        button.classList.add('loading');
        btnContent.style.display = 'none';
        btnLoading.style.display = 'flex';
        
        try {
            // Try different API paths
            const apiPaths = [
                '../admin/add-to-cart.php',
                '../../admin/add-to-cart.php',
                '/PHP_PROJECT/admin/add-to-cart.php',
                'admin/add-to-cart.php'
            ];
            
            let response;
            for (const path of apiPaths) {
                try {
                    response = await fetch(path, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&quantity=1`
                    });
                    break;
                } catch (e) {
                    console.log(`Path ${path} failed, trying next...`);
                    continue;
                }
            }

            if (!response) {
                throw new Error('All API paths failed');
            }

            const result = await response.json();
            console.log('Add to cart response:', result);

            // Show success state
            button.classList.remove('loading');
            button.classList.add('success');
            btnLoading.style.display = 'none';
            btnSuccess.style.display = 'flex';
            
            if (result.success) {
                showNotification(result.message + ': ' + result.product_name, 'success');
                updateCartCount(result.cart_count);
                
                // Show quick controls after success
                setTimeout(() => {
                    if (quickControls) {
                        quickControls.style.display = 'flex';
                    }
                    btnSuccess.style.display = 'none';
                    btnContent.style.display = 'flex';
                    button.classList.remove('success');
                }, 1500);
                
            } else {
                showNotification('Error: ' + result.message, 'error');
                // Reset button state on error
                setTimeout(() => {
                    btnLoading.style.display = 'none';
                    btnContent.style.display = 'flex';
                    button.classList.remove('loading');
                }, 1000);
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error adding product to cart', 'error');
            // Reset button state on error
            setTimeout(() => {
                btnLoading.style.display = 'none';
                btnContent.style.display = 'flex';
                button.classList.remove('loading');
            }, 1000);
        }
    }

    // Fixed updateCartQuantity function
    async function updateCartQuantity(productId, change) {
        const button = document.querySelector(`.add-to-cart-btn[data-product-id="${productId}"]`);
        if (!button) return;
        
        const quickControls = button.closest('.card-actions').querySelector('.quick-controls');
        const qtyDisplay = quickControls.querySelector('.qty-display');
        let currentQty = parseInt(qtyDisplay.textContent);
        let newQty = currentQty + change;
        
        if (newQty < 1) newQty = 1;
        
        try {
            // Try different API paths
            const apiPaths = [
                '../admin/update-cart.php',
                '../../admin/update-cart.php',
                '/PHP_PROJECT/admin/update-cart.php',
                'admin/update-cart.php'
            ];
            
            let response;
            for (const path of apiPaths) {
                try {
                    response = await fetch(path, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&quantity=${newQty}`
                    });
                    break;
                } catch (e) {
                    console.log(`Path ${path} failed, trying next...`);
                    continue;
                }
            }

            if (!response) {
                throw new Error('All API paths failed');
            }

            const result = await response.json();
            console.log('Update cart response:', result);

            if (result.success) {
                qtyDisplay.textContent = newQty;
                updateCartCount(result.cart_count);
                
                // Visual feedback
                qtyDisplay.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    qtyDisplay.style.transform = 'scale(1)';
                }, 200);
            } else {
                showNotification('Error: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error updating cart quantity', 'error');
        }
    }

    // Fixed updateCartCount function
    function updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            if (count > 0) {
                element.style.display = 'flex';
            } else {
                element.style.display = 'none';
            }
        });
        
        // Also update any other cart count indicators
        const cartIcons = document.querySelectorAll('.cart-icon');
        cartIcons.forEach(icon => {
            const existingCount = icon.querySelector('.cart-count');
            if (existingCount) {
                existingCount.textContent = count;
            } else if (count > 0) {
                const newCount = document.createElement('span');
                newCount.className = 'cart-count';
                newCount.textContent = count;
                icon.appendChild(newCount);
            }
        });
    }

    // Ripple effect function (fixed event parameter)
    function createRipple(button, event) {
        const ripple = document.createElement('span');
        ripple.classList.add('ripple');
        
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        
        button.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Make functions globally available
    window.addToCart = addToCart;
    window.updateCartQuantity = updateCartQuantity;
    window.updateCartCount = updateCartCount;

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener("click", function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute("href"));
            if (target) {
                target.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                });
            }
        });
    });

    // Sticky header effect
    const navbar = document.querySelector(".navbar");
    let lastScrollTop = 0;

    window.addEventListener("scroll", () => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            navbar.style.transform = "translateY(-100%)";
        } else {
            // Scrolling up
            navbar.style.transform = "translateY(0)";
        }

        lastScrollTop = scrollTop;
    });

    // Add loading animation for images
    const images = document.querySelectorAll("img");
    images.forEach((img) => {
        img.addEventListener("load", function () {
            this.style.opacity = "1";
        });

        // Set initial opacity
        img.style.opacity = "0";
        img.style.transition = "opacity 0.3s ease";
    });

    // Form validation
    const forms = document.querySelectorAll("form");
    forms.forEach((form) => {
        form.addEventListener("submit", (e) => {
            const inputs = form.querySelectorAll("input[required]");
            let isValid = true;

            inputs.forEach((input) => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = "#dc3545";
                } else {
                    input.style.borderColor = "#28a745";
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert("Please fill in all required fields.");
            }
        });
    });
});

// Check authentication status
function checkAuthStatus() {
    const authPaths = [
        '../controllers/auth.php?action=check',
        '../../controllers/auth.php?action=check',
        '/PHP_PROJECT/controllers/auth.php?action=check',
        'controllers/auth.php?action=check'
    ];
    
    let fetchPromise;
    for (const path of authPaths) {
        try {
            fetchPromise = fetch(path);
            break;
        } catch (e) {
            continue;
        }
    }
    
    if (fetchPromise) {
        fetchPromise
            .then(response => response.json())
            .then(data => {
                if (data.loggedIn) {
                    console.log('User is logged in:', data.user);
                } else {
                    console.log('User is not logged in');
                }
            })
            .catch(error => {
                console.error('Error checking auth status:', error);
            });
    }
}

// Utility functions
function showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        z-index: 3000;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;

    // Set background color based on type
    switch (type) {
        case "success":
            notification.style.backgroundColor = "#28a745";
            break;
        case "error":
            notification.style.backgroundColor = "#dc3545";
            break;
        case "warning":
            notification.style.backgroundColor = "#ffc107";
            notification.style.color = "#333";
            break;
        default:
            notification.style.backgroundColor = "#007bff";
    }

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.style.opacity = "1";
        notification.style.transform = "translateX(0)";
    }, 100);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = "0";
        notification.style.transform = "translateX(100%)";
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}
