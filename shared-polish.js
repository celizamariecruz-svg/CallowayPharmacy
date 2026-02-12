/**
 * Shared Polish Functions
 * Modern UI enhancements for all features
 */

// Toast Notification System
class ToastNotification {
    constructor() {
        this.createToastContainer();
    }

    createToastContainer() {
        if (!document.getElementById('toast')) {
            const toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast';
            toast.innerHTML = `
                <span class="toast-icon" id="toastIcon"></span>
                <div class="toast-content">
                    <div class="toast-title" id="toastTitle"></div>
                    <div class="toast-message" id="toastMessage"></div>
                </div>
            `;
            document.body.appendChild(toast);
        }
    }

    show(message, type = 'success', title = '') {
        const toast = document.getElementById('toast');
        const toastIcon = document.getElementById('toastIcon');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');

        const icons = {
            success: '✅',
            error: '❌',
            info: 'ℹ️',
            warning: '⚠️'
        };

        const titles = {
            success: title || 'Success',
            error: title || 'Error',
            info: title || 'Info',
            warning: title || 'Warning'
        };

        toastIcon.textContent = icons[type] || icons.info;
        toastTitle.textContent = titles[type];
        toastMessage.textContent = message;
        toast.className = `toast ${type} active`;

        setTimeout(() => {
            toast.classList.remove('active');
        }, 3000);
    }

    success(message, title = '') {
        this.show(message, 'success', title);
    }

    error(message, title = '') {
        this.show(message, 'error', title);
    }

    info(message, title = '') {
        this.show(message, 'info', title);
    }

    warning(message, title = '') {
        this.show(message, 'warning', title);
    }
}

// Initialize Toast
const toast = new ToastNotification();

// Loading Overlay
class LoadingOverlay {
    constructor() {
        this.createOverlay();
    }

    createOverlay() {
        if (!document.getElementById('loadingOverlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div>
                    <div class="loading-spinner"></div>
                    <div class="loading-text" id="loadingText">Loading...</div>
                </div>
            `;
            document.body.appendChild(overlay);
        }
    }

    show(message = 'Loading...') {
        const overlay = document.getElementById('loadingOverlay');
        const loadingText = document.getElementById('loadingText');
        if (loadingText) loadingText.textContent = message;
        overlay.classList.add('active');
    }

    hide() {
        const overlay = document.getElementById('loadingOverlay');
        overlay.classList.remove('active');
    }
}

// Initialize Loading Overlay
const loading = new LoadingOverlay();

// Never let a loading overlay persist during page navigation.
// (Users reported seeing "Loading..." when switching pages.)
window.addEventListener('beforeunload', () => {
    try { loading.hide(); } catch (e) {}
});
window.addEventListener('pageshow', () => {
    try { loading.hide(); } catch (e) {}
});
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        try { loading.hide(); } catch (e) {}
    }
});

// Ripple Effect
function createRipple(event) {
    const button = event.currentTarget;
    const ripple = document.createElement('span');
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;

    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple');

    button.style.position = 'relative';
    button.style.overflow = 'hidden';
    button.appendChild(ripple);

    setTimeout(() => ripple.remove(), 600);
}

// Add ripple to all buttons with class
function initRippleButtons() {
    document.querySelectorAll('.btn-enhanced, .ripple-effect').forEach(button => {
        button.addEventListener('click', createRipple);
    });
}

// Confirm Dialog with style
function confirmAction(message, title = 'Confirm Action') {
    return new Promise((resolve) => {
        const result = confirm(`${title}\n\n${message}`);
        resolve(result);
    });
}

// Enhanced Fetch with loading and error handling
async function fetchWithLoading(url, options = {}, loadingMessage = 'Loading...') {
    loading.show(loadingMessage);
    try {
        const response = await fetch(url, options);
        const data = await response.json();
        loading.hide();
        return data;
    } catch (error) {
        loading.hide();
        toast.error('Network error occurred');
        throw error;
    }
}

// Debounce function for search inputs
function debounce(func, wait = 300) {
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

// Format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Format datetime
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add keyboard shortcut helper
class KeyboardShortcuts {
    constructor() {
        this.shortcuts = new Map();
        this.init();
    }

    init() {
        document.addEventListener('keydown', (e) => {
            const key = this.getKeyString(e);
            const handler = this.shortcuts.get(key);
            if (handler) {
                e.preventDefault();
                handler(e);
            }
        });
    }

    getKeyString(e) {
        const parts = [];
        if (e.ctrlKey) parts.push('Ctrl');
        if (e.altKey) parts.push('Alt');
        if (e.shiftKey) parts.push('Shift');
        parts.push(e.key);
        return parts.join('+');
    }

    register(key, handler, description = '') {
        this.shortcuts.set(key, handler);
    }

    unregister(key) {
        this.shortcuts.delete(key);
    }
}

// Initialize Keyboard Shortcuts
const shortcuts = new KeyboardShortcuts();

// Smooth scroll to element
function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Add animation to element
function animateElement(element, animationClass) {
    element.classList.add(animationClass);
    element.addEventListener('animationend', () => {
        element.classList.remove(animationClass);
    }, { once: true });
}

// Copy to clipboard with feedback
async function copyToClipboard(text, successMessage = 'Copied to clipboard') {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(successMessage);
    } catch (err) {
        toast.error('Failed to copy');
    }
}

// Export to CSV
function exportToCSV(data, filename = 'export.csv') {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
    toast.success('Exported successfully');
}

function convertToCSV(data) {
    if (data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvRows = [];
    
    // Add headers
    csvRows.push(headers.join(','));
    
    // Add data
    for (const row of data) {
        const values = headers.map(header => {
            const value = row[header];
            return `"${String(value).replace(/"/g, '""')}"`;
        });
        csvRows.push(values.join(','));
    }
    
    return csvRows.join('\n');
}

// Print function
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Print</title>');
    printWindow.document.write('<link rel="stylesheet" href="styles.css">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(element.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// Initialize all polished features on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add ripple to buttons
    initRippleButtons();
    
    // Add smooth transitions to all buttons
    document.querySelectorAll('button, .btn').forEach(btn => {
        if (!btn.classList.contains('smooth-transition')) {
            btn.classList.add('smooth-transition');
        }
    });
    
    // Add enhanced class to inputs
    document.querySelectorAll('input, select, textarea').forEach(input => {
        if (!input.classList.contains('input-enhanced')) {
            input.classList.add('input-enhanced');
        }
    });
    
    // Add fade-in animation to cards
    document.querySelectorAll('.card, .stat-card, .table').forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('fade-in');
        }, index * 50);
    });
});

// Expose functions globally
window.PolishUI = {
    toast,
    loading,
    createRipple,
    confirmAction,
    fetchWithLoading,
    debounce,
    formatCurrency,
    formatDate,
    formatDateTime,
    escapeHtml,
    shortcuts,
    scrollToElement,
    animateElement,
    copyToClipboard,
    exportToCSV,
    printElement
};
