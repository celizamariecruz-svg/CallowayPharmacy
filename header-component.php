<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
$_headerUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$_headerIsCustomer = (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'customer');
?>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-brand">
      <i class="fas fa-prescription-bottle-medical"></i>
      <span>Calloway Pharmacy</span>
    </div>
    <button class="sidebar-close" onclick="closeSidebar()" aria-label="Close menu">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <div class="sidebar-user">
    <div class="sidebar-user-avatar"><i class="fas fa-user-circle"></i></div>
    <div class="sidebar-user-info">
      <span class="sidebar-user-name"><?php echo htmlspecialchars($_headerUser); ?></span>
      <span class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'Staff'); ?></span>
    </div>
  </div>

  <div class="sidebar-nav">
    <?php if (!$_headerIsCustomer): ?>
    <div class="sidebar-section-label">Operations</div>
    <a href="index.php" class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
      <i class="fas fa-home"></i><span>Home</span>
    </a>
    <a href="pos.php" class="sidebar-link <?php echo $current_page === 'pos.php' ? 'active' : ''; ?>">
      <i class="fas fa-cash-register"></i><span>Point of Sale</span>
    </a>
    <a href="inventory_management.php" class="sidebar-link <?php echo $current_page === 'inventory_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-boxes-stacked"></i><span>Inventory</span>
    </a>
    <a href="medicine-locator.php" class="sidebar-link <?php echo $current_page === 'medicine-locator.php' ? 'active' : ''; ?>">
      <i class="fas fa-search-location"></i><span>Medicine Locator</span>
    </a>
    <a href="expiry-monitoring.php" class="sidebar-link <?php echo $current_page === 'expiry-monitoring.php' ? 'active' : ''; ?>">
      <i class="fas fa-calendar-xmark"></i><span>Expiry Monitoring</span>
    </a>

    <div class="sidebar-section-label">Customer Services</div>
    <?php endif; ?>

    <?php if ($_headerIsCustomer): ?>
    <div class="sidebar-section-label">Your Services</div>
    <a href="index.php" class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
      <i class="fas fa-home"></i><span>Home</span>
    </a>
    <?php endif; ?>

    <a href="onlineordering.php" class="sidebar-link <?php echo $current_page === 'onlineordering.php' ? 'active' : ''; ?>">
      <i class="fas fa-cart-shopping"></i><span>Online Ordering</span>
    </a>
    <a href="order_status.php" class="sidebar-link <?php echo $current_page === 'order_status.php' ? 'active' : ''; ?>">
      <i class="fas fa-receipt"></i><span>Order Status</span>
    </a>
    <a href="loyalty_qr.php" class="sidebar-link <?php echo $current_page === 'loyalty_qr.php' ? 'active' : ''; ?>">
      <i class="fas fa-gift"></i><span>Loyalty & QR</span>
    </a>

    <?php if (!$_headerIsCustomer): ?>
    <div class="sidebar-section-label">Administration</div>
    <a href="reports.php" class="sidebar-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
      <i class="fas fa-chart-bar"></i><span>Reports & Analytics</span>
    </a>
    <a href="employee-management.php" class="sidebar-link <?php echo $current_page === 'employee-management.php' ? 'active' : ''; ?>">
      <i class="fas fa-users"></i><span>Employees</span>
    </a>
    <a href="user_management.php" class="sidebar-link <?php echo $current_page === 'user_management.php' ? 'active' : ''; ?>">
      <i class="fas fa-user-shield"></i><span>Users & Access</span>
    </a>
    <a href="settings_enhanced.php" class="sidebar-link <?php echo ($current_page === 'settings.php' || $current_page === 'settings_enhanced.php') ? 'active' : ''; ?>">
      <i class="fas fa-gear"></i><span>System Settings</span>
    </a>
    <a href="dashboard.php" class="sidebar-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
      <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
    </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-link sidebar-logout">
      <i class="fas fa-right-from-bracket"></i><span>Logout</span>
    </a>
  </div>
</nav>

<!-- Top Header Bar -->
<header class="topbar" id="topbar">
  <button class="topbar-hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Open menu">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <a href="index.php" class="topbar-brand" aria-label="Calloway Pharmacy Integrated Management System">
    <img src="logo-removebg-preview.png" alt="Calloway Pharmacy" class="topbar-logo-img" width="36" height="36">
    <span class="topbar-brand-stack">
      <span class="topbar-brand-main">Calloway Pharmacy</span>
      <span class="topbar-brand-subtitle">Integrated Management System</span>
    </span>
  </a>

  <div class="topbar-center" id="topbarCenter"></div>

  <div class="topbar-right" id="topbarRight">
    <button class="topbar-notification-btn" id="notifBellBtn" onclick="toggleNotifPanel()" aria-label="Notifications">
      <i class="fas fa-bell"></i>
      <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
    </button>

    <div id="topbarRightExtras"></div>

    <button class="topbar-theme-toggle" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Toggle theme">
      <i class="fas fa-moon" id="themeIcon"></i>
    </button>
  </div>
</header>

<style>
  .topbar-center {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.6rem;
  }

  .topbar-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.45rem;
  }

  #topbarRightExtras {
    display: flex;
    align-items: center;
    gap: 0.45rem;
  }

  .topbar-notification-btn {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.2s;
  }

  .topbar-notification-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.05);
  }

  .notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: #fff;
    border-radius: 999px;
    min-width: 16px;
    height: 16px;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
    padding: 0 3px;
  }
</style>

<script>
  if (typeof window.toggleNotifPanel !== 'function') {
    window.toggleNotifPanel = function () {};
  }
</script>