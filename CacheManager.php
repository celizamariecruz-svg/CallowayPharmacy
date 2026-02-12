<?php
/**
 * Cache Manager - File-based caching for improved performance
 * Reduces database load by caching frequently accessed data
 */

class CacheManager {
    private static $instance = null;
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hour default
    private $enabled = true;
    private $memoryCache = [];
    
    // Cache keys for common data
    const CACHE_SETTINGS = 'settings';
    const CACHE_CATEGORIES = 'categories';
    const CACHE_SUPPLIERS = 'suppliers';
    const CACHE_PRODUCTS_COUNT = 'products_count';
    const CACHE_LOW_STOCK = 'low_stock';
    const CACHE_EXPIRING = 'expiring';
    const CACHE_DASHBOARD_STATS = 'dashboard_stats';
    
    private function __construct() {
        $this->cacheDir = __DIR__ . '/cache';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
            
            // Create .htaccess to protect cache files
            file_put_contents($this->cacheDir . '/.htaccess', 'Deny from all');
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enable/disable caching
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }
    
    /**
     * Get item from cache
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/expired
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        // Check memory cache first
        if (isset($this->memoryCache[$key])) {
            $data = $this->memoryCache[$key];
            if ($data['expires'] > time()) {
                return $data['value'];
            }
            unset($this->memoryCache[$key]);
        }
        
        // Check file cache
        $file = $this->getCacheFile($key);
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        $data = unserialize($content);
        
        if ($data === false || !isset($data['expires']) || $data['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        // Store in memory cache for subsequent requests
        $this->memoryCache[$key] = $data;
        
        return $data['value'];
    }
    
    /**
     * Set item in cache
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int $ttl Time to live in seconds (0 = use default)
     */
    public function set($key, $value, $ttl = 0) {
        if (!$this->enabled) {
            return false;
        }
        
        $ttl = $ttl > 0 ? $ttl : $this->defaultTTL;
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        // Store in memory
        $this->memoryCache[$key] = $data;
        
        // Store in file
        $file = $this->getCacheFile($key);
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    /**
     * Delete item from cache
     */
    public function delete($key) {
        unset($this->memoryCache[$key]);
        
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    /**
     * Check if key exists in cache
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Get or set cache (lazy loading pattern)
     * @param string $key Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int $ttl Time to live
     * @return mixed Cached or generated value
     */
    public function remember($key, $callback, $ttl = 0) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $this->memoryCache = [];
        
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Clear expired cache entries
     */
    public function clearExpired() {
        $files = glob($this->cacheDir . '/*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = unserialize($content);
            
            if ($data === false || !isset($data['expires']) || $data['expires'] < time()) {
                unlink($file);
                $cleared++;
            }
        }
        
        return $cleared;
    }
    
    /**
     * Invalidate cache by prefix
     */
    public function invalidateByPrefix($prefix) {
        // Clear from memory
        foreach (array_keys($this->memoryCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->memoryCache[$key]);
            }
        }
        
        // Clear from files
        $files = glob($this->cacheDir . '/' . $this->sanitizeKey($prefix) . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFile($key) {
        return $this->cacheDir . '/' . $this->sanitizeKey($key) . '.cache';
    }
    
    /**
     * Sanitize cache key for use as filename
     */
    private function sanitizeKey($key) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $validCount = 0;
        $expiredCount = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $content = file_get_contents($file);
            $data = unserialize($content);
            
            if ($data !== false && isset($data['expires']) && $data['expires'] > time()) {
                $validCount++;
            } else {
                $expiredCount++;
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_entries' => $validCount,
            'expired_entries' => $expiredCount,
            'total_size_bytes' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'memory_entries' => count($this->memoryCache)
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

/**
 * Helper function to get cache instance
 */
function cache() {
    return CacheManager::getInstance();
}

/**
 * Cached Settings Manager
 */
class CachedSettings {
    private static $settings = null;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get a setting value with caching
     */
    public function get($key, $default = null) {
        $settings = $this->getAllSettings();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Get all settings with caching
     */
    public function getAllSettings() {
        if (self::$settings !== null) {
            return self::$settings;
        }
        
        self::$settings = cache()->remember(CacheManager::CACHE_SETTINGS, function() {
            $settings = [];
            $result = $this->conn->query("SELECT setting_key, setting_value FROM settings");
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
            
            return $settings;
        }, 1800); // 30 minutes cache
        
        return self::$settings;
    }
    
    /**
     * Set a setting value and invalidate cache
     */
    public function set($key, $value) {
        $stmt = $this->conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = ?"
        );
        
        $stmt->bind_param('sss', $key, $value, $value);
        $result = $stmt->execute();
        
        if ($result) {
            // Invalidate cache
            cache()->delete(CacheManager::CACHE_SETTINGS);
            self::$settings = null;
        }
        
        return $result;
    }
    
    /**
     * Clear settings cache
     */
    public function clearCache() {
        cache()->delete(CacheManager::CACHE_SETTINGS);
        self::$settings = null;
    }
}
