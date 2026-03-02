<?php
/**
 * Order Handler - Processes online orders
 * Saves to online_orders + online_order_items tables
 * Creates POS notification
 */

// Prevent any stray output from breaking JSON
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Load config FIRST so session security settings are applied before session_start()
require_once 'db_connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Throw exceptions for mysqli errors so we can return real messages
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Clean any stray output from includes
ob_clean();

header('Content-Type: application/json');

// Require login to place orders
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to place an order. Please sign in or create an account.']);
    ob_end_flush();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    ob_end_flush();
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No items in order']);
    ob_end_flush();
    exit;
}

$items = $input['items'];
$paymentMethod = $input['payment_method'] ?? 'Cash on Pickup';
$customerName = 'Guest';
$customerId = null;
$email = null;

// Get customer info from session if logged in
if (isset($_SESSION['user_id'])) {
    $customerId = intval($_SESSION['user_id']);
    $customerName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Customer';
    
    // Get email from DB
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $email = $row['email'];
    }
    $stmt->close();
} else {
    $customerName = $input['customer_name'] ?? 'Guest';
}

// Calculate total using SERVER-SIDE prices from the database (not client-supplied)
$totalAmount = 0;
$verifiedItems = [];
foreach ($items as $item) {
    $productId = intval($item['id']);
    $qty = intval($item['qty'] ?? $item['quantity'] ?? 1);
    
    // Fetch real price from database
    // Use selling_price when available, fall back to price
    $stmt = $conn->prepare("SELECT product_id, name, COALESCE(selling_price, price) AS unit_price, stock_quantity FROM products WHERE product_id = ? AND is_active = 1 AND stock_quantity > 0 AND (expiry_date IS NULL OR DATE(expiry_date) >= CURDATE())");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$product) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product unavailable or expired: ' . ($item['name'] ?? $productId)]);
        ob_end_flush();
        exit;
    }
    
    if ($product['stock_quantity'] < $qty) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Insufficient stock for: ' . $product['name'] . ' (available: ' . $product['stock_quantity'] . ')']);
        ob_end_flush();
        exit;
    }
    
    $realPrice = floatval($product['unit_price']);
    $totalAmount += $realPrice * $qty;
    $verifiedItems[] = [
        'id' => $product['product_id'],
        'name' => $product['name'],
        'price' => $realPrice,
        'qty' => $qty
    ];
}

// ===== EXPIRY ENFORCEMENT (FIFO) =====
require_once 'ExpiryEnforcement.php';
$expiryEnforcer = new ExpiryEnforcement($conn);

// Validate cart for expired products
$expiryCheck = $expiryEnforcer->validateCart($verifiedItems);
if (!$expiryCheck['valid']) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Cannot process order',
        'errors' => $expiryCheck['errors']
    ]);
    ob_end_flush();
    exit;
}

// ===== RX PRESCRIPTION ENFORCEMENT =====
require_once 'RxEnforcement.php';
$rxEnforcer = new RxEnforcement($conn);

// Check if cart contains prescription medications
$has_rx_products = false;
$rx_product_names = [];
try {
    $rxCheck = $rxEnforcer->checkCartForRxProducts($verifiedItems);
    $has_rx_products = $rxCheck['has_rx'];
    $rx_product_names = array_column($rxCheck['rx_products'], 'name');
} catch (Exception $e) {
    // Log but don't block the order if Rx check fails
    error_log('RxEnforcement check failed: ' . $e->getMessage());
}

// ===== LOYALTY POINTS REDEMPTION =====
$pointsRedeemed = 0.0;
$loyaltyMemberId = null;
$pointsDiscount = 0.0;

// Ensure decimal points support in loyalty schema
try {
    $conn->query("ALTER TABLE loyalty_members MODIFY COLUMN points DECIMAL(12,2) NOT NULL DEFAULT 0");
} catch (Exception $e) {
}
try {
    $conn->query("ALTER TABLE loyalty_points_log MODIFY COLUMN points DECIMAL(12,2) NOT NULL DEFAULT 0");
} catch (Exception $e) {
}

if ($customerId !== null && isset($input['points_to_redeem']) && $input['points_to_redeem'] > 0) {
    $requestedPoints = round((float)$input['points_to_redeem'], 2);
    
    // Verify user has a loyalty account and enough points
    $stmt = $conn->prepare("SELECT member_id, points FROM loyalty_members WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($member && (float)$member['points'] + 0.0001 >= $requestedPoints) {
        $availablePoints = round((float)$member['points'], 2);
        
        // Don't let discount exceed order total
        $maxRedeemable = min($requestedPoints, round($totalAmount, 2));
        $maxRedeemable = round($maxRedeemable, 2);
        
        if ($maxRedeemable > 0 && $maxRedeemable <= $availablePoints + 0.0001) {
            $pointsRedeemed = $maxRedeemable;
            $pointsDiscount = $pointsRedeemed; // 1 point = ₱1
            $loyaltyMemberId = $member['member_id'];
        }
    }
}

