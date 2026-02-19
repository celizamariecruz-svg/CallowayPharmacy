<?php
/**
 * Reward QR Code API
 * Handles generating one-time QR codes for purchases and redeeming them for loyalty points
 * 
 * Actions:
 *   - generate_reward_qr : Create a one-time QR code after a purchase (POS or online)
 *   - redeem_reward_qr   : Scan/redeem a QR code to earn loyalty points
 *   - get_my_points       : Get current user's loyalty points
 *   - get_my_qr_history   : Get QR code history for current user
 *   - validate_qr         : Check if a QR code is valid (not yet redeemed)
 */

session_start();
require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

// Ensure the reward_qr_codes table exists
$conn->query("CREATE TABLE IF NOT EXISTS reward_qr_codes (
    qr_id INT AUTO_INCREMENT PRIMARY KEY,
    qr_code VARCHAR(100) NOT NULL UNIQUE,
    source_type ENUM('pos','online') NOT NULL DEFAULT 'pos',
    source_order_id INT NULL,
    sale_reference VARCHAR(50) NULL,
    generated_for_user INT NULL,
    generated_for_name VARCHAR(100) NULL,
    redeemed_by_user INT NULL,
    redeemed_by_name VARCHAR(100) NULL,
    points_value INT NOT NULL DEFAULT 0,
    is_redeemed TINYINT(1) NOT NULL DEFAULT 0,
    redeemed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    INDEX idx_qr_code (qr_code),
    INDEX idx_redeemed (is_redeemed),
    INDEX idx_generated_for (generated_for_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure loyalty_members table exists
$conn->query("CREATE TABLE IF NOT EXISTS loyalty_members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    points INT NOT NULL DEFAULT 0,
    member_since DATE DEFAULT (CURRENT_DATE),
    user_id INT NULL,
    INDEX idx_email (email),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure loyalty_points_log table exists
$conn->query("CREATE TABLE IF NOT EXISTS loyalty_points_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    points INT NOT NULL,
    transaction_type ENUM('EARN','REDEEM','QR_SCAN','BONUS','ADJUSTMENT') NOT NULL DEFAULT 'EARN',
    reference_id VARCHAR(100) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add user_id column to loyalty_members if missing
try {
    $check = $conn->query("SHOW COLUMNS FROM loyalty_members LIKE 'user_id'");
    if ($check->num_rows === 0) {
        $conn->query("ALTER TABLE loyalty_members ADD COLUMN user_id INT NULL, ADD INDEX idx_user_id (user_id)");
    }
} catch (Exception $e) {}

// Add description column to loyalty_points_log if missing
try {
    $check = $conn->query("SHOW COLUMNS FROM loyalty_points_log LIKE 'description'");
    if ($check->num_rows === 0) {
        $conn->query("ALTER TABLE loyalty_points_log ADD COLUMN description TEXT NULL");
    }
} catch (Exception $e) {}

// Fix: Ensure transaction_type supports QR redemption values on existing tables
try {
    $conn->query("ALTER TABLE loyalty_points_log MODIFY COLUMN transaction_type ENUM('EARN','REDEEM','QR_SCAN','BONUS','ADJUSTMENT') NOT NULL DEFAULT 'EARN'");
} catch (Exception $e) {}

// Fix: Ensure reward_qr_codes does not default to hardcoded 1-point values
try {
    $conn->query("ALTER TABLE reward_qr_codes MODIFY COLUMN points_value INT NOT NULL DEFAULT 0");
} catch (Exception $e) {}

// Fix: Make phone column nullable (prevents 'no default value' crash)
try {
    $conn->query("ALTER TABLE loyalty_members MODIFY COLUMN phone VARCHAR(20) NULL DEFAULT NULL");
} catch (Exception $e) {}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'generate_reward_qr':
        generateRewardQR($conn);
        break;

    case 'redeem_reward_qr':
        redeemRewardQR($conn);
        break;

    case 'get_my_points':
        getMyPoints($conn);
        break;

    case 'get_my_qr_history':
        getMyQRHistory($conn);
        break;

    case 'validate_qr':
        validateQR($conn);
        break;

    case 'get_customer_points':
        getCustomerPoints($conn);
        break;

    case 'staff_redeem_for_customer':
        staffRedeemForCustomer($conn);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Generate a one-time reward QR code after a purchase
 * Staff can generate codes for walk-in customers (customer_user_id = 0 or null)
 * or for a specific customer by passing customer_user_id.
 * Customers placing online orders get codes automatically.
 */
function generateRewardQR($conn) {
    // SECURITY: only POS-authorized staff can generate reward QR codes.
    $auth = new Auth($conn);
    if (!$auth->isLoggedIn() || !$auth->hasPermission('pos.access')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden. POS authorization required to generate reward QR codes.'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    // Only POS-validated sales can generate QR rewards.
    $sourceType = 'pos';
    $orderId = intval($input['order_id'] ?? 0);
    $saleReference = $input['sale_reference'] ?? '';
    $customerName = $input['customer_name'] ?? 'Customer';
    $purchaseAmount = 0;

    if ($orderId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing or invalid POS sale reference.'
        ]);
        return;
    }

    // Idempotency: reuse existing unredeemed QR for this POS sale.
    $existingStmt = $conn->prepare("SELECT qr_id, qr_code, points_value, expires_at FROM reward_qr_codes WHERE source_type = 'pos' AND source_order_id = ? AND is_redeemed = 0 ORDER BY qr_id DESC LIMIT 1");
    $existingStmt->bind_param("i", $orderId);
    $existingStmt->execute();
    $existingQr = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existingQr) {
        echo json_encode([
            'success' => true,
            'qr_code' => $existingQr['qr_code'],
            'qr_id' => intval($existingQr['qr_id']),
            'points_value' => intval($existingQr['points_value']),
            'expires_at' => $existingQr['expires_at'],
            'message' => 'Existing reward QR code returned for this sale.'
        ]);
        return;
    }

    // Pull verified amount from sales table (never trust client totals).
    $saleStmt = $conn->prepare("SELECT total, sale_reference FROM sales WHERE sale_id = ? LIMIT 1");
    $saleStmt->bind_param("i", $orderId);
    $saleStmt->execute();
    $sale = $saleStmt->get_result()->fetch_assoc();
    $saleStmt->close();

    if (!$sale) {
        echo json_encode([
            'success' => false,
            'message' => 'POS sale not found. Cannot generate reward QR.'
        ]);
        return;
    }

    $purchaseAmount = floatval($sale['total'] ?? 0);
    if (empty($saleReference) && !empty($sale['sale_reference'])) {
        $saleReference = $sale['sale_reference'];
    }

    $pointsValue = calculateLoyaltyPoints($purchaseAmount);
    if ($pointsValue <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No reward points for POS sales below ₱500.'
        ]);
        return;
    }
    
    // Determine who the QR code is for:
    // - If customer_user_id is provided (staff generating for a specific customer), use that
    // - Otherwise keep NULL for walk-in customers
    $generatedForUser = null;
    
    if (isset($input['customer_user_id']) && intval($input['customer_user_id']) > 0) {
        $generatedForUser = intval($input['customer_user_id']);
        // Look up customer name if not provided
        if ($customerName === 'Customer' || empty($customerName)) {
            $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $generatedForUser);
            $stmt->execute();
            $cust = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($cust) $customerName = $cust['full_name'];
        }
    }
    // For POS walk-in without customer_user_id, generatedForUser stays NULL
    
    // Generate unique QR code
    $qrCode = 'RWD-' . strtoupper(bin2hex(random_bytes(6))) . '-' . time();
    
    // Set expiry to 30 days from now
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $conn->prepare("INSERT INTO reward_qr_codes (qr_code, source_type, source_order_id, sale_reference, generated_for_user, generated_for_name, points_value, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssis", $qrCode, $sourceType, $orderId, $saleReference, $generatedForUser, $customerName, $pointsValue, $expiresAt);
    $stmt->execute();
    $qrId = $conn->insert_id;
    $stmt->close();

    $message = $pointsValue > 0
        ? "Reward QR code generated! Scan to earn {$pointsValue} loyalty points."
        : 'Reward QR code generated. No points will be earned for amounts below ₱500.';
    
    echo json_encode([
        'success' => true,
        'qr_code' => $qrCode,
        'qr_id' => $qrId,
        'points_value' => $pointsValue,
        'expires_at' => $expiresAt,
        'message' => $message
    ]);
}

