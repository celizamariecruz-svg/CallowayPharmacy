<?php
// Expiry Monitoring is now combined with Medicine Locator
// Redirect to the unified page with expiry filter pre-set
header('Location: medicine-locator.php');
exit;
?>
<?php
// ── Legacy code below kept for reference ──
include 'db_connection.php';
include 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

// Only staff can access expiry monitoring
$currentUser = $auth->getCurrentUser();
if (strtolower($currentUser['role_name'] ?? '') === 'customer') {
    header('Location: onlineordering.php');
    exit;
}

// Initialize variables
$message = '';
$message_type = '';

// Get unique categories for filter
$categories = [];
$categoriesQuery = "SELECT DISTINCT category FROM products WHERE (is_active = 1 OR is_active IS NULL) AND category IS NOT NULL AND category != '' ORDER BY category";
$categoriesResult = $conn->query($categoriesQuery);
if ($categoriesResult && $categoriesResult->num_rows > 0) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

try {
    // Get expiry threshold from settings
    $expiryThreshold = 30; // Default
    $s_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'expiry_alert_days'");
    $s_stmt->execute();
    if ($s_res = $s_stmt->get_result()->fetch_assoc()) {
        $expiryThreshold = (int) $s_res['setting_value'];
    }

    // Get all products with expiry dates
    $query = "SELECT product_id, name, category, stock_quantity, expiry_date, location 
              FROM products 
              WHERE (is_active = 1 OR is_active IS NULL) 
              AND expiry_date IS NOT NULL 
              ORDER BY expiry_date ASC";
    $result = $conn->query($query);

    // Calculate expiry statistics
    $totalProducts = 0;
    $expiredCount = 0;
    $expiringSoonCount = 0;
    $validCount = 0;

    $today = new DateTime();

    if ($result && $result->num_rows > 0) {
        $totalProducts = $result->num_rows;

        while ($row = $result->fetch_assoc()) {
            $expiryDate = new DateTime($row['expiry_date']);
            $diff = $today->diff($expiryDate)->format("%r%a"); // Difference in days

            if ($diff < 0) {
                $expiredCount++;
            } else if ($diff <= $expiryThreshold) {
                $expiringSoonCount++;
            } else {
                $validCount++;
            }
        }

        // Reset pointer for display
        $result->data_seek(0);
    }
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = "error";
}