if (!in_array($paymentMethod, ['Cash on Pickup', 'Loyalty Points', 'GCash'], true)) {
    $paymentMethod = 'Cash on Pickup';
}

if ($paymentMethod === 'Loyalty Points') {
    $requiredPoints = round($totalAmount, 2);
    if ($pointsDiscount + 0.0001 < $requiredPoints) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient loyalty points for Loyalty Points payment method.'
        ]);
        ob_end_flush();
        exit;
    }
}

// ===== SENIOR/PWD DISCOUNT CLAIM (verified at pickup, NOT applied now) =====
$scPwdClaimed = (!empty($input['senior_discount']) && $input['senior_discount'] == 1) ? 1 : 0;
// NOTE: The 20% discount is NOT subtracted from the order total here.
// It will only be applied by the POS cashier after verifying the customer's
// SC/PWD ID at pickup time. This prevents abuse of the discount.

// Apply points discount to total
$totalAmount = max(0, $totalAmount - $pointsDiscount);
// Store original total for points earning calculation
$originalTotal = $totalAmount + $pointsDiscount;

// Schema checks (handle older migrations) — cached per-request (resettable)
$__columnExistsCache = [];

function resetColumnExistsCache() {
    global $__columnExistsCache;
    $__columnExistsCache = [];
}

function columnExists($conn, $table, $column) {
    global $__columnExistsCache;
    $key = "$table.$column";
    if (isset($__columnExistsCache[$key])) return $__columnExistsCache[$key];

    // MariaDB/MySQL do not reliably allow placeholders in SHOW COLUMNS ... LIKE ?
    // Use INFORMATION_SCHEMA instead.
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = ($result && $result->num_rows > 0);
    $stmt->close();

    $__columnExistsCache[$key] = $exists;
    return $exists;
}

