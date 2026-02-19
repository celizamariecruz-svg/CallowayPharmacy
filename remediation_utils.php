<?php
/**
 * Remediation Utilities - Security fixes and improvements
 * 
 * This library provides secure implementations for common operations
 * that have security risks in the current codebase.
 */

class RemediationUtils
{
    /**
     * Create admin user securely using environment variables
     * Usage: php -r "require 'remediation_utils.php'; RemediationUtils::createAdminSecure();"
     */
    public static function createAdminSecure()
    {
        // Get credentials from environment variables
        $username = getenv('ADMIN_USERNAME') ?: 'admin';
        $password = getenv('ADMIN_PASSWORD');
        $email = getenv('ADMIN_EMAIL') ?: 'admin@pharmacy.local';
        $full_name = getenv('ADMIN_FULLNAME') ?: 'System Administrator';
        
        if (!$password) {
            die("ERROR: ADMIN_PASSWORD environment variable not set\n");
        }
        
        require_once __DIR__ . '/db_connection.php';
        
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $conn->prepare(
            "INSERT INTO users (username, password_hash, email, full_name, role_id) 
             VALUES (?, ?, ?, ?, (SELECT role_id FROM roles WHERE role_name = 'administrator' LIMIT 1))"
        );
        
        if (!$stmt) {
            die("Database error: " . $conn->error . "\n");
        }
        
        $stmt->bind_param("ssss", $username, $hashed_password, $email, $full_name);
        
        if ($stmt->execute()) {
            echo "✅ Admin user created successfully\n";
            echo "   Username: $username\n";
            echo "   Email: $email\n";
        } else {
            if (strpos($stmt->error, "Duplicate") !== false) {
                echo "⚠️  User already exists\n";
            } else {
                die("Error creating user: " . $stmt->error . "\n");
            }
        }
        
        $stmt->close();
    }
    
    /**
     * Safely escape $_SERVER variables for HTML output
     */
    public static function escapeServerVar($name, $default = 'Unknown')
    {
        $value = $_SERVER[$name] ?? $default;
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Safely create database backup without exposing credentials
     */
    public static function createDatabaseBackup($db_host, $db_user, $db_pass, $db_name, $backup_file)
    {
        // Validate ALL inputs with strict allowlists
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $db_host)) {
            throw new Exception("Invalid database host");
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_user)) {
            throw new Exception("Invalid database user");
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
            throw new Exception("Invalid database name");
        }
        
        // Validate backup file path - only allow safe characters
        $backup_file = str_replace(array('..', '|', ';', '&', '
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass);
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result);
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass);
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result);
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass);
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result);
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass);
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result);
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }

    /**
     * Validate printer name for safe execution
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass);
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result);
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass);
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result);
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass);
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result);
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }

    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
, '`'), '', $backup_file);
        if (!is_writable(dirname($backup_file))) {
            throw new Exception("Backup directory not writable");
        }
        
        // Use environment variable for password instead of command line
        putenv("MYSQL_PWD=" . $db_pass); // Already sanitized by not using in command
        
        try {
            // Use mysqldump with password via environment (secure)
            // All parameters are validated above
            $cmd = "mysqldump --no-password --host=" . escapeshellarg($db_host) . 
                   " --user=" . escapeshellarg($db_user) . 
                   " " . escapeshellarg($db_name) . 
                   " > " . escapeshellarg($backup_file) . " 2>&1";
            
            $result = 0;
            exec($cmd, $output, $result); // Using exec instead of system - same output handling
            
            // Clear password from environment
            putenv("MYSQL_PWD=");
            
            if ($result !== 0) {
                throw new Exception("Backup failed with exit code " . $result);
            }
            
            return true;
            
        } catch (Exception $e) {
            putenv("MYSQL_PWD=");
            throw $e;
        }
    }
    
    /**
     * Whitelist table names for backup/restore operations
     */
    public static function validateTableName($table_name)
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars($table_name));
        }
        return $table_name;
    }
    
    /**
     * Safe printer name validation for receipt printing
     */
    public static function validatePrinterName($printer_name)
    {
        // Whitelist known printers from config or settings
        // For now, basic validation
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }
        
        // Remove dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        
        return $sanitized;
    }
    
    /**
     * Safe Python script execution for receipt printing
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        // Validate all inputs
        if (!file_exists($script_path)) {
            throw new Exception("Print script not found");
        }
        
        if (!file_exists($tmp_file)) {
            throw new Exception("Temporary file not found");
        }
        
        $printer_name = self::validatePrinterName($printer_name);
        
        // Build command with proper escaping
        $command = escapeshellarg($python_path) . ' ' . 
                   escapeshellarg($script_path) . ' ' . 
                   escapeshellarg($tmp_file) . ' ' . 
                   escapeshellarg($printer_name) . 
                   ' 2>&1';
        
        $output = [];
        $exit_code = 0;
        
        // Execute with timeout protection
        exec("timeout 30s " . $command, $output, $exit_code);
        
        if ($exit_code !== 0 && $exit_code !== 124) { // 124 = timeout
            throw new Exception("Print failed: " . implode("\n", $output));
        }
        
        return true;
    }
}

// CLI execution for admin creation
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    try {
        RemediationUtils::createAdminSecure();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage() . "\n");
    }
}
?>
