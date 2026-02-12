<?php
/**
 * System Configuration - Optimized
 * Store sensitive credentials and system-wide settings here.
 * 
 * SECURITY WARNING: 
 * - This file contains sensitive information.
 * - Ensure .htaccess blocks direct access to this file.
 * - Do not commit this file to public repositories.
 * 
 * DEPLOYMENT NOTES:
 * - For production, set environment variables OR create a .env file
 * - The system will use environment variables if available, otherwise defaults below
 */

// Detect environment (production vs development)
// Set ENVIRONMENT=production on your hosting server
$environment = getenv('ENVIRONMENT') ?: 'development';
define('ENVIRONMENT', $environment);
define('IS_PRODUCTION', ENVIRONMENT === 'production');

// Database Credentials
// For production: Set these as environment variables on your hosting provider
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'calloway_pharmacy');

// System Settings
define('SITE_NAME', getenv('SITE_NAME') ?: 'Calloway Pharmacy');

// Base URL - automatically detect or use environment variable
// For production hosting, set BASE_URL environment variable to your domain path
if (getenv('BASE_URL')) {
    define('BASE_URL', getenv('BASE_URL'));
} else {
    // Auto-detect base URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // For local development
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        define('BASE_URL', '/CallowayPharmacyIMS/');
    } else {
        // For production - assumes files are in root or subdirectory
        define('BASE_URL', rtrim($scriptDir, '/') . '/');
    }
}

// Full site URL for emails, links, etc.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', getenv('SITE_URL') ?: $protocol . $host . BASE_URL);

// Error Reporting - Automatically disable in production
define('DEBUG_MODE', !IS_PRODUCTION && (getenv('DEBUG_MODE') !== 'false'));

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    // Log errors to file in production
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Session security settings for production
if (IS_PRODUCTION) {
    ini_set('session.cookie_secure', 1);      // Only send cookies over HTTPS
    ini_set('session.cookie_httponly', 1);    // Prevent JavaScript access to cookies
    ini_set('session.cookie_samesite', 'Strict');
}

// Timezone
date_default_timezone_set(getenv('TIMEZONE') ?: 'Asia/Manila');

// =============================================================================
// PERFORMANCE OPTIMIZATION SETTINGS
// =============================================================================

// Output buffering for faster page loads
if (!IS_PRODUCTION) {
    // Development: smaller buffer for quicker debugging
    ini_set('output_buffering', 4096);
} else {
    // Production: larger buffer for better performance
    ini_set('output_buffering', 'On');
}

// Memory limit optimization
ini_set('memory_limit', IS_PRODUCTION ? '128M' : '256M');

// Max execution time
ini_set('max_execution_time', IS_PRODUCTION ? 30 : 60);

// =============================================================================
// CACHE SETTINGS
// =============================================================================
define('CACHE_ENABLED', IS_PRODUCTION);
define('CACHE_DEFAULT_TTL', 300); // 5 minutes default cache

// =============================================================================
// SECURITY SETTINGS
// =============================================================================
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// =============================================================================
// API RATE LIMITING
// =============================================================================
define('API_RATE_LIMIT', 100); // Requests per minute
define('API_RATE_WINDOW', 60); // Window in seconds

// =============================================================================
// FILE UPLOAD SETTINGS
// =============================================================================
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'csv', 'xlsx']);

// =============================================================================
// PAGINATION DEFAULTS
// =============================================================================
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE', 100);

// =============================================================================
// APPLICATION CONSTANTS
// =============================================================================
define('LOW_STOCK_THRESHOLD', 20);
define('DEFAULT_EXPIRY_ALERT_DAYS', 30);
define('CURRENCY_SYMBOL', '₱');
define('CURRENCY_CODE', 'PHP');

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Format currency for display
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

/**
 * Get asset URL with cache busting in production
 */
function asset($path) {
    $fullPath = __DIR__ . '/' . ltrim($path, '/');
    if (IS_PRODUCTION && file_exists($fullPath)) {
        return BASE_URL . ltrim($path, '/') . '?v=' . filemtime($fullPath);
    }
    return BASE_URL . ltrim($path, '/');
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response and exit
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>