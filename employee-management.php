<?php
/**
 * Employee Management - Enhanced with Activity & Session Logs
 * Features: Employee CRUD, Login/Logout Session Tracking, Change Audit Trail
 */

ob_start();

require_once 'db_connection.php';
require_once 'CacheManager.php';
require_once 'ActivityLogger.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

$currentUser = $auth->getCurrentUser();

// Only admin can manage employees
if (strtolower($currentUser['role_name'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$logger = new ActivityLogger($conn);

function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - Employee Management: " . $message . "\n", 3, __DIR__ . "/logs/employee_errors.log");
}

// Ensure employees table exists
$conn->query("CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    shift_start TIME NOT NULL,
    shift_end TIME NOT NULL,
    on_leave TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_on_leave (on_leave)
) ENGINE=InnoDB");

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $response = ['success' => false, 'message' => '', 'html' => ''];
    $currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown';

    try {
        // Add employee
        if (isset($_POST['add_employee'])) {
            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $shift_start = trim($_POST['shift_start'] ?? '');
            $shift_end = trim($_POST['shift_end'] ?? '');

            if (empty($name) || empty($role) || empty($shift_start) || empty($shift_end)) {
                throw new Exception("All fields are required");
            }
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $shift_start) ||
                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $shift_end)) {
                throw new Exception("Invalid time format");
            }

            $stmt = $conn->prepare("INSERT INTO employees (name, role, shift_start, shift_end) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $name, $role, $shift_start, $shift_end);
            $stmt->execute();
            $empId = $conn->insert_id;
            $stmt->close();

            $logger->logChange('create', 'Employee Management', "$currentUser added employee: $name (ID: $empId)", [
                'target_type' => 'employee',
                'target_id' => $empId,
                'target_name' => $name,
                'new_values' => ['name' => $name, 'role' => $role, 'shift_start' => $shift_start, 'shift_end' => $shift_end]
            ]);

            $response['success'] = true;
            $response['message'] = "Employee added successfully";
            cache()->invalidateByPrefix('employees');
        }

        // Remove employee
        if (isset($_POST['remove_employee'])) {
            $id = filter_var($_POST['remove_employee'], FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) throw new Exception("Invalid employee ID");

            // Get employee info before deletion
            $infoStmt = $conn->prepare("SELECT name, role, shift_start, shift_end, on_leave FROM employees WHERE id = ?");
            $infoStmt->bind_param('i', $id);
            $infoStmt->execute();
            $empInfo = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();

            if (!$empInfo) throw new Exception("Employee not found");

            $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) throw new Exception("Employee not found");
            $stmt->close();

            $logger->logChange('delete', 'Employee Management', "$currentUser removed employee: {$empInfo['name']} (ID: $id)", [
                'target_type' => 'employee',
                'target_id' => $id,
                'target_name' => $empInfo['name'],
                'old_values' => $empInfo
            ]);

            $response['success'] = true;
            $response['message'] = "Employee removed successfully";
            cache()->invalidateByPrefix('employees');
        }

        // Toggle leave
        if (isset($_POST['toggle_leave'])) {
            $id = filter_var($_POST['toggle_leave'], FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) throw new Exception("Invalid employee ID");

            // Get current state
            $infoStmt = $conn->prepare("SELECT name, on_leave FROM employees WHERE id = ?");
            $infoStmt->bind_param('i', $id);
            $infoStmt->execute();
            $empInfo = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();

            if (!$empInfo) throw new Exception("Employee not found");

            $stmt = $conn->prepare("UPDATE employees SET on_leave = NOT on_leave WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) throw new Exception("Employee not found");
            $stmt->close();

            $newStatus = $empInfo['on_leave'] ? 'Active' : 'On Leave';
            $oldStatus = $empInfo['on_leave'] ? 'On Leave' : 'Active';

            $logger->logChange('toggle', 'Employee Management', "$currentUser changed {$empInfo['name']} status from $oldStatus to $newStatus", [
                'target_type' => 'employee',
                'target_id' => $id,
                'target_name' => $empInfo['name'],
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus]
            ]);

            $response['success'] = true;
            $response['message'] = "Leave status updated";
            cache()->invalidateByPrefix('employees');
        }

        // Edit employee
        if (isset($_POST['edit_employee'])) {
            $id = filter_var($_POST['edit_id'], FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) throw new Exception("Invalid employee ID");
            $name = trim($_POST['edit_name'] ?? '');
            $role = trim($_POST['edit_role'] ?? '');
            $shift_start = trim($_POST['edit_shift_start'] ?? '');
            $shift_end = trim($_POST['edit_shift_end'] ?? '');
            if (empty($name) || empty($role) || empty($shift_start) || empty($shift_end)) {
                throw new Exception("All fields are required");
            }
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $shift_start) ||
                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $shift_end)) {
                throw new Exception("Invalid time format");
            }
            $infoStmt = $conn->prepare("SELECT name, role, shift_start, shift_end FROM employees WHERE id = ?");
            $infoStmt->bind_param('i', $id);
            $infoStmt->execute();
            $oldInfo = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();
            if (!$oldInfo) throw new Exception("Employee not found");
            $stmt = $conn->prepare("UPDATE employees SET name=?, role=?, shift_start=?, shift_end=? WHERE id=?");
            $stmt->bind_param('ssssi', $name, $role, $shift_start, $shift_end, $id);
            $stmt->execute();
            $stmt->close();
            $logger->logChange('update', 'Employee Management', "$currentUser updated employee: $name (ID: $id)", [
                'target_type' => 'employee', 'target_id' => $id, 'target_name' => $name,
                'old_values' => $oldInfo,
                'new_values' => ['name' => $name, 'role' => $role, 'shift_start' => $shift_start, 'shift_end' => $shift_end]
            ]);
            $response['success'] = true;
            $response['message'] = "Employee updated successfully";
            cache()->invalidateByPrefix('employees');
        }

    } catch (Exception $e) {
        logError($e->getMessage());
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }

    if ($response['success']) {
        $response['html'] = render_employee_table($conn);
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle AJAX data requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'get_login_sessions') {
        $filters = [];
        if (!empty($_GET['user_id'])) $filters['user_id'] = (int)$_GET['user_id'];
        if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
        if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        $filters['limit'] = 100;
        echo json_encode(['success' => true, 'data' => $logger->getLoginSessions($filters)]);
        exit;
    }

    if ($_GET['action'] === 'get_change_logs') {
        $filters = [];
        if (!empty($_GET['user_id'])) $filters['user_id'] = (int)$_GET['user_id'];
        if (!empty($_GET['module'])) $filters['module'] = $_GET['module'];
        if (!empty($_GET['action_type'])) $filters['action_type'] = $_GET['action_type'];
        if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
        if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
        $filters['limit'] = 100;
        echo json_encode(['success' => true, 'data' => $logger->getChangeLogs($filters)]);
        exit;
    }

    if ($_GET['action'] === 'get_stats') {
        echo json_encode(['success' => true, 'data' => $logger->getStats()]);
        exit;
    }

    if ($_GET['action'] === 'get_users') {
        $result = $conn->query("SELECT user_id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
        $users = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $users[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    }
}

// Render employee table
function render_employee_table($conn) {
    $stmt = $conn->prepare("SELECT id, name, role, shift_start, shift_end, on_leave, created_at FROM employees ORDER BY id DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $roleIcons = ['pharmacist'=>'fa-prescription-bottle-medical','cashier'=>'fa-cash-register','staff'=>'fa-id-badge','manager'=>'fa-user-tie','admin'=>'fa-shield-halved'];
    ob_start();
    ?>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Role</th>
                <th>Shift</th>
                <th>Status</th>
                <th>Added</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()):
                    $rowClass = $row['on_leave'] ? 'on-leave' : '';
                    $roleClass = strtolower(preg_replace('/[^a-z0-9]/', '-', $row['role']));
                    $id = (int)$row['id'];
                    $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                    $role = htmlspecialchars(ucfirst($row['role']), ENT_QUOTES, 'UTF-8');
                    $initials = strtoupper(substr($row['name'], 0, 1));
                    $roleIcon = $roleIcons[strtolower($row['role'])] ?? 'fa-user';
                ?>
                <tr class="<?= $rowClass ?>" data-name="<?= $name ?>" data-role="<?= strtolower($row['role']) ?>">
                    <td>
                        <div class="emp-cell">
                            <div class="emp-avatar <?= $roleClass ?>"><?= $initials ?></div>
                            <div class="emp-info">
                                <span class="emp-name"><?= $name ?></span>
                                <span class="emp-id">#<?= $id ?></span>
                            </div>
                        </div>
                    </td>
                    <td><span class="role-badge <?= $roleClass ?>"><i class="fas <?= $roleIcon ?>"></i> <?= $role ?></span></td>
                    <td><span class="shift-time"><i class="far fa-clock"></i> <?= date('h:i A', strtotime($row['shift_start'])) ?> – <?= date('h:i A', strtotime($row['shift_end'])) ?></span></td>
                    <td>
                        <?php if ($row['on_leave']): ?>
                            <span class="status-badge on-leave"><i class="fas fa-moon"></i> On Leave</span>
                        <?php else: ?>
                            <span class="status-badge active"><i class="fas fa-circle-check"></i> Active</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="date-text"><?= date('M d, Y', strtotime($row['created_at'])) ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="action-btn-edit" onclick="openEditModal(<?= $id ?>, '<?= addslashes($row['name']) ?>', '<?= addslashes($row['role']) ?>', '<?= $row['shift_start'] ?>', '<?= $row['shift_end'] ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                            <form method="POST" class="toggle-leave-form" style="display:inline">
                                <button type="submit" name="toggle_leave" value="<?= $id ?>" class="action-btn-leave" title="<?= $row['on_leave'] ? 'Mark Active' : 'Put on Leave' ?>"><i class="fas <?= $row['on_leave'] ? 'fa-user-check' : 'fa-user-clock' ?>"></i></button>
                            </form>
                            <form method="POST" class="remove-employee-form" style="display:inline">
                                <button type="submit" name="remove_employee" value="<?= $id ?>" class="action-btn-delete" title="Remove"><i class="fas fa-trash-can"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 3rem;">
                        <div class="em-empty">
                            <i class="fas fa-users"></i>
                            <p>No employees yet</p>
                            <p>Add your first employee using the form above</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

// Get stats
$stats = $logger->getStats();
$employeeCount = $conn->query("SELECT COUNT(*) as cnt FROM employees")->fetch_assoc()['cnt'];
$activeCount = $conn->query("SELECT COUNT(*) as cnt FROM employees WHERE on_leave = 0")->fetch_assoc()['cnt'];
$onLeaveCount = $conn->query("SELECT COUNT(*) as cnt FROM employees WHERE on_leave = 1")->fetch_assoc()['cnt'];
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
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<title>Employee Management - Calloway Pharmacy</title>
<link rel="stylesheet" href="design-system.css">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="polish.css">
<link rel="stylesheet" href="responsive.css">
<link rel="stylesheet" href="custom-modal.css?v=2">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="custom-modal.js?v=2"></script>
<style>
/* ── Employee Management Premium ─────────────────────────── */
main { width:100%; max-width:1400px; margin:0 auto; padding:1.25rem 1.5rem 2rem; }

/* Page Header */
.page-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; animation:ds-fade-in 0.3s var(--ease-out) both; }
.page-header-left h1 { font-size:var(--text-2xl); font-weight:800; letter-spacing:-0.02em; color:var(--c-text); margin:0; line-height:1.2; display:flex; align-items:center; gap:0.4rem; }
.page-header-left p { margin:0.2rem 0 0; color:var(--c-text-muted); font-size:var(--text-sm); }
.page-header-right { display:flex; gap:0.5rem; }
.header-action-btn { display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 1rem; font-size:var(--text-sm); font-weight:600; border-radius:var(--radius-md); border:1px solid var(--c-border); background:var(--c-surface); color:var(--c-text); cursor:pointer; transition:all var(--duration-fast) var(--ease-out); font-family:var(--font-sans); }
.header-action-btn:hover { border-color:var(--c-brand); color:var(--c-brand); box-shadow:var(--shadow-sm); transform:translate3d(0,-1px,0); }
.header-action-btn:active { transform:translate3d(0,0,0) scale(0.98); transition-duration:80ms; }

/* Stats Grid */
.em-stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(185px,1fr)); gap:0.85rem; margin-bottom:1.5rem; }
.em-stat-card { background:var(--c-surface); border:1px solid var(--c-border); border-radius:var(--radius-lg); padding:1.1rem; display:flex; align-items:center; gap:0.85rem; transition:transform var(--duration-fast) var(--ease-out),box-shadow var(--duration-fast) var(--ease-out); will-change:transform; position:relative; overflow:hidden; animation:ds-fade-in 0.3s var(--ease-out) both; }
.em-stat-card:nth-child(1){animation-delay:.02s} .em-stat-card:nth-child(2){animation-delay:.04s} .em-stat-card:nth-child(3){animation-delay:.06s} .em-stat-card:nth-child(4){animation-delay:.08s} .em-stat-card:nth-child(5){animation-delay:.10s} .em-stat-card:nth-child(6){animation-delay:.12s}
.em-stat-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:0 3px 3px 0; opacity:0; transition:opacity var(--duration-fast) var(--ease-out); }
.em-stat-card:hover { transform:translate3d(0,-2px,0); box-shadow:var(--shadow-md); }
.em-stat-card:hover::before { opacity:1; }
.em-stat-card:nth-child(1)::before{background:var(--c-brand)} .em-stat-card:nth-child(2)::before{background:var(--c-success)} .em-stat-card:nth-child(3)::before{background:#f59e0b} .em-stat-card:nth-child(4)::before{background:#8b5cf6} .em-stat-card:nth-child(5)::before{background:#ec4899} .em-stat-card:nth-child(6)::before{background:#06b6d4}
.em-stat-icon { width:44px; height:44px; border-radius:var(--radius-lg); display:grid; place-items:center; font-size:1.15rem; flex-shrink:0; }
.em-stat-icon.blue{background:rgba(var(--c-brand-rgb),.1);color:var(--c-brand)} .em-stat-icon.green{background:rgba(16,185,129,.1);color:#10b981} .em-stat-icon.amber{background:rgba(245,158,11,.1);color:#f59e0b} .em-stat-icon.purple{background:rgba(139,92,246,.1);color:#8b5cf6} .em-stat-icon.pink{background:rgba(236,72,153,.1);color:#ec4899} .em-stat-icon.cyan{background:rgba(6,182,212,.1);color:#06b6d4}
.em-stat-body { min-width:0; }
.em-stat-value { font-size:var(--text-2xl); font-weight:800; line-height:1.1; color:var(--c-text); letter-spacing:-0.02em; }
.em-stat-label { font-size:0.68rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--c-text-muted); margin-top:0.15rem; }

/* Tabs */
.em-tabs { background:var(--c-surface); border:1px solid var(--c-border); border-radius:var(--radius-xl); overflow:visible; box-shadow:var(--shadow-sm); animation:ds-fade-in 0.35s .1s var(--ease-out) both; }
.em-tabs-header { display:flex; border-bottom:1px solid var(--c-border); overflow-x:auto; scrollbar-width:none; }
.em-tabs-header::-webkit-scrollbar{display:none}
.em-tab-btn { padding:0.85rem 1.25rem; background:transparent; border:none; color:var(--c-text-muted); font-weight:600; font-size:var(--text-sm); cursor:pointer; transition:color var(--duration-fast) var(--ease-out),background var(--duration-fast) var(--ease-out); white-space:nowrap; display:inline-flex; align-items:center; gap:0.5rem; position:relative; font-family:var(--font-sans); }
.em-tab-btn:hover{color:var(--c-text);background:var(--c-surface-sunken)} .em-tab-btn.active{color:var(--c-brand)}
.em-tab-btn.active::after { content:''; position:absolute; bottom:-1px; left:.75rem; right:.75rem; height:2px; background:var(--c-brand); border-radius:2px 2px 0 0; }
.em-tab-badge { background:var(--c-brand-ghost); color:var(--c-brand); padding:.1rem .45rem; border-radius:var(--radius-full); font-size:0.7rem; font-weight:700; line-height:1.4; }
.em-tab-btn.active .em-tab-badge{background:rgba(var(--c-brand-rgb),.15)}
.em-tab-content{display:none;padding:1.25rem} .em-tab-content.active{display:block;animation:ds-fade-in .25s var(--ease-out) both}

/* Form Card */
.em-form-card { border:1px solid var(--c-border); border-radius:var(--radius-lg); background:var(--c-surface-sunken); margin-bottom:1.25rem; overflow:hidden; }
.em-form-header { padding:.85rem 1.15rem; font-weight:700; font-size:var(--text-sm); color:var(--c-text); border-bottom:1px solid var(--c-border); display:flex; align-items:center; gap:.5rem; background:var(--c-surface); }
.em-form-body{padding:1.15rem}
.em-form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1rem; }
.em-form-group{display:flex;flex-direction:column;gap:.3rem}
.em-form-group label { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--c-text-secondary); }
.em-form-group input,.em-form-group select { padding:.6rem .85rem; border-radius:var(--radius-md); border:1.5px solid var(--c-border); background:var(--c-surface); color:var(--c-text); font-size:var(--text-base); font-family:var(--font-sans); transition:border-color var(--duration-fast) var(--ease-out),box-shadow var(--duration-fast) var(--ease-out); }
.em-form-group input:focus,.em-form-group select:focus { outline:none; border-color:var(--c-brand); box-shadow:0 0 0 3px rgba(var(--c-brand-rgb),.1); }
.em-form-submit { display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1.25rem; font-size:var(--text-sm); font-weight:600; font-family:var(--font-sans); border:none; border-radius:var(--radius-md); background:var(--c-brand); color:white; cursor:pointer; transition:all var(--duration-fast) var(--ease-out); box-shadow:var(--shadow-sm),0 1px 2px rgba(var(--c-brand-rgb),.2); }
.em-form-submit:hover { background:var(--c-brand-dark); box-shadow:var(--shadow-md); transform:translate3d(0,-1px,0); }
.em-form-submit:active { transform:translate3d(0,0,0) scale(.98); transition-duration:80ms; }

