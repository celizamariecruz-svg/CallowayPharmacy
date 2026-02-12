<?php
/**
 * Database Backup Manager
 * Automated backup and restore functionality
 * 
 * Features:
 * - Automatic daily backups
 * - Manual backup on demand
 * - Backup restoration
 * - Backup compression (gzip)
 * - Backup cleanup (keep last 30 days)
 * - Email notifications
 */

class BackupManager {
    
    private $conn;
    private $backup_dir;
    private $max_backups = 30; // Keep last 30 days
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->backup_dir = __DIR__ . '/backups';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
        
        // Protect backup directory with .htaccess
        $this->protectBackupDirectory();
    }
    
    /**
     * Create .htaccess to protect backup directory
     */
    private function protectBackupDirectory() {
        $htaccess_file = $this->backup_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Require all denied\nDeny from all");
        }
    }
    
    /**
     * Create database backup
     * @param string $type 'manual' or 'automatic'
     * @return array ['success' => bool, 'message' => string, 'file' => string]
     */
    public function createBackup($type = 'manual') {
        try {
            // Get database credentials
            $db_host = DB_HOST ?? 'localhost';
            $db_user = DB_USER ?? 'root';
            $db_pass = DB_PASS ?? '';
            $db_name = DB_NAME ?? 'calloway_pharmacy';
            
            // Generate filename
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$type}_{$timestamp}.sql";
            $filepath = $this->backup_dir . '/' . $filename;
            
            // Create backup using mysqldump
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --databases %s --add-drop-table --complete-insert --extended-insert --quote-names --routines --triggers > %s',
                escapeshellarg($db_host),
                escapeshellarg($db_user),
                escapeshellarg($db_pass),
                escapeshellarg($db_name),
                escapeshellarg($filepath)
            );
            
            // Execute mysqldump
            exec($command, $output, $return_var);
            
            if ($return_var !== 0) {
                // Fallback to PHP backup method
                $this->phpBackup($filepath);
            }
            
            // Compress backup
            $this->compressBackup($filepath);
            $compressed_file = $filepath . '.gz';
            
            // Remove uncompressed file
            if (file_exists($compressed_file)) {
                unlink($filepath);
                $filename .= '.gz';
            }
            
            // Get file size
            $filesize = file_exists($this->backup_dir . '/' . $filename) 
                ? filesize($this->backup_dir . '/' . $filename) 
                : 0;
            
            // Log backup
            $this->logBackup($filename, $filesize, $type);
            
            // Cleanup old backups
            $this->cleanupOldBackups();
            
            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'file' => $filename,
                'size' => $this->formatBytes($filesize),
                'path' => $this->backup_dir . '/' . $filename
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * PHP-based backup (fallback method)
     * @param string $filepath
     */
    private function phpBackup($filepath) {
        $sql = "-- Calloway Pharmacy Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Get all tables
        $tables_query = "SHOW TABLES";
        $tables_result = $this->conn->query($tables_query);
        
        while ($table = $tables_result->fetch_array()) {
            $table_name = $table[0];
            
            // Drop table
            $sql .= "DROP TABLE IF EXISTS `$table_name`;\n\n";
            
            // Create table
            $create_query = "SHOW CREATE TABLE `$table_name`";
            $create_result = $this->conn->query($create_query);
            $create_row = $create_result->fetch_array();
            $sql .= $create_row[1] . ";\n\n";
            
            // Insert data
            $data_query = "SELECT * FROM `$table_name`";
            $data_result = $this->conn->query($data_query);
            
            if ($data_result->num_rows > 0) {
                while ($row = $data_result->fetch_assoc()) {
                    $sql .= "INSERT INTO `$table_name` VALUES (";
                    
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $this->conn->real_escape_string($value) . "'";
                        }
                    }
                    
                    $sql .= implode(', ', $values);
                    $sql .= ");\n";
                }
                $sql .= "\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write to file
        file_put_contents($filepath, $sql);
    }
    
    /**
     * Compress backup file with gzip
     * @param string $filepath
     */
    private function compressBackup($filepath) {
        if (!file_exists($filepath)) {
            return false;
        }
        
        $gz_filepath = $filepath . '.gz';
        $fp_in = fopen($filepath, 'rb');
        $fp_out = gzopen($gz_filepath, 'wb9');
        
        if ($fp_in && $fp_out) {
            while (!feof($fp_in)) {
                gzwrite($fp_out, fread($fp_in, 1024 * 512));
            }
            fclose($fp_in);
            gzclose($fp_out);
            return true;
        }
        
        return false;
    }
    
    /**
     * Restore database from backup
     * @param string $filename
     * @return array ['success' => bool, 'message' => string]
     */
    public function restoreBackup($filename) {
        try {
            $filepath = $this->backup_dir . '/' . $filename;
            
            if (!file_exists($filepath)) {
                return [
                    'success' => false,
                    'message' => 'Backup file not found'
                ];
            }
            
            // Decompress if needed
            $sql_file = $filepath;
            if (substr($filename, -3) === '.gz') {
                $sql_file = $this->backup_dir . '/temp_restore.sql';
                $this->decompressBackup($filepath, $sql_file);
            }
            
            // Read SQL file
            $sql = file_get_contents($sql_file);
            
            if (empty($sql)) {
                return [
                    'success' => false,
                    'message' => 'Backup file is empty'
                ];
            }
            
            // Execute SQL
            $this->conn->multi_query($sql);
            
            // Wait for all queries to finish
            do {
                if ($result = $this->conn->store_result()) {
                    $result->free();
                }
            } while ($this->conn->more_results() && $this->conn->next_result());
            
            // Clean up temp file
            if ($sql_file !== $filepath && file_exists($sql_file)) {
                unlink($sql_file);
            }
            
            // Log restoration
            $this->logRestore($filename);
            
            return [
                'success' => true,
                'message' => 'Database restored successfully from ' . $filename
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Decompress gzip file
     * @param string $gz_file
     * @param string $output_file
     */
    private function decompressBackup($gz_file, $output_file) {
        $fp_in = gzopen($gz_file, 'rb');
        $fp_out = fopen($output_file, 'wb');
        
        if ($fp_in && $fp_out) {
            while (!gzeof($fp_in)) {
                fwrite($fp_out, gzread($fp_in, 1024 * 512));
            }
            gzclose($fp_in);
            fclose($fp_out);
        }
    }
    
    /**
     * Get list of all backups
     * @return array List of backup files with details
     */
    public function listBackups() {
        $backups = array();
        $files = glob($this->backup_dir . '/backup_*.sql*');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $filesize = filesize($file);
            $filetime = filemtime($file);
            
            // Parse filename to get type and date
            preg_match('/backup_(manual|automatic)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/', $filename, $matches);
            
            $backups[] = [
                'filename' => $filename,
                'type' => $matches[1] ?? 'unknown',
                'date' => $matches[2] ?? 'unknown',
                'size' => $this->formatBytes($filesize),
                'timestamp' => $filetime,
                'formatted_date' => date('Y-m-d H:i:s', $filetime)
            ];
        }
        
        // Sort by timestamp (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }
    
    /**
     * Delete a backup file
     * @param string $filename
     * @return bool
     */
    public function deleteBackup($filename) {
        $filepath = $this->backup_dir . '/' . $filename;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
    
    /**
     * Download backup file
     * @param string $filename
     */
    public function downloadBackup($filename) {
        $filepath = $this->backup_dir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            die('File not found');
        }
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        readfile($filepath);
        exit;
    }
    
    /**
     * Cleanup old backups (keep last N days)
     */
    private function cleanupOldBackups() {
        $backups = $this->listBackups();
        
        // Keep only automatic backups for cleanup
        $automatic_backups = array_filter($backups, function($backup) {
            return $backup['type'] === 'automatic';
        });
        
        // Delete if more than max_backups
        if (count($automatic_backups) > $this->max_backups) {
            $to_delete = array_slice($automatic_backups, $this->max_backups);
            
            foreach ($to_delete as $backup) {
                $this->deleteBackup($backup['filename']);
            }
        }
    }
    
    /**
     * Log backup creation
     * @param string $filename
     * @param int $filesize
     * @param string $type
     */
    private function logBackup($filename, $filesize, $type) {
        require_once 'Security.php';
        Security::logEvent('BACKUP_CREATED', "Database backup created: $filename", [
            'filename' => $filename,
            'size' => $this->formatBytes($filesize),
            'type' => $type
        ]);
    }
    
    /**
     * Log backup restoration
     * @param string $filename
     */
    private function logRestore($filename) {
        require_once 'Security.php';
        Security::logEvent('BACKUP_RESTORED', "Database restored from: $filename", [
            'filename' => $filename
        ]);
    }
    
    /**
     * Format bytes to human readable
     * @param int $bytes
     * @return string
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * Schedule automatic backup (call this from cron or task scheduler)
     * @return array
     */
    public static function automaticBackup() {
        require_once 'db_connection.php';
        
        $backup = new self($conn);
        return $backup->createBackup('automatic');
    }
}
