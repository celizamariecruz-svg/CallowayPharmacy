<?php
/**
 * System Settings
 * Configure store information, backups, and system preferences
 */

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('settings.view')) {
    die('<h1>Access Denied</h1><p>You do not have permission to access settings.</p>');
}

$currentUser = $auth->getCurrentUser();
$page_title = 'System Settings';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <script>
    // Apply theme immediately to prevent flash
    (function() {
      const theme = localStorage.getItem('calloway_theme') || 'light';
      document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="custom-modal.js?v=2"></script>
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 80px auto 0;
            padding: 2rem;
        }

        .settings-header {
            margin-bottom: 2rem;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--input-border);
            box-shadow: var(--shadow-sm);
        }

        .settings-header h1 {
            font-size: 2rem;
            margin: 0 0 0.5rem;
            color: var(--primary-color);
            font-weight: 800;
        }

        .settings-header p {
            color: var(--text-light);
            margin: 0;
            font-size: 1rem;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 2rem;
        }

        .settings-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--input-border);
            transition: transform 0.3s ease;
        }

        .settings-card:hover {
            transform: translateY(-5px);
        }

        .settings-card h2 {
            margin: 0 0 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .settings-card h2 i {
            background: rgba(37, 99, 235, 0.1);
            padding: 0.5rem;
            border-radius: 10px;
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--input-border);
            border-radius: 10px;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-color);
            opacity: 0.7;
            font-size: 0.85rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .backup-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            padding: 1rem;
            background: var(--bg-color);
        }

        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid var(--input-border);
        }

        .backup-item:last-child {
            border-bottom: none;
        }

        .backup-item:hover {
            background: var(--dropdown-hover);
        }

        .backup-info {
            flex: 1;
        }

        .backup-info .name {
            font-weight: 600;
            color: var(--text-color);
        }

        .backup-info .date {
            font-size: 0.85rem;
            color: var(--text-color);
            opacity: 0.7;
        }

        .backup-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .toast {
            position: fixed;
            top: 100px;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: none;
            z-index: 10000;
            border-left: 4px solid var(--primary-color);
        }

        .toast.success {
            border-color: var(--secondary-color);
        }

        .toast.error {
            border-color: var(--danger-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .theme-preview {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .theme-option {
            flex: 1;
            padding: 2rem;
            border: 3px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .theme-option.light {
            background: #ffffff;
            color: #1a1a2e;
        }

        .theme-option.dark {
            background: #1a1a2e;
            color: #ffffff;
        }

        .theme-option.active {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(10, 116, 218, 0.3);
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'header-component.php'; ?>

    <div class="settings-container">
        <div class="settings-header">
            <h1>⚙️ System Settings</h1>
            <p>Configure your pharmacy's system preferences and settings</p>
        </div>

        <div class="settings-grid">
            <!-- Store Information -->
            <div class="settings-card">
                <h2>🏪 Store Information</h2>
                <form id="storeForm" onsubmit="saveStoreSettings(event)">
                    <div class="form-group">
                        <label for="storeName">Store Name *</label>
                        <input type="text" id="storeName" value="Calloway Pharmacy" required>
                    </div>

                    <div class="form-group">
                        <label for="storeAddress">Address</label>
                        <textarea id="storeAddress" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="storePhone">Phone Number</label>
                        <input type="tel" id="storePhone">
                    </div>

                    <div class="form-group">
                        <label for="storeEmail">Email</label>
                        <input type="email" id="storeEmail">
                    </div>

                    <?php if ($auth->hasPermission('settings.edit')): ?>
                        <button type="submit" class="btn btn-primary">💾 Save Store Info</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tax & Currency Settings -->
            <div class="settings-card">
                <h2>💰 Tax & Currency</h2>
                <form id="taxForm" onsubmit="saveTaxSettings(event)">
                    <div class="form-group">
                        <label for="taxRate">Tax Rate (%)</label>
                        <input type="number" id="taxRate" step="0.01" value="12.00">
                        <small>Default tax rate applied to sales</small>
                    </div>

                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select id="currency">
                            <option value="PHP" selected>PHP (₱)</option>
                            <option value="USD">USD ($)</option>
                            <option value="EUR">EUR (€)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="receiptFooter">Receipt Footer Message</label>
                        <textarea id="receiptFooter" rows="3" placeholder="Thank you for shopping with us!"></textarea>
                        <small>Custom message displayed on receipts</small>
                    </div>

                    <?php if ($auth->hasPermission('settings.edit')): ?>
                        <button type="submit" class="btn btn-primary">💾 Save Tax Settings</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Appearance Theme -->
            <div class="settings-card">
                <h2>🎨 Appearance</h2>
                <div class="form-group">
                    <label>Theme</label>
                    <div class="theme-preview">
                        <div class="theme-option light" onclick="setThemeOption('light')">
                            <div style="font-size: 2rem;">☀️</div>
                            <div><strong>Light Mode</strong></div>
                        </div>
                        <div class="theme-option dark" onclick="setThemeOption('dark')">
                            <div style="font-size: 2rem;">🌙</div>
                            <div><strong>Dark Mode</strong></div>
                        </div>
                    </div>
                    <small>Choose your preferred theme</small>
                </div>
            </div>

            <!-- Database Backup & Restore -->
            <div class="settings-card">
                <h2>💾 Database Backup</h2>

                <div style="margin-bottom: 1.5rem;">
                    <p><strong>Automatic Backups:</strong> Products are automatically backed up when modified</p>
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <?php if ($auth->hasPermission('settings.backup')): ?>
                            <button class="btn btn-primary" onclick="createBackup()">
                                📦 Create Manual Backup
                            </button>
                            <button class="btn btn-secondary" onclick="loadBackups()">
                                🔄 Refresh List
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Recent Backups</label>
                    <div class="backup-list" id="backupList">
                        <p style="text-align: center; padding: 1rem; opacity: 0.7;">Loading backups...</p>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert Settings -->
            <div class="settings-card">
                <h2>⚠️ Alert Settings</h2>
                <form id="alertForm" onsubmit="saveAlertSettings(event)">
                    <div class="form-group">
                        <label for="lowStockThreshold">Low Stock Threshold</label>
                        <input type="number" id="lowStockThreshold" value="20">
                        <small>Alert when product stock falls below this level</small>
                    </div>

                    <div class="form-group">
                        <label for="expiryAlert">Expiry Alert (Days)</label>
                        <input type="number" id="expiryAlert" value="30">
                        <small>Alert when products are expiring within this many days</small>
                    </div>

                    <?php if ($auth->hasPermission('settings.edit')): ?>
                        <button type="submit" class="btn btn-primary">💾 Save Alert Settings</button>
                        <button type="button" class="btn btn-secondary" onclick="testLowStockAlert()" style="margin-left: 0.5rem; background: #f59e0b; color: #fff; border: none;">
                            📧 Test Low Stock Alert Email
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- System Info -->
            <div class="settings-card">
                <h2>ℹ️ System Information</h2>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 0.5rem 0; font-weight: 600;">System Version:</td>
                        <td style="padding: 0.5rem 0;">1.0.0</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem 0; font-weight: 600;">PHP Version:</td>
                        <td style="padding: 0.5rem 0;"><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem 0; font-weight: 600;">Database:</td>
                        <td style="padding: 0.5rem 0;">MySQL/MariaDB</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.5rem 0; font-weight: 600;">Last Backup:</td>
                        <td style="padding: 0.5rem 0;" id="lastBackupDate">Checking...</td>
                    </tr>
                </table>

                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button class="btn btn-warning" onclick="clearCache()">🗑️ Clear Cache</button>
                    <button class="btn btn-danger"
                        onclick="customConfirm('Logout', 'This will log you out. Continue?', 'logout', { confirmText: 'Yes, Logout', cancelText: 'Stay' }).then(ok => { if(ok) window.location.href='logout.php'; })">
                        🚪 Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>

    <script src="theme.js"></script>
    <script>
        // Load data on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadSettings();
            loadBackups();
            updateThemeSelection();
        });

        // Load settings from API
        async function loadSettings() {
            try {
                const response = await fetch('settings_api.php?action=get_settings');
                const result = await response.json();

                if (result.success) {
                    const settings = result.data;

                    // Store Settings
                    if (document.getElementById('storeName')) document.getElementById('storeName').value = settings.company_name || '';
                    if (document.getElementById('storeAddress')) document.getElementById('storeAddress').value = settings.store_address || '';
                    if (document.getElementById('storePhone')) document.getElementById('storePhone').value = settings.store_phone || '';
                    if (document.getElementById('storeEmail')) document.getElementById('storeEmail').value = settings.store_email || '';

                    // Tax & Currency
                    if (document.getElementById('taxRate')) document.getElementById('taxRate').value = settings.tax_rate || '12.00';
                    if (document.getElementById('currency')) {
                        const symbol = settings.currency_symbol || '₱';
                        document.getElementById('currency').value = symbol === '₱' ? 'PHP' : 'USD';
                    }
                    if (document.getElementById('receiptFooter')) document.getElementById('receiptFooter').value = settings.receipt_footer || '';

                    // Alert Settings
                    if (document.getElementById('lowStockThreshold')) document.getElementById('lowStockThreshold').value = settings.low_stock_threshold || '20';
                    if (document.getElementById('expiryAlert')) document.getElementById('expiryAlert').value = settings.expiry_alert_days || '30';
                }
            } catch (error) {
                console.error('Error loading settings:', error);
                showToast('Error loading settings', 'error');
            }
        }

        // Save store settings
        async function saveStoreSettings(event) {
            event.preventDefault();

            const settings = {
                store_name: document.getElementById('storeName').value,
                store_address: document.getElementById('storeAddress').value,
                store_phone: document.getElementById('storePhone').value,
                store_email: document.getElementById('storeEmail').value
            };

            await saveSettingsToApi(settings, 'Store information saved successfully');
        }

        // Save tax settings
        async function saveTaxSettings(event) {
            event.preventDefault();

            const settings = {
                tax_rate: document.getElementById('taxRate').value,
                currency: document.getElementById('currency').value,
                receipt_footer: document.getElementById('receiptFooter').value
            };

            await saveSettingsToApi(settings, 'Tax settings saved successfully');
        }

        // Save alert settings
        async function saveAlertSettings(event) {
            event.preventDefault();

            const settings = {
                low_stock_threshold: document.getElementById('lowStockThreshold').value,
                expiry_alert: document.getElementById('expiryAlert').value
            };

            await saveSettingsToApi(settings, 'Alert settings saved successfully');
        }

        // Test Low Stock Alert Email
        async function testLowStockAlert() {
            const btn = event.target;
            const origText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Sending...';
            try {
                const formData = new FormData();
                formData.append('action', 'test_low_stock_alert');
                const res = await fetch('api_settings.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Low stock alert email sent!', 'success');
                } else {
                    showToast(data.message || 'Failed to send alert email', 'error');
                }
            } catch (err) {
                console.error('Test low stock alert error:', err);
                showToast('Network error sending test alert', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = origText;
            }
        }

        // Helper to save to API
        async function saveSettingsToApi(data, successMessage) {
            try {
                const response = await fetch('settings_api.php?action=update_settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast(successMessage, 'success');
                } else {
                    showToast(result.message || 'Failed to save settings', 'error');
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                showToast('Error saving settings', 'error');
            }
        }

        // Set theme option
        function setThemeOption(theme) {
            setTheme(theme);
            updateThemeSelection();
            showToast(`Theme changed to ${theme} mode`, 'success');
        }

        // Update theme selection UI
        function updateThemeSelection() {
            const currentTheme = localStorage.getItem('calloway_theme') || 'light';
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelector(`.theme-option.${currentTheme}`).classList.add('active');
        }

        // Create manual backup
        async function createBackup() {
            try {
                showToast('Creating backup...', 'success');

                const response = await fetch('backup_products.php');
                const text = await response.text();

                if (text.includes('successfully')) {
                    showToast('Backup created successfully', 'success');
                    loadBackups();
                } else {
                    showToast('Backup may have failed. Check console.', 'error');
                }
            } catch (error) {
                console.error('Error creating backup:', error);
                showToast('Error creating backup', 'error');
            }
        }

        // Load backups list
        async function loadBackups() {
            try {
                const response = await fetch('list_backups.php');
                const data = await response.json();

                const backupList = document.getElementById('backupList');

                if (data.success && data.backups.length > 0) {
                    backupList.innerHTML = data.backups.map(backup => `
                        <div class="backup-item">
                            <div class="backup-info">
                                <div class="name">📦 ${escapeHtml(backup.name)}</div>
                                <div class="date">${escapeHtml(backup.date)} - ${escapeHtml(backup.size)}</div>
                            </div>
                            <div class="backup-actions">
                                <button class="btn btn-secondary btn-small" onclick="downloadBackup('${escapeHtml(backup.name)}')">
                                    Download
                                </button>
                            </div>
                        </div>
                    `).join('');

                    // Update last backup date
                    if (data.backups.length > 0) {
                        document.getElementById('lastBackupDate').textContent = data.backups[0].date;
                    }
                } else {
                    backupList.innerHTML = '<p style="text-align: center; padding: 1rem; opacity: 0.7;">No backups found</p>';
                    document.getElementById('lastBackupDate').textContent = 'Never';
                }
            } catch (error) {
                console.error('Error loading backups:', error);
                document.getElementById('backupList').innerHTML = '<p style="text-align: center; padding: 1rem; color: red;">Error loading backups</p>';
            }
        }

        // Download backup
        function downloadBackup(filename) {
            window.location.href = `backups/${filename}`;
        }

        // Clear cache
        function clearCache() {
            localStorage.removeItem('calloway_cached_products');
            showToast('Cache cleared successfully', 'success');
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');

            toastMessage.textContent = message;
            toast.className = `toast ${type} active`;

            setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        }

        // Utility function
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Keyboard Shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl+S - Save Settings
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.querySelector('.settings-form')?.requestSubmit();
            }
        });
    </script>
    <script src="shared-polish.js"></script>
</body>

</html>