<?php
/**
 * Email Notification Service
 * Handles all email notifications using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php'; // For PHPMailer via Composer
}
require_once 'db_connection.php';

class EmailService {
    private const OWNER_ALERT_EMAIL = 'pharmacycalloway@gmail.com';
    private $conn;
    private $mailer;
    private $fromEmail;
    private $fromName;
    private $smtpSettings = [];
    private $lastError = '';
    
    public function __construct($conn) {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new Exception('PHPMailer is not installed. Run composer install in the project root.');
        }

        $this->conn = $conn;
        $this->mailer = new PHPMailer(true);
        
        // Load SMTP settings from database
        $this->loadSettings();
        
        // Configure PHPMailer
        $this->configureSMTP();
    }
    
    private function loadSettings() {
        // Try to load from settings table (supports both smtp_* and email_* keys)
        $query = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
            'email_host', 'email_port', 'email_username', 'email_password', 'email_from_address', 'email_from_name', 'email_encryption'
        )";
        $result = $this->conn->query($query);
        
        $settings = [
            'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'port' => (int) (getenv('SMTP_PORT') ?: 465),
            'username' => getenv('SMTP_USER') ?: '',
            'password' => getenv('SMTP_PASS') ?: '',
            'from_email' => getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USER') ?: 'noreply@callowaypharmacy.com'),
            'from_name' => getenv('SMTP_FROM_NAME') ?: 'Calloway Pharmacy',
            'encryption' => strtolower(getenv('SMTP_ENCRYPTION') ?: 'ssl')
        ];
        
        if ($result && $result->num_rows > 0) {
            $raw = [];
            while ($row = $result->fetch_assoc()) {
                $raw[$row['setting_key']] = $row['setting_value'];
            }

            $settings['host'] = trim($raw['smtp_host'] ?? $raw['email_host'] ?? $settings['host']);
            $settings['port'] = (int) ($raw['smtp_port'] ?? $raw['email_port'] ?? $settings['port']);
            $settings['username'] = trim($raw['smtp_username'] ?? $raw['email_username'] ?? $settings['username']);

            $passwordValue = $raw['smtp_password'] ?? ($raw['email_password'] ?? $settings['password']);
            // Decrypt using AES-256-CBC encryption
            require_once __DIR__ . '/CryptoManager.php';
            $decryptedPassword = CryptoManager::decrypt($passwordValue);
            // Fallback to raw value if decryption fails (for backward compatibility with base64)
            if ($decryptedPassword === false) {
                $decryptedPassword = base64_decode((string) $passwordValue, true);
                if ($decryptedPassword === false) {
                    $decryptedPassword = $passwordValue;
                }
            }
            $settings['password'] = ($decryptedPassword !== '' ? $decryptedPassword : $passwordValue);

            $settings['from_email'] = trim($raw['smtp_from_email'] ?? $raw['email_from_address'] ?? $settings['from_email']);
            $settings['from_name'] = trim($raw['smtp_from_name'] ?? $raw['email_from_name'] ?? $settings['from_name']);
            $settings['encryption'] = strtolower(trim($raw['email_encryption'] ?? $settings['encryption']));
        }

        if ($settings['username'] === '' && filter_var((string) $settings['from_email'], FILTER_VALIDATE_EMAIL)) {
            $settings['username'] = (string) $settings['from_email'];
        }

        if ($settings['from_email'] === '') {
            $settings['from_email'] = $settings['username'] ?: 'noreply@callowaypharmacy.com';
        }

        $hostLower = strtolower((string) $settings['host']);
        if (strpos($hostLower, 'gmail.com') !== false) {
            $settings['username'] = trim((string) $settings['username']);
            $settings['password'] = preg_replace('/\s+/', '', (string) $settings['password']);
        }
        
        if (trim((string) $settings['host']) === '') {
            $settings['host'] = 'smtp.gmail.com';
        }

        $settings['encryption'] = 'ssl';
        $settings['port'] = 465;

        $this->smtpSettings = $settings;
        $this->fromEmail = $settings['from_email'];
        $this->fromName = $settings['from_name'];
        
        return $settings;
    }
    
    private function configureSMTP() {
        $settings = !empty($this->smtpSettings) ? $this->smtpSettings : $this->loadSettings();
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $settings['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->AuthType = 'LOGIN';
            $this->mailer->Username = $settings['username'];
            $this->mailer->Password = $settings['password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->SMTPAutoTLS = false;
            $this->mailer->Port = (int) $settings['port'];
            $this->mailer->Timeout = 20;
            $this->mailer->SMTPKeepAlive = false;
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            // Ensure UTF-8 headers/body so emojis render correctly in inbox previews
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = PHPMailer::ENCODING_BASE64;
            
            // Set from address
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            
            // HTML format
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Email configuration error: " . $e->getMessage());
        }
    }

    public function getLastError() {
        return (string) $this->lastError;
    }

    private function buildAppUrl($path) {
        $path = ltrim($path, '/');

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');

        $basePath = trim(str_replace('\\', '/', $scriptDir));
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        } else {
            $basePath = rtrim($basePath, '/');
        }

        return $protocol . $host . $basePath . '/' . $path;
    }

    public function sendTestEmail($toEmail) {
        try {
            $this->mailer->Subject = '✅ Test Email - Calloway Pharmacy';
            $this->mailer->Body = $this->getEmailTemplate('test_email', [
                'toEmail' => $toEmail,
                'timeSent' => date('Y-m-d H:i:s')
            ]);
            $this->mailer->addAddress($toEmail);
            $this->mailer->send();
            $this->mailer->clearAddresses();
            return true;
        } catch (Exception $e) {
            $details = trim((string) ($this->mailer->ErrorInfo ?? ''));
            $this->lastError = $details !== '' ? $details : $e->getMessage();
            error_log("Test email error: " . $this->lastError);
            return false;
        }
    }

    public function sendReceiptEmail($toEmail, $saleReference, $totalAmount, $pdfContent, $attachmentName) {
        try {
            $this->mailer->Subject = 'Receipt #' . $saleReference . ' - Calloway Pharmacy';
            $body = '<h2>Thank you for your purchase!</h2>';
            $body .= '<p>Please find your receipt attached.</p>';
            $body .= '<p><strong>Receipt #:</strong> ' . htmlspecialchars($saleReference) . '</p>';
            $body .= '<p><strong>Total:</strong> ₱' . number_format((float) $totalAmount, 2) . '</p>';
            $this->mailer->Body = $body;
            $this->mailer->addAddress($toEmail);
            $this->mailer->addStringAttachment($pdfContent, $attachmentName);
            $this->mailer->send();
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            return true;
        } catch (Exception $e) {
            error_log("Receipt email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send Low Stock Alert
     */
    public function sendLowStockAlert($products) {
        if (empty($products)) return false;
        
        $alertRecipients = $this->getLowStockAlertRecipients();
        if (empty($alertRecipients)) return false;
        
        try {
            $this->mailer->Subject = '⚠️ Low Stock Alert - Calloway Pharmacy';
            
            $body = $this->getEmailTemplate('low_stock', [
                'products' => $products,
                'count' => count($products)
            ]);
            
            $this->mailer->Body = $body;
            
            foreach ($alertRecipients as $email) {
                $this->mailer->addAddress($email);
            }
            
            $this->mailer->send();
            $this->mailer->clearAddresses();
            
            return true;
        } catch (Exception $e) {
            error_log("Low stock email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get low stock alert recipients.
     * Always includes the owner email and optional configured alert email.
     */
    private function getLowStockAlertRecipients() {
        return $this->getSystemAlertRecipients();
    }

    /**
     * Get system alert recipients.
     * Always includes owner email and optional configured alert email.
     */
    private function getSystemAlertRecipients() {
        $emails = [self::OWNER_ALERT_EMAIL];

        $settingsQuery = "SELECT setting_value FROM settings WHERE setting_key = 'alert_email' LIMIT 1";
        $settingsResult = $this->conn->query($settingsQuery);
        if ($settingsResult && $settingsResult->num_rows > 0) {
            $row = $settingsResult->fetch_assoc();
            $configuredAlertEmail = trim((string)($row['setting_value'] ?? ''));
            if ($configuredAlertEmail !== '') {
                $emails[] = $configuredAlertEmail;
            }
        }

        $normalized = [];
        foreach ($emails as $email) {
            $cleanEmail = strtolower(trim((string)$email));
            if ($cleanEmail !== '' && filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                $normalized[$cleanEmail] = true;
            }
        }

        return array_keys($normalized);
    }
    
    /**
     * Send Expiry Warning
     */
    public function sendExpiryWarning($products, $days = 30) {
        if (empty($products)) return false;
        
        $recipients = $this->getSystemAlertRecipients();
        if (empty($recipients)) return false;
        
        try {
            $this->mailer->Subject = '📅 Product Expiry Warning - Calloway Pharmacy';
            
            $body = $this->getEmailTemplate('expiry_warning', [
                'products' => $products,
                'days' => $days,
                'count' => count($products)
            ]);
            
            $this->mailer->Body = $body;
            
            foreach ($recipients as $email) {
                $this->mailer->addAddress($email);
            }
            
            $this->mailer->send();
            $this->mailer->clearAddresses();
            
            return true;
        } catch (Exception $e) {
            error_log("Expiry warning email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send Daily Sales Summary
     */
    public function sendDailySummary($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $recipients = $this->getSystemAlertRecipients();
        if (empty($recipients)) return false;
        
        // Get sales data
        $query = "SELECT 
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(total), 0) as total_sales,
                    AVG(total) as avg_transaction
                  FROM sales 
                  WHERE DATE(created_at) = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $salesData = $result->fetch_assoc();
        
        // Get top products
        $productNameExpr = $this->columnExists('products', 'name') ? 'p.name' : 'p.product_name';
        $query = "SELECT {$productNameExpr} AS product_name, SUM(si.quantity) as qty
              FROM sale_items si
              JOIN products p ON si.product_id = p.product_id
              JOIN sales s ON si.sale_id = s.sale_id
              WHERE DATE(s.created_at) = ?
              GROUP BY si.product_id, {$productNameExpr}
              ORDER BY qty DESC
              LIMIT 5";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $topProducts = [];
        while ($row = $result->fetch_assoc()) {
            $topProducts[] = $row;
        }
        
        try {
            $this->mailer->Subject = '📊 Daily Sales Summary - ' . date('M d, Y', strtotime($date));
            
            $body = $this->getEmailTemplate('daily_summary', [
                'date' => $date,
                'salesData' => $salesData,
                'topProducts' => $topProducts
            ]);
            
            $this->mailer->Body = $body;
            
            foreach ($recipients as $email) {
                $this->mailer->addAddress($email);
            }
            
            $this->mailer->send();
            $this->mailer->clearAddresses();
            
            return true;
        } catch (Exception $e) {
            error_log("Daily summary email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send Password Reset Email
     */
    public function sendPasswordReset($email, $username, $resetToken) {
        $this->lastError = '';
        $resetLink = $this->buildAppUrl('reset_password.php?token=' . urlencode($resetToken));
        $body = $this->getEmailTemplate('password_reset', [
            'username' => $username,
            'resetLink' => $resetLink
        ]);

        $this->mailer->Subject = '🔐 Password Reset Request - Calloway Pharmacy';
        $this->mailer->Body = $body;

        try {
            $this->mailer->addAddress($email, $username);
            
            $this->mailer->send();
            $this->mailer->clearAddresses();
            
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Password reset email primary send error: " . $e->getMessage());

            if (stripos((string) $this->mailer->Host, 'gmail.com') !== false) {
                try {
                    $this->mailer->clearAddresses();

                    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $this->mailer->SMTPAutoTLS = false;
                    $this->mailer->Port = 465;

                    $this->mailer->addAddress($email, $username);
                    $this->mailer->send();
                    $this->mailer->clearAddresses();
                    return true;
                } catch (Exception $fallbackError) {
                    $this->lastError = $fallbackError->getMessage();
                    error_log("Password reset email fallback send error: " . $fallbackError->getMessage());
                }
            }

            return false;
        }
    }
    
    /**
     * Send Welcome Email
     */
    public function sendWelcomeEmail($email, $username, $fullName, $tempPassword) {
        try {
            $this->mailer->Subject = '👋 Welcome to Calloway Pharmacy';
            
            $body = $this->getEmailTemplate('welcome', [
                'username' => $username,
                'fullName' => $fullName,
                'tempPassword' => $tempPassword
            ]);
            
            $this->mailer->Body = $body;
            $this->mailer->addAddress($email, $fullName);
            
            $this->mailer->send();
            $this->mailer->clearAddresses();
            
            return true;
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get admin emails
     */
    private function getAdminEmails() {
        $emails = [];

        try {
            $query = null;

            if ($this->columnExists('users', 'role')) {
                $query = "SELECT email FROM users WHERE role = 'Admin' AND email IS NOT NULL AND email != ''";
            } elseif ($this->columnExists('users', 'role_name')) {
                $query = "SELECT email FROM users WHERE LOWER(role_name) IN ('admin', 'administrator') AND email IS NOT NULL AND email != ''";
            } elseif ($this->columnExists('users', 'role_id') && $this->tableExists('roles') && $this->columnExists('roles', 'role_name')) {
                $query = "SELECT u.email
                          FROM users u
                          INNER JOIN roles r ON r.role_id = u.role_id
                          WHERE LOWER(r.role_name) IN ('admin', 'administrator')
                          AND u.email IS NOT NULL AND u.email != ''";
            }

            if ($query !== null) {
                $result = $this->conn->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $emails[] = $row['email'];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Admin email lookup error: ' . $e->getMessage());
        }

        return $emails;
    }

    private function tableExists($table) {
        $stmt = $this->conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result && $result->num_rows > 0;
    }

    private function columnExists($table, $column) {
        $stmt = $this->conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result && $result->num_rows > 0;
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($type, $data) {
        $template = $this->getBaseTemplate();
        
        switch ($type) {
            case 'low_stock':
                $content = $this->getLowStockContent($data);
                break;
            case 'expiry_warning':
                $content = $this->getExpiryWarningContent($data);
                break;
            case 'daily_summary':
                $content = $this->getDailySummaryContent($data);
                break;
            case 'password_reset':
                $content = $this->getPasswordResetContent($data);
                break;
            case 'welcome':
                $content = $this->getWelcomeContent($data);
                break;
            case 'test_email':
                $content = $this->getTestEmailContent($data);
                break;
            default:
                $content = '';
        }
        
        return str_replace('{{CONTENT}}', $content, $template);
    }
    
    private function getBaseTemplate() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #2563eb; color: white; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
                .alert { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 Calloway Pharmacy</h1>
                </div>
                <div class="content">
                    {{CONTENT}}
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Calloway Pharmacy. All rights reserved.</p>
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getLowStockContent($data) {
        $html = '<h2>⚠️ Low Stock Alert</h2>';
        $html .= '<div class="alert">';
        $html .= '<p><strong>' . $data['count'] . ' product(s)</strong> are running low on stock.</p>';
        $html .= '<p>Please order from suppliers as soon as possible to avoid stockouts.</p>';
        $html .= '</div>';
        
        $html .= '<table>';
        $html .= '<tr><th>Product Name</th><th>Current Stock</th><th>Reorder Point</th></tr>';
        
        foreach ($data['products'] as $product) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($product['product_name']) . '</td>';
            $html .= '<td>' . $product['stock_quantity'] . '</td>';
            $html .= '<td>' . ($product['reorder_level'] ?? 20) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        $html .= '<p>Reminder: Kindly order these products from suppliers as soon as possible.</p>';
        
        return $html;
    }
    
    private function getExpiryWarningContent($data) {
        $html = '<h2>📅 Product Expiry Warning</h2>';
        $html .= '<div class="warning">';
        $html .= '<p><strong>' . $data['count'] . ' product(s)</strong> will expire in the next ' . $data['days'] . ' days.</p>';
        $html .= '</div>';
        
        $html .= '<table>';
        $html .= '<tr><th>Product Name</th><th>Batch Number</th><th>Expiry Date</th><th>Stock</th></tr>';
        
        foreach ($data['products'] as $product) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($product['product_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($product['batch_number'] ?? 'N/A') . '</td>';
            $html .= '<td>' . date('M d, Y', strtotime($product['expiry_date'])) . '</td>';
            $html .= '<td>' . $product['stock_quantity'] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        $html .= '<p>Please review these products and take appropriate action.</p>';
        
        return $html;
    }
    
    private function getDailySummaryContent($data) {
        $html = '<h2>📊 Daily Sales Summary</h2>';
        $html .= '<p>Here\'s your sales summary for <strong>' . date('F d, Y', strtotime($data['date'])) . '</strong></p>';
        
        $html .= '<table>';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        $html .= '<tr><td>Total Transactions</td><td>' . $data['salesData']['transaction_count'] . '</td></tr>';
        $html .= '<tr><td>Total Sales</td><td>₱' . number_format($data['salesData']['total_sales'], 2) . '</td></tr>';
        $html .= '<tr><td>Average Transaction</td><td>₱' . number_format($data['salesData']['avg_transaction'], 2) . '</td></tr>';
        $html .= '</table>';
        
        if (!empty($data['topProducts'])) {
            $html .= '<h3>🏆 Top Products</h3>';
            $html .= '<table>';
            $html .= '<tr><th>Product</th><th>Units Sold</th></tr>';
            
            foreach ($data['topProducts'] as $product) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($product['product_name']) . '</td>';
                $html .= '<td>' . $product['qty'] . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table>';
        }
        
        return $html;
    }
    
    private function getPasswordResetContent($data) {
        $html = '<h2>🔐 Password Reset Request</h2>';
        $html .= '<p>Hello <strong>' . htmlspecialchars($data['username']) . '</strong>,</p>';
        $html .= '<p>We received a request to reset your password. Click the button below to create a new password:</p>';
        $html .= '<p style="text-align:center; margin:18px 0;">';
        $html .= '<a href="' . htmlspecialchars($data['resetLink']) . '" style="display:inline-block; padding:12px 24px; background-color:#1d4ed8; color:#ffffff !important; text-decoration:none; font-weight:700; border-radius:8px; border:1px solid #1d4ed8;">Reset Password</a>';
        $html .= '</p>';
        $html .= '<p style="font-size:13px; color:#475569; margin-top:8px;">If the button does not work, copy and paste this link into your browser:</p>';
        $html .= '<p style="font-size:13px; word-break:break-all;"><a href="' . htmlspecialchars($data['resetLink']) . '" style="color:#1d4ed8;">' . htmlspecialchars($data['resetLink']) . '</a></p>';
        $html .= '<p>If you didn\'t request this, you can safely ignore this email.</p>';
        $html .= '<p>This link will expire in 1 hour.</p>';
        
        return $html;
    }
    
    private function getWelcomeContent($data) {
        $html = '<h2>👋 Welcome to Calloway Pharmacy!</h2>';
        $html .= '<p>Hello <strong>' . htmlspecialchars($data['fullName']) . '</strong>,</p>';
        $html .= '<p>Your account has been created successfully! Here are your login credentials:</p>';
        $html .= '<div class="warning">';
        $html .= '<p><strong>Username:</strong> ' . htmlspecialchars($data['username']) . '<br>';
        $html .= '<strong>Temporary Password:</strong> ' . htmlspecialchars($data['tempPassword']) . '</p>';
        $html .= '</div>';
        $html .= '<p>Please change your password after your first login for security purposes.</p>';
        $html .= '<p style="text-align: center;"><a href="' . htmlspecialchars($this->buildAppUrl('login.php')) . '" class="button">Login Now</a></p>';
        
        return $html;
    }

    private function getTestEmailContent($data) {
        $html = '<h2>✅ SMTP Test Successful</h2>';
        $html .= '<p>This confirms your Calloway Pharmacy email settings are working.</p>';
        $html .= '<table>';
        $html .= '<tr><th>Detail</th><th>Value</th></tr>';
        $html .= '<tr><td>Recipient</td><td>' . htmlspecialchars($data['toEmail']) . '</td></tr>';
        $html .= '<tr><td>Sent At</td><td>' . htmlspecialchars($data['timeSent']) . '</td></tr>';
        $html .= '</table>';
        return $html;
    }

    /**
     * Send a custom HTML email to any recipient
     */
    public function sendCustomEmail($toEmail, $subject, $htmlBody) {
        try {
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->addAddress($toEmail);
            $this->mailer->send();
            $this->mailer->clearAddresses();
            return true;
        } catch (Exception $e) {
            $details = trim((string) ($this->mailer->ErrorInfo ?? ''));
            $this->lastError = $details !== '' ? $details : $e->getMessage();
            error_log("Custom email error: " . $this->lastError);
            return false;
        }
    }
}
