<?php
/**
 * Loyalty & QR Code System
 * Customer loyalty rewards and QR code scanning
 * Accessible by both customers and staff
 */

session_start();
require_once 'db_connection.php';

// Allow customers AND staff to access this page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION;
$isCustomer = (($_SESSION['role_name'] ?? '') === 'customer');
$isStaff = !$isCustomer;
$page_title = 'Loyalty & QR';
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
    <link rel="stylesheet" href="design-system.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" onerror="console.warn('QRCode CDN failed to load, using fallback');"></script>
    <script src="custom-modal.js?v=2"></script>
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
        
        .form-group input,
        .form-group select {
            width: 100%;
            box-sizing: border-box;
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
            <p><?php echo $isCustomer ? 'Earn points with every purchase! Scan your QR codes below.' : 'Reward your customers with points and manage QR codes'; ?></p>
        </div>

        <!-- Points Display Card -->
        <div style="max-width:500px; margin:0 auto 2rem; padding:2rem; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius:16px; color:white; text-align:center; box-shadow:0 8px 24px rgba(102,126,234,0.3);">
            <div style="font-size:0.9rem; opacity:0.9; margin-bottom:0.5rem;">Your Loyalty Points</div>
            <div id="myPointsDisplay" style="font-size:3rem; font-weight:800; margin-bottom:0.3rem;">...</div>
            <div style="font-size:0.85rem; opacity:0.8;">1 point = ₱1 discount on your next order</div>
            <div id="memberSinceDisplay" style="font-size:0.8rem; opacity:0.7; margin-top:0.5rem;"></div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('scan', this)">📷 Scan QR Code</button>
            <button class="tab" onclick="switchTab('myqr', this)">🎫 My QR Codes</button>
            <button class="tab" onclick="switchTab('history', this)">📊 Points History</button>
            <?php if ($isStaff): ?>
            <button class="tab" onclick="switchTab('customers', this)">👥 All Members</button>
            <?php endif; ?>
        </div>
        
        <!-- Scan QR Tab -->
        <div class="tab-content active" id="scanTab">
            <div class="scanner-area">
                <div class="scanner-icon">📷</div>
                <h2>Scan Reward QR Code</h2>
                <p>Enter or scan the QR code from your receipt to earn loyalty points</p>
                <input type="text" class="scanner-input" id="qrScanInput" placeholder="Paste or scan QR code here..." autofocus>
                <div style="display:flex; gap:0.75rem; justify-content:center; margin-top:1rem;">
                    <button class="btn btn-primary" onclick="redeemQRCode()" style="padding:0.85rem 2rem; font-size:1rem;">
                        <i class="fas fa-qrcode"></i> Redeem Points
                    </button>
                    <?php if ($isStaff): ?>
                    <button class="btn btn-secondary" onclick="openStaffRedeemModal(document.getElementById('qrScanInput').value.trim())" style="padding:0.85rem 1.5rem; font-size:1rem; background:#059669; color:#fff; border:none;">
                        <i class="fas fa-user-plus"></i> Redeem for Customer
                    </button>
                    <?php endif; ?>
                </div>
                <div id="scanResult" style="margin-top:1.5rem; display:none;"></div>
            </div>
        </div>
        
        <!-- My QR Codes Tab -->
        <div class="tab-content" id="myqrTab">
            <div style="max-width:700px; margin:0 auto;">
                <h3 style="text-align:center; margin-bottom:1.5rem; color:var(--text-color);">Your Reward QR Codes</h3>
                <p style="text-align:center; color:var(--text-light); margin-bottom:1.5rem; font-size:0.9rem;">These QR codes were generated from your purchases. Unredeemed codes can still be scanned for points.</p>
                <div id="myQrCodesList" style="display:flex; flex-direction:column; gap:1rem;">
                    <div style="text-align:center; padding:2rem; color:var(--text-light);">Loading...</div>
                </div>
            </div>
        </div>
        
        <!-- Points History Tab -->
        <div class="tab-content" id="historyTab">
            <div style="max-width:700px; margin:0 auto;">
                <h3 style="text-align:center; margin-bottom:1.5rem; color:var(--text-color);">Points History</h3>
                <div class="customer-table" id="pointsHistoryTable">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Points</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="pointsHistoryBody">
                            <tr><td colspan="4" style="text-align:center; padding:2rem;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($isStaff): ?>
        <!-- Customers Tab (Staff Only) -->
        <div class="tab-content" id="customersTab">
            <div style="margin-bottom:1.5rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <input type="text" id="memberSearchInput" placeholder="🔍 Search members by name, email, or phone..." oninput="filterMembersTable()" style="width:100%; box-sizing:border-box; padding:0.75rem 1rem; border:2px solid var(--input-border); border-radius:10px; font-size:0.95rem; background:var(--bg-color); color:var(--text-color); outline:none; transition:border-color 0.3s;">
                </div>
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
        <?php endif; ?>
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

    <?php if ($isStaff): ?>
    <!-- Staff QR Redemption Modal -->
    <div class="modal" id="staffRedeemModal">
        <div class="modal-content" style="max-width:550px;">
            <div class="modal-header">
                <h2>🎁 Award Points to Customer</h2>
                <button class="close-modal" onclick="closeStaffRedeemModal()">&times;</button>
            </div>
            <div id="staffRedeemQrInfo" style="padding:1rem; background:linear-gradient(135deg,#fef3c7,#fde68a); border-radius:12px; margin-bottom:1.5rem; text-align:center;">
                <div style="font-weight:700; color:#92400e;">Loading QR details...</div>
            </div>
            <div class="form-group">
                <label for="staffRedeemMember">Select Customer</label>
                <input type="text" id="staffRedeemSearch" placeholder="🔍 Search customer by name..." oninput="filterStaffRedeemDropdown()" style="width:100%; box-sizing:border-box; padding:0.65rem 1rem; border:2px solid var(--input-border); border-radius:8px; font-size:0.9rem; background:var(--bg-color); color:var(--text-color); margin-bottom:0.5rem; outline:none;">
                <select id="staffRedeemMember" style="width:100%; box-sizing:border-box; padding:0.75rem; border:2px solid var(--input-border); border-radius:8px; font-size:1rem; background:var(--bg-color); color:var(--text-color);">
                    <option value="">-- Select Loyalty Member --</option>
                </select>
            </div>
            <div style="text-align:center; margin:0.75rem 0; color:var(--text-light); font-size:0.85rem;">or</div>
            <button type="button" class="btn btn-secondary" onclick="closeStaffRedeemModal(); openAddCustomerModal();" style="width:100%; margin-bottom:1.5rem;">
                ➕ Create New Loyalty Member First
            </button>
            <div style="display:flex; gap:1rem;">
                <button type="button" class="btn btn-primary" id="staffRedeemBtn" onclick="staffRedeemQR()" style="flex:1; padding:0.85rem; font-size:1rem;">
                    ✅ Award Points
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeStaffRedeemModal()" style="flex:0.5;">Cancel</button>
            </div>
            <div id="staffRedeemResult" style="margin-top:1rem; display:none;"></div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="theme.js"></script>
    <script>
        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMyPoints();
            loadMyQRCodes();
            <?php if ($isStaff): ?>
            loadLoyaltyMembers();
            <?php endif; ?>
            
            // Auto-redeem if ?auto_redeem= param is present (from QR scan)
            const urlParams = new URLSearchParams(window.location.search);
            const autoRedeem = urlParams.get('auto_redeem');
            if (autoRedeem) {
                // Switch to scan tab
                const scanBtn = document.querySelector('[onclick*="switchTab(\'scan\'"]');
                if (scanBtn) {
                    switchTab('scan', scanBtn);
                }
                // Put the code into the input and auto-submit
                setTimeout(() => {
                    const input = document.getElementById('qrScanInput');
                    if (input) {
                        input.value = autoRedeem;
                        redeemQRCode();
                    }
                }, 400);
                // Clean URL
                window.history.replaceState({}, '', 'loyalty_qr.php');
            }

            // Employee scan: ?scan=CODE pre-fills the QR input for staff redemption
            const scanParam = urlParams.get('scan');
            if (scanParam) {
                const scanBtn = document.querySelector('[onclick*="switchTab(\'scan\'"]');
                if (scanBtn) switchTab('scan', scanBtn);
                setTimeout(() => {
                    const input = document.getElementById('qrScanInput');
                    if (input) input.value = scanParam;
                    <?php if ($isStaff): ?>
                    // Staff: show the "Redeem for Customer" modal
                    openStaffRedeemModal(scanParam);
                    <?php else: ?>
                    // Non-staff: just auto-redeem for self
                    redeemQRCode();
                    <?php endif; ?>
                }, 400);
                window.history.replaceState({}, '', 'loyalty_qr.php');
            }
        });
        
        // Switch tabs
        function switchTab(tab, btn) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            
            if (tab === 'scan') {
                setTimeout(() => document.getElementById('qrScanInput').focus(), 100);
            }
            if (tab === 'myqr') {
                loadMyQRCodes();
            }
            if (tab === 'history') {
                loadMyQRCodes(); // also loads points history
            }
        }

        // Load current user's points
        async function loadMyPoints() {
            try {
                const res = await fetch('reward_qr_api.php?action=get_my_points');
                const data = await res.json();
                if (data.success) {
                    document.getElementById('myPointsDisplay').textContent = data.points + ' pts';
                    if (data.member_since) {
                        document.getElementById('memberSinceDisplay').textContent = 'Member since: ' + new Date(data.member_since).toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
                    }
                } else {
                    document.getElementById('myPointsDisplay').textContent = '0 pts';
                    document.getElementById('memberSinceDisplay').textContent = data.message || 'Start earning by scanning QR codes!';
                }
            } catch (err) {
                document.getElementById('myPointsDisplay').textContent = '0 pts';
            }
        }

        // Redeem QR code
        async function redeemQRCode() {
            const input = document.getElementById('qrScanInput');
            const qrCode = input.value.trim();
            const resultDiv = document.getElementById('scanResult');
            
            if (!qrCode) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div style="padding:1rem; background:#fee2e2; border-radius:10px; color:#dc2626; font-weight:600;"><i class="fas fa-exclamation-circle"></i> Please enter or scan a QR code</div>';
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div style="padding:1rem; text-align:center;"><i class="fas fa-spinner fa-spin"></i> Processing...</div>';
            
            try {
                const res = await fetch('reward_qr_api.php?action=redeem_reward_qr', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ qr_code: qrCode })
                });
                const data = await res.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div style="padding:1.5rem; background:#dcfce7; border-radius:12px; text-align:center;">
                            <div style="font-size:3rem; margin-bottom:0.5rem;">🎉</div>
                            <div style="font-size:1.1rem; font-weight:700; color:#16a34a; margin-bottom:0.3rem;">+${data.points_earned} Point Earned!</div>
                            <div style="font-size:0.9rem; color:#166534;">Your total: <strong>${data.total_points} points</strong></div>
                        </div>`;
                    input.value = '';
                    loadMyPoints();
                    loadMyQRCodes(); // Refresh QR codes list to show redeemed status
                    showToast(data.message, 'success');
                } else {
                    resultDiv.innerHTML = `
                        <div style="padding:1.5rem; background:#fee2e2; border-radius:12px; text-align:center;">
                            <div style="font-size:2.5rem; margin-bottom:0.5rem;">❌</div>
                            <div style="font-size:1rem; font-weight:700; color:#dc2626;">${escapeHtml(data.message)}</div>
                        </div>`;
                }
            } catch (err) {
                resultDiv.innerHTML = '<div style="padding:1rem; background:#fee2e2; border-radius:10px; color:#dc2626;">Network error. Please try again.</div>';
            }
        }

        // Load user's QR codes and points history
        async function loadMyQRCodes() {
            try {
                const res = await fetch('reward_qr_api.php?action=get_my_qr_history');
                const data = await res.json();
                
                if (data.success) {
                    renderMyQRCodes(data.qr_codes || []);
                    renderPointsHistory(data.points_log || []);
                }
            } catch (err) {
                console.error('Failed to load QR history:', err);
            }
        }

        // Render QR codes list
        function renderMyQRCodes(qrCodes) {
            const container = document.getElementById('myQrCodesList');
            
            if (qrCodes.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--text-light);"><div style="font-size:3rem; margin-bottom:0.75rem;">🎫</div><p style="font-size:1rem; font-weight:600;">No QR codes yet</p><p style="font-size:0.85rem;">Make a purchase to receive reward QR codes!</p></div>';
                return;
            }
            
            container.innerHTML = qrCodes.map(qr => {
                const isRedeemed = qr.is_redeemed == 1;
                const isExpired = qr.expires_at && new Date(qr.expires_at) < new Date();
                const statusColor = isRedeemed ? '#22c55e' : (isExpired ? '#ef4444' : '#f59e0b');
                const statusText = isRedeemed ? '✅ Redeemed' : (isExpired ? '❌ Expired' : '⏳ Available');
                const statusBg = isRedeemed ? '#dcfce7' : (isExpired ? '#fee2e2' : '#fef3c7');
                
                return `
                    <div style="background:var(--card-bg,white); border-radius:12px; padding:1.25rem; box-shadow:0 2px 8px rgba(0,0,0,0.08); display:flex; align-items:center; gap:1.25rem; border-left:4px solid ${statusColor};">
                        <div id="qr_${qr.qr_id}" style="flex-shrink:0; width:80px; height:80px; display:flex; align-items:center; justify-content:center; background:white; border-radius:8px; overflow:hidden;"></div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:0.9rem; color:var(--text-color); margin-bottom:0.2rem; word-break:break-all;">${escapeHtml(qr.qr_code)}</div>
                            <div style="font-size:0.8rem; color:var(--text-light);">Source: ${qr.source_type === 'pos' ? '🏪 In-store' : '🌐 Online'} &bull; ${qr.points_value} point</div>
                            <div style="font-size:0.78rem; color:var(--text-light);">Created: ${new Date(qr.created_at).toLocaleDateString()}</div>
                            <div style="margin-top:0.4rem; display:inline-block; padding:0.2rem 0.6rem; background:${statusBg}; color:${statusColor}; border-radius:6px; font-size:0.75rem; font-weight:700;">${statusText}</div>
                        </div>
                        ${!isRedeemed && !isExpired ? `<button onclick="quickRedeem('${escapeHtml(qr.qr_code)}')" style="padding:0.5rem 1rem; background:var(--primary-color,#2563eb); color:white; border:none; border-radius:8px; font-weight:600; font-size:0.82rem; cursor:pointer; white-space:nowrap;">Scan Now</button>` : ''}
                    </div>
                `;
            }).join('');
            
            // Render mini QR codes
            qrCodes.forEach(qr => {
                const container = document.getElementById('qr_' + qr.qr_id);
                if (container) {
                    if (typeof QRCode !== 'undefined') {
                        new QRCode(container, {
                            text: qr.qr_code,
                            width: 70,
                            height: 70,
                            colorDark: '#000000',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.L
                        });
                    } else {
                        // Fallback: use QR code image API
                        const img = document.createElement('img');
                        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=' + encodeURIComponent(qr.qr_code);
                        img.alt = 'QR Code';
                        img.width = 70;
                        img.height = 70;
                        img.style.borderRadius = '4px';
                        container.appendChild(img);
                    }
                }
            });
        }

        // Render points history
        function renderPointsHistory(log) {
            const tbody = document.getElementById('pointsHistoryBody');
            
            if (log.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-light);">No points history yet</td></tr>';
                return;
            }
            
            tbody.innerHTML = log.map(entry => {
                const isPositive = entry.points > 0;
                const typeLabels = {
                    'EARN': '💰 Earned',
                    'REDEEM': '🛒 Redeemed',
                    'QR_SCAN': '📷 QR Scan',
                    'BONUS': '🎁 Bonus',
                    'ADJUSTMENT': '⚙️ Adjustment'
                };
                return `
                    <tr>
                        <td>${new Date(entry.created_at).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' })}</td>
                        <td>${typeLabels[entry.transaction_type] || entry.transaction_type}</td>
                        <td><span style="font-weight:700; color:${isPositive ? '#16a34a' : '#dc2626'};">${isPositive ? '+' : ''}${entry.points}</span></td>
                        <td style="font-size:0.85rem; color:var(--text-light);">${escapeHtml(entry.description || entry.reference_id || '-')}</td>
                    </tr>
                `;
            }).join('');
        }

        // Quick redeem from the QR codes list
        function quickRedeem(qrCode) {
            document.getElementById('qrScanInput').value = qrCode;
            // Switch to scan tab
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab')[0].classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('scanTab').classList.add('active');
            redeemQRCode();
        }

        // Enter key to scan QR
        document.getElementById('qrScanInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                redeemQRCode();
            }
        });
        
        <?php if ($isStaff): ?>
        // Staff functions
        let loyaltyMembers = [];
        
        async function loadLoyaltyMembers() {
            try {
                const res = await fetch('api/get_loyalty_members.php');
                const data = await res.json();
                
                if (data.success) {
                    loyaltyMembers = data.members;
                    renderCustomersTable();
                } else {
                    loyaltyMembers = [];
                    renderCustomersTable();
                }
            } catch (err) {
                loyaltyMembers = [];
                renderCustomersTable();
            }
        }
        
        function renderCustomersTable(filteredList) {
            const tbody = document.getElementById('customersTableBody');
            const members = filteredList || loyaltyMembers;
            
            if (members.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No loyalty members found</td></tr>';
                return;
            }
            
            tbody.innerHTML = members.map(customer => `
                <tr>
                    <td><strong>${escapeHtml(customer.name)}</strong></td>
                    <td>${escapeHtml(customer.email || '')}</td>
                    <td>${escapeHtml(customer.phone || '')}</td>
                    <td><span class="points-badge">${customer.points} pts</span></td>
                    <td>${customer.memberSince ? formatDate(customer.memberSince) : (customer.member_since ? formatDate(customer.member_since) : 'N/A')}</td>
                    <td>
                        <button class="btn btn-primary" style="padding:0.4rem 0.8rem; font-size:0.8rem;" onclick="viewCustomer(${customer.id || customer.member_id})">View</button>
                    </td>
                </tr>
            `).join('');
        }

        function filterMembersTable() {
            const query = document.getElementById('memberSearchInput').value.toLowerCase().trim();
            if (!query) {
                renderCustomersTable();
                return;
            }
            const filtered = loyaltyMembers.filter(c => 
                (c.name && c.name.toLowerCase().includes(query)) ||
                (c.email && c.email.toLowerCase().includes(query)) ||
                (c.phone && c.phone.toLowerCase().includes(query))
            );
            renderCustomersTable(filtered);
        }
        
        function openAddCustomerModal() {
            document.getElementById('customerModal').classList.add('active');
        }
        
        function closeCustomerModal() {
            document.getElementById('customerModal').classList.remove('active');
            document.getElementById('customerForm').reset();
        }
        
        async function saveCustomer(event) {
            event.preventDefault();
            
            const name = document.getElementById('customerName').value;
            const email = document.getElementById('customerEmail').value;
            const phone = document.getElementById('customerPhone').value;
            
            try {
                const res = await fetch('api/get_loyalty_members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', name, email, phone })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Loyalty member added!', 'success');
                    closeCustomerModal();
                    loadLoyaltyMembers();
                } else {
                    showToast(data.message || 'Failed to add member', 'error');
                }
            } catch (err) {
                // Fallback: add locally
                loyaltyMembers.push({
                    id: loyaltyMembers.length + 1,
                    name, email, phone,
                    points: 0,
                    memberSince: new Date().toISOString().split('T')[0]
                });
                renderCustomersTable();
                closeCustomerModal();
                showToast('Member added locally', 'success');
            }
        }
        
        function viewCustomer(id) {
            const customer = loyaltyMembers.find(c => (c.id || c.member_id) == id);
            if (customer) {
                customAlert('Customer Details', `Name: ${customer.name}\nEmail: ${customer.email || 'N/A'}\nPhone: ${customer.phone || 'N/A'}\nPoints: ${customer.points} pts\nMember Since: ${customer.memberSince || customer.member_since || 'N/A'}`, 'info');
            }
        }
        <?php endif; ?>

        <?php if ($isStaff): ?>
        // ─── Staff QR Redemption ───
        let staffRedeemQrCode = '';

        async function openStaffRedeemModal(qrCode) {
            staffRedeemQrCode = qrCode;
            const infoDiv = document.getElementById('staffRedeemQrInfo');
            const select = document.getElementById('staffRedeemMember');
            const resultDiv = document.getElementById('staffRedeemResult');
            resultDiv.style.display = 'none';

            // Show modal
            document.getElementById('staffRedeemModal').classList.add('active');

            // Fetch QR info
            infoDiv.innerHTML = '<div style="font-weight:700; color:#92400e;"><i class="fas fa-spinner fa-spin"></i> Validating QR code...</div>';
            try {
                const res = await fetch('reward_qr_api.php?action=validate_qr&qr_code=' + encodeURIComponent(qrCode));
                const data = await res.json();
                if (data.success && data.valid) {
                    infoDiv.innerHTML = `
                        <div style="font-size:1.5rem; font-weight:800; color:#d97706; margin-bottom:0.25rem;">${data.points_value} Points</div>
                        <div style="font-size:0.85rem; color:#92400e;">QR Code is valid &bull; ${data.source === 'pos' ? '🏪 In-store purchase' : '🌐 Online order'}</div>
                    `;
                } else {
                    infoDiv.innerHTML = `<div style="font-weight:700; color:#dc2626;">❌ ${escapeHtml(data.message || 'Invalid QR code')}</div>`;
                }
            } catch (err) {
                infoDiv.innerHTML = '<div style="font-weight:700; color:#dc2626;">❌ Could not validate QR code</div>';
            }

            // Populate customer dropdown
            select.innerHTML = '<option value="">-- Select Loyalty Member --</option>';
            if (loyaltyMembers && loyaltyMembers.length > 0) {
                loyaltyMembers.forEach(m => {
                    const id = m.member_id || m.id;
                    select.innerHTML += `<option value="${id}">${escapeHtml(m.name)} (${m.points || 0} pts)</option>`;
                });
            } else {
                // Try reloading
                try {
                    const res = await fetch('api/get_loyalty_members.php');
                    const data = await res.json();
                    if (data.success && data.members) {
                        data.members.forEach(m => {
                            select.innerHTML += `<option value="${m.member_id}">${escapeHtml(m.name)} (${m.points || 0} pts)</option>`;
                        });
                    }
                } catch (e) {}
            }
        }

        function closeStaffRedeemModal() {
            document.getElementById('staffRedeemModal').classList.remove('active');
            staffRedeemQrCode = '';
            document.getElementById('staffRedeemSearch').value = '';
        }

        function filterStaffRedeemDropdown() {
            const query = document.getElementById('staffRedeemSearch').value.toLowerCase().trim();
            const select = document.getElementById('staffRedeemMember');
            select.innerHTML = '<option value="">-- Select Loyalty Member --</option>';
            const members = (loyaltyMembers && loyaltyMembers.length > 0) ? loyaltyMembers : [];
            members.forEach(m => {
                const name = (m.name || '').toLowerCase();
                const email = (m.email || '').toLowerCase();
                const phone = (m.phone || '').toLowerCase();
                if (!query || name.includes(query) || email.includes(query) || phone.includes(query)) {
                    const id = m.member_id || m.id;
                    select.innerHTML += `<option value="${id}">${escapeHtml(m.name)} (${m.points || 0} pts)</option>`;
                }
            });
        }

        async function staffRedeemQR() {
            const memberId = document.getElementById('staffRedeemMember').value;
            const resultDiv = document.getElementById('staffRedeemResult');
            const btn = document.getElementById('staffRedeemBtn');

            if (!memberId) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div style="padding:0.75rem; background:#fee2e2; border-radius:8px; color:#dc2626; font-size:0.9rem;"><i class="fas fa-exclamation-circle"></i> Please select a customer first</div>';
                return;
            }

            btn.disabled = true;
            btn.textContent = '⏳ Processing...';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div style="text-align:center; padding:0.5rem;"><i class="fas fa-spinner fa-spin"></i></div>';

            try {
                const res = await fetch('reward_qr_api.php?action=staff_redeem_for_customer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ qr_code: staffRedeemQrCode, member_id: parseInt(memberId) })
                });
                const data = await res.json();
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div style="padding:1rem; background:#dcfce7; border-radius:10px; text-align:center;">
                            <div style="font-size:2rem; margin-bottom:0.3rem;">🎉</div>
                            <div style="font-weight:700; color:#16a34a; font-size:1rem;">+${data.points_earned} Points Awarded!</div>
                            <div style="font-size:0.85rem; color:#166534;">Customer: ${escapeHtml(data.customer_name)} &bull; Total: ${data.total_points} pts</div>
                        </div>`;
                    showToast(data.message, 'success');
                    // Refresh data
                    loadMyPoints();
                    loadLoyaltyMembers();
                    // Close after 2s
                    setTimeout(() => closeStaffRedeemModal(), 2500);
                } else {
                    resultDiv.innerHTML = `<div style="padding:0.75rem; background:#fee2e2; border-radius:8px; color:#dc2626; font-size:0.9rem;">${escapeHtml(data.message)}</div>`;
                }
            } catch (err) {
                resultDiv.innerHTML = '<div style="padding:0.75rem; background:#fee2e2; border-radius:8px; color:#dc2626;">Network error. Please try again.</div>';
            } finally {
                btn.disabled = false;
                btn.textContent = '✅ Award Points';
            }
        }
        <?php endif; ?>
        
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
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
    </script>
    <script src="shared-polish.js"></script>

    <!-- ═══════ Mega Footer + Modal Styles ═══════ -->
    <style>
        .mega-footer {
            background: #0a1628;
            color: #c8d6e5;
            padding: 3rem 2rem 0;
            margin-top: 2rem;
            border-top: 3px solid #2563eb;
        }
        .mega-footer-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 2.5rem;
        }
        .mega-footer h3 { color: white; font-size: 1rem; font-weight: 700; margin-bottom: 1.2rem; }
        .footer-col p, .footer-col li, .footer-col a { font-size: 0.85rem; color: #8899a8; line-height: 1.7; }
        .footer-col a { text-decoration: none; transition: color 0.2s; }
        .footer-col a:hover { color: white; }
        .footer-col ul { list-style: none; padding: 0; margin: 0; }
        .footer-col ul li { margin-bottom: 0.3rem; }
        .footer-contact-item { display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.6rem; font-size: 0.85rem; color: #8899a8; }
        .footer-contact-item i { margin-top: 0.25rem; color: #2563eb; width: 16px; text-align: center; flex-shrink: 0; }
        .footer-bottom { border-top: 1px solid #1a2a3e; margin-top: 2.5rem; padding: 1rem 0; text-align: center; }
        .footer-bottom-links { display: flex; justify-content: center; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
        .footer-bottom-links a { font-size: 0.8rem; color: #8899a8; text-decoration: none; transition: color 0.2s; }
        .footer-bottom-links a:hover { color: white; }
        .footer-copyright { font-size: 0.78rem; color: #5a6f83; padding: 0.8rem 0; border-top: 1px solid #1a2a3e; margin-top: 0.5rem; }

        /* Footer Modal Popup Styles */
        .footer-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 10000; justify-content: center; align-items: center; padding: 1.5rem; backdrop-filter: blur(4px); animation: fadeInOverlay 0.25s ease; }
        .footer-modal-overlay.active { display: flex; }
        @keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUpModal { from { opacity: 0; transform: translateY(30px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .footer-modal { background: white; border-radius: 16px; width: 100%; max-width: 600px; max-height: 85vh; overflow-y: auto; padding: 2rem; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUpModal 0.3s ease; }
        .footer-modal-close { position: absolute; top: 0.8rem; right: 1rem; background: none; border: none; font-size: 1.8rem; color: #94a3b8; cursor: pointer; line-height: 1; transition: color 0.2s; z-index: 1; }
        .footer-modal-close:hover { color: #ef4444; }
        .footer-modal-icon { text-align: center; font-size: 2.5rem; color: #2563eb; margin-bottom: 0.5rem; }
        .footer-modal h2 { text-align: center; font-size: 1.35rem; font-weight: 700; color: #1e293b; margin-bottom: 1.2rem; }
        .footer-modal-body { font-size: 0.9rem; color: #475569; line-height: 1.75; }
        .footer-modal-body h4 { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin: 1.2rem 0 0.5rem; }
        .footer-modal-body h4 i { color: #2563eb; margin-right: 0.4rem; }
        .footer-modal-body ul { padding-left: 1.3rem; margin: 0.4rem 0 0.8rem; }
        .footer-modal-body ul li { margin-bottom: 0.35rem; }
        .footer-modal-divider { border-top: 1px solid #e2e8f0; margin: 1.2rem 0; }
        .faq-item { border-bottom: 1px solid #f1f5f9; }
        .faq-question { padding: 0.8rem 0; cursor: pointer; font-weight: 600; color: #334155; display: flex; align-items: center; gap: 0.5rem; transition: color 0.2s; }
        .faq-question:hover { color: #2563eb; }
        .faq-arrow { font-size: 0.7rem; transition: transform 0.25s; flex-shrink: 0; }
        .faq-question.open .faq-arrow { transform: rotate(90deg); }
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s ease; color: #64748b; font-size: 0.88rem; line-height: 1.65; padding: 0 0 0 1.2rem; }
        .faq-answer.open { max-height: 300px; padding-bottom: 0.8rem; }
        .status-guide-item { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem; font-size: 0.88rem; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .status-dot.pending { background: #f59e0b; }
        .status-dot.processing { background: #3b82f6; }
        .status-dot.ready { background: #10b981; }
        .status-dot.completed { background: #22c55e; }
        .status-dot.cancelled { background: #ef4444; }
        .contact-method-card { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; background: #f8fafc; border-radius: 10px; margin-bottom: 0.6rem; }
        .contact-method-card i { font-size: 1.3rem; color: #2563eb; width: 30px; text-align: center; }
        .contact-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 0.8rem; }
        .contact-info-card { text-align: center; padding: 1rem 0.5rem; background: #f8fafc; border-radius: 10px; }
        .contact-info-card i { font-size: 1.5rem; color: #2563eb; margin-bottom: 0.4rem; }
        .contact-info-card h4 { font-size: 0.8rem; margin: 0.3rem 0 0.2rem; color: #64748b; }
        .contact-info-card p { font-size: 0.88rem; margin: 0; color: #1e293b; }
        .hours-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .hours-table td { padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; }
        .hours-table td:last-child { text-align: right; font-weight: 600; color: #1e293b; }
        .footer-modal-form-group label { font-size: 0.88rem; font-weight: 600; color: #334155; }
        @media (max-width: 768px) {
            .mega-footer-grid { grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        }
        @media (max-width: 600px) {
            .mega-footer-grid { grid-template-columns: 1fr; }
            .footer-modal { padding: 1.5rem 1.2rem; max-height: 90vh; }
            .contact-info-grid { grid-template-columns: 1fr; }
        }
    </style>

    <!-- Mega Footer -->
    <div class="mega-footer">
        <div class="mega-footer-grid">
            <div class="footer-col">
                <h3>Calloway Pharmacy Inc.</h3>
                <div class="footer-contact-item">
                    <i class="fas fa-location-dot"></i>
                    <span>051 J. Corona St, Tanauan City, Batangas</span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-phone"></i>
                    <span>8833 2273</span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-envelope"></i>
                    <span>callowaypharmacy@gmail.com</span>
                </div>
            </div>
            <div class="footer-col">
                <h3>About Us</h3>
                <ul>
                    <li><a href="javascript:void(0)" onclick="openFooterModal('aboutModal')">About Calloway Pharmacy</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Get Help</h3>
                <ul>
                    <li><a href="javascript:void(0)" onclick="openFooterModal('orderStatusModal')">Order Status</a></li>
                    <li><a href="javascript:void(0)" onclick="openFooterModal('faqModal')">FAQs</a></li>
                    <li><a href="javascript:void(0)" onclick="openFooterModal('privacyModal')">Privacy Policy</a></li>
                    <li><a href="javascript:void(0)" onclick="openFooterModal('contactModal')">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Services</h3>
                <ul>
                    <li><a href="javascript:void(0)" onclick="openFooterModal('aboutModal')">Order & Pick-up</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-links">
                <a href="javascript:void(0)" onclick="openFooterModal('refundModal')">Refund policy</a>
                <a href="javascript:void(0)" onclick="openFooterModal('privacyModal')">Privacy policy</a>
                <a href="javascript:void(0)" onclick="openFooterModal('termsModal')">Terms of service</a>
                <a href="javascript:void(0)" onclick="openFooterModal('contactInfoModal')">Contact information</a>
            </div>
            <div class="footer-copyright">&copy; <?php echo date('Y'); ?>, Calloway Pharmacy</div>
        </div>
    </div>

    <!-- About Calloway Pharmacy Modal -->
    <div class="footer-modal-overlay" id="aboutModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('aboutModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-store-alt"></i></div>
            <h2>About Calloway Pharmacy</h2>
            <div class="footer-modal-body">
                <p>Calloway Pharmacy Inc. is your trusted neighborhood pharmacy located at <strong>051 J. Corona St, Tanauan City, Batangas</strong>. We have been serving the community with quality pharmaceutical products and exceptional customer care.</p>
                <h4><i class="fas fa-star"></i> Our Services</h4>
                <ul>
                    <li><strong>Prescription Dispensing</strong> &mdash; Accurate and timely filling of prescriptions by licensed pharmacists</li>
                    <li><strong>Online Ordering & Pick-up</strong> &mdash; Browse and order medicines online, then pick up at our store</li>
                    <li><strong>Over-the-Counter Medicines</strong> &mdash; Wide selection of OTC pain relievers, cold & flu remedies, vitamins, and supplements</li>
                    <li><strong>Health & Wellness Products</strong> &mdash; Skin care, baby care, first aid supplies, and personal care items</li>
                    <li><strong>Loyalty Rewards Program</strong> &mdash; Earn points with every purchase and redeem exclusive discounts</li>
                    <li><strong>Medicine Consultation</strong> &mdash; Free consultations with our in-house pharmacists</li>
                    <li><strong>Expiry Monitoring</strong> &mdash; We ensure all products on our shelves are safe and within date</li>
                </ul>
                <h4><i class="fas fa-clock"></i> Store Hours</h4>
                <p>Monday &ndash; Saturday: 8:00 AM &ndash; 9:00 PM<br>Sunday: 9:00 AM &ndash; 6:00 PM</p>
                <h4><i class="fas fa-bullseye"></i> Our Mission</h4>
                <p>To provide accessible, affordable, and quality pharmaceutical products and services to every Filipino family in Tanauan City and beyond.</p>
            </div>
        </div>
    </div>

    <!-- Order Status Modal -->
    <div class="footer-modal-overlay" id="orderStatusModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('orderStatusModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-box-open"></i></div>
            <h2>Order Status</h2>
            <div class="footer-modal-body">
                <p>Track the status of your online orders placed through Calloway Pharmacy.</p>
                <div class="footer-modal-form-group">
                    <label for="orderTrackInputLQ"><i class="fas fa-search"></i> Enter your Order Number or Reference Code</label>
                    <input type="text" id="orderTrackInputLQ" placeholder="e.g. ORD-20250213-001" style="width:100%;padding:0.7rem 1rem;border:1px solid #d1d5db;border-radius:8px;font-size:0.95rem;margin-top:0.4rem;">
                    <button onclick="trackOrderStatusLQ()" style="margin-top:0.7rem;width:100%;padding:0.7rem;background:#2563eb;color:white;border:none;border-radius:8px;font-size:0.95rem;font-weight:600;cursor:pointer;"><i class="fas fa-search"></i> Track Order</button>
                </div>
                <div id="orderTrackResultLQ" style="margin-top:1rem;"></div>
                <div class="footer-modal-divider"></div>
                <h4>Order Status Guide</h4>
                <div class="status-guide-item"><span class="status-dot pending"></span><strong>Pending</strong> &mdash; Your order has been received and is waiting for processing</div>
                <div class="status-guide-item"><span class="status-dot processing"></span><strong>Processing</strong> &mdash; Our pharmacist is preparing your order</div>
                <div class="status-guide-item"><span class="status-dot ready"></span><strong>Ready for Pick-up</strong> &mdash; Your order is ready! Visit our store to collect it</div>
                <div class="status-guide-item"><span class="status-dot completed"></span><strong>Completed</strong> &mdash; Order has been collected. Thank you!</div>
                <div class="status-guide-item"><span class="status-dot cancelled"></span><strong>Cancelled</strong> &mdash; Order was cancelled</div>
            </div>
        </div>
    </div>

    <!-- FAQs Modal -->
    <div class="footer-modal-overlay" id="faqModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('faqModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-question-circle"></i></div>
            <h2>Frequently Asked Questions</h2>
            <div class="footer-modal-body">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)"><i class="fas fa-chevron-right faq-arrow"></i> How do I place an online order?</div>
                    <div class="faq-answer">Browse our product categories, add items to your cart, and proceed to checkout. You will receive an order confirmation with your reference number. Pick up your order at our store once it's ready.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)"><i class="fas fa-chevron-right faq-arrow"></i> Do I need a prescription?</div>
                    <div class="faq-answer">Over-the-counter medicines can be purchased directly. Prescription medicines require a valid prescription which you must present upon pick-up at our store.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)"><i class="fas fa-chevron-right faq-arrow"></i> How does the loyalty program work?</div>
                    <div class="faq-answer">Earn 25 loyalty points for every ₱500 spent on qualifying purchases. Accumulate points to unlock exclusive discounts and rewards. Visit our <a href="loyalty_qr.php" style="color:#2563eb;">Loyalty & QR page</a> to scan and track your points.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)"><i class="fas fa-chevron-right faq-arrow"></i> What payment methods are accepted?</div>
                    <div class="faq-answer">We accept cash payment upon pick-up at our store. Online payment integrations are coming soon.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)"><i class="fas fa-chevron-right faq-arrow"></i> Can I cancel my order?</div>
                    <div class="faq-answer">Yes, you can cancel your order while it is still in "Pending" status. Once it moves to "Processing," please contact us directly at <strong>8833 2273</strong> for assistance.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)"><i class="fas fa-chevron-right faq-arrow"></i> What are your store hours?</div>
                    <div class="faq-answer">Monday to Saturday: 8:00 AM – 9:00 PM. Sunday: 9:00 AM – 6:00 PM.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="footer-modal-overlay" id="privacyModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('privacyModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-shield-alt"></i></div>
            <h2>Privacy Policy</h2>
            <div class="footer-modal-body">
                <p><em>Last updated: <?php echo date('F Y'); ?></em></p>
                <h4>Information We Collect</h4>
                <p>We collect personal information that you provide when creating an account, placing an order, or contacting us. This may include your name, email address, phone number, and order history.</p>
                <h4>How We Use Your Information</h4>
                <ul>
                    <li>To process and fulfill your orders</li>
                    <li>To manage your loyalty rewards account</li>
                    <li>To communicate order updates and promotions</li>
                    <li>To improve our products and services</li>
                </ul>
                <h4>Data Protection</h4>
                <p>We implement industry-standard security measures to protect your personal data. Your information is stored securely and is never sold or shared with third parties for marketing purposes.</p>
                <h4>Your Rights</h4>
                <p>You may request access to, correction of, or deletion of your personal data at any time by contacting us at <strong>callowaypharmacy@gmail.com</strong>.</p>
                <h4>Cookies</h4>
                <p>Our website uses cookies to enhance your browsing experience and remember your preferences. You can manage cookie settings through your browser.</p>
            </div>
        </div>
    </div>

    <!-- Contact Us Modal -->
    <div class="footer-modal-overlay" id="contactModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('contactModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-headset"></i></div>
            <h2>Contact Us</h2>
            <div class="footer-modal-body">
                <p>We'd love to hear from you! Reach out through any of the following channels:</p>
                <div class="contact-method-card">
                    <i class="fas fa-phone-alt"></i>
                    <div><strong>Phone</strong><br><a href="tel:88332273" style="color:#2563eb;">8833 2273</a></div>
                </div>
                <div class="contact-method-card">
                    <i class="fas fa-envelope"></i>
                    <div><strong>Email</strong><br><a href="mailto:callowaypharmacy@gmail.com" style="color:#2563eb;">callowaypharmacy@gmail.com</a></div>
                </div>
                <div class="contact-method-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <div><strong>Visit Us</strong><br>051 J. Corona St, Tanauan City, Batangas</div>
                </div>
                <div class="contact-method-card">
                    <i class="fab fa-facebook"></i>
                    <div><strong>Facebook</strong><br><a href="#" style="color:#2563eb;">Calloway Pharmacy</a></div>
                </div>
                <div class="footer-modal-divider"></div>
                <h4><i class="fas fa-clock"></i> Customer Service Hours</h4>
                <p>Monday &ndash; Saturday: 8:00 AM &ndash; 9:00 PM<br>Sunday: 9:00 AM &ndash; 6:00 PM</p>
            </div>
        </div>
    </div>

    <!-- Refund Policy Modal -->
    <div class="footer-modal-overlay" id="refundModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('refundModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-undo-alt"></i></div>
            <h2>Refund Policy</h2>
            <div class="footer-modal-body">
                <p><em>Effective: <?php echo date('F Y'); ?></em></p>
                <h4>Eligibility for Refund</h4>
                <ul>
                    <li>Items must be returned within <strong>7 days</strong> of purchase</li>
                    <li>Products must be unopened, unused, and in original packaging</li>
                    <li>A valid receipt or order reference number is required</li>
                </ul>
                <h4>Non-Refundable Items</h4>
                <ul>
                    <li>Prescription medicines (once dispensed)</li>
                    <li>Opened or used personal care products</li>
                    <li>Items purchased on clearance or special promotion</li>
                </ul>
                <h4>Refund Process</h4>
                <p>Bring the item and your receipt to our store. Our staff will inspect the product and process your refund within <strong>3&ndash;5 business days</strong>. Refunds are issued via the original payment method.</p>
                <h4>Exchanges</h4>
                <p>We offer exchanges for defective or incorrect items. Please contact us within 48 hours of receiving your order.</p>
            </div>
        </div>
    </div>

    <!-- Terms of Service Modal -->
    <div class="footer-modal-overlay" id="termsModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('termsModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-file-contract"></i></div>
            <h2>Terms of Service</h2>
            <div class="footer-modal-body">
                <p><em>Last updated: <?php echo date('F Y'); ?></em></p>
                <h4>1. Acceptance of Terms</h4>
                <p>By accessing and using the Calloway Pharmacy website and services, you agree to be bound by these Terms of Service.</p>
                <h4>2. Online Orders</h4>
                <p>All online orders are subject to product availability. We reserve the right to limit quantities. Prices displayed are in Philippine Pesos (PHP) and may change without prior notice.</p>
                <h4>3. Prescription Medicines</h4>
                <p>Certain products require a valid prescription. You must present the original prescription upon pick-up. Calloway Pharmacy reserves the right to refuse dispensing without proper documentation.</p>
                <h4>4. Account Responsibility</h4>
                <p>You are responsible for maintaining the confidentiality of your account credentials. Any activity under your account is your responsibility.</p>
                <h4>5. Loyalty Program</h4>
                <p>Loyalty points are non-transferable and have no cash value. Calloway Pharmacy reserves the right to modify or discontinue the loyalty program at any time.</p>
                <h4>6. Limitation of Liability</h4>
                <p>Calloway Pharmacy shall not be liable for any indirect, incidental, or consequential damages arising from the use of our services.</p>
                <h4>7. Governing Law</h4>
                <p>These terms are governed by the laws of the Republic of the Philippines.</p>
            </div>
        </div>
    </div>

    <!-- Contact Information Modal -->
    <div class="footer-modal-overlay" id="contactInfoModal">
        <div class="footer-modal">
            <button class="footer-modal-close" onclick="closeFooterModal('contactInfoModal')">&times;</button>
            <div class="footer-modal-icon"><i class="fas fa-address-card"></i></div>
            <h2>Contact Information</h2>
            <div class="footer-modal-body">
                <div class="contact-info-grid">
                    <div class="contact-info-card">
                        <i class="fas fa-building"></i>
                        <h4>Business Name</h4>
                        <p>Calloway Pharmacy Inc.</p>
                    </div>
                    <div class="contact-info-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Address</h4>
                        <p>051 J. Corona St,<br>Tanauan City, Batangas</p>
                    </div>
                    <div class="contact-info-card">
                        <i class="fas fa-phone-alt"></i>
                        <h4>Phone</h4>
                        <p><a href="tel:88332273" style="color:#2563eb;">8833 2273</a></p>
                    </div>
                    <div class="contact-info-card">
                        <i class="fas fa-envelope"></i>
                        <h4>Email</h4>
                        <p><a href="mailto:callowaypharmacy@gmail.com" style="color:#2563eb;">callowaypharmacy@gmail.com</a></p>
                    </div>
                </div>
                <div class="footer-modal-divider"></div>
                <h4><i class="fas fa-clock"></i> Store Hours</h4>
                <table class="hours-table">
                    <tr><td>Monday &ndash; Saturday</td><td>8:00 AM &ndash; 9:00 PM</td></tr>
                    <tr><td>Sunday</td><td>9:00 AM &ndash; 6:00 PM</td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer Modal Scripts -->
    <script>
    function openFooterModal(id) {
        const modal = document.getElementById(id);
        if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    }
    function closeFooterModal(id) {
        const modal = document.getElementById(id);
        if (modal) { modal.classList.remove('active'); document.body.style.overflow = ''; }
    }
    document.querySelectorAll('.footer-modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) { this.classList.remove('active'); document.body.style.overflow = ''; }
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.footer-modal-overlay.active').forEach(m => m.classList.remove('active'));
            document.body.style.overflow = '';
        }
    });
    function toggleFaq(el) {
        const answer = el.nextElementSibling;
        const isOpen = el.classList.contains('open');
        document.querySelectorAll('.faq-question').forEach(q => { q.classList.remove('open'); q.nextElementSibling.classList.remove('open'); });
        if (!isOpen) { el.classList.add('open'); answer.classList.add('open'); }
    }
    function trackOrderStatusLQ() {
        const input = document.getElementById('orderTrackInputLQ');
        const resultDiv = document.getElementById('orderTrackResultLQ');
        const ref = input.value.trim();
        if (!ref) {
            resultDiv.innerHTML = '<div style="padding:0.8rem;background:#fef3c7;border-radius:8px;color:#92400e;font-size:0.88rem;"><i class="fas fa-exclamation-triangle"></i> Please enter an order number or reference code.</div>';
            return;
        }
        resultDiv.innerHTML = '<div style="text-align:center;padding:1rem;color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Looking up order...</div>';
        fetch('api_orders.php?action=track&ref=' + encodeURIComponent(ref))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.order) {
                    const o = data.order;
                    const statusColors = {pending:'#f59e0b',processing:'#3b82f6',ready:'#10b981',completed:'#22c55e',cancelled:'#ef4444'};
                    const statusColor = statusColors[o.status?.toLowerCase()] || '#64748b';
                    resultDiv.innerHTML = `
                        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:1rem;margin-top:0.5rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
                                <strong style="font-size:0.95rem;color:#0c4a6e;">${escapeHtml(o.reference || ref)}</strong>
                                <span style="background:${statusColor};color:white;padding:0.2rem 0.8rem;border-radius:20px;font-size:0.78rem;font-weight:600;">${escapeHtml(o.status || 'Unknown')}</span>
                            </div>
                            <div style="font-size:0.85rem;color:#475569;">
                                <div><strong>Date:</strong> ${escapeHtml(o.date || 'N/A')}</div>
                                <div><strong>Items:</strong> ${o.item_count || 'N/A'} item(s)</div>
                                <div><strong>Total:</strong> ₱${parseFloat(o.total || 0).toFixed(2)}</div>
                            </div>
                        </div>`;
                } else {
                    resultDiv.innerHTML = '<div style="padding:0.8rem;background:#fef2f2;border-radius:8px;color:#991b1b;font-size:0.88rem;"><i class="fas fa-times-circle"></i> No order found with that reference. Please double-check and try again.</div>';
                }
            })
            .catch(() => {
                resultDiv.innerHTML = '<div style="padding:0.8rem;background:#fef2f2;border-radius:8px;color:#991b1b;font-size:0.88rem;"><i class="fas fa-times-circle"></i> Unable to look up order at this time. Please try again later.</div>';
            });
    }
    </script>

</body>
</html>
