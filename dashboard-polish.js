/**
 * Dashboard Polish Enhancement
 * Add animations, charts, and professional touches
 */

// Add this script to dashboard.php
function enhanceDashboard() {
    // Animate numbers counting up
    function animateValue(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    }
    
    // Animate all stat numbers on page load
    document.querySelectorAll('.stat-number').forEach(element => {
        const finalValue = parseInt(element.textContent.replace(/,/g, ''));
        element.textContent = '0';
        setTimeout(() => {
            animateValue(element, 0, finalValue, 1500);
        }, 300);
    });
    
    // Add hover effects to cards
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate-in');
    });
    
    // Add shimmer effect to loading states
    function addShimmer(element) {
        element.classList.add('skeleton');
        setTimeout(() => {
            element.classList.remove('skeleton');
        }, 1500);
    }
    
    // Stock level visualization
    function updateStockLevels() {
        document.querySelectorAll('[data-stock-level]').forEach(element => {
            const level = parseFloat(element.dataset.stockLevel);
            const bar = element.querySelector('.stock-bar-fill');
            
            if (bar) {
                setTimeout(() => {
                    bar.style.width = level + '%';
                    
                    if (level >= 70) {
                        bar.classList.add('stock-high');
                    } else if (level >= 40) {
                        bar.classList.add('stock-medium');
                    } else if (level >= 20) {
                        bar.classList.add('stock-low');
                    } else {
                        bar.classList.add('stock-critical');
                    }
                }, 500);
            }
        });
    }
    
    // Initialize
    updateStockLevels();
    
    // Add refresh animation
    document.querySelectorAll('[data-refresh]').forEach(btn => {
        btn.addEventListener('click', function() {
            this.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                this.style.transform = 'rotate(0deg)';
            }, 600);
        });
    });
    
    // Auto-refresh data every 30 seconds
    setInterval(() => {
        // Reload dashboard data silently
        if (typeof loadDashboardData === 'function') {
            loadDashboardData();
        }
    }, 30000);
}

// Run on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceDashboard);
} else {
    enhanceDashboard();
}
