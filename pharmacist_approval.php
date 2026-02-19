<?php
/**
 * Pharmacist Approval Interface
 * Displays orders requiring prescription verification
 */

session_start();
require_once 'db_connection.php';
require_once 'Auth.php';
require_once 'RxEnforcement.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$rx = new RxEnforcement($conn);

// Check if user is pharmacist/admin
if (!$rx->isPharmacist($currentUser['user_id'])) {
    die('Access denied. This page is only for pharmacists.');
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve' && $order_id > 0) {
        $result = $rx->approveRxOrder($order_id, $currentUser['user_id'], $notes);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'reject' && $order_id > 0) {
        $result = $rx->rejectRxOrder($order_id, $currentUser['user_id'], $notes);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Get pending approvals
$pendingOrders = $rx->getPendingRxApprovals();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Approvals - Calloway Pharmacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .header h1 i {
            color: #2563eb;
        }
        
        .header p {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            border-left: 4px solid #f59e0b;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .order-id {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .order-time {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .order-details {
            margin-bottom: 1rem;
        }
        
        .detail-row {
            display: flex;
            padding: 0.5rem 0;
            font-size: 0.95rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4b5563;
            width: 150px;
        }
        
        .detail-value {
            color: #1f2937;
        }
        
        .rx-products {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .rx-products h4 {
            color: #92400e;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .rx-products ul {
            list-style: none;
            padding-left: 0;
            color: #78350f;
        }
        
        .rx-products li {
            padding: 0.25rem 0;
        }
        
        .rx-products li::before {
            content: '💊 ';
            margin-right: 0.5rem;
        }
        
        .action-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
        }
        
        .btn-group {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: white;
            color: #2563eb;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            margin-bottom: 1rem;
        }
        
        .back-btn:hover {
            transform: translateX(-4px);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="header">
            <h1>
                <i class="fas fa-prescription-bottle-medical"></i>
                Prescription Order Approvals
            </h1>
            <p>Review and approve orders containing prescription medications</p>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($pendingOrders)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>All Clear!</h3>
                <p>No prescription orders pending approval at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingOrders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                            <div class="order-time">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        <div style="background: #fef3c7; padding: 0.5rem 1rem; border-radius: 6px; color: #92400e; font-weight: 600; font-size: 0.85rem;">
                            <i class="fas fa-exclamation-triangle"></i> PENDING APPROVAL
                        </div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-user"></i> Customer:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-phone"></i> Contact:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['contact_number']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-peso-sign"></i> Total Amount:</div>
                            <div class="detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fas fa-box"></i> Total Items:</div>
                            <div class="detail-value"><?php echo $order['item_count']; ?> item(s)</div>
                        </div>
                    </div>
                    
                    <div class="rx-products">
                        <h4><i class="fas fa-prescription-bottle-medical"></i> Prescription Medications:</h4>
                        <ul>
                            <?php 
                            $products = explode(', ', $order['rx_products']);
                            foreach ($products as $product): 
                            ?>
                                <li><?php echo htmlspecialchars($product); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <form method="POST" class="action-form">
                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                        <div class="form-group">
                            <label for="notes_<?php echo $order['order_id']; ?>">
                                <i class="fas fa-notes-medical"></i> Notes (Optional):
                            </label>
                            <textarea name="notes" id="notes_<?php echo $order['order_id']; ?>" rows="2" placeholder="Add any notes about this prescription verification..."></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="submit" name="action" value="approve" class="btn btn-approve">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
