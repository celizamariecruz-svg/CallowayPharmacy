<?php
require_once 'Security.php';
Security::initSession();

include 'db_connection.php';

// Initialize variables
$search = '';
$category_filter = '';
$error_message = '';

// Handle search and filter
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['category'])) {
        $category_filter = trim($_GET['category']);
    }
}

try {
    // Build query with filters
    $sql = "SELECT p.product_id, p.name, p.category, p.stock_quantity, p.price, p.expiry_date, p.location,
                   p.image_url, p.generic_name, p.brand_name, p.strength, p.dosage_form, p.sell_by_piece, p.price_per_piece, p.requires_prescription
            FROM products p 
            WHERE (p.is_active = 1 OR p.is_active IS NULL)";

    // Add search condition if provided
    if (!empty($search)) {
        $search = '%' . $search . '%';
        $sql .= " AND (p.name LIKE ? OR p.category LIKE ? OR CAST(p.price AS CHAR) LIKE ? OR p.location LIKE ? OR p.generic_name LIKE ? OR p.brand_name LIKE ?)";
    }

    // Add category filter if provided
    if (!empty($category_filter)) {
        $sql .= " AND p.category = ?";
    }

    $sql .= " ORDER BY p.name";

    // Get list of unique categories for the filter dropdown
    $categoriesQuery = "SELECT DISTINCT category FROM products WHERE (is_active = 1 OR is_active IS NULL) ORDER BY category";
    $categoriesResult = $conn->query($categoriesQuery);
    $categories = [];
    if ($categoriesResult && $categoriesResult->num_rows > 0) {
        while ($row = $categoriesResult->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

    // Prepare and execute the main query
    $stmt = $conn->prepare($sql);
    if (!empty($search) && !empty($category_filter)) {
        $stmt->bind_param('sssssss', $search, $search, $search, $search, $search, $search, $category_filter);
    } else if (!empty($search)) {
        $stmt->bind_param('ssssss', $search, $search, $search, $search, $search, $search);
    } else if (!empty($category_filter)) {
        $stmt->bind_param('s', $category_filter);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Calculate statistics
    $totalMedicines = $result->num_rows;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $uniqueCategories = count($categories);
    
    // Count low and out of stock items
    $statsResult = $conn->query("SELECT 
      COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
      COUNT(CASE WHEN stock_quantity > 0 AND stock_quantity < 10 THEN 1 END) as low_stock
      FROM products WHERE is_active = 1 OR is_active IS NULL");
    if ($statsResult && $statsResult->num_rows > 0) {
      $statsRow = $statsResult->fetch_assoc();
      $lowStockCount = $statsRow['low_stock'];
      $outOfStockCount = $statsRow['out_of_stock'];
    }

    // Add location update handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location'])) {
        $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
        $new_location = trim($_POST['new_location']);
        
        if ($product_id === false || empty($new_location)) {
            echo json_encode(['error' => 'Invalid input data']);
            exit;
        }
        
        try {
            $updateQuery = "UPDATE products SET location = ? WHERE product_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('si', $new_location, $product_id);
            
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to update location']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

$page_title = "Medicine Locator";
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<title>Medicine Locator - Calloway Pharmacy</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="shared-polish.css">
<link rel="stylesheet" href="polish.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  /* Global styling */
  * {
    box-sizing: border-box;
  }
  /* header styling is handled by the shared topbar in header-component.php */

  @keyframes fadeInDown {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .back-button {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    text-decoration: none;
    font-weight: 700;
    padding: 0.5rem 1.2rem;
    border-radius: 50px;
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    transition: all 0.3s ease;
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
    z-index: 1;
  }

  .back-button:before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.3);
    transition: transform 0.5s ease;
    z-index: -1;
    border-radius: 50px;
  }

  .back-button:hover {
    background: rgba(255, 255, 255, 0.25);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-50%) scale(1.05);
  }

  .back-button:hover:before {
    transform: translateX(100%);
  }

  h1 {
    font-weight: 800;
    font-size: 2.2rem;
    margin: 0;
    letter-spacing: 0.05em;
    color: white;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
  }

  main {
    width: 100%;
    max-width: 1400px;
    margin: 100px auto 0;
    padding: 0 2rem;
    animation: fadeIn 1s ease-out;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Card-based layout */
  .card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 5px 20px var(--shadow-color);
    padding: 1.5rem 2rem;
    margin-bottom: 2rem;
    border: 1px solid var(--table-border);
    transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
    animation: fadeIn 1s ease-out;
    animation-fill-mode: both;
    animation-delay: 0.2s;
  }
  
  .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px var(--shadow-color);
  }

  /* Headings */
  h2 {
    color: var(--primary-color);
    font-weight: 700;
    margin-top: 0;
    position: relative;
    display: inline-block;
    margin-bottom: 1.5rem;
  }
  
  h2:after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 40px;
    height: 3px;
    background: var(--primary-color);
    border-radius: 3px;
  }

  /* Table styling */
  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 1rem;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px var(--shadow-color);
  }

  th {
    background: var(--table-header-bg);
    color: var(--text-color);
    font-weight: 600;
    text-align: left;
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--table-border);
  }
  
  td {
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--table-border);
    transition: background-color 0.2s ease;
  }
  
  tr:hover {
    background: var(--hover-bg);
  }
  
  tr:last-child td {
    border-bottom: none;
  }

  footer {
    width: 100%;
    padding: 1.5rem 0;
    text-align: center;
    background: var(--footer-bg);
    color: white;
    font-size: 0.9rem;
    position: relative;
    z-index: 10;
    box-shadow: 0 -4px 15px var(--shadow-color);
    margin-top: 2rem;
    transition: background 0.3s ease;
  }

  /* Search and filter section */
  .search-filter-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
  }

  .search-container {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
    width: 100%;
  }

  .clear-filters-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
  }

  .clear-filters-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4);
  }

  .clear-filters-btn:active {
    transform: translateY(0);
  }

  .clear-filters-btn svg {
    transition: transform 0.3s ease;
  }

  .clear-filters-btn:hover svg {
    transform: rotate(90deg);
  }

  .filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--primary-color);
    color: white;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    animation: pulse 2s ease-in-out infinite;
  }

  .filter-badge #filter-count {
    background: rgba(255, 255, 255, 0.3);
    padding: 0.2rem 0.6rem;
    border-radius: 15px;
    font-weight: 800;
  }

  .search-input {
    flex: 1;
    min-width: 300px;
    position: relative;
  }

  .search-input input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--input-border);
    border-radius: 12px;
    font-size: 1.05rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background-color: var(--card-bg);
    color: var(--text-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  }

  .search-input::before {
    content: "🔍";
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.6;
    font-size: 1.2rem;
    transition: all 0.3s ease;
  }

  .search-input:has(input:focus)::before {
    opacity: 1;
    transform: translateY(-50%) scale(1.1);
  }

  .search-input input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15), 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
  }

  .search-input input::placeholder {
    color: var(--text-color);
    opacity: 0.5;
  }

  .category-filter {
    min-width: 200px;
  }

  .category-filter select {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 1px solid var(--input-border);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background-color: var(--card-bg);
    color: var(--text-color);
    cursor: pointer;
  }

  .category-filter select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
  }

  .medicine-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.8rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 15px var(--shadow-color);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid var(--table-border);
    position: relative;
    overflow: hidden;
    animation: fadeInScale 0.5s ease-out backwards;
  }

  @keyframes fadeInScale {
    from {
      opacity: 0;
      transform: scale(0.95);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  .medicine-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-color);
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .medicine-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 12px 30px var(--shadow-color);
    border-color: var(--primary-color);
  }

  .medicine-card:hover::after {
    opacity: 1;
    animation: shimmer 2s linear infinite;
  }

  @keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
  }

  .medicine-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-color);
  }

  .medicine-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
  }

  .stock-indicator {
    display: inline-block;
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 700;
    margin-top: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
  }

  .stock-indicator:hover {
    transform: scale(1.05);
  }

  .in-stock {
    background: var(--secondary-color);
    color: white;
    animation: pulse 2s ease-in-out infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
  }

  .low-stock {
    background: #f59e0b;
    color: white;
    animation: warningPulse 1.5s ease-in-out infinite;
  }

  @keyframes warningPulse {
    0%, 100% { 
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }
    50% { 
      box-shadow: 0 2px 16px rgba(245, 158, 11, 0.6);
    }
  }

  .out-of-stock {
    background: #ef4444;
    color: white;
    animation: dangerPulse 1s ease-in-out infinite;
  }

  @keyframes dangerPulse {
    0%, 100% { 
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    50% { 
      box-shadow: 0 2px 16px rgba(239, 68, 68, 0.6);
    }
  }

  .expiring-soon {
    background: #f59e0b;
    color: white;
    margin-left: 0.5rem;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
  }

  .expired {
    background: #374151;
    color: white;
    margin-left: 0.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
  }

  .medicine-price {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary-color);
    margin-top: 0.8rem;
    display: inline-block;
  }

  .medicine-category {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    background: rgba(37, 99, 235, 0.1);
    color: var(--text-color);
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 20px;
    margin-top: 0.5rem;
    border: 1px solid rgba(37, 99, 235, 0.3);
  }

  .no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-color);
    animation: fadeIn 0.8s ease-out;
  }

  .no-results h3 {
    font-size: 1.8rem;
    margin-bottom: 1rem;
    color: var(--text-color);
    opacity: 0.8;
  }

  .no-results p {
    font-size: 1.1rem;
    opacity: 0.6;
  }

  .no-results::before {
    content: "🔍";
    display: block;
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
    animation: float 3s ease-in-out infinite;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
  }

  /* Stats cards - Enhanced with Polish Framework */
  .stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    animation: fadeIn 1s ease-out;
    animation-delay: 0.2s;
    animation-fill-mode: both;
  }

  .stats-card {
    background: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 8px 20px var(--shadow-color);
    padding: 1.5rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    border: 1px solid var(--table-border);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.6s ease-out backwards;
  }

  .stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s ease;
  }

  .stats-card:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 12px 30px var(--shadow-color);
  }

  .stats-card:hover::before {
    left: 100%;
  }

  @keyframes slideInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .stats-card:nth-child(1) { animation-delay: 0.1s; }
  .stats-card:nth-child(2) { animation-delay: 0.2s; }
  .stats-card:nth-child(3) { animation-delay: 0.3s; }
  .stats-card:nth-child(4) { animation-delay: 0.4s; }

  .stats-card .label {
    font-size: 0.95rem;
    color: var(--text-color);
    opacity: 0.8;
    margin-bottom: 0.5rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .stats-card .value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary-color);
  }

  .stats-card.total-meds {
    background: rgba(37, 99, 235, 0.1);
    border-left: 5px solid var(--primary-color);
  }

  .stats-card.total-meds .value {
    color: var(--primary-color);
  }

  .stats-card.categories {
    background: rgba(16, 185, 129, 0.1);
    border-left: 5px solid #10b981;
  }

  .stats-card.categories .value {
    color: #10b981;
  }

  .stats-card.low-stock {
    background: rgba(245, 158, 11, 0.1);
    border-left: 5px solid #f59e0b;
  }

  .stats-card.low-stock .value {
    color: #f59e0b;
  }

  .stats-card.out-stock {
    background: rgba(239, 68, 68, 0.1);
    border-left: 5px solid #ef4444;
  }

  .stats-card.out-stock .value {
    color: #ef4444;
  }

  /* Low stock and out of stock indicators */
  .low-stock-label {
    color: white;
    background: #f59e0b;
    border-radius: 4px;
    padding: 3px 8px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 5px;
    display: inline-block;
    box-shadow: 0 2px 5px rgba(214, 126, 33, 0.2);
  }
  
  .out-of-stock-label {
    font-weight: 700;
    color: white;
    background: #ef4444;
    padding: 4px 10px;
    border-radius: 4px;
    display: inline-block;
    box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
  }

  /* Error message */
  .error-message {
    padding: 1rem;
    background: #ffebee;
    color: #c62828;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-left: 4px solid #c62828;
  }
  
  /* Medicine location indicator */
  .location-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .location-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.5rem 1rem;
    background: var(--primary-color);
    color: white;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    transition: all 0.3s ease;
  }

  .location-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
  }
  
  .location-badge svg {
    width: 16px;
    height: 16px;
  }

  /* Category badge styles */
  .category-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    background: #10b981;
    color: white;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 2px 5px rgba(46, 204, 113, 0.2);
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    main {
      padding: 0 1rem;
      margin-top: 80px;
    }
    
    .search-container {
      flex-direction: column;
    }
    
    .search-input,
    .category-filter {
      width: 100%;
      min-width: unset;
    }
    
    .medicine-grid {
      grid-template-columns: 1fr;
    }
  }

  .medicine-location {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 1rem;
    padding: 0.8rem 1rem;
    background: rgba(37, 99, 235, 0.08);
    border-radius: 12px;
    border: 1px solid rgba(37, 99, 235, 0.2);
    transition: all 0.3s ease;
  }

  .medicine-location:hover {
    background: rgba(37, 99, 235, 0.15);
    border-color: rgba(37, 99, 235, 0.4);
  }

  .location-text {
    flex: 1;
    color: var(--text-color);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .location-text::before {
    content: "📍";
    font-size: 1.2rem;
  }

  .edit-location-btn,
  .save-location-btn {
    background: var(--primary-color);
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
  }

  .edit-location-btn:hover,
  .save-location-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.5);
  }

  .edit-location-btn:active,
  .save-location-btn:active {
    transform: scale(0.95);
  }

  .location-input {
    flex: 1;
    padding: 0.6rem 1rem;
    border: 2px solid var(--input-border);
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-color);
    background: var(--card-bg);
    transition: all 0.3s ease;
  }

  .location-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
    transform: scale(1.02);
  }

  /* Quick action button */
  .quick-action-fab {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    border: none;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
    z-index: 100;
    animation: fadeInScale 0.5s ease-out;
  }

  .quick-action-fab:hover {
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 12px 30px rgba(37, 99, 235, 0.6);
  }

  .quick-action-fab:active {
    transform: scale(0.95);
  }

  /* Smooth scroll behavior */
  html {
    scroll-behavior: smooth;
  }