/**
 * Redeem a QR code - awards points based on source purchase amount
 */
function redeemRewardQR($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $qrCode = trim($input['qr_code'] ?? '');
    
    if (empty($qrCode)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a QR code']);
        return;
    }
    
    // Must be logged in to redeem
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please log in to redeem reward points']);
        return;
    }
    
    $userId = intval($_SESSION['user_id']);
    $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Customer';
    $userEmail = '';
    
    // Get user email
    $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $userEmail = $user['email'] ?? '';
        $userName = $user['full_name'] ?? $userName;
    }
    
    // Find the QR code
    $stmt = $conn->prepare("SELECT * FROM reward_qr_codes WHERE qr_code = ? LIMIT 1");
    $stmt->bind_param("s", $qrCode);
    $stmt->execute();
    $qr = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$qr) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code. This code does not exist.']);
        return;
    }
    
    if ($qr['is_redeemed']) {
        echo json_encode(['success' => false, 'message' => 'This QR code has already been used! Each code can only be scanned once.']);
        return;
    }
    
    // Check expiry
    if ($qr['expires_at'] && strtotime($qr['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This QR code has expired.']);
        return;
    }
    
    $pointsValue = intval($qr['points_value']);
    
    $conn->begin_transaction();
    
    try {
        // Mark QR as redeemed
        $stmt = $conn->prepare("UPDATE reward_qr_codes SET is_redeemed = 1, redeemed_by_user = ?, redeemed_by_name = ?, redeemed_at = NOW() WHERE qr_id = ? AND is_redeemed = 0");
        $stmt->bind_param("isi", $userId, $userName, $qr['qr_id']);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'QR code was already redeemed']);
            return;
        }
        $stmt->close();
        
        // Find or create loyalty member
        $memberId = findOrCreateLoyaltyMember($conn, $userId, $userName, $userEmail);
        
        // Add points
        $stmt = $conn->prepare("UPDATE loyalty_members SET points = points + ? WHERE member_id = ?");
        $stmt->bind_param("ii", $pointsValue, $memberId);
        $stmt->execute();
        $stmt->close();
        
        // Log the transaction
        $refId = 'QR-' . $qr['qr_code'];
        $desc = "Scanned reward QR code from " . ($qr['source_type'] === 'pos' ? 'in-store' : 'online') . " purchase";
        $stmt = $conn->prepare("INSERT INTO loyalty_points_log (member_id, points, transaction_type, reference_id, description) VALUES (?, ?, 'QR_SCAN', ?, ?)");
        $stmt->bind_param("iiss", $memberId, $pointsValue, $refId, $desc);
        $stmt->execute();
        $stmt->close();
        
        // Get updated total
        $stmt = $conn->prepare("SELECT points FROM loyalty_members WHERE member_id = ?");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $updated = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'points_earned' => $pointsValue,
            'total_points' => intval($updated['points']),
            'message' => "🎉 You earned {$pointsValue} loyalty points! Total: {$updated['points']} points"
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('QR redemption error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to redeem QR code. Please try again.']);
    }
}

