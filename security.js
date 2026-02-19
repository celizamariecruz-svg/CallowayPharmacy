/**
 * Security Initialization Script
 * Auto-loads CSRF protection and security features on all pages
 */

// Initialize security on page load
document.addEventListener('DOMContentLoaded', function() {
    // Get CSRF token from meta tag
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';
    
    // CSRF Protection Object
    window.CSRF = {
        token: csrfToken,
        
        getToken: function() {
            return this.token;
        },
        
        // Add to fetch() requests
        getHeaders: function() {
            return {
                'X-CSRF-Token': this.token,
                'Content-Type': 'application/json'
            };
        },
        
        // For FormData submissions
        addToFormData: function(formData) {
            formData.append('csrf_token', this.token);
            return formData;
        }
    };
    
    // Auto-add CSRF token to all forms
    document.querySelectorAll('form').forEach(function(form) {
        // Skip if form already has CSRF token
        if (form.querySelector('input[name="csrf_token"]')) {
            return;
        }
        
        // Add CSRF token to form
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = csrfToken;
        form.appendChild(input);
    });
    
    // Intercept all fetch requests to add CSRF token
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        // Only add CSRF to POST, PUT, DELETE, PATCH requests to same origin
        const method = (options.method || 'GET').toUpperCase();
        const needsCSRF = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method);
        const isSameOrigin = url.indexOf(window.location.origin) === 0 || url.charAt(0) === '/';
        
        if (needsCSRF && isSameOrigin) {
            options.headers = options.headers || {};
            
            // Add CSRF token if not already present
            if (!options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) {
                options.headers['X-CSRF-Token'] = csrfToken;
            }
        }
        
        return originalFetch(url, options);
    };
    
    // Session timeout warning
    let sessionWarningShown = false;
    const SESSION_TIMEOUT = 30 * 60 * 1000; // 30 minutes
    const WARNING_TIME = 5 * 60 * 1000; // 5 minutes before timeout
    
    // Reset activity timer on user interaction
    let lastActivity = Date.now();
    
    function resetActivity() {
        lastActivity = Date.now();
        sessionWarningShown = false;
    }
    
    // Track user activity
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetActivity, true);
    });
    
    // Check session timeout
    setInterval(function() {
        const timeSinceActivity = Date.now() - lastActivity;
        const timeUntilTimeout = SESSION_TIMEOUT - timeSinceActivity;
        
        // Show warning 5 minutes before timeout
        if (timeUntilTimeout <= WARNING_TIME && !sessionWarningShown) {
            sessionWarningShown = true;
            const minutes = Math.ceil(timeUntilTimeout / 60000);
            
            if (window.PolishUI && window.PolishUI.toast) {
                window.PolishUI.toast.warning(
                    `Your session will expire in ${minutes} minutes. Please save your work.`,
                    'Session Timeout Warning'
                );
            } else if (typeof window.customAlert === 'function') {
                window.customAlert('Session Timeout Warning', `Your session will expire in ${minutes} minutes due to inactivity.`, 'warning');
            } else {
                console.warn(`Session timeout warning: ${minutes} minutes remaining.`);
            }
        }
        
        // Redirect to login on timeout
        if (timeSinceActivity >= SESSION_TIMEOUT) {
            window.location.href = 'login.php?timeout=1';
        }
    }, 60000); // Check every minute
    
    // Confirmation dialogs for destructive actions
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', async function(e) {
            if (this.dataset.confirmApproved === '1') {
                this.dataset.confirmApproved = '0';
                return;
            }

            const message = this.getAttribute('data-confirm');
            e.preventDefault();
            e.stopPropagation();

            const ok = (typeof window.customConfirm === 'function')
                ? await window.customConfirm('Confirm Action', message, 'warning', { confirmText: 'Continue', cancelText: 'Cancel' })
                : true;

            if (!ok) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            this.dataset.confirmApproved = '1';
            this.click();
        });
    });
    
    // Sanitize user inputs on form submit
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            // Trim all text inputs
            this.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(function(input) {
                input.value = input.value.trim();
            });
        });
    });
    
    // Check for session timeout message
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('timeout') === '1') {
        if (window.PolishUI && window.PolishUI.toast) {
            window.PolishUI.toast.warning('Your session has expired due to inactivity. Please login again.');
        } else if (typeof window.customAlert === 'function') {
            window.customAlert('Session Expired', 'Your session has expired. Please login again.', 'warning');
        } else {
            console.warn('Session expired due to inactivity.');
        }
    }
});

// Security utilities
window.Security = {
    // Sanitize HTML to prevent XSS
    sanitizeHTML: function(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    },
    
    // Escape HTML entities
    escapeHTML: function(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },
    
    // Validate email
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    // Validate Philippine phone number
    validatePhone: function(phone) {
        const cleaned = phone.replace(/[\s\-\(\)]/g, '');
        const re = /^(09|\+639)\d{9}$/;
        return re.test(cleaned);
    },
    
    // Check password strength
    checkPasswordStrength: function(password) {
        const strength = {
            score: 0,
            feedback: []
        };
        
        if (password.length >= 8) strength.score++;
        else strength.feedback.push('Use at least 8 characters');
        
        if (/[a-z]/.test(password)) strength.score++;
        else strength.feedback.push('Include lowercase letters');
        
        if (/[A-Z]/.test(password)) strength.score++;
        else strength.feedback.push('Include uppercase letters');
        
        if (/[0-9]/.test(password)) strength.score++;
        else strength.feedback.push('Include numbers');
        
        if (/[^A-Za-z0-9]/.test(password)) strength.score++;
        else strength.feedback.push('Include special characters');
        
        const levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        strength.level = levels[Math.min(strength.score, levels.length - 1)];
        
        return strength;
    }
};
