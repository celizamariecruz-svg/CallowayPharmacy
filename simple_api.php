<?php
/**
 * SIMPLE PRODUCTS API - NO PERMISSION CHECKS
 * Direct database access for getting products
 */

require_once 'db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Disable HTML error reporting for API to ensure JSON
ini_set('display_errors', 0);

// Simple session check - just check if user_id exists
session_start();
if (!isset($_SESSION['user_id'])) {
    // Return empty products instead of error - let frontend handle
    echo json_encode([
        'success' => true,
        'data' => [],
        'total' => 0,
        'message' => 'Not logged in'
    ]);
    exit;
}

$action = $_GET['action'] ?? 'get_products';
$limit = intval($_GET['limit'] ?? 1000);
$search = $_GET['search'] ?? '';

try {
    if ($action === 'get_products') {
        $where = "p.is_active = 1";
        $params = [];
        $types = '';

        if ($search) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types = 'ss';
        }

        $query = "
            SELECT 
                p.product_id,
                p.name,
                p.sku,
                p.barcode,
                p.selling_price,
                p.stock_quantity,
                c.category_name,
                p.location,
                p.expiry_date,
                p.reorder_level,
                p.is_active
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE $where
            ORDER BY p.name ASC
            LIMIT $limit
        ";

        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $products,
            'total' => count($products)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>