<?php
/**
 * POS API — handles sale creation and stock deduction
 */

session_start();
require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

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

        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty']);
            exit;
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
            $changeAmount = max(0, $amountTendered - $total);

            // 1. Ensure discount columns exist on sales table
            $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2) DEFAULT 0");
            $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0");

            // 2. Insert sale header with subtotal, tax, discount
            $stmt = $conn->prepare("
                INSERT INTO sales (sale_reference, subtotal, tax_amount, discount_percent, discount_amount, total, payment_method, paid_amount, change_amount, cashier)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sdddddsdds", $receiptNo, $subtotal, $taxAmount, $discountPercent, $discountAmount, $total, $paymentMethod, $amountTendered, $changeAmount, $cashier);
            $stmt->execute();
            $saleId = $stmt->insert_id;

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
