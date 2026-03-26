<?php
/**
 * Purchase Orders Management
 * Create and manage purchase orders for restocking
 */

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('suppliers.view')) {
    die('<h1>Access Denied</h1><p>You do not have permission to manage purchase orders.</p>');
}

$currentUser = $auth->getCurrentUser();
$page_title = 'Purchase Orders';
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
    <link rel="stylesheet" href="design-system.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="award-winning-polish.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="custom-modal.js"></script>
    <style>
        /* ── Purchase Orders — Award-Winning ─────────────── */
        .po-container { max-width:1440px; margin:0 auto; padding:1.25rem 1.5rem 2rem; }

        /* Page Header Card */
        .po-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 1px 3px rgba(0,0,0,.06); position:relative; overflow:hidden; animation:poFadeIn .35s ease both; }
        .po-header::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(180deg,#0a74da,#6366f1); border-radius:4px 0 0 4px; }
        .po-header::after { content:''; position:absolute; top:-50%; right:-15%; width:280px; height:280px; background:radial-gradient(circle,rgba(10,116,218,.04) 0%,transparent 70%); pointer-events:none; }
        .po-header h1 { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; color:var(--text-color,#222); margin:0; display:flex; align-items:center; gap:.5rem; }
        .po-header h1 i { background:linear-gradient(135deg,#0a74da,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; font-size:1.3rem; }
        .po-header p { margin:.25rem 0 0; color:#64748b; font-size:.87rem; }

        /* Buttons */
        .btn { padding:.6rem 1.25rem; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:.87rem; font-family:inherit; transition:all .25s cubic-bezier(.34,1.56,.64,1); display:inline-flex; align-items:center; gap:.4rem; }
        .btn-primary { background:linear-gradient(135deg,#0a74da,#5b7fff); color:#fff; box-shadow:0 3px 12px rgba(10,116,218,.25); }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(10,116,218,.35); }
        .btn-primary:active { transform:scale(.97); }
        .btn-success { background:#10b981; color:#fff; } .btn-success:hover{background:#059669;transform:translateY(-1px)}
        .btn-danger { background:#ef4444; color:#fff; } .btn-danger:hover{background:#dc2626;transform:translateY(-1px)}
        .btn-sm { padding:.4rem .85rem; font-size:.8rem; border-radius:8px; }

        /* Stat Cards */
        .po-stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:.75rem; margin-bottom:1.5rem; }
        .po-stat { background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:14px; padding:1rem 1.15rem; display:flex; align-items:center; gap:.75rem; transition:transform .25s cubic-bezier(.34,1.56,.64,1),box-shadow .25s ease; position:relative; overflow:hidden; animation:poFadeIn .35s ease both; }
        .po-stat:nth-child(1){animation-delay:.04s} .po-stat:nth-child(2){animation-delay:.08s} .po-stat:nth-child(3){animation-delay:.12s} .po-stat:nth-child(4){animation-delay:.16s}
        .po-stat::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:0 3px 3px 0; }
        .po-stat.s-all::before{background:linear-gradient(180deg,#0a74da,#6366f1)} .po-stat.s-pending::before{background:linear-gradient(180deg,#f59e0b,#d97706)} .po-stat.s-ordered::before{background:linear-gradient(180deg,#3b82f6,#2563eb)} .po-stat.s-received::before{background:linear-gradient(180deg,#10b981,#059669)}
        .po-stat:hover { transform:translateY(-3px); box-shadow:0 8px 25px -5px rgba(0,0,0,.1); }
        .po-stat .si { width:42px; height:42px; border-radius:12px; display:grid; place-items:center; font-size:1.1rem; flex-shrink:0; transition:transform .25s ease; }
        .po-stat:hover .si { transform:scale(1.08); }
        .si.blue{background:rgba(10,116,218,.1);color:#0a74da} .si.amber{background:rgba(245,158,11,.1);color:#f59e0b} .si.sky{background:rgba(59,130,246,.1);color:#3b82f6} .si.green{background:rgba(16,185,129,.1);color:#10b981}
        .po-stat .sv { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; line-height:1.1; }
        .sv.blue{color:#0a74da} .sv.amber{color:#f59e0b} .sv.sky{color:#3b82f6} .sv.green{color:#10b981}
        .po-stat .sl { font-size:.68rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#64748b; margin-top:.1rem; }

        /* Tabs */
        .po-tabs { display:flex; gap:2px; padding:3px; border-radius:12px; background:var(--c-surface-sunken,#f1f5f9); border:1px solid var(--c-border,#e5e7eb); margin-bottom:1.25rem; overflow-x:auto; animation:poFadeIn .35s .1s ease both; }
        .po-tab { padding:.55rem 1.25rem; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:.85rem; background:transparent; color:#64748b; transition:all .2s ease; font-family:inherit; display:inline-flex; align-items:center; gap:.35rem; white-space:nowrap; }
        .po-tab:hover { color:var(--text-color,#222); background:rgba(0,0,0,.03); }
        .po-tab.active { background:linear-gradient(135deg,#0a74da,#5b7fff); color:#fff; box-shadow:0 2px 8px rgba(10,116,218,.25); }
        .po-tab .badge { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 6px; border-radius:10px; font-size:.65rem; font-weight:800; background:rgba(0,0,0,.08); color:inherit; margin-left:2px; }
        .po-tab.active .badge { background:rgba(255,255,255,.25); }

        /* Table Card */
        .po-table { background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:16px; padding:0; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; animation:poFadeIn .35s .15s ease both; }
        [data-theme="dark"] .po-table { background:#1e293b; }
        table { width:100%; border-collapse:collapse; }
        th { padding:.85rem 1.25rem; text-align:left; font-weight:700; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; background:var(--c-surface-sunken,#f8fafc); border-bottom:1px solid var(--c-border,#e5e7eb); }
        td { padding:.85rem 1.25rem; text-align:left; border-bottom:1px solid var(--c-border,#e5e7eb); font-size:.9rem; color:var(--text-color,#222); transition:background .15s ease; }
        tbody tr { transition:background .15s ease; }
        tbody tr:hover { background:rgba(10,116,218,.02); }
        tbody tr:last-child td { border-bottom:none; }

        /* Status Badges */
        .status-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.25rem .7rem; border-radius:20px; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
        .status-pending { background:rgba(245,158,11,.1); color:#d97706; } .status-pending i{color:#f59e0b}
        .status-ordered { background:rgba(59,130,246,.1); color:#2563eb; } .status-ordered i{color:#3b82f6}
        .status-received { background:rgba(16,185,129,.1); color:#059669; } .status-received i{color:#10b981}
        .status-cancelled { background:rgba(239,68,68,.1); color:#dc2626; } .status-cancelled i{color:#ef4444}

        /* Modal */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center; }
        .modal.active { display:flex; }
        .modal-content { background:var(--c-surface,#fff); border-radius:16px; max-width:900px; width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px rgba(0,0,0,.15); animation:poModalIn .3s cubic-bezier(.34,1.56,.64,1) both; }
        [data-theme="dark"] .modal-content { background:#1e293b; }
        .modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--c-border,#e5e7eb); display:flex; justify-content:space-between; align-items:center; }
        .modal-header h2 { margin:0; font-weight:700; font-size:1.15rem; }
        .close-modal { background:none; border:1px solid var(--c-border,#e5e7eb); width:32px; height:32px; border-radius:8px; display:grid; place-items:center; cursor:pointer; font-size:1.1rem; color:var(--text-color,#222); transition:all .15s ease; }
        .close-modal:hover { background:rgba(239,68,68,.08); border-color:#ef4444; color:#ef4444; }
        .modal-body { padding:1.5rem; }
        .form-group { margin-bottom:1.25rem; }
        .form-group label { display:block; margin-bottom:.4rem; font-weight:600; font-size:.85rem; color:var(--text-color,#222); }
        .form-group input,.form-group select,.form-group textarea { width:100%; padding:.65rem .85rem; border:1.5px solid var(--c-border,#e5e7eb); border-radius:10px; font-size:.9rem; background:var(--bg-color,#fff); color:var(--text-color,#222); font-family:inherit; transition:border-color .2s ease,box-shadow .2s ease; }
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus { outline:none; border-color:#0a74da; box-shadow:0 0 0 3px rgba(10,116,218,.1); }
        .items-section { margin-top:1.5rem; }
        .item-row { display:grid; grid-template-columns:2fr 1fr 1fr 1fr 44px; gap:.75rem; margin-bottom:.75rem; align-items:end; }
        .total-section { margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--c-border,#e5e7eb); text-align:right; }
        .total-amount { font-size:1.4rem; font-weight:800; background:linear-gradient(135deg,#0a74da,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .modal-footer { padding:1.25rem 1.5rem; border-top:1px solid var(--c-border,#e5e7eb); display:flex; gap:.75rem; justify-content:flex-end; }
        
        /* Empty State */
        .empty-state { text-align:center; padding:3.5rem 2rem; }
        .empty-icon { font-size:3rem; margin-bottom:.75rem; opacity:.25; }
        .empty-title { font-size:1rem; font-weight:700; color:var(--text-color,#222); margin:0 0 .3rem; opacity:.6; }
        .empty-sub { font-size:.85rem; color:#64748b; margin:0; opacity:.5; }

        /* Animations */
        @keyframes poFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        @keyframes poModalIn { from{opacity:0;transform:scale(.95) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }

        @media (max-width:768px) {
            .po-container { padding:.75rem 1rem; }
            .po-header { flex-direction:column; align-items:flex-start; }
            .po-stats { grid-template-columns:repeat(2,1fr); }
            .item-row { grid-template-columns:1fr; }
            table { display:block; overflow-x:auto; }
            .po-tabs { gap:0; }
        }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="po-container">
        <!-- Page Header -->
        <div class="po-header">
            <div>
                <h1><i class="fas fa-boxes-packing"></i> Purchase Orders</h1>
                <p>Create and manage supplier purchase orders for restocking</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create Purchase Order
            </button>
        </div>

        <!-- Stat Cards -->
        <div class="po-stats">
            <div class="po-stat s-all"><div class="si blue"><i class="fas fa-clipboard-list"></i></div><div><div class="sv blue" id="statAll">0</div><div class="sl">Total Orders</div></div></div>
            <div class="po-stat s-pending"><div class="si amber"><i class="fas fa-clock"></i></div><div><div class="sv amber" id="statPending">0</div><div class="sl">Pending</div></div></div>
            <div class="po-stat s-ordered"><div class="si sky"><i class="fas fa-truck"></i></div><div><div class="sv sky" id="statOrdered">0</div><div class="sl">Ordered</div></div></div>
            <div class="po-stat s-received"><div class="si green"><i class="fas fa-check-circle"></i></div><div><div class="sv green" id="statReceived">0</div><div class="sl">Received</div></div></div>
        </div>
        
        <!-- Tabs -->
        <div class="po-tabs">
            <button class="po-tab active" data-status="all" onclick="filterByStatus('all')"><i class="fas fa-border-all"></i> All <span class="badge" id="badgeAll">0</span></button>
            <button class="po-tab" data-status="Pending" onclick="filterByStatus('Pending')"><i class="fas fa-clock"></i> Pending <span class="badge" id="badgePending">0</span></button>
            <button class="po-tab" data-status="Ordered" onclick="filterByStatus('Ordered')"><i class="fas fa-truck"></i> Ordered <span class="badge" id="badgeOrdered">0</span></button>
            <button class="po-tab" data-status="Received" onclick="filterByStatus('Received')"><i class="fas fa-check-circle"></i> Received <span class="badge" id="badgeReceived">0</span></button>
            <button class="po-tab" data-status="Cancelled" onclick="filterByStatus('Cancelled')"><i class="fas fa-times-circle"></i> Cancelled <span class="badge" id="badgeCancelled">0</span></button>
        </div>
        
        <!-- Table -->
        <div class="po-table">
            <table id="poTable">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="poTableBody">
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-boxes-packing"></i></div>
                                <div class="empty-title">Loading purchase orders...</div>
                                <div class="empty-sub">Please wait while we fetch your data</div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Create/Edit PO Modal -->
    <div class="modal" id="poModal">
        <div class="modal-content modal-content-slide">
            <div class="modal-header">
                <h2 id="modalTitle">Create Purchase Order</h2>
                <button class="btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="poForm" onsubmit="savePO(event)">
                <div class="modal-body">
                    <input type="hidden" id="poId">
                    
                    <div class="form-group">
                        <label for="supplierId">Supplier *</label>
                        <select id="supplierId" required onchange="loadSupplierProducts()">
                            <option value="">Select Supplier...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="items-section">
                        <h3>Items</h3>
                        <div id="itemsContainer"></div>
                        <button type="button" class="btn btn-secondary" onclick="addItemRow()">+ Add Item</button>
                    </div>
                    
                    <div class="total-section">
                        <strong>Total: </strong>
                        <span class="total-amount">₱<span id="totalAmount">0.00</span></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="theme.js"></script>
    <script src="shared-polish.js"></script>
    <script>
        let allPOs = [];
        let currentStatus = 'all';
        let suppliers = [];
        let supplierProducts = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            loadPurchaseOrders();
            loadSuppliers();
        });
        
        async function loadPurchaseOrders() {
            loading.show('Loading purchase orders...');
            
            try {
                const response = await fetch('purchase_order_api.php?action=get_all');
                const data = await response.json();
                
                loading.hide();
                
                if (data.success) {
                    allPOs = data.data || [];
                    renderPOs(allPOs);
                } else {
                    toast.error(data.message || 'Failed to load purchase orders');
                }
            } catch (error) {
                loading.hide();
                console.error('Error:', error);
                toast.error('Error loading purchase orders');
            }
        }
        
        async function loadSuppliers() {
            try {
                const response = await fetch('supplier_api.php?action=get_all');
                const data = await response.json();
                
                if (data.success) {
                    suppliers = data.data || [];
                    populateSupplierDropdown();
                }
            } catch (error) {
                console.error('Error loading suppliers:', error);
            }
        }
        
        function populateSupplierDropdown() {
            const select = document.getElementById('supplierId');
            select.innerHTML = '<option value="">Select Supplier...</option>';
            
            suppliers.forEach(supplier => {
                const option = document.createElement('option');
                option.value = supplier.supplier_id;
                option.textContent = supplier.name;
                select.appendChild(option);
            });
        }
        
        async function loadSupplierProducts() {
            const supplierId = document.getElementById('supplierId').value;
            if (!supplierId) return;
            
            try {
                const response = await fetch('supplier_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'get_supplier_products',
                        supplier_id: parseInt(supplierId)
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    supplierProducts = data.data || [];
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
        
        function renderPOs(pos) {
            const tbody = document.getElementById('poTableBody');
            
            // Update stat cards & badges
            const counts = {all:allPOs.length, Pending:0, Ordered:0, Received:0, Cancelled:0};
            allPOs.forEach(po => counts[po.status] = (counts[po.status]||0)+1);
            const s = id => document.getElementById(id);
            if(s('statAll')) s('statAll').textContent = counts.all;
            if(s('statPending')) s('statPending').textContent = counts.Pending;
            if(s('statOrdered')) s('statOrdered').textContent = counts.Ordered;
            if(s('statReceived')) s('statReceived').textContent = counts.Received;
            if(s('badgeAll')) s('badgeAll').textContent = counts.all;
            if(s('badgePending')) s('badgePending').textContent = counts.Pending;
            if(s('badgeOrdered')) s('badgeOrdered').textContent = counts.Ordered;
            if(s('badgeReceived')) s('badgeReceived').textContent = counts.Received;
            if(s('badgeCancelled')) s('badgeCancelled').textContent = counts.Cancelled;
            
            if (pos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="fas fa-inbox"></i></div><div class="empty-title">No purchase orders found</div><div class="empty-sub">Create your first purchase order to get started</div></div></td></tr>';
                return;
            }
            
            const statusIcons = {Pending:'fa-clock',Ordered:'fa-truck',Received:'fa-check-circle',Cancelled:'fa-times-circle'};
            tbody.innerHTML = pos.map(po => `
                <tr>
                    <td><strong style="color:var(--c-brand,#0a74da)">${escapeHtml(po.po_number)}</strong></td>
                    <td>${escapeHtml(po.supplier_name)}</td>
                    <td><span style="font-weight:600">${po.item_count}</span> items</td>
                    <td><strong>₱${formatNumber(po.total_amount)}</strong></td>
                    <td><span class="status-badge status-${po.status.toLowerCase()}"><i class="fas ${statusIcons[po.status]||'fa-circle'}"></i> ${po.status}</span></td>
                    <td style="color:#64748b;font-size:.85rem">${formatDate(po.created_at)}</td>
                    <td>
                        <button class="btn btn-sm" style="background:rgba(10,116,218,.08);color:#0a74da" onclick="viewPO(${po.po_id})"><i class="fas fa-eye"></i> View</button>
                        ${po.status === 'Ordered' ? `<button class="btn btn-sm btn-success" onclick="receivePO(${po.po_id})"><i class="fas fa-check"></i> Receive</button>` : ''}
                        ${po.status === 'Pending' ? `<button class="btn btn-sm btn-danger" onclick="cancelPO(${po.po_id})"><i class="fas fa-times"></i> Cancel</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }
        
        function filterByStatus(status) {
            currentStatus = status;
            
            // Update active tab
            document.querySelectorAll('.po-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.dataset.status === status) {
                    tab.classList.add('active');
                }
            });
            
            // Filter POs
            const filtered = status === 'all' ? allPOs : allPOs.filter(po => po.status === status);
            renderPOs(filtered);
        }
        
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create Purchase Order';
            document.getElementById('poForm').reset();
            document.getElementById('poId').value = '';
            document.getElementById('itemsContainer').innerHTML = '';
            addItemRow();
            document.getElementById('poModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('poModal').classList.remove('active');
        }
        
        function addItemRow() {
            const container = document.getElementById('itemsContainer');
            const rowId = Date.now();
            
            const row = document.createElement('div');
            row.className = 'item-row';
            row.id = `row-${rowId}`;
            row.innerHTML = `
                <select class="product-select" required onchange="updateLineTotal(${rowId})">
                    <option value="">Select Product...</option>
                    ${supplierProducts.map(p => `<option value="${p.product_id}" data-price="${p.selling_price}">${p.product_name}</option>`).join('')}
                </select>
                <input type="number" class="qty-input" placeholder="Quantity" min="1" required onchange="updateLineTotal(${rowId})">
                <input type="number" class="cost-input" placeholder="Unit Cost" step="0.01" min="0" required onchange="updateLineTotal(${rowId})">
                <input type="text" class="total-input" readonly placeholder="0.00">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(${rowId})">×</button>
            `;
            
            container.appendChild(row);
        }
        
        function removeItemRow(rowId) {
            document.getElementById(`row-${rowId}`).remove();
            calculateTotal();
        }
        
        function updateLineTotal(rowId) {
            const row = document.getElementById(`row-${rowId}`);
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
            const total = qty * cost;
            
            row.querySelector('.total-input').value = total.toFixed(2);
            calculateTotal();
        }
        
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.total-input').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('totalAmount').textContent = total.toFixed(2);
        }
        
        async function savePO(event) {
            event.preventDefault();
            
            const supplierId = document.getElementById('supplierId').value;
            const notes = document.getElementById('notes').value;
            
            // Collect items
            const items = [];
            document.querySelectorAll('.item-row').forEach(row => {
                const productId = row.querySelector('.product-select').value;
                const quantity = row.querySelector('.qty-input').value;
                const unitCost = row.querySelector('.cost-input').value;
                
                if (productId && quantity && unitCost) {
                    items.push({
                        product_id: parseInt(productId),
                        quantity: parseInt(quantity),
                        unit_cost: parseFloat(unitCost)
                    });
                }
            });
            
            if (items.length === 0) {
                toast.error('Please add at least one item');
                return;
            }
            
            loading.show('Creating purchase order...');
            
            try {
                const response = await fetch('purchase_order_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'create',
                        supplier_id: parseInt(supplierId),
                        notes: notes,
                        items: items
                    })
                });
                
                const data = await response.json();
                
                loading.hide();
                
                if (data.success) {
                    toast.success('Purchase order created successfully!');
                    closeModal();
                    loadPurchaseOrders();
                } else {
                    toast.error(data.message || 'Failed to create purchase order');
                }
            } catch (error) {
                loading.hide();
                console.error('Error:', error);
                toast.error('Error creating purchase order');
            }
        }
        
        async function receivePO(id) {
            const ok = await customConfirm('Receive Purchase Order', 'Mark this purchase order as received? This will update inventory.', 'success', { confirmText: 'Yes, Mark Received', cancelText: 'Cancel' });
            if (!ok) {
                return;
            }
            
            loading.show('Processing...');
            
            try {
                const response = await fetch('purchase_order_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'receive',
                        po_id: id
                    })
                });
                
                const data = await response.json();
                
                loading.hide();
                
                if (data.success) {
                    toast.success('Purchase order received! Inventory updated.');
                    loadPurchaseOrders();
                } else {
                    toast.error(data.message || 'Failed to receive purchase order');
                }
            } catch (error) {
                loading.hide();
                console.error('Error:', error);
                toast.error('Error processing purchase order');
            }
        }
        
        async function cancelPO(id) {
            const ok = await customConfirm('Cancel Purchase Order', 'Cancel this purchase order?', 'danger', { confirmText: 'Yes, Cancel It', cancelText: 'Go Back' });
            if (!ok) {
                return;
            }
            
            loading.show('Cancelling...');
            
            try {
                const response = await fetch('purchase_order_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'cancel',
                        po_id: id
                    })
                });
                
                const data = await response.json();
                
                loading.hide();
                
                if (data.success) {
                    toast.success('Purchase order cancelled');
                    loadPurchaseOrders();
                } else {
                    toast.error(data.message || 'Failed to cancel purchase order');
                }
            } catch (error) {
                loading.hide();
                console.error('Error:', error);
                toast.error('Error cancelling purchase order');
            }
        }
        
        function formatNumber(num) {
            return parseFloat(num).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openCreateModal();
            }
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
    <script src="global-polish.js"></script>
</body>
</html>
