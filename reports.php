<?php
/**
 * Reports & Analytics Dashboard
 * Sales reports, inventory analytics, financial summaries
 */

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('reports.sales') && !$auth->hasPermission('reports.inventory')) {
    die('<h1>Access Denied</h1><p>You do not have permission to access reports.</p>');
}

$currentUser = $auth->getCurrentUser();
$page_title = 'Reports & Analytics';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 8rem 1.5rem 2rem;
        }
        
        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--input-border);
            box-shadow: var(--shadow-sm);
        }
        
        .reports-header h1 {
            font-size: 2rem;
            margin: 0;
            color: var(--primary-color);
            font-weight: 800;
        }
        
        .date-range-selector {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            border: 1px solid var(--input-border);
        }
        
        [data-theme="dark"] .date-range-selector {
            background: rgba(30, 41, 59, 0.7);
        }
        
        .date-input {
            padding: 0.8rem 1rem;
            border: 1px solid var(--input-border);
            border-radius: 10px;
            font-size: 1rem;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s;
        }
        
        .date-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }
        
        .quick-range-btn {
            padding: 0.6rem 1.2rem;
            border: 1px solid var(--primary-color);
            background: transparent;
            color: var(--primary-color);
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .quick-range-btn:hover,
        .quick-range-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .metric-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--input-border);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .metric-card h3 {
            margin: 0 0 0.5rem;
            font-size: 0.9rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .metric-card .value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin: 0.5rem 0;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .report-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--input-border);
        }
        
        .report-card h2 {
            margin: 0 0 1.5rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .report-card h2 i {
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.1);
            padding: 0.5rem;
            border-radius: 8px;
        }

        .charts-grid {
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
        }

        .chart-card {
            min-height: 360px;
        }

        .chart-wrap {
            position: relative;
            height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-wrap canvas {
            max-width: 100%;
            max-height: 100%;
        }

        .kpi-subtext {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            background: var(--table-header-bg);
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--divider-color);
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--divider-color);
            color: var(--text-color);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background: var(--hover-bg);
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--input-border);
        }
        
        .btn-secondary:hover {
            background: var(--hover-bg);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .loading {
            padding: 2rem;
            text-align: center;
            font-style: italic;
            color: var(--text-light);
        }
        
        .toast {
            position: fixed;
            top: 100px;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 10000;
            border-left: 4px solid var(--primary-color);
        }
        
        .toast.success { border-color: var(--secondary-color); }
        .toast.error { border-color: var(--danger-color); }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>

    <main class="reports-container fade-in">
        <div class="reports-header">
            <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
            <div style="display: flex; gap: 0.5rem;">
                 <button class="btn btn-secondary" onclick="exportReport('all')">
                    <i class="fas fa-file-csv"></i> Export Data
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
        
        <!-- Date Range Filter -->
        <div class="date-range-selector">
            <span style="font-weight: 600; color: var(--text-color);"><i class="far fa-calendar-alt"></i> Date Range:</span>
            <button class="quick-range-btn active" onclick="setRange('today')">Today</button>
            <button class="quick-range-btn" onclick="setRange('7days')">Last 7 Days</button>
            <button class="quick-range-btn" onclick="setRange('month')">This Month</button>
            <div style="margin-left: auto; display: flex; gap: 0.5rem; align-items: center;">
                <input type="date" class="date-input" id="startDate">
                <span style="color: var(--text-light)">to</span>
                <input type="date" class="date-input" id="endDate">
                <button class="btn btn-primary" style="padding: 0.6rem 1rem;" onclick="loadReports()">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <h3>Total Revenue</h3>
                <div class="value" id="totalRevenue">₱0.00</div>
                <div class="kpi-subtext">Selected period</div>
            </div>
            <div class="metric-card">
                <h3>Revenue Growth</h3>
                <div class="value" id="revenueGrowth">0%</div>
                <div class="kpi-subtext">Vs previous period</div>
            </div>
            <div class="metric-card">
                <h3>Gross Profit</h3>
                <div class="value" id="grossProfit">₱0.00</div>
                <div class="kpi-subtext">Estimated margin</div>
            </div>
            <div class="metric-card">
                <h3>Gross Margin</h3>
                <div class="value" id="grossMargin">0%</div>
                <div class="kpi-subtext">Profitability</div>
            </div>
            <div class="metric-card">
                <h3>Total Transactions</h3>
                <div class="value" id="totalSales">0</div>
                <div class="kpi-subtext">Completed sales</div>
            </div>
            <div class="metric-card">
                <h3>Avg. Transaction</h3>
                <div class="value" id="avgTransaction">₱0.00</div>
                <div class="kpi-subtext">Per sale</div>
            </div>
            <div class="metric-card">
                <h3>Products Sold</h3>
                <div class="value" id="productsSold">0</div>
                <div class="kpi-subtext">Units moved</div>
            </div>
            <div class="metric-card">
                <h3>Expiry Risk Value</h3>
                <div class="value" id="expiryRiskValue">₱0.00</div>
                <div class="kpi-subtext">Next 30 days</div>
            </div>
            <div class="metric-card">
                <h3>Pickup Cycle</h3>
                <div class="value" id="pickupCycleTime">0m</div>
                <div class="kpi-subtext">Avg order to pickup</div>
            </div>
            <div class="metric-card">
                <h3>Rx Approval Rate</h3>
                <div class="value" id="rxApprovalRate">0%</div>
                <div class="kpi-subtext">Approved Rx orders</div>
            </div>
            <div class="metric-card">
                <h3>Repeat Customers</h3>
                <div class="value" id="repeatRate">0%</div>
                <div class="kpi-subtext">2+ orders in period</div>
            </div>
            <div class="metric-card">
                <h3>Loyalty Points</h3>
                <div class="value" id="loyaltyPoints">0</div>
                <div class="kpi-subtext">Outstanding points</div>
            </div>
        </div>

        <div class="reports-grid charts-grid">
            <div class="report-card chart-card">
                <h2><i class="fas fa-chart-area"></i> Revenue Trend</h2>
                <div class="chart-wrap">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
            <div class="report-card chart-card">
                <h2><i class="fas fa-chart-pie"></i> Payment Mix</h2>
                <div class="chart-wrap">
                    <canvas id="paymentMixChart"></canvas>
                </div>
            </div>
        </div>

        <div class="reports-grid charts-grid">
            <div class="report-card chart-card">
                <h2><i class="fas fa-layer-group"></i> Category Share</h2>
                <div class="chart-wrap">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="report-card">
                <h2><i class="fas fa-route"></i> Online Order Status</h2>
                <table id="orderStatusTable">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <h2><i class="fas fa-trophy"></i> Top Selling Products</h2>
                <table id="topProductsTable">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-balance-scale"></i> Top Products by Profit</h2>
                <table id="topProfitTable">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product</th>
                            <th>Profit</th>
                            <th>Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h2>
                <table id="lowStockTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-clock"></i> Expiring Soon</h2>
                <table id="expiringTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Expiry Date</th>
                            <th>Days Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <h2><i class="fas fa-snowflake"></i> Dead Stock (90+ days)</h2>
                <table id="deadStockTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Stock</th>
                            <th>Last Sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-tachometer-alt"></i> Slow Movers (90 days)</h2>
                <table id="slowMoversTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty Sold</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <h2><i class="fas fa-prescription"></i> Recent Rx Decisions</h2>
                <table id="rxLogTable">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Product</th>
                            <th>Pharmacist</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="report-card">
                <h2><i class="fas fa-users"></i> Top Customers</h2>
                <table id="topCustomersTable">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <div class="toast" id="toast"></div>

    <script src="theme.js"></script>
    <script src="shared-polish.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let revenueTrendChart = null;
        let paymentMixChart = null;
        let categoryChart = null;

        document.addEventListener('DOMContentLoaded', () => {
            // Set today as default filter on page load
            const today = new Date();
            const startInput = document.getElementById('startDate');
            const endInput = document.getElementById('endDate');
            
            // Set both dates to today
            startInput.valueAsDate = today;
            endInput.valueAsDate = today;
            
            // Load reports with today's filter
            loadReports();
        });

        function setRange(range) {
            const today = new Date();
            const endInput = document.getElementById('endDate');
            const startInput = document.getElementById('startDate');
            
            // UI Update
            document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
            if(typeof event !== 'undefined' && event && event.target) {
                event.target.classList.add('active');
            }
            
            endInput.valueAsDate = today;
            let start = new Date();
            
            switch(range) {
                case 'today': 
                    // Set both dates to today for today's filter
                    start = new Date(today);
                    break;
                case '7days': 
                    start.setDate(today.getDate() - 7); 
                    break;
                case 'month': 
                    start.setDate(1); 
                    break;
            }
            startInput.valueAsDate = start;
            
            loadReports();
        }
        
        async function safeFetchJson(url) {
            try {
                const res = await fetch(url);
                const text = await res.text();
                try {
                    const data = JSON.parse(text);
                    if (!res.ok) {
                        console.error('Report endpoint error:', url, data);
                    }
                    return data;
                } catch (parseErr) {
                    console.error('Non-JSON response:', url, text.slice(0, 300));
                    return { success: false, message: 'Invalid JSON response' };
                }
            } catch (err) {
                console.error('Fetch failed:', url, err);
                return { success: false, message: 'Network error' };
            }
        }

        async function loadReports() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const base = `get_reports_data.php?start=${startDate}&end=${endDate}`;

            try {
                // Fetch all data in parallel
                const [
                    metricsRes,
                    trendRes,
                    paymentRes,
                    categoryRes,
                    topProductsRes,
                    topProfitRes,
                    inventoryRiskRes,
                    deadStockRes,
                    slowMoversRes,
                    orderStatusRes,
                    operationalRes,
                    rxRes,
                    customerRes,
                    loyaltyRes,
                    lowStockRes,
                    expiringRes
                ] = await Promise.all([
                    safeFetchJson(`${base}&action=metrics`),
                    safeFetchJson(`${base}&action=sales_trend`),
                    safeFetchJson(`${base}&action=payment_mix`),
                    safeFetchJson(`${base}&action=category_sales`),
                    safeFetchJson(`${base}&action=top_products&limit=10`),
                    safeFetchJson(`${base}&action=top_products_profit&limit=10`),
                    safeFetchJson(`${base}&action=inventory_risk`),
                    safeFetchJson(`${base}&action=dead_stock&limit=10`),
                    safeFetchJson(`${base}&action=slow_movers&limit=10`),
                    safeFetchJson(`${base}&action=order_status`),
                    safeFetchJson(`${base}&action=operational_stats`),
                    safeFetchJson(`${base}&action=rx_stats&limit=10`),
                    safeFetchJson(`${base}&action=customer_stats&limit=10`),
                    safeFetchJson(`${base}&action=loyalty_stats`),
                    safeFetchJson('inventory_api.php?action=low_stock_alert'),
                    safeFetchJson('inventory_api.php?action=expiring_products')
                ]);

                // -- Metrics cards --
                if (metricsRes.success) {
                    const m = metricsRes.data;
                    document.getElementById('totalRevenue').textContent = formatCurrency(m.revenue);
                    document.getElementById('revenueGrowth').textContent = formatPercent(m.revenue_growth_pct);
                    document.getElementById('grossProfit').textContent = formatCurrency(m.gross_profit);
                    document.getElementById('grossMargin').textContent = formatPercent(m.gross_margin_pct);
                    document.getElementById('totalSales').textContent = m.sales_count || 0;
                    document.getElementById('avgTransaction').textContent = formatCurrency(m.avg_transaction);
                    document.getElementById('productsSold').textContent = m.products_sold || 0;
                }

                if (inventoryRiskRes.success) {
                    const r = inventoryRiskRes.data;
                    document.getElementById('expiryRiskValue').textContent = formatCurrency(r.risk_30_value);
                }

                if (operationalRes.success) {
                    const o = operationalRes.data;
                    document.getElementById('pickupCycleTime').textContent = formatMinutes(o.avg_cycle_minutes);
                }

                if (rxRes.success) {
                    const r = rxRes.data;
                    document.getElementById('rxApprovalRate').textContent = formatPercent(r.approval_rate);
                }

                if (customerRes.success) {
                    const c = customerRes.data;
                    document.getElementById('repeatRate').textContent = formatPercent(c.repeat_rate);
                }

                if (loyaltyRes.success) {
                    const l = loyaltyRes.data;
                    document.getElementById('loyaltyPoints').textContent = Number(l.points_total || 0).toLocaleString();
                }

                // -- Top Products table --
                if (topProductsRes.success && topProductsRes.data.length > 0) {
                    const rows = topProductsRes.data.map((p, i) => [
                        i + 1,
                        p.product_name || p.name,
                        p.total_quantity,
                        formatCurrency(p.total_revenue)
                    ]);
                    mockTable('topProductsTable', rows);
                } else {
                    mockTable('topProductsTable', [['—', 'No sales data for this period', '—', '—']]);
                }

                if (topProfitRes.success && topProfitRes.data.length > 0) {
                    const rows = topProfitRes.data.map((p, i) => [
                        i + 1,
                        p.product_name || p.name,
                        formatCurrency(p.gross_profit),
                        formatPercent(p.margin_pct)
                    ]);
                    mockTable('topProfitTable', rows);
                } else {
                    mockTable('topProfitTable', [['—', 'No profit data for this period', '—', '—']]);
                }

                // -- Category Sales table --
                if (orderStatusRes.success && orderStatusRes.data.length > 0) {
                    const totalOrders = orderStatusRes.data.reduce((s, r) => s + Number(r.order_count || 0), 0);
                    const rows = orderStatusRes.data.map(r => {
                        const pct = totalOrders > 0 ? ((Number(r.order_count) / totalOrders) * 100).toFixed(1) + '%' : '0%';
                        return [r.status, r.order_count, pct];
                    });
                    mockTable('orderStatusTable', rows);
                } else {
                    mockTable('orderStatusTable', [['—', 'No online orders in this period', '—']]);
                }

                // -- Low Stock table --
                if (lowStockRes.success && lowStockRes.data.length > 0) {
                    const rows = lowStockRes.data.slice(0, 10).map(p => {
                        let badge;
                        if (p.stock_quantity <= 0) badge = '<span style="color: var(--danger-color, #dc3545); font-weight: 700;">Out of Stock</span>';
                        else if (p.stock_quantity <= 5) badge = '<span style="color: var(--danger-color, #dc3545); font-weight: 700;">Critical</span>';
                        else badge = '<span style="color: var(--accent-color, #fd7e14); font-weight: 700;">Low</span>';
                        return [p.name, p.stock_quantity, badge];
                    });
                    mockTable('lowStockTable', rows);
                } else {
                    mockTable('lowStockTable', [['—', '—', 'All stock levels OK']]);
                }

                // -- Expiring table --
                if (expiringRes.success && expiringRes.data.length > 0) {
                    const rows = expiringRes.data.slice(0, 10).map(p => {
                        const days = p.days_until_expiry;
                        let badge;
                        if (days <= 7) badge = `<span style="color: var(--danger-color, #dc3545); font-weight: 700;">${days} Days</span>`;
                        else badge = `<span style="color: var(--accent-color, #fd7e14); font-weight: 700;">${days} Days</span>`;
                        return [p.name, p.expiry_date, badge];
                    });
                    mockTable('expiringTable', rows);
                } else {
                    mockTable('expiringTable', [['—', '—', 'No products expiring soon']]);
                }

                if (deadStockRes.success && deadStockRes.data.length > 0) {
                    const rows = deadStockRes.data.map(p => [
                        p.product_name || p.name,
                        p.stock_quantity,
                        p.last_sale_date || 'Never'
                    ]);
                    mockTable('deadStockTable', rows);
                } else {
                    mockTable('deadStockTable', [['—', 'No dead stock found', '—']]);
                }

                if (slowMoversRes.success && slowMoversRes.data.length > 0) {
                    const rows = slowMoversRes.data.map(p => [
                        p.product_name || p.name,
                        p.qty_90_days,
                        p.stock_quantity
                    ]);
                    mockTable('slowMoversTable', rows);
                } else {
                    mockTable('slowMoversTable', [['—', 'No slow movers found', '—']]);
                }

                if (rxRes.success && rxRes.data.recent_logs && rxRes.data.recent_logs.length > 0) {
                    const rows = rxRes.data.recent_logs.map(l => [
                        l.action,
                        l.product_name || 'Unknown',
                        l.pharmacist_name || 'Unknown',
                        l.created_at
                    ]);
                    mockTable('rxLogTable', rows);
                } else {
                    mockTable('rxLogTable', [['—', 'No recent Rx actions', '—', '—']]);
                }

                if (customerRes.success && customerRes.data.top_customers && customerRes.data.top_customers.length > 0) {
                    const rows = customerRes.data.top_customers.map(c => [
                        c.customer_name || c.email || 'Unknown',
                        c.order_count,
                        formatCurrency(c.total_spent),
                        c.last_order_date || '—'
                    ]);
                    mockTable('topCustomersTable', rows);
                } else {
                    mockTable('topCustomersTable', [['—', 'No customer data for this period', '—', '—']]);
                }

                if (trendRes.success && trendRes.data.length > 0) {
                    const labels = trendRes.data.map(r => r.sale_date);
                    const values = trendRes.data.map(r => Number(r.revenue || 0));
                    revenueTrendChart = renderLineChart(revenueTrendChart, 'revenueTrendChart', labels, values, 'Revenue');
                } else {
                    revenueTrendChart = renderLineChart(revenueTrendChart, 'revenueTrendChart', [], [], 'Revenue');
                }

                if (paymentRes.success && paymentRes.data.length > 0) {
                    const labels = paymentRes.data.map(r => r.payment_method || 'Unknown');
                    const values = paymentRes.data.map(r => Number(r.total_amount || 0));
                    paymentMixChart = renderDoughnutChart(paymentMixChart, 'paymentMixChart', labels, values);
                } else {
                    paymentMixChart = renderDoughnutChart(paymentMixChart, 'paymentMixChart', [], []);
                }

                if (categoryRes.success && categoryRes.data.length > 0) {
                    const labels = categoryRes.data.map(r => r.category_name || r.category);
                    const values = categoryRes.data.map(r => Number(r.total_revenue || 0));
                    categoryChart = renderBarChart(categoryChart, 'categoryChart', labels, values, 'Revenue');
                } else {
                    categoryChart = renderBarChart(categoryChart, 'categoryChart', [], [], 'Revenue');
                }

            } catch (err) {
                console.error('Error loading reports:', err);
                showToast('Failed to load report data', 'error');
            }
        }

        function formatCurrency(value) {
            return '₱' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatPercent(value) {
            if (value === null || value === undefined || isNaN(value)) return 'N/A';
            return Number(value).toFixed(1) + '%';
        }

        function formatMinutes(value) {
            if (!value || isNaN(value)) return '0m';
            const minutes = Math.round(Number(value));
            if (minutes < 60) return `${minutes}m`;
            const hours = Math.floor(minutes / 60);
            const rem = minutes % 60;
            return `${hours}h ${rem}m`;
        }

        function renderLineChart(existing, canvasId, labels, data, label) {
            if (existing) existing.destroy();
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { callback: value => '₱' + value } }
                    }
                }
            });
        }

        function renderDoughnutChart(existing, canvasId, labels, data) {
            if (existing) existing.destroy();
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#ef4444', '#7c3aed', '#0ea5e9']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        function renderBarChart(existing, canvasId, labels, data, label) {
            if (existing) existing.destroy();
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { callback: value => '₱' + value } }
                    }
                }
            });
        }
        
        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = String(str ?? '');
            return div.innerHTML;
        }

        function mockTable(id, rows) {
            const tbody = document.querySelector(`#${id} tbody`);
            tbody.innerHTML = rows.map(r => `<tr>${r.map(c => {
                // If the cell contains HTML markup (badges), render it directly; otherwise escape
                if (typeof c === 'string' && c.includes('<span')) return `<td>${c}</td>`;
                return `<td>${escHtml(c)}</td>`;
            }).join('')}</tr>`).join('');
        }
        
        function exportReport(type) {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const url = `get_reports_data.php?action=export&type=${type}&start=${startDate}&end=${endDate}`;
            window.open(url, '_blank');
            showToast('Exporting data to CSV...', 'success');
        }
        
        function showToast(msg, type='success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = `toast ${type}`;
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 3000);
        }
    </script>
</body>
</html>