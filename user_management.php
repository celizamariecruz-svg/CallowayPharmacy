<?php
/**
 * User Management System
 * Manage users, roles, permissions
 */

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('users.view')) {
    die('<h1>Access Denied</h1><p>You do not have permission to access user management.</p>');
}

$currentUser = $auth->getCurrentUser();
$page_title = 'User Management';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Calloway Pharmacy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-container {
            max-width: 1400px;
            margin: 100px auto 2rem;
            padding: 2rem;
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .user-header h1 {
            color: var(--primary-color);
            font-size: 2rem;
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
            box-shadow: 0 4px 12px rgba(10, 116, 218, 0.3);
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--input-border);
        }
        
        .tab {
            padding: 1rem 2rem;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-color);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        [data-theme="dark"] .stat-card {
            background: #1e293b;
        }
        
        .stat-icon {
            font-size: 3rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .stat-content h3 {
            margin: 0;
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .stat-content p {
            margin: 0.25rem 0 0;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        [data-theme="dark"] .table-container {
            background: #1e293b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table thead {
            background: var(--primary-color);
            color: white;
        }
        
        table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        table td {
            padding: 1rem;
            border-bottom: 1px solid var(--input-border);
        }
        
        table tbody tr:hover {
            background: var(--dropdown-hover);
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .role-admin {
            background: #6f42c1;
            color: white;
        }
        
        .role-cashier {
            background: #17a2b8;
            color: white;
        }
        
        .role-inventory {
            background: #28a745;
            color: white;
        }
        
        .role-manager {
            background: #fd7e14;
            color: white;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
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
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--primary-color);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-color);
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
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            background: var(--bg-color);
        }
        
        .permission-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .permission-item input[type="checkbox"] {
            width: auto;
        }
        
        .toast {
            position: fixed;
            top: 100px;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 10000;
            animation: slideIn 0.3s;
        }
        
        .toast.active {
            display: flex;
        }
        
        .toast.success {
            border-left: 4px solid #28a745;
        }
        
        .toast.error {
            border-left: 4px solid #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @media (max-width: 768px) {
            .tabs {
                overflow-x: auto;
            }
            
            table {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="user-container">
        <div class="user-header">
            <h1>👥 User Management</h1>
            <?php if ($auth->hasPermission('users.create')): ?>
            <button class="btn btn-primary" onclick="openAddUserModal()">
                ➕ Add User
            </button>
            <?php endif; ?>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3 id="totalUsers">0</h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3 id="activeUsers">0</h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔐</div>
                <div class="stat-content">
                    <h3 id="totalRoles">0</h3>
                    <p>User Roles</p>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('users')">Users</button>
            <button class="tab" onclick="switchTab('roles')">Roles & Permissions</button>
        </div>
        
        <!-- Users Tab -->
        <div class="tab-content active" id="usersTab">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr><td colspan="8" style="text-align: center; padding: 2rem;">Loading users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Roles Tab -->
        <div class="tab-content" id="rolesTab">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Role Name</th>
                            <th>Description</th>
                            <th>Users Count</th>
                            <th>Permissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rolesTableBody">
                        <tr><td colspan="5" style="text-align: center; padding: 2rem;">Loading roles...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="userModalTitle">Add User</h2>
                <button class="close-modal" onclick="closeUserModal()">&times;</button>
            </div>
            <form id="userForm" onsubmit="saveUser(event)">
                <input type="hidden" id="userId">
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" required>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label for="password">Password *</label>
                    <input type="password" id="password">
                </div>
                
                <div class="form-group">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName">
                </div>

                <div class="form-group">
                    <label for="employeeId">Employee *</label>
                    <select id="employeeId" required>
                        <option value="">Select Employee...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="userRole">Role *</label>
                    <select id="userRole" required>
                        <option value="">Select Role...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="userStatus">Status *</label>
                    <select id="userStatus" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>
    
    <script src="theme.js"></script>
    <script>
        let currentTab = 'users';
        
        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadUsers();
            loadRoles();
            loadRolesForSelect();
            loadEmployeesForSelect();
        });
        
        // Switch tabs
        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab + 'Tab').classList.add('active');
            
            // Load data for active tab
            if (tab === 'users') loadUsers();
            else if (tab === 'roles') loadRoles();
        }
        
        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('user_api.php?action=get_stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalUsers').textContent = data.data.total_users;
                    document.getElementById('activeUsers').textContent = data.data.active_users;
                    document.getElementById('totalRoles').textContent = data.data.total_roles;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        // Load users
        async function loadUsers() {
            try {
                const response = await fetch('user_api.php?action=get_users');
                const data = await response.json();
                
                const tbody = document.getElementById('usersTableBody');
                
                if (data.success && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(user => {
                        const statusClass = user.is_active == 1 ? 'status-active' : 'status-inactive';
                        const statusText = user.is_active == 1 ? 'Active' : 'Inactive';
                        const roleClass = 'role-' + (user.role_name || 'admin').toLowerCase().replace(' ', '');
                        const employeeName = user.employee_name ? escapeHtml(user.employee_name) : 'Unlinked';
                        
                        return `
                            <tr>
                                <td>${user.user_id}</td>
                                <td><strong>${escapeHtml(user.username)}</strong></td>
                                <td>${escapeHtml(user.email || 'N/A')}</td>
                                <td>${employeeName}</td>
                                <td><span class="role-badge ${roleClass}">${escapeHtml(user.role_name)}</span></td>
                                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                                <td>${user.last_login ? formatDateTime(user.last_login) : 'Never'}</td>
                                <td>
                                    <?php if ($auth->hasPermission('users.edit')): ?>
                                    <button class="btn btn-primary btn-small" onclick="editUser(${user.user_id})">Edit</button>
                                    <?php endif; ?>
                                    <?php if ($auth->hasPermission('users.delete')): ?>
                                    <button class="btn btn-danger btn-small" onclick="deleteUser(${user.user_id})">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">No users found</td></tr>';
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }
        
        // Load roles
        async function loadRoles() {
            try {
                const response = await fetch('user_api.php?action=get_roles');
                const data = await response.json();
                
                const tbody = document.getElementById('rolesTableBody');
                
                if (data.success && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(role => {
                        return `
                            <tr>
                                <td><strong>${escapeHtml(role.role_name)}</strong></td>
                                <td>${escapeHtml(role.description || 'N/A')}</td>
                                <td>${role.users_count}</td>
                                <td>${role.permissions_count} permissions</td>
                                <td>
                                    <?php if ($auth->hasPermission('roles.manage')): ?>
                                    <button class="btn btn-primary btn-small" onclick="viewRolePermissions(${role.role_id}, '${escapeHtml(role.role_name)}')">View Permissions</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No roles found</td></tr>';
                }
            } catch (error) {
                console.error('Error loading roles:', error);
            }
        }
        
        // Load roles for select dropdown
        async function loadRolesForSelect() {
            try {
                const response = await fetch('user_api.php?action=get_roles');
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('userRole');
                    data.data.forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.role_id;
                        option.textContent = role.role_name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading roles:', error);
            }
        }

        // Load employees for select dropdown
        async function loadEmployeesForSelect() {
            try {
                const response = await fetch('user_api.php?action=get_employees');
                const data = await response.json();

                if (data.success) {
                    const select = document.getElementById('employeeId');
                    select.innerHTML = '<option value="">Select Employee...</option>';
                    if (data.data.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = 'No employees found - add employees first';
                        opt.disabled = true;
                        select.appendChild(opt);
                        return;
                    }
                    data.data.forEach(emp => {
                        const option = document.createElement('option');
                        option.value = emp.id;
                        option.textContent = emp.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading employees:', error);
            }
        }
        
        
        // Open add user modal
        function openAddUserModal() {
            document.getElementById('userModalTitle').textContent = 'Add User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('password').required = true;
            document.getElementById('passwordGroup').style.display = 'block';
            document.getElementById('userModal').classList.add('active');
        }
        
        // Edit user
        async function editUser(id) {
            try {
                const response = await fetch(`user_api.php?action=get_user&id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const user = data.data;
                    document.getElementById('userModalTitle').textContent = 'Edit User';
                    document.getElementById('userId').value = user.user_id;
                    document.getElementById('username').value = user.username;
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('fullName').value = user.full_name || '';
                    document.getElementById('employeeId').value = user.employee_id || '';
                    document.getElementById('userRole').value = user.role_id;
                    document.getElementById('userStatus').value = user.is_active;
                    document.getElementById('password').required = false;
                    document.getElementById('passwordGroup').style.display = 'none';
                    document.getElementById('userModal').classList.add('active');
                }
            } catch (error) {
                console.error('Error loading user:', error);
                showToast('Error loading user', 'error');
            }
        }
        
        // Save user
        async function saveUser(event) {
            event.preventDefault();
            
            const userId = document.getElementById('userId').value;
            const isEdit = userId !== '';
            
            const userData = {
                user_id: userId || undefined,
                username: document.getElementById('username').value,
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                full_name: document.getElementById('fullName').value,
                employee_id: document.getElementById('employeeId').value,
                role_id: document.getElementById('userRole').value,
                is_active: document.getElementById('userStatus').value
            };
            
            const action = isEdit ? 'update_user' : 'create_user';
            
            try {
                const response = await fetch(`user_api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(userData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(isEdit ? 'User updated successfully' : 'User created successfully', 'success');
                    closeUserModal();
                    loadUsers();
                    loadStats();
                } else {
                    showToast(data.message || 'Failed to save user', 'error');
                }
            } catch (error) {
                console.error('Error saving user:', error);
                showToast('Error saving user', 'error');
            }
        }
        
        // Delete user
        async function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user?')) return;
            
            try {
                const response = await fetch(`user_api.php?action=delete_user`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: id })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('User deleted successfully', 'success');
                    loadUsers();
                    loadStats();
                } else {
                    showToast(data.message || 'Failed to delete user', 'error');
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                showToast('Error deleting user', 'error');
            }
        }
        
        // View role permissions
        function viewRolePermissions(roleId, roleName) {
            alert(`Viewing permissions for role: ${roleName}\n\nThis feature shows all permissions assigned to this role.`);
        }
        
        // Close user modal
        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            toast.className = `toast ${type} active`;
            
            setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        }
        
        // Utility functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N - New User
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddUserModal();
            }
            // F3 - Focus Search
            if (e.key === 'F3') {
                e.preventDefault();
                document.getElementById('searchInput')?.focus();
            }
        });
    </script>
    <script src="shared-polish.js"></script>
</body>
</html>
