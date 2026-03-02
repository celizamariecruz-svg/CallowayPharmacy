<?php
require_once 'Security.php';
Security::initSession();
include 'db_connection.php';
require_once 'ImageHelper.php';

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
    // Build query — fetch all needed fields for locator + expiry
    $sql = "SELECT p.product_id, p.name, p.category, p.stock_quantity, p.price, p.expiry_date, p.location,
                   p.image_url, p.generic_name, p.brand_name, p.strength, p.dosage_form,
                   p.sell_by_piece, p.price_per_piece, p.requires_prescription
            FROM products p
            WHERE (p.is_active = 1 OR p.is_active IS NULL)";

    if (!empty($search)) {
        $search = '%' . $search . '%';
        $sql .= " AND (p.name LIKE ? OR p.category LIKE ? OR CAST(p.price AS CHAR) LIKE ? OR p.location LIKE ? OR p.generic_name LIKE ? OR p.brand_name LIKE ?)";
    }
    if (!empty($category_filter)) {
        $sql .= " AND p.category = ?";
    }

    $sql .= " ORDER BY p.expiry_date ASC, p.name ASC";

    // Get unique categories
    $categoriesQuery = "SELECT DISTINCT category FROM products WHERE (is_active = 1 OR is_active IS NULL) AND category IS NOT NULL AND category != '' ORDER BY category";
    $categoriesResult = $conn->query($categoriesQuery);
    $categories = [];
    if ($categoriesResult && $categoriesResult->num_rows > 0) {
        while ($row = $categoriesResult->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

    // Prepare and execute
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

    // Get expiry threshold from settings
    $expiryThreshold = 30;
    $s_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'expiry_alert_days'");
    if ($s_stmt) {
        $s_stmt->execute();
        if ($s_res = $s_stmt->get_result()->fetch_assoc()) {
            $expiryThreshold = (int) $s_res['setting_value'];
        }
    }

    // Combined statistics
    $totalMedicines = $result->num_rows;
    $uniqueCategories = count($categories);
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $expiredCount = 0;
    $expiringSoonCount = 0;
    $validExpiryCount = 0;
    $today = new DateTime();

    $statsResult = $conn->query("SELECT
      COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
      COUNT(CASE WHEN stock_quantity > 0 AND stock_quantity < 10 THEN 1 END) as low_stock,
      COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 END) as expired,
      COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$expiryThreshold} DAY) THEN 1 END) as expiring_soon,
      COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date > DATE_ADD(CURDATE(), INTERVAL {$expiryThreshold} DAY) THEN 1 END) as valid_expiry
      FROM products WHERE is_active = 1 OR is_active IS NULL");
    if ($statsResult && $statsResult->num_rows > 0) {
        $statsRow = $statsResult->fetch_assoc();
        $lowStockCount    = $statsRow['low_stock'];
        $outOfStockCount  = $statsRow['out_of_stock'];
        $expiredCount     = $statsRow['expired'];
        $expiringSoonCount = $statsRow['expiring_soon'];
        $validExpiryCount = $statsRow['valid_expiry'];
    }

    // Handle location update (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location'])) {
        $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
        $new_location = trim($_POST['new_location']);
        if ($product_id === false || empty($new_location)) {
            echo json_encode(['error' => 'Invalid input data']);
            exit;
        }
        try {
            $updateStmt = $conn->prepare("UPDATE products SET location = ? WHERE product_id = ?");
            $updateStmt->bind_param('si', $new_location, $product_id);
            echo json_encode($updateStmt->execute() ? ['success' => true] : ['error' => 'Failed to update location']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

$page_title = "Medicine Locator & Expiry Monitor";
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<script>
(function() {
  var theme = localStorage.getItem('calloway_theme') || 'light';
  document.documentElement.setAttribute('data-theme', theme);
})();
</script>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<title>Medicine Locator &amp; Expiry Monitor &mdash; Calloway Pharmacy</title>
<link rel="stylesheet" href="design-system.css">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="shared-polish.css">
<link rel="stylesheet" href="polish.css">
<link rel="stylesheet" href="responsive.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Reset ── */
*{box-sizing:border-box}
html{scroll-behavior:smooth}

/* ── Layout ── */
main{
  width:100%;max-width:1400px;margin:80px auto 0;padding:0 2rem 2rem;
  animation:pageFadeIn .6s ease-out;
}
@keyframes pageFadeIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* ── Page header ── */
.page-header{
  display:flex;justify-content:space-between;align-items:center;
  flex-wrap:wrap;gap:1rem;margin-bottom:2rem;
}
.page-header h1{margin:0;font-size:2rem;font-weight:800;color:var(--text-color)}
.page-header p{margin:.3rem 0 0;color:var(--text-color);opacity:.7;font-size:.95rem}

/* ── Quick-view tabs ── */
.view-tabs{
  display:flex;gap:.25rem;padding:4px;border-radius:12px;
  background:var(--table-border,rgba(0,0,0,.06));width:fit-content;
}
.view-tab{
  padding:.55rem 1.2rem;border:none;border-radius:10px;cursor:pointer;
  font-weight:700;font-size:.9rem;background:transparent;
  color:var(--text-color);opacity:.7;transition:all .25s ease;
}
.view-tab:hover{opacity:1}
.view-tab.active{
  background:var(--primary-color);color:#fff;opacity:1;
  box-shadow:0 2px 8px var(--shadow-color);
}

/* ── Statistics grid ── */
.stats-grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));
  gap:1rem;margin-bottom:2rem;
}
.stat-card{
  background:var(--card-bg);border-radius:14px;padding:1.2rem 1.4rem;
  border:1px solid var(--table-border);display:flex;flex-direction:column;
  align-items:center;text-align:center;transition:all .3s ease;
  position:relative;overflow:hidden;
}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 6px 18px var(--shadow-color)}
.stat-card .stat-label{
  font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;
  color:var(--text-color);opacity:.7;margin-bottom:.35rem;
}
.stat-card .stat-val{font-size:1.9rem;font-weight:800}
.stat-card.c-blue  .stat-val{color:var(--primary-color)}
.stat-card.c-green .stat-val{color:#10b981}
.stat-card.c-amber .stat-val{color:#f59e0b}
.stat-card.c-red   .stat-val{color:#ef4444}
.stat-card.c-blue {border-left:4px solid var(--primary-color);background:rgba(37,99,235,.04)}
.stat-card.c-green{border-left:4px solid #10b981;background:rgba(16,185,129,.04)}
.stat-card.c-amber{border-left:4px solid #f59e0b;background:rgba(245,158,11,.04)}
.stat-card.c-red  {border-left:4px solid #ef4444;background:rgba(239,68,68,.04)}

/* ── Section card ── */
.sec-card{
  background:var(--card-bg);border-radius:14px;padding:1.5rem 2rem;
  margin-bottom:1.5rem;border:1px solid var(--table-border);transition:background .3s ease;
}
.sec-card h2{
  color:var(--primary-color);font-weight:700;margin:0 0 1rem;
  font-size:1.15rem;display:flex;align-items:center;gap:.5rem;
}

/* ── Search / Filters ── */
.filter-row{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center}
.filter-row .search-wrap{flex:1;min-width:260px;position:relative}
.filter-row .search-wrap input{
  width:100%;padding:.8rem 1rem .8rem 2.6rem;border:2px solid var(--input-border);
  border-radius:10px;font-size:1rem;background:var(--card-bg);color:var(--text-color);
  transition:all .25s ease;
}
.filter-row .search-wrap::before{
  content:"\f002";font-family:"Font Awesome 6 Free";font-weight:900;
  position:absolute;left:.9rem;top:50%;transform:translateY(-50%);
  color:var(--text-color);opacity:.4;font-size:.95rem;pointer-events:none;
}
.filter-row .search-wrap input:focus{
  outline:none;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(37,99,235,.12);
}
.filter-row select{
  padding:.8rem 1rem;border:2px solid var(--input-border);border-radius:10px;
  font-size:.95rem;background:var(--card-bg);color:var(--text-color);
  cursor:pointer;transition:all .25s ease;min-width:160px;
}
.filter-row select:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.clear-btn{
  display:inline-flex;align-items:center;gap:.4rem;padding:.8rem 1.2rem;
  background:#ef4444;color:#fff;border:none;border-radius:10px;
  font-size:.9rem;font-weight:600;cursor:pointer;transition:all .25s ease;
}
.clear-btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(239,68,68,.35)}
.badge-pill{
  display:none;align-items:center;gap:.4rem;padding:.35rem .85rem;
  background:var(--primary-color);color:#fff;border-radius:20px;
  font-size:.82rem;font-weight:700;
}
.badge-pill.visible{display:inline-flex}
.badge-pill .cnt{
  background:rgba(255,255,255,.25);padding:.12rem .45rem;border-radius:12px;font-weight:800;
}

/* ── Product grid ── */
.product-grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
  gap:1.25rem;margin-top:1rem;
}

/* ── Product card ── */
.prod-card{
  background:var(--card-bg);border-radius:14px;padding:1.4rem;
  border:2px solid var(--table-border);position:relative;overflow:hidden;
  transition:all .3s ease;
}
.prod-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:var(--primary-color);opacity:0;transition:opacity .25s ease;
}
.prod-card:hover{
  transform:translateY(-5px);box-shadow:0 8px 24px var(--shadow-color);
  border-color:var(--primary-color);
}
.prod-card:hover::before{opacity:1}
.prod-card.b-red{border-color:#ef4444}
.prod-card.b-red::before{background:#ef4444}
.prod-card.b-amber{border-color:#f59e0b}
.prod-card.b-amber::before{background:#f59e0b}
.prod-card.b-green{border-color:#10b981}
.prod-card.b-green::before{background:#10b981}

.prod-top{display:flex;align-items:flex-start;gap:.75rem;margin-bottom:.6rem}
.prod-img{
  width:52px;height:52px;border-radius:10px;object-fit:cover;
  border:1px solid var(--table-border);flex-shrink:0;
}
.prod-name{font-size:1.12rem;font-weight:700;color:var(--text-color);margin:0}
.prod-variant{font-size:.78rem;color:var(--text-color);opacity:.6;margin-top:2px}
.rx-tag{
  display:inline-block;background:#e65100;color:#fff;font-size:.6rem;
  padding:2px 6px;border-radius:4px;font-weight:700;vertical-align:middle;margin-left:4px;
}
.cat-badge{
  display:inline-block;padding:.2rem .65rem;font-size:.8rem;font-weight:600;
  border-radius:16px;margin-top:.4rem;
  background:rgba(37,99,235,.08);color:var(--text-color);border:1px solid rgba(37,99,235,.18);
}
.price-tag{font-size:1.3rem;font-weight:800;color:var(--primary-color);margin-top:.5rem}
.piece-price{font-size:.78rem;color:var(--primary-color);font-weight:600}

/* ── Stock pill ── */
.stock-pill{
  display:inline-block;padding:.25rem .75rem;border-radius:40px;
  font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
  color:#fff;margin-top:.6rem;
}
.stock-pill.in-stock{background:#10b981}
.stock-pill.low-stock{background:#f59e0b}
.stock-pill.out-of-stock{background:#ef4444}

/* ── Expiry row ── */
.expiry-row{
  display:flex;align-items:center;gap:.5rem;margin-top:.55rem;
  padding:.45rem .7rem;border-radius:10px;font-size:.85rem;font-weight:600;
  border:1px solid var(--table-border);background:rgba(37,99,235,.03);
}
.expiry-row i{font-size:.85rem}
.expiry-row.e-expired{background:rgba(239,68,68,.05);border-color:rgba(239,68,68,.2);color:#ef4444}
.expiry-row.e-soon{background:rgba(245,158,11,.05);border-color:rgba(245,158,11,.2);color:#f59e0b}
.expiry-row.e-valid{background:rgba(16,185,129,.05);border-color:rgba(16,185,129,.2);color:#10b981}

/* ── Status pill ── */
.status-pill{
  display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .7rem;
  border-radius:40px;font-size:.72rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.4px;color:#fff;margin-top:.4rem;
}
.status-pill.s-expired{background:#ef4444}
.status-pill.s-soon{background:#f59e0b}
.status-pill.s-valid{background:#10b981}
.status-pill .days-txt{font-weight:600;opacity:.9}

/* ── Location row ── */
.loc-row{
  display:flex;align-items:center;gap:.6rem;margin-top:.65rem;
  padding:.5rem .75rem;border-radius:10px;
  background:rgba(37,99,235,.05);border:1px solid rgba(37,99,235,.12);
  transition:all .25s ease;
}
.loc-row:hover{background:rgba(37,99,235,.1);border-color:rgba(37,99,235,.25)}
.loc-text{flex:1;font-weight:600;color:var(--text-color);font-size:.88rem;display:flex;align-items:center;gap:.35rem}
.loc-text::before{content:"\f3c5";font-family:"Font Awesome 6 Free";font-weight:900;color:var(--primary-color);font-size:.85rem}
.edit-loc,.save-loc{
  background:var(--primary-color);border:none;padding:.35rem;cursor:pointer;
  color:#fff;border-radius:6px;display:flex;align-items:center;
  justify-content:center;transition:all .2s ease;
}
.edit-loc:hover,.save-loc:hover{transform:scale(1.1)}
.loc-input{
  flex:1;padding:.45rem .7rem;border:2px solid var(--input-border);border-radius:8px;
  font-size:.88rem;font-weight:600;color:var(--text-color);background:var(--card-bg);
  transition:all .25s ease;
}
.loc-input:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(37,99,235,.12)}

/* ── No results ── */
.no-results{grid-column:1/-1;text-align:center;padding:3rem 2rem;color:var(--text-color)}
.no-results h3{font-size:1.5rem;margin-bottom:.5rem;opacity:.7}
.no-results p{opacity:.5}

/* ── Scroll-to-top FAB ── */
.fab-top{
  position:fixed;bottom:2rem;right:2rem;width:46px;height:46px;border-radius:50%;
  background:var(--primary-color);color:#fff;border:none;
  box-shadow:0 4px 14px var(--shadow-color);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:1rem;
  transition:all .3s ease;z-index:100;opacity:0;pointer-events:none;
}
.fab-top.visible{opacity:1;pointer-events:auto}
.fab-top:hover{transform:scale(1.1);box-shadow:0 6px 20px var(--shadow-color)}

/* ── Responsive ── */
@media(max-width:768px){
  main{padding:0 1rem;margin-top:70px}
  .filter-row{flex-direction:column}
  .filter-row .search-wrap,.filter-row select{width:100%;min-width:unset}
  .product-grid{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .page-header{flex-direction:column;align-items:flex-start}
}

/* ── Print ── */
@media print{
  header,.topbar,.sidebar,.sidebar-overlay,.fab-top,.clear-btn,
  .filter-row,.view-tabs,.edit-loc{display:none!important}
  main{margin:0!important;padding:0!important}
  .prod-card{break-inside:avoid;page-break-inside:avoid;border:1px solid #ccc!important}
}
</style>
</head>
<body>

<?php
$page_title = 'Medicine Locator & Expiry Monitor';
include 'header-component.php';
?>

<main>
  <?php if (!empty($error_message)): ?>
    <div style="padding:1rem;background:#ffebee;color:#c62828;border-radius:10px;margin-bottom:1.5rem;border-left:4px solid #c62828;">
      <?= htmlspecialchars($error_message) ?>
    </div>
  <?php endif; ?>

  <!-- Page header -->
  <div class="page-header">
    <div>
      <h1>Medicine Locator &amp; Expiry Monitor</h1>
      <p>Search medicines, check stock levels, and track expiry dates — all in one place.</p>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
      <div class="view-tabs">
        <button class="view-tab active" data-view="all" onclick="setView('all')">All</button>
        <button class="view-tab" data-view="expired" onclick="setView('expired')">Expired</button>
        <button class="view-tab" data-view="expiring" onclick="setView('expiring')">Expiring</button>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card c-blue">
      <div class="stat-label">Total Medicines</div>
      <div class="stat-val stat-num" data-target="<?= $totalMedicines ?>">0</div>
    </div>
    <div class="stat-card c-green">
      <div class="stat-label">Categories</div>
      <div class="stat-val stat-num" data-target="<?= $uniqueCategories ?>">0</div>
    </div>
    <div class="stat-card c-amber">
      <div class="stat-label">Low Stock</div>
      <div class="stat-val stat-num" data-target="<?= $lowStockCount ?>">0</div>
    </div>
    <div class="stat-card c-red">
      <div class="stat-label">Out of Stock</div>
      <div class="stat-val stat-num" data-target="<?= $outOfStockCount ?>">0</div>
    </div>
    <div class="stat-card c-red">
      <div class="stat-label">Expired</div>
      <div class="stat-val stat-num" data-target="<?= $expiredCount ?>">0</div>
    </div>
    <div class="stat-card c-amber">
      <div class="stat-label">Expiring Soon</div>
      <div class="stat-val stat-num" data-target="<?= $expiringSoonCount ?>">0</div>
      <small style="color:#f59e0b;font-weight:600;font-size:.7rem">(Within <?= $expiryThreshold ?> days)</small>
    </div>
    <div class="stat-card c-green">
      <div class="stat-label">Valid Stock</div>
      <div class="stat-val stat-num" data-target="<?= $validExpiryCount ?>">0</div>
    </div>
  </div>

  <!-- Search & Filter -->
  <div class="sec-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem">
      <h2><i class="fas fa-filter"></i> Search &amp; Filter</h2>
      <div class="badge-pill" id="filterBadge"><span class="cnt" id="filterCnt">0</span> filters active</div>
    </div>
    <div class="filter-row">
      <div class="search-wrap">
        <input type="text" id="searchInput" placeholder="Search by name, category, price, or location&hellip;" autocomplete="off">
      </div>
      <select id="categoryFilter">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="statusFilter">
        <option value="">All Expiry Status</option>
        <option value="expired">Expired</option>
        <option value="expiring-soon">Expiring Soon</option>
        <option value="valid">Valid</option>
      </select>
      <button class="clear-btn" onclick="clearFilters()"><i class="fas fa-times"></i> Clear</button>
    </div>
  </div>

  <!-- Products -->
  <div class="sec-card">
    <h2><i class="fas fa-pills"></i> Medicine Inventory</h2>
    <div class="product-grid" id="productGrid">
      <?php if ($result && $result->num_rows > 0):
        $idx = 0;
        while ($row = $result->fetch_assoc()):
          $row['image_url'] = resolveProductImageUrl((string)($row['image_url'] ?? ''), (string)($row['name'] ?? ''));
          // Stock
          $stockClass = $row['stock_quantity'] <= 0 ? 'out-of-stock' :
                        ($row['stock_quantity'] < 10 ? 'low-stock' : 'in-stock');
          $stockText  = $row['stock_quantity'] <= 0 ? 'Out of Stock' :
                        ($row['stock_quantity'] < 10 ? 'Low Stock' : 'In Stock');

          // Expiry
          $expiryStatus = '';
          $expiryClass  = '';
          $borderClass  = '';
          $daysDiff     = null;
          if (!empty($row['expiry_date'])) {
              $expiryDate = new DateTime($row['expiry_date']);
              $daysDiff = (int) $today->diff($expiryDate)->format("%r%a");
              if ($daysDiff < 0) {
                  $expiryStatus = 'expired';
                  $expiryClass  = 'e-expired';
                  $borderClass  = 'b-red';
              } elseif ($daysDiff <= $expiryThreshold) {
                  $expiryStatus = 'expiring-soon';
                  $expiryClass  = 'e-soon';
                  $borderClass  = 'b-amber';
              } else {
                  $expiryStatus = 'valid';
                  $expiryClass  = 'e-valid';
                  $borderClass  = 'b-green';
              }
          }
          $variant = trim(($row['strength'] ?? '') . ' ' . ($row['dosage_form'] ?? ''));
          $idx++;
      ?>
        <div class="prod-card <?= $borderClass ?>"
             data-category="<?= htmlspecialchars($row['category']) ?>"
             data-status="<?= $expiryStatus ?>"
             data-name="<?= htmlspecialchars(strtolower($row['name'])) ?>"
             data-location="<?= htmlspecialchars(strtolower($row['location'] ?: '')) ?>">

          <div class="prod-top">
            <?php if (!empty($row['image_url'])): ?>
              <img class="prod-img" src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
            <?php else: ?>
              <img class="prod-img" src="assets/placeholder-product.svg" alt="No image" style="opacity:.45">
            <?php endif; ?>
            <div>
              <h3 class="prod-name">
                <?= htmlspecialchars($row['name']) ?>
                <?php if (!empty($row['requires_prescription'])): ?><span class="rx-tag">Rx</span><?php endif; ?>
              </h3>
              <?php if ($variant): ?>
                <div class="prod-variant"><?= htmlspecialchars($variant) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="cat-badge"><?= htmlspecialchars($row['category']) ?></div>

          <div class="price-tag">&#8369;<?= number_format($row['price'], 2) ?></div>
          <?php if (!empty($row['sell_by_piece']) && $row['price_per_piece'] > 0): ?>
            <div class="piece-price">&#8369;<?= number_format($row['price_per_piece'], 2) ?>/piece</div>
          <?php endif; ?>

          <div><span class="stock-pill <?= $stockClass ?>"><?= $stockText ?> (<?= $row['stock_quantity'] ?>)</span></div>

          <?php if (!empty($row['expiry_date'])): ?>
            <div class="expiry-row <?= $expiryClass ?>">
              <i class="fas fa-calendar-alt"></i>
              <span><?= $expiryDate->format('M d, Y') ?></span>
            </div>
            <div>
              <span class="status-pill <?= $expiryStatus === 'expired' ? 's-expired' : ($expiryStatus === 'expiring-soon' ? 's-soon' : 's-valid') ?>">
                <?php if ($expiryStatus === 'expired'): ?>
                  <i class="fas fa-times-circle"></i> Expired
                  <span class="days-txt">(<?= abs($daysDiff) ?> days ago)</span>
                <?php elseif ($expiryStatus === 'expiring-soon'): ?>
                  <i class="fas fa-exclamation-triangle"></i> Expiring Soon
                  <span class="days-txt">(<?= $daysDiff ?> days left)</span>
                <?php else: ?>
                  <i class="fas fa-check-circle"></i> Valid
                  <span class="days-txt">(<?= $daysDiff ?> days left)</span>
                <?php endif; ?>
              </span>
            </div>
          <?php else: ?>
            <div class="expiry-row" style="opacity:.5"><i class="fas fa-calendar-alt"></i> No expiry date set</div>
          <?php endif; ?>

          <div class="loc-row" data-product-id="<?= $row['product_id'] ?>">
            <span class="loc-text"><?= htmlspecialchars($row['location'] ?: 'Location not set') ?></span>
            <button class="edit-loc" onclick="editLocation(<?= $row['product_id'] ?>)" title="Edit location">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </button>
          </div>
        </div>
      <?php endwhile; else: ?>
        <div class="no-results">
          <h3>No medicines found</h3>
          <p>Try adjusting your search criteria or check back later.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Scroll-to-top -->
<button class="fab-top" id="fabTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">
  <i class="fas fa-arrow-up"></i>
</button>

<?php include 'footer-component.php'; ?>
<?php if (file_exists('pills-background.php')) include 'pills-background.php'; ?>

<script src="theme.js"></script>
<script src="shared-polish.js"></script>
<script>
/* ── State ── */
var currentSearch   = '';
var currentCategory = '';
var currentStatus   = '';
var currentView     = 'all';

/* ── Animate numbers ── */
function animVal(el, from, to, ms) {
  var t0 = null;
  function step(ts) {
    if (!t0) t0 = ts;
    var p = Math.min((ts - t0) / ms, 1);
    el.textContent = Math.floor(p * (to - from) + from);
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
document.addEventListener('DOMContentLoaded', function() {
  var els = document.querySelectorAll('.stat-num');
  els.forEach(function(el, i) {
    var t = parseInt(el.dataset.target);
    setTimeout(function(){ animVal(el, 0, t, 1200); }, i * 60);
  });
});

/* ── View tabs ── */
function setView(v) {
  currentView = v;
  document.querySelectorAll('.view-tab').forEach(function(b){ b.classList.toggle('active', b.dataset.view === v); });
  var s = document.getElementById('statusFilter');
  if (v === 'expired')       { s.value = 'expired';       currentStatus = 'expired'; }
  else if (v === 'expiring') { s.value = 'expiring-soon'; currentStatus = 'expiring-soon'; }
  else                       { s.value = '';               currentStatus = ''; }
  filterCards();
}

/* ── Filter ── */
function filterCards() {
  var cards = document.querySelectorAll('.prod-card');
  var vis = 0;
  cards.forEach(function(c) {
    var name = c.dataset.name || '';
    var cat  = c.dataset.category || '';
    var st   = c.dataset.status || '';
    var loc  = c.dataset.location || '';

    var sm = !currentSearch || name.includes(currentSearch) || cat.toLowerCase().includes(currentSearch) || loc.includes(currentSearch);
    var cm = !currentCategory || cat === currentCategory;
    var stm = !currentStatus || st === currentStatus;

    if (sm && cm && stm) {
      c.style.display = 'block'; c.style.opacity = '1'; c.style.transform = 'none'; vis++;
    } else {
      c.style.opacity = '0'; c.style.transform = 'scale(.97)';
      setTimeout(function(){ if(c.style.opacity==='0') c.style.display='none'; }, 180);
    }
  });
  handleEmpty(vis);
  updateBadge();
}

function handleEmpty(vis) {
  var grid = document.getElementById('productGrid');
  var nr = grid.querySelector('.no-results-dyn');
  if (vis === 0) {
    if (!nr) { nr = document.createElement('div'); nr.className = 'no-results no-results-dyn'; nr.innerHTML = '<h3>No matching medicines</h3><p>Try adjusting your filters.</p>'; grid.appendChild(nr); }
  } else if (nr) nr.remove();
}

function updateBadge() {
  var n = 0;
  if (currentSearch) n++;
  if (currentCategory) n++;
  if (currentStatus) n++;
  var b = document.getElementById('filterBadge');
  var c = document.getElementById('filterCnt');
  if (n) { b.classList.add('visible'); c.textContent = n; }
  else   { b.classList.remove('visible'); }
}

function clearFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('categoryFilter').value = '';
  document.getElementById('statusFilter').value = '';
  currentSearch = ''; currentCategory = ''; currentStatus = ''; currentView = 'all';
  document.querySelectorAll('.view-tab').forEach(function(b){ b.classList.toggle('active', b.dataset.view === 'all'); });
  filterCards();
  document.querySelectorAll('.stat-num').forEach(function(el){
    var t = parseInt(el.dataset.target), cur = parseInt(el.textContent);
    if (cur !== t) animVal(el, cur, t, 400);
  });
  showToast('Filters cleared', 'success');
}

/* ── Events ── */
document.addEventListener('DOMContentLoaded', function() {
  var si = document.getElementById('searchInput');
  var cf = document.getElementById('categoryFilter');
  var sf = document.getElementById('statusFilter');
  if (si) si.addEventListener('input', function(e){ currentSearch = e.target.value.toLowerCase(); filterCards(); });
  if (cf) cf.addEventListener('change', function(e){ currentCategory = e.target.value; filterCards(); });
  if (sf) sf.addEventListener('change', function(e){ currentStatus = e.target.value; filterCards(); });
  var fab = document.getElementById('fabTop');
  window.addEventListener('scroll', function(){ fab.classList.toggle('visible', window.scrollY > 280); });
});

/* ── Location editing ── */
function editLocation(pid) {
  var row = document.querySelector('.loc-row[data-product-id="'+pid+'"]');
  var cur = row.querySelector('.loc-text').textContent;
  var blank = cur === 'Location not set';
  var inp = document.createElement('input');
  inp.type = 'text'; inp.value = blank ? '' : cur;
  inp.placeholder = 'Enter location'; inp.className = 'loc-input';
  var btn = document.createElement('button');
  btn.className = 'save-loc';
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
  btn.onclick = function(){ saveLocation(pid, inp.value); };
  row.innerHTML = '';
  row.appendChild(inp); row.appendChild(btn);
  inp.focus();
  inp.addEventListener('keydown', function(e){ if(e.key==='Enter') saveLocation(pid, inp.value); });
}

function saveLocation(pid, val) {
  if (!val.trim()) { showToast('Location cannot be empty', 'error'); return; }
  var fd = new FormData();
  fd.append('update_location', '1');
  fd.append('product_id', pid);
  fd.append('new_location', val);
  fetch('medicine-locator.php', { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.success) {
        var row = document.querySelector('.loc-row[data-product-id="'+pid+'"]');
        row.innerHTML = '<span class="loc-text">'+val+'</span>'+
          '<button class="edit-loc" onclick="editLocation('+pid+')" title="Edit location">'+
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>';
        row.closest('.prod-card').dataset.location = val.toLowerCase();
        showToast('Location updated', 'success');
      } else { showToast(d.error || 'Update failed', 'error'); }
    })
    .catch(function(){ showToast('Error updating location', 'error'); });
}

/* ── Toast (uses shared-polish.js ToastNotification) ── */
function showToast(msg, type) {
  if (typeof toast !== 'undefined' && toast.show) { toast.show(msg, type); return; }
  var t = document.createElement('div');
  t.textContent = msg;
  t.style.cssText = 'position:fixed;top:80px;right:20px;padding:.75rem 1.2rem;border-radius:10px;color:#fff;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.25);z-index:10000;transition:opacity .3s ease;background:'+(type==='success'?'#10b981':'#ef4444')+';';
  document.body.appendChild(t);
  setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){t.remove();},300); }, 2800);
}

/* ── Keyboard shortcuts ── */
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey && e.key === 'f') || e.key === 'F3') { e.preventDefault(); document.getElementById('searchInput').focus(); }
  if (e.key === 'Escape') {
    var s = document.getElementById('searchInput');
    if (s && s === document.activeElement) { s.value = ''; s.blur(); currentSearch = ''; filterCards(); }
  }
});
</script>
</body>
</html>
