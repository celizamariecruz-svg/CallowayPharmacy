<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
$_headerUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$_headerIsCustomer = (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'customer');
$_headerIsGuest = !isset($_SESSION['user_id']);
$_headerIsCashier = (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'cashier');
$_headerIsAdmin = (isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], ['admin', 'super_admin', 'manager']));
$_headerRoleName = $_SESSION['role_name'] ?? 'Guest';
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

  <?php if (!$_headerIsGuest): ?>
  <div class="sidebar-user">
    <div class="sidebar-user-avatar"><i class="fas fa-user-circle"></i></div>
    <div class="sidebar-user-info">
      <span class="sidebar-user-name"><?php echo htmlspecialchars($_headerUser); ?></span>
      <span class="sidebar-user-role"><?php echo htmlspecialchars($_headerRoleName); ?></span>
    </div>
  </div>
  <?php else: ?>
  <div class="sidebar-user">
    <div class="sidebar-user-avatar"><i class="fas fa-user-circle"></i></div>
    <div class="sidebar-user-info">
      <span class="sidebar-user-name">Guest</span>
      <span class="sidebar-user-role"><a href="login.php" style="color:var(--accent,#4FC3F7);text-decoration:none;">Login / Register</a></span>
    </div>
  </div>
  <?php endif; ?>

  <div class="sidebar-nav">
    <?php if ($_headerIsGuest): ?>
    <!-- Guest: minimal nav -->
    <div class="sidebar-section-label">Browse</div>
    <a href="onlineordering.php" class="sidebar-link <?php echo $current_page === 'onlineordering.php' ? 'active' : ''; ?>">
      <i class="fas fa-cart-shopping"></i><span>Online Ordering</span>
    </a>
    <a href="medicine-locator.php" class="sidebar-link <?php echo $current_page === 'medicine-locator.php' ? 'active' : ''; ?>">
      <i class="fas fa-search-location"></i><span>Medicine Locator</span>
    </a>
    <div class="sidebar-section-label">Account</div>
    <a href="login.php" class="sidebar-link">
      <i class="fas fa-sign-in-alt"></i><span>Login</span>
    </a>

    <?php elseif ($_headerIsCustomer): ?>
    <!-- Customer nav -->
    <div class="sidebar-section-label">Your Services</div>
    <a href="index.php" class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
      <i class="fas fa-home"></i><span>Home</span>
    </a>
    <a href="onlineordering.php" class="sidebar-link <?php echo $current_page === 'onlineordering.php' ? 'active' : ''; ?>">
      <i class="fas fa-cart-shopping"></i><span>Online Ordering</span>
    </a>
    <a href="order_status.php" class="sidebar-link <?php echo $current_page === 'order_status.php' ? 'active' : ''; ?>">
      <i class="fas fa-receipt"></i><span>Order Status</span>
    </a>
    <a href="loyalty_qr.php" class="sidebar-link <?php echo $current_page === 'loyalty_qr.php' ? 'active' : ''; ?>">
      <i class="fas fa-gift"></i><span>Loyalty & QR</span>
    </a>

    <?php elseif ($_headerIsCashier): ?>
    <!-- Cashier: limited operations -->
    <div class="sidebar-section-label">Operations</div>
    <a href="index.php" class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
      <i class="fas fa-home"></i><span>Home</span>
    </a>
    <a href="pos.php" class="sidebar-link <?php echo $current_page === 'pos.php' ? 'active' : ''; ?>">
      <i class="fas fa-cash-register"></i><span>Point of Sale</span>
    </a>
    <a href="medicine-locator.php" class="sidebar-link <?php echo $current_page === 'medicine-locator.php' ? 'active' : ''; ?>">
      <i class="fas fa-search-location"></i><span>Medicine Locator</span>
    </a>
    <div class="sidebar-section-label">Customer Services</div>
    <a href="onlineordering.php" class="sidebar-link <?php echo $current_page === 'onlineordering.php' ? 'active' : ''; ?>">
      <i class="fas fa-cart-shopping"></i><span>Online Ordering</span>
    </a>

    <?php else: ?>
    <!-- Admin / Manager: full nav (no supplier_management) -->
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
    <a href="onlineordering.php" class="sidebar-link <?php echo $current_page === 'onlineordering.php' ? 'active' : ''; ?>">
      <i class="fas fa-cart-shopping"></i><span>Online Ordering</span>
    </a>
    <a href="loyalty_qr.php" class="sidebar-link <?php echo $current_page === 'loyalty_qr.php' ? 'active' : ''; ?>">
      <i class="fas fa-gift"></i><span>Loyalty & QR</span>
    </a>

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
    <?php if ($_headerIsGuest): ?>
    <a href="login.php" class="sidebar-link sidebar-logout">
      <i class="fas fa-sign-in-alt"></i><span>Login</span>
    </a>
    <?php else: ?>
    <a href="logout.php" class="sidebar-link sidebar-logout">
      <i class="fas fa-right-from-bracket"></i><span>Logout</span>
    </a>
    <?php endif; ?>
  </div>
</nav>

<!-- Top Header Bar -->
<header class="topbar" id="topbar">
  <div class="topbar-left">
    <button class="topbar-hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Open menu">
      <i class="fas fa-bars"></i>
    </button>
    <a href="index.php" class="topbar-brand">Calloway Pharmacy</a>
  </div>

  <div class="topbar-center" id="topbarCenter">
    <!-- Content injected by specific pages (Search bar etc) -->
  </div>

  <div class="topbar-right">
    <?php if (!$_headerIsGuest && !$_headerIsCustomer): ?>
    <!-- Notification bell for staff -->
    <button class="topbar-notification-btn" id="notifBellBtn" onclick="toggleNotifPanel()" aria-label="Notifications">
      <i class="fas fa-bell"></i>
      <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
    </button>
    <?php endif; ?>

    <div id="topbarRightExtras">
       <!-- Cart/Wishlist injected here by pages -->
    </div>

    <button class="topbar-theme-toggle" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Toggle theme">
      <i class="fas fa-moon" id="themeIcon"></i>
    </button>
  </div>
</header>
<style>
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 1rem;
    height: 60px;
    background: var(--header-bg, #1a3c34);
    color: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
    z-index: 1001;
}
.topbar-left, .topbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}
#topbarRightExtras {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: nowrap;
}
.topbar-center {
    flex: 1;
    display: flex;
    justify-content: center;
    padding: 0 1rem;
}
.topbar-hamburger {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
}
.topbar-brand {
    color: white;
    text-decoration: none;
    font-weight: bold;
    font-size: 1.2rem;
    white-space: nowrap;
}
.topbar-notification-btn, .topbar-theme-toggle {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0.5rem;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}
.notif-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: red;
    color: white;
    border-radius: 50%;
    padding: 0.1rem 0.4rem;
    font-size: 0.7rem;
    font-weight: bold;
}
@media (max-width: 768px) {
    .topbar-brand { display: none; }
  .topbar-right { gap: 0.4rem; }
  #topbarRightExtras { gap: 0.35rem; }
}
</style>

<?php if (!$_headerIsGuest && !$_headerIsCustomer): ?>
<link rel="stylesheet" href="notification-tray.css">
<script src="notification-tray.js" defer></script>
<?php endif; ?>