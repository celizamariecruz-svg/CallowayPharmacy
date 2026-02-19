<?php
/**
 * Supplier Management System
 * Full CRUD interface for managing suppliers
 */

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('suppliers.view')) {
    die('<h1>Access Denied</h1><p>You do not have permission to manage suppliers.</p>');
}

$currentUser = $auth->getCurrentUser();
$page_title = 'Supplier Management';
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
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="custom-modal.js?v=2"></script>
    <style>
        .supplier-container {
            max-width: 1400px;
            margin: 100px auto 2rem;
            padding: 2rem;
        }
        
        .supplier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .supplier-header h1 {
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
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .search-bar {
            margin-bottom: 1.5rem;
        }
        
        .search-bar input {
            width: 100%;
            max-width: 400px;
            padding: 0.75rem 1rem;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .suppliers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .supplier-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        [data-theme="dark"] .supplier-card {
            background: #1e293b;
        }
        
        .supplier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .supplier-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 1rem;
        }
        
        .supplier-info {
            margin-bottom: 1rem;
        }
        
        .supplier-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .supplier-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        /* Modal */
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
            max-width: 600px;
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
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-color);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-color);
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
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="supplier-container">
        <div class="supplier-header">
            <h1>🏢 Supplier Management</h1>
            <button class="btn btn-primary ripple-effect" onclick="openAddModal()">
                ➕ Add New Supplier
            </button>
        </div>
        
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search suppliers..." oninput="filterSuppliers()">
        </div>
        
        <div class="suppliers-grid" id="suppliersGrid">
            <div class="empty-state">
                <div class="empty-icon">⏳</div>
                <div>Loading suppliers...</div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Modal -->
    <div class="modal" id="supplierModal">
        <div class="modal-content modal-content-slide">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Supplier</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="supplierForm" onsubmit="saveSupplier(event)">
                <div class="modal-body">
                    <input type="hidden" id="supplierId">
                    
                    <div class="form-group">
                        <label for="supplierName">Supplier Name *</label>
                        <input type="text" id="supplierName" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactPerson">Contact Person</label>
                        <input type="text" id="contactPerson">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="theme.js"></script>
    <script src="shared-polish.js"></script>
    <script>
        let allSuppliers = [];
        
        // Load suppliers on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSuppliers();
        });
        
        async function loadSuppliers() {
            loading.show('Loading suppliers...');
            
            try {
                const response = await fetch('supplier_api.php?action=get_all');
                const data = await response.json();
                
                loading.hide();
                
                if (data.success) {
                    allSuppliers = data.data || [];
                    renderSuppliers(allSuppliers);
                } else {
                    toast.error(data.message || 'Failed to load suppliers');
                }
            } catch (error) {
                loading.hide();
                console.error('Error:', error);
                toast.error('Error loading suppliers');
            }
        }
        
        function renderSuppliers(suppliers) {
            const grid = document.getElementById('suppliersGrid');
            
            if (suppliers.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🏢</div>
                        <div>No suppliers found</div>
                        <div style="margin-top: 1rem;">
                            <button class="btn btn-primary" onclick="openAddModal()">Add Your First Supplier</button>
                        </div>
                    </div>
                `;
                return;
            }
            
            grid.innerHTML = suppliers.map(supplier => `
                <div class="supplier-card fade-in">
                    <div class="supplier-name">${escapeHtml(supplier.name)}</div>
                    <div class="supplier-info">
                        ${supplier.contact_person ? `
                            <div class="supplier-info-item">
                                <span>👤</span>
                                <span>${escapeHtml(supplier.contact_person)}</span>
                            </div>
                        ` : ''}
                        ${supplier.email ? `
                            <div class="supplier-info-item">
                                <span>📧</span>
                                <span>${escapeHtml(supplier.email)}</span>
                            </div>
                        ` : ''}
                        ${supplier.phone ? `
                            <div class="supplier-info-item">
                                <span>📞</span>
                                <span>${escapeHtml(supplier.phone)}</span>
                            </div>
                        ` : ''}
                        ${supplier.address ? `
                            <div class="supplier-info-item">
                                <span>📍</span>
                                <span>${escapeHtml(supplier.address)}</span>
                            </div>
                        ` : ''}
                    </div>
                    <div class="supplier-actions">
                        <button class="btn btn-secondary btn-sm" onclick="editSupplier(${supplier.supplier_id})">✏️ Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteSupplier(${supplier.supplier_id})">🗑️ Delete</button>
                    </div>
                </div>
            `).join('');
        }
        
        function filterSuppliers() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const filtered = allSuppliers.filter(s => 
                s.name.toLowerCase().includes(query) ||
                (s.contact_person && s.contact_person.toLowerCase().includes(query)) ||
                (s.email && s.email.toLowerCase().includes(query)) ||
                (s.phone && s.phone.toLowerCase().includes(query))
            );
            renderSuppliers(filtered);
        }
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Supplier';
            document.getElementById('supplierForm').reset();
            document.getElementById('supplierId').value = '';
            document.getElementById('supplierModal').classList.add('active');
        }
        
        function editSupplier(id) {
            const supplier = allSuppliers.find(s => s.supplier_id === id);
            if (!supplier) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Supplier';
            document.getElementById('supplierId').value = supplier.supplier_id;
            document.getElementById('supplierName').value = supplier.name;
            document.getElementById('contactPerson').value = supplier.contact_person || '';
            document.getElementById('email').value = supplier.email || '';
            document.getElementById('phone').value = supplier.phone || '';
            document.getElementById('address').value = supplier.address || '';
            document.getElementById('supplierModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('supplierModal').classList.remove('active');
        }
        
        async function saveSupplier(event) {
            event.preventDefault();
            
            const supplierId = document.getElementById('supplierId').value;
            const action = supplierId ? 'update' : 'create';
            
            const formData = {
                action: action,
                supplier_id: supplierId,
                name: document.getElementById('supplierName').value,
                contact_person: document.getElementById('contactPerson').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                address: document.getElementById('address').value
            };
            
            loading.show('Saving supplier...');
            
            try {
                const response = await fetch('supplier_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                loading.hide();
                
                if (data.success) {
                    toast.success(action === 'create' ? 'Supplier added successfully!' : 'Supplier updated successfully!');
                    closeModal();
                    loadSuppliers();
                } else {
                    toast.error(data.message || 'Failed to save supplier');
                }
            } catch (error) {
                loading.hide();
                console.error('Error:', error);
                toast.error('Error saving supplier');
            }
        }
        
        async function deleteSupplier(id) {
            const supplier = allSuppliers.find(s => s.supplier_id === id);
            if (!supplier) return;
            
            const ok = await customConfirm('Delete Supplier', `Are you sure you want to delete "${supplier.name}"?`, 'danger', { confirmText: 'Yes, Delete', cancelText: 'Cancel' });
            if (!ok) {
                return;
            }
            
            loading.show('Deleting supplier...');
            
            try {
                const response = await fetch('supplier_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        supplier_id: id
                    })
                });
                
                const data = await response.json();
                
                loading.hide();
                
                if (data.success) {
                    toast.success('Supplier deleted successfully!');
                    loadSuppliers();
                } else {
                    toast.error(data.message || 'Failed to delete supplier');
                }
            } catch (error) {
                loading.hide();
                console.error('Error:', error);
                toast.error('Error deleting supplier');
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddModal();
            }
            if (e.key === 'Escape') {
                closeModal();
            }
            if (e.key === 'F3') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
    <script src="global-polish.js"></script>
</body>
</html>
