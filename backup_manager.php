<?php
/**
 * Backup Manager UI
 * Create, restore, and manage database backups
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

require_once 'db_connection.php';
require_once 'BackupManager.php';
require_once 'Security.php';
require_once 'CSRF.php';

$backup = new BackupManager($conn);
$message = '';
$message_type = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        $message = 'Invalid security token';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $result = $backup->createBackup('manual');
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                break;
                
            case 'restore':
                $filename = $_POST['filename'] ?? '';
                $result = $backup->restoreBackup($filename);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                break;
                
            case 'delete':
                $filename = $_POST['filename'] ?? '';
                if ($backup->deleteBackup($filename)) {
                    $message = 'Backup deleted successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete backup';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Handle download
if (isset($_GET['download'])) {
    $backup->downloadBackup($_GET['download']);
}

// Get list of backups
$backups = $backup->listBackups();

$page_title = 'Database Backup Manager';
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
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-polish.css">
    <link rel="stylesheet" href="polish.css">
    <link rel="stylesheet" href="custom-modal.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="custom-modal.js?v=2"></script>
</head>
<body>
    <?php include 'header-component.php'; ?>
    
    <div class="container" style="max-width: 1200px; margin: 2rem auto; padding: 0 2rem;">
        <div class="card">
            <div class="card-header">
                <h2>🗄️ Database Backup Manager</h2>
                <p class="text-muted">Create, restore, and manage database backups</p>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="margin: 1rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <div class="card-body">
                <!-- Create Backup Section -->
                <div class="backup-actions" style="margin-bottom: 2rem;">
                    <form method="POST" style="display: inline-block;">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="create">
                        <button type="submit" class="btn btn-primary btn-enhanced" style="padding: 1rem 2rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Create New Backup
                        </button>
                    </form>
                    
                    <div class="backup-info" style="display: inline-block; margin-left: 1rem; color: #666;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Backups are stored securely and kept for 30 days
                    </div>
                </div>
                
                <!-- Backup Statistics -->
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div class="stat-card">
                        <div class="stat-label">Total Backups</div>
                        <div class="stat-value"><?php echo count($backups); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Latest Backup</div>
                        <div class="stat-value" style="font-size: 0.9rem;">
                            <?php echo !empty($backups) ? date('M d, Y', $backups[0]['timestamp']) : 'None'; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Size</div>
                        <div class="stat-value" style="font-size: 1rem;">
                            <?php 
                            $total_size = 0;
                            foreach ($backups as $b) {
                                $filepath = __DIR__ . '/backups/' . $b['filename'];
                                if (file_exists($filepath)) {
                                    $total_size += filesize($filepath);
                                }
                            }
                            echo round($total_size / 1024 / 1024, 2) . ' MB';
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Backup List -->
                <h3 style="margin-bottom: 1rem;">📋 Backup History</h3>
                
                <?php if (empty($backups)): ?>
                <div class="empty-state" style="text-align: center; padding: 3rem; color: #999;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity: 0.3; margin-bottom: 1rem;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <h3>No Backups Yet</h3>
                    <p>Create your first backup to protect your data</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Filename</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $b): ?>
                            <tr>
                                <td><?php echo $b['formatted_date']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $b['type'] === 'manual' ? 'primary' : 'secondary'; ?>">
                                        <?php echo ucfirst($b['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $b['size']; ?></td>
                                <td style="font-family: monospace; font-size: 0.85rem;"><?php echo $b['filename']; ?></td>
                                <td style="text-align: center;">
                                    <!-- Download -->
                                    <a href="?download=<?php echo urlencode($b['filename']); ?>" 
                                       class="btn btn-sm btn-success" 
                                       title="Download" 
                                       style="margin: 0 0.25rem;">
                                        ⬇ Download
                                    </a>
                                    
                                    <!-- Restore -->
                                    <form method="POST" style="display: inline-block;" 
                                          onsubmit="return customFormConfirm(event, 'Restore Backup', 'WARNING: This will replace all current data with this backup. Are you absolutely sure?', 'danger');">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($b['filename']); ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Restore" style="margin: 0 0.25rem;">
                                            ↻ Restore
                                        </button>
                                    </form>
                                    
                                    <!-- Delete -->
                                    <?php if ($b['type'] === 'manual'): ?>
                                    <form method="POST" style="display: inline-block;" 
                                          onsubmit="return customFormConfirm(event, 'Delete Backup', 'Delete this backup? This cannot be undone.', 'danger');">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($b['filename']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete" style="margin: 0 0.25rem;">
                                            🗑 Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Information Box -->
                <div class="info-box" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-left: 4px solid #007bff; border-radius: 8px;">
                    <h4 style="margin: 0 0 1rem 0;">ℹ️ Important Information</h4>
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        <li><strong>Automatic Backups:</strong> Created daily at midnight (requires cron job setup)</li>
                        <li><strong>Manual Backups:</strong> Created on-demand and kept permanently (until deleted)</li>
                        <li><strong>Backup Retention:</strong> Automatic backups are kept for 30 days</li>
                        <li><strong>Security:</strong> Backups are stored in protected directory (not web-accessible)</li>
                        <li><strong>Compression:</strong> All backups are gzip compressed to save space</li>
                        <li><strong>Restore:</strong> ⚠️ Restoring will replace ALL current database data</li>
                    </ul>
                </div>
                
                <!-- Automatic Backup Setup Instructions -->
                <div class="setup-instructions" style="margin-top: 2rem; padding: 1.5rem; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 8px;">
                    <h4 style="margin: 0 0 1rem 0;">⚙️ Setup Automatic Daily Backups</h4>
                    <p>Add this to your system's task scheduler (cron job):</p>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 6px; overflow-x: auto;">
<code style="font-family: 'Consolas', 'Monaco', monospace;"># Run daily at midnight
0 0 * * * cd <?php echo __DIR__; ?> && php -f backup_cron.php</code></pre>
                    <p style="margin-top: 1rem; color: #856404;">
                        <strong>Note:</strong> Create a <code>backup_cron.php</code> file that calls <code>BackupManager::automaticBackup()</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="theme.js"></script>
    <script src="shared-polish.js"></script>
    <script src="global-polish.js"></script>
</body>
</html>
