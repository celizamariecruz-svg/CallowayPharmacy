<?php
/**
 * CSRF Token Protection Class
 * Protects against Cross-Site Request Forgery attacks
 * 
 * Usage:
 * - In forms: echo CSRF::getTokenField();
 * - In AJAX: headers: { 'X-CSRF-Token': CSRF.getToken() }
 * - Validate: CSRF::validate() or CSRF::validateAjax()
 */

class CSRF {
    
    /**
     * Generate a new CSRF token
     * @return string The generated token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Store in session
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Get the current CSRF token (or generate new one)
     * @return string The CSRF token
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists and is not expired (1 hour)
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > 3600) {
            return self::generateToken();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get HTML input field for forms
     * @return string HTML hidden input field
     */
    public static function getTokenField() {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get meta tag for AJAX requests
     * @return string HTML meta tag
     */
    public static function getTokenMeta() {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF token from POST request
     * @return bool True if valid, false otherwise
     */
    public static function validate() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists in session
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Check if token was provided
        $provided_token = $_POST['csrf_token'] ?? '';
        
        if (empty($provided_token)) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $provided_token);
    }
    
    /**
     * Validate CSRF token from AJAX request headers
     * @return bool True if valid, false otherwise
     */
    public static function validateAjax() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists in session
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Get token from header or POST data
        $provided_token = '';
        
        // Check X-CSRF-Token header
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $provided_token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        // Fallback to POST data
        elseif (isset($_POST['csrf_token'])) {
            $provided_token = $_POST['csrf_token'];
        }
        
        if (empty($provided_token)) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $provided_token);
    }
    
    /**
     * Validate or die with error message
     * @param string $error_message Custom error message
     * @param bool $is_ajax Whether this is an AJAX request
     */
    public static function validateOrDie($error_message = 'Invalid CSRF token', $is_ajax = false) {
        $valid = $is_ajax ? self::validateAjax() : self::validate();
        
        if (!$valid) {
            if ($is_ajax) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $error_message
                ]);
                exit;
            } else {
                http_response_code(403);
                die($error_message);
            }
        }
    }
    
    /**
     * Regenerate CSRF token (call after successful form submission)
     */
    public static function regenerate() {
        return self::generateToken();
    }
    
    /**
     * Get JavaScript code for AJAX protection
     * @return string JavaScript code
     */
    public static function getAjaxScript() {
        $token = self::getToken();
        return <<<JAVASCRIPT
<script>
// CSRF Protection for AJAX requests
const CSRF = {
    token: '{$token}',
    
    getToken: function() {
        return this.token;
    },
    
    // Add to fetch() requests
    getHeaders: function() {
        return {
            'X-CSRF-Token': this.token,
            'Content-Type': 'application/json'
        };
    },
    
    // For FormData submissions
    addToFormData: function(formData) {
        formData.append('csrf_token', this.token);
        return formData;
    }
};

// Auto-add CSRF token to all fetch requests
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    // Only add CSRF to POST, PUT, DELETE requests to same origin
    if (url.indexOf(window.location.origin) === 0 || url.charAt(0) === '/') {
        options.headers = options.headers || {};
        if (!options.headers['X-CSRF-Token']) {
            options.headers['X-CSRF-Token'] = CSRF.token;
        }
    }
    return originalFetch(url, options);
};

// Auto-add CSRF token to all forms on submit
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        // Skip if form already has CSRF token
        if (form.querySelector('input[name="csrf_token"]')) {
            return;
        }
        
        // Add CSRF token to form
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = CSRF.token;
        form.appendChild(input);
    });
});
</script>
JAVASCRIPT;
    }
}
