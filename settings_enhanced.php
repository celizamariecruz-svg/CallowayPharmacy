<?php
/**
 * Enhanced System Settings
 * Comprehensive configuration for all system aspects
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

// Load all settings
$settings_query = "SELECT setting_key, setting_value, category FROM settings";
$settings_result = $conn->query($settings_query);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1400px;
            margin: 100px auto 2rem;
            padding: 2rem;
        }
        
        .settings-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            overflow-x: auto;
        }
        
        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-color);
            opacity: 0.6;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab-button:hover {
            opacity: 0.8;
        }
        
        .tab-button.active {
            opacity: 1;
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 2rem;
        }
        
        .settings-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] .settings-card {
            background: #1e293b;
        }
        
        .settings-card h3 {
            margin: 0 0 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-color);
            opacity: 0.7;
            font-size: 0.875rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(10, 116, 218, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .backup-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: var(--bg-color);
            border-radius: 6px;
        }
        
        .backup-item:last-child {
            margin-bottom: 0;
        }
        
        .info-box {
            background: rgba(37, 99, 235, 0.1);
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .success-box {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            display: none;
        }
        
        .success-box.show {
            display: block;
            animation: slideInRight 0.3s;
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
        <div style="margin-bottom: 2rem;">
            <h1>⚙️ System Settings</h1>
            <p style="color: var(--text-color); opacity: 0.8;">Configure all aspects of your pharmacy management system</p>
        </div>
        
        <!-- Settings Tabs -->
        <div class="settings-tabs">
            <button class="tab-button active" onclick="showTab('company')">
                🏪 Company Info
            </button>
            <button class="tab-button" onclick="showTab('tax')">
                💰 Tax & Currency
            </button>
            <button class="tab-button" onclick="showTab('email')">
                📧 Email Server
            </button>
            <button class="tab-button" onclick="showTab('receipt')">
                🧾 Receipt Settings
            </button>
            <button class="tab-button" onclick="showTab('alerts')">
                ⚠️ Alerts
            </button>
            <button class="tab-button" onclick="showTab('backup')">
                💾 Backup & Restore
            </button>
            <button class="tab-button" onclick="showTab('system')">
                🔧 System
            </button>
        </div>
        
        <!-- Company Information Tab -->
        <div id="company-tab" class="tab-content active">
            <div class="settings-card">
                <h3>🏪 Company Information</h3>
                <form id="companyForm" onsubmit="saveCompanySettings(event)">
                    <div class="form-group">
                        <label for="company_name">Company Name *</label>
                        <input type="text" id="company_name" name="company_name" 
                               value="<?php echo htmlspecialchars($settings['company_name'] ?? 'Calloway Pharmacy'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="company_address">Address</label>
                        <textarea id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                        <small>Full business address for receipts and documents</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="company_phone">Phone Number</label>
                            <input type="tel" id="company_phone" name="company_phone" 
                                   value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="company_email">Email</label>
                            <input type="email" id="company_email" name="company_email" 
                                   value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="company_website">Website</label>
                        <input type="url" id="company_website" name="company_website" 
                               value="<?php echo htmlspecialchars($settings['company_website'] ?? ''); ?>" 
                               placeholder="https://www.example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="company_logo">Logo URL</label>
                        <input type="text" id="company_logo" name="company_logo" 
                               value="<?php echo htmlspecialchars($settings['company_logo'] ?? ''); ?>" 
                               placeholder="logo.png">
                        <small>Path to your company logo image</small>
                    </div>
                    
                    <?php if ($auth->hasPermission('settings.edit')): ?>
                    <button type="submit" class="btn btn-primary">💾 Save Company Information</button>
                    <?php endif; ?>
                    
                    <div class="success-box" id="company-success">
                        ✅ Company information saved successfully!
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tax & Currency Tab -->
        <div id="tax-tab" class="tab-content">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>💰 Tax Configuration</h3>
                    <form id="taxForm" onsubmit="saveTaxSettings(event)">
                        <div class="form-group">
                            <label for="tax_rate">Tax Rate (%)</label>
                            <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100"
                                   value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '12.00'); ?>">
                            <small>Default tax rate applied to all sales</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="enable_tax" name="enable_tax" 
                                       <?php echo ($settings['enable_tax'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Enable tax calculation
                            </label>
                            <small>Turn off to disable tax on all transactions</small>
                        </div>
                        
                        <?php if ($auth->hasPermission('settings.edit')): ?>
                        <button type="submit" class="btn btn-primary">💾 Save Tax Settings</button>
                        <?php endif; ?>
                        
                        <div class="success-box" id="tax-success">
                            ✅ Tax settings saved successfully!
                        </div>
                    </form>
                </div>
                
                <div class="settings-card">
                    <h3>💱 Currency Settings</h3>
                    <form id="currencyForm" onsubmit="saveCurrencySettings(event)">
                        <div class="form-group">
                            <label for="currency">Currency Code</label>
                            <select id="currency" name="currency">
                                <option value="PHP" <?php echo ($settings['currency'] ?? 'PHP') == 'PHP' ? 'selected' : ''; ?>>PHP - Philippine Peso</option>
                                <option value="USD" <?php echo ($settings['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo ($settings['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="GBP" <?php echo ($settings['currency'] ?? '') == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                <option value="JPY" <?php echo ($settings['currency'] ?? '') == 'JPY' ? 'selected' : ''; ?>>JPY - Japanese Yen</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" 
                                   value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? '₱'); ?>">
                            <small>Symbol displayed on prices (e.g., ₱, $, €)</small>
                        </div>
                        
                        <?php if ($auth->hasPermission('settings.edit')): ?>
                        <button type="submit" class="btn btn-primary">💾 Save Currency Settings</button>
                        <?php endif; ?>
                        
                        <div class="success-box" id="currency-success">
                            ✅ Currency settings saved successfully!
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Email Server Tab -->
        <div id="email-tab" class="tab-content">
            <div class="settings-card">
                <h3>📧 Email Server Configuration</h3>
                <div class="info-box">
                    <strong>ℹ️ SMTP Configuration</strong><br>
                    Configure your email server settings to send automated emails for alerts, reports, and notifications.
                </div>
                
                <form id="emailForm" onsubmit="saveEmailSettings(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_host">SMTP Host *</label>
                            <input type="text" id="email_host" name="email_host" 
                                   value="<?php echo htmlspecialchars($settings['email_host'] ?? ''); ?>" 
                                   placeholder="smtp.gmail.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="email_port">SMTP Port *</label>
                            <input type="number" id="email_port" name="email_port" 
                                   value="<?php echo htmlspecialchars($settings['email_port'] ?? '587'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_username">Username/Email *</label>
                            <input type="text" id="email_username" name="email_username" 
                                   value="<?php echo htmlspecialchars($settings['email_username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email_password">Password *</label>
                            <input type="password" id="email_password" name="email_password" 
                                   placeholder="Enter password">
                            <small>Password is encrypted before storage</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_from_name">From Name</label>
                            <input type="text" id="email_from_name" name="email_from_name" 
                                   value="<?php echo htmlspecialchars($settings['email_from_name'] ?? 'Calloway Pharmacy'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email_from_address">From Email</label>
                            <input type="email" id="email_from_address" name="email_from_address" 
                                   value="<?php echo htmlspecialchars($settings['email_from_address'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_encryption">Encryption</label>
                        <select id="email_encryption" name="email_encryption">
                            <option value="tls" <?php echo ($settings['email_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                            <option value="ssl" <?php echo ($settings['email_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($settings['email_encryption'] ?? '') == 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    
                    <?php if ($auth->hasPermission('settings.edit')): ?>
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">💾 Save Email Settings</button>
                        <button type="button" class="btn btn-secondary" onclick="testEmail()">📧 Send Test Email</button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="success-box" id="email-success">
                        ✅ Email settings saved successfully!
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Receipt Settings Tab -->
        <div id="receipt-tab" class="tab-content">
            <div class="settings-card">
                <h3>🧾 Receipt Customization</h3>
                <form id="receiptForm" onsubmit="saveReceiptSettings(event)">
                    <div class="form-group">
                        <label for="receipt_header">Receipt Header</label>
                        <textarea id="receipt_header" name="receipt_header" rows="2"><?php echo htmlspecialchars($settings['receipt_header'] ?? 'Thank you for shopping with us!'); ?></textarea>
                        <small>Message displayed at the top of receipts</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="receipt_footer">Receipt Footer</label>
                        <textarea id="receipt_footer" name="receipt_footer" rows="2"><?php echo htmlspecialchars($settings['receipt_footer'] ?? 'Please come again!'); ?></textarea>
                        <small>Message displayed at the bottom of receipts</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="receipt_show_logo" name="receipt_show_logo" 
                                       <?php echo ($settings['receipt_show_logo'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Show Company Logo
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="receipt_show_barcode" name="receipt_show_barcode" 
                                       <?php echo ($settings['receipt_show_barcode'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Show Receipt Barcode
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="receipt_width">Receipt Width</label>
                        <select id="receipt_width" name="receipt_width">
                            <option value="58mm" <?php echo ($settings['receipt_width'] ?? '') == '58mm' ? 'selected' : ''; ?>>58mm (Small)</option>
                            <option value="80mm" <?php echo ($settings['receipt_width'] ?? '80mm') == '80mm' ? 'selected' : ''; ?>>80mm (Standard)</option>
                            <option value="A4" <?php echo ($settings['receipt_width'] ?? '') == 'A4' ? 'selected' : ''; ?>>A4 (Full Page)</option>
                        </select>
                        <small>Paper size for thermal printers</small>
                    </div>
                    
                    <?php if ($auth->hasPermission('settings.edit')): ?>
                    <button type="submit" class="btn btn-primary">💾 Save Receipt Settings</button>
                    <?php endif; ?>
                    
                    <div class="success-box" id="receipt-success">
                        ✅ Receipt settings saved successfully!
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Alerts Tab -->
        <div id="alerts-tab" class="tab-content">
            <div class="settings-card">
                <h3>⚠️ Alert Configuration</h3>
                <form id="alertsForm" onsubmit="saveAlertsSettings(event)">
                    <div class="form-group">
                        <label for="low_stock_threshold">Low Stock Threshold</label>
                        <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0"
                               value="<?php echo htmlspecialchars($settings['low_stock_threshold'] ?? '20'); ?>">
                        <small>Alert when product quantity falls below this number</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="expiry_alert_days">Expiry Alert (Days)</label>
                        <input type="number" id="expiry_alert_days" name="expiry_alert_days" min="1"
                               value="<?php echo htmlspecialchars($settings['expiry_alert_days'] ?? '30'); ?>">
                        <small>Alert when products are expiring within this many days</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="enable_email_alerts" name="enable_email_alerts" 
                                   <?php echo ($settings['enable_email_alerts'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            Enable Email Alerts
                        </label>
                        <small>Send automated email alerts for low stock and expiry</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="alert_email">Alert Email Address</label>
                        <input type="email" id="alert_email" name="alert_email" 
                               value="<?php echo htmlspecialchars($settings['alert_email'] ?? ''); ?>">
                        <small>Email address to receive automated alerts</small>
                    </div>
                    
                    <?php if ($auth->hasPermission('settings.edit')): ?>
                    <button type="submit" class="btn btn-primary">💾 Save Alert Settings</button>
                    <?php endif; ?>
                    
                    <div class="success-box" id="alerts-success">
                        ✅ Alert settings saved successfully!
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Backup & Restore Tab -->
        <div id="backup-tab" class="tab-content">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>💾 Database Backup</h3>
                    <div class="info-box">
                        <strong>ℹ️ Automatic Backups</strong><br>
                        Products are automatically backed up when modified. Create manual backups for complete database snapshots.
                    </div>
                    
                    <?php if ($auth->hasPermission('settings.backup')): ?>
                    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                        <button class="btn btn-success" onclick="createManualBackup()">
                            📦 Create Full Backup
                        </button>
                        <button class="btn btn-secondary" onclick="loadBackupList()">
                            🔄 Refresh List
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Recent Backups</label>
                        <div class="backup-list" id="backupList">
                            <p style="text-align: center; padding: 1rem; opacity: 0.7;">Loading backups...</p>
                        </div>
                    </div>
                    
                    <div class="success-box" id="backup-success">
                        ✅ Backup created successfully!
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>⚙️ Backup Configuration</h3>
                    <form id="backupConfigForm" onsubmit="saveBackupConfig(event)">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="auto_backup_enabled" name="auto_backup_enabled" 
                                       <?php echo ($settings['auto_backup_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Enable Automatic Backups
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="backup_frequency">Backup Frequency</label>
                            <select id="backup_frequency" name="backup_frequency">
                                <option value="daily" <?php echo ($settings['backup_frequency'] ?? 'daily') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo ($settings['backup_frequency'] ?? '') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo ($settings['backup_frequency'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="backup_retention_days">Retention Period (Days)</label>
                            <input type="number" id="backup_retention_days" name="backup_retention_days" min="1"
                                   value="<?php echo htmlspecialchars($settings['backup_retention_days'] ?? '30'); ?>">
                            <small>Automatically delete backups older than this</small>
                        </div>
                        
                        <?php if ($auth->hasPermission('settings.edit')): ?>
                        <button type="submit" class="btn btn-primary">💾 Save Backup Config</button>
                        <?php endif; ?>
                        
                        <div class="success-box" id="backup-config-success">
                            ✅ Backup configuration saved!
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- System Tab -->
        <div id="system-tab" class="tab-content">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>ℹ️ System Information</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 0.75rem 0; font-weight: 600; width: 50%;">System Version:</td>
                            <td style="padding: 0.75rem 0;"><?php echo $settings['system_version'] ?? '1.0.0'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0; font-weight: 600;">PHP Version:</td>
                            <td style="padding: 0.75rem 0;"><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0; font-weight: 600;">Database:</td>
                            <td style="padding: 0.75rem 0;">MySQL <?php echo $conn->server_info; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0; font-weight: 600;">Server:</td>
                            <td style="padding: 0.75rem 0;"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0; font-weight: 600;">Last Backup:</td>
                            <td style="padding: 0.75rem 0;" id="lastBackupDate"><?php echo $settings['last_backup_date'] ?? 'Never'; ?></td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button class="btn btn-danger" onclick="if(confirm('This will log you out. Continue?')) window.location.href='logout.php'">
                            🚪 Logout
                        </button>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>🎨 Appearance</h3>
                    <div class="form-group">
                        <label>Theme</label>
                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <div style="flex: 1; padding: 2rem; background: #ffffff; color: #1a1a2e; border: 3px solid transparent; border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.3s;" onclick="setTheme('light')" id="light-theme">
                                <div style="font-size: 2rem;">☀️</div>
                                <div><strong>Light Mode</strong></div>
                            </div>
                            <div style="flex: 1; padding: 2rem; background: #1a1a2e; color: #ffffff; border: 3px solid transparent; border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.3s;" onclick="setTheme('dark')" id="dark-theme">
                                <div style="font-size: 2rem;">🌙</div>
                                <div><strong>Dark Mode</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="theme.js"></script>
    <script src="global-polish.js"></script>
    <script>
        // Tab Management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        // Save functions
        async function saveSettings(formId, action, successId) {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            formData.append('action', action);
            
            // Handle checkboxes
            form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                formData.set(checkbox.name, checkbox.checked ? '1' : '0');
            });
            
            try {
                const response = await fetch('api_settings.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const successBox = document.getElementById(successId);
                    successBox.classList.add('show');
                    setTimeout(() => successBox.classList.remove('show'), 3000);
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error saving settings: ' + error.message);
            }
        }
        
        function saveCompanySettings(e) {
            e.preventDefault();
            saveSettings('companyForm', 'save_company_info', 'company-success');
        }
        
        function saveTaxSettings(e) {
            e.preventDefault();
            saveSettings('taxForm', 'save_tax_settings', 'tax-success');
        }
        
        function saveCurrencySettings(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('currencyForm'));
            formData.append('action', 'save_tax_settings');
            formData.append('tax_rate', document.getElementById('tax_rate').value);
            formData.append('enable_tax', document.getElementById('enable_tax').checked ? '1' : '0');
            
            fetch('api_settings.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(result => {
                if (result.success) {
                    const successBox = document.getElementById('currency-success');
                    successBox.classList.add('show');
                    setTimeout(() => successBox.classList.remove('show'), 3000);
                }
            });
        }
        
        function saveEmailSettings(e) {
            e.preventDefault();
            saveSettings('emailForm', 'save_email_settings', 'email-success');
        }
        
        function saveReceiptSettings(e) {
            e.preventDefault();
            saveSettings('receiptForm', 'save_receipt_settings', 'receipt-success');
        }
        
        function saveAlertsSettings(e) {
            e.preventDefault();
            saveSettings('alertsForm', 'save_alert_settings', 'alerts-success');
        }
        
        function saveBackupConfig(e) {
            e.preventDefault();
            saveSettings('backupConfigForm', 'save_backup_config', 'backup-config-success');
        }
        
        // Test Email
        async function testEmail() {
            const testEmail = prompt('Enter email address to send test email:');
            if (!testEmail) return;
            
            const formData = new FormData();
            formData.append('action', 'test_email');
            formData.append('test_email', testEmail);
            
            try {
                const response = await fetch('api_settings.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                alert(result.message);
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        // Backup functions
        async function createManualBackup() {
            if (!confirm('Create a full database backup? This may take a moment.')) return;
            
            const formData = new FormData();
            formData.append('action', 'create_backup');
            
            try {
                const response = await fetch('api_settings.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('backup-success').classList.add('show');
                    setTimeout(() => document.getElementById('backup-success').classList.remove('show'), 3000);
                    loadBackupList();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error creating backup: ' + error.message);
            }
        }
        
        function loadBackupList() {
            // TODO: Load backup files from server
            document.getElementById('backupList').innerHTML = '<p style="text-align: center; padding: 1rem; opacity: 0.7;">No backups found</p>';
        }
        
        // Theme handling
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeUI();
        }
        
        function updateThemeUI() {
            const theme = localStorage.getItem('theme') || 'light';
            document.getElementById('light-theme').style.borderColor = theme === 'light' ? 'var(--primary-color)' : 'transparent';
            document.getElementById('dark-theme').style.borderColor = theme === 'dark' ? 'var(--primary-color)' : 'transparent';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadBackupList();
            updateThemeUI();
        });
    </script>
</body>
</html>
