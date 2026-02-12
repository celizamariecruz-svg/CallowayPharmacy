<?php
/**
 * Loyalty & QR Code System
 * Customer loyalty rewards and QR code scanning
 */

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('pos.access')) {
    die('<h1>Access Denied</h1><p>You do not have permission to access the loyalty system.</p>');
}

$currentUser = $auth->getCurrentUser();
$page_title = 'Loyalty & QR';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loyalty-container {
            max-width: 1200px;
            margin: 100px auto 2rem;
            padding: 2rem;
        }
        
        .loyalty-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .loyalty-header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin: 0 0 1rem;
        }
        
        .loyalty-header p {
            color: var(--text-color);
            opacity: 0.8;
            font-size: 1.1rem;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--input-border);
            justify-content: center;
        }
        
        .tab {
            padding: 1rem 2rem;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-color);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
        }
        
        [data-theme="dark"] .action-card {
            background: #1e293b;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        .action-card .icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        
        .action-card h2 {
            color: var(--primary-color);
            margin: 0 0 1rem;
        }
        
        .action-card p {
            color: var(--text-color);
            opacity: 0.8;
            margin-bottom: 1.5rem;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .qr-display {
            max-width: 400px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        [data-theme="dark"] .qr-display {
            background: #1e293b;
        }
        
        .qr-code {
            width: 300px;
            height: 300px;
            margin: 1rem auto;
            background: white;
            border: 3px solid var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10rem;
        }
        
        .qr-info {
            margin-top: 1rem;
        }
        
        .qr-info h3 {
            color: var(--primary-color);
            margin: 0 0 0.5rem;
        }
        
        .qr-info p {
            color: var(--text-color);
            margin: 0.25rem 0;
        }
        
        .scanner-area {
            max-width: 600px;
            margin: 2rem auto;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        [data-theme="dark"] .scanner-area {
            background: #1e293b;
        }
        
        .scanner-icon {
            font-size: 8rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .scanner-input {
            width: 100%;
            max-width: 400px;
            padding: 1rem;
            margin: 1rem auto;
            border: 3px solid var(--primary-color);
            border-radius: 8px;
            font-size: 1.2rem;
            text-align: center;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .scanner-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(10, 116, 218, 0.3);
        }
        
        .customer-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        [data-theme="dark"] .customer-table {
            background: #1e293b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead {
            background: var(--primary-color);
            color: white;
        }
        
        table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        table td {
            padding: 1rem;
            border-bottom: 1px solid var(--input-border);
        }
        
        table tbody tr:hover {
            background: var(--dropdown-hover);
        }
        
        .points-badge {
            padding: 0.5rem 1rem;
            background: var(--secondary-color);
            color: white;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
        
        [data-theme="dark"] .modal-content {
            background: #1e293b;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--primary-color);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-color);
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
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .toast {
            position: fixed;
            top: 100px;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 10000;
            animation: slideIn 0.3s;
        }
        
        .toast.active {
            display: flex;
        }
        
        .toast.success {
            border-left: 4px solid #28a745;
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
        
        @media (max-width: 768px) {
            .action-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="loyalty-container">
        <div class="loyalty-header">
            <h1>🎁 Loyalty & QR System</h1>
            <p>Reward your customers with points and manage QR codes</p>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('generate')">Generate QR</button>
            <button class="tab" onclick="switchTab('scan')">Scan QR</button>
            <button class="tab" onclick="switchTab('customers')">Loyalty Members</button>
        </div>
        
        <!-- Generate QR Tab -->
        <div class="tab-content active" id="generateTab">
            <div class="action-cards">
                <div class="action-card">
                    <div class="icon">👤</div>
                    <h2>Customer QR Code</h2>
                    <p>Generate a unique QR code for a customer to earn loyalty points</p>
                    <button class="btn btn-primary" onclick="generateCustomerQR()">Generate QR</button>
                </div>
                
                <div class="action-card">
                    <div class="icon">🎫</div>
                    <h2>Promo QR Code</h2>
                    <p>Create a promotional QR code for special discounts and offers</p>
                    <button class="btn btn-secondary" onclick="generatePromoQR()">Create Promo</button>
                </div>
            </div>
            
            <div class="qr-display" id="qrDisplay" style="display: none;">
                <h3>Generated QR Code</h3>
                <div class="qr-code" id="qrCodeDisplay">
                    <span style="font-size: 3rem;">Loading...</span>
                </div>
                <div class="qr-info">
                    <h3 id="qrTitle">Customer QR</h3>
                    <p><strong>ID:</strong> <span id="qrId">N/A</span></p>
                    <p><strong>Type:</strong> <span id="qrType">N/A</span></p>
                    <p><strong>Status:</strong> <span id="qrStatus">Active</span></p>
                </div>
                <button class="btn btn-primary" onclick="downloadQR()">💾 Download QR</button>
                <button class="btn btn-secondary" onclick="printQR()">🖨️ Print QR</button>
            </div>
        </div>
        
        <!-- Scan QR Tab -->
        <div class="tab-content" id="scanTab">
            <div class="scanner-area">
                <div class="scanner-icon">📷</div>
                <h2>Scan Customer QR Code</h2>
                <p>Scan or enter the customer's QR code to award loyalty points</p>
                <input type="text" class="scanner-input" id="qrInput" placeholder="Scan QR code here..." autofocus>
                <button class="btn btn-primary" onclick="processQRScan()">Process Scan</button>
            </div>
        </div>
        
        <!-- Customers Tab -->
        <div class="tab-content" id="customersTab">
            <div style="margin-bottom: 2rem; text-align: right;">
                <button class="btn btn-primary" onclick="openAddCustomerModal()">➕ Add Loyalty Member</button>
            </div>
            
            <div class="customer-table">
                <table>
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Points</th>
                            <th>Member Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                Loading loyalty members...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Customer Modal -->
    <div class="modal" id="customerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Loyalty Member</h2>
                <button class="close-modal" onclick="closeCustomerModal()">&times;</button>
            </div>
            <form id="customerForm" onsubmit="saveCustomer(event)">
                <div class="form-group">
                    <label for="customerName">Full Name *</label>
                    <input type="text" id="customerName" required>
                </div>
                
                <div class="form-group">
                    <label for="customerEmail">Email</label>
                    <input type="email" id="customerEmail">
                </div>
                
                <div class="form-group">
                    <label for="customerPhone">Phone Number *</label>
                    <input type="tel" id="customerPhone" required>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save Member</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>
    
    <script src="theme.js"></script>
    <script>
        let currentTab = 'generate';
        let loyaltyMembers = [];
        
        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadLoyaltyMembers();
        });
        
        // Switch tabs
        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            
            if (tab === 'scan') {
                setTimeout(() => document.getElementById('qrInput').focus(), 100);
            }
        }
        
        // Generate customer QR
        function generateCustomerQR() {
            const customerId = 'CUST-' + Date.now();
            const qrCode = generateQRCode(customerId);
            
            document.getElementById('qrCodeDisplay').innerHTML = qrCode;
            document.getElementById('qrTitle').textContent = 'Customer Loyalty QR';
            document.getElementById('qrId').textContent = customerId;
            document.getElementById('qrType').textContent = 'Customer';
            document.getElementById('qrStatus').textContent = 'Active';
            document.getElementById('qrDisplay').style.display = 'block';
            
            showToast('Customer QR code generated successfully', 'success');
        }
        
        // Generate promo QR
        function generatePromoQR() {
            const promoId = 'PROMO-' + Date.now();
            const qrCode = generateQRCode(promoId);
            
            document.getElementById('qrCodeDisplay').innerHTML = qrCode;
            document.getElementById('qrTitle').textContent = 'Promotional QR';
            document.getElementById('qrId').textContent = promoId;
            document.getElementById('qrType').textContent = 'Promotion';
            document.getElementById('qrStatus').textContent = 'Active';
            document.getElementById('qrDisplay').style.display = 'block';
            
            showToast('Promotional QR code generated successfully', 'success');
        }
        
        // Generate QR code (simplified - using emoji as placeholder)
        function generateQRCode(data) {
            // In production, use a QR code library like qrcode.js
            return `<div style="font-size: 4rem; padding: 2rem;">
                        <div>▓▓░░▓▓░░▓▓</div>
                        <div>░░▓▓░░▓▓░░</div>
                        <div>▓▓░░▓▓░░▓▓</div>
                        <div>░░▓▓░░▓▓░░</div>
                        <div>▓▓░░▓▓░░▓▓</div>
                    </div>
                    <small style="font-size: 0.8rem; word-break: break-all;">${data}</small>`;
        }
        
        // Download QR
        function downloadQR() {
            showToast('QR code download feature coming soon', 'success');
        }
        
        // Print QR
        function printQR() {
            window.print();
        }
        
        // Process QR scan
        function processQRScan() {
            const qrCode = document.getElementById('qrInput').value.trim();
            
            if (!qrCode) {
                alert('Please scan or enter a QR code');
                return;
            }
            
            // Simulate processing
            if (qrCode.startsWith('CUST-')) {
                showToast(`✓ Customer QR scanned! Awarded 10 points`, 'success');
                document.getElementById('qrInput').value = '';
            } else if (qrCode.startsWith('PROMO-')) {
                showToast(`✓ Promo QR scanned! Applied discount`, 'success');
                document.getElementById('qrInput').value = '';
            } else {
                showToast('Invalid QR code', 'error');
            }
        }
        
        // Load loyalty members
        function loadLoyaltyMembers() {
            // Sample data (in production, fetch from database)
            loyaltyMembers = [
                { id: 1, name: 'John Doe', email: 'john@example.com', phone: '0912-345-6789', points: 150, memberSince: '2024-01-15' },
                { id: 2, name: 'Jane Smith', email: 'jane@example.com', phone: '0923-456-7890', points: 280, memberSince: '2024-02-20' },
                { id: 3, name: 'Bob Johnson', email: 'bob@example.com', phone: '0934-567-8901', points: 95, memberSince: '2024-03-10' }
            ];
            
            renderCustomersTable();
        }
        
        // Render customers table
        function renderCustomersTable() {
            const tbody = document.getElementById('customersTableBody');
            
            if (loyaltyMembers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No loyalty members yet</td></tr>';
                return;
            }
            
            tbody.innerHTML = loyaltyMembers.map(customer => `
                <tr>
                    <td><strong>${escapeHtml(customer.name)}</strong></td>
                    <td>${escapeHtml(customer.email)}</td>
                    <td>${escapeHtml(customer.phone)}</td>
                    <td><span class="points-badge">${customer.points} pts</span></td>
                    <td>${formatDate(customer.memberSince)}</td>
                    <td>
                        <button class="btn btn-primary btn-small" onclick="viewCustomer(${customer.id})">View</button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Open add customer modal
        function openAddCustomerModal() {
            document.getElementById('customerModal').classList.add('active');
        }
        
        // Close customer modal
        function closeCustomerModal() {
            document.getElementById('customerModal').classList.remove('active');
            document.getElementById('customerForm').reset();
        }
        
        // Save customer
        function saveCustomer(event) {
            event.preventDefault();
            
            const newCustomer = {
                id: loyaltyMembers.length + 1,
                name: document.getElementById('customerName').value,
                email: document.getElementById('customerEmail').value,
                phone: document.getElementById('customerPhone').value,
                points: 0,
                memberSince: new Date().toISOString().split('T')[0]
            };
            
            loyaltyMembers.push(newCustomer);
            renderCustomersTable();
            closeCustomerModal();
            showToast('Loyalty member added successfully', 'success');
        }
        
        // View customer
        function viewCustomer(id) {
            const customer = loyaltyMembers.find(c => c.id === id);
            if (customer) {
                alert(`Customer Details:\n\nName: ${customer.name}\nEmail: ${customer.email}\nPhone: ${customer.phone}\nPoints: ${customer.points}\nMember Since: ${customer.memberSince}`);
            }
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
        
        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        
        // Enter key to scan QR
        document.getElementById('qrInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                processQRScan();
            }
        });
        
        // Additional Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            // F3 - Focus Search
            if (e.key === 'F3') {
                e.preventDefault();
                document.getElementById('customerSearchInput')?.focus();
            }
        });
    </script>
    <script src="shared-polish.js"></script>
</body>
</html>