</style>
</head>
<body>

<!-- Loading Screen -->
<div id="loading-screen" style="
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--primary-color);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  transition: opacity 0.5s ease;
">
  <div style="
    width: 80px;
    height: 80px;
    border: 6px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  "></div>
  <p style="color: white; margin-top: 1.5rem; font-size: 1.2rem; font-weight: 600;">Loading Medicine Locator...</p>
</div>

<style>
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

<?php 
$page_title = 'Medicine Locator';
include 'header-component.php'; 
?>

<main style="margin-top: 80px;">
  <?php if (!empty($error_message)): ?>
    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <!-- Statistics Cards -->
  <div class="stats-container">
    <div class="stats-card total-meds">
      <div class="label">📦 Total Medicines</div>
      <div class="value stat-number" data-target="<?php echo $totalMedicines; ?>">0</div>
    </div>
    <div class="stats-card categories">
      <div class="label">🏷️ Categories</div>
      <div class="value stat-number" data-target="<?php echo $uniqueCategories; ?>">0</div>
    </div>
    <div class="stats-card low-stock">
      <div class="label">⚠️ Low Stock</div>
      <div class="value stat-number" data-target="<?php echo $lowStockCount; ?>">0</div>
    </div>
    <div class="stats-card out-stock">
      <div class="label">🚫 Out of Stock</div>
      <div class="value stat-number" data-target="<?php echo $outOfStockCount; ?>">0</div>
    </div>
  </div>

  <!-- Search and Filter Form -->
  <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h2>🔍 Find Medicines</h2>
      <div id="filter-badge" class="filter-badge" style="display: none;">
        <span id="filter-count">0</span> filters active
        <button onclick="clearFilters()" style="background: none; border: none; color: white; cursor: pointer; margin-left: 0.5rem;">✕</button>
      </div>
    </div>
    <div class="search-filter-container">
      <div class="search-container">
        <div class="search-input">
          <input type="text" 
                 id="medicine-search"
                 placeholder="Search medicines by name, category, or price..."
                 autocomplete="off">
        </div>
        <div class="category-filter">
          <select id="category-filter">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= htmlspecialchars($category) ?>">
                <?= htmlspecialchars($category) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="button" class="clear-filters-btn" onclick="clearFilters()" title="Clear all filters">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
          Clear
        </button>
      </div>
    </div>
  </div>

  <!-- Medicines Table -->
  <div class="card">
    <h2>Medicine Inventory</h2>
    <div class="medicine-grid">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): 
          $stockClass = $row['stock_quantity'] <= 0 ? 'out-of-stock' : 
                      ($row['stock_quantity'] < 10 ? 'low-stock' : 'in-stock');
          
          $stockText = $row['stock_quantity'] <= 0 ? 'Out of Stock' : 
                      ($row['stock_quantity'] < 10 ? 'Low Stock' : 'In Stock');
          
          // Calculate expiry status if expiry_date exists
          $expiryClass = '';
          $expiryText = '';
          if (!empty($row['expiry_date'])) {
            $expiryDate = new DateTime($row['expiry_date']);
            $today = new DateTime();
            $diff = $today->diff($expiryDate)->format("%r%a");
            
            if ($diff < 0) {
              $expiryClass = 'expired';
              $expiryText = 'Expired';
            } elseif ($diff <= 30) {
              $expiryClass = 'expiring-soon';
              $expiryText = 'Expiring Soon';
            }
          }
        ?>
          <div class="medicine-card" data-category="<?= htmlspecialchars($row['category']) ?>">
            <?php if (!empty($row['image_url'])): ?>
              <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" style="width:60px;height:60px;border-radius:10px;object-fit:cover;margin-bottom:6px;border:1px solid var(--border-color);">
            <?php else: ?>
              <img src="assets/placeholder-product.svg" alt="No image" style="width:60px;height:60px;border-radius:10px;object-fit:cover;margin-bottom:6px;opacity:0.5;">
            <?php endif; ?>
            <h3><?= htmlspecialchars($row['name']) ?><?php if (!empty($row['requires_prescription'])): ?> <span style="background:#e65100;color:#fff;font-size:0.6rem;padding:2px 6px;border-radius:4px;font-weight:700;vertical-align:middle;">Rx</span><?php endif; ?></h3>
            <?php
              $variant = trim(($row['strength'] ?? '') . ' ' . ($row['dosage_form'] ?? ''));
              if ($variant): ?>
              <div style="font-size:0.78rem;color:var(--text-light);margin:-4px 0 4px;"><?= htmlspecialchars($variant) ?></div>
            <?php endif; ?>
            <div class="medicine-category"><?= htmlspecialchars($row['category']) ?></div>
            <div class="medicine-price">₱<?= number_format($row['price'], 2) ?></div>
            <?php if (!empty($row['sell_by_piece']) && $row['price_per_piece'] > 0): ?>
              <div style="font-size:0.78rem;color:var(--primary-color);font-weight:600;">₱<?= number_format($row['price_per_piece'], 2) ?>/piece</div>
            <?php endif; ?>
            <div class="stock-indicator <?= $stockClass ?>"><?= $stockText ?></div>
            <?php if ($expiryClass): ?>
              <div class="stock-indicator <?= $expiryClass ?>"><?= $expiryText ?></div>
            <?php endif; ?>
            <div class="medicine-location" data-product-id="<?= $row['product_id'] ?>">
              <span class="location-text"><?= htmlspecialchars($row['location'] ?: 'Location not set') ?></span>
              <button class="edit-location-btn" onclick="editLocation(<?= $row['product_id'] ?>)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="no-results">
          <h3>No medicines found</h3>
          <p>Try adjusting your search criteria</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Scroll to top button -->
  <button class="quick-action-fab" onclick="scrollToTop()" title="Scroll to top">
    ↑
  </button>
