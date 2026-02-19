<?php
// Require authentication to access dashboard
require_once 'Security.php';
Security::initSession();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$isCustomer = (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'customer');
$isCashier = (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'cashier');
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<title>Calloway Pharmacy</title>
<script>
  (function () {
    try {
      var key = 'calloway_theme';
      var theme = localStorage.getItem(key);
      if (!theme) {
        theme = 'dark';
        localStorage.setItem(key, theme);
      }
      document.documentElement.setAttribute('data-theme', theme);
    } catch (e) {
      // ignore
    }
  })();
</script>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="home.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <?php include 'header-component.php'; ?>

  <?php
    // Time-of-day greeting
    $hour = (int) date('G');
    if ($hour < 12)      $greeting = 'Good morning';
    elseif ($hour < 18)  $greeting = 'Good afternoon';
    else                 $greeting = 'Good evening';

    $greetingText = $greeting . ', ' . ($userName ?? 'User');
  ?>

  <main class="home-shell">
    <!-- Hero -->
    <section class="home-hero" aria-label="Dashboard overview">
      <p class="home-hero-greeting"><?php echo htmlspecialchars($greetingText); ?></p>
      <h2 class="home-hero-title"><?php echo $isCustomer ? htmlspecialchars($userName) : 'Calloway Pharmacy Dashboard'; ?></h2>
      <p class="home-hero-subtitle">
        <?php echo $isCustomer
          ? 'Browse medicines, place orders, and manage your loyalty rewards.'
          : 'Everything you need to run your pharmacy — sales, stock, monitoring, and admin.'; ?>
      </p>
    </section>

    <?php if (!$isCustomer): ?>
    <!-- Operations -->
    <section class="home-section">
      <h3 class="home-section-label">Operations</h3>
      <div class="home-card-grid">
        <a href="pos.php" class="home-card" style="--i:0">
          <div class="home-card-icon" aria-hidden="true">💊</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Point of Sale</h2>
            <p class="home-card-desc">Process sales, scan barcodes, and manage daily transactions.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <?php if (!$isCashier): ?>
        <a href="inventory_management.php" class="home-card" style="--i:1">
          <div class="home-card-icon" aria-hidden="true">📦</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Inventory</h2>
            <p class="home-card-desc">Manage products, stock levels, and track inventory movements.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <?php endif; ?>
        <a href="medicine-locator.php" class="home-card" style="--i:2">
          <div class="home-card-icon" aria-hidden="true">🔍</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Medicine Locator</h2>
            <p class="home-card-desc">Find medicines quickly with the interactive store map.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <?php if (!$isCashier): ?>
        <a href="expiry-monitoring.php" class="home-card" style="--i:3">
          <div class="home-card-icon" aria-hidden="true">📅</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Expiry Monitoring</h2>
            <p class="home-card-desc">Track expiration dates to reduce waste and ensure safety.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Customer-facing -->
    <section class="home-section">
      <h3 class="home-section-label"><?php echo $isCustomer ? 'Your Services' : 'Customer Services'; ?></h3>
      <div class="home-card-grid">
        <a href="onlineordering.php" class="home-card" style="--i:4">
          <div class="home-card-icon" aria-hidden="true">🛒</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Online Ordering</h2>
            <p class="home-card-desc"><?php echo $isCustomer ? 'Browse and order medicines for store pickup.' : 'Online order management and delivery tracking.'; ?></p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <a href="order_status.php" class="home-card" style="--i:5">
          <div class="home-card-icon" aria-hidden="true">📋</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Order Status</h2>
            <p class="home-card-desc"><?php echo $isCustomer ? 'Track your orders and view delivery status.' : 'View and manage online order statuses.'; ?></p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <a href="loyalty_qr.php" class="home-card" style="--i:6">
          <div class="home-card-icon" aria-hidden="true">🎁</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Loyalty & QR</h2>
            <p class="home-card-desc"><?php echo $isCustomer ? 'View loyalty points and scan QR codes for rewards.' : 'Manage loyalty programs and QR-code rewards.'; ?></p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
      </div>
    </section>

    <?php if (!$isCustomer && !$isCashier): ?>
    <!-- Administration -->
    <section class="home-section">
      <h3 class="home-section-label">Administration</h3>
      <div class="home-card-grid">
        <a href="reports.php" class="home-card" style="--i:6">
          <div class="home-card-icon" aria-hidden="true">📊</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Reports & Analytics</h2>
            <p class="home-card-desc">Sales reports, inventory analytics, and financial summaries.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <a href="employee-management.php" class="home-card" style="--i:7">
          <div class="home-card-icon" aria-hidden="true">👥</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Employees</h2>
            <p class="home-card-desc">Schedules, performance tracking, and payroll information.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <a href="user_management.php" class="home-card" style="--i:8">
          <div class="home-card-icon" aria-hidden="true">🔐</div>
          <div class="home-card-body">
            <h2 class="home-card-title">Users & Access</h2>
            <p class="home-card-desc">User accounts, roles, permissions, and access control.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        <a href="settings_enhanced.php" class="home-card" style="--i:9">
          <div class="home-card-icon" aria-hidden="true">⚙️</div>
          <div class="home-card-body">
            <h2 class="home-card-title">System Settings</h2>
            <p class="home-card-desc">Store profile, tax rates, backups, and preferences.</p>
          </div>
          <svg class="home-card-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
      </div>
    </section>
    <?php endif; ?>

    <div class="home-actions">
      <a href="logout.php" class="login-btn home-logout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        Logout
      </a>
    </div>
  </main>

  <footer>
    <div class="footer-content">
      <div class="footer-copyright">
        &copy; 2026 Calloway Pharmacy. All rights reserved.
      </div>
    </div>
  </footer>

  <?php include 'pills-background.php'; ?>

  <script src="theme.js"></script>
</body>
</html>