$page_title = 'Expiry Monitoring';
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <script>
    // Apply theme immediately to prevent flash
    (function() {
      const theme = localStorage.getItem('calloway_theme') || 'light';
      document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Expiry Monitoring - Calloway Pharmacy</title>
    <link rel="stylesheet" href="design-system.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Premium Expiry Monitoring Styles - Matching Medicine Locator */
        * {
            box-sizing: border-box;
        }
        
        main {
            width: 100%;
            max-width: 1400px;
            margin: 100px auto 0;
            padding: 0 2rem 2rem;
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
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--shadow-color);
        }

        /* Stats Cards - Enhanced */
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

        /* Specific Card Styles */
        .stats-card.total {
            background: rgba(37, 99, 235, 0.05);
            border-left: 5px solid var(--primary-color);
        }

        .stats-card.total .value {
            color: var(--primary-color);
        }

        .stats-card.expired {
            background: rgba(239, 68, 68, 0.05);
            border-left: 5px solid #ef4444;
        }

        .stats-card.expired .value {
            color: #ef4444;
        }

        .stats-card.expiring {
            background: rgba(245, 158, 11, 0.05);
            border-left: 5px solid #f59e0b;
        }

        .stats-card.expiring .value {
            color: #f59e0b;
        }

        .stats-card.valid {
            background: rgba(16, 185, 129, 0.05);
            border-left: 5px solid var(--secondary-color);
        }

        .stats-card.valid .value {
            color: var(--secondary-color);
        }

        /* Search & Filter Area - Enhanced */
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

        .search-input-wrapper {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input-wrapper input {
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

        .search-input-wrapper::before {
            content: "🔍";
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.6;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .search-input-wrapper:has(input:focus)::before {
            opacity: 1;
            transform: translateY(-50%) scale(1.1);
        }

        .search-input-wrapper input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15), 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .search-input-wrapper input::placeholder {
            color: var(--text-color);
            opacity: 0.5;
        }

        .category-filter {
            min-width: 200px;
        }

        .category-filter select {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--input-border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--card-bg);
            color: var(--text-color);
            cursor: pointer;
        }

        .category-filter select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }

        .status-filter {
            min-width: 180px;
        }

        .status-filter select {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--input-border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--card-bg);
            color: var(--text-color);
            cursor: pointer;
        }

        .status-filter select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
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
            transition: transform 0.15s, box-shadow 0.15s;
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

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        /* Product Cards Grid - Medicine Locator Style */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .product-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 4px 15px var(--shadow-color);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid var(--table-border);
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s ease-out backwards;
        }

        .product-card::after {
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

        .product-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 30px var(--shadow-color);
            border-color: #667eea;
        }

        .product-card:hover::after {
            opacity: 1;
            animation: shimmer 2s linear infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .product-card.status-expired {
            border-color: #ef4444;
        }

        .product-card.status-expired::after {
            background: #ef4444;
        }

        .product-card.status-expiring {
            border-color: #f59e0b;
        }

        .product-card.status-expiring::after {
            background: #f59e0b;
        }

        .product-card.status-valid {
            border-color: var(--secondary-color);
        }

        .product-card.status-valid::after {
            background: var(--secondary-color);
        }

        .product-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-color);
        }

        .product-category {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: rgba(37, 99, 235, 0.1);
            color: var(--text-color);
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 20px;
            margin-bottom: 0.8rem;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin: 1rem 0;
        }

        .product-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: var(--text-color);
            opacity: 0.9;
        }

        .product-info-item i {
            width: 20px;
            color: #667eea;
        }

        .expiry-date-display {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 1rem 0;
            padding: 0.8rem 1rem;
            background: rgba(37, 99, 235, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(37, 99, 235, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .expiry-date-display.expired {
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .expiry-date-display.expiring-soon {
            background: rgba(245, 158, 11, 0.05);
            border-color: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .expiry-date-display.valid {
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.2);
            color: var(--secondary-color);
        }

        /* Status Badges - Enhanced */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .status-badge:hover {
            transform: scale(1.05);
        }

        .status-badge.expired {
            background: #ef4444;
            color: white;
            animation: dangerPulse 1s ease-in-out infinite;
        }

        .status-badge.expiring-soon {
            background: #f59e0b;
            color: white;
            animation: warningPulse 1.5s ease-in-out infinite;
        }

        .status-badge.valid {
            background: #10b981;
            color: white;
        }

        @keyframes dangerPulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3); }
            50% { box-shadow: 0 2px 16px rgba(231, 76, 60, 0.6); }
        }

        @keyframes warningPulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3); }
            50% { box-shadow: 0 2px 16px rgba(243, 156, 18, 0.6); }
        }

        .days-remaining {
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* No results styling */
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
            content: "📦";
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

        /* Quick action FAB */
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
            transition: transform 0.15s, box-shadow 0.15s;
            z-index: 100;
            animation: fadeInScale 0.5s ease-out;
            opacity: 0;
            pointer-events: none;
        }

        .quick-action-fab.visible {
            opacity: 1;
            pointer-events: auto;
        }

        .quick-action-fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.6);
        }

        .quick-action-fab:active {
            transform: scale(0.95);
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
            
            .search-input-wrapper,
            .category-filter,
            .status-filter {
                width: 100%;
                min-width: unset;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }
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
        background: #f59e0b;
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
        <p style="color: white; margin-top: 1.5rem; font-size: 1.2rem; font-weight: 600;">Loading Expiry Monitor...</p>
    </div>

    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <?php include 'header-component.php'; ?>

    <main>
        <?php if (!empty($message)): ?>
            <div class="error-message" style="padding: 1rem; background: #ffebee; color: #c62828; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #c62828;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Title & Actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="margin: 0; font-size: 2.2rem; color: var(--text-color); font-weight: 800;">⏰ Expiry Monitoring</h1>
                <p style="margin: 0.5rem 0 0; color: var(--text-light); opacity: 0.8;">Track product expiry dates and status in real-time</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stats-card total">
                <div class="label">📦 Total Products</div>
                <div class="value stat-number" data-target="<?php echo $totalProducts; ?>">0</div>
            </div>
            <div class="stats-card expired">
                <div class="label">❌ Expired Items</div>
                <div class="value stat-number" data-target="<?php echo $expiredCount; ?>">0</div>
            </div>
            <div class="stats-card expiring">
                <div class="label">⚠️ Expiring Soon</div>
                <div class="value stat-number" data-target="<?php echo $expiringSoonCount; ?>">0</div>
                <small style="color: #f39c12; margin-top: 5px; font-weight: 600;">(Within <?php echo $expiryThreshold; ?> days)</small>
            </div>
            <div class="stats-card valid">
                <div class="label">✅ Valid Stock</div>
                <div class="value stat-number" data-target="<?php echo $validCount; ?>">0</div>
            </div>
        </div>

        <!-- Search & Filter Card -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                <h2>🔍 Filter Products</h2>
                <div id="filter-badge" class="filter-badge" style="display: none;">
                    <span id="filter-count">0</span> filters active
                    <button onclick="clearFilters()" style="background: none; border: none; color: white; cursor: pointer; margin-left: 0.5rem;">✕</button>
                </div>
            </div>
            <div class="search-filter-container">
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <input type="text" id="searchInput" placeholder="Search by name, category, or location..." autocomplete="off">
                    </div>
                    <div class="category-filter">
                        <select id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="status-filter">
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="expired">Expired</option>
                            <option value="expiring-soon">Expiring Soon</option>
                            <option value="valid">Valid</option>
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

        <!-- Products Grid Card -->
        <div class="card">
            <h2>📋 Product Expiry Status</h2>
            <div class="product-grid" id="productsGrid">
                <?php if ($result && $result->num_rows > 0):
                    $index = 0;
                    while ($row = $result->fetch_assoc()):
                        $expiryDate = new DateTime($row['expiry_date']);
                        $diff = $today->diff($expiryDate)->format("%r%a");

                        $statusClass = '';
                        $statusText = '';
                        $icon = '';
                        $cardStatusClass = '';

                        if ($diff < 0) {
                            $statusClass = 'expired';
                            $statusText = 'Expired';
                            $icon = '<i class="fas fa-times-circle"></i>';
                            $cardStatusClass = 'status-expired';
                        } else if ($diff <= $expiryThreshold) {
                            $statusClass = 'expiring-soon';
                            $statusText = 'Expiring Soon';
                            $icon = '<i class="fas fa-exclamation-triangle"></i>';
                            $cardStatusClass = 'status-expiring';
                        } else {
                            $statusClass = 'valid';
                            $statusText = 'Valid';
                            $icon = '<i class="fas fa-check-circle"></i>';
                            $cardStatusClass = 'status-valid';
                        }
                        $index++;
                ?>
                    <div class="product-card <?= $cardStatusClass ?>" 
                         data-category="<?= htmlspecialchars($row['category']) ?>"
                         data-status="<?= $statusClass ?>"
                         data-name="<?= htmlspecialchars(strtolower($row['name'])) ?>"
                         data-location="<?= htmlspecialchars(strtolower($row['location'] ?: '')) ?>"
                         style="animation-delay: <?= $index * 0.05 ?>s;">
                        <h3><?= htmlspecialchars($row['name']) ?></h3>
                        <div class="product-category"><?= htmlspecialchars($row['category']) ?></div>
                        
                        <div class="product-info">
                            <div class="product-info-item">
                                <i class="fas fa-boxes"></i>
                                <span>Stock: <strong><?= $row['stock_quantity'] ?> units</strong></span>
                            </div>
                            <div class="product-info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($row['location'] ?: 'Location not set') ?></span>
                            </div>
                        </div>

                        <div class="expiry-date-display <?= $statusClass ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?= $expiryDate->format('F d, Y') ?></span>
                        </div>

                        <span class="status-badge <?= $statusClass ?>">
                            <?= $icon ?>
                            <?= $statusText ?>
                            <?php if ($diff >= 0): ?>
                                <span class="days-remaining">(<?= $diff ?> days left)</span>
                            <?php else: ?>
                                <span class="days-remaining">(<?= abs($diff) ?> days ago)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endwhile;
                else: ?>
                    <div class="no-results">
                        <h3>No products found</h3>
                        <p>No products with expiry dates are currently in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Scroll to top button -->
    <button class="quick-action-fab" id="scrollTopBtn" onclick="scrollToTop()" title="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <?php include 'footer-component.php'; ?>
    <?php if (file_exists('pills-background.php')) include 'pills-background.php'; ?>

    <div id="toast" class="toast"></div>

    <script src="theme.js"></script>
    <script src="shared-polish.js"></script>
    <script>
        // Variables
        let currentSearch = '';
        let currentCategory = '';
        let currentStatus = '';
        let originalTotalProducts = <?php echo $totalProducts; ?>;

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

        // Animate stat numbers on page load
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

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            // Animate stat numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach((stat, index) => {
                const target = parseInt(stat.getAttribute('data-target'));
                setTimeout(() => {
                    animateValue(stat, 0, target, 1500);
                }, index * 100);
            });

            // Setup event listeners
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const statusFilter = document.getElementById('statusFilter');

            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    currentSearch = e.target.value.toLowerCase();
                    filterProducts();
                });
            }

            if (categoryFilter) {
                categoryFilter.addEventListener('change', function(e) {
                    currentCategory = e.target.value;
                    filterProducts();
                });
            }

            if (statusFilter) {
                statusFilter.addEventListener('change', function(e) {
                    currentStatus = e.target.value;
                    filterProducts();
                });
            }

            // Setup scroll to top button
            const scrollBtn = document.getElementById('scrollTopBtn');
            if (scrollBtn) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 300) {
                        scrollBtn.classList.add('visible');
                    } else {
                        scrollBtn.classList.remove('visible');
                    }
                });
            }
        });

        // Filter products
        function filterProducts() {
            const cards = document.querySelectorAll('.product-card');
            let visibleCount = 0;
            let delay = 0;

            cards.forEach((card) => {
                const cardName = card.getAttribute('data-name') || '';
                const cardCategory = card.getAttribute('data-category') || '';
                const cardStatus = card.getAttribute('data-status') || '';
                const cardLocation = card.getAttribute('data-location') || '';

                // Check all filters
                const searchMatch = !currentSearch || 
                                   cardName.includes(currentSearch) || 
                                   cardCategory.toLowerCase().includes(currentSearch) ||
                                   cardLocation.includes(currentSearch);
                const categoryMatch = !currentCategory || cardCategory === currentCategory;
                const statusMatch = !currentStatus || cardStatus === currentStatus;

                if (searchMatch && categoryMatch && statusMatch) {
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

            // Handle no results
            handleNoResults(visibleCount);
            updateFilterBadge();
            updateFilteredStats(visibleCount);
        }

        // Handle no results message
        function handleNoResults(visibleCount) {
            const grid = document.getElementById('productsGrid');
            let noResults = grid.querySelector('.no-results-search');

            if (visibleCount === 0) {
                if (!noResults) {
                    const div = document.createElement('div');
                    div.className = 'no-results no-results-search';
                    div.innerHTML = '<h3>No matching products found</h3><p>Try adjusting your search or filter criteria</p>';
                    grid.appendChild(div);
                }
            } else if (noResults) {
                noResults.remove();
            }
        }

        // Update filter badge
        function updateFilterBadge() {
            const filterBadge = document.getElementById('filter-badge');
            const filterCount = document.getElementById('filter-count');
            
            let activeFilters = 0;
            if (currentSearch) activeFilters++;
            if (currentCategory) activeFilters++;
            if (currentStatus) activeFilters++;
            
            if (activeFilters > 0) {
                filterBadge.style.display = 'inline-flex';
                filterCount.textContent = activeFilters;
            } else {
                filterBadge.style.display = 'none';
            }
        }

        // Update filtered stats
        function updateFilteredStats(visibleCount) {
            const totalCard = document.querySelector('.stats-card.total .value');
            if (totalCard) {
                const currentValue = parseInt(totalCard.textContent) || 0;
                const targetValue = (currentCategory || currentSearch || currentStatus) ? visibleCount : originalTotalProducts;
                
                if (currentValue !== targetValue) {
                    animateValue(totalCard, currentValue, targetValue, 500);
                }
            }
        }

        // Clear all filters
        function clearFilters() {
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const statusFilter = document.getElementById('statusFilter');

            if (searchInput) searchInput.value = '';
            if (categoryFilter) categoryFilter.value = '';
            if (statusFilter) statusFilter.value = '';

            currentSearch = '';
            currentCategory = '';
            currentStatus = '';

            filterProducts();

            // Reset stats
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach((stat) => {
                const target = parseInt(stat.getAttribute('data-target'));
                const current = parseInt(stat.textContent);
                if (current !== target) {
                    animateValue(stat, current, target, 500);
                }
            });

            showNotification('🔄 Filters cleared', 'success');
        }

        // Scroll to top
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Simple notification function
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

        // Keyboard Shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl+F or F3 - Focus search
            if ((e.ctrlKey && e.key === 'f') || e.key === 'F3') {
                e.preventDefault();
                document.getElementById('searchInput')?.focus();
            }
            // Escape - Clear search focus
            if (e.key === 'Escape') {
                const searchInput = document.getElementById('searchInput');
                if (searchInput && searchInput === document.activeElement) {
                    searchInput.value = '';
                    searchInput.blur();
                    currentSearch = '';
                    filterProducts();
                }
            }
        });
    </script>
</body>

</html>