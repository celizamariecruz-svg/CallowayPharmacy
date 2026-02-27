<?php
/**
 * POS API — handles sale creation and stock deduction
 */

// Load config FIRST so session security settings are applied before session_start()
require_once 'db_connection.php';
require_once 'Auth.php';

// Session is started by Auth constructor (after config.php sets secure cookie params)
header('Content-Type: application/json');

/**
 * Check if a column exists in a table (MySQL 5.7+ compatible).
 */
function posColumnExists($conn, $table, $column) {
    static $cache = [];
    $key = "$table.$column";
    if (isset($cache[$key])) return $cache[$key];
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cache[$key] = intval($row['cnt']) > 0;
    return $cache[$key];
}

$auth = new Auth($conn);

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!$auth->hasPermission('pos.access')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'create_sale':
        $input = json_decode(file_get_contents('php://input'), true);

        $items           = $input['items'] ?? [];
        $total           = floatval($input['total'] ?? 0);
        $paymentMethod   = $input['payment_method'] ?? 'Cash';
        $amountTendered  = floatval($input['amount_tendered'] ?? $total);
        // Server-generated receipt number — never trust client-supplied values
        $receiptNo       = 'TX-' . substr(time(), -8) . '-' . mt_rand(100, 999);
        // Cashier always comes from session — prevent impersonation
        $cashier         = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'POS';

        // Loyalty points data
        $loyaltyMemberId   = !empty($input['loyalty_member_id']) ? intval($input['loyalty_member_id']) : null;
        $loyaltyMemberName = $input['loyalty_member_name'] ?? null;
        $pointsToRedeem    = floatval($input['points_to_redeem'] ?? 0);

        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty']);
            exit;
        }

        // Ensure discount columns exist BEFORE the transaction (DDL causes implicit commit)
        try {
            if (!posColumnExists($conn, 'sales', 'discount_percent')) {
                $conn->query("ALTER TABLE sales ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0");
            }
            if (!posColumnExists($conn, 'sales', 'discount_amount')) {
                $conn->query("ALTER TABLE sales ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0");
            }
            // Add loyalty columns if needed
            if (!posColumnExists($conn, 'sales', 'loyalty_member_id')) {
                $conn->query("ALTER TABLE sales ADD COLUMN loyalty_member_id INT NULL");
            }
            if (!posColumnExists($conn, 'sales', 'points_redeemed')) {
                $conn->query("ALTER TABLE sales ADD COLUMN points_redeemed DECIMAL(12,2) DEFAULT 0");
            }
            // Ensure loyalty_members has decimal points
            $conn->query("ALTER TABLE loyalty_members MODIFY COLUMN points DECIMAL(12,2) NOT NULL DEFAULT 0");
            // Ensure loyalty_points_log exists with decimal amounts
            $conn->query("CREATE TABLE IF NOT EXISTS loyalty_points_log (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                points DECIMAL(12,2) NOT NULL,
                transaction_type ENUM('EARN','REDEEM','QR_SCAN','BONUS','ADJUSTMENT') NOT NULL DEFAULT 'EARN',
                reference_id VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_member (member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // Compatibility for older/alternate schemas
            if (!posColumnExists($conn, 'loyalty_points_log', 'points')) {
                $conn->query("ALTER TABLE loyalty_points_log ADD COLUMN points DECIMAL(12,2) NOT NULL DEFAULT 0");
            }
            if (!posColumnExists($conn, 'loyalty_points_log', 'transaction_type')) {
                $conn->query("ALTER TABLE loyalty_points_log ADD COLUMN transaction_type ENUM('EARN','REDEEM','QR_SCAN','BONUS','ADJUSTMENT') NOT NULL DEFAULT 'EARN'");
            }
            $conn->query("ALTER TABLE loyalty_points_log MODIFY COLUMN points DECIMAL(12,2) NOT NULL");
        } catch (Exception $schemaEx) {
            // Columns may already exist or lack permission — non-fatal
            error_log('POS schema migration note: ' . $schemaEx->getMessage());
        }

        // Validate loyalty points if being used
        if ($pointsToRedeem > 0 && !$loyaltyMemberId) {
            echo json_encode(['success' => false, 'message' => 'Select a loyalty member to redeem points']);
            exit;
        }

        if ($pointsToRedeem > 0 && $loyaltyMemberId) {
            $checkStmt = $conn->prepare("SELECT points FROM loyalty_members WHERE member_id = ?");
            $checkStmt->bind_param("i", $loyaltyMemberId);
            $checkStmt->execute();
            $memberRow = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$memberRow) {
                echo json_encode(['success' => false, 'message' => 'Loyalty member not found']);
                exit;
            }

            $availablePoints = floatval($memberRow['points']);
            if ($pointsToRedeem > $availablePoints) {
                echo json_encode(['success' => false, 'message' => 'Insufficient loyalty points. Available: ' . number_format($availablePoints, 2)]);
                exit;
            }
        }

        try {
            $conn->begin_transaction();

            // Verify all items and recalculate total from server-side prices
            $verifiedItems = [];
            $serverTotal = 0;
            foreach ($items as $item) {
                $productId = intval($item['id']);
                $qty = intval($item['qty']);
                $perPiece = !empty($item['perPiece']);

                $lookupStmt = $conn->prepare("SELECT product_id, name, selling_price, price_per_piece, stock_quantity, pieces_per_box FROM products WHERE product_id = ? AND is_active = 1");
                $lookupStmt->bind_param("i", $productId);
                $lookupStmt->execute();
                $product = $lookupStmt->get_result()->fetch_assoc();
                $lookupStmt->close();

                if (!$product) {
                    throw new Exception("Product not found (ID: $productId)");
                }

                if ($perPiece) {
                    $realPrice = floatval($product['price_per_piece'] ?? 0);
                    if ($realPrice <= 0) {
                        $ppb = max(1, intval($product['pieces_per_box'] ?? 1));
                        $realPrice = round(floatval($product['selling_price']) / $ppb, 2);
                    }
                } else {
                    $realPrice = floatval($product['selling_price']);
                }

                $lineTotal = $realPrice * $qty;
                $serverTotal += $lineTotal;

                $verifiedItems[] = [
                    'id' => $product['product_id'],
                    'name' => $product['name'],
                    'price' => $realPrice,
                    'qty' => $qty,
                    'lineTotal' => $lineTotal,
                    'perPiece' => $perPiece,
                    'piecesPerBox' => intval($product['pieces_per_box'] ?? $item['piecesPerBox'] ?? 1)
                ];
            }

            // Apply 12% VAT and optional discount
            $subtotal = $serverTotal;
            $taxAmount = round($subtotal * 0.12, 2);
            $beforeDiscount = round($subtotal + $taxAmount, 2);
            
            // Apply discount if provided (e.g., 20% SC/PWD discount)
            $discountPercent = floatval($input['discount_percent'] ?? 0);
            $discountAmount = 0;
            if ($discountPercent > 0 && $discountPercent <= 100) {
                // Enforce ₱200 minimum subtotal for 20% SC/PWD discount
                if ($discountPercent == 20 && $subtotal < 200) {
                    echo json_encode(['success' => false, 'message' => 'Subtotal must be at least ₱200.00 to apply the 20% Senior/PWD discount.']);
                    ob_end_flush();
                    exit;
                }
                $discountAmount = round($beforeDiscount * ($discountPercent / 100), 2);
            }
            $total = round($beforeDiscount - $discountAmount, 2);
            $pointsApplied = ($pointsToRedeem > 0 && $loyaltyMemberId) ? min($pointsToRedeem, $total) : 0;
            $cashDue = max(0, $total - $pointsApplied);

            if ($paymentMethod === 'loyalty_points' && $amountTendered + 0.005 < $cashDue) {
                echo json_encode(['success' => false, 'message' => 'Cash is not enough for the remaining amount (₱' . number_format($cashDue, 2) . ')']);
                ob_end_flush();
                exit;
            }

            $changeAmount = max(0, $amountTendered - $cashDue);
            $paymentMethodStored = ($paymentMethod === 'loyalty_points' && $pointsApplied > 0 && $cashDue > 0)
                ? 'loyalty_points+cash'
                : $paymentMethod;

            // 2. Insert sale header with subtotal, tax, discount, loyalty info
            $stmt = $conn->prepare("
                INSERT INTO sales (sale_reference, subtotal, tax_amount, discount_percent, discount_amount, total, payment_method, paid_amount, change_amount, cashier, loyalty_member_id, points_redeemed)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sdddddsddsid", $receiptNo, $subtotal, $taxAmount, $discountPercent, $discountAmount, $total, $paymentMethodStored, $amountTendered, $changeAmount, $cashier, $loyaltyMemberId, $pointsApplied);
            $stmt->execute();
            $saleId = $stmt->insert_id;

            // Deduct loyalty points if used
            if ($pointsApplied > 0 && $loyaltyMemberId) {
                // Deduct from member's balance
                $deductStmt = $conn->prepare("UPDATE loyalty_members SET points = points - ? WHERE member_id = ? AND points >= ?");
                $deductStmt->bind_param("did", $pointsApplied, $loyaltyMemberId, $pointsApplied);
                $deductStmt->execute();
                
                if ($deductStmt->affected_rows === 0) {
                    throw new Exception("Failed to deduct loyalty points - insufficient balance");
                }
                $deductStmt->close();

                // Log the points transaction
                $negPoints = -$pointsApplied;
                $logType = 'REDEEM';
                $logStmt = $conn->prepare("INSERT INTO loyalty_points_log (member_id, points, transaction_type, reference_id) VALUES (?, ?, ?, ?)");
                $logStmt->bind_param("idss", $loyaltyMemberId, $negPoints, $logType, $receiptNo);
                $logStmt->execute();
                $logStmt->close();
            }

            // 2. Insert sale items + deduct stock
            $itemStmt = $conn->prepare("
                INSERT INTO sale_items (sale_id, product_id, name, unit_price, quantity, line_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stockStmt = $conn->prepare("
                UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?
            ");

            foreach ($verifiedItems as $item) {
                $productId  = $item['id'];
                $name       = $item['name'];
                $price      = $item['price'];
                $qty        = $item['qty'];
                $lineTotal  = $item['lineTotal'];
                $perPiece   = $item['perPiece'];

                // Insert sale item
                $itemStmt->bind_param("iisdid", $saleId, $productId, $name, $price, $qty, $lineTotal);
                $itemStmt->execute();

                // Deduct stock
                if ($perPiece) {
                    $piecesPerBox = max(1, $item['piecesPerBox']);
                    $boxesToDeduct = intval(ceil($qty / $piecesPerBox));
                } else {
                    $boxesToDeduct = $qty;
                }

                $stockStmt->bind_param("iii", $boxesToDeduct, $productId, $boxesToDeduct);
                $stockStmt->execute();

                if ($stockStmt->affected_rows === 0) {
                    throw new Exception("Insufficient stock for product: $name");
                }
            }

            // 3. Log activity
            $userId = $_SESSION['user_id'] ?? 0;
            $auth->logActivity($userId, 'sale_completed', 'POS', "Sale $receiptNo — ₱" . number_format($total, 2));

            $conn->commit();

            // 4. Generate one-time reward QR code for this POS sale
            $rewardQrCode = null;
            $rewardQrExpires = null;
            try {
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
                    INDEX idx_redeemed (is_redeemed)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $rewardQrCode = 'RWD-' . strtoupper(bin2hex(random_bytes(6))) . '-' . time();
                $rewardQrExpires = date('Y-m-d H:i:s', strtotime('+30 days'));
                $qrSourceType = 'pos';
                $qrPointsVal = intval(floor(floatval($beforeDiscount) / 500) * 25);
                $qrCustomerName = 'Walk-in Customer';

                $qrStmt = $conn->prepare("INSERT INTO reward_qr_codes (qr_code, source_type, source_order_id, sale_reference, generated_for_user, generated_for_name, points_value, expires_at) VALUES (?, ?, ?, ?, NULL, ?, ?, ?)");
                $qrStmt->bind_param("ssissis", $rewardQrCode, $qrSourceType, $saleId, $receiptNo, $qrCustomerName, $qrPointsVal, $rewardQrExpires);
                $qrStmt->execute();
                $qrStmt->close();
            } catch (Exception $e) {
                error_log('POS Reward QR generation error: ' . $e->getMessage());
            }

            $response = [
                'success'        => true,
                'message'        => 'Sale recorded successfully',
                'sale_id'        => $saleId,
                'sale_reference' => $receiptNo
            ];
            
            if ($rewardQrCode) {
                $response['reward_qr_code'] = $rewardQrCode;
                $response['reward_qr_expires'] = $rewardQrExpires;
            }

            // Include loyalty points info in response
            if ($pointsApplied > 0 && $loyaltyMemberId) {
                $response['loyalty'] = [
                    'member_id' => $loyaltyMemberId,
                    'member_name' => $loyaltyMemberName,
                    'points_redeemed' => round($pointsApplied, 2)
                ];
            }
            
            echo json_encode($response);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Sale failed: ' . $e->getMessage()]);
        }
        break;

    case 'get_recent_sales':
        $limit = intval($_GET['limit'] ?? 20);
        $stmt = $conn->prepare("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) as item_count
            FROM sales s
            ORDER BY s.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $sales = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $sales]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