/**
 * Get current user's loyalty points
 */
function getMyPoints($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'points' => 0, 'message' => 'Not logged in']);
        return;
    }
    
    $userId = intval($_SESSION['user_id']);
    $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Customer';
    $userEmail = '';
    
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user) $userEmail = $user['email'] ?? '';
    
    // Try by user_id first, then email
    $member = null;
    $stmt = $conn->prepare("SELECT * FROM loyalty_members WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$member && $userEmail) {
        $stmt = $conn->prepare("SELECT * FROM loyalty_members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    if ($member) {
        echo json_encode([
            'success' => true,
            'points' => intval($member['points']),
            'member_id' => intval($member['member_id']),
            'name' => $member['name'],
            'member_since' => $member['member_since']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'points' => 0,
            'message' => 'No loyalty account yet. Scan a reward QR code to get started!'
        ]);
    }
}

/**
 * Get QR code history for current user
 */
function getMyQRHistory($conn) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }
    
    $userId = intval($_SESSION['user_id']);
    
    // Get QR codes generated for this user OR redeemed by this user
    $stmt = $conn->prepare("SELECT * FROM reward_qr_codes WHERE generated_for_user = ? OR redeemed_by_user = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $qrCodes = [];
    $seenIds = [];
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['qr_id'], $seenIds)) {
            $qrCodes[] = $row;
            $seenIds[] = $row['qr_id'];
        }
    }
    $stmt->close();
    
    // Get points log
    $userEmail = '';
    $stmtU = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $userRow = $stmtU->get_result()->fetch_assoc();
    $stmtU->close();
    if ($userRow) $userEmail = $userRow['email'] ?? '';
    
    $pointsLog = [];
    $memberId = null;
    $stmt = $conn->prepare("SELECT member_id FROM loyalty_members WHERE user_id = ? OR email = ? LIMIT 1");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $memberRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($memberRow) {
        $memberId = $memberRow['member_id'];
        $stmt = $conn->prepare("SELECT * FROM loyalty_points_log WHERE member_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $pointsLog[] = $row;
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'qr_codes' => $qrCodes,
        'points_log' => $pointsLog
    ]);
}

