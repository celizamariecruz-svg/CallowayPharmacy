<?php
/**
 * Database Connection - Optimized
 * Uses credentials from config.php with connection pooling and performance settings
 */

require_once 'config.php';

// Use persistent connection for better performance (connection pooling)
// Note: SSL connections are created without the 'p:' prefix to avoid SSL handshake issues.
$host = IS_PRODUCTION ? 'p:' . DB_HOST : DB_HOST; // 'p:' prefix enables persistent connection

// Enable SSL automatically for Azure MySQL hosts, or via env override
$sslEnv = getenv('DB_SSL');
$useSsl = $sslEnv !== false
    ? filter_var($sslEnv, FILTER_VALIDATE_BOOLEAN)
    : (strpos(DB_HOST, '.mysql.database.azure.com') !== false);

// Create connection with error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if ($useSsl) {
        $conn = mysqli_init();
        if (!$conn) {
            throw new mysqli_sql_exception('Failed to initialize MySQL connection');
        }

        // Disable cert verification to support Azure's default SSL without local CA files.
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $conn->ssl_set(null, null, null, null, null);

        $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306, null, MYSQLI_CLIENT_SSL);
    } else {
        $conn = new mysqli($host, DB_USER, DB_PASS, DB_NAME);
    }
    
    // Set charset to utf8mb4 for full unicode support
    $conn->set_charset("utf8mb4");
    
    // Optimize connection settings for performance
    $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1); // Return native types
    
    // Set reasonable timeouts
    if (IS_PRODUCTION) {
        $conn->query("SET SESSION wait_timeout = 28800"); // 8 hours
        $conn->query("SET SESSION interactive_timeout = 28800");
    }
    
    // Set SQL mode for strict data handling
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    
} catch (mysqli_sql_exception $e) {
    // Log error instead of leaking it to user in production
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Connection failed: " . $e->getMessage());
    } else {
        error_log("Database connection failed: " . $e->getMessage());
        die("System error. Please try again later.");
    }
}

/**
 * Get PDO connection (optional - for code that prefers PDO)
 * @return PDO|null
 */
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_NAME
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            if (IS_PRODUCTION) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("PDO Connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

?>