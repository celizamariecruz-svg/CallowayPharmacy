<?php
/**
 * Receipt PDF Generator
 * Generates professional PDF receipts for transactions
 */

require_once 'vendor/autoload.php'; // For TCPDF via Composer
require_once 'db_connection.php';
require_once 'Auth.php';

class ReceiptGenerator {
    private $conn;
    private $pdf;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generate receipt for a sale
     */
    public function generateReceipt($saleId, $outputMode = 'I') {
        // Get sale data
        $saleData = $this->getSaleData($saleId);
        
        if (!$saleData) {
            return false;
        }
        
        // Create PDF
        $this->pdf = new TCPDF('P', 'mm', array(80, 200), true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('Calloway Pharmacy');
        $this->pdf->SetAuthor('Calloway Pharmacy');
        $this->pdf->SetTitle('Receipt #' . $saleData['sale_reference']);
        
        // Remove default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        
        // Set margins (small for receipt)
        $this->pdf->SetMargins(5, 5, 5);
        $this->pdf->SetAutoPageBreak(TRUE, 5);
        
        // Add a page
        $this->pdf->AddPage();
        
        // Set font
        $this->pdf->SetFont('helvetica', '', 9);
        
        // Build receipt content
        $this->buildReceiptContent($saleData);
        
        // Output PDF
        $filename = 'receipt_' . $saleData['sale_reference'] . '.pdf';
        
        // I = Inline (browser), D = Download, F = File, S = String
        return $this->pdf->Output($filename, $outputMode);
    }
    
    private function buildReceiptContent($data) {
        // Store header
        $html = '<div style="text-align: center;">';
        $html .= '<h2 style="margin: 0;">CALLOWAY PHARMACY</h2>';
        $html .= '<p style="font-size: 8px; margin: 2px 0;">Your Health, Our Priority</p>';
        $html .= '<p style="font-size: 8px; margin: 2px 0;">123 Main Street, City</p>';
        $html .= '<p style="font-size: 8px; margin: 2px 0;">Tel: (123) 456-7890</p>';
        $html .= '<hr style="border: 1px dashed #000;">';
        $html .= '</div>';
        
        // Receipt details
        $html .= '<table style="width: 100%; font-size: 8px; margin: 5px 0;">';
        $html .= '<tr><td><strong>Receipt #:</strong></td><td>' . htmlspecialchars($data['sale_reference']) . '</td></tr>';
        $html .= '<tr><td><strong>Date:</strong></td><td>' . date('M d, Y g:i A', strtotime($data['created_at'])) . '</td></tr>';
        $html .= '<tr><td><strong>Cashier:</strong></td><td>' . htmlspecialchars($data['cashier']) . '</td></tr>';
        $html .= '</table>';
        
        $html .= '<hr style="border: 1px dashed #000;">';
        
        // Items table
        $html .= '<table style="width: 100%; font-size: 8px; margin: 5px 0;">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th style="text-align: left; border-bottom: 1px solid #000;">Item</th>';
        $html .= '<th style="text-align: center; border-bottom: 1px solid #000;">Qty</th>';
        $html .= '<th style="text-align: right; border-bottom: 1px solid #000;">Price</th>';
        $html .= '<th style="text-align: right; border-bottom: 1px solid #000;">Total</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($data['items'] as $item) {
            $html .= '<tr>';
            $html .= '<td style="padding: 2px 0;">' . htmlspecialchars($item['name']) . '</td>';
            $html .= '<td style="text-align: center; padding: 2px 0;">' . $item['quantity'] . '</td>';
            $html .= '<td style="text-align: right; padding: 2px 0;">₱' . number_format($item['unit_price'], 2) . '</td>';
            $html .= '<td style="text-align: right; padding: 2px 0;">₱' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        $html .= '<hr style="border: 1px dashed #000;">';
        
        // Totals
        $html .= '<table style="width: 100%; font-size: 9px; margin: 5px 0;">';
        $html .= '<tr><td><strong>TOTAL:</strong></td><td style="text-align: right;"><strong>₱' . number_format($data['total'], 2) . '</strong></td></tr>';
        $html .= '<tr><td>Payment Method:</td><td style="text-align: right;">' . htmlspecialchars($data['payment_method']) . '</td></tr>';
        $html .= '<tr><td>Amount Paid:</td><td style="text-align: right;">₱' . number_format($data['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><td>Change:</td><td style="text-align: right;">₱' . number_format($data['change_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        
        $html .= '<hr style="border: 1px dashed #000;">';
        
        // QR Code for verification
        $html .= '<div style="text-align: center; margin: 10px 0;">';
        $qrData = 'RECEIPT:' . $data['sale_reference'] . '|TOTAL:' . $data['total'] . '|DATE:' . $data['created_at'];
        
        // Add QR code (TCPDF built-in)
        $this->pdf->write2DBarcode($qrData, 'QRCODE,L', 25, '', 30, 30, '', 'N');
        $html .= '<br><br><br><br>'; // Space for QR code
        $html .= '<p style="font-size: 7px;">Scan to verify</p>';
        $html .= '</div>';
        
        // Footer
        $html .= '<div style="text-align: center; font-size: 7px; margin-top: 10px;">';
        $html .= '<p style="margin: 2px 0;">Thank you for your purchase!</p>';
        $html .= '<p style="margin: 2px 0;">For inquiries: info@callowaypharmacy.com</p>';
        $html .= '<p style="margin: 2px 0;">This serves as your official receipt.</p>';
        $html .= '</div>';
        
        $this->pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function getSaleData($saleId) {
        // Get sale header
        $query = "SELECT * FROM sales WHERE sale_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $saleData = $result->fetch_assoc();
        
        // Get sale items
        $query = "SELECT * FROM sale_items WHERE sale_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $saleData['items'] = $items;
        
        return $saleData;
    }
    
    /**
     * Email receipt
     */
    public function emailReceipt($saleId, $email) {
        // Generate PDF as string
        $pdfContent = $this->generateReceipt($saleId, 'S');
        
        if (!$pdfContent) {
            return false;
        }
        
        // Use email service to send
        require_once 'email_service.php';
        
        try {
            $emailService = new EmailService($this->conn);
            $saleData = $this->getSaleData($saleId);

            return $emailService->sendReceiptEmail(
                $email,
                $saleData['sale_reference'],
                $saleData['total'],
                $pdfContent,
                'receipt_' . $saleData['sale_reference'] . '.pdf'
            );
        } catch (Exception $e) {
            error_log("Email receipt error: " . $e->getMessage());
            return false;
        }
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['sale_id'])) {
    require_once 'Auth.php';
    
    $auth = new Auth($conn);
    $auth->requireAuth();
    
    $saleId = intval($_GET['sale_id']);
    $action = $_GET['action'] ?? 'view';
    
    $generator = new ReceiptGenerator($conn);
    
    if ($action === 'download') {
        $generator->generateReceipt($saleId, 'D');
    } elseif ($action === 'email' && isset($_GET['email'])) {
        $email = $_GET['email'];
        $result = $generator->emailReceipt($saleId, $email);
        echo json_encode(['success' => $result]);
    } else {
        $generator->generateReceipt($saleId, 'I');
    }
}