/**
 * Validate a QR code (check if valid and not yet redeemed)
 */
function validateQR($conn) {
    $qrCode = trim($_GET['qr_code'] ?? '');
    
    if (empty($qrCode)) {
        echo json_encode(['success' => false, 'message' => 'No QR code provided']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT qr_id, qr_code, source_type, points_value, is_redeemed, expires_at, created_at FROM reward_qr_codes WHERE qr_code = ? LIMIT 1");
    $stmt->bind_param("s", $qrCode);
    $stmt->execute();
    $qr = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$qr) {
        echo json_encode(['success' => false, 'valid' => false, 'message' => 'QR code not found']);
        return;
    }
    
    $expired = ($qr['expires_at'] && strtotime($qr['expires_at']) < time());
    
    echo json_encode([
        'success' => true,
        'valid' => !$qr['is_redeemed'] && !$expired,
        'is_redeemed' => (bool)$qr['is_redeemed'],
        'is_expired' => $expired,
        'points_value' => intval($qr['points_value']),
        'source' => $qr['source_type'],
        'message' => $qr['is_redeemed'] ? 'Already redeemed' : ($expired ? 'Expired' : 'Valid - Ready to scan!')
    ]);
}

/**
 * Get customer points (for staff lookup)
 */
function getCustomerPoints($conn) {
    $email = trim($_GET['email'] ?? '');
    $phone = trim($_GET['phone'] ?? '');
    
    if (empty($email) && empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Provide email or phone']);
        return;
    }
    
    $member = null;
    if ($email) {
        $stmt = $conn->prepare("SELECT * FROM loyalty_members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    if (!$member && $phone) {
        $stmt = $conn->prepare("SELECT * FROM loyalty_members WHERE phone = ? LIMIT 1");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    if ($member) {
        echo json_encode([
            'success' => true,
            'member' => $member
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No loyalty member found']);
    }
}

/**
 * Staff-initiated QR redemption for a customer
 * Employee scans the receipt QR and awards points to a specific loyalty member
 */
function staffRedeemForCustomer($conn) {
    $auth = new Auth($conn);
    if (!$auth->isLoggedIn() || !$auth->hasPermission('pos.access')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Staff authorization required']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $qrCode   = trim($input['qr_code'] ?? '');
    $memberId = intval($input['member_id'] ?? 0);

    if (empty($qrCode)) {
        echo json_encode(['success' => false, 'message' => 'QR code is required']);
        return;
    }
    if ($memberId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a loyalty member']);
        return;
    }

    // Validate QR code
    $stmt = $conn->prepare("SELECT * FROM reward_qr_codes WHERE qr_code = ? LIMIT 1");
    $stmt->bind_param("s", $qrCode);
    $stmt->execute();
    $qr = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$qr) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code']);
        return;
    }
    if ($qr['is_redeemed']) {
        echo json_encode(['success' => false, 'message' => 'This QR code has already been redeemed']);
        return;
    }
    if ($qr['expires_at'] && strtotime($qr['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This QR code has expired']);
        return;
    }

    // Validate member exists
    $stmt = $conn->prepare("SELECT member_id, name, points FROM loyalty_members WHERE member_id = ? LIMIT 1");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Loyalty member not found']);
        return;
    }

    $pointsValue = intval($qr['points_value']);
    $staffName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Staff';

    $conn->begin_transaction();
    try {
        // Mark QR as redeemed (by staff on behalf of customer)
        $redeemedName = $member['name'] . ' (via ' . $staffName . ')';
        $stmt = $conn->prepare("UPDATE reward_qr_codes SET is_redeemed = 1, redeemed_by_user = NULL, redeemed_by_name = ?, redeemed_at = NOW() WHERE qr_id = ? AND is_redeemed = 0");
        $stmt->bind_param("si", $redeemedName, $qr['qr_id']);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'QR code was already redeemed']);
            return;
        }
        $stmt->close();

        // Add points to member
        $stmt = $conn->prepare("UPDATE loyalty_members SET points = points + ? WHERE member_id = ?");
        $stmt->bind_param("ii", $pointsValue, $memberId);
        $stmt->execute();
        $stmt->close();

        // Log the transaction
        $refId = 'QR-' . $qr['qr_code'];
        $desc = "Staff ({$staffName}) scanned receipt QR code for in-store purchase";
        $stmt = $conn->prepare("INSERT INTO loyalty_points_log (member_id, points, transaction_type, reference_id, description) VALUES (?, ?, 'QR_SCAN', ?, ?)");
        $stmt->bind_param("iiss", $memberId, $pointsValue, $refId, $desc);
        $stmt->execute();
        $stmt->close();

        // Get updated total
        $stmt = $conn->prepare("SELECT points FROM loyalty_members WHERE member_id = ?");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $updated = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'points_earned' => $pointsValue,
            'total_points' => intval($updated['points']),
            'customer_name' => $member['name'],
            'message' => "🎉 {$pointsValue} points awarded to {$member['name']}! Total: {$updated['points']} points"
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Staff QR redemption error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to redeem QR code']);
    }
}

