/**
 * UI Enhancements for Calloway Pharmacy IMS
 * Includes: Loading states, confirmations, tooltips, keyboard shortcuts, empty states
 */

(function() {
    'use strict';
    
    // ============================================
    // LOADING STATES
    // ============================================
    
    let loadingOverlay = null;
    
    function createLoadingOverlay() {
        if (!loadingOverlay) {
            loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Processing...</p>
                </div>
            `;
            document.body.appendChild(loadingOverlay);
        }
        return loadingOverlay;
    }
    
    function showLoading(message = 'Processing...') {
        const overlay = createLoadingOverlay();
        overlay.querySelector('p').textContent = message;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function hideLoading() {
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    // Auto-add loading to forms
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Don't add loading to search forms
        if (form.method.toLowerCase() === 'get') return;
        
        // Check if form has data-no-loading attribute
        if (form.hasAttribute('data-no-loading')) return;
        
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        const message = submitBtn?.getAttribute('data-loading-message') || 'Processing...';
        
        showLoading(message);
        
        // Auto-hide after 10 seconds as fallback
        setTimeout(hideLoading, 10000);
    });
    
    // Hide loading on page unload
    window.addEventListener('beforeunload', hideLoading);
    
    // ============================================
    // CONFIRMATION DIALOGS
    // ============================================
    
    function showConfirmDialog(message, onConfirm, onCancel) {
        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog-overlay';
        dialog.innerHTML = `
            <div class="confirm-dialog">
                <div class="confirm-icon">⚠️</div>
                <h3>Confirm Action</h3>
                <p>${message}</p>
                <div class="confirm-buttons">
                    <button class="btn btn-danger confirm-yes">Yes, Continue</button>
                    <button class="btn btn-secondary confirm-no">Cancel</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(dialog);
        setTimeout(() => dialog.style.display = 'flex', 10);
        
        dialog.querySelector('.confirm-yes').onclick = () => {
            dialog.remove();
            if (onConfirm) onConfirm();
        };
        
        dialog.querySelector('.confirm-no').onclick = () => {
            dialog.remove();
            if (onCancel) onCancel();
        };
        
        // Close on outside click
        dialog.onclick = (e) => {
            if (e.target === dialog) {
                dialog.remove();
                if (onCancel) onCancel();
            }
        };
        
        // Focus on No button
        dialog.querySelector('.confirm-no').focus();
    }
    
    // Auto-add confirmations to elements with data-confirm
    document.addEventListener('click', function(e) {
        const element = e.target.closest('[data-confirm]');
        if (!element) return;
        
        const message = element.getAttribute('data-confirm');
        if (!message) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        showConfirmDialog(message, () => {
            // Remove the confirmation attribute temporarily
            element.removeAttribute('data-confirm');
            
            // Trigger the action
            if (element.tagName === 'FORM') {
                element.submit();
            } else if (element.onclick) {
                element.onclick(e);
            } else if (element.href) {
                window.location.href = element.href;
            } else {
                element.click();
            }
            
            // Restore confirmation
            element.setAttribute('data-confirm', message);
        });
    });
    
    // ============================================
    // TOOLTIPS
    // ============================================
    
    let tooltipElement = null;
    
    function createTooltip() {
        if (!tooltipElement) {
            tooltipElement = document.createElement('div');
            tooltipElement.className = 'ui-tooltip';
            document.body.appendChild(tooltipElement);
        }
        return tooltipElement;
    }
    
    function showTooltip(element, text) {
        const tooltip = createTooltip();
        tooltip.textContent = text;
        tooltip.style.display = 'block';
        
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        let top = rect.top - tooltipRect.height - 10;
        
        // Adjust if tooltip goes off screen
        if (left < 5) left = 5;
        if (left + tooltipRect.width > window.innerWidth - 5) {
            left = window.innerWidth - tooltipRect.width - 5;
        }
        
        // If tooltip goes above viewport, show below
        if (top < 5) {
            top = rect.bottom + 10;
            tooltip.classList.add('bottom');
        } else {
            tooltip.classList.remove('bottom');
        }
        
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + window.scrollY + 'px';
    }
    
    function hideTooltip() {
        if (tooltipElement) {
            tooltipElement.style.display = 'none';
        }
    }
    
    // Add tooltip listeners
    document.addEventListener('mouseover', function(e) {
        const element = e.target.closest('[data-tooltip], [title]');
        if (!element) return;
        
        const text = element.getAttribute('data-tooltip') || element.getAttribute('title');
        if (!text) return;
        
        // Remove title to prevent default tooltip
        if (element.hasAttribute('title')) {
            element.setAttribute('data-original-title', element.getAttribute('title'));
            element.removeAttribute('title');
        }
        
        showTooltip(element, text);
    });
    
    document.addEventListener('mouseout', function(e) {
        const element = e.target.closest('[data-tooltip], [data-original-title]');
        if (!element) return;
        
        // Restore title
        if (element.hasAttribute('data-original-title')) {
            element.setAttribute('title', element.getAttribute('data-original-title'));
        }
        
        hideTooltip();
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    
    const shortcuts = {
        'ctrl+s': function(e) {
            e.preventDefault();
            const form = document.querySelector('form');
            if (form) {
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        },
        'ctrl+n': function(e) {
            e.preventDefault();
            const addButton = document.querySelector('[data-action="add"], .add-btn, #addBtn');
            if (addButton) addButton.click();
        },
        'escape': function(e) {
            // Close modals
            const modal = document.querySelector('.modal:not([style*="display: none"])');
            if (modal) {
                modal.style.display = 'none';
                return;
            }
            
            // Clear search
            const searchInput = document.querySelector('input[type="search"], input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
            }
        },
        'ctrl+f': function(e) {
            const searchInput = document.querySelector('input[type="search"], input[name="search"]');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        },
        '/': function(e) {
            // Quick search (like GitHub)
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const searchInput = document.querySelector('input[type="search"], input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        }
    };
    
    document.addEventListener('keydown', function(e) {
        const key = [];
        if (e.ctrlKey) key.push('ctrl');
        if (e.shiftKey) key.push('shift');
        if (e.altKey) key.push('alt');
        key.push(e.key.toLowerCase());
        
        const combo = key.join('+');
        
        if (shortcuts[combo]) {
            shortcuts[combo](e);
        }
    });
    
    // ============================================
    // SEARCH IMPROVEMENTS
    // ============================================
    
    function highlightSearchTerm(text, term) {
        if (!term) return text;
        
        const regex = new RegExp(`(${term})`, 'gi');
        return text.replace(regex, '<mark class="search-highlight">$1</mark>');
    }
    
    // Enhanced search with debouncing
    const searchInputs = document.querySelectorAll('input[type="search"], input[name="search"]');
    searchInputs.forEach(input => {
        let timeout;
        
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            
            timeout = setTimeout(() => {
                const term = this.value.toLowerCase();
                const table = this.closest('section, div').querySelector('table');
                
                if (!table) return;
                
                const rows = table.querySelectorAll('tbody tr');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    
                    if (text.includes(term)) {
                        row.style.display = '';
                        visibleCount++;
                        
                        // Highlight matching text
                        if (term) {
                            row.querySelectorAll('td').forEach(cell => {
                                const originalText = cell.getAttribute('data-original-text') || cell.textContent;
                                cell.setAttribute('data-original-text', originalText);
                                cell.innerHTML = highlightSearchTerm(originalText, term);
                            });
                        }
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Show "no results" message
                const existingMsg = table.querySelector('.no-results-message');
                if (existingMsg) existingMsg.remove();
                
                if (visibleCount === 0 && term) {
                    const noResultsRow = document.createElement('tr');
                    noResultsRow.className = 'no-results-message';
                    noResultsRow.innerHTML = `
                        <td colspan="100" style="text-align: center; padding: 40px; color: #999;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🔍</div>
                            <p>No results found for "<strong>${term}</strong>"</p>
                        </td>
                    `;
                    table.querySelector('tbody').appendChild(noResultsRow);
                }
            }, 300); // 300ms debounce
        });
        
        // Clear search on Escape
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('input'));
            }
        });
    });
    
    // ============================================
    // EMPTY STATES
    // ============================================
    
    function checkEmptyTables() {
        const tables = document.querySelectorAll('table');
        
        tables.forEach(table => {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            const rows = tbody.querySelectorAll('tr:not(.empty-state-row)');
            const hasData = Array.from(rows).some(row => row.style.display !== 'none');
            
            if (!hasData && !tbody.querySelector('.empty-state-row')) {
                const emptyRow = document.createElement('tr');
                emptyRow.className = 'empty-state-row';
                emptyRow.innerHTML = `
                    <td colspan="100" style="text-align: center; padding: 60px 20px; color: #999;">
                        <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;">📋</div>
                        <h3 style="margin: 0 0 10px 0; color: #666;">No data available</h3>
                        <p style="margin: 0; color: #999;">Start by adding your first entry</p>
                    </td>
                `;
                tbody.appendChild(emptyRow);
            } else if (hasData) {
                const emptyRow = tbody.querySelector('.empty-state-row');
                if (emptyRow) emptyRow.remove();
            }
        });
    }
    
    // Check on page load
    window.addEventListener('DOMContentLoaded', checkEmptyTables);
    
    // Check after AJAX updates
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args).then(response => {
            setTimeout(checkEmptyTables, 100);
            return response;
        });
    };
    
    // ============================================
    // LAST UPDATED TIMESTAMPS
    // ============================================
    
    function formatRelativeTime(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const diff = now - date;
        
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (seconds < 60) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        
        return date.toLocaleDateString();
    }
    
    function updateTimestamps() {
        document.querySelectorAll('[data-timestamp]').forEach(element => {
            const timestamp = element.getAttribute('data-timestamp');
            const relative = formatRelativeTime(timestamp);
            
            if (!element.hasAttribute('data-original-text')) {
                element.setAttribute('data-original-text', element.textContent);
            }
            
            element.textContent = relative;
            element.setAttribute('title', new Date(timestamp).toLocaleString());
        });
    }
    
    // Update timestamps every minute
    updateTimestamps();
    setInterval(updateTimestamps, 60000);
    
    // ============================================
    // GLOBAL EXPOSURE
    // ============================================
    
    window.UIEnhancements = {
        showLoading,
        hideLoading,
        showConfirmDialog,
        showTooltip,
        hideTooltip,
        highlightSearchTerm,
        checkEmptyTables,
        updateTimestamps
    };
    
    // ============================================
    // KEYBOARD SHORTCUTS HELP
    // ============================================
    
    // Show shortcuts help with ? key
    document.addEventListener('keydown', function(e) {
        if (e.key === '?' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            showShortcutsHelp();
        }
    });
    
    function showShortcutsHelp() {
        const helpDialog = document.createElement('div');
        helpDialog.className = 'confirm-dialog-overlay';
        helpDialog.innerHTML = `
            <div class="confirm-dialog" style="max-width: 500px;">
                <h3>⌨️ Keyboard Shortcuts</h3>
                <table style="width: 100%; margin: 20px 0; text-align: left;">
                    <tr>
                        <td style="padding: 8px; font-weight: bold;">Ctrl + S</td>
                        <td style="padding: 8px;">Save / Submit form</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold;">Ctrl + N</td>
                        <td style="padding: 8px;">Add new entry</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold;">Ctrl + F</td>
                        <td style="padding: 8px;">Focus search</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold;">/</td>
                        <td style="padding: 8px;">Quick search</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold;">Escape</td>
                        <td style="padding: 8px;">Close modal / Clear search</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold;">?</td>
                        <td style="padding: 8px;">Show this help</td>
                    </tr>
                </table>
                <button class="btn btn-primary" onclick="this.closest('.confirm-dialog-overlay').remove()" style="width: 100%;">
                    Got it!
                </button>
            </div>
        `;
        
        document.body.appendChild(helpDialog);
        setTimeout(() => helpDialog.style.display = 'flex', 10);
        
        helpDialog.onclick = (e) => {
            if (e.target === helpDialog) {
                helpDialog.remove();
            }
        };
    }
    
    console.log('✨ UI Enhancements loaded. Press ? for keyboard shortcuts help.');
    
})();