</main>

<?php include 'footer-component.php'; ?>

<?php include 'pills-background.php'; ?>

<script src="theme.js"></script>
<script src="global-polish.js"></script>
<script>
let searchTimeout;
let currentCategory = '';
let currentSearch = '';
let originalTotalMeds = 0;

function handleSearch(value) {
    currentSearch = value.toLowerCase();
    filterMedicines();
}

function handleCategoryFilter(value) {
    currentCategory = value;
    filterMedicines();
}

function filterMedicines() {
    const medicineCards = document.querySelectorAll('.medicine-card');
    let visibleCount = 0;
    let delay = 0;
    
    medicineCards.forEach((card) => {
        const cardCategory = card.getAttribute('data-category') || '';
        const cardName = card.querySelector('h3')?.textContent.toLowerCase() || '';
        const cardPrice = card.querySelector('.medicine-price')?.textContent.toLowerCase() || '';
        const cardCategoryText = card.querySelector('.medicine-category')?.textContent.toLowerCase() || '';
        
        // Check category filter
        const categoryMatch = !currentCategory || cardCategory === currentCategory;
        
        // Check search filter
        const searchMatch = !currentSearch || 
                           cardName.includes(currentSearch) || 
                           cardPrice.includes(currentSearch) ||
                           cardCategoryText.includes(currentSearch);
        
        // Show or hide card with animation
        if (categoryMatch && searchMatch) {
            card.style.display = 'block';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
            card.style.animation = `fadeInScale 0.4s ease-out ${delay * 0.03}s backwards`;
            delay++;
            visibleCount++;
        } else {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                if (card.style.opacity === '0') {
                    card.style.display = 'none';
                }
            }, 200);
        }
    });
    
    // Show/hide no results message
    const noResults = document.querySelector('.no-results');
    if (noResults) {
        if (visibleCount === 0) {
            noResults.style.display = 'block';
            noResults.style.animation = 'fadeIn 0.5s ease-out';
        } else {
            noResults.style.display = 'none';
        }
    }
    
    // Update filter badge
    updateFilterBadge();
    
    // Update stats
    updateFilteredStats(visibleCount);
}

