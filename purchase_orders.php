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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .po-container {
            max-width: 1400px;
            margin: 100px auto 2rem;
            padding: 2rem;
        }
        
        .po-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .po-header h1 {
            color: var(--primary-color);
            margin: 0;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--input-border);
        }
        
        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            color: var(--text-color);
        }
        
        .tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .po-table {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] .po-table {
            background: #1e293b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--input-border);
        }
        
        th {
            font-weight: 600;
            color: var(--text-color);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-ordered {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-received {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        [data-theme="dark"] .modal-content {
            background: #1e293b;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--input-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .items-section {
            margin-top: 2rem;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 50px;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: end;
        }
        
        .total-section {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px solid var(--input-border);
            text-align: right;
        }
        
        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 2px solid var(--input-border);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-color);
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="po-container">
        <div class="po-header">
            <h1>📦 Purchase Orders</h1>
            <button class="btn btn-primary ripple-effect" onclick="openCreateModal()">
                ➕ Create Purchase Order
            </button>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-status="all" onclick="filterByStatus('all')">All Orders</div>
            <div class="tab" data-status="Pending" onclick="filterByStatus('Pending')">Pending</div>
            <div class="tab" data-status="Ordered" onclick="filterByStatus('Ordered')">Ordered</div>
            <div class="tab" data-status="Received" onclick="filterByStatus('Received')">Received</div>
            <div class="tab" data-status="Cancelled" onclick="filterByStatus('Cancelled')">Cancelled</div>
        </div>
        
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
                        <td colspan="7" class="empty-state">Loading...</td>
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
            
            if (pos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No purchase orders found</td></tr>';
                return;
            }
            
            tbody.innerHTML = pos.map(po => `
                <tr class="fade-in">
                    <td><strong>${escapeHtml(po.po_number)}</strong></td>
                    <td>${escapeHtml(po.supplier_name)}</td>
                    <td>${po.item_count} items</td>
                    <td>₱${formatNumber(po.total_amount)}</td>
                    <td><span class="status-badge status-${po.status.toLowerCase()}">${po.status}</span></td>
                    <td>${formatDate(po.created_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="viewPO(${po.po_id})">View</button>
                        ${po.status === 'Ordered' ? `<button class="btn btn-sm btn-success" onclick="receivePO(${po.po_id})">Receive</button>` : ''}
                        ${po.status === 'Pending' ? `<button class="btn btn-sm btn-danger" onclick="cancelPO(${po.po_id})">Cancel</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }
        
        function filterByStatus(status) {
            currentStatus = status;
            
            // Update active tab
            document.querySelectorAll('.tab').forEach(tab => {
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
            if (!confirm('Mark this purchase order as received? This will update inventory.')) {
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
            if (!confirm('Cancel this purchase order?')) {
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
