<?php
/**
 * API Rate Limiter and Response Handler
 * Optimized for performance and security
 */

class RateLimiter {
    private static $instance = null;
    private $storageDir;
    
    private function __construct() {
        $this->storageDir = __DIR__ . '/cache/rate_limits';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if request is allowed under rate limit
     * @param string $identifier IP or user ID
     * @param int $limit Max requests allowed
     * @param int $window Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => timestamp]
     */
    public function check($identifier, $limit = 100, $window = 60) {
        $key = md5($identifier);
        $file = $this->storageDir . '/' . $key . '.limit';
        $now = time();
        
        $data = $this->loadData($file);
        
        // Reset if window has passed
        if ($data['window_start'] < $now - $window) {
            $data = [
                'window_start' => $now,
                'count' => 0
            ];
        }
        
        $data['count']++;
        $remaining = max(0, $limit - $data['count']);
        $reset = $data['window_start'] + $window;
        
        $this->saveData($file, $data);
        
        return [
            'allowed' => $data['count'] <= $limit,
            'remaining' => $remaining,
            'reset' => $reset,
            'limit' => $limit
        ];
    }
    
    /**
     * Apply rate limit and set appropriate headers
     */
    public function apply($identifier, $limit = 100, $window = 60) {
        $result = $this->check($identifier, $limit, $window);
        
        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);
        
        if (!$result['allowed']) {
            header('Retry-After: ' . ($result['reset'] - time()));
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'retry_after' => $result['reset'] - time()
            ]);
            exit;
        }
        
        return $result;
    }
    
    private function loadData($file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if ($data) {
                return $data;
            }
        }
        return ['window_start' => time(), 'count' => 0];
    }
    
    private function saveData($file, $data) {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Clean up old rate limit files
     */
    public function cleanup($maxAge = 3600) {
        $files = glob($this->storageDir . '/*.limit');
        $now = time();
        $cleaned = 0;
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > $maxAge) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}

/**
 * Optimized API Response Helper
 */
class ApiResponse {
    private static $startTime = null;
    
    /**
     * Start timing the request
     */
    public static function startTiming() {
        self::$startTime = microtime(true);
    }
    
    /**
     * Get execution time
     */
    public static function getExecutionTime() {
        if (self::$startTime === null) {
            return 0;
        }
        return round((microtime(true) - self::$startTime) * 1000, 2);
    }
    
    /**
     * Send successful JSON response
     */
    public static function success($data = [], $message = 'Success', $statusCode = 200) {
        self::sendHeaders();
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $response['_debug'] = [
                'execution_time_ms' => self::getExecutionTime(),
                'memory_peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2)
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send error JSON response
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = []) {
        self::sendHeaders();
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'Success') {
        self::sendHeaders();
        
        $totalPages = ceil($total / $perPage);
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$perPage,
                'current_page' => (int)$page,
                'total_pages' => (int)$totalPages,
                'has_more' => $page < $totalPages
            ]
        ];
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $response['_debug'] = [
                'execution_time_ms' => self::getExecutionTime()
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send common headers
     */
    private static function sendHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        // CORS headers (configure for your domain in production)
        if (!defined('IS_PRODUCTION') || !IS_PRODUCTION) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        }
    }
    
    /**
     * Handle OPTIONS preflight request
     */
    public static function handlePreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::sendHeaders();
            http_response_code(204);
            exit;
        }
    }
}

/**
 * Input Validator with common validation rules
 */
class InputValidator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    /**
     * Validate required field
     */
    public function required($field, $message = null) {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? "$field is required";
        }
        return $this;
    }
    
    /**
     * Validate email format
     */
    public function email($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "Invalid email format";
        }
        return $this;
    }
    
    /**
     * Validate numeric value
     */
    public function numeric($field, $message = null) {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? "$field must be numeric";
        }
        return $this;
    }
    
    /**
     * Validate integer value
     */
    public function integer($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field] = $message ?? "$field must be an integer";
        }
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? "$field must be at least $length characters";
        }
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? "$field must not exceed $length characters";
        }
        return $this;
    }
    
    /**
     * Validate against regex pattern
     */
    public function pattern($field, $pattern, $message = null) {
        if (isset($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field] = $message ?? "$field format is invalid";
        }
        return $this;
    }
    
    /**
     * Validate date format
     */
    public function date($field, $format = 'Y-m-d', $message = null) {
        if (isset($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field] = $message ?? "$field must be a valid date ($format)";
            }
        }
        return $this;
    }
    
    /**
     * Validate value is in allowed list
     */
    public function in($field, $allowed, $message = null) {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed)) {
            $this->errors[$field] = $message ?? "$field must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }
    
    /**
     * Validate positive number
     */
    public function positive($field, $message = null) {
        if (isset($this->data[$field]) && (float)$this->data[$field] <= 0) {
            $this->errors[$field] = $message ?? "$field must be a positive number";
        }
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails() {
        return !$this->passes();
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get sanitized data
     */
    public function getSanitized() {
        $sanitized = [];
        foreach ($this->data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    /**
     * Get specific field value
     */
    public function get($field, $default = null) {
        return $this->data[$field] ?? $default;
    }
}

// Helper functions
function rateLimiter() {
    return RateLimiter::getInstance();
}

function validate($data) {
    return new InputValidator($data);
}