function updateFilterBadge() {
    const filterBadge = document.getElementById('filter-badge');
    const filterCount = document.getElementById('filter-count');
    
    let activeFilters = 0;
    if (currentSearch) activeFilters++;
    if (currentCategory) activeFilters++;
    
    if (activeFilters > 0) {
        filterBadge.style.display = 'inline-flex';
        filterCount.textContent = activeFilters;
    } else {
        filterBadge.style.display = 'none';
    }
}

function updateFilteredStats(visibleCount) {
    const totalMedsCard = document.querySelector('.stats-card.total-meds .value');
    if (totalMedsCard) {
        const currentValue = parseInt(totalMedsCard.textContent) || 0;
        const targetValue = (currentCategory || currentSearch) ? visibleCount : originalTotalMeds;
        
        if (currentValue !== targetValue) {
            animateValue(totalMedsCard, currentValue, targetValue, 500);
        }
    }
}

function clearFilters() {
    // Clear search input
    const searchInput = document.getElementById('medicine-search');
    if (searchInput) {
        searchInput.value = '';
        currentSearch = '';
    }
    
    // Reset category dropdown
    const categoryFilter = document.getElementById('category-filter');
    if (categoryFilter) {
        categoryFilter.value = '';
        currentCategory = '';
    }
    
    // Clear filter variables
    currentSearch = '';
    currentCategory = '';
    
    // Show all medicines with animation
    filterMedicines();
    
    // Reset stats to original values
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((stat) => {
        const target = parseInt(stat.getAttribute('data-target'));
        const current = parseInt(stat.textContent);
        if (current !== target) {
            animateValue(stat, current, target, 500);
        }
    });
    
    // Show success notification
    showNotification('🔄 Filters cleared', 'success');
}

