<?php
/**
 * Remediation Utilities - safe helpers for operations that touch the shell or database.
 *
 * Every shell call uses an argument array (no shell string), so user-controlled
 * values can never be interpreted as shell syntax.
 */

class RemediationUtils
{
    /**
     * Create the first admin user from environment variables.
     *
     * Required: ADMIN_PASSWORD
     * Optional: ADMIN_USERNAME, ADMIN_EMAIL, ADMIN_FULLNAME
     */
    public static function createAdminSecure()
    {
        $username  = getenv('ADMIN_USERNAME') ?: 'admin';
        $password  = getenv('ADMIN_PASSWORD');
        $email     = getenv('ADMIN_EMAIL') ?: 'admin@pharmacy.local';
        $full_name = getenv('ADMIN_FULLNAME') ?: 'System Administrator';

        if (!$password) {
            die("ERROR: ADMIN_PASSWORD environment variable not set\n");
        }
        if (strlen($password) < 10) {
            die("ERROR: ADMIN_PASSWORD must be at least 10 characters\n");
        }

        global $conn; // provided by db_connection.php
        if (!isset($conn)) {
            require_once __DIR__ . '/db_connection.php';
        }

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
            echo "Admin user created successfully\n";
            echo "   Username: $username\n";
            echo "   Email: $email\n";
        } elseif (strpos($stmt->error, "Duplicate") !== false) {
            echo "User already exists\n";
        } else {
            die("Error creating user: " . $stmt->error . "\n");
        }

        $stmt->close();
    }

    /**
     * Escape a $_SERVER variable for HTML output.
     */
    public static function escapeServerVar($name, $default = 'Unknown')
    {
        $value = $_SERVER[$name] ?? $default;
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Dump the database with mysqldump without exposing the password
     * on the command line. Throws on any failure so callers can fall back
     * to a pure-PHP backup.
     */
    public static function createDatabaseBackup($db_host, $db_user, $db_pass, $db_name, $backup_file)
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', (string)$db_host)) {
            throw new Exception("Invalid database host");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$db_user)) {
            throw new Exception("Invalid database user");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$db_name)) {
            throw new Exception("Invalid database name");
        }

        $backup_file = str_replace(["\0", "\r", "\n"], '', (string)$backup_file);
        if (strpos($backup_file, '..') !== false || $backup_file === '') {
            throw new Exception("Invalid backup file path");
        }

        $binary = self::findMysqldump();
        if ($binary === null) {
            throw new Exception("mysqldump not found");
        }

        $command = [
            $binary,
            '--host=' . $db_host,
            '--user=' . $db_user,
            '--single-transaction',
            '--routines',
            '--result-file=' . $backup_file,
            $db_name,
        ];

        // Password goes in the child's environment, never in argv.
        $env = array_merge(getenv(), ['MYSQL_PWD' => (string)$db_pass]);
        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env
        );
        if (!is_resource($process)) {
            throw new Exception("Could not start mysqldump");
        }

        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        if ($exit_code !== 0 || !is_file($backup_file) || filesize($backup_file) === 0) {
            throw new Exception("mysqldump failed: " . trim($stderr));
        }
        return true;
    }

    private static function findMysqldump()
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        // Fall back to PATH lookup.
        $finder = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'which';
        $out = [];
        $code = 1;
        @exec($finder . ' mysqldump', $out, $code);
        return ($code === 0 && !empty($out[0]) && is_file(trim($out[0]))) ? trim($out[0]) : null;
    }

    /**
     * Whitelist table names for backup/restore operations.
     */
    public static function validateTableName($table_name)
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$table_name)) {
            throw new Exception("Invalid table name: " . htmlspecialchars((string)$table_name));
        }
        return $table_name;
    }

    /**
     * Validate a printer name against a strict allowlist of characters.
     */
    public static function validatePrinterName($printer_name)
    {
        if (empty($printer_name) || strlen($printer_name) > 255) {
            throw new Exception("Invalid printer name");
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.:]/', '', $printer_name);
        if (empty($sanitized)) {
            throw new Exception("Printer name contains invalid characters");
        }
        return $sanitized;
    }

    /**
     * Run the Python receipt-printing script without a shell.
     */
    public static function executePrintScript($python_path, $script_path, $tmp_file, $printer_name)
    {
        if (!is_file($script_path)) {
            throw new Exception("Print script not found");
        }
        if (!is_file($tmp_file)) {
            throw new Exception("Temporary file not found");
        }

        $printer_name = self::validatePrinterName($printer_name);

        $process = proc_open(
            [$python_path, $script_path, $tmp_file, $printer_name],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new Exception("Could not start print script");
        }

        // Non-blocking read with a 30 second limit.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $deadline = time() + 30;
        do {
            $status = proc_get_status($process);
            $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            if (!$status['running']) {
                break;
            }
            usleep(100000);
        } while (time() < $deadline);

        if ($status['running']) {
            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            throw new Exception("Print timed out");
        }

        $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = ($status['exitcode'] >= 0) ? $status['exitcode'] : proc_close($process);
        if ($status['exitcode'] >= 0) {
            proc_close($process);
        }

        if ($exit_code !== 0) {
            throw new Exception("Print failed: " . trim($output));
        }
        return true;
    }
}

// CLI: php remediation_utils.php create-admin
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'create-admin') {
    RemediationUtils::createAdminSecure();
}
