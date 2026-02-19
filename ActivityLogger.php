<?php
/**
 * ActivityLogger - Comprehensive activity and session logging
 * Tracks login/logout sessions, and all changes made by employees
 */

class ActivityLogger
{
    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
        try {
            $this->ensureTables();
        } catch (Throwable $e) {
            error_log("ActivityLogger::ensureTables skipped: " . $e->getMessage());
        }
    }

    /**
     * Ensure required tables exist
     */
    private function ensureTables()
    {
        // Login sessions table - tracks login/logout times
        $this->conn->query("CREATE TABLE IF NOT EXISTS login_sessions (
            session_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            full_name VARCHAR(100) NULL,
            login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            logout_time DATETIME NULL,
            duration_minutes INT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            status ENUM('active', 'logged_out', 'expired', 'forced') DEFAULT 'active',
            INDEX idx_user_id (user_id),
            INDEX idx_login_time (login_time),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Change logs table - tracks every modification
        $this->conn->query("CREATE TABLE IF NOT EXISTS change_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(50) NULL,
            full_name VARCHAR(100) NULL,
            action_type ENUM('create', 'update', 'delete', 'toggle', 'import', 'export', 'other') NOT NULL,
            module VARCHAR(50) NOT NULL,
            target_type VARCHAR(50) NULL,
            target_id INT NULL,
            target_name VARCHAR(255) NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            description TEXT NOT NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_module (module),
            INDEX idx_action_type (action_type),
            INDEX idx_created_at (created_at),
            INDEX idx_target (target_type, target_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Record a login session
     */
    public function recordLogin($user_id, $username, $full_name = null)
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $stmt = $this->conn->prepare("
                INSERT INTO login_sessions (user_id, username, full_name, login_time, ip_address, user_agent, status)
                VALUES (?, ?, ?, NOW(), ?, ?, 'active')
            ");
            $stmt->bind_param("issss", $user_id, $username, $full_name, $ip, $ua);
            $stmt->execute();

            // Store session_id in PHP session for logout tracking
            $session_id = $this->conn->insert_id;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['login_session_id'] = $session_id;
            }

            return $session_id;
        } catch (Exception $e) {
            error_log("ActivityLogger::recordLogin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record a logout
     */
    public function recordLogout($user_id = null, $status = 'logged_out')
    {
        try {
            $session_id = $_SESSION['login_session_id'] ?? null;

            if ($session_id) {
                $stmt = $this->conn->prepare("
                    UPDATE login_sessions 
                    SET logout_time = NOW(), 
                        status = ?,
                        duration_minutes = TIMESTAMPDIFF(MINUTE, login_time, NOW())
                    WHERE session_id = ?
                ");
                $stmt->bind_param("si", $status, $session_id);
                $stmt->execute();
            } elseif ($user_id) {
                // Fallback: close latest active session for user
                $stmt = $this->conn->prepare("
                    UPDATE login_sessions 
                    SET logout_time = NOW(), 
                        status = ?,
                        duration_minutes = TIMESTAMPDIFF(MINUTE, login_time, NOW())
                    WHERE user_id = ? AND status = 'active'
                    ORDER BY login_time DESC LIMIT 1
                ");
                $stmt->bind_param("si", $status, $user_id);
                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            error_log("ActivityLogger::recordLogout error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a change/action made by a user
     */
    public function logChange($action_type, $module, $description, $options = [])
    {
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? null;
            $full_name = $_SESSION['full_name'] ?? null;
            $target_type = $options['target_type'] ?? null;
            $target_id = $options['target_id'] ?? null;
            $target_name = $options['target_name'] ?? null;
            $old_values = isset($options['old_values']) ? json_encode($options['old_values']) : null;
            $new_values = isset($options['new_values']) ? json_encode($options['new_values']) : null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $stmt = $this->conn->prepare("
                INSERT INTO change_logs 
                    (user_id, username, full_name, action_type, module, target_type, target_id, target_name, old_values, new_values, description, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "isssssisssss",
                $user_id, $username, $full_name,
                $action_type, $module,
                $target_type, $target_id, $target_name,
                $old_values, $new_values,
                $description, $ip
            );
            $stmt->execute();
            return $this->conn->insert_id;
        } catch (Exception $e) {
            error_log("ActivityLogger::logChange error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get login sessions with filters
     */
    public function getLoginSessions($filters = [])
    {
        $where = "1=1";
        $params = [];
        $types = "";

        if (!empty($filters['user_id'])) {
            $where .= " AND ls.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        if (!empty($filters['date_from'])) {
            $where .= " AND ls.login_time >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND ls.login_time <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= "s";
        }
        if (!empty($filters['status'])) {
            $where .= " AND ls.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;

        $sql = "SELECT ls.* FROM login_sessions ls WHERE $where ORDER BY ls.login_time DESC LIMIT $limit";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get change logs with filters
     */
    public function getChangeLogs($filters = [])
    {
        $where = "1=1";
        $params = [];
        $types = "";

        if (!empty($filters['user_id'])) {
            $where .= " AND cl.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        if (!empty($filters['module'])) {
            $where .= " AND cl.module = ?";
            $params[] = $filters['module'];
            $types .= "s";
        }
        if (!empty($filters['action_type'])) {
            $where .= " AND cl.action_type = ?";
            $params[] = $filters['action_type'];
            $types .= "s";
        }
        if (!empty($filters['date_from'])) {
            $where .= " AND cl.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND cl.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= "s";
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;

        $sql = "SELECT cl.* FROM change_logs cl WHERE $where ORDER BY cl.created_at DESC LIMIT $limit";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get summary stats
     */
    public function getStats()
    {
        $stats = [];

        // Today's logins
        $r = $this->conn->query("SELECT COUNT(*) as cnt FROM login_sessions WHERE DATE(login_time) = CURDATE()");
        $stats['today_logins'] = $r ? $r->fetch_assoc()['cnt'] : 0;

        // Active sessions
        $r = $this->conn->query("SELECT COUNT(*) as cnt FROM login_sessions WHERE status = 'active'");
        $stats['active_sessions'] = $r ? $r->fetch_assoc()['cnt'] : 0;

        // Today's changes
        $r = $this->conn->query("SELECT COUNT(*) as cnt FROM change_logs WHERE DATE(created_at) = CURDATE()");
        $stats['today_changes'] = $r ? $r->fetch_assoc()['cnt'] : 0;

        // Total changes this week
        $r = $this->conn->query("SELECT COUNT(*) as cnt FROM change_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $stats['week_changes'] = $r ? $r->fetch_assoc()['cnt'] : 0;

        return $stats;
    }
}
?>