// Animate numbers on page load
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = value;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Scroll to top function
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Add smooth transitions and number animations
document.addEventListener('DOMContentLoaded', function() {
    // Store original total meds count
    const totalMedsCard = document.querySelector('.stats-card.total-meds .value');
    if (totalMedsCard) {
        originalTotalMeds = parseInt(totalMedsCard.getAttribute('data-target'));
    }
    
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((stat, index) => {
        const target = parseInt(stat.getAttribute('data-target'));
        setTimeout(() => {
            animateValue(stat, 0, target, 1500);
        }, index * 100);
    });

    // Add staggered animation to medicine cards
    const cards = document.querySelectorAll('.medicine-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
    });

    // Show/hide scroll to top button
    const scrollBtn = document.querySelector('.quick-action-fab');
    if (scrollBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollBtn.style.opacity = '1';
                scrollBtn.style.pointerEvents = 'auto';
            } else {
                scrollBtn.style.opacity = '0';
                scrollBtn.style.pointerEvents = 'none';
            }
        });
        
        // Initial state
        scrollBtn.style.opacity = '0';
        scrollBtn.style.transition = 'opacity 0.3s ease';
    }
    
    // Setup event listeners for filtering
    const searchInput = document.getElementById('medicine-search');
    const categoryFilter = document.getElementById('category-filter');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            handleSearch(e.target.value);
        });
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function(e) {
            handleCategoryFilter(e.target.value);
        });
    }
});

