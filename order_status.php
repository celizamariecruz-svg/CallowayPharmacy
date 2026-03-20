<?php
/**
 * Order Status Page
 * Customers can view and track their online orders
 */
require_once 'Security.php';
Security::initSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$isCustomer = (($_SESSION['role_name'] ?? '') === 'customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
(function() {
    const theme = localStorage.getItem('calloway_theme') || 'light';
  document.documentElement.setAttribute('data-theme', theme);
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Order Status - Calloway Pharmacy</title>
<link rel="stylesheet" href="design-system.css">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="home.css">
<link rel="stylesheet" href="responsive.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .os-container {
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.25rem 1.5rem 2rem;
    }
    .os-page-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }
    .os-page-header h1 {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-color);
        margin: 0;
    }
    .os-page-header i {
        font-size: 1.3rem;
        color: var(--primary-color);
    }
    .os-back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.85rem;
        color: var(--primary-color);
        text-decoration: none;
        margin-bottom: 1rem;
        font-weight: 600;
        transition: opacity 0.2s;
    }
    .os-back-link:hover { opacity: 0.8; }

    /* Filter tabs */
    .os-filter-bar {
        display: flex;
        gap: 0.4rem;
        margin-bottom: 1.2rem;
        overflow-x: auto;
        padding-bottom: 0.3rem;
    }
    .os-filter-btn {
        padding: 0.4rem 1rem;
        border-radius: 999px;
        border: 1.5px solid var(--table-border);
        background: var(--card-bg);
        color: var(--text-light, #94a3b8);
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .os-filter-btn:hover {
        background: var(--hover-bg);
        color: var(--primary-color);
        border-color: rgba(var(--primary-rgb), 0.28);
        transform: none;
        box-shadow: none;
    }
    .os-filter-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    /* Orders list */
    .os-orders-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 1rem;
    }

    /* Order card */
    .os-order-card {
        background: var(--card-bg, #1e293b);
        border: 1px solid var(--table-border);
        border-radius: 14px;
        padding: 1.2rem;
        transition: transform 0.15s, box-shadow 0.15s;
        box-shadow: var(--shadow-sm);
    }
    .os-order-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    .os-order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.65rem;
    }
    .os-order-ref {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--primary-color);
        font-family: 'Courier New', monospace;
    }

    .os-status-badge {
        padding: 0.2rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .os-status-badge.pending { background: #fef3c7; color: #92400e; }
    .os-status-badge.confirmed { background: #dbeafe; color: #1e40af; }
    .os-status-badge.preparing { background: #e0e7ff; color: #3730a3; }
    .os-status-badge.ready { background: #d1fae5; color: #065f46; }
    .os-status-badge.completed { background: #dcfce7; color: #166534; }
    .os-status-badge.cancelled { background: #fee2e2; color: #991b1b; }

    /* Tracker */
    .os-tracker {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 0.75rem 0 0.5rem;
        padding: 0 0.5rem;
        position: relative;
    }
    .os-tracker::before {
        content: '';
        position: absolute;
        top: 14px;
        left: 28px;
        right: 28px;
        height: 3px;
        background: var(--table-border);
        z-index: 0;
    }
    .os-tracker-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        z-index: 1;
        gap: 0.3rem;
    }
    .os-tracker-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--hover-bg);
        box-shadow: inset 0 0 0 1px var(--table-border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        color: var(--text-light, #94a3b8);
        transition: all 0.3s;
    }
    .os-tracker-dot.active {
        background: var(--primary-color);
        color: white;
        box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.3);
    }
    .os-tracker-dot.done {
        background: #22c55e;
        color: white;
    }
    .os-tracker-dot.cancelled-dot {
        background: #ef4444;
        color: white;
    }
    .os-tracker-label {
        font-size: 0.62rem;
        font-weight: 600;
        color: var(--text-light, #94a3b8);
        text-align: center;
        max-width: 55px;
    }
    .os-tracker-label.active { color: var(--primary-color); }
    .os-tracker-label.done { color: #22c55e; }

    /* Items */
    .os-items {
        font-size: 0.85rem;
        color: var(--text-light, #94a3b8);
        margin-bottom: 0.5rem;
        line-height: 1.5;
    }
    .os-item-line {
        display: flex;
        justify-content: space-between;
        padding: 0.15rem 0;
    }

    /* Footer */
    .os-order-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 0.6rem;
        border-top: 1px solid var(--table-border);
        margin-top: 0.4rem;
    }
    .os-order-total {
        font-weight: 800;
        font-size: 1.05rem;
        color: var(--text-color);
    }
    .os-order-date {
        font-size: 0.78rem;
        color: var(--text-light, #94a3b8);
    }

    /* Empty state */
    .os-empty {
        grid-column: 1 / -1;
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-light, #94a3b8);
    }
    .os-empty i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    .os-empty p {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.3rem;
        color: var(--text-color);
    }
    .os-empty span {
        font-size: 0.85rem;
    }
    .os-empty .os-shop-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 1.2rem;
        padding: 0.6rem 1.5rem;
        background: var(--primary-blue, #2563eb);
        color: white;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: opacity 0.2s;
    }
    .os-empty .os-shop-btn:hover { opacity: 0.85; }

    /* Loading spinner */
    .os-loading {
        grid-column: 1 / -1;
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-light, #94a3b8);
    }

    /* Summary bar */
    .os-summary {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.2rem;
        flex-wrap: wrap;
    }
    .os-summary-chip {
        padding: 0.35rem 0.85rem;
        border-radius: 10px;
        font-size: 0.78rem;
        font-weight: 700;
        background: var(--card-bg);
        border: 1px solid var(--table-border);
        color: var(--text-light, #94a3b8);
    }
    .os-summary-chip strong {
        color: var(--text-color);
    }

    /* Detail Modal */
    .os-modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .os-modal-overlay.active { display: flex; }
    .os-modal {
        background: var(--card-bg, white);
        border-radius: 16px;
        max-width: 550px;
        width: 100%;
        max-height: 85vh;
        overflow-y: auto;
        padding: 1.5rem;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        position: relative;
    }
    .os-modal-close {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: var(--hover-bg);
        color: var(--text-light);
        font-size: 1.1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .os-modal h2 {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text-color);
        margin: 0 0 1rem;
    }
    .os-detail-section {
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--table-border);
    }
    .os-detail-section:last-child { border-bottom: none; }
    .os-detail-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-light, #94a3b8);
        margin-bottom: 0.3rem;
    }
    .os-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.25rem 0;
        font-size: 0.88rem;
        color: var(--text-color);
    }
    .os-detail-row span:first-child { color: var(--text-light); }
    .os-detail-row span:last-child { font-weight: 600; }
    .os-points-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.7rem;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 700;
    }
    .os-points-earned { background: #dcfce7; color: #166534; }
    .os-points-redeemed { background: #fef3c7; color: #92400e; }
    .os-detail-items {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .os-detail-items li {
        display: flex;
        justify-content: space-between;
        padding: 0.35rem 0;
        font-size: 0.85rem;
        color: var(--text-color);
        border-bottom: 1px dashed var(--table-border);
    }
    .os-detail-items li:last-child { border-bottom: none; }
    .os-admin-badge {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        background: #ede9fe;
        color: #6d28d9;
        margin-left: 0.5rem;
    }

    @media (max-width: 600px) {
        .os-container { padding: 1rem 0.75rem 2rem; }
        .os-orders-list { grid-template-columns: 1fr; }
        .os-order-card { padding: 1rem; }
        .os-tracker { padding: 0; }
        .os-tracker::before { left: 18px; right: 18px; }
        .os-tracker-dot { width: 24px; height: 24px; font-size: 0.55rem; }
        .os-tracker-label { font-size: 0.55rem; max-width: 45px; }
        .os-modal { padding: 1rem; }
    }


</style>
</head>
<body>
  <?php include 'header-component.php'; ?>

  <main class="os-container">
    <a href="index.php" class="os-back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
    
    <div class="os-page-header">
        <i class="fas fa-receipt"></i>
        <h1>Order Status</h1>
    </div>

    <!-- Summary chips -->
    <div class="os-summary" id="osSummary" style="display:none;"></div>

    <!-- Filter tabs -->
    <div class="os-filter-bar">
        <button class="os-filter-btn active" data-filter="all">All Orders</button>
        <button class="os-filter-btn" data-filter="active">Active</button>
        <button class="os-filter-btn" data-filter="pending">Pending</button>
        <button class="os-filter-btn" data-filter="confirmed_preparing">Confirmed &amp; Preparing</button>
        <button class="os-filter-btn" data-filter="ready">Ready</button>
        <button class="os-filter-btn" data-filter="completed">Completed</button>
        <button class="os-filter-btn" data-filter="cancelled">Cancelled</button>
    </div>

    <!-- Orders list -->
    <div class="os-orders-list" id="osOrdersList">
        <div class="os-loading">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;opacity:0.3;"></i>
            <p style="margin-top:0.8rem;">Loading your orders...</p>
        </div>
    </div>
  </main>

  <!-- Detail Modal -->
  <div class="os-modal-overlay" id="osDetailModal" onclick="if(event.target===this)closeDetailModal()">
    <div class="os-modal">
        <button class="os-modal-close" onclick="closeDetailModal()">&times;</button>
        <div id="osDetailContent"></div>
    </div>
  </div>

  <?php include 'pills-background.php'; ?>
  <script src="theme.js"></script>
  <script>
    let allOrders = [];
    let currentFilter = 'all';
    let isStaff = false;

    // Escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Load orders from API
    async function loadOrders() {
        try {
            const res = await fetch('api_orders.php?action=my_orders', { credentials: 'same-origin' });
            const data = await res.json();

            if (!data.success) {
                showEmpty('Unable to load orders', data.message || 'Please try again later', 'fa-exclamation-circle');
                return;
            }

            allOrders = data.orders || [];
            isStaff = data.is_staff || false;

            if (allOrders.length === 0) {
                const emptyMsg = isStaff ? 'No online orders have been placed yet.' : 'Your orders will appear here once you place one';
                showEmpty('No orders yet', emptyMsg, 'fa-shopping-bag', !isStaff);
                return;
            }

            renderSummary();
            renderOrders();

        } catch (err) {
            console.error('Error loading orders:', err);
            showEmpty('Connection error', 'Please check your internet and try again', 'fa-wifi');
        }
    }

    function showEmpty(title, subtitle, icon, showShopBtn) {
        const list = document.getElementById('osOrdersList');
        list.innerHTML = `
            <div class="os-empty">
                <i class="fas ${icon}"></i>
                <p>${escapeHtml(title)}</p>
                <span>${escapeHtml(subtitle)}</span>
                ${showShopBtn ? '<a href="onlineordering.php" class="os-shop-btn"><i class="fas fa-cart-shopping"></i> Browse Medicines</a>' : ''}
            </div>`;
    }

    function renderSummary() {
        const summary = document.getElementById('osSummary');
        const counts = { total: allOrders.length, active: 0, completed: 0, cancelled: 0 };
        allOrders.forEach(o => {
            const s = (o.status || '').toLowerCase();
            if (['pending', 'confirmed', 'preparing', 'ready'].includes(s)) counts.active++;
            else if (s === 'completed') counts.completed++;
            else if (s === 'cancelled') counts.cancelled++;
        });

        summary.style.display = 'flex';
        summary.innerHTML = `
            <div class="os-summary-chip"><strong>${counts.total}</strong> Total</div>
            <div class="os-summary-chip" style="color:#2563eb;"><strong>${counts.active}</strong> Active</div>
            <div class="os-summary-chip" style="color:#22c55e;"><strong>${counts.completed}</strong> Completed</div>
            ${counts.cancelled > 0 ? `<div class="os-summary-chip" style="color:#ef4444;"><strong>${counts.cancelled}</strong> Cancelled</div>` : ''}
        `;
    }

    function renderOrders() {
        const list = document.getElementById('osOrdersList');
        let filtered = allOrders;

        if (currentFilter === 'active') {
            filtered = allOrders.filter(o => ['pending', 'confirmed', 'preparing', 'ready'].includes((o.status || '').toLowerCase()));
        } else if (currentFilter === 'confirmed_preparing') {
            filtered = allOrders.filter(o => ['confirmed', 'preparing'].includes((o.status || '').toLowerCase()));
        } else if (currentFilter !== 'all') {
            filtered = allOrders.filter(o => (o.status || '').toLowerCase() === currentFilter);
        }

        if (filtered.length === 0) {
            const emptyLabel = currentFilter === 'all' ? 'orders' : `${currentFilter} orders`;
            list.innerHTML = `
                <div class="os-empty">
                    <i class="fas fa-filter"></i>
                    <p>No ${emptyLabel} found</p>
                    <span>Try a different filter</span>
                </div>`;
            return;
        }

        list.innerHTML = filtered.map(order => renderOrderCard(order)).join('');
    }

    function renderOrderCard(order) {
        const statusClass = (order.status || 'pending').toLowerCase();
        const statusSteps = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed'];
        const currentIndex = statusSteps.findIndex(s => s.toLowerCase() === statusClass);
        const isCancelled = statusClass === 'cancelled';

        // Items
        let itemsHtml = '';
        if (order.items && order.items.length > 0) {
            const maxShow = 3;
            const shown = order.items.slice(0, maxShow);
            itemsHtml = shown.map(item =>
                `<div class="os-item-line"><span>${escapeHtml(item.product_name)} x${item.quantity}</span><span>₱${parseFloat(item.subtotal).toFixed(2)}</span></div>`
            ).join('');
            if (order.items.length > maxShow) {
                itemsHtml += `<div class="os-item-line" style="color:var(--primary-blue);font-weight:600;"><span>+${order.items.length - maxShow} more item(s)</span><span></span></div>`;
            }
        } else {
            itemsHtml = `<div class="os-item-line"><span>${order.item_count || 0} item(s)</span><span></span></div>`;
        }

        // Tracker
        let trackerHtml = '';
        if (isCancelled) {
            trackerHtml = `
                <div class="os-tracker" style="justify-content:center;">
                    <div class="os-tracker-step">
                        <div class="os-tracker-dot cancelled-dot"><i class="fas fa-times"></i></div>
                        <span class="os-tracker-label" style="color:#ef4444;">Cancelled</span>
                    </div>
                </div>`;
        } else {
            trackerHtml = '<div class="os-tracker">';
            statusSteps.forEach((step, idx) => {
                let dotClass = 'os-tracker-dot';
                let labelClass = 'os-tracker-label';
                let icon = '';
                if (idx < currentIndex) {
                    dotClass += ' done';
                    labelClass += ' done';
                    icon = '<i class="fas fa-check"></i>';
                } else if (idx === currentIndex) {
                    dotClass += ' active';
                    labelClass += ' active';
                    const icons = ['fa-clock', 'fa-thumbs-up', 'fa-mortar-pestle', 'fa-box-open', 'fa-check-double'];
                    icon = '<i class="fas ' + icons[idx] + '"></i>';
                } else {
                    icon = '<i class="fas fa-circle" style="font-size:0.4rem;"></i>';
                }
                trackerHtml += `
                    <div class="os-tracker-step">
                        <div class="${dotClass}">${icon}</div>
                        <span class="${labelClass}">${step}</span>
                    </div>`;
            });
            trackerHtml += '</div>';
        }

        // Date
        const orderDate = order.created_at ? new Date(order.created_at) : null;
        const dateStr = orderDate ? orderDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        const timeStr = orderDate ? orderDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';

        return `
            <div class="os-order-card" onclick="openDetailModal(${order.order_id})" style="cursor:pointer;">
                <div class="os-order-header">
                    <span class="os-order-ref">${escapeHtml(order.order_ref)}</span>
                    <span class="os-status-badge ${statusClass}">${escapeHtml(order.status)}</span>
                </div>
                ${trackerHtml}
                <div class="os-items">${itemsHtml}</div>
                <div class="os-order-footer">
                    <span class="os-order-total">₱${parseFloat(order.total_amount).toFixed(2)}</span>
                    <span class="os-order-date"><i class="far fa-calendar-alt"></i> ${dateStr} ${timeStr}</span>
                </div>
                ${order.points_earned > 0 ? `<div style="margin-top:0.4rem;"><span class="os-points-badge os-points-earned"><i class="fas fa-star"></i> +${parseFloat(order.points_earned).toFixed(0)} pts earned</span></div>` : ''}
                ${order.points_redeemed > 0 ? `<div style="margin-top:0.3rem;"><span class="os-points-badge os-points-redeemed"><i class="fas fa-gift"></i> ${parseFloat(order.points_redeemed).toFixed(0)} pts used</span></div>` : ''}
            </div>`;
    }

    // Detail Modal
    function openDetailModal(orderId) {
        const order = allOrders.find(o => o.order_id == orderId);
        if (!order) return;

        const statusClass = (order.status || 'pending').toLowerCase();
        const orderDate = order.created_at ? new Date(order.created_at) : null;
        const dateStr = orderDate ? orderDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
        const timeStr = orderDate ? orderDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
        const updatedDate = order.updated_at ? new Date(order.updated_at) : null;
        const updatedStr = updatedDate ? updatedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + updatedDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A';

        let html = `<h2><i class="fas fa-receipt" style="color:var(--primary-color);margin-right:0.4rem;"></i> ${escapeHtml(order.order_ref)}<span class="os-status-badge ${statusClass}" style="margin-left:0.5rem;font-size:0.7rem;">${escapeHtml(order.status)}</span></h2>`;

        // Order info
        html += `<div class="os-detail-section">
            <div class="os-detail-label"><i class="fas fa-info-circle"></i> Order Information</div>
            <div class="os-detail-row"><span>Order Date</span><span>${dateStr}</span></div>
            <div class="os-detail-row"><span>Order Time</span><span>${timeStr}</span></div>
            <div class="os-detail-row"><span>Last Updated</span><span>${updatedStr}</span></div>
            <div class="os-detail-row"><span>Payment Method</span><span>${escapeHtml(order.payment_method || 'Cash on Pickup')}</span></div>
        </div>`;

        // Admin extra details
        if (isStaff) {
            html += `<div class="os-detail-section">
                <div class="os-detail-label"><i class="fas fa-user-shield"></i> Admin Details <span class="os-admin-badge">STAFF VIEW</span></div>
                <div class="os-detail-row"><span>Customer Name</span><span>${escapeHtml(order.customer_full_name || order.customer_name || 'N/A')}</span></div>
                <div class="os-detail-row"><span>Customer Email</span><span>${escapeHtml(order.customer_email || 'N/A')}</span></div>
                <div class="os-detail-row"><span>Customer ID</span><span>#${order.customer_id || 'N/A'}</span></div>
                <div class="os-detail-row"><span>Order ID</span><span>#${order.order_id}</span></div>
                ${order.notes ? `<div class="os-detail-row"><span>Notes</span><span>${escapeHtml(order.notes)}</span></div>` : ''}
            </div>`;
        }

        // Items section
        html += `<div class="os-detail-section">
            <div class="os-detail-label"><i class="fas fa-pills"></i> Items (${order.items ? order.items.length : 0})</div>
            <ul class="os-detail-items">`;
        if (order.items && order.items.length > 0) {
            order.items.forEach(item => {
                html += `<li><span>${escapeHtml(item.product_name)} × ${item.quantity}</span><span>₱${parseFloat(item.subtotal).toFixed(2)}</span></li>`;
            });
        } else {
            html += `<li><span>${order.item_count || 0} item(s)</span><span></span></li>`;
        }
        html += `</ul></div>`;

        // Points & Totals
        html += `<div class="os-detail-section">
            <div class="os-detail-label"><i class="fas fa-calculator"></i> Summary</div>
            <div class="os-detail-row"><span>Total Amount</span><span style="font-size:1.1rem;font-weight:800;">₱${parseFloat(order.total_amount).toFixed(2)}</span></div>`;
        
        if (order.points_redeemed > 0) {
            html += `<div class="os-detail-row"><span>Points Redeemed</span><span class="os-points-badge os-points-redeemed"><i class="fas fa-gift"></i> ${parseFloat(order.points_redeemed).toFixed(0)} points (₱${parseFloat(order.points_redeemed).toFixed(2)} discount)</span></div>`;
        }
        if (order.points_earned > 0) {
            html += `<div class="os-detail-row"><span>Points Earned</span><span class="os-points-badge os-points-earned"><i class="fas fa-star"></i> +${parseFloat(order.points_earned).toFixed(0)} points</span></div>`;
        }
        if (statusClass === 'completed' && order.points_earned == 0 && !isStaff) {
            html += `<div style="font-size:0.8rem;color:var(--text-light);margin-top:0.3rem;"><i class="fas fa-info-circle"></i> Points are awarded upon order pickup verification.</div>`;
        }
        html += `</div>`;

        document.getElementById('osDetailContent').innerHTML = html;
        document.getElementById('osDetailModal').classList.add('active');
    }

    function closeDetailModal() {
        document.getElementById('osDetailModal').classList.remove('active');
    }

    // Close on Escape key
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetailModal(); });

    // Filter buttons
    document.querySelectorAll('.os-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.os-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            renderOrders();
        });
    });

    // Initial load
    loadOrders();

    // Auto refresh every 30s
    setInterval(loadOrders, 30000);
  </script>
</body>
</html>
