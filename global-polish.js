/**
 * Professional Loading Screen & Page Transitions
 * Add smooth loading animations to all pages
 */

// Create loading overlay HTML
const loadingHTML = `
<div id="globalLoadingScreen" class="global-loading-screen">
    <div class="loading-content">
        <div class="pharmacy-logo-loader">
            <div class="pill-loader">
                <div class="pill-half pill-left"></div>
                <div class="pill-half pill-right"></div>
            </div>
        </div>
        <h2 class="loading-text">Calloway Pharmacy</h2>
        <div class="loading-bar">
            <div class="loading-bar-fill"></div>
        </div>
        <p class="loading-message">Loading your healthcare solutions...</p>
    </div>
</div>

<style>
.global-loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    transition: opacity 0.5s ease, visibility 0.5s ease;
    pointer-events: none;
}

.global-loading-screen.loaded {
    opacity: 0;
    visibility: hidden;
}

.loading-content {
    text-align: center;
    color: white;
}

.pharmacy-logo-loader {
    margin-bottom: 2rem;
}

.pill-loader {
    width: 100px;
    height: 50px;
    margin: 0 auto;
    position: relative;
    animation: pulse 2s ease-in-out infinite;
}

.pill-half {
    width: 50px;
    height: 50px;
    border-radius: 50px 0 0 50px;
    position: absolute;
    animation: rotate 2s linear infinite;
}

.pill-left {
    left: 0;
    background: linear-gradient(135deg, #ffffff 0%, #e0e0e0 100%);
    transform-origin: right center;
}

.pill-right {
    right: 0;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    border-radius: 0 50px 50px 0;
    transform-origin: left center;
    animation-delay: 1s;
}

@keyframes rotate {
    0%, 100% {
        transform: rotate(0deg);
    }
    50% {
        transform: rotate(180deg);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

.loading-text {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
    letter-spacing: 2px;
    animation: fadeInOut 2s ease-in-out infinite;
}

@keyframes fadeInOut {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

.loading-bar {
    width: 300px;
    height: 4px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    margin: 0 auto 1rem;
    overflow: hidden;
}

.loading-bar-fill {
    height: 100%;
    background: white;
    border-radius: 10px;
    animation: loadingProgress 2s ease-in-out infinite;
}

@keyframes loadingProgress {
    0% {
        width: 0%;
        margin-left: 0%;
    }
    50% {
        width: 70%;
        margin-left: 15%;
    }
    100% {
        width: 0%;
        margin-left: 100%;
    }
}

.loading-message {
    font-size: 1rem;
    opacity: 0.9;
    animation: fadeInOut 2s ease-in-out infinite;
    animation-delay: 0.5s;
}
</style>
`;

// Initialize loading screen
function initLoadingScreen() {
    // Add loading HTML to body
    document.body.insertAdjacentHTML('afterbegin', loadingHTML);
    
    // Remove loading screen when page is fully loaded
    window.addEventListener('load', function() {
        setTimeout(() => {
            const loadingScreen = document.getElementById('globalLoadingScreen');
            if (loadingScreen) {
                loadingScreen.classList.add('loaded');
                setTimeout(() => {
                    loadingScreen.remove();
                }, 600);
            }
        }, 300);
    });
    
    // Safety fallback: remove after 5 seconds no matter what
    setTimeout(() => {
        const loadingScreen = document.getElementById('globalLoadingScreen');
        if (loadingScreen) {
            loadingScreen.remove();
        }
    }, 5000);
}

// Add smooth page transitions
function addPageTransitions() {
    // Fade in content
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease';
        document.body.style.opacity = '1';
    }, 100);
    
    // Handle all internal links
    document.querySelectorAll('a[href]:not([target="_blank"])').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Skip if it's an external link, anchor, or javascript
            if (href.startsWith('http') || href.startsWith('#') || href.startsWith('javascript')) {
                return;
            }
            
            e.preventDefault();
            
            // Fade out
            document.body.style.opacity = '0';
            
            setTimeout(() => {
                window.location.href = href;
            }, 300);
        });
    });
}

// Add scroll animations
function addScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe all animatable elements
    document.querySelectorAll('.stat-card, .chart-container, table tr').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
    
    // Add class when in view
    const style = document.createElement('style');
    style.textContent = `
        .animate-in {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
    `;
    document.head.appendChild(style);
}

// Add keyboard shortcuts indicator
function addKeyboardShortcuts() {
    const shortcuts = {
        'Ctrl+/': 'Show shortcuts',
        'Ctrl+K': 'Search',
        'Esc': 'Close modals',
        'F5': 'Refresh'
    };
    
    // Show shortcuts on Ctrl + /
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            showShortcutsModal();
        }
    });
    
    function showShortcutsModal() {
        const modal = document.createElement('div');
        modal.className = 'shortcuts-modal';
        modal.innerHTML = `
            <div class="shortcuts-content">
                <h3>⌨️ Keyboard Shortcuts</h3>
                <div class="shortcuts-list">
                    ${Object.entries(shortcuts).map(([key, desc]) => `
                        <div class="shortcut-item">
                            <kbd>${key}</kbd>
                            <span>${desc}</span>
                        </div>
                    `).join('')}
                </div>
                <button onclick="this.closest('.shortcuts-modal').remove()">Close</button>
            </div>
        `;
        
        const style = document.createElement('style');
        style.textContent = `
            .shortcuts-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            }
            
            .shortcuts-content {
                background: white;
                padding: 2rem;
                border-radius: 16px;
                max-width: 500px;
                width: 90%;
            }
            
            .shortcuts-content h3 {
                margin: 0 0 1.5rem 0;
                font-size: 1.5rem;
            }
            
            .shortcuts-list {
                margin-bottom: 1.5rem;
            }
            
            .shortcut-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.75rem;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .shortcut-item kbd {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 8px;
                font-weight: 600;
            }
            
            .shortcuts-content button {
                width: 100%;
                padding: 1rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 12px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .shortcuts-content button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            }
        `;
        
        document.body.appendChild(modal);
        document.head.appendChild(style);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
}

// Initialize all enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Loading screen and page transitions disabled — they cause
    // a jarring "Loading..." overlay when switching between pages.
    // initLoadingScreen();
    // addPageTransitions();
    setTimeout(addScrollAnimations, 500);
    addKeyboardShortcuts();
});
