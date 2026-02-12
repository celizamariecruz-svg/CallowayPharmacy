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

                $lookupStmt = $conn->prepare("SELECT product_id, name, price, stock_quantity, pieces_per_box FROM products WHERE product_id = ? AND is_active = 1");
                $lookupStmt->bind_param("i", $productId);
                $lookupStmt->execute();
                $product = $lookupStmt->get_result()->fetch_assoc();
                $lookupStmt->close();

                if (!$product) {
                    throw new Exception("Product not found (ID: $productId)");
                }

                $realPrice = floatval($product['price']);
                // For per-piece, price = box price / pieces_per_box
                if ($perPiece) {
                    $ppb = max(1, intval($product['pieces_per_box'] ?? $item['piecesPerBox'] ?? 1));
                    $realPrice = round($realPrice / $ppb, 2);
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

            // Use server-calculated total
            $total = $serverTotal;
            $changeAmount = max(0, $amountTendered - $total);

            // 1. Insert sale header
            $stmt = $conn->prepare("
                INSERT INTO sales (sale_reference, total, payment_method, paid_amount, change_amount, cashier)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sdsdds", $receiptNo, $total, $paymentMethod, $amountTendered, $changeAmount, $cashier);
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

            echo json_encode([
                'success'        => true,
                'message'        => 'Sale recorded successfully',
                'sale_id'        => $saleId,
                'sale_reference' => $receiptNo
            ]);

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
