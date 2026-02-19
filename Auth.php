<?php
/**
 * Authentication & Authorization Class
 * Handles user login, logout, session management, and permission checks
 * Implements bcrypt password hashing and CSRF protection
 */

class Auth
{
    private $conn;
    private $session_timeout = 3600; // 1 hour in seconds

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Check session timeout
        $this->checkSessionTimeout();
    }

    /**
     * Check if session has timed out
     */
    private function checkSessionTimeout()
    {
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            if ($elapsed > $this->session_timeout) {
                $this->logout();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Authenticate user with username/email and password
     * @param string $username_or_email
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'user' => array]
     */
    public function login($username_or_email, $password)
    {
        try {
            // Sanitize input
            $input = trim($username_or_email);

            // Check if input is email or username
            $field = filter_var($input, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            // Query user with role information
            $stmt = $this->conn->prepare("
                SELECT 
                    u.user_id,
                    u.username,
                    u.email,
                    u.password_hash,
                    u.full_name,
                    u.role_id,
                    u.is_active,
                    r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.role_id
                WHERE u.$field = ?
                LIMIT 1
            ");

            $stmt->bind_param("s", $input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $this->logActivity(null, 'login_failed', 'Authentication', "Failed login attempt for: $input");
                return [
                    'success' => false,
                    'message' => 'Invalid username/email or password'
                ];
            }

            $user = $result->fetch_assoc();

            // Check if user is active
            if (!$user['is_active']) {
                // Check if it's an unverified email (vs deactivated by admin)
                $needsVerify = false;
                $checkVerify = $this->conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
                if ($checkVerify && $checkVerify->num_rows > 0) {
                    $vStmt = $this->conn->prepare("SELECT email_verified FROM users WHERE user_id = ?");
                    $vStmt->bind_param("i", $user['user_id']);
                    $vStmt->execute();
                    $vRes = $vStmt->get_result()->fetch_assoc();
                    if ($vRes && !$vRes['email_verified']) {
                        $needsVerify = true;
                    }
                }
                if ($needsVerify) {
                    return [
                        'success' => false,
                        'needs_verification' => true,
                        'email' => $user['email'],
                        'message' => 'Please verify your email before logging in.'
                    ];
                }
                $this->logActivity($user['user_id'], 'login_failed', 'Authentication', 'Inactive user login attempt');
                return [
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact administrator.'
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->logActivity($user['user_id'], 'login_failed', 'Authentication', 'Invalid password');
                return [
                    'success' => false,
                    'message' => 'Invalid username/email or password'
                ];
            }

            // Password correct - create session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['last_activity'] = time();
            
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Update last login time
            $updateStmt = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $user['user_id']);
            $updateStmt->execute();

            // Log successful login
            $this->logActivity($user['user_id'], 'login_success', 'Authentication', 'User logged in successfully');

            // Record login session for tracking (non-blocking)
            try {
                require_once __DIR__ . '/ActivityLogger.php';
                $logger = new ActivityLogger($this->conn);
                $logger->recordLogin($user['user_id'], $user['username'], $user['full_name']);
            } catch (Throwable $loggingError) {
                error_log("Activity logging skipped during login: " . $loggingError->getMessage());
            }

            // Remove sensitive data before returning
            unset($user['password_hash']);

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user
            ];

        } catch (Throwable $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ];
        }
    }

    /**
     * Logout current user
     */
    public function logout()
    {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'Authentication', 'User logged out');

            // Record logout session
            try {
                require_once __DIR__ . '/ActivityLogger.php';
                $logger = new ActivityLogger($this->conn);
                $logger->recordLogout($_SESSION['user_id'], 'logged_out');
            } catch (Throwable $loggingError) {
                error_log("Activity logging skipped during logout: " . $loggingError->getMessage());
            }
        }

        // Clear all session data
        $_SESSION = [];

        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy session
        session_destroy();

        return true;
    }

    /**
     * Check if user is currently logged in
     * @return bool
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current logged in user information
     * @return array|null
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role_id' => $_SESSION['role_id'] ?? null,
            'role_name' => $_SESSION['role_name'] ?? null
        ];
    }

    /**
     * Check if current user has a specific permission
     * @param string $permission_name
     * @return bool
     */
    public function hasPermission($permission_name)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $role_id = $_SESSION['role_id'] ?? null;
        if (!$role_id) {
            return false;
        }

        // Admin has all permissions (bypass permission check)
        $role_name = $_SESSION['role_name'] ?? '';
        if (strtolower($role_name) === 'admin') {
            return true;
        }

        // Fallback: Check if user's role is admin by querying database
        if (empty($role_name) && $role_id) {
            $stmt = $this->conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
            $stmt->bind_param("i", $role_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $role = $result->fetch_assoc();
                $role_name = $role['role_name'];
                $_SESSION['role_name'] = $role_name; // Update session

                if (strtolower($role_name) === 'admin') {
                    return true;
                }
            }
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as has_permission
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.permission_id
                WHERE rp.role_id = ?
                AND p.permission_name = ?
            ");

            $stmt->bind_param("is", $role_id, $permission_name);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            return $row['has_permission'] > 0;

        } catch (Exception $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if current user has any of the specified permissions (OR logic)
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission($permissions)
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if current user has all of the specified permissions (AND logic)
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions($permissions)
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Register a new user
     * @param array $userData ['username', 'email', 'password', 'full_name', 'role_id']
     * @return array ['success' => bool, 'message' => string, 'user_id' => int]
     */
    public function registerUser($userData)
    {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password', 'full_name', 'role_id'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '$field' is required"
                    ];
                }
            }

            // Validate email format
            if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email format'
                ];
            }

            // Check if username already exists
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $userData['username']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return [
                    'success' => false,
                    'message' => 'Username already exists'
                ];
            }

            // Check if email already exists
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $userData['email']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }

            // Hash password
            $password_hash = password_hash($userData['password'], PASSWORD_BCRYPT);

            // Insert new user
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");

            $stmt->bind_param(
                "ssssi",
                $userData['username'],
                $userData['email'],
                $password_hash,
                $userData['full_name'],
                $userData['role_id']
            );

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;

                // Log user creation
                $creator_id = $_SESSION['user_id'] ?? null;
                $this->logActivity($creator_id, 'user_created', 'User Management', "New user created: {$userData['username']} (ID: $user_id)");

                return [
                    'success' => true,
                    'message' => 'User registered successfully',
                    'user_id' => $user_id
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to register user'
                ];
            }

        } catch (Exception $e) {
            error_log("User registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during registration'
            ];
        }
    }

    /**
     * Log user activity to activity_logs table
     * @param int|null $user_id
     * @param string $action
     * @param string $module
     * @param string $details
     */
    public function logActivity($user_id, $action, $module, $details = null)
    {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $stmt = $this->conn->prepare("
                INSERT INTO activity_logs (user_id, action, module, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param("isssss", $user_id, $action, $module, $details, $ip_address, $user_agent);
                $stmt->execute();
            } else {
                error_log("Activity log failed: " . $this->conn->error);
            }

        } catch (Exception $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }

    /**
     * Verify CSRF token
     * @param string $token
     * @return bool
     */
    public function verifyCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token
     * @return string
     */
    public function getCsrfToken()
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Require authentication (redirect if not logged in)
     * @param string $redirect_url
     */
    public function requireAuth($redirect_url = 'login.php')
    {
        if (!$this->isLoggedIn()) {
            header("Location: $redirect_url");
            exit;
        }
    }

    /**
     * Require specific permission (return JSON error if not authorized)
     * @param string $permission_name
     * @param bool $json_response
     */
    public function requirePermission($permission_name, $json_response = true)
    {
        if (!$this->hasPermission($permission_name)) {
            if ($json_response) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action'
                ]);
                exit;
            } else {
                http_response_code(403);
                die('Access Denied: Insufficient permissions');
            }
        }
    }
}
?>