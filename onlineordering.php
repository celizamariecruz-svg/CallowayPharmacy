<?php
/**
 * Pharmacy Online Ordering - Connected to Database
 * Fetches real medicine data from the products table
 */
session_start();
require_once 'db_connection.php';

// Ensure tables exist — only run once per session to avoid repeated DDL on every page load
if (empty($_SESSION['_online_tables_checked'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS online_orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        contact_number VARCHAR(20) NULL,
        email VARCHAR(100) NULL,
        status ENUM('Pending','Confirmed','Preparing','Ready','Completed','Cancelled') DEFAULT 'Pending',
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash on Pickup',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS online_order_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES online_orders(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS pos_notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        type VARCHAR(50) DEFAULT 'online_order',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES online_orders(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $_SESSION['_online_tables_checked'] = true;
}

$isLoggedIn = isset($_SESSION['user_id']);
$customerName = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Customer') : '';

// Fetch categories for filter buttons
$catQuery = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
$catResult = $conn->query($catQuery);
$categories = [];
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch products that are active and in stock
$prodQuery = "
    SELECT 
        p.product_id,
        p.name,
        p.description,
        p.selling_price,
        p.stock_quantity,
        p.sku,
        p.image_url,
        c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
";
$prodResult = $conn->query($prodQuery);
$products = [];
if ($prodResult) {
    while ($row = $prodResult->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Online Ordering - Calloway Pharmacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #f0f4f8;
            --text-color: #333333;
            --primary-blue: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-bg: #e0ecff;
            --card-bg: #ffffff;
            --accent: #4FC3F7;
            --shadow: rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --text-light: #6b7280;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
        }

        [data-theme="dark"] {
            --bg-color: #0f172a;
            --text-color: #e2e8f0;
            --primary-blue: #3b82f6;
            --primary-dark: #2563eb;
            --secondary-bg: #1e293b;
            --card-bg: #1e293b;
            --accent: #4FC3F7;
            --shadow: rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --text-light: #94a3b8;
            --danger: #f87171;
            --success: #4ade80;
            --warning: #fbbf24;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
            min-height: 100vh;
        }

        /* ─── Header ─── */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: var(--secondary-bg);
            box-shadow: 0 2px 8px var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-bar {
            flex: 1;
            max-width: 500px;
            margin: 0 1.5rem;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.5rem;
            border: 2px solid transparent;
            border-radius: 12px;
            background-color: var(--card-bg);
            color: var(--text-color);
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        .search-bar i {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background 0.2s, transform 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: white;
        }

        .toggle-btn {
            background: var(--card-bg);
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: var(--text-color);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .toggle-btn:hover { background: var(--secondary-bg); }

        /* ─── Stats Bar ─── */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 2rem;
            padding: 1rem 2rem;
            background: var(--card-bg);
            box-shadow: var(--shadow-md);
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-blue);
        }

        .stat-item .stat-label {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ─── Categories ─── */
        .categories-section {
            padding: 1.5rem 2rem 0;
            max-width: 1400px;
            margin: 0 auto;
        }

        .categories-nav {
            display: flex;
            gap: 0.7rem;
            overflow-x: auto;
            padding: 0.5rem 0;
            flex-wrap: wrap;
        }

        .category-btn {
            padding: 0.6rem 1.2rem;
            background: var(--card-bg);
            border: 1px solid rgba(0,0,0,0.08);
            color: var(--text-color);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .category-btn:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }

        .category-btn.active {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        /* ─── Product Grid ─── */
        .products-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 2rem 4rem;
        }

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .products-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .product-count {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            border: 1px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px var(--shadow);
            border-color: rgba(37, 99, 235, 0.15);
        }

        .product-image {
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
        }

        [data-theme="dark"] .product-image {
            background: linear-gradient(135deg, #1e293b, #0f172a);
        }

        .product-content {
            padding: 1.2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-category-tag {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--primary-blue);
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .product-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            line-height: 1.3;
        }

        .product-desc {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-bottom: 0.6rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-stock {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-bottom: auto;
        }

        .stock-good { color: var(--success); }
        .stock-low { color: var(--warning); }
        .stock-out { color: var(--danger); }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(0,0,0,0.06);
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary-blue);
        }

        .add-cart-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--primary-blue);
            background: transparent;
            color: var(--primary-blue);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .add-cart-btn:hover {
            background: var(--primary-blue);
            color: white;
            transform: rotate(90deg) scale(1.1);
        }

        .add-cart-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ─── Empty State ─── */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i { font-size: 3rem; opacity: 0.25; margin-bottom: 1rem; }
        .empty-state h3 { margin-bottom: 0.5rem; }
        .empty-state p { color: var(--text-light); }

        /* ─── Floating Cart Button ─── */
        .floating-cart {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 200;
            display: none;
        }

        .floating-cart button {
            background: var(--primary-blue);
            color: white;
            padding: 0.9rem 1.8rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(37,99,235,0.4);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.3s ease;
        }

        .floating-cart button:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 32px rgba(37,99,235,0.5);
        }

        .cart-badge {
            background: white;
            color: var(--primary-blue);
            padding: 0.15rem 0.55rem;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.85rem;
        }

        /* ─── Toast ─── */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            background: var(--card-bg);
            color: var(--text-color);
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            box-shadow: 0 4px 16px var(--shadow);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            animation: slideIn 0.3s ease;
            border-left: 4px solid var(--success);
        }

        .toast.info { border-left-color: var(--primary-blue); }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* ─── Footer ─── */
        footer {
            text-align: center;
            padding: 1.5rem;
            background-color: var(--secondary-bg);
            color: var(--text-light);
            font-size: 0.85rem;
        }

        footer a {
            color: var(--primary-blue);
            text-decoration: none;
        }

        /* ─── Cart Sidebar Panel ─── */
        .cart-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .cart-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .cart-panel {
            position: fixed;
            top: 0;
            right: -460px;
            width: 440px;
            max-width: 90vw;
            height: 100vh;
            background: var(--card-bg);
            z-index: 999;
            display: flex;
            flex-direction: column;
            box-shadow: -8px 0 30px rgba(0,0,0,0.15);
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .cart-panel.open {
            right: 0;
        }

        .cart-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            background: var(--secondary-bg);
        }

        .cart-panel-header h2 {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-blue);
        }

        .cart-close-btn {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: var(--text-light);
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .cart-close-btn:hover {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }

        .cart-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.2rem;
        }

        .cart-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
        }

        .cart-empty i {
            font-size: 3rem;
            opacity: 0.25;
            margin-bottom: 1rem;
            display: block;
        }

        .cart-empty p {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: var(--text-color);
        }

        .cart-empty span {
            font-size: 0.85rem;
        }

        /* Cart Item */
        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-color);
            border-radius: 12px;
            margin-bottom: 0.8rem;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .cart-item:hover {
            border-color: rgba(37,99,235,0.15);
        }

        .cart-item-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: var(--secondary-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-price-info {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-bottom: 0.4rem;
        }

        .cart-item-subtotal {
            font-weight: 800;
            color: var(--primary-blue);
            font-size: 1rem;
        }

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid rgba(0,0,0,0.1);
            background: var(--card-bg);
            color: var(--text-color);
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .qty-btn:hover {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .qty-btn.remove:hover {
            background: var(--danger);
            border-color: var(--danger);
        }

        .cart-item-qty {
            font-weight: 700;
            font-size: 0.95rem;
            min-width: 20px;
            text-align: center;
        }

        /* Cart Footer */
        .cart-panel-footer {
            padding: 1.2rem 1.5rem;
            border-top: 1px solid rgba(0,0,0,0.08);
            background: var(--secondary-bg);
        }

        .cart-summary {
            margin-bottom: 1rem;
        }

        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .cart-summary-row.total {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-color);
            padding: 0.6rem 0;
            border-top: 2px solid rgba(0,0,0,0.08);
            margin-top: 0.3rem;
        }

        .cart-summary-row.total span:last-child {
            color: var(--primary-blue);
        }

        .cart-summary-row.items-count {
            font-size: 0.8rem;
        }

        .cart-checkout-btn {
            width: 100%;
            padding: 0.9rem;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .cart-checkout-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .cart-clear-btn {
            width: 100%;
            padding: 0.6rem;
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .cart-clear-btn:hover {
            background: var(--danger);
            color: white;
        }

        /* ─── Checkout Modal ─── */
        .checkout-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        .checkout-overlay.active { display: flex; }

        .checkout-modal {
            background: var(--card-bg);
            border-radius: 20px;
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .checkout-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .checkout-header h2 {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
        }

        .checkout-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-light);
            cursor: pointer;
            padding: 0.3rem;
            border-radius: 8px;
            transition: all 0.15s;
        }
        .checkout-close:hover { background: var(--bg-color); color: var(--text-color); }

        .checkout-step { padding: 1.5rem; }

        .checkout-subtitle {
            font-size: 0.95rem;
            color: var(--text-color);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkout-items {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .checkout-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.88rem;
        }
        .checkout-item:last-child { border-bottom: none; }

        .checkout-item-name {
            font-weight: 600;
            color: var(--text-color);
        }
        .checkout-item-qty {
            color: var(--text-light);
            font-size: 0.8rem;
        }
        .checkout-item-price {
            font-weight: 700;
            color: var(--primary-blue);
        }

        .checkout-total-section {
            margin-bottom: 1.25rem;
        }

        .checkout-total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        .checkout-total-row.grand {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-color);
            border-top: 2px solid var(--border-color);
            padding-top: 0.6rem;
            margin-top: 0.3rem;
        }

        .checkout-notice {
            display: flex;
            gap: 0.75rem;
            padding: 1rem;
            background: #fef3cd;
            border: 1px solid #ffc107;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            color: #856404;
        }
        .checkout-notice i { font-size: 1.2rem; margin-top: 2px; color: #f59e0b; }
        .checkout-notice p { margin: 0.2rem 0 0; line-height: 1.4; }

        [data-theme="dark"] .checkout-notice {
            background: rgba(245, 158, 11, 0.12);
            border-color: rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }

        .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .payment-option input { display: none; }

        .payment-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 1rem 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .payment-option input:checked + .payment-card {
            border-color: var(--primary-blue);
            background: rgba(37, 99, 235, 0.06);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .payment-card:hover {
            border-color: var(--primary-blue);
            transform: translateY(-1px);
        }

        .payment-label {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-color);
        }
        .payment-desc {
            font-size: 0.72rem;
            color: var(--text-light);
        }

        .checkout-guest-notice {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 10px;
            font-size: 0.82rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        .checkout-guest-notice i { color: var(--primary-blue); }

        .checkout-place-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .checkout-place-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4); }
        .checkout-place-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Order success */
        .order-success { text-align: center; padding: 2rem 0; }
        .success-icon { font-size: 4rem; color: #22c55e; margin-bottom: 1rem; }
        .order-success h3 { font-size: 1.3rem; color: var(--text-color); margin-bottom: 0.5rem; }
        .order-ref { font-size: 1rem; color: var(--primary-blue); font-weight: 700; margin-bottom: 1.5rem; }

        .success-details {
            text-align: left;
            background: var(--bg-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .success-details p {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 0;
            font-size: 0.88rem;
            color: var(--text-color);
        }
        .success-details i { color: var(--primary-blue); width: 18px; text-align: center; }

        .checkout-done-btn {
            width: 100%;
            padding: 0.9rem;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .checkout-done-btn:hover { transform: translateY(-1px); }

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            header { flex-direction: column; gap: 0.8rem; padding: 1rem; }
            .search-bar { max-width: 100%; margin: 0; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
            .stats-bar { gap: 1rem; }
            .cart-panel { width: 100%; max-width: 100vw; right: -100%; }
        }
    </style>
</head>
<body data-theme="light">
    <!-- Toast container -->
    <div class="toast-container" id="toastContainer"></div>

    <header>
        <a href="index.php" class="logo" style="text-decoration:none; color:inherit;">
            <i class="fas fa-prescription-bottle-medical"></i> Calloway Pharmacy
        </a>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search medicines, vitamins, supplements..." oninput="filterProducts()">
        </div>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="toggleCartPanel()"><i class="fas fa-shopping-cart"></i> Cart (<span id="headerCartCount">0</span>)</button>
            <button class="toggle-btn" id="theme-toggle" title="Toggle theme">🌙</button>
            <?php if ($isLoggedIn): ?>
                <a href="logout.php" class="btn" style="background:var(--danger, #ef4444); color:#fff; padding:0.5rem 1rem; border-radius:8px; text-decoration:none; font-size:0.85rem; display:flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn" style="background:var(--primary-color); color:#fff; padding:0.5rem 1rem; border-radius:8px; text-decoration:none; font-size:0.85rem; display:flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Stats bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?php echo count($products); ?></div>
            <div class="stat-label">Products Available</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo count($categories); ?></div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo count(array_filter($products, fn($p) => intval($p['stock_quantity']) > 0)); ?></div>
            <div class="stat-label">In Stock</div>
        </div>
    </div>

    <!-- Category filters -->
    <div class="categories-section">
        <div class="categories-nav" id="categoriesNav">
            <button class="category-btn active" onclick="filterByCategory(this, 'all')">
                <i class="fas fa-th-large"></i> All
            </button>
            <?php foreach ($categories as $cat): ?>
                <button class="category-btn" onclick="filterByCategory(this, '<?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES); ?>')">
                    <?php echo htmlspecialchars($cat['category_name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="products-section">
        <div class="products-header">
            <h2 id="sectionTitle">All Products</h2>
            <span class="product-count" id="productCount"><?php echo count($products); ?> items</span>
        </div>
        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No products found</h3>
                    <p>The inventory is currently empty. Please check back later.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?php
                        $stock = intval($product['stock_quantity']);
                        $stockClass = $stock <= 0 ? 'stock-out' : ($stock <= 10 ? 'stock-low' : 'stock-good');
                        $stockLabel = $stock <= 0 ? 'Out of stock' : ($stock <= 10 ? "Low stock ({$stock} left)" : "{$stock} available");
                        $stockIcon  = $stock <= 0 ? 'fa-times-circle' : ($stock <= 10 ? 'fa-exclamation-triangle' : 'fa-check-circle');
                        $emoji = getCategoryEmoji($product['category_name'] ?? '');
                    ?>
                    <div class="product-card"
                         data-name="<?php echo htmlspecialchars(strtolower($product['name']), ENT_QUOTES); ?>"
                         data-category="<?php echo htmlspecialchars($product['category_name'] ?? 'General', ENT_QUOTES); ?>"
                         data-desc="<?php echo htmlspecialchars(strtolower($product['description'] ?? ''), ENT_QUOTES); ?>">
                        <div class="product-image">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <?php echo $emoji; ?>
                            <?php endif; ?>
                        </div>
                        <div class="product-content">
                            <div class="product-category-tag"><?php echo htmlspecialchars($product['category_name'] ?? 'General'); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <?php if (!empty($product['description'])): ?>
                                <div class="product-desc"><?php echo htmlspecialchars($product['description']); ?></div>
                            <?php endif; ?>
                            <div class="product-stock <?php echo $stockClass; ?>">
                                <i class="fas <?php echo $stockIcon; ?>"></i> <?php echo $stockLabel; ?>
                            </div>
                            <div class="product-footer">
                                <div class="product-price">₱<?php echo number_format(floatval($product['selling_price']), 2); ?></div>
                                <button class="add-cart-btn"
                                        <?php echo $stock <= 0 ? 'disabled' : ''; ?>
                                        onclick="addToCart(<?php echo intval($product['product_id']); ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>', <?php echo floatval($product['selling_price']); ?>)"
                                        title="<?php echo $stock <= 0 ? 'Out of stock' : 'Add to cart'; ?>">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Cart Button -->
    <div class="floating-cart" id="floatingCart">
        <button onclick="toggleCartPanel()">
            <i class="fas fa-shopping-basket"></i>
            <span>My Cart</span>
            <span class="cart-badge" id="cartCount">0</span>
        </button>
    </div>

    <!-- Cart Sidebar Panel -->
    <div class="cart-overlay" id="cartOverlay" onclick="toggleCartPanel()"></div>
    <div class="cart-panel" id="cartPanel">
        <div class="cart-panel-header">
            <h2><i class="fas fa-shopping-cart"></i> Your Cart</h2>
            <button class="cart-close-btn" onclick="toggleCartPanel()"><i class="fas fa-times"></i></button>
        </div>
        <div class="cart-panel-body" id="cartPanelBody">
            <div class="cart-empty" id="cartEmpty">
                <i class="fas fa-shopping-basket"></i>
                <p>Your cart is empty</p>
                <span>Add some medicines to get started</span>
            </div>
        </div>
        <div class="cart-panel-footer" id="cartPanelFooter" style="display:none;">
            <div class="cart-summary">
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span id="cartSubtotal">₱0.00</span>
                </div>
                <div class="cart-summary-row total">
                    <span>Total</span>
                    <span id="cartTotal">₱0.00</span>
                </div>
                <div class="cart-summary-row items-count">
                    <span>Total Items</span>
                    <span id="cartTotalItems">0</span>
                </div>
            </div>
            <button class="cart-checkout-btn" onclick="checkout()"><i class="fas fa-credit-card"></i> Proceed to Checkout</button>
            <button class="cart-clear-btn" onclick="clearCart()"><i class="fas fa-trash-alt"></i> Clear Cart</button>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div class="checkout-overlay" id="checkoutOverlay" onclick="closeCheckoutModal(event)">
        <div class="checkout-modal" onclick="event.stopPropagation()">
            <div class="checkout-header">
                <h2><i class="fas fa-receipt"></i> Checkout</h2>
                <button class="checkout-close" onclick="closeCheckoutModal()"><i class="fas fa-times"></i></button>
            </div>

            <!-- Step 1: Order Summary -->
            <div class="checkout-step" id="checkoutStep1">
                <h3 class="checkout-subtitle"><i class="fas fa-shopping-bag"></i> Order Summary</h3>
                <div class="checkout-items" id="checkoutItemsList"></div>
                <div class="checkout-total-section">
                    <div class="checkout-total-row">
                        <span>Subtotal</span>
                        <span id="checkoutSubtotal">₱0.00</span>
                    </div>
                    <div class="checkout-total-row grand">
                        <span>Total</span>
                        <span id="checkoutTotal">₱0.00</span>
                    </div>
                </div>

                <div class="checkout-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Pickup Only</strong>
                        <p>Delivery is not available at the moment. You can only pick up your medicine at our physical store — <strong>Calloway Pharmacy</strong>.</p>
                    </div>
                </div>

                <h3 class="checkout-subtitle"><i class="fas fa-wallet"></i> Payment Method</h3>
                <div class="payment-options">
                    <label class="payment-option">
                        <input type="radio" name="paymentMethod" value="Cash on Pickup" checked>
                        <div class="payment-card">
                            <i class="fas fa-money-bill-wave" style="color:#22c55e; font-size:1.5rem;"></i>
                            <span class="payment-label">Cash on Pickup</span>
                            <span class="payment-desc">Pay when you pick up</span>
                        </div>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="paymentMethod" value="GCash">
                        <div class="payment-card">
                            <i class="fas fa-mobile-screen-button" style="color:#0070f0; font-size:1.5rem;"></i>
                            <span class="payment-label">GCash</span>
                            <span class="payment-desc">Online payment via GCash</span>
                        </div>
                    </label>
                </div>

                <?php if (!$isLoggedIn): ?>
                <div class="checkout-guest-notice">
                    <i class="fas fa-info-circle"></i>
                    <span>You are ordering as a guest. <a href="login.php" style="color:var(--primary-color); font-weight:600;">Login</a> for a better experience.</span>
                </div>
                <?php endif; ?>

                <button class="checkout-place-btn" onclick="placeOrder()">
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
            </div>

            <!-- Step 2: Order Confirmation -->
            <div class="checkout-step" id="checkoutStep2" style="display:none;">
                <div class="order-success">
                    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                    <h3>Order Placed Successfully!</h3>
                    <p class="order-ref" id="orderRefDisplay"></p>
                    <div class="success-details">
                        <p><i class="fas fa-store"></i> Please pick up your order at <strong>Calloway Pharmacy</strong></p>
                        <p><i class="fas fa-clock"></i> We will prepare your order shortly</p>
                        <p><i class="fas fa-bell"></i> Our staff has been notified</p>
                    </div>
                    <button class="checkout-done-btn" onclick="closeCheckoutModal(); location.reload();">
                        <i class="fas fa-home"></i> Continue Shopping
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Calloway Pharmacy. All rights reserved.</p>
    </footer>

    <script>
        // ─── Cart State (localStorage) ───
        let cart = JSON.parse(localStorage.getItem('calloway_cart') || '[]');
        let currentCategory = 'all';

        document.addEventListener('DOMContentLoaded', () => {
            updateCartUI();
            renderCartPanel();
        });

        // ─── Search + Category filter ───
        function filterProducts() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            applyFilters(query, currentCategory);
        }

        function filterByCategory(btn, category) {
            currentCategory = category;
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('sectionTitle').textContent = category === 'all' ? 'All Products' : category;
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            applyFilters(query, category);
        }

        function applyFilters(query, category) {
            const cards = document.querySelectorAll('.product-card');
            let visible = 0;
            cards.forEach(card => {
                const name = card.dataset.name || '';
                const cat = card.dataset.category || '';
                const desc = card.dataset.desc || '';
                const matchCategory = (category === 'all' || cat === category);
                const matchSearch = !query || name.includes(query) || desc.includes(query);
                if (matchCategory && matchSearch) { card.style.display = ''; visible++; }
                else { card.style.display = 'none'; }
            });
            document.getElementById('productCount').textContent = visible + ' item' + (visible !== 1 ? 's' : '');
            const grid = document.getElementById('productsGrid');
            let emptyState = grid.querySelector('.empty-state');
            if (visible === 0) {
                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.className = 'empty-state';
                    emptyState.innerHTML = '<i class="fas fa-search"></i><h3>No matches found</h3><p>Try a different search term or category.</p>';
                    grid.appendChild(emptyState);
                }
                emptyState.style.display = '';
            } else if (emptyState) { emptyState.style.display = 'none'; }
        }

        // ─── Cart Panel Toggle ───
        function toggleCartPanel() {
            document.getElementById('cartPanel').classList.toggle('open');
            document.getElementById('cartOverlay').classList.toggle('open');
            renderCartPanel();
        }

        // ─── Cart Logic ───
        function addToCart(productId, productName, price) {
            const existing = cart.find(item => item.id === productId);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({ id: productId, name: productName, price: parseFloat(price), quantity: 1 });
            }
            saveCart();
            showToast('Added ' + productName + ' to cart', 'success');
            // Auto-open cart panel so user can see what they added
            if (!document.getElementById('cartPanel').classList.contains('open')) {
                toggleCartPanel();
            } else {
                renderCartPanel();
            }
        }

        function changeQty(productId, delta) {
            const item = cart.find(i => i.id === productId);
            if (!item) return;
            item.quantity += delta;
            if (item.quantity <= 0) {
                cart = cart.filter(i => i.id !== productId);
            }
            saveCart();
            renderCartPanel();
        }

        function removeFromCart(productId) {
            const item = cart.find(i => i.id === productId);
            cart = cart.filter(i => i.id !== productId);
            saveCart();
            renderCartPanel();
            if (item) showToast('Removed ' + item.name, 'info');
        }

        function clearCart() {
            cart = [];
            saveCart();
            renderCartPanel();
            showToast('Cart cleared', 'info');
        }

        function saveCart() {
            localStorage.setItem('calloway_cart', JSON.stringify(cart));
            updateCartUI();
        }

        function updateCartUI() {
            const total = cart.reduce((s, i) => s + i.quantity, 0);
            document.getElementById('cartCount').textContent = total;
            document.getElementById('headerCartCount').textContent = total;
            document.getElementById('floatingCart').style.display = total > 0 ? 'block' : 'none';
        }

        // ─── Render Cart Panel Contents ───
        function renderCartPanel() {
            const body = document.getElementById('cartPanelBody');
            const footer = document.getElementById('cartPanelFooter');
            const emptyEl = document.getElementById('cartEmpty');

            if (cart.length === 0) {
                body.innerHTML = '';
                body.appendChild(createEmptyState());
                footer.style.display = 'none';
                return;
            }

            const totalAmount = cart.reduce((s, i) => s + (i.price * i.quantity), 0);
            const totalItems = cart.reduce((s, i) => s + i.quantity, 0);

            let html = '';
            cart.forEach(item => {
                const subtotal = item.price * item.quantity;
                html += `
                    <div class="cart-item">
                        <div class="cart-item-icon"><i class="fas fa-pills"></i></div>
                        <div class="cart-item-details">
                            <div class="cart-item-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                            <div class="cart-item-price-info">
                                Unit Price: ₱${item.price.toFixed(2)} &times; ${item.quantity}
                            </div>
                            <div class="cart-item-subtotal">₱${subtotal.toFixed(2)}</div>
                        </div>
                        <div class="cart-item-actions">
                            <button class="qty-btn" onclick="changeQty(${item.id}, 1)" title="Increase"><i class="fas fa-plus"></i></button>
                            <span class="cart-item-qty">${item.quantity}</span>
                            <button class="qty-btn" onclick="changeQty(${item.id}, -1)" title="Decrease"><i class="fas fa-minus"></i></button>
                            <button class="qty-btn remove" onclick="removeFromCart(${item.id})" title="Remove"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </div>
                `;
            });
            body.innerHTML = html;

            // Update footer totals
            footer.style.display = '';
            document.getElementById('cartSubtotal').textContent = '₱' + totalAmount.toFixed(2);
            document.getElementById('cartTotal').textContent = '₱' + totalAmount.toFixed(2);
            document.getElementById('cartTotalItems').textContent = totalItems + ' item' + (totalItems !== 1 ? 's' : '');
        }

        function createEmptyState() {
            const div = document.createElement('div');
            div.className = 'cart-empty';
            div.id = 'cartEmpty';
            div.innerHTML = '<i class="fas fa-shopping-basket"></i><p>Your cart is empty</p><span>Add some medicines to get started</span>';
            return div;
        }

        function checkout() {
            if (cart.length === 0) {
                showToast('Your cart is empty!', 'info');
                return;
            }

            // Build order summary in modal
            const overlay = document.getElementById('checkoutOverlay');
            const itemsList = document.getElementById('checkoutItemsList');
            let totalAmount = 0;
            let html = '';

            cart.forEach(item => {
                const qty = item.quantity || item.qty || 1;
                const lineTotal = item.price * qty;
                totalAmount += lineTotal;
                html += `
                    <div class="checkout-item">
                        <div>
                            <div class="checkout-item-name">${escapeHtml(item.name)}</div>
                            <div class="checkout-item-qty">${qty} x ₱${item.price.toFixed(2)}</div>
                        </div>
                        <div class="checkout-item-price">₱${lineTotal.toFixed(2)}</div>
                    </div>`;
            });

            itemsList.innerHTML = html;
            document.getElementById('checkoutSubtotal').textContent = '₱' + totalAmount.toFixed(2);
            document.getElementById('checkoutTotal').textContent = '₱' + totalAmount.toFixed(2);

            // Reset to step 1
            document.getElementById('checkoutStep1').style.display = '';
            document.getElementById('checkoutStep2').style.display = 'none';

            // Close cart panel, show checkout
            document.getElementById('cartPanel').classList.remove('open');
            document.getElementById('cartOverlay').classList.remove('open');
            overlay.classList.add('active');
        }

        function closeCheckoutModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('checkoutOverlay').classList.remove('active');
        }

        async function placeOrder() {
            const btn = document.querySelector('.checkout-place-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing Order...';

            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;

            // Map cart items to ensure 'qty' property exists for the backend
            const orderItems = cart.map(item => ({
                id: item.id,
                name: item.name,
                price: item.price,
                qty: item.quantity || item.qty || 1
            }));

            const orderData = {
                items: orderItems,
                payment_method: paymentMethod,
                customer_name: '<?php echo addslashes($customerName ?: "Guest"); ?>'
            };

            try {
                const res = await fetch('order_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(orderData)
                });

                const rawText = await res.text();
                let data = null;
                try {
                    data = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('Non-JSON response:', rawText);
                }

                if (!res.ok) {
                    const statusMsg = `Server error (${res.status})`;
                    showToast(data?.message || statusMsg, 'info');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order';
                    return;
                }

                if (data && data.success) {
                    // Show success step
                    document.getElementById('checkoutStep1').style.display = 'none';
                    document.getElementById('checkoutStep2').style.display = '';
                    document.getElementById('orderRefDisplay').textContent = 'Order Reference: ' + data.order_ref;

                    // Clear cart
                    cart = [];
                    localStorage.setItem('calloway_cart', JSON.stringify(cart));
                    updateCartUI();
                    renderCartPanel();

                    showToast('Order placed successfully! 🎉', 'success');
                } else {
                    showToast(data?.message || 'Failed to place order (invalid response)', 'info');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order';
                }
            } catch (err) {
                console.error('Order error:', err);
                const errMsg = err && err.message ? err.message : 'Network error. Please try again.';
                showToast(errMsg, 'info');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Place Order';
            }
        }

        // ─── Theme Toggle ───
        const toggleBtn = document.getElementById('theme-toggle');
        toggleBtn.addEventListener('click', () => {
            const body = document.body;
            if (body.getAttribute('data-theme') === 'light') {
                body.setAttribute('data-theme', 'dark');
                toggleBtn.textContent = '☀️';
            } else {
                body.setAttribute('data-theme', 'light');
                toggleBtn.textContent = '🌙';
            }
        });

        // ─── Toast Notification ───
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast' + (type === 'info' ? ' info' : '');
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i> ${message}`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 2500);
        }

        // ─── Utility ───
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
<?php
// ─── Helper: map category name → icon ───
function getCategoryEmoji($category) {
    $map = [
        'Pain Relief'   => '<i class="fas fa-pills" style="color:#ef4444"></i>',
        'Antibiotics'   => '<i class="fas fa-capsules" style="color:#3b82f6"></i>',
        'Vitamins'      => '<i class="fas fa-lemon" style="color:#f59e0b"></i>',
        'Cold & Flu'    => '<i class="fas fa-head-side-mask" style="color:#10b981"></i>',
        'Supplements'   => '<i class="fas fa-leaf" style="color:#8b5cf6"></i>',
        'Skin Care'     => '<i class="fas fa-hand-sparkles" style="color:#ec4899"></i>',
        'First Aid'     => '<i class="fas fa-kit-medical" style="color:#f97316"></i>',
        'Baby Care'     => '<i class="fas fa-baby" style="color:#06b6d4"></i>',
    ];
    return $map[$category] ?? '<i class="fas fa-prescription-bottle-medical" style="color:#64748b"></i>';
}
?>
