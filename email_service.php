<?php
/**
 * Email Notification Service
 * Handles all email notifications using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php'; // For PHPMailer via Composer
require_once 'db_connection.php';

class EmailService {
    private $conn;
    private $mailer;
    private $fromEmail;
    private $fromName;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->mailer = new PHPMailer(true);
        
        // Load SMTP settings from database
        $this->loadSettings();
        
        // Configure PHPMailer
        $this->configureSMTP();
    }
    
    private function loadSettings() {
        // Try to load from settings table
        $query = "SELECT * FROM settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name') LIMIT 6";
        $result = $this->conn->query($query);
        
        $settings = [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => 'noreply@callowaypharmacy.com',
            'smtp_from_name' => 'Calloway Pharmacy'
        ];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        $this->fromEmail = $settings['smtp_from_email'];
        $this->fromName = $settings['smtp_from_name'];
        
        return $settings;
    }
    
    private function configureSMTP() {
        $settings = $this->loadSettings();
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $settings['smtp_host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $settings['smtp_username'];
            $this->mailer->Password = $settings['smtp_password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $settings['smtp_port'];
            
            // Set from address
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            
            // HTML format
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
        }
    }
    
    /**
     * Send Low Stock Alert
     */
    public function sendLowStockAlert($products) {
        if (empty($products)) return false;
        
        // Get admin emails
        $adminEmails = $this->getAdminEmails();
        if (empty($adminEmails)) return false;
        
        try {
            $this->mailer->Subject = '⚠️ Low Stock Alert - Calloway Pharmacy';
            
            $body = $this->getEmailTemplate('low_stock', [
                'products' => $products,
                'count' => count($products)
            ]);
            
            $this->mailer->Body = $body;
            
            foreach ($adminEmails as $email) {
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
     * Send Expiry Warning
     */
    public function sendExpiryWarning($products, $days = 30) {
        if (empty($products)) return false;
        
        $adminEmails = $this->getAdminEmails();
        if (empty($adminEmails)) return false;
        
        try {
            $this->mailer->Subject = '📅 Product Expiry Warning - Calloway Pharmacy';
            
            $body = $this->getEmailTemplate('expiry_warning', [
                'products' => $products,
                'days' => $days,
                'count' => count($products)
            ]);
            
            $this->mailer->Body = $body;
            
            foreach ($adminEmails as $email) {
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
        
        $adminEmails = $this->getAdminEmails();
        if (empty($adminEmails)) return false;
        
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
        $query = "SELECT p.product_name, SUM(si.quantity) as qty
                  FROM sale_items si
                  JOIN products p ON si.product_id = p.product_id
                  JOIN sales s ON si.sale_id = s.sale_id
                  WHERE DATE(s.created_at) = ?
                  GROUP BY si.product_id
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
            
            foreach ($adminEmails as $email) {
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
        try {
            $this->mailer->Subject = '🔐 Password Reset Request - Calloway Pharmacy';
            
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $resetToken;
            
            $body = $this->getEmailTemplate('password_reset', [
                'username' => $username,
                'resetLink' => $resetLink
            ]);
            
            $this->mailer->Body = $body;
            $this->mailer->addAddress($email, $username);
            
            $this->mailer->send();
            $this->mailer->clearAddresses();
            
            return true;
        } catch (Exception $e) {
            error_log("Password reset email error: " . $e->getMessage());
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
        $query = "SELECT email FROM users WHERE role = 'Admin' AND email IS NOT NULL AND email != ''";
        $result = $this->conn->query($query);
        
        $emails = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $emails[] = $row['email'];
            }
        }
        
        return $emails;
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
        $html .= '<p><strong>' . $data['count'] . ' product(s)</strong> are running low on stock and need to be restocked.</p>';
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
        $html .= '<p>Please reorder these products as soon as possible to avoid stockouts.</p>';
        
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
        $html .= '<p style="text-align: center;"><a href="' . $data['resetLink'] . '" class="button">Reset Password</a></p>';
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
        $html .= '<p style="text-align: center;"><a href="http://' . $_SERVER['HTTP_HOST'] . '/login.php" class="button">Login Now</a></p>';
        
        return $html;
    }
}
