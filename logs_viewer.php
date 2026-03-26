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
    <link rel="stylesheet" href="design-system.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="award-winning-polish.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="custom-modal.js?v=2"></script>
    <style>
        /* ── Logs Viewer — Award-Winning ──────────────────── */
        .logs-container { max-width:1440px; margin:0 auto; padding:1.25rem 1.5rem 2rem; }

        /* Page Header */
        .logs-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 1px 3px rgba(0,0,0,.06); position:relative; overflow:hidden; animation:lvFade .35s ease both; }
        .logs-header::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(180deg,#0a74da,#6366f1); border-radius:4px 0 0 4px; }
        .logs-header h1 { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; margin:0; display:flex; align-items:center; gap:.5rem; color:var(--text-color,#222); }
        .logs-header h1 i { background:linear-gradient(135deg,#0a74da,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; font-size:1.3rem; }
        .logs-header p { margin:.25rem 0 0; color:#64748b; font-size:.87rem; }

        /* Stat Cards */
        .stats-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:.75rem; margin-bottom:1.25rem; }
        .stat-card { background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:14px; padding:1rem 1.15rem; display:flex; align-items:center; gap:.75rem; transition:transform .25s cubic-bezier(.34,1.56,.64,1),box-shadow .25s ease; position:relative; overflow:hidden; animation:lvFade .35s ease both; }
        .stat-card:nth-child(1){animation-delay:.03s} .stat-card:nth-child(2){animation-delay:.06s} .stat-card:nth-child(3){animation-delay:.09s} .stat-card:nth-child(4){animation-delay:.12s} .stat-card:nth-child(5){animation-delay:.15s}
        .stat-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:0 3px 3px 0; }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 25px -5px rgba(0,0,0,.1); }
        .stat-card .si { width:40px; height:40px; border-radius:10px; display:grid; place-items:center; font-size:1rem; flex-shrink:0; }
        .stat-card h3 { font-size:.68rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#64748b; margin:0; }
        .stat-card .count { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; margin:0; line-height:1.1; }
        .stat-card.error::before{background:linear-gradient(180deg,#ef4444,#dc2626)} .stat-card.error .si{background:rgba(239,68,68,.1);color:#ef4444} .stat-card.error .count{color:#ef4444}
        .stat-card.warning::before{background:linear-gradient(180deg,#f59e0b,#d97706)} .stat-card.warning .si{background:rgba(245,158,11,.1);color:#f59e0b} .stat-card.warning .count{color:#d97706}
        .stat-card.info::before{background:linear-gradient(180deg,#3b82f6,#2563eb)} .stat-card.info .si{background:rgba(59,130,246,.1);color:#3b82f6} .stat-card.info .count{color:#3b82f6}
        .stat-card.debug::before{background:linear-gradient(180deg,#94a3b8,#64748b)} .stat-card.debug .si{background:rgba(148,163,184,.1);color:#64748b} .stat-card.debug .count{color:#64748b}
        .stat-card.total::before{background:linear-gradient(180deg,#0a74da,#6366f1)} .stat-card.total .si{background:rgba(10,116,218,.1);color:#0a74da} .stat-card.total .count{color:#0a74da}

        /* Filters */
        .filters { background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); padding:1.25rem 1.5rem; border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.06); margin-bottom:1.25rem; animation:lvFade .35s .05s ease both; }
        .filter-group { display:flex; gap:.75rem; flex-wrap:wrap; align-items:end; }
        .filter-field { flex:1; min-width:180px; }
        .filter-field label { display:block; margin-bottom:.35rem; font-weight:600; font-size:.8rem; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
        .filter-field input,.filter-field select { width:100%; padding:.6rem .85rem; border:1.5px solid var(--c-border,#e5e7eb); border-radius:10px; font-size:.87rem; font-family:inherit; background:var(--c-surface,#fff); color:var(--text-color,#222); transition:border-color .2s ease,box-shadow .2s ease; }
        .filter-field input:focus,.filter-field select:focus { outline:none; border-color:#0a74da; box-shadow:0 0 0 3px rgba(10,116,218,.1); }

        /* Tabs */
        .tabs { display:flex; gap:2px; padding:3px; border-radius:12px; background:var(--c-surface-sunken,#f1f5f9); border:1px solid var(--c-border,#e5e7eb); margin-bottom:1.25rem; overflow-x:auto; animation:lvFade .35s .08s ease both; }
        .tab { padding:.55rem 1.25rem; border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:.85rem; background:transparent; color:#64748b; transition:all .2s ease; font-family:inherit; }
        .tab:hover { color:var(--text-color,#222); background:rgba(0,0,0,.03); }
        .tab.active { background:linear-gradient(135deg,#0a74da,#5b7fff); color:#fff; box-shadow:0 2px 8px rgba(10,116,218,.25); }
        .tab-content { display:none; } .tab-content.active { display:block; }

        /* Log Entries */
        .logs-list { background:var(--c-surface,#fff); border:1px solid var(--c-border,#e5e7eb); border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; animation:lvFade .35s .1s ease both; }
        .log-entry { padding:.85rem 1.25rem; border-bottom:1px solid var(--c-border,#e5e7eb); font-family:'JetBrains Mono','Courier New',monospace; font-size:.78rem; white-space:pre-wrap; word-break:break-word; border-left:4px solid transparent; transition:background .15s ease; }
        .log-entry:last-child { border-bottom:none; }
        .log-entry:hover { background:rgba(10,116,218,.02); }
        .log-entry.error { background:rgba(239,68,68,.04); border-left-color:#ef4444; }
        .log-entry.warning { background:rgba(245,158,11,.04); border-left-color:#f59e0b; }
        .log-entry.info { background:rgba(59,130,246,.04); border-left-color:#3b82f6; }
        .log-entry.debug { background:rgba(148,163,184,.04); border-left-color:#94a3b8; }
        [data-theme="dark"] .log-entry.error{background:rgba(239,68,68,.06)} [data-theme="dark"] .log-entry.warning{background:rgba(245,158,11,.06)} [data-theme="dark"] .log-entry.info{background:rgba(59,130,246,.06)}

        /* Log Files Table */
        .log-files-table { width:100%; border-collapse:collapse; }
        .log-files-table th { padding:.85rem 1.25rem; text-align:left; font-weight:700; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; background:var(--c-surface-sunken,#f8fafc); border-bottom:1px solid var(--c-border,#e5e7eb); }
        .log-files-table td { padding:.85rem 1.25rem; border-bottom:1px solid var(--c-border,#e5e7eb); font-size:.87rem; }
        .log-files-table tr { transition:background .15s ease; }
        .log-files-table tr:hover { background:rgba(10,116,218,.02); }

        /* Buttons */
        .btn { padding:.55rem 1.15rem; border:none; border-radius:10px; cursor:pointer; font-size:.85rem; font-weight:600; font-family:inherit; transition:all .25s cubic-bezier(.34,1.56,.64,1); display:inline-flex; align-items:center; gap:.35rem; }
        .btn-primary { background:linear-gradient(135deg,#0a74da,#5b7fff); color:#fff; box-shadow:0 2px 8px rgba(10,116,218,.2); }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 15px rgba(10,116,218,.3); }
        .btn-danger { background:#ef4444; color:#fff; } .btn-danger:hover{background:#dc2626;transform:translateY(-1px)}
        .btn-secondary { background:rgba(100,116,139,.08); color:#64748b; } .btn-secondary:hover{background:rgba(100,116,139,.15);color:#475569}
        .actions { display:flex; gap:.5rem; }

        /* Badges */
        .badge { padding:.2rem .55rem; border-radius:8px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
        .badge-error{background:rgba(239,68,68,.1);color:#ef4444} .badge-warning{background:rgba(245,158,11,.1);color:#d97706} .badge-info{background:rgba(59,130,246,.1);color:#3b82f6} .badge-debug{background:rgba(148,163,184,.1);color:#64748b}

        /* Empty State */
        .empty-state { text-align:center; padding:3.5rem 2rem; }
        .empty-state i { font-size:3rem; margin-bottom:.75rem; opacity:.25; display:block; }
        .empty-state h3 { font-size:1rem; font-weight:700; opacity:.6; margin:0 0 .3rem; }
        .empty-state p { font-size:.85rem; color:#64748b; opacity:.5; margin:0; }

        .success-message { background:rgba(16,185,129,.08); color:#059669; padding:.85rem 1.25rem; border-radius:12px; margin-bottom:1.25rem; border:1px solid rgba(16,185,129,.15); font-weight:600; font-size:.87rem; }
        .search-highlight { background:rgba(245,158,11,.3); padding:1px 2px; border-radius:3px; }

        @keyframes lvFade { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

        @media(max-width:768px) { .logs-container{padding:.75rem 1rem} .stats-cards{grid-template-columns:repeat(2,1fr)} .filter-group{flex-direction:column} .filter-field{min-width:unset} }
    </style>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="logs-container">
        <div class="logs-header">
            <div>
                <h1><i class="fas fa-clipboard-list"></i> System Logs</h1>
                <p>Monitor application events, errors, and security activity</p>
            </div>
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
