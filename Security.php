<?php
/**
 * Security Helper Class
 * Provides security utilities for the application
 * 
 * Features:
 * - Session management with timeout
 * - Input sanitization and validation
 * - Rate limiting for authentication
 * - XSS prevention
 * - SQL injection prevention helpers
 */

class Security
{

    // Session timeout (30 minutes)
    const SESSION_TIMEOUT = 1800;

    // Max login attempts
    const MAX_LOGIN_ATTEMPTS = 5;

    // Login lockout time (15 minutes)
    const LOCKOUT_TIME = 900;

    /**
     * Initialize secure session
     */
    public static function initSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');

            // For HTTPS, enable secure cookies
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }

            session_start();

            // Initialize security session vars if not set
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
                $_SESSION['last_activity'] = time();
                $_SESSION['created'] = time();
            }
        }

        // Check session timeout
        self::checkSessionTimeout();

        // Regenerate session ID periodically (every 30 minutes)
        if (isset($_SESSION['created']) && (time() - $_SESSION['created']) > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    /**
     * Check if session has timed out
     */
    public static function checkSessionTimeout()
    {
        if (
            isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT
        ) {
            self::destroySession();
            header('Location: login.php?timeout=1');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Destroy session securely
     */
    public static function destroySession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();

            // Delete session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }

            session_destroy();
        }
    }

    /**
     * Sanitize input to prevent XSS
     * @param mixed $data
     * @return mixed Sanitized data
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        // Trim whitespace
        $data = trim($data);

        // Remove null bytes
        $data = str_replace(chr(0), '', $data);

        // Convert special characters to HTML entities
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $data;
    }

    /**
     * Sanitize for database output (decode HTML entities)
     * @param string $data
     * @return string
     */
    public static function sanitizeOutput($data)
    {
        return htmlspecialchars_decode($data, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Validate email address
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number (Philippine format)
     * @param string $phone
     * @return bool
     */
    public static function validatePhone($phone)
    {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Check if valid Philippine phone number
        return preg_match('/^(09|\+639)\d{9}$/', $phone) === 1;
    }

    /**
     * Validate password strength
     * @param string $password
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePassword($password)
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        // Optional: special character requirement
        // if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        //     $errors[] = 'Password must contain at least one special character';
        // }

        return [
            'valid' => empty($errors),
            'message' => implode('. ', $errors)
        ];
    }

    /**
     * Hash password securely
     * @param string $password
     * @return string
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Check login rate limiting
     * @param string $identifier (IP or username)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public static function checkRateLimit($identifier)
    {
        self::initSession();

        $key = 'login_attempts_' . md5($identifier);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }

        $attempts = &$_SESSION[$key];

        // Reset if lockout period has passed
        if ((time() - $attempts['first_attempt']) > self::LOCKOUT_TIME) {
            $attempts['count'] = 0;
            $attempts['first_attempt'] = time();
        }

        $remaining = self::MAX_LOGIN_ATTEMPTS - $attempts['count'];
        $reset_time = $attempts['first_attempt'] + self::LOCKOUT_TIME;

        return [
            'allowed' => $attempts['count'] < self::MAX_LOGIN_ATTEMPTS,
            'remaining' => max(0, $remaining),
            'reset_time' => $reset_time,
            'wait_minutes' => ceil(($reset_time - time()) / 60)
        ];
    }

    /**
     * Record failed login attempt
     * @param string $identifier
     */
    public static function recordFailedLogin($identifier)
    {
        self::initSession();

        $key = 'login_attempts_' . md5($identifier);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }

        $_SESSION[$key]['count']++;
    }

    /**
     * Reset login attempts (on successful login)
     * @param string $identifier
     */
    public static function resetLoginAttempts($identifier)
    {
        self::initSession();

        $key = 'login_attempts_' . md5($identifier);
        unset($_SESSION[$key]);
    }

    /**
     * Generate random secure token
     * @param int $length
     * @return string
     */
    public static function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Prevent SQL injection in LIKE queries
     * @param string $string
     * @return string
     */
    public static function escapeLike($string)
    {
        return addcslashes($string, '%_');
    }

    /**
     * Check if request is AJAX
     * @return bool
     */
    public static function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get client IP address
     * @return string
     */
    public static function getClientIP()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Validate IP
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Log security event
     * @param string $event_type
     * @param string $message
     * @param array $context
     */
    public static function logEvent($event_type, $message, $context = [])
    {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $event_type,
            'message' => $message,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'user_id' => $_SESSION['user_id'] ?? 'Guest',
            'context' => $context
        ];

        $log_file = __DIR__ . '/security.log';
        $log_line = json_encode($log_entry) . PHP_EOL;

        // Write to log file
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Prevent directory traversal in file paths
     * @param string $path
     * @return string|false Safe path or false if invalid
     */
    public static function sanitizeFilePath($path)
    {
        // Remove any directory traversal attempts
        $path = str_replace(['../', '..\\', './'], '', $path);

        // Get realpath
        $realPath = realpath($path);

        // Ensure path is within allowed directory
        $basePath = realpath(__DIR__);

        if ($realPath === false || strpos($realPath, $basePath) !== 0) {
            return false;
        }

        return $realPath;
    }

    /**
     * Send JSON response securely
     * @param array $data
     * @param int $status_code
     */
    public static function jsonResponse($data, $status_code = 200)
    {
        http_response_code($status_code);
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data);
        exit;
    }

    /**
     * Check if user is authenticated
     * @return bool
     */
    public static function isAuthenticated()
    {
        self::initSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Require authentication (redirect if not logged in)
     * @param string $redirect_url
     */
    public static function requireAuth($redirect_url = 'login.php')
    {
        if (!self::isAuthenticated()) {
            header("Location: $redirect_url");
            exit;
        }
    }

    /**
     * Check if user has required permission
     * @param string $permission
     * @return bool
     */
    public static function hasPermission($permission)
    {
        self::initSession();

        // Admin has all permissions
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }

        // Check specific permission
        $permissions = $_SESSION['permissions'] ?? [];
        return in_array($permission, $permissions);
    }
}
