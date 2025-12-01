// Smooth Performance Enhancements
(function() {
    'use strict';
    
    // Debounce function for performance
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Smooth scroll polyfill
    function smoothScrollTo(element, to, duration) {
        const start = element.scrollTop;
        const change = to - start;
        const startTime = performance.now();
        
        function animateScroll(currentTime) {
            const timeElapsed = currentTime - startTime;
            const progress = Math.min(timeElapsed / duration, 1);
            const easeInOutQuad = progress < 0.5 
                ? 2 * progress * progress 
                : -1 + (4 - 2 * progress) * progress;
            
            element.scrollTop = start + change * easeInOutQuad;
            
            if (timeElapsed < duration) {
                requestAnimationFrame(animateScroll);
            }
        }
        
        requestAnimationFrame(animateScroll);
    }
    
    // Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            .animate-in {
                animation: slideInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            }
            
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .card {
                opacity: 0;
                transform: translateY(30px);
                transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .preload * {
                transition: none !important;
            }
        `;
        document.head.appendChild(style);
        
        // Prevent transitions during page load
        document.body.classList.add('preload');
        setTimeout(() => {
            document.body.classList.remove('preload');
        }, 100);
        
        // Observe cards for animation
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => observer.observe(card));
        
        // Smooth filter changes
        const filterInputs = document.querySelectorAll('select, input[type="radio"]');
        filterInputs.forEach(input => {
            input.addEventListener('change', debounce(() => {
                // Add loading state
                const container = document.querySelector('.EyeGlassesSection-Right');
                if (container) {
                    container.style.opacity = '0.7';
                    container.style.pointerEvents = 'none';
                }
            }, 100));
        });
        
        // Smooth scroll to top on page change
        const paginationLinks = document.querySelectorAll('.pagination-button');
        paginationLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                if (!link.disabled) {
                    smoothScrollTo(document.documentElement, 0, 500);
                }
            });
        });
        
        // Optimize images
        const images = document.querySelectorAll('img[loading="lazy"]');
        images.forEach(img => {
            img.addEventListener('load', () => {
                img.style.opacity = '1';
            });
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';
        });
        
        // Add ripple effect to buttons
        const buttons = document.querySelectorAll('.add-to-cart-btn, .pagination-button');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s ease-out;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Add ripple animation
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(rippleStyle);
    });
    
    // Performance monitoring
    window.addEventListener('load', () => {
        // Log performance metrics
        if ('performance' in window) {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log('Page Load Time:', perfData.loadEventEnd - perfData.loadEventStart, 'ms');
        }
    });
})();