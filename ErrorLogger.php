<?php
/**
 * Error Logger Class
 * Centralized error logging and monitoring system
 * 
 * Features:
 * - Multiple log levels (ERROR, WARNING, INFO, DEBUG)
 * - Automatic error capturing
 * - Database error logging
 * - File-based logging
 * - Log rotation
 * - Email notifications for critical errors
 */

class ErrorLogger {
    
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';
    
    private static $log_dir = null;
    private static $max_log_size = 10485760; // 10MB
    private static $email_on_error = true;
    private static $admin_email = 'admin@callowaypharmacy.com';
    
    /**
     * Initialize error logger
     */
    public static function init($log_dir = null) {
        self::$log_dir = $log_dir ?? __DIR__ . '/logs';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }
        
        // Protect logs directory
        self::protectLogsDirectory();
        
        // Set custom error handler
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
        register_shutdown_function([self::class, 'fatalErrorHandler']);
    }
    
    /**
     * Protect logs directory with .htaccess
     */
    private static function protectLogsDirectory() {
        $htaccess_file = self::$log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Require all denied\nDeny from all");
        }
    }
    
    /**
     * Log a message
     * @param string $level Log level (ERROR, WARNING, INFO, DEBUG)
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function log($level, $message, $context = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'url' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'user_id' => $_SESSION['user_id'] ?? 'Guest',
            'file' => $context['file'] ?? 'N/A',
            'line' => $context['line'] ?? 'N/A'
        ];
        
        // Write to file
        self::writeToFile($level, $log_entry);
        
        // Email on critical errors
        if ($level === self::ERROR && self::$email_on_error) {
            self::sendErrorEmail($log_entry);
        }
        
        return true;
    }
    
    /**
     * Log error
     */
    public static function error($message, $context = []) {
        return self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Log warning
     */
    public static function warning($message, $context = []) {
        return self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Log info
     */
    public static function info($message, $context = []) {
        return self::log(self::INFO, $message, $context);
    }
    
    /**
     * Log debug
     */
    public static function debug($message, $context = []) {
        return self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Custom error handler
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $error_types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_NOTICE => 'INFO',
            E_USER_ERROR => 'ERROR',
            E_USER_WARNING => 'WARNING',
            E_USER_NOTICE => 'INFO',
            E_STRICT => 'INFO',
            E_DEPRECATED => 'INFO'
        ];
        
        $level = $error_types[$errno] ?? 'ERROR';
        
        self::log($level, $errstr, [
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ]);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Exception handler
     */
    public static function exceptionHandler($exception) {
        self::error($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    
    /**
     * Fatal error handler
     */
    public static function fatalErrorHandler() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::error($error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        }
    }
    
    /**
     * Write log entry to file
     */
    private static function writeToFile($level, $log_entry) {
        $log_file = self::$log_dir . '/' . strtolower($level) . '_' . date('Y-m-d') . '.log';
        
        // Check log rotation
        if (file_exists($log_file) && filesize($log_file) > self::$max_log_size) {
            self::rotateLog($log_file);
        }
        
        // Format log entry
        $log_line = sprintf(
            "[%s] %s: %s | IP: %s | User: %s | File: %s:%s\n",
            $log_entry['timestamp'],
            $log_entry['level'],
            $log_entry['message'],
            $log_entry['ip'],
            $log_entry['user_id'],
            $log_entry['file'],
            $log_entry['line']
        );
        
        // Add context if present
        if (!empty($log_entry['context'])) {
            $log_line .= "Context: " . json_encode($log_entry['context']) . "\n";
        }
        
        $log_line .= "---\n";
        
        // Write to file
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotate log file when it gets too large
     */
    private static function rotateLog($log_file) {
        $backup_file = $log_file . '.' . time() . '.bak';
        rename($log_file, $backup_file);
        
        // Compress old log
        if (function_exists('gzencode')) {
            $content = file_get_contents($backup_file);
            file_put_contents($backup_file . '.gz', gzencode($content, 9));
            unlink($backup_file);
        }
    }
    
    /**
     * Send error notification email
     */
    private static function sendErrorEmail($log_entry) {
        // Only send email for errors, not for every log
        if ($log_entry['level'] !== self::ERROR) {
            return;
        }
        
        $subject = 'Error Alert: ' . $log_entry['message'];
        
        $body = "An error has occurred in Calloway Pharmacy IMS:\n\n";
        $body .= "Time: " . $log_entry['timestamp'] . "\n";
        $body .= "Level: " . $log_entry['level'] . "\n";
        $body .= "Message: " . $log_entry['message'] . "\n";
        $body .= "File: " . $log_entry['file'] . ":" . $log_entry['line'] . "\n";
        $body .= "User: " . $log_entry['user_id'] . "\n";
        $body .= "IP: " . $log_entry['ip'] . "\n";
        $body .= "URL: " . $log_entry['url'] . "\n\n";
        
        if (!empty($log_entry['context'])) {
            $body .= "Context:\n" . print_r($log_entry['context'], true) . "\n";
        }
        
        // Use email service if available
        if (file_exists(__DIR__ . '/email_service.php')) {
            require_once __DIR__ . '/email_service.php';
            // Implement email sending here
        } else {
            // Fallback to PHP mail
            @mail(self::$admin_email, $subject, $body);
        }
    }
    
    /**
     * Get logs by level and date
     * @param string $level
     * @param string $date (Y-m-d format)
     * @return array
     */
    public static function getLogs($level = null, $date = null) {
        $logs = [];
        $date = $date ?? date('Y-m-d');
        
        if ($level) {
            $log_files = [self::$log_dir . '/' . strtolower($level) . '_' . $date . '.log'];
        } else {
            $log_files = glob(self::$log_dir . '/*_' . $date . '.log');
        }
        
        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                $content = file_get_contents($log_file);
                $entries = explode("---\n", $content);
                
                foreach ($entries as $entry) {
                    if (!empty(trim($entry))) {
                        $logs[] = $entry;
                    }
                }
            }
        }
        
        return array_reverse($logs); // Newest first
    }
    
    /**
     * Get all log files
     * @return array
     */
    public static function getLogFiles() {
        $files = [];
        $log_files = glob(self::$log_dir . '/*.log');
        
        foreach ($log_files as $file) {
            $files[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file),
                'readable' => is_readable($file)
            ];
        }
        
        // Sort by modified time (newest first)
        usort($files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $files;
    }
    
    /**
     * Clear old logs (older than N days)
     * @param int $days
     * @return int Number of files deleted
     */
    public static function clearOldLogs($days = 30) {
        $count = 0;
        $cutoff = time() - ($days * 86400);
        $log_files = glob(self::$log_dir . '/*.log*');
        
        foreach ($log_files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get error statistics
     * @param string $date
     * @return array
     */
    public static function getStats($date = null) {
        $date = $date ?? date('Y-m-d');
        $stats = [
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
            'debug' => 0,
            'total' => 0
        ];
        
        $levels = ['error', 'warning', 'info', 'debug'];
        
        foreach ($levels as $level) {
            $log_file = self::$log_dir . '/' . $level . '_' . $date . '.log';
            if (file_exists($log_file)) {
                $content = file_get_contents($log_file);
                $count = substr_count($content, '---');
                $stats[$level . 's'] = $count;
                $stats['total'] += $count;
            }
        }
        
        return $stats;
    }
}

// Auto-initialize
ErrorLogger::init();