function ensureOnlineOrderSchema($conn) {
    $safeQuery = function($sql) use ($conn) {
        try {
            $conn->query($sql);
        } catch (Exception $e) {
            // Ignore "already exists" / "can't drop" cases; order flow should continue
        }
    };

    // Ensure required columns exist for online ordering (handles older databases)
    if (!columnExists($conn, 'online_orders', 'status')) {
        $safeQuery("ALTER TABLE online_orders ADD COLUMN status ENUM('Pending','Confirmed','Preparing','Ready','Completed','Cancelled') DEFAULT 'Pending'");
    }
    if (!columnExists($conn, 'online_orders', 'payment_method')) {
        $safeQuery("ALTER TABLE online_orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash on Pickup'");
    }
    if (!columnExists($conn, 'online_orders', 'updated_at')) {
        $safeQuery("ALTER TABLE online_orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    // Email column name varies across deployments (email vs customer_email)
    if (!columnExists($conn, 'online_orders', 'email') && !columnExists($conn, 'online_orders', 'customer_email')) {
        // Keep nullable for guest orders
        $safeQuery("ALTER TABLE online_orders ADD COLUMN email VARCHAR(100) NULL");
    }
    if (!columnExists($conn, 'online_order_items', 'product_name')) {
        $safeQuery("ALTER TABLE online_order_items ADD COLUMN product_name VARCHAR(255) NOT NULL DEFAULT ''");
    }
    if (!columnExists($conn, 'online_order_items', 'subtotal')) {
        $safeQuery("ALTER TABLE online_order_items ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    // SC/PWD discount claim flag — verified at POS pickup, not applied at checkout
    if (!columnExists($conn, 'online_orders', 'sc_pwd_claimed')) {
        $safeQuery("ALTER TABLE online_orders ADD COLUMN sc_pwd_claimed TINYINT(1) NOT NULL DEFAULT 0");
    }

    // If the table is the "medicine_id" schema, migrate it to support product_id (used by our cart/products)
    if (columnExists($conn, 'online_order_items', 'medicine_id') && !columnExists($conn, 'online_order_items', 'product_id')) {
        $safeQuery("ALTER TABLE online_order_items ADD COLUMN product_id INT NULL AFTER order_id");
        $safeQuery("ALTER TABLE online_order_items DROP FOREIGN KEY online_order_items_ibfk_2");
        $safeQuery("ALTER TABLE online_order_items MODIFY medicine_id INT NULL");
        $safeQuery("ALTER TABLE online_order_items ADD CONSTRAINT fk_online_order_items_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT");
    }
}

ensureOnlineOrderSchema($conn);

// Schema migration may have added columns; reset cache so future checks are accurate
resetColumnExistsCache();

$emailColName = null;
if (columnExists($conn, 'online_orders', 'customer_email')) {
    $emailColName = 'customer_email';
} elseif (columnExists($conn, 'online_orders', 'email')) {
    $emailColName = 'email';
}

$phoneColName = null;
if (columnExists($conn, 'online_orders', 'customer_phone')) {
    $phoneColName = 'customer_phone';
} elseif (columnExists($conn, 'online_orders', 'contact_number')) {
    $phoneColName = 'contact_number';
}

$hasEmailCol = ($emailColName !== null);
$hasPhoneCol = ($phoneColName !== null);
$hasOrderNumberCol = columnExists($conn, 'online_orders', 'order_number');
$hasItemProductNameCol = columnExists($conn, 'online_order_items', 'product_name');

function firstExistingColumn($conn, $table, $candidates) {
    foreach ($candidates as $col) {
        if (columnExists($conn, $table, $col)) return $col;
    }
    return null;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Some deployments include a required online_orders.order_number column
    $orderNumber = 'ONL-' . date('YmdHis') . '-' . mt_rand(100, 999);

    // Insert order — dynamically include legacy columns if present
    $emailVal = $email ?? '';
    $phoneVal = $input['contact_number'] ?? $input['customer_phone'] ?? $input['phone'] ?? '';
    $addressColName = firstExistingColumn($conn, 'online_orders', ['delivery_address', 'address', 'customer_address']);
    $addressVal = $input['delivery_address'] ?? $input['address'] ?? $input['customer_address'] ?? 'Pickup at Calloway Pharmacy';

    // Advanced schema support: online_orders may require a FK customer_id referencing customers table
    $hasCustomersTable = false;
    try {
        $chk = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' LIMIT 1");
        $hasCustomersTable = ($chk && $chk->num_rows > 0);
    } catch (Exception $e) {
        $hasCustomersTable = false;
    }

    $customerEmailInput = trim($input['customer_email'] ?? $input['email'] ?? $emailVal);
    $customerPhoneInput = trim($input['customer_phone'] ?? $input['phone'] ?? $phoneVal);

    // If customers table exists and online_orders.customer_id exists, ensure we have a customer_id
    if ($hasCustomersTable && columnExists($conn, 'online_orders', 'customer_id')) {
        // If not logged in, we still create/find a customer row
        if ($customerEmailInput === '') {
            // Avoid empty for schemas that require customer_email
            $customerEmailInput = 'guest_' . date('YmdHis') . '_' . mt_rand(100, 999) . '@local.invalid';
        }
        if ($customerPhoneInput === '') {
            $customerPhoneInput = 'N/A';
        }

        // Find by email first
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $customerEmailInput);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && !empty($existing['customer_id'])) {
            $customerId = intval($existing['customer_id']);
        } else {
            $qr = 'QR-' . date('YmdHis') . '-' . mt_rand(100000, 999999);
            $custAddress = $addressVal;
            $stmt = $conn->prepare("INSERT INTO customers (customer_name, email, phone, address, qr_code, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param('sssss', $customerName, $customerEmailInput, $customerPhoneInput, $custAddress, $qr);
            $stmt->execute();
            $customerId = intval($conn->insert_id);
            $stmt->close();
        }
    }

    $cols = [];
    $placeholders = [];
    $types = '';
    $params = [];

    if (columnExists($conn, 'online_orders', 'customer_id')) {
        if ($customerId !== null) {
            $cols[] = 'customer_id';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = $customerId;
        }
    }

    if (columnExists($conn, 'online_orders', 'customer_name')) {
        $cols[] = 'customer_name';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $customerName;
    }

    // Populate both email columns if present (some schemas have both email + customer_email)
    if (columnExists($conn, 'online_orders', 'email')) {
        $cols[] = 'email';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $customerEmailInput;
    }
    if (columnExists($conn, 'online_orders', 'customer_email')) {
        $cols[] = 'customer_email';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $customerEmailInput;
    }

    // Populate both phone columns if present
    if (columnExists($conn, 'online_orders', 'customer_phone')) {
        $cols[] = 'customer_phone';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $customerPhoneInput;
    } elseif ($phoneColName !== null) {
        $cols[] = $phoneColName;
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $customerPhoneInput;
    }

    if ($addressColName !== null) {
        $cols[] = $addressColName;
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $addressVal;
    }

    if (columnExists($conn, 'online_orders', 'subtotal')) {
        $cols[] = 'subtotal';
        $placeholders[] = '?';
        $types .= 'd';
        $params[] = $totalAmount;
    }

    if ($hasOrderNumberCol) {
        $cols[] = 'order_number';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $orderNumber;
    }

    $cols[] = 'total_amount';
    $placeholders[] = '?';
    $types .= 'd';
    $params[] = $totalAmount;

    if (columnExists($conn, 'online_orders', 'payment_method')) {
        $cols[] = 'payment_method';
        $placeholders[] = '?';
        $types .= 's';
        // Map to enum-style values if the advanced schema is present
        if (columnExists($conn, 'online_orders', 'order_status')) {
            $mapped = 'cash_on_delivery';
            if (stripos($paymentMethod, 'gcash') !== false) $mapped = 'mobile_money';
            $params[] = $mapped;
        } else {
            $params[] = $paymentMethod;
        }
    }

    if (columnExists($conn, 'online_orders', 'order_status')) {
        $cols[] = 'order_status';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = 'pending';
    }

    if (columnExists($conn, 'online_orders', 'status')) {
        $cols[] = 'status';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = 'Pending';
    }

    // SC/PWD discount claim flag
    if (columnExists($conn, 'online_orders', 'sc_pwd_claimed')) {
        $cols[] = 'sc_pwd_claimed';
        $placeholders[] = '?';
        $types .= 'i';
        $params[] = $scPwdClaimed;
    }

    $sql = 'INSERT INTO online_orders (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) {
            $bindArgs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    }

    $stmt->execute();
    $orderId = $conn->insert_id;
    $stmt->close();

    // ===== FLAG ORDER FOR RX APPROVAL IF NEEDED =====
    if ($has_rx_products) {
        $rxEnforcer->flagOrderForRxApproval($orderId);
    }

    // Insert order items (support different schemas)
    $hasProductIdCol = columnExists($conn, 'online_order_items', 'product_id');
    $hasMedicineIdCol = columnExists($conn, 'online_order_items', 'medicine_id');
    $itemPriceCol = firstExistingColumn($conn, 'online_order_items', ['unit_price', 'price']);

    if (!$hasProductIdCol && $hasMedicineIdCol) {
        throw new Exception('Online order items table is not compatible with products ordering yet (missing product_id).');
    }

    if ($hasItemProductNameCol && $itemPriceCol) {
        $stmt = $conn->prepare("INSERT INTO online_order_items (order_id, product_id, product_name, quantity, {$itemPriceCol}, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    } elseif ($itemPriceCol) {
        $stmt = $conn->prepare("INSERT INTO online_order_items (order_id, product_id, quantity, {$itemPriceCol}, subtotal) VALUES (?, ?, ?, ?, ?)");
    } else {
        // Last-resort: columns unknown
        throw new Exception('Online order items schema is missing price column');
    }
    
    // Prepared statement for stock deduction (no string interpolation)
    $stockStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");
    
    $itemsList = [];
    foreach ($verifiedItems as $item) {
        $productId = intval($item['id']);
        $productName = $item['name'];
        $quantity = intval($item['qty']);
        $price = floatval($item['price']);
        $subtotal = $price * $quantity;
        
        if ($hasItemProductNameCol) {
            $stmt->bind_param("iisidd", $orderId, $productId, $productName, $quantity, $price, $subtotal);
        } else {
            $stmt->bind_param("iiidd", $orderId, $productId, $quantity, $price, $subtotal);
        }
        $stmt->execute();
        
        $itemsList[] = $quantity . 'x ' . $productName . ' (₱' . number_format($price, 2) . ')';
        
        // Reduce stock using prepared statement
        $stockStmt->bind_param("iii", $quantity, $productId, $quantity);
        $stockStmt->execute();

        // Check if stock was actually deducted
        if ($stockStmt->affected_rows === 0) {
            throw new Exception("Insufficient stock for: $productName");
        }
    }
    $stmt->close();
    $stockStmt->close();

    // Create POS notification
    $orderRef = 'ONL-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
    $notifTitle = '🛒 New Online Order #' . $orderRef;
    $notifMessage = "Customer: $customerName\n";
    $notifMessage .= "Payment: $paymentMethod\n";
    $notifMessage .= "Items:\n" . implode("\n", $itemsList) . "\n";
    $notifMessage .= "Total: ₱" . number_format($totalAmount, 2) . "\n";
    $notifMessage .= "Status: Pickup Only\n";
    $notifMessage .= "Order Time: " . date('M d, Y h:i A');

    $stmt = $conn->prepare("INSERT INTO pos_notifications (order_id, type, title, message) VALUES (?, 'online_order', ?, ?)");
    $stmt->bind_param("iss", $orderId, $notifTitle, $notifMessage);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // ===== LOYALTY POINTS SYSTEM =====
    // Security rule: Online orders must NOT receive earned points or redeemable
    // QR rewards until POS validates payment at pickup.
    // Points are awarded in online_order_api.php (mark_picked_up -> awardLoyaltyForPickup).
    $responseMessages = [];
    
    if ($customerId !== null && $customerId > 0) {
        try {
            // Get user details from users table
            $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                $userName = $user['full_name'] ?? $customerName;
                $userEmail = $user['email'] ?? $email ?? '';
                $userPhone = '';
                
                // Find or create loyalty member
                $stmt = $conn->prepare("SELECT member_id, points FROM loyalty_members WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $userEmail);
                $stmt->execute();
                $member = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $memberId = null;
                if ($member) {
                    $memberId = $member['member_id'];
                } else {
                    // Create new member with 0 points initially
                    $stmt = $conn->prepare("INSERT INTO loyalty_members (name, email, phone, points, member_since) VALUES (?, ?, ?, 0, CURDATE())");
                    $stmt->bind_param("sss", $userName, $userEmail, $userPhone);
                    $stmt->execute();
                    $memberId = $conn->insert_id;
                    $stmt->close();
                }
                
                // STEP 1: Process points redemption if requested
                if ($pointsRedeemed > 0 && $loyaltyMemberId === $memberId) {
                    // Deduct redeemed points
                    $stmt = $conn->prepare("UPDATE loyalty_members SET points = points - ? WHERE member_id = ?");
                    $stmt->bind_param("di", $pointsRedeemed, $memberId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Log redemption
                    $refId = 'ORDER-' . $orderId;
                    $negativePoints = -$pointsRedeemed; // Store as negative for REDEEM
                    $stmt = $conn->prepare("INSERT INTO loyalty_points_log (member_id, points, transaction_type, reference_id) VALUES (?, ?, 'REDEEM', ?)");
                    $stmt->bind_param("ids", $memberId, $negativePoints, $refId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $responseMessages[] = "You saved ₱" . number_format($pointsDiscount, 2) . " using " . number_format($pointsRedeemed, 2) . " points!";
                }
                
                // IMPORTANT: Do NOT award earned points here.
                // Online-order earnings are only granted after POS pickup validation.
            }
        } catch (Exception $e) {
            // Log loyalty error but don't fail the order
            error_log('Loyalty points error: ' . $e->getMessage());
        }
    }

    // Clean any stray output, then send JSON
    ob_clean();
    
    $successMessage = 'Order placed successfully!';
    if (!empty($responseMessages)) {
        $successMessage = implode(' ', $responseMessages);
    }
    
    // Do NOT generate redeemable reward QR here.
    // QR/points must only be issued after POS pickup validation.

    $response = [
        'success' => true,
        'order_id' => $orderId,
        'order_ref' => $orderRef,
        'message' => $successMessage
    ];
    
    // ===== ADD RX WARNING IF ORDER CONTAINS PRESCRIPTION MEDICATIONS =====
    if ($has_rx_products) {
        $rxWarning = $rxEnforcer->getRxCustomerWarning();
        $response['rx_warning'] = $rxWarning;
        $response['requires_prescription'] = true;
        $response['rx_products'] = $rx_product_names;
    }
    
    $response['loyalty_pending_validation'] = true;
    $response['loyalty_message'] = 'Loyalty rewards will be credited after payment is validated at pickup.';
    if ($pointsRedeemed > 0) {
        $response['points_redeemed'] = $pointsRedeemed;
        $response['discount_applied'] = $pointsDiscount;
    }
    if ($scPwdClaimed) {
        $response['sc_pwd_claimed'] = true;
        $response['sc_pwd_message'] = 'SC/PWD discount will be applied after ID verification at pickup.';
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    error_log('Order error: ' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    // Show user-safe message; only expose "insufficient stock" messages
    $userMessage = (strpos($e->getMessage(), 'Insufficient stock') !== false) 
        ? $e->getMessage() 
        : 'Failed to place order. Please try again.';
    echo json_encode(['success' => false, 'message' => $userMessage]);
}

ob_end_flush();
?>
