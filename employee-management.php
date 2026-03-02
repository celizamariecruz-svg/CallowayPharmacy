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
    
    ob_start();
    ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Shift Schedule</th>
                <th>Status</th>
                <th>Date Added</th>
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
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= $id ?></td>
                    <td><strong><?= $name ?></strong></td>
                    <td>
                        <span class="role-badge <?= $roleClass ?>"><?= $role ?></span>
                    </td>
                    <td>
                        <span class="shift-time">
                            <?= date('h:i A', strtotime($row['shift_start'])) ?> - 
                            <?= date('h:i A', strtotime($row['shift_end'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['on_leave']): ?>
                            <span class="status-badge on-leave">On Leave</span>
                        <?php else: ?>
                            <span class="status-badge active">Active</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="date-text"><?= date('M d, Y', strtotime($row['created_at'])) ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <form method="POST" class="toggle-leave-form">
                                <button type="submit" name="toggle_leave" value="<?= $id ?>" class="toggle-leave-button">
                                    <?= $row['on_leave'] ? '✅ Mark Active' : '🏖️ Put on Leave' ?>
                                </button>
                            </form>
                            <form method="POST" class="remove-employee-form">
                                <button type="submit" name="remove_employee" value="<?= $id ?>" class="remove-button">
                                    🗑️ Remove
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding: 2rem;">
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                            <p>No employees found</p>
                            <small>Add your first employee using the form above</small>
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
  main {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem 2rem 2rem;
    animation: fadeIn 0.8s ease-out;
  }

  @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

  /* Stats Grid */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.2rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px var(--shadow-color);
    border: 1px solid var(--table-border);
    text-align: center;
    transition: transform 0.15s, box-shadow 0.15s;
    animation: slideUp 0.6s ease-out backwards;
    position: relative;
    overflow: hidden;
  }

  .stat-card:nth-child(1) { animation-delay: 0.05s; border-left: 4px solid #3b82f6; }
  .stat-card:nth-child(2) { animation-delay: 0.1s; border-left: 4px solid #10b981; }
  .stat-card:nth-child(3) { animation-delay: 0.15s; border-left: 4px solid #f59e0b; }
  .stat-card:nth-child(4) { animation-delay: 0.2s; border-left: 4px solid #8b5cf6; }
  .stat-card:nth-child(5) { animation-delay: 0.25s; border-left: 4px solid #ec4899; }
  .stat-card:nth-child(6) { animation-delay: 0.3s; border-left: 4px solid #06b6d4; }

  .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px var(--shadow-color); }
  .stat-card .stat-value { font-size: 2.2rem; font-weight: 800; color: var(--primary-color); }
  .stat-card .stat-label { font-size: 0.85rem; color: var(--text-color); opacity: 0.7; margin-top: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
  .stat-card .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }

  /* Tabs */
  .tabs-container {
    background: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 4px 15px var(--shadow-color);
    border: 1px solid var(--table-border);
    overflow: visible;
    margin-bottom: 2rem;
  }

  .tabs-header {
    display: flex;
    background: var(--header-bg, var(--primary-color));
    overflow-x: auto;
    border-bottom: 2px solid var(--table-border);
    border-radius: 16px 16px 0 0;
  }

  .tab-btn {
    padding: 1rem 1.5rem;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 16px 16px 0 0;
  }

  .tab-btn:hover { color: white; background: rgba(255,255,255,0.1); }
  .tab-btn.active {
    color: white;
    background: rgba(255,255,255,0.15);
    border-radius: 16px 16px 0 0;
  }
  .tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: white;
    border-radius: 3px 3px 0 0;
  }

  .tab-badge {
    background: rgba(255,255,255,0.25);
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 700;
  }

  .tab-content { display: none; padding: 1.5rem; overflow-x: auto; }
  .tab-content.active { display: block; animation: fadeIn 0.4s ease-out; }

  /* Card styles */
  .card {
    background: var(--card-bg);
    border-radius: 15px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    width: 100%;
    margin-bottom: 2rem;
    overflow: hidden;
    border: 1px solid var(--table-border);
    padding: 0;
  }

  .card-header {
    background: var(--primary-color);
    color: white;
    padding: 1.2rem 1.5rem;
    font-weight: 700;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 15px 15px 0 0;
  }

  .card-body { padding: 1.5rem; }

  /* Form grid */
  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1rem;
  }

  .form-group { margin-bottom: 0.5rem; }
  .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.9rem; }
  .form-group input, .form-group select {
    width: 100%;
    padding: 0.7rem 1rem;
    border-radius: 12px;
    border: 1px solid var(--input-border);
    background: var(--card-bg);
    color: var(--text-color);
    font-size: 0.95rem;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
  }
  .form-group input:focus, .form-group select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
  }

  .btn {
    padding: 0.7rem 1.5rem;
    border: none;
    border-radius: 30px;
    background: var(--primary-color);
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.25);
  }
  .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(52, 152, 219, 0.35); }

  /* Table */
  table { width: 100%; border-collapse: separate; border-spacing: 0; }
  thead th {
    background-color: var(--table-header-bg);
    color: var(--text-color);
    padding: 0.9rem 1.2rem;
    font-weight: 700;
    text-align: left;
    font-size: 0.9rem;
    border-bottom: 1px solid var(--table-border);
    position: sticky;
    top: 0;
    z-index: 10;
  }
  thead th:first-child { border-radius: 12px 0 0 0; }
  thead th:last-child { border-radius: 0 12px 0 0; }
  tbody td { padding: 0.9rem 1.2rem; border-bottom: 1px solid var(--table-border); transition: background-color 0.2s ease; }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:last-child td:first-child { border-radius: 0 0 0 12px; }
  tbody tr:last-child td:last-child { border-radius: 0 0 12px 0; }
  tbody tr:hover { background-color: var(--hover-bg); }
  tr.on-leave { background-color: rgba(255, 152, 0, 0.08); }

  .shift-time { font-family: 'Consolas', monospace; font-size: 0.9rem; }
  .date-text { font-size: 0.85rem; opacity: 0.8; }

  .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
  .toggle-leave-button, .remove-button {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.4rem 0.8rem; border: none; border-radius: 20px;
    font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s;
  }
  .toggle-leave-button { background: #f59e0b; color: white; }
  .toggle-leave-button:hover { background: #d97706; transform: translateY(-2px); }
  .remove-button { background: #ef4444; color: white; }
  .remove-button:hover { background: #dc2626; transform: translateY(-2px); }

  .role-badge { display: inline-block; padding: 0.25rem 0.7rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
  .role-badge.pharmacist { background-color: #3498db; color: white; }
  .role-badge.cashier { background-color: #2ecc71; color: white; }
  .role-badge.staff { background-color: #f39c12; color: white; }
  .role-badge.admin { background-color: #8b5cf6; color: white; }
  .role-badge.manager { background-color: #ec4899; color: white; }

  .status-badge { display: inline-block; padding: 0.25rem 0.7rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
  .status-badge.active { background-color: #27ae60; color: white; }
  .status-badge.on-leave { background-color: #f39c12; color: white; }

  .empty-state { display: flex; flex-direction: column; align-items: center; padding: 2rem; opacity: 0.6; }
  .empty-state p { font-size: 1.1rem; font-weight: 600; margin: 0.5rem 0; }

  /* Logs Section */
  .log-filters {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    align-items: flex-end;
  }

  .log-filters .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
  }

  .log-filters label { font-size: 0.8rem; font-weight: 600; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.5px; }
  .log-filters input, .log-filters select {
    padding: 0.5rem 0.8rem;
    border-radius: 8px;
    border: 1px solid var(--input-border);
    background: var(--card-bg);
    color: var(--text-color);
    font-size: 0.85rem;
  }

  .filter-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 12px;
    background: var(--primary-color);
    color: white;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s ease;
  }
  .filter-btn:hover { opacity: 0.9; transform: translateY(-1px); }

  .log-entry {
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--table-border);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    transition: background 0.2s ease;
  }
  .log-entry:hover { background: var(--hover-bg); }
  .log-entry:last-child { border-bottom: none; }

  .log-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
  }

  .log-icon.login { background: rgba(16,185,129,0.15); }
  .log-icon.logout { background: rgba(239,68,68,0.15); }
  .log-icon.create { background: rgba(59,130,246,0.15); }
  .log-icon.update, .log-icon.toggle { background: rgba(245,158,11,0.15); }
  .log-icon.delete { background: rgba(239,68,68,0.15); }

  .log-details { flex: 1; min-width: 0; }
  .log-details .log-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.2rem; }
  .log-details .log-meta { font-size: 0.8rem; opacity: 0.6; display: flex; gap: 1rem; flex-wrap: wrap; }
  .log-details .log-changes { margin-top: 0.5rem; padding: 0.5rem 0.8rem; background: var(--hover-bg); border-radius: 8px; font-size: 0.8rem; font-family: 'Consolas', monospace; }

  .log-time { font-size: 0.8rem; opacity: 0.5; white-space: nowrap; text-align: right; min-width: 130px; }

  .session-status {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
  }
  .session-status.active { background: #10b981; color: white; }
  .session-status.logged_out, .session-status.logged-out { background: #6b7280; color: white; }
  .session-status.expired { background: #f59e0b; color: white; }
  .session-status.forced { background: #ef4444; color: white; }

  .duration-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    background: rgba(59,130,246,0.1);
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--primary-color);
  }

  /* Notification */
  .notification {
    position: fixed; top: 80px; right: 20px;
    padding: 15px 20px; border-radius: 8px; color: white; font-weight: 500;
    z-index: 9999; box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    transform: translateX(150%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    max-width: 350px;
  }
  .notification.show { transform: translateX(0); }
  .notification.success { background: #10b981; }
  .notification.error { background: #ef4444; }

  .loading-spinner { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; }
  .spinner { width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

  .no-logs { text-align: center; padding: 3rem; opacity: 0.5; }
  .no-logs .no-logs-icon { font-size: 3rem; margin-bottom: 1rem; }

  @media (max-width: 768px) {
    main { padding: 0 1rem; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .form-grid { grid-template-columns: 1fr; }
    .log-filters { flex-direction: column; }
    .action-buttons { flex-direction: column; }
    table { display: block; overflow-x: auto; }
    .tabs-header { overflow-x: auto; }
    .tab-btn { padding: 0.8rem 1rem; font-size: 0.85rem; }
  }
</style>
</head>
<body>
<?php 
$page_title = 'Employee Management';
include 'header-component.php'; 
?>

<main>
  <!-- Stats Overview -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-value"><?= $employeeCount ?></div>
      <div class="stat-label">Total Employees</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-value"><?= $activeCount ?></div>
      <div class="stat-label">Active</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🏖️</div>
      <div class="stat-value"><?= $onLeaveCount ?></div>
      <div class="stat-label">On Leave</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🔑</div>
      <div class="stat-value"><?= $stats['today_logins'] ?></div>
      <div class="stat-label">Today's Logins</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🟢</div>
      <div class="stat-value"><?= $stats['active_sessions'] ?></div>
      <div class="stat-label">Active Sessions</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📝</div>
      <div class="stat-value"><?= $stats['today_changes'] ?></div>
      <div class="stat-label">Today's Changes</div>
    </div>
  </div>

  <!-- Tabbed Interface -->
  <div class="tabs-container">
    <div class="tabs-header">
      <button class="tab-btn active" data-tab="employees">
        👥 Employees <span class="tab-badge"><?= $employeeCount ?></span>
      </button>
      <button class="tab-btn" data-tab="login-logs">
        � Login / Logout Logs
      </button>
      <button class="tab-btn" data-tab="change-logs">
        📋 Change History
      </button>
    </div>

    <!-- Tab 1: Employees -->
    <div class="tab-content active" id="tab-employees">
      <!-- Add Employee Form -->
      <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">➕ Add New Employee</div>
        <div class="card-body">
          <form id="addEmployeeForm" method="POST">
            <div class="form-grid">
              <div class="form-group">
                <label for="name">Employee Name</label>
                <input type="text" id="name" name="name" required maxlength="100" placeholder="Enter full name">
              </div>
              <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                  <option value="">Select role</option>
                  <option value="pharmacist">Pharmacist</option>
                  <option value="cashier">Cashier</option>
                  <option value="staff">Staff</option>
                  <option value="manager">Manager</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="form-group">
                <label for="shift_start">Shift Start</label>
                <input type="time" id="shift_start" name="shift_start" required>
              </div>
              <div class="form-group">
                <label for="shift_end">Shift End</label>
                <input type="time" id="shift_end" name="shift_end" required>
              </div>
            </div>
            <button type="submit" name="add_employee" class="btn">➕ Add Employee</button>
          </form>
        </div>
      </div>

      <!-- Employee List -->
      <div class="card">
        <div class="card-header">📋 Employee Roster</div>
        <div class="card-body" id="employeeTableContainer">
          <?php echo render_employee_table($conn); ?>
        </div>
      </div>
    </div>

    <!-- Tab 2: Login/Logout Logs -->
    <div class="tab-content" id="tab-login-logs">
      <div class="log-filters">
        <div class="filter-group">
          <label>User</label>
          <select id="login-filter-user">
            <option value="">All Users</option>
          </select>
        </div>
        <div class="filter-group">
          <label>From</label>
          <input type="date" id="login-filter-from">
        </div>
        <div class="filter-group">
          <label>To</label>
          <input type="date" id="login-filter-to">
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select id="login-filter-status">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="logged_out">Logged Out</option>
            <option value="expired">Expired</option>
          </select>
        </div>
        <button class="filter-btn" onclick="loadLoginSessions()">🔍 Filter</button>
      </div>

      <div id="login-sessions-list">
        <div class="no-logs">
          <div class="no-logs-icon">🔑</div>
          <p>Click "Filter" to load login/logout sessions</p>
        </div>
      </div>
    </div>

    <!-- Tab 3: Change Logs -->
    <div class="tab-content" id="tab-change-logs">
      <div class="log-filters">
        <div class="filter-group">
          <label>User</label>
          <select id="change-filter-user">
            <option value="">All Users</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Module</label>
          <select id="change-filter-module">
            <option value="">All Modules</option>
            <option value="Employee Management">Employee Management</option>
            <option value="Inventory">Inventory</option>
            <option value="POS">POS</option>
            <option value="Authentication">Authentication</option>
            <option value="User Management">User Management</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Action</label>
          <select id="change-filter-action">
            <option value="">All Actions</option>
            <option value="create">Create</option>
            <option value="update">Update</option>
            <option value="delete">Delete</option>
            <option value="toggle">Toggle</option>
          </select>
        </div>
        <div class="filter-group">
          <label>From</label>
          <input type="date" id="change-filter-from">
        </div>
        <div class="filter-group">
          <label>To</label>
          <input type="date" id="change-filter-to">
        </div>
        <button class="filter-btn" onclick="loadChangeLogs()">🔍 Filter</button>
      </div>

      <div id="change-logs-list">
        <div class="no-logs">
          <div class="no-logs-icon">📋</div>
          <p>Click "Filter" to load change history</p>
        </div>
      </div>
    </div>
  </div>
</main>

<div class="loading-spinner"><div class="spinner"></div></div>

<?php include 'footer-component.php'; ?>
<?php include 'pills-background.php'; ?>

<script src="theme.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // -- Tabs --
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('tab-' + this.dataset.tab).classList.add('active');

      // Auto-load data when switching tabs
      if (this.dataset.tab === 'login-logs') loadLoginSessions();
      if (this.dataset.tab === 'change-logs') loadChangeLogs();
    });
  });

  // -- Helpers --
  function showLoading() { document.querySelector('.loading-spinner').style.display = 'flex'; }
  function hideLoading() { document.querySelector('.loading-spinner').style.display = 'none'; }

  function showNotification(message, type = 'success') {
    const n = document.createElement('div');
    n.className = `notification ${type}`;
    n.textContent = message;
    document.body.appendChild(n);
    setTimeout(() => n.classList.add('show'), 10);
    setTimeout(() => { n.classList.remove('show'); setTimeout(() => n.remove(), 400); }, 4000);
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + 
           ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  }

  function formatDuration(min) {
    if (!min && min !== 0) return '—';
    if (min < 60) return min + ' min';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h + 'h ' + m + 'm';
  }

  // -- Load users for filter dropdowns --
  fetch('employee-management.php?action=get_users')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const selects = [document.getElementById('login-filter-user'), document.getElementById('change-filter-user')];
        selects.forEach(sel => {
          data.data.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.user_id;
            opt.textContent = u.full_name || u.username;
            sel.appendChild(opt);
          });
        });
      }
    }).catch(() => {});

  // -- Employee Form (AJAX) --
  document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    showLoading();
    const fd = new FormData(this);
    fd.append('add_employee', '1');

    fetch('employee-management.php', {
      method: 'POST', body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('employeeTableContainer').innerHTML = data.html;
        showNotification(data.message);
        this.reset();
      } else {
        showNotification(data.message || 'Error', 'error');
      }
    })
    .catch(() => showNotification('Error adding employee', 'error'))
    .finally(() => { hideLoading(); attachEventListeners(); });
  });

  async function removeEmployee(btn) {
    const ok = await customConfirm('Remove Employee', 'Are you sure you want to remove this employee?', 'danger', { confirmText: 'Yes, Remove', cancelText: 'Cancel' });
    if (!ok) return false;
    showLoading();
    const fd = new FormData();
    fd.append('remove_employee', btn.value);
    fetch('employee-management.php', {
      method: 'POST', body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('employeeTableContainer').innerHTML = data.html;
        showNotification(data.message);
      } else showNotification(data.message || 'Error', 'error');
    })
    .catch(() => showNotification('Error', 'error'))
    .finally(() => { hideLoading(); attachEventListeners(); });
    return false;
  }

  function toggleLeave(btn) {
    showLoading();
    const fd = new FormData();
    fd.append('toggle_leave', btn.value);
    fetch('employee-management.php', {
      method: 'POST', body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('employeeTableContainer').innerHTML = data.html;
        showNotification(data.message);
      } else showNotification(data.message || 'Error', 'error');
    })
    .catch(() => showNotification('Error', 'error'))
    .finally(() => { hideLoading(); attachEventListeners(); });
    return false;
  }

  function attachEventListeners() {
    document.querySelectorAll('.remove-button').forEach(b => {
      b.onclick = function(e) { e.preventDefault(); return removeEmployee(this); };
    });
    document.querySelectorAll('.toggle-leave-button').forEach(b => {
      b.onclick = function(e) { e.preventDefault(); return toggleLeave(this); };
    });
  }

  attachEventListeners();

  // -- Login Sessions --
  window.loadLoginSessions = function() {
    const params = new URLSearchParams({ action: 'get_login_sessions' });
    const userId = document.getElementById('login-filter-user').value;
    const from = document.getElementById('login-filter-from').value;
    const to = document.getElementById('login-filter-to').value;
    const status = document.getElementById('login-filter-status').value;
    if (userId) params.set('user_id', userId);
    if (from) params.set('date_from', from);
    if (to) params.set('date_to', to);
    if (status) params.set('status', status);

    const container = document.getElementById('login-sessions-list');
    container.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="spinner" style="margin:0 auto;width:30px;height:30px;border-width:3px;"></div></div>';

    fetch('employee-management.php?' + params.toString())
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.data.length) {
          container.innerHTML = '<div class="no-logs"><div class="no-logs-icon">📭</div><p>No login sessions found</p></div>';
          return;
        }

        let html = '';
        data.data.forEach(s => {
          const icon = s.status === 'active' ? '🟢' : (s.status === 'logged_out' ? '🔴' : '⏰');
          html += `
            <div class="log-entry">
              <div class="log-icon ${s.status === 'active' ? 'login' : 'logout'}">${icon}</div>
              <div class="log-details">
                <div class="log-title">${s.full_name || s.username}</div>
                <div class="log-meta">
                  <span>🔑 Login: ${formatDate(s.login_time)}</span>
                  <span>🚪 Logout: ${s.logout_time ? formatDate(s.logout_time) : '—'}</span>
                  <span class="duration-badge">⏱ ${formatDuration(s.duration_minutes)}</span>
                  <span class="session-status ${s.status}">${s.status.replace('_', ' ')}</span>
                </div>
                <div class="log-meta" style="margin-top:0.3rem;">
                  <span>🌐 ${s.ip_address || '—'}</span>
                </div>
              </div>
              <div class="log-time">${formatDate(s.login_time)}</div>
            </div>`;
        });
        container.innerHTML = html;
      })
      .catch(() => {
        container.innerHTML = '<div class="no-logs"><p>Error loading sessions</p></div>';
      });
  };

  // -- Change Logs --
  window.loadChangeLogs = function() {
    const params = new URLSearchParams({ action: 'get_change_logs' });
    const userId = document.getElementById('change-filter-user').value;
    const module = document.getElementById('change-filter-module').value;
    const actionType = document.getElementById('change-filter-action').value;
    const from = document.getElementById('change-filter-from').value;
    const to = document.getElementById('change-filter-to').value;
    if (userId) params.set('user_id', userId);
    if (module) params.set('module', module);
    if (actionType) params.set('action_type', actionType);
    if (from) params.set('date_from', from);
    if (to) params.set('date_to', to);

    const container = document.getElementById('change-logs-list');
    container.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="spinner" style="margin:0 auto;width:30px;height:30px;border-width:3px;"></div></div>';

    fetch('employee-management.php?' + params.toString())
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.data.length) {
          container.innerHTML = '<div class="no-logs"><div class="no-logs-icon">📭</div><p>No changes found</p></div>';
          return;
        }

        const actionIcons = { create: '➕', update: '✏️', delete: '🗑️', toggle: '🔄', import: '📥', export: '📤', other: '📌' };
        let html = '';

        data.data.forEach(log => {
          const icon = actionIcons[log.action_type] || '📌';
          let changesHtml = '';
          
          if (log.old_values || log.new_values) {
            let oldV = log.old_values;
            let newV = log.new_values;
            try { if (typeof oldV === 'string') oldV = JSON.parse(oldV); } catch(e) {}
            try { if (typeof newV === 'string') newV = JSON.parse(newV); } catch(e) {}
            
            let changeLines = [];
            if (oldV && typeof oldV === 'object') {
              Object.keys(oldV).forEach(k => {
                const newVal = newV && newV[k] !== undefined ? newV[k] : '—';
                changeLines.push(`<span style="opacity:0.6">${k}:</span> <span style="color:#ef4444;text-decoration:line-through">${oldV[k]}</span> → <span style="color:#10b981">${newVal}</span>`);
              });
            } else if (newV && typeof newV === 'object') {
              Object.keys(newV).forEach(k => {
                changeLines.push(`<span style="opacity:0.6">${k}:</span> <span style="color:#10b981">${newV[k]}</span>`);
              });
            }
            if (changeLines.length) {
              changesHtml = `<div class="log-changes">${changeLines.join('<br>')}</div>`;
            }
          }

          html += `
            <div class="log-entry">
              <div class="log-icon ${log.action_type}">${icon}</div>
              <div class="log-details">
                <div class="log-title">${log.description}</div>
                <div class="log-meta">
                  <span>👤 ${log.full_name || log.username || 'System'}</span>
                  <span>📦 ${log.module}</span>
                  <span style="text-transform:capitalize;">⚡ ${log.action_type}</span>
                  ${log.target_name ? `<span>🎯 ${log.target_name}</span>` : ''}
                </div>
                ${changesHtml}
              </div>
              <div class="log-time">${formatDate(log.created_at)}</div>
            </div>`;
        });
        container.innerHTML = html;
      })
      .catch(() => {
        container.innerHTML = '<div class="no-logs"><p>Error loading change logs</p></div>';
      });
  };
});
</script>
<script src="global-polish.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
