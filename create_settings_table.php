-- Settings Table Schema
-- Run this to create the settings table

USE calloway_pharmacy;

CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, category, description) VALUES
('company_name', 'Calloway Pharmacy', 'company', 'Company/Store name'),
('company_address', '', 'company', 'Company address'),
('company_phone', '', 'company', 'Company phone number'),
('company_email', '', 'company', 'Company email address'),
('company_website', '', 'company', 'Company website URL'),
('company_logo', '', 'company', 'Company logo path/URL'),
('tax_rate', '12.00', 'tax', 'Default tax rate percentage'),
('currency', 'PHP', 'tax', 'Currency code'),
('currency_symbol', '₱', 'tax', 'Currency symbol'),
('enable_tax', '1', 'tax', 'Enable/disable tax calculation'),
('email_host', '', 'email', 'SMTP server host'),
('email_port', '587', 'email', 'SMTP server port'),
('email_username', '', 'email', 'SMTP username'),
('email_password', '', 'email', 'SMTP password (encrypted)'),
('email_from_name', 'Calloway Pharmacy', 'email', 'From name for emails'),
('email_from_address', '', 'email', 'From email address'),
('email_encryption', 'tls', 'email', 'Email encryption (tls/ssl)'),
('receipt_header', 'Thank you for shopping with us!', 'receipt', 'Receipt header message'),
('receipt_footer', 'Please come again!', 'receipt', 'Receipt footer message'),
('receipt_show_logo', '1', 'receipt', 'Show logo on receipt'),
('receipt_show_barcode', '1', 'receipt', 'Show barcode on receipt'),
('receipt_width', '80mm', 'receipt', 'Receipt paper width'),
('low_stock_threshold', '20', 'alerts', 'Low stock alert threshold'),
('expiry_alert_days', '30', 'alerts', 'Days before expiry to alert'),
('enable_email_alerts', '0', 'alerts', 'Enable email alerts'),
('alert_email', '', 'alerts', 'Email address for alerts'),
('auto_backup_enabled', '1', 'backup', 'Enable automatic backups'),
('backup_frequency', 'daily', 'backup', 'Backup frequency'),
('backup_retention_days', '30', 'backup', 'Days to keep backups'),
('last_backup_date', '', 'backup', 'Last backup timestamp')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
