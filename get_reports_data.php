<?php
/**
 * Reports Data API
 * Provides data for reports and analytics
 */

require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

$auth = new Auth($conn);

// Check authentication
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? '';
$startDate = $_GET['start'] ?? date('Y-m-d');
$endDate = $_GET['end'] ?? date('Y-m-d');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// Validate date formats (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-d');

// Add one day to end date to include the entire end date
$endDateInclusive = date('Y-m-d', strtotime($endDate . ' +1 day'));

switch($action) {
    case 'metrics':
        if (!$auth->hasPermission('reports.sales')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        // Get sales metrics
        $query = "SELECT 
                    COUNT(DISTINCT s.sale_id) as sales_count,
                    SUM(s.total) as revenue,
                    SUM(si.quantity) as products_sold,
                    AVG(s.total) as avg_transaction
                  FROM sales s
                  LEFT JOIN sale_items si ON s.sale_id = si.sale_id
                  WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                  ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDateInclusive);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        break;
        
    case 'top_products':
        if (!$auth->hasPermission('reports.sales')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $query = "SELECT 
                    p.product_id,
                    p.name as product_name,
                    SUM(si.quantity) as total_quantity,
                    SUM(si.line_total) as total_revenue
                  FROM sale_items si
                  JOIN products p ON si.product_id = p.product_id
                  JOIN sales s ON si.sale_id = s.sale_id
                  WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                  
                  GROUP BY p.product_id, p.name
                  ORDER BY total_revenue DESC
                  LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $startDate, $endDateInclusive, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        break;
        
    case 'category_sales':
        if (!$auth->hasPermission('reports.sales')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $query = "SELECT 
                    COALESCE(c.category_name, p.category, 'Uncategorized') as category_name,
                    SUM(si.line_total) as total_revenue,
                    SUM(si.quantity) as total_quantity
                  FROM sale_items si
                  JOIN products p ON si.product_id = p.product_id
                  LEFT JOIN categories c ON p.category_id = c.category_id
                  JOIN sales s ON si.sale_id = s.sale_id
                  WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                  
                  GROUP BY category_name
                  ORDER BY total_revenue DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDateInclusive);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        break;
        
    case 'inventory_value':
        if (!$auth->hasPermission('reports.inventory')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $query = "SELECT 
                    SUM(stock_quantity * cost_price) as total_cost_value,
                    SUM(stock_quantity * selling_price) as total_selling_value
                  FROM products
                  WHERE is_active = 1";
        
        $result = $conn->query($query);
        $data = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        break;
        
    case 'top_cashiers':
        if (!$auth->hasPermission('reports.sales')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $query = "SELECT 
                    s.cashier as cashier_name,
                    COUNT(s.sale_id) as sales_count,
                    SUM(s.total) as total_revenue
                  FROM sales s
                  WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                  
                  GROUP BY s.cashier
                  ORDER BY total_revenue DESC
                  LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $startDate, $endDateInclusive, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        break;
        
    case 'export':
        // Permission check for exports
        if (!$auth->hasPermission('reports.sales')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        $type = $_GET['type'] ?? 'all';
        
        // Set CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        switch($type) {
            case 'top-products':
                fputcsv($output, ['Rank', 'Product Name', 'Quantity Sold', 'Revenue']);
                
                $query = "SELECT 
                            p.name as product_name,
                            SUM(si.quantity) as total_quantity,
                            SUM(si.line_total) as total_revenue
                          FROM sale_items si
                          JOIN products p ON si.product_id = p.product_id
                          JOIN sales s ON si.sale_id = s.sale_id
                          WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                          
                          GROUP BY p.product_id, p.name
                          ORDER BY total_revenue DESC";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $startDate, $endDateInclusive);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $rank = 1;
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [$rank++, $row['product_name'], $row['total_quantity'], $row['total_revenue']]);
                }
                break;
                
            case 'category-sales':
                fputcsv($output, ['Category', 'Sales', 'Revenue']);
                
                $query = "SELECT 
                            c.category_name,
                            SUM(si.quantity) as total_quantity,
                            SUM(si.line_total) as total_revenue
                          FROM sale_items si
                          JOIN products p ON si.product_id = p.product_id
                          JOIN categories c ON p.category_id = c.category_id
                          JOIN sales s ON si.sale_id = s.sale_id
                          WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                          
                          GROUP BY c.category_id, c.category_name
                          ORDER BY total_revenue DESC";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $startDate, $endDateInclusive);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [$row['category_name'], $row['total_quantity'], $row['total_revenue']]);
                }
                break;
                
            case 'low-stock':
                fputcsv($output, ['Product', 'Stock Quantity', 'Reorder Level']);
                
                $query = "SELECT name, stock_quantity, reorder_level FROM products 
                          WHERE is_active = 1 AND stock_quantity <= reorder_level 
                          ORDER BY stock_quantity ASC";
                $result = $conn->query($query);
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        fputcsv($output, [$row['name'], $row['stock_quantity'], $row['reorder_level']]);
                    }
                }
                break;
                
            case 'expiring':
                fputcsv($output, ['Product', 'Expiry Date', 'Days Until Expiry']);
                
                $query = "SELECT name, expiry_date, DATEDIFF(expiry_date, CURDATE()) as days_until_expiry 
                          FROM products 
                          WHERE is_active = 1 AND expiry_date IS NOT NULL AND DATEDIFF(expiry_date, CURDATE()) <= 90
                          ORDER BY days_until_expiry ASC";
                $result = $conn->query($query);
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        fputcsv($output, [$row['name'], $row['expiry_date'], $row['days_until_expiry']]);
                    }
                }
                break;
                
            case 'cashiers':
                fputcsv($output, ['Cashier', 'Sales Count', 'Total Revenue']);
                
                $query = "SELECT 
                            s.cashier as cashier_name,
                            COUNT(s.sale_id) as sales_count,
                            SUM(s.total) as total_revenue
                          FROM sales s
                          WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                          
                          GROUP BY s.cashier
                          ORDER BY total_revenue DESC";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $startDate, $endDateInclusive);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [$row['cashier_name'], $row['sales_count'], $row['total_revenue']]);
                }
                break;
        }
        
        fclose($output);
        exit;
        
    case 'export_all':
        // Permission check for exports
        if (!$auth->hasPermission('reports.sales')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        // Export comprehensive report
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="comprehensive_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['CALLOWAY PHARMACY - COMPREHENSIVE REPORT']);
        fputcsv($output, ['Period: ' . $startDate . ' to ' . $endDate]);
        fputcsv($output, []);
        
        fputcsv($output, ['=== SUMMARY METRICS ===']);
        fputcsv($output, ['Metric', 'Value']);
        
        $query = "SELECT 
                    COUNT(DISTINCT s.sale_id) as sales_count,
                    SUM(s.total) as revenue,
                    SUM(si.quantity) as products_sold,
                    AVG(s.total) as avg_transaction
                  FROM sales s
                  LEFT JOIN sale_items si ON s.sale_id = si.sale_id
                  WHERE DATE(s.created_at) >= ? AND DATE(s.created_at) < ?
                  ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDateInclusive);
        $stmt->execute();
        $metrics = $stmt->get_result()->fetch_assoc();
        
        fputcsv($output, ['Total Revenue', '₱' . number_format($metrics['revenue'], 2)]);
        fputcsv($output, ['Total Sales', $metrics['sales_count']]);
        fputcsv($output, ['Products Sold', $metrics['products_sold']]);
        fputcsv($output, ['Avg Transaction', '₱' . number_format($metrics['avg_transaction'], 2)]);
        fputcsv($output, []);
        
        fclose($output);
        exit;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}
?>
