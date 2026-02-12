<?php
/**
 * Purchase Order API
 * Handles all CRUD operations for purchase orders
 */

require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

$auth = new Auth($conn);
$auth->requireAuth();

if (!$auth->hasPermission('suppliers.view')) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$currentUser = $auth->getCurrentUser();

try {
    switch ($action) {
        case 'get_all':
            getAllPurchaseOrders($conn);
            break;
            
        case 'create':
            createPurchaseOrder($conn, $input, $currentUser);
            break;
            
        case 'receive':
            receivePurchaseOrder($conn, $input);
            break;
            
        case 'cancel':
            cancelPurchaseOrder($conn, $input);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function getAllPurchaseOrders($conn) {
    $query = "SELECT po.*, s.supplier_name,
              (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.po_id) as item_count
              FROM purchase_orders po
              JOIN suppliers s ON po.supplier_id = s.supplier_id
              ORDER BY po.created_at DESC";
    
    $result = $conn->query($query);
    
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $orders
    ]);
}

function createPurchaseOrder($conn, $input, $currentUser) {
    // Validate input
    if (empty($input['supplier_id']) || empty($input['items'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier and items are required'
        ]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Generate PO number
        $poNumber = generatePONumber($conn);
        
        // Calculate total
        $totalAmount = 0;
        foreach ($input['items'] as $item) {
            $totalAmount += $item['quantity'] * $item['unit_cost'];
        }
        
        // Insert purchase order
        $query = "INSERT INTO purchase_orders (po_number, supplier_id, total_amount, notes, ordered_by, status) 
                  VALUES (?, ?, ?, ?, ?, 'Pending')";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            'sidsi',
            $poNumber,
            $input['supplier_id'],
            $totalAmount,
            $input['notes'],
            $currentUser['user_id']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create purchase order');
        }
        
        $poId = $conn->insert_id;
        
        // Insert items
        $query = "INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_cost, line_total) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        
        foreach ($input['items'] as $item) {
            $lineTotal = $item['quantity'] * $item['unit_cost'];
            $stmt->bind_param(
                'iiidd',
                $poId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_cost'],
                $lineTotal
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to add item to purchase order');
            }
        }
        
        // Update status to Ordered
        $query = "UPDATE purchase_orders SET status = 'Ordered', ordered_date = NOW() WHERE po_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $poId);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Purchase order created successfully',
            'po_id' => $poId,
            'po_number' => $poNumber
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function receivePurchaseOrder($conn, $input) {
    if (empty($input['po_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'PO ID is required'
        ]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if PO exists and is in Ordered status
        $query = "SELECT * FROM purchase_orders WHERE po_id = ? AND status = 'Ordered'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $input['po_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Purchase order not found or not in Ordered status');
        }
        
        // Get PO items
        $query = "SELECT * FROM purchase_order_items WHERE po_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $input['po_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Update inventory for each item
        while ($item = $result->fetch_assoc()) {
            $query = "UPDATE products 
                      SET stock_quantity = stock_quantity + ? 
                      WHERE product_id = ?";
            
            $stmt2 = $conn->prepare($query);
            $stmt2->bind_param('ii', $item['quantity'], $item['product_id']);
            
            if (!$stmt2->execute()) {
                throw new Exception('Failed to update inventory for product ' . $item['product_id']);
            }
            
            // Update received quantity in PO items
            $query = "UPDATE purchase_order_items 
                      SET received_quantity = quantity 
                      WHERE po_item_id = ?";
            
            $stmt2 = $conn->prepare($query);
            $stmt2->bind_param('i', $item['po_item_id']);
            $stmt2->execute();
        }
        
        // Update PO status to Received
        $query = "UPDATE purchase_orders 
                  SET status = 'Received', received_date = NOW() 
                  WHERE po_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $input['po_id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update purchase order status');
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Purchase order received successfully. Inventory updated.'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function cancelPurchaseOrder($conn, $input) {
    if (empty($input['po_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'PO ID is required'
        ]);
        return;
    }
    
    // Check if PO can be cancelled (only Pending orders)
    $query = "SELECT status FROM purchase_orders WHERE po_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $input['po_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Purchase order not found'
        ]);
        return;
    }
    
    $po = $result->fetch_assoc();
    
    if ($po['status'] !== 'Pending' && $po['status'] !== 'Ordered') {
        echo json_encode([
            'success' => false,
            'message' => 'Only Pending or Ordered purchase orders can be cancelled'
        ]);
        return;
    }
    
    // Update status
    $query = "UPDATE purchase_orders SET status = 'Cancelled' WHERE po_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $input['po_id']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Purchase order cancelled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cancel purchase order'
        ]);
    }
}

function generatePONumber($conn) {
    $prefix = 'PO-' . date('Ymd') . '-';
    
    // Get last PO number for today
    $query = "SELECT po_number FROM purchase_orders 
              WHERE po_number LIKE ? 
              ORDER BY po_id DESC LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $search = $prefix . '%';
    $stmt->bind_param('s', $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = intval(substr($row['po_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}
