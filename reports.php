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
                <div style="color: var(--text-light); font-size: 0.9rem;">
                    For selected period
                </div>
            </div>
            <div class="metric-card">
                <h3>Total Transactions</h3>
                <div class="value" id="totalSales">0</div>
                <div style="color: var(--text-light); font-size: 0.9rem;">
                    Completed orders
                </div>
            </div>
            <div class="metric-card">
                <h3>Avg. Transaction</h3>
                <div class="value" id="avgTransaction">₱0.00</div>
                <div style="color: var(--text-light); font-size: 0.9rem;">
                    Per customer
                </div>
            </div>
            <div class="metric-card">
                <h3>Products Sold</h3>
                <div class="value" id="productsSold">0</div>
                <div style="color: var(--text-light); font-size: 0.9rem;">
                   Units moved
                </div>
            </div>
        </div>

        <div class="reports-grid">
            <!-- Top Selling -->
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

            <!-- Categories -->
            <div class="report-card">
                <h2><i class="fas fa-tags"></i> Sales by Category</h2>
                <table id="categoryTable">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Revenue</th>
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
             <!-- Low Stock -->
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
            
             <!-- Expiring -->
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
    </main>
    
    <div class="toast" id="toast"></div>

    <script src="theme.js"></script>
    <script src="shared-polish.js"></script>
    <script>
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
        
        async function loadReports() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const base = `get_reports_data.php?start=${startDate}&end=${endDate}`;

            try {
                // Fetch all data in parallel
                const [metricsRes, topProductsRes, categoryRes, lowStockRes, expiringRes] = await Promise.all([
                    fetch(`${base}&action=metrics`).then(r => r.json()),
                    fetch(`${base}&action=top_products&limit=10`).then(r => r.json()),
                    fetch(`${base}&action=category_sales`).then(r => r.json()),
                    fetch('inventory_api.php?action=low_stock_alert').then(r => r.json()),
                    fetch('inventory_api.php?action=expiring_products').then(r => r.json())
                ]);

                // -- Metrics cards --
                if (metricsRes.success) {
                    const m = metricsRes.data;
                    document.getElementById('totalRevenue').textContent = '₱' + Number(m.revenue || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('totalSales').textContent = m.sales_count || 0;
                    document.getElementById('avgTransaction').textContent = '₱' + Number(m.avg_transaction || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('productsSold').textContent = m.products_sold || 0;
                }

                // -- Top Products table --
                if (topProductsRes.success && topProductsRes.data.length > 0) {
                    const rows = topProductsRes.data.map((p, i) => [
                        i + 1,
                        p.product_name || p.name,
                        p.total_quantity,
                        '₱' + Number(p.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2})
                    ]);
                    mockTable('topProductsTable', rows);
                } else {
                    mockTable('topProductsTable', [['—', 'No sales data for this period', '—', '—']]);
                }

                // -- Category Sales table --
                if (categoryRes.success && categoryRes.data.length > 0) {
                    const catTotal = categoryRes.data.reduce((s, c) => s + Number(c.total_revenue || 0), 0);
                    const rows = categoryRes.data.map(c => {
                        const pct = catTotal > 0 ? ((Number(c.total_revenue) / catTotal) * 100).toFixed(1) + '%' : '0%';
                        return [
                            c.category_name || c.category,
                            '₱' + Number(c.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2}),
                            pct
                        ];
                    });
                    mockTable('categoryTable', rows);
                } else {
                    mockTable('categoryTable', [['—', '—', '—']]);
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

            } catch (err) {
                console.error('Error loading reports:', err);
                showToast('Failed to load report data', 'error');
            }
        }
        
        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = String(str ?? '');
            return div.innerHTML;
        }

        function mockTable(id, rows) {
            const tbody = document.querySelector(`#${id} tbody`);
            tbody.innerHTML = rows.map(r => `<tr>${r.map(c => `<td>${escHtml(c)}</td>`).join('')}</tr>`).join('');
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