<?php
session_start();
require_once 'db_connection.php';
require_once 'ErrorLogger.php';
require_once 'Security.php';
require_once 'CSRF.php';

// Check authentication and admin permission
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle log clearing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::validateOrDie();
    
    if ($_POST['action'] === 'clear_old') {
        $days = (int)($_POST['days'] ?? 30);
        $deleted = ErrorLogger::clearOldLogs($days);
        $success_message = "Cleared $deleted old log files (older than $days days)";
    }
}

// Get filter parameters
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_level = $_GET['level'] ?? '';
$search_term = $_GET['search'] ?? '';

// Get logs
$logs = ErrorLogger::getLogs($selected_level ?: null, $selected_date);
$log_files = ErrorLogger::getLogFiles();
$stats = ErrorLogger::getStats($selected_date);

// Filter by search term
if ($search_term) {
    $logs = array_filter($logs, function($log) use ($search_term) {
        return stripos($log, $search_term) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
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
    <title>System Logs - Calloway Pharmacy</title>
    <?php echo CSRF::getTokenMeta(); ?>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="custom-modal.js?v=2"></script>
    <style>
        .logs-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        .stat-card .count {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
        }
        
        .stat-card.error .count { color: #e74c3c; }
        .stat-card.warning .count { color: #f39c12; }
        .stat-card.info .count { color: #3498db; }
        .stat-card.debug .count { color: #95a5a6; }
        .stat-card.total .count { color: #2c3e50; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-field {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .filter-field input,
        .filter-field select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: #333;
            background: #f8f9fa;
        }
        
        .tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .logs-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .log-entry {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-entry.error {
            background: #fee;
            border-left: 4px solid #e74c3c;
        }
        
        .log-entry.warning {
            background: #ffc;
            border-left: 4px solid #f39c12;
        }
        
        .log-entry.info {
            background: #eff7ff;
            border-left: 4px solid #3498db;
        }
        
        .log-entry.debug {
            background: #f8f9fa;
            border-left: 4px solid #95a5a6;
        }
        
        .log-files-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .log-files-table th,
        .log-files-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .log-files-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .log-files-table tr:hover {
            background: #f8f9fa;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-error { background: #fee; color: #e74c3c; }
        .badge-warning { background: #ffc; color: #f39c12; }
        .badge-info { background: #eff7ff; color: #3498db; }
        .badge-debug { background: #f8f9fa; color: #95a5a6; }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .search-highlight {
            background: yellow;
            padding: 2px;
        }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="logs-container">
        <div class="logs-header">
            <h1>System Logs</h1>
            <div class="actions">
                <button class="btn btn-primary" onclick="location.reload()">
                    🔄 Refresh
                </button>
                <button class="btn btn-danger" onclick="showClearDialog()">
                    🗑️ Clear Old Logs
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card error">
                <h3>Errors</h3>
                <p class="count"><?php echo $stats['errors']; ?></p>
            </div>
            <div class="stat-card warning">
                <h3>Warnings</h3>
                <p class="count"><?php echo $stats['warnings']; ?></p>
            </div>
            <div class="stat-card info">
                <h3>Info</h3>
                <p class="count"><?php echo $stats['info']; ?></p>
            </div>
            <div class="stat-card debug">
                <h3>Debug</h3>
                <p class="count"><?php echo $stats['debug']; ?></p>
            </div>
            <div class="stat-card total">
                <h3>Total</h3>
                <p class="count"><?php echo $stats['total']; ?></p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-group">
                <div class="filter-field">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                </div>
                <div class="filter-field">
                    <label for="level">Level</label>
                    <select id="level" name="level">
                        <option value="">All Levels</option>
                        <option value="error" <?php echo $selected_level === 'error' ? 'selected' : ''; ?>>Errors</option>
                        <option value="warning" <?php echo $selected_level === 'warning' ? 'selected' : ''; ?>>Warnings</option>
                        <option value="info" <?php echo $selected_level === 'info' ? 'selected' : ''; ?>>Info</option>
                        <option value="debug" <?php echo $selected_level === 'debug' ? 'selected' : ''; ?>>Debug</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search in logs..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="filter-field">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('logs')">📋 Logs</button>
            <button class="tab" onclick="switchTab('files')">📁 Log Files</button>
        </div>
        
        <!-- Logs Tab -->
        <div id="logs-tab" class="tab-content active">
            <div class="logs-list">
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <div>📋</div>
                        <h3>No logs found</h3>
                        <p>No log entries for the selected date and filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                            // Determine log level from content
                            $level_class = 'info';
                            if (stripos($log, 'ERROR') !== false) $level_class = 'error';
                            elseif (stripos($log, 'WARNING') !== false) $level_class = 'warning';
                            elseif (stripos($log, 'DEBUG') !== false) $level_class = 'debug';
                            
                            // Highlight search term
                            if ($search_term) {
                                $log = preg_replace('/(' . preg_quote($search_term, '/') . ')/i', '<span class="search-highlight">$1</span>', $log);
                            }
                        ?>
                        <div class="log-entry <?php echo $level_class; ?>">
                            <?php echo $log; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Files Tab -->
        <div id="files-tab" class="tab-content">
            <table class="log-files-table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Last Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($log_files)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px;">
                                No log files found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($log_files as $file): ?>
                            <tr>
                                <td>
                                    <?php 
                                        $level = '';
                                        if (strpos($file['filename'], 'error') !== false) $level = 'error';
                                        elseif (strpos($file['filename'], 'warning') !== false) $level = 'warning';
                                        elseif (strpos($file['filename'], 'info') !== false) $level = 'info';
                                        elseif (strpos($file['filename'], 'debug') !== false) $level = 'debug';
                                        
                                        if ($level) {
                                            echo '<span class="badge badge-' . $level . '">' . strtoupper($level) . '</span> ';
                                        }
                                        echo htmlspecialchars($file['filename']);
                                    ?>
                                </td>
                                <td><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
                                <td><?php echo date('Y-m-d H:i:s', $file['modified']); ?></td>
                                <td>
                                    <button class="btn btn-secondary" onclick="viewLogFile('<?php echo htmlspecialchars($file['filename']); ?>')">
                                        👁️ View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Clear Logs Dialog -->
    <div id="clearDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h2>Clear Old Logs</h2>
            <p>Remove log files older than:</p>
            <form method="POST" id="clearForm">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="clear_old">
                <select name="days" style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30" selected>30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                </select>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-danger" style="flex: 1;">Clear Logs</button>
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="hideClearDialog()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="security.js"></script>
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function showClearDialog() {
            document.getElementById('clearDialog').style.display = 'flex';
        }
        
        function hideClearDialog() {
            document.getElementById('clearDialog').style.display = 'none';
        }
        
        function viewLogFile(filename) {
            // Parse date from filename
            const dateMatch = filename.match(/\d{4}-\d{2}-\d{2}/);
            const level = filename.split('_')[0];
            
            if (dateMatch) {
                window.location.href = `logs_viewer.php?date=${dateMatch[0]}&level=${level}`;
            }
        }
        
        // Close dialog on outside click
        document.getElementById('clearDialog')?.addEventListener('click', function(e) {
            if (e.target === this) {
                hideClearDialog();
            }
        });
        
        // Auto-refresh every 30 seconds (silently)
        let autoRefresh = setInterval(() => {
            location.reload();
        }, 30000);
        
        // Stop auto-refresh on page unload
        window.addEventListener('beforeunload', () => {
            clearInterval(autoRefresh);
        });
    </script>
    <script src="theme.js"></script>
    
    <?php include 'footer-component.php'; ?>
</body>
</html>