function editLocation(productId) {
  const locationDiv = document.querySelector(`.medicine-location[data-product-id="${productId}"]`);
  const currentLocation = locationDiv.querySelector('.location-text').textContent;
  const isLocationNotSet = currentLocation === 'Location not set';
  
  const input = document.createElement('input');
  input.type = 'text';
  input.value = isLocationNotSet ? '' : currentLocation;
  input.placeholder = 'Enter location';
  input.className = 'location-input';
  
  const saveBtn = document.createElement('button');
  saveBtn.className = 'save-location-btn';
  saveBtn.innerHTML = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
      <polyline points="17 21 17 13 7 13 7 21"></polyline>
      <polyline points="7 3 7 8 15 8"></polyline>
    </svg>
  `;
  
  saveBtn.onclick = () => saveLocation(productId, input.value);
  
  locationDiv.innerHTML = '';
  locationDiv.appendChild(input);
  locationDiv.appendChild(saveBtn);
  input.focus();
}

function saveLocation(productId, newLocation) {
  if (!newLocation.trim()) {
    showNotification('Location cannot be empty', 'error');
    return;
  }
  
  const formData = new FormData();
  formData.append('update_location', '1');
  formData.append('product_id', productId);
  formData.append('new_location', newLocation);
  
  fetch('medicine-locator.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const locationDiv = document.querySelector(`.medicine-location[data-product-id="${productId}"]`);
      locationDiv.innerHTML = `
        <span class="location-text">${newLocation}</span>
        <button class="edit-location-btn" onclick="editLocation(${productId})">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
          </svg>
        </button>
      `;
      // Add success animation
      locationDiv.style.animation = 'none';
      setTimeout(() => {
        locationDiv.style.animation = 'pulse 0.5s ease';
      }, 10);
      showNotification('✅ Location updated successfully', 'success');
    } else {
      showNotification(data.error || 'Failed to update location', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showNotification('An error occurred while updating location', 'error');
  });
}

// Simple notification function if not defined
function showNotification(message, type) {
  const notification = document.createElement('div');
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 100px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    color: white;
    font-weight: 600;
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    z-index: 10000;
    animation: slideInRight 0.3s ease;
    background: ${type === 'success' ? '#10b981' : '#ef4444'};
  `;
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Hide loading screen when page is fully loaded
window.addEventListener('load', function() {
    const loadingScreen = document.getElementById('loading-screen');
    setTimeout(() => {
        loadingScreen.style.opacity = '0';
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }, 500);
});

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // F3 - Focus Search
    if (e.key === 'F3') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input input');
        searchInput?.focus();
    }
    // Ctrl+F - Find Medicine
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input input');
        searchInput?.focus();
    }
    // Escape - Clear search
    if (e.key === 'Escape') {
        const searchInput = document.querySelector('.search-input input');
        if (searchInput && searchInput === document.activeElement) {
            searchInput.value = '';
            searchInput.blur();
        }
    }
});

// Add hover effect to cards
document.addEventListener('DOMContentLoaded', function() {
    const medicineCards = document.querySelectorAll('.medicine-card');
    medicineCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        });
    });
});
</script>
<script src="shared-polish.js"></script>
</body>
</html>
