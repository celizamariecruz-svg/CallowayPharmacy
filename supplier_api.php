<?php
/**
 * Supplier Management API
 * Handles all CRUD operations for suppliers
 */

require_once 'db_connection.php';
require_once 'Auth.php';

header('Content-Type: application/json');

$auth = new Auth($conn);
$auth->requireAuth();

if (!$auth->hasPermission('suppliers.view')) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. You do not have permission to manage suppliers.'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            getSuppliers($conn);
            break;
            
        case 'create':
            createSupplier($conn, $input);
            break;
            
        case 'update':
            updateSupplier($conn, $input);
            break;
            
        case 'delete':
            deleteSupplier($conn, $input);
            break;
            
        case 'get_supplier_products':
            getSupplierProducts($conn, $input);
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

function getSuppliers($conn) {
    $query = "SELECT * FROM suppliers ORDER BY supplier_name ASC";
    $result = $conn->query($query);
    
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $suppliers,
        'count' => count($suppliers)
    ]);
}

function createSupplier($conn, $input) {
    // Validate required fields
    if (empty($input['name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier name is required'
        ]);
        return;
    }
    
    // Check for duplicate supplier name
    $check_query = "SELECT supplier_id FROM suppliers WHERE supplier_name = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('s', $input['name']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'A supplier with this name already exists'
        ]);
        return;
    }
    
    // Insert new supplier
    $query = "INSERT INTO suppliers (supplier_name, contact_person, email, phone, address) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        'sssss',
        $input['name'],
        $input['contact_person'],
        $input['email'],
        $input['phone'],
        $input['address']
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Supplier created successfully',
            'supplier_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create supplier: ' . $conn->error
        ]);
    }
}

function updateSupplier($conn, $input) {
    // Validate required fields
    if (empty($input['supplier_id']) || empty($input['name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier ID and name are required'
        ]);
        return;
    }
    
    // Check if supplier exists
    $check_query = "SELECT supplier_id FROM suppliers WHERE supplier_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('i', $input['supplier_id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found'
        ]);
        return;
    }
    
    // Check for duplicate name (excluding current supplier)
    $check_query = "SELECT supplier_id FROM suppliers WHERE supplier_name = ? AND supplier_id != ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('si', $input['name'], $input['supplier_id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Another supplier with this name already exists'
        ]);
        return;
    }
    
    // Update supplier
    $query = "UPDATE suppliers 
              SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ? 
              WHERE supplier_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        'sssssi',
        $input['name'],
        $input['contact_person'],
        $input['email'],
        $input['phone'],
        $input['address'],
        $input['supplier_id']
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Supplier updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update supplier: ' . $conn->error
        ]);
    }
}

function deleteSupplier($conn, $input) {
    // Validate required fields
    if (empty($input['supplier_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier ID is required'
        ]);
        return;
    }
    
    // Check if supplier exists
    $check_query = "SELECT supplier_id FROM suppliers WHERE supplier_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('i', $input['supplier_id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found'
        ]);
        return;
    }
    
    // Check if supplier has products
    $check_products = "SELECT COUNT(*) as count FROM products WHERE supplier_id = ?";
    $stmt = $conn->prepare($check_products);
    $stmt->bind_param('i', $input['supplier_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete supplier with existing products. Please reassign or delete the products first.'
        ]);
        return;
    }
    
    // Delete supplier
    $query = "DELETE FROM suppliers WHERE supplier_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $input['supplier_id']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Supplier deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete supplier: ' . $conn->error
        ]);
    }
}

function getSupplierProducts($conn, $input) {
    if (empty($input['supplier_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier ID is required'
        ]);
        return;
    }
    
    $query = "SELECT p.*, c.category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.category_id 
              WHERE p.supplier_id = ? 
              ORDER BY p.name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $input['supplier_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products)
    ]);
}
