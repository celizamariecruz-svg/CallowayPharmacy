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
    <link rel="stylesheet" href="design-system.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="award-winning-polish.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="custom-modal.js?v=2"></script>
    <style>
        /* ── Supplier Management — Award-Winning ──────────── */
        .supplier-container { max-width:1440px; margin:0 auto; padding:1.25rem 1.5rem 2rem; }

        /* Page Header Card */
        .supplier-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 1px 3px rgba(0,0,0,.06); position:relative; overflow:hidden; animation:smFade .35s ease both; }
        .supplier-header::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(180deg,#0a74da,#6366f1); border-radius:4px 0 0 4px; }
        .supplier-header::after { content:''; position:absolute; top:-50%; right:-15%; width:280px; height:280px; background:radial-gradient(circle,rgba(10,116,218,.04) 0%,transparent 70%); pointer-events:none; }
        .supplier-header h1 { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; color:var(--text-color,#222); margin:0; display:flex; align-items:center; gap:.5rem; }
        .supplier-header h1 i { background:linear-gradient(135deg,#0a74da,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; font-size:1.3rem; }
        .supplier-header p { margin:.25rem 0 0; color:#64748b; font-size:.87rem; }

        .btn { padding:.6rem 1.25rem; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:.87rem; font-family:inherit; transition:all .25s cubic-bezier(.34,1.56,.64,1); display:inline-flex; align-items:center; gap:.4rem; }
        .btn-primary { background:linear-gradient(135deg,#0a74da,#5b7fff); color:#fff; box-shadow:0 3px 12px rgba(10,116,218,.25); }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(10,116,218,.35); }
        .btn-primary:active { transform:scale(.97); }
        .btn-secondary { background:rgba(10,116,218,.08); color:#0a74da; } .btn-secondary:hover{background:rgba(10,116,218,.15);transform:translateY(-1px)}
        .btn-danger { background:#ef4444; color:#fff; } .btn-danger:hover{background:#dc2626;transform:translateY(-1px)}
        .btn-sm { padding:.4rem .85rem; font-size:.8rem; border-radius:8px; }

        /* Search Bar */
        .search-bar { margin-bottom:1.25rem; animation:smFade .35s .05s ease both; }
        .search-bar input { width:100%; max-width:420px; padding:.65rem 1rem .65rem 2.5rem; border:1.5px solid var(--c-border,#e5e7eb); border-radius:12px; font-size:.9rem; background:var(--c-surface,#fff) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2394a3b8' viewBox='0 0 24 24' width='18' height='18'%3E%3Cpath d='M15.5 14h-.79l-.28-.27a6.5 6.5 0 0 0 1.48-5.34c-.47-2.78-2.79-5-5.59-5.34a6.505 6.505 0 0 0-7.27 7.27c.34 2.8 2.56 5.12 5.34 5.59a6.5 6.5 0 0 0 5.34-1.48l.27.28v.79l4.25 4.25c.41.41 1.08.41 1.49 0 .41-.41.41-1.08 0-1.49L15.5 14zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E") no-repeat 10px center; color:var(--text-color,#222); font-family:inherit; transition:border-color .2s ease,box-shadow .2s ease; }
        .search-bar input:focus { outline:none; border-color:#0a74da; box-shadow:0 0 0 3px rgba(10,116,218,.1); }

        /* Supplier Cards */
        .suppliers-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:1rem; }
        .supplier-card { background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 1px 3px rgba(0,0,0,.06); transition:transform .3s cubic-bezier(.34,1.56,.64,1),box-shadow .3s ease; position:relative; overflow:hidden; animation:smFade .35s ease both; }
        .supplier-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,#0a74da,#6366f1); opacity:0; transition:opacity .2s ease; }
        .supplier-card:hover { transform:translateY(-4px); box-shadow:0 12px 35px -8px rgba(0,0,0,.12); }
        .supplier-card:hover::before { opacity:1; }
        [data-theme="dark"] .supplier-card { background:#1e293b; }
        .supplier-name { font-size:1.1rem; font-weight:700; color:var(--text-color,#222); margin-bottom:.75rem; display:flex; align-items:center; gap:.4rem; transition:color .2s ease; }
        .supplier-card:hover .supplier-name { color:#0a74da; }
        .supplier-info { margin-bottom:.75rem; }
        .supplier-info-item { display:flex; align-items:center; gap:.5rem; margin-bottom:.45rem; color:var(--text-color,#222); font-size:.87rem; }
        .supplier-info-item i { color:#0a74da; font-size:.8rem; width:16px; text-align:center; }
        .supplier-actions { display:flex; gap:.5rem; margin-top:.75rem; padding-top:.75rem; border-top:1px solid var(--c-border,#e5e7eb); }

        /* Modal */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center; }
        .modal.active { display:flex; }
        .modal-content { background:var(--c-surface,#fff); border-radius:16px; max-width:600px; width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px rgba(0,0,0,.15); animation:smModalIn .3s cubic-bezier(.34,1.56,.64,1); }
        [data-theme="dark"] .modal-content { background:#1e293b; }
        .modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--c-border,#e5e7eb); display:flex; justify-content:space-between; align-items:center; }
        .modal-header h2 { margin:0; font-weight:700; font-size:1.15rem; color:var(--text-color,#222); }
        .close-modal { background:none; border:1px solid var(--c-border,#e5e7eb); width:32px; height:32px; border-radius:8px; display:grid; place-items:center; cursor:pointer; font-size:1.1rem; color:var(--text-color,#222); transition:all .15s ease; }
        .close-modal:hover { background:rgba(239,68,68,.08); border-color:#ef4444; color:#ef4444; }
        .modal-body { padding:1.5rem; }
        .form-group { margin-bottom:1.25rem; }
        .form-group label { display:block; margin-bottom:.4rem; font-weight:600; font-size:.85rem; color:var(--text-color,#222); }
        .form-group input,.form-group textarea { width:100%; padding:.65rem .85rem; border:1.5px solid var(--c-border,#e5e7eb); border-radius:10px; font-size:.9rem; background:var(--bg-color,#fff); color:var(--text-color,#222); font-family:inherit; transition:border-color .2s ease,box-shadow .2s ease; }
        .form-group input:focus,.form-group textarea:focus { outline:none; border-color:#0a74da; box-shadow:0 0 0 3px rgba(10,116,218,.1); }
        .form-group textarea { resize:vertical; min-height:80px; }
        .modal-footer { padding:1.25rem 1.5rem; border-top:1px solid var(--c-border,#e5e7eb); display:flex; gap:.75rem; justify-content:flex-end; }

        /* Empty State */
        .empty-state { text-align:center; padding:3.5rem 2rem; grid-column:1/-1; }
        .empty-icon { font-size:3rem; margin-bottom:.75rem; opacity:.25; }
        .empty-title { font-size:1rem; font-weight:700; opacity:.6; margin:0 0 .3rem; }
        .empty-sub { font-size:.85rem; color:#64748b; opacity:.5; }

        /* Animations */
        @keyframes smFade { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        @keyframes smModalIn { from{opacity:0;transform:scale(.95) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }

        @media (max-width:768px) {
            .supplier-container { padding:.75rem 1rem; }
            .supplier-header { flex-direction:column; align-items:flex-start; }
            .suppliers-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="supplier-container">
        <div class="supplier-header">
            <div>
                <h1><i class="fas fa-building"></i> Supplier Management</h1>
                <p>Manage your pharmacy's supplier network and contacts</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Supplier
            </button>
        </div>
        
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search suppliers by name, contact, or email..." oninput="filterSuppliers()">
        </div>
        
        <div class="suppliers-grid" id="suppliersGrid">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                <div class="empty-title">Loading suppliers...</div>
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