/* Search & Toolbar */
.em-toolbar { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1rem; flex-wrap:wrap; }
.em-search { position:relative; flex:1; max-width:320px; }
.em-search i { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:var(--c-text-muted); font-size:.85rem; pointer-events:none; }
.em-search input { width:100%; padding:.55rem .85rem .55rem 2.2rem; border:1.5px solid var(--c-border); border-radius:var(--radius-md); background:var(--c-surface); color:var(--c-text); font-size:var(--text-sm); font-family:var(--font-sans); transition:border-color var(--duration-fast) var(--ease-out),box-shadow var(--duration-fast) var(--ease-out); }
.em-search input:focus { outline:none; border-color:var(--c-brand); box-shadow:0 0 0 3px rgba(var(--c-brand-rgb),.1); }
.em-toolbar-right { display:flex; gap:.4rem; align-items:center; }
.em-filter-pill { padding:.4rem .75rem; border:1px solid var(--c-border); border-radius:var(--radius-full); background:var(--c-surface); color:var(--c-text-secondary); font-size:.73rem; font-weight:600; cursor:pointer; transition:all var(--duration-fast) var(--ease-out); font-family:var(--font-sans); }
.em-filter-pill:hover,.em-filter-pill.active { border-color:var(--c-brand); color:var(--c-brand); background:var(--c-brand-ghost); }