/**
 * Helper: Find or create loyalty member for a user
 */
function findOrCreateLoyaltyMember($conn, $userId, $userName, $userEmail) {
    // Try by user_id
    $stmt = $conn->prepare("SELECT member_id FROM loyalty_members WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($member) return $member['member_id'];
    
    // Try by email
    if ($userEmail) {
        $stmt = $conn->prepare("SELECT member_id FROM loyalty_members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($member) {
            // Link user_id
            $stmt = $conn->prepare("UPDATE loyalty_members SET user_id = ? WHERE member_id = ?");
            $stmt->bind_param("ii", $userId, $member['member_id']);
            $stmt->execute();
            $stmt->close();
            return $member['member_id'];
        }
    }
    
    // Create new member (phone column is now nullable, use empty string)
    $emptyPhone = '';
    $stmt = $conn->prepare("INSERT INTO loyalty_members (name, email, phone, user_id, points, member_since) VALUES (?, ?, ?, ?, 0, CURDATE())");
    $stmt->bind_param("sssi", $userName, $userEmail, $emptyPhone, $userId);
    $stmt->execute();
    $memberId = $conn->insert_id;
    $stmt->close();
    
    return $memberId;
}

function calculateLoyaltyPoints($amount) {
    $amount = floatval($amount);
    if ($amount <= 0) {
        return 0;
    }
    return intval(floor($amount / 500) * 25);
}
?>