/* Employee Table */
.em-table-wrap { border:1px solid var(--c-border); border-radius:var(--radius-lg); overflow:hidden; }
.em-table-wrap table { width:100%; border-collapse:collapse; border-spacing:0; font-size:var(--text-sm); }
.em-table-wrap thead th { background:var(--c-surface-sunken); color:var(--c-text-secondary); font-weight:600; font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; padding:.7rem 1rem; text-align:left; border-bottom:1px solid var(--c-border); white-space:nowrap; }
.em-table-wrap tbody td { padding:.7rem 1rem; border-bottom:1px solid var(--c-border-light); color:var(--c-text); vertical-align:middle; }
.em-table-wrap tbody tr:last-child td{border-bottom:none}
.em-table-wrap tbody tr { transition:background var(--duration-fast) var(--ease-out); }
.em-table-wrap tbody tr:hover{background:var(--c-brand-ghost)}
.em-table-wrap tbody tr.on-leave{background:rgba(245,158,11,.04)} .em-table-wrap tbody tr.on-leave:hover{background:rgba(245,158,11,.08)}

/* Employee cell */
.emp-cell { display:flex; align-items:center; gap:.65rem; }
.emp-avatar { width:36px; height:36px; border-radius:var(--radius-full); display:grid; place-items:center; font-size:.8rem; font-weight:700; color:white; flex-shrink:0; text-transform:uppercase; }
.emp-avatar.pharmacist{background:#3b82f6} .emp-avatar.cashier{background:#10b981} .emp-avatar.staff{background:#f59e0b} .emp-avatar.admin{background:#8b5cf6} .emp-avatar.manager{background:#ec4899}
.emp-info{display:flex;flex-direction:column;min-width:0}
.emp-name { font-weight:600; color:var(--c-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.emp-id { font-size:.7rem; color:var(--c-text-muted); }

/* Role & Status Badges */
.role-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .6rem; border-radius:var(--radius-full); font-size:.73rem; font-weight:600; text-transform:capitalize; }
.role-badge i{font-size:.65rem}
.role-badge.pharmacist{background:rgba(59,130,246,.1);color:#3b82f6} .role-badge.cashier{background:rgba(16,185,129,.1);color:#10b981} .role-badge.staff{background:rgba(245,158,11,.1);color:#d97706} .role-badge.admin{background:rgba(139,92,246,.1);color:#8b5cf6} .role-badge.manager{background:rgba(236,72,153,.1);color:#ec4899}
[data-theme="dark"] .role-badge.pharmacist{background:rgba(59,130,246,.15);color:#60a5fa} [data-theme="dark"] .role-badge.cashier{background:rgba(16,185,129,.15);color:#34d399} [data-theme="dark"] .role-badge.staff{background:rgba(245,158,11,.15);color:#fbbf24} [data-theme="dark"] .role-badge.admin{background:rgba(139,92,246,.15);color:#a78bfa} [data-theme="dark"] .role-badge.manager{background:rgba(236,72,153,.15);color:#f472b6}
.status-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .55rem; border-radius:var(--radius-full); font-size:.73rem; font-weight:600; }
.status-badge i{font-size:.6rem}
.status-badge.active{background:rgba(16,185,129,.1);color:#10b981} .status-badge.on-leave{background:rgba(245,158,11,.1);color:#d97706}
[data-theme="dark"] .status-badge.active{background:rgba(16,185,129,.15);color:#34d399} [data-theme="dark"] .status-badge.on-leave{background:rgba(245,158,11,.15);color:#fbbf24}
.shift-time { font-size:.8rem; color:var(--c-text-secondary); display:inline-flex; align-items:center; gap:.35rem; }
.shift-time i{font-size:.7rem;color:var(--c-text-muted)} .date-text{font-size:.8rem;color:var(--c-text-muted)}

/* Action buttons */
.action-buttons { display:flex; gap:.35rem; align-items:center; }
.action-btn-edit,.action-btn-leave,.action-btn-delete { width:32px; height:32px; border-radius:var(--radius-md); display:grid; place-items:center; border:1px solid var(--c-border); background:var(--c-surface); cursor:pointer; font-size:.8rem; transition:all var(--duration-fast) var(--ease-out); }
.action-btn-edit{color:var(--c-brand)} .action-btn-edit:hover{background:var(--c-brand-ghost);border-color:var(--c-brand);transform:translate3d(0,-1px,0)}
.action-btn-leave{color:#f59e0b} .action-btn-leave:hover{background:rgba(245,158,11,.08);border-color:#f59e0b;transform:translate3d(0,-1px,0)}
.action-btn-delete{color:#ef4444} .action-btn-delete:hover{background:rgba(239,68,68,.08);border-color:#ef4444;transform:translate3d(0,-1px,0)}
.action-btn-edit:active,.action-btn-leave:active,.action-btn-delete:active{transform:scale(.92);transition-duration:80ms}

/* Edit Modal */
.em-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); z-index:10000; display:none; align-items:center; justify-content:center; padding:1rem; opacity:0; transition:opacity .2s var(--ease-out); }
.em-modal-overlay.active{display:flex;opacity:1}
.em-modal { background:var(--c-surface); border-radius:var(--radius-xl); box-shadow:var(--shadow-2xl); max-width:500px; width:100%; overflow:hidden; transform:scale(.95) translate3d(0,8px,0); transition:transform .2s var(--ease-spring); }
.em-modal-overlay.active .em-modal{transform:scale(1) translate3d(0,0,0)}
.em-modal-header { padding:1rem 1.25rem; border-bottom:1px solid var(--c-border); display:flex; align-items:center; justify-content:space-between; }
.em-modal-header h3 { font-size:var(--text-lg); font-weight:700; margin:0; color:var(--c-text); display:flex; align-items:center; gap:.4rem; }
.em-modal-close { width:32px; height:32px; border-radius:var(--radius-md); border:none; background:var(--c-surface-sunken); color:var(--c-text-muted); cursor:pointer; display:grid; place-items:center; font-size:.85rem; transition:all var(--duration-fast) var(--ease-out); }
.em-modal-close:hover{background:rgba(239,68,68,.1);color:#ef4444}
.em-modal-body{padding:1.25rem}
.em-modal-footer { padding:1rem 1.25rem; border-top:1px solid var(--c-border); display:flex; justify-content:flex-end; gap:.5rem; }
.em-btn-cancel { padding:.55rem 1rem; border:1px solid var(--c-border); border-radius:var(--radius-md); background:var(--c-surface); color:var(--c-text-secondary); font-weight:600; font-size:var(--text-sm); cursor:pointer; font-family:var(--font-sans); transition:all var(--duration-fast) var(--ease-out); }
.em-btn-cancel:hover{border-color:var(--c-text-muted);color:var(--c-text)}
.em-btn-save { padding:.55rem 1.15rem; border:none; border-radius:var(--radius-md); background:var(--c-brand); color:white; font-weight:600; font-size:var(--text-sm); font-family:var(--font-sans); cursor:pointer; transition:all var(--duration-fast) var(--ease-out); display:inline-flex; align-items:center; gap:.35rem; }
.em-btn-save:hover{background:var(--c-brand-dark);transform:translate3d(0,-1px,0)} .em-btn-save:active{transform:scale(.98);transition-duration:80ms}

/* Log Filters */
.em-log-filters { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.25rem; align-items:flex-end; }
.em-filter-group { display:flex; flex-direction:column; gap:.25rem; }
.em-filter-group label { font-size:.68rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--c-text-muted); }
.em-filter-group input,.em-filter-group select { padding:.45rem .7rem; border:1.5px solid var(--c-border); border-radius:var(--radius-md); background:var(--c-surface); color:var(--c-text); font-size:var(--text-sm); font-family:var(--font-sans); transition:border-color var(--duration-fast) var(--ease-out); }
.em-filter-group input:focus,.em-filter-group select:focus{outline:none;border-color:var(--c-brand)}
.em-filter-btn { padding:.45rem .85rem; border:none; border-radius:var(--radius-md); background:var(--c-brand); color:white; font-weight:600; font-size:var(--text-sm); cursor:pointer; font-family:var(--font-sans); transition:all var(--duration-fast) var(--ease-out); display:inline-flex; align-items:center; gap:.35rem; }
.em-filter-btn:hover{background:var(--c-brand-dark);transform:translate3d(0,-1px,0)}

/* Log Entries */
.em-log-entry { padding:.85rem 1rem; border-bottom:1px solid var(--c-border-light); display:flex; gap:.75rem; align-items:flex-start; transition:background var(--duration-fast) var(--ease-out); }
.em-log-entry:hover{background:var(--c-brand-ghost)} .em-log-entry:last-child{border-bottom:none}
.em-log-icon { width:36px; height:36px; border-radius:var(--radius-md); display:grid; place-items:center; font-size:.9rem; flex-shrink:0; }
.em-log-icon.login{background:rgba(16,185,129,.1);color:#10b981} .em-log-icon.logout{background:rgba(107,114,128,.1);color:#6b7280} .em-log-icon.create{background:rgba(var(--c-brand-rgb),.1);color:var(--c-brand)} .em-log-icon.update,.em-log-icon.toggle{background:rgba(245,158,11,.1);color:#f59e0b} .em-log-icon.delete{background:rgba(239,68,68,.1);color:#ef4444}
.em-log-body{flex:1;min-width:0} .em-log-title{font-weight:600;font-size:var(--text-sm);color:var(--c-text);margin-bottom:.15rem}
.em-log-meta { font-size:.73rem; color:var(--c-text-muted); display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }
.em-log-changes { margin-top:.4rem; padding:.4rem .7rem; background:var(--c-surface-sunken); border:1px solid var(--c-border-light); border-radius:var(--radius-sm); font-size:.73rem; font-family:var(--font-mono); line-height:1.6; }
.em-log-time { font-size:.73rem; color:var(--c-text-muted); white-space:nowrap; text-align:right; min-width:110px; flex-shrink:0; }
.em-session-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.15rem .45rem; border-radius:var(--radius-full); font-size:.68rem; font-weight:700; text-transform:uppercase; }
.em-session-badge.active{background:rgba(16,185,129,.1);color:#10b981} .em-session-badge.logged_out,.em-session-badge.logged-out{background:rgba(107,114,128,.1);color:#6b7280} .em-session-badge.expired{background:rgba(245,158,11,.1);color:#d97706} .em-session-badge.forced{background:rgba(239,68,68,.1);color:#ef4444}
.em-duration-badge { display:inline-flex; align-items:center; gap:.2rem; padding:.15rem .45rem; background:rgba(var(--c-brand-rgb),.08); border-radius:var(--radius-full); font-size:.7rem; font-weight:600; color:var(--c-brand); }

/* Empty & Toast & Loading */
.em-empty { text-align:center; padding:3rem 1.5rem; color:var(--c-text-muted); }
.em-empty i{font-size:2.5rem;margin-bottom:.75rem;opacity:.4;display:block} .em-empty p{font-size:var(--text-sm);margin:.2rem 0} .em-empty p:first-of-type{font-weight:600;color:var(--c-text-secondary);font-size:var(--text-base)}
.em-toast { position:fixed; top:72px; right:1rem; padding:.75rem 1.15rem; border-radius:var(--radius-lg); color:white; font-weight:600; font-size:var(--text-sm); z-index:10001; box-shadow:var(--shadow-lg); transform:translate3d(120%,0,0); transition:transform .3s var(--ease-spring); max-width:360px; display:flex; align-items:center; gap:.5rem; }
.em-toast.show{transform:translate3d(0,0,0)} .em-toast.success{background:#10b981} .em-toast.error{background:#ef4444}
.em-loading { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); backdrop-filter:blur(2px); z-index:10002; justify-content:center; align-items:center; }
.em-loading.active{display:flex}
.em-spinner { width:36px; height:36px; border:3px solid rgba(255,255,255,.3); border-top-color:white; border-radius:50%; animation:ds-spin .7s linear infinite; }

/* Responsive */
@media(max-width:768px){
  main{padding:.75rem 1rem 1.5rem!important}
  .em-stats-grid{grid-template-columns:repeat(2,1fr);gap:.6rem} .em-stat-card{padding:.85rem} .em-stat-value{font-size:var(--text-xl)}
  .em-form-grid{grid-template-columns:1fr} .em-log-filters{flex-direction:column}
  .em-toolbar{flex-direction:column} .em-search{max-width:none}
  .action-buttons{gap:.25rem} .page-header{flex-direction:column;align-items:flex-start}
  .em-tabs-header{overflow-x:auto} .em-tab-btn{padding:.75rem 1rem;font-size:.8rem}
  .em-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch} .em-table-wrap table{min-width:650px}
  .em-log-time{display:none}
}
@media(max-width:480px){
  .em-stats-grid{grid-template-columns:1fr 1fr;gap:.5rem} .em-stat-icon{width:38px;height:38px;font-size:1rem} .emp-avatar{width:32px;height:32px;font-size:.7rem}
}
</style>
</head>
<body>
<?php 
$page_title = 'Employee Management';
include 'header-component.php'; 
?>

<main>
  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-left">
      <h1><i class="fas fa-users" style="color:var(--c-brand);font-size:1.15rem;"></i> Employee Management</h1>
      <p>Manage your team, track schedules, and monitor activity</p>
    </div>
    <div class="page-header-right">
      <button class="header-action-btn" onclick="exportCSV()" title="Export to CSV"><i class="fas fa-download"></i> Export</button>
    </div>
  </div>

  <!-- Stats -->
  <div class="em-stats-grid">
    <div class="em-stat-card"><div class="em-stat-icon blue"><i class="fas fa-users"></i></div><div class="em-stat-body"><div class="em-stat-value" data-count="<?= $employeeCount ?>"><?= $employeeCount ?></div><div class="em-stat-label">Total Employees</div></div></div>
    <div class="em-stat-card"><div class="em-stat-icon green"><i class="fas fa-user-check"></i></div><div class="em-stat-body"><div class="em-stat-value" data-count="<?= $activeCount ?>"><?= $activeCount ?></div><div class="em-stat-label">Active</div></div></div>
    <div class="em-stat-card"><div class="em-stat-icon amber"><i class="fas fa-user-clock"></i></div><div class="em-stat-body"><div class="em-stat-value" data-count="<?= $onLeaveCount ?>"><?= $onLeaveCount ?></div><div class="em-stat-label">On Leave</div></div></div>
    <div class="em-stat-card"><div class="em-stat-icon purple"><i class="fas fa-right-to-bracket"></i></div><div class="em-stat-body"><div class="em-stat-value" data-count="<?= $stats['today_logins'] ?>"><?= $stats['today_logins'] ?></div><div class="em-stat-label">Today's Logins</div></div></div>
    <div class="em-stat-card"><div class="em-stat-icon pink"><i class="fas fa-signal"></i></div><div class="em-stat-body"><div class="em-stat-value" data-count="<?= $stats['active_sessions'] ?>"><?= $stats['active_sessions'] ?></div><div class="em-stat-label">Active Sessions</div></div></div>
    <div class="em-stat-card"><div class="em-stat-icon cyan"><i class="fas fa-pen-to-square"></i></div><div class="em-stat-body"><div class="em-stat-value" data-count="<?= $stats['today_changes'] ?>"><?= $stats['today_changes'] ?></div><div class="em-stat-label">Today's Changes</div></div></div>
  </div>

  <!-- Tabs -->
  <div class="em-tabs">
    <div class="em-tabs-header">
      <button class="em-tab-btn active" data-tab="employees"><i class="fas fa-users"></i> Employees <span class="em-tab-badge"><?= $employeeCount ?></span></button>
      <button class="em-tab-btn" data-tab="login-logs"><i class="fas fa-right-to-bracket"></i> Login Sessions</button>
      <button class="em-tab-btn" data-tab="change-logs"><i class="fas fa-clock-rotate-left"></i> Change History</button>
    </div>

    <!-- Tab: Employees -->
    <div class="em-tab-content active" id="tab-employees">
      <div class="em-form-card">
        <div class="em-form-header"><i class="fas fa-user-plus"></i> Add New Employee</div>
        <div class="em-form-body">
          <form id="addEmployeeForm" method="POST">
            <div class="em-form-grid">
              <div class="em-form-group"><label for="name">Full Name</label><input type="text" id="name" name="name" required maxlength="100" placeholder="e.g. Juan Dela Cruz" autocomplete="off"></div>
              <div class="em-form-group"><label for="role">Role</label><select id="role" name="role" required><option value="">Select role…</option><option value="pharmacist">Pharmacist</option><option value="cashier">Cashier</option><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
              <div class="em-form-group"><label for="shift_start">Shift Start</label><input type="time" id="shift_start" name="shift_start" required></div>
              <div class="em-form-group"><label for="shift_end">Shift End</label><input type="time" id="shift_end" name="shift_end" required></div>
            </div>
            <button type="submit" name="add_employee" class="em-form-submit"><i class="fas fa-plus"></i> Add Employee</button>
          </form>
        </div>
      </div>

      <div class="em-toolbar">
        <div class="em-search"><i class="fas fa-magnifying-glass"></i><input type="text" id="empSearchInput" placeholder="Search employees…" autocomplete="off"></div>
        <div class="em-toolbar-right">
          <button class="em-filter-pill active" data-filter="all">All</button>
          <button class="em-filter-pill" data-filter="active">Active</button>
          <button class="em-filter-pill" data-filter="on-leave">On Leave</button>
        </div>
      </div>

      <div class="em-table-wrap" id="employeeTableContainer">
        <?php echo render_employee_table($conn); ?>
      </div>
    </div>

    <!-- Tab: Login Sessions -->
    <div class="em-tab-content" id="tab-login-logs">
      <div class="em-log-filters">
        <div class="em-filter-group"><label>User</label><select id="login-filter-user"><option value="">All Users</option></select></div>
        <div class="em-filter-group"><label>From</label><input type="date" id="login-filter-from"></div>
        <div class="em-filter-group"><label>To</label><input type="date" id="login-filter-to"></div>
        <div class="em-filter-group"><label>Status</label><select id="login-filter-status"><option value="">All</option><option value="active">Active</option><option value="logged_out">Logged Out</option><option value="expired">Expired</option></select></div>
        <button class="em-filter-btn" onclick="loadLoginSessions()"><i class="fas fa-magnifying-glass"></i> Filter</button>
      </div>
      <div id="login-sessions-list">
        <div class="em-empty"><i class="fas fa-right-to-bracket"></i><p>No sessions loaded</p><p>Click Filter to load login/logout sessions</p></div>
      </div>
    </div>

    <!-- Tab: Change History -->
    <div class="em-tab-content" id="tab-change-logs">
      <div class="em-log-filters">
        <div class="em-filter-group"><label>User</label><select id="change-filter-user"><option value="">All Users</option></select></div>
        <div class="em-filter-group"><label>Module</label><select id="change-filter-module"><option value="">All Modules</option><option value="Employee Management">Employee Mgmt</option><option value="Inventory">Inventory</option><option value="POS">POS</option><option value="Authentication">Auth</option><option value="User Management">User Mgmt</option></select></div>
        <div class="em-filter-group"><label>Action</label><select id="change-filter-action"><option value="">All Actions</option><option value="create">Create</option><option value="update">Update</option><option value="delete">Delete</option><option value="toggle">Toggle</option></select></div>
        <div class="em-filter-group"><label>From</label><input type="date" id="change-filter-from"></div>
        <div class="em-filter-group"><label>To</label><input type="date" id="change-filter-to"></div>
        <button class="em-filter-btn" onclick="loadChangeLogs()"><i class="fas fa-magnifying-glass"></i> Filter</button>
      </div>
      <div id="change-logs-list">
        <div class="em-empty"><i class="fas fa-clock-rotate-left"></i><p>No changes loaded</p><p>Click Filter to view change history</p></div>
      </div>
    </div>
  </div>
</main>

<!-- Edit Employee Modal -->
<div class="em-modal-overlay" id="editModalOverlay">
  <div class="em-modal">
    <div class="em-modal-header">
      <h3><i class="fas fa-user-pen" style="color:var(--c-brand)"></i> Edit Employee</h3>
      <button class="em-modal-close" onclick="closeEditModal()"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="em-modal-body">
      <form id="editEmployeeForm">
        <input type="hidden" id="edit_id" name="edit_id">
        <div class="em-form-grid" style="grid-template-columns:1fr 1fr">
          <div class="em-form-group" style="grid-column:1/-1"><label for="edit_name">Full Name</label><input type="text" id="edit_name" name="edit_name" required maxlength="100"></div>
          <div class="em-form-group"><label for="edit_role">Role</label><select id="edit_role" name="edit_role" required><option value="pharmacist">Pharmacist</option><option value="cashier">Cashier</option><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
          <div class="em-form-group"><label for="edit_shift_start">Shift Start</label><input type="time" id="edit_shift_start" name="edit_shift_start" required></div>
          <div class="em-form-group"><label for="edit_shift_end">Shift End</label><input type="time" id="edit_shift_end" name="edit_shift_end" required></div>
        </div>
      </form>
    </div>
    <div class="em-modal-footer">
      <button class="em-btn-cancel" onclick="closeEditModal()">Cancel</button>
      <button class="em-btn-save" onclick="saveEdit()"><i class="fas fa-check"></i> Save Changes</button>
    </div>
  </div>
</div>

<div class="em-loading" id="emLoading"><div class="em-spinner"></div></div>

<?php include 'pills-background.php'; ?>

<script src="theme.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  /* ── Tabs ────────────────────────────────────────────── */
  document.querySelectorAll('.em-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.em-tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.em-tab-content').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('tab-' + this.dataset.tab).classList.add('active');
      if (this.dataset.tab === 'login-logs') loadLoginSessions();
      if (this.dataset.tab === 'change-logs') loadChangeLogs();
    });
  });

  /* ── Helpers ──────────────────────────────────────────── */
  function showLoading() { document.getElementById('emLoading').classList.add('active'); }
  function hideLoading() { document.getElementById('emLoading').classList.remove('active'); }

  function showToast(message, type) {
    type = type || 'success';
    var t = document.createElement('div');
    t.className = 'em-toast ' + type;
    t.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'circle-check' : 'circle-exclamation') + '"></i> ' + message;
    document.body.appendChild(t);
    requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.classList.add('show'); }); });
    setTimeout(function(){ t.classList.remove('show'); setTimeout(function(){ t.remove(); }, 300); }, 3500);
  }

  function formatDate(s) {
    if (!s) return '—';
    var d = new Date(s);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
           ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  }

  function formatDuration(min) {
    if (!min && min !== 0) return '—';
    if (min < 60) return min + 'm';
    return Math.floor(min / 60) + 'h ' + (min % 60) + 'm';
  }

  /* ── Animated Counters ────────────────────────────────── */
  document.querySelectorAll('.em-stat-value[data-count]').forEach(function(el) {
    var target = parseInt(el.dataset.count) || 0;
    if (target === 0) return;
    var current = 0;
    var step = Math.max(1, Math.ceil(target / 20));
    var iv = setInterval(function() {
      current = Math.min(current + step, target);
      el.textContent = current;
      if (current >= target) clearInterval(iv);
    }, 30);
  });

  /* ── Load users for filters ───────────────────────────── */
  fetch('employee-management.php?action=get_users')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (!data.success) return;
      ['login-filter-user', 'change-filter-user'].forEach(function(id) {
        var sel = document.getElementById(id);
        data.data.forEach(function(u) {
          var opt = document.createElement('option');
          opt.value = u.user_id;
          opt.textContent = u.full_name || u.username;
          sel.appendChild(opt);
        });
      });
    }).catch(function(){});

  /* ── Employee Search & Filter ──────────────────────────── */
  var searchInput = document.getElementById('empSearchInput');
  if (searchInput) searchInput.addEventListener('input', filterEmployees);

  document.querySelectorAll('.em-filter-pill').forEach(function(pill) {
    pill.addEventListener('click', function() {
      document.querySelectorAll('.em-filter-pill').forEach(function(p){ p.classList.remove('active'); });
      this.classList.add('active');
      filterEmployees();
    });
  });

  function filterEmployees() {
    var query = (searchInput ? searchInput.value : '').toLowerCase().trim();
    var activePill = document.querySelector('.em-filter-pill.active');
    var filter = activePill ? activePill.dataset.filter : 'all';
    var rows = document.querySelectorAll('#employeeTableContainer tbody tr[data-name]');
    rows.forEach(function(row) {
      var name = (row.dataset.name || '').toLowerCase();
      var role = (row.dataset.role || '').toLowerCase();
      var isOnLeave = row.classList.contains('on-leave');
      var matchesSearch = !query || name.indexOf(query) !== -1 || role.indexOf(query) !== -1;
      var matchesFilter = filter === 'all' || (filter === 'active' && !isOnLeave) || (filter === 'on-leave' && isOnLeave);
      row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
    });
  }

  /* ── Add Employee (AJAX) ──────────────────────────────── */
  document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    showLoading();
    var fd = new FormData(this);
    fd.append('add_employee', '1');
    var form = this;
    fetch('employee-management.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data.success) {
          document.getElementById('employeeTableContainer').innerHTML = data.html;
          showToast(data.message);
          form.reset();
          attachEventListeners();
        } else showToast(data.message || 'Error adding employee', 'error');
      })
      .catch(function(){ showToast('Network error', 'error'); })
      .finally(function(){ hideLoading(); });
  });

  /* ── Delete Employee ──────────────────────────────────── */
  function removeEmployee(btn) {
    customConfirm('Remove Employee', 'This will permanently remove this employee. Continue?', 'danger', { confirmText: 'Remove', cancelText: 'Cancel' })
      .then(function(ok) {
        if (!ok) return;
        showLoading();
        var fd = new FormData();
        fd.append('remove_employee', btn.value);
        fetch('employee-management.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function(r){ return r.json(); })
          .then(function(data) {
            if (data.success) {
              document.getElementById('employeeTableContainer').innerHTML = data.html;
              showToast(data.message);
              attachEventListeners();
            } else showToast(data.message || 'Error', 'error');
          })
          .catch(function(){ showToast('Network error', 'error'); })
          .finally(function(){ hideLoading(); });
      });
  }

  /* ── Toggle Leave ─────────────────────────────────────── */
  function toggleLeave(btn) {
    showLoading();
    var fd = new FormData();
    fd.append('toggle_leave', btn.value);
    fetch('employee-management.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data.success) {
          document.getElementById('employeeTableContainer').innerHTML = data.html;
          showToast(data.message);
          attachEventListeners();
        } else showToast(data.message || 'Error', 'error');
      })
      .catch(function(){ showToast('Network error', 'error'); })
      .finally(function(){ hideLoading(); });
  }

  /* ── Edit Employee Modal ──────────────────────────────── */
  window.openEditModal = function(id, name, role, shiftStart, shiftEnd) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_shift_start').value = shiftStart;
    document.getElementById('edit_shift_end').value = shiftEnd;
    var overlay = document.getElementById('editModalOverlay');
    overlay.style.display = 'flex';
    requestAnimationFrame(function(){ overlay.classList.add('active'); });
    document.getElementById('edit_name').focus();
  };

  window.closeEditModal = function() {
    var overlay = document.getElementById('editModalOverlay');
    overlay.classList.remove('active');
    setTimeout(function(){ overlay.style.display = 'none'; }, 200);
  };

  window.saveEdit = function() {
    var id = document.getElementById('edit_id').value;
    var name = document.getElementById('edit_name').value.trim();
    var role = document.getElementById('edit_role').value;
    var shiftStart = document.getElementById('edit_shift_start').value;
    var shiftEnd = document.getElementById('edit_shift_end').value;
    if (!name || !role || !shiftStart || !shiftEnd) { showToast('All fields are required', 'error'); return; }
    showLoading();
    closeEditModal();
    var fd = new FormData();
    fd.append('edit_employee', '1');
    fd.append('edit_id', id);
    fd.append('edit_name', name);
    fd.append('edit_role', role);
    fd.append('edit_shift_start', shiftStart);
    fd.append('edit_shift_end', shiftEnd);
    fetch('employee-management.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data.success) {
          document.getElementById('employeeTableContainer').innerHTML = data.html;
          showToast(data.message);
          attachEventListeners();
        } else showToast(data.message || 'Error updating', 'error');
      })
      .catch(function(){ showToast('Network error', 'error'); })
      .finally(function(){ hideLoading(); });
  };

  // Close modal on overlay click or Escape
  document.getElementById('editModalOverlay').addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeEditModal(); });

  /* ── Export CSV ─────────────────────────────────────────── */
  window.exportCSV = function() {
    var rows = document.querySelectorAll('#employeeTableContainer table tbody tr[data-name]');
    if (!rows.length) { showToast('No employees to export', 'error'); return; }
    var csv = 'ID,Name,Role,Shift,Status,Date Added\n';
    rows.forEach(function(row) {
      var cells = row.querySelectorAll('td');
      var id = (cells[0].querySelector('.emp-id') || {}).textContent || '';
      id = id.replace('#','');
      var name = (cells[0].querySelector('.emp-name') || {}).textContent || '';
      var role = (cells[1] || {}).textContent || '';
      var shift = ((cells[2] || {}).textContent || '').replace(/\s+/g,' ').trim();
      var status = ((cells[3] || {}).textContent || '').trim();
      var date = ((cells[4] || {}).textContent || '').trim();
      csv += '"' + id + '","' + name.trim() + '","' + role.trim() + '","' + shift + '","' + status + '","' + date + '"\n';
    });
    var blob = new Blob([csv], { type: 'text/csv' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = 'employees_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click(); URL.revokeObjectURL(url);
    showToast('Exported to CSV');
  };

  /* ── Attach Event Listeners ───────────────────────────── */
  function attachEventListeners() {
    document.querySelectorAll('.action-btn-delete').forEach(function(b) {
      b.onclick = function(e) { e.preventDefault(); removeEmployee(this); };
    });
    document.querySelectorAll('.action-btn-leave').forEach(function(b) {
      b.onclick = function(e) { e.preventDefault(); toggleLeave(this); };
    });
  }
  attachEventListeners();

  /* ── Login Sessions ────────────────────────────────────── */
  window.loadLoginSessions = function() {
    var params = new URLSearchParams({ action: 'get_login_sessions' });
    [['user_id','login-filter-user'],['date_from','login-filter-from'],['date_to','login-filter-to'],['status','login-filter-status']].forEach(function(pair) {
      var val = document.getElementById(pair[1]).value;
      if (val) params.set(pair[0], val);
    });
    var container = document.getElementById('login-sessions-list');
    container.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="em-spinner" style="margin:0 auto;border-color:rgba(var(--c-brand-rgb),.2);border-top-color:var(--c-brand);"></div></div>';
    fetch('employee-management.php?' + params.toString())
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (!data.success || !data.data || !data.data.length) {
          container.innerHTML = '<div class="em-empty"><i class="fas fa-inbox"></i><p>No sessions found</p><p>Try adjusting your filters</p></div>';
          return;
        }
        container.innerHTML = data.data.map(function(s) {
          return '<div class="em-log-entry">' +
            '<div class="em-log-icon ' + (s.status === 'active' ? 'login' : 'logout') + '">' +
              '<i class="fas ' + (s.status === 'active' ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket') + '"></i>' +
            '</div>' +
            '<div class="em-log-body">' +
              '<div class="em-log-title">' + (s.full_name || s.username || '—') + '</div>' +
              '<div class="em-log-meta">' +
                '<span><i class="fas fa-right-to-bracket"></i> ' + formatDate(s.login_time) + '</span>' +
                '<span><i class="fas fa-right-from-bracket"></i> ' + (s.logout_time ? formatDate(s.logout_time) : '—') + '</span>' +
                '<span class="em-duration-badge"><i class="far fa-clock"></i> ' + formatDuration(s.duration_minutes) + '</span>' +
                '<span class="em-session-badge ' + s.status + '">' + (s.status || '').replace('_', ' ') + '</span>' +
              '</div>' +
              (s.ip_address ? '<div class="em-log-meta" style="margin-top:.2rem"><span><i class="fas fa-globe"></i> ' + s.ip_address + '</span></div>' : '') +
            '</div>' +
            '<div class="em-log-time">' + formatDate(s.login_time) + '</div>' +
          '</div>';
        }).join('');
      })
      .catch(function(){ container.innerHTML = '<div class="em-empty"><p>Error loading sessions</p></div>'; });
  };

  /* ── Change Logs ───────────────────────────────────────── */
  window.loadChangeLogs = function() {
    var params = new URLSearchParams({ action: 'get_change_logs' });
    [['user_id','change-filter-user'],['module','change-filter-module'],['action_type','change-filter-action'],['date_from','change-filter-from'],['date_to','change-filter-to']].forEach(function(pair) {
      var val = document.getElementById(pair[1]).value;
      if (val) params.set(pair[0], val);
    });
    var container = document.getElementById('change-logs-list');
    container.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="em-spinner" style="margin:0 auto;border-color:rgba(var(--c-brand-rgb),.2);border-top-color:var(--c-brand);"></div></div>';
    var icons = { create: 'fa-plus', update: 'fa-pen', delete: 'fa-trash-can', toggle: 'fa-arrows-rotate', import: 'fa-file-import', export: 'fa-file-export' };
    fetch('employee-management.php?' + params.toString())
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (!data.success || !data.data || !data.data.length) {
          container.innerHTML = '<div class="em-empty"><i class="fas fa-inbox"></i><p>No changes found</p><p>Try adjusting your filters</p></div>';
          return;
        }
        container.innerHTML = data.data.map(function(log) {
          var icon = icons[log.action_type] || 'fa-circle-info';
          var changesHtml = '';
          var oldV = log.old_values, newV = log.new_values;
          try { if (typeof oldV === 'string') oldV = JSON.parse(oldV); } catch(e) {}
          try { if (typeof newV === 'string') newV = JSON.parse(newV); } catch(e) {}
          var lines = [];
          if (oldV && typeof oldV === 'object') {
            Object.keys(oldV).forEach(function(k) {
              var nv = newV && newV[k] !== undefined ? newV[k] : '—';
              lines.push('<span style="color:var(--c-text-muted)">' + k + ':</span> <span style="color:#ef4444;text-decoration:line-through">' + oldV[k] + '</span> → <span style="color:#10b981">' + nv + '</span>');
            });
          } else if (newV && typeof newV === 'object') {
            Object.keys(newV).forEach(function(k) {
              lines.push('<span style="color:var(--c-text-muted)">' + k + ':</span> <span style="color:#10b981">' + newV[k] + '</span>');
            });
          }
          if (lines.length) changesHtml = '<div class="em-log-changes">' + lines.join('<br>') + '</div>';
          return '<div class="em-log-entry">' +
            '<div class="em-log-icon ' + log.action_type + '"><i class="fas ' + icon + '"></i></div>' +
            '<div class="em-log-body">' +
              '<div class="em-log-title">' + (log.description || '—') + '</div>' +
              '<div class="em-log-meta">' +
                '<span><i class="fas fa-user"></i> ' + (log.full_name || log.username || 'System') + '</span>' +
                '<span><i class="fas fa-cube"></i> ' + log.module + '</span>' +
                '<span style="text-transform:capitalize"><i class="fas fa-bolt"></i> ' + log.action_type + '</span>' +
                (log.target_name ? '<span><i class="fas fa-crosshairs"></i> ' + log.target_name + '</span>' : '') +
              '</div>' +
              changesHtml +
            '</div>' +
            '<div class="em-log-time">' + formatDate(log.created_at) + '</div>' +
          '</div>';
        }).join('');
      })
      .catch(function(){ container.innerHTML = '<div class="em-empty"><p>Error loading logs</p></div>'; });
  };
});
</script>
<script src="global-polish.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
