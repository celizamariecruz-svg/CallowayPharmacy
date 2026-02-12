<?php
/**
 * Performance Monitor and Profiler
 * Track query execution times, memory usage, and performance bottlenecks
 */

class PerformanceMonitor {
    private static $instance = null;
    private $queries = [];
    private $timers = [];
    private $startTime;
    private $startMemory;
    private $enabled = true;
    
    private function __construct() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Enable/disable monitoring
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }
    
    /**
     * Start a timer
     */
    public function startTimer($name) {
        if (!$this->enabled) return;
        
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage()
        ];
    }
    
    /**
     * Stop a timer and return duration
     */
    public function stopTimer($name) {
        if (!$this->enabled || !isset($this->timers[$name])) {
            return 0;
        }
        
        $this->timers[$name]['end'] = microtime(true);
        $this->timers[$name]['memory_end'] = memory_get_usage();
        $this->timers[$name]['duration'] = $this->timers[$name]['end'] - $this->timers[$name]['start'];
        $this->timers[$name]['memory_diff'] = $this->timers[$name]['memory_end'] - $this->timers[$name]['memory_start'];
        
        return $this->timers[$name]['duration'];
    }
    
    /**
     * Log a database query
     */
    public function logQuery($query, $duration, $rowCount = null) {
        if (!$this->enabled) return;
        
        $this->queries[] = [
            'query' => $this->truncateQuery($query),
            'duration' => $duration,
            'rows' => $rowCount,
            'time' => microtime(true)
        ];
    }
    
    /**
     * Truncate long queries for logging
     */
    private function truncateQuery($query, $maxLength = 500) {
        $query = trim(preg_replace('/\s+/', ' ', $query));
        if (strlen($query) > $maxLength) {
            return substr($query, 0, $maxLength) . '...';
        }
        return $query;
    }
    
    /**
     * Get total execution time
     */
    public function getTotalTime() {
        return microtime(true) - $this->startTime;
    }
    
    /**
     * Get memory usage
     */
    public function getMemoryUsage() {
        return memory_get_usage() - $this->startMemory;
    }
    
    /**
     * Get peak memory usage
     */
    public function getPeakMemory() {
        return memory_get_peak_usage();
    }
    
    /**
     * Get total query time
     */
    public function getTotalQueryTime() {
        $total = 0;
        foreach ($this->queries as $query) {
            $total += $query['duration'];
        }
        return $total;
    }
    
    /**
     * Get query count
     */
    public function getQueryCount() {
        return count($this->queries);
    }
    
    /**
     * Get slow queries (over threshold)
     */
    public function getSlowQueries($threshold = 0.1) {
        return array_filter($this->queries, function($q) use ($threshold) {
            return $q['duration'] > $threshold;
        });
    }
    
    /**
     * Get performance summary
     */
    public function getSummary() {
        $totalTime = $this->getTotalTime();
        $queryTime = $this->getTotalQueryTime();
        
        return [
            'total_time' => round($totalTime * 1000, 2) . ' ms',
            'query_time' => round($queryTime * 1000, 2) . ' ms',
            'php_time' => round(($totalTime - $queryTime) * 1000, 2) . ' ms',
            'query_count' => $this->getQueryCount(),
            'memory_usage' => $this->formatBytes($this->getMemoryUsage()),
            'peak_memory' => $this->formatBytes($this->getPeakMemory()),
            'slow_queries' => count($this->getSlowQueries())
        ];
    }
    
    /**
     * Get detailed report
     */
    public function getDetailedReport() {
        return [
            'summary' => $this->getSummary(),
            'timers' => array_map(function($t) {
                return [
                    'duration' => isset($t['duration']) ? round($t['duration'] * 1000, 2) . ' ms' : 'running',
                    'memory' => isset($t['memory_diff']) ? $this->formatBytes($t['memory_diff']) : 'N/A'
                ];
            }, $this->timers),
            'queries' => array_map(function($q) {
                return [
                    'query' => $q['query'],
                    'duration' => round($q['duration'] * 1000, 2) . ' ms',
                    'rows' => $q['rows']
                ];
            }, $this->queries)
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while (abs($bytes) >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Output debug bar (for development)
     */
    public function renderDebugBar() {
        if (!$this->enabled || (defined('IS_PRODUCTION') && IS_PRODUCTION)) {
            return '';
        }
        
        $summary = $this->getSummary();
        $slowQueries = $this->getSlowQueries();
        
        ob_start();
        ?>
        <div id="perf-debug-bar" style="
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1e1e1e;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 8px 16px;
            z-index: 99999;
            display: flex;
            gap: 24px;
            align-items: center;
            border-top: 2px solid #4caf50;
        ">
            <span style="color: #4caf50;">⚡ Performance</span>
            <span>Time: <strong><?= $summary['total_time'] ?></strong></span>
            <span>Queries: <strong><?= $summary['query_count'] ?></strong> (<?= $summary['query_time'] ?>)</span>
            <span>Memory: <strong><?= $summary['peak_memory'] ?></strong></span>
            <?php if ($summary['slow_queries'] > 0): ?>
            <span style="color: #ff9800;">⚠ <?= $summary['slow_queries'] ?> slow queries</span>
            <?php endif; ?>
            <button onclick="document.getElementById('perf-debug-details').style.display = document.getElementById('perf-debug-details').style.display === 'none' ? 'block' : 'none'" style="
                margin-left: auto;
                background: #333;
                border: 1px solid #555;
                color: #fff;
                padding: 4px 12px;
                cursor: pointer;
                border-radius: 4px;
            ">Details</button>
        </div>
        <div id="perf-debug-details" style="
            display: none;
            position: fixed;
            bottom: 40px;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow: auto;
            background: #252525;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            padding: 16px;
            z-index: 99998;
            border-top: 1px solid #333;
        ">
            <h4 style="margin: 0 0 12px; color: #4caf50;">Queries (<?= count($this->queries) ?>)</h4>
            <?php foreach ($this->queries as $i => $q): ?>
            <div style="margin-bottom: 8px; padding: 8px; background: #1e1e1e; border-radius: 4px; <?= $q['duration'] > 0.1 ? 'border-left: 3px solid #ff9800;' : '' ?>">
                <span style="color: #888;">#<?= $i + 1 ?></span>
                <span style="color: <?= $q['duration'] > 0.1 ? '#ff9800' : '#4caf50' ?>;"><?= round($q['duration'] * 1000, 2) ?>ms</span>
                <?php if ($q['rows'] !== null): ?>
                <span style="color: #888;">(<?= $q['rows'] ?> rows)</span>
                <?php endif; ?>
                <div style="color: #ddd; margin-top: 4px; word-break: break-all;"><?= htmlspecialchars($q['query']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Log performance data to file
     */
    public function logToFile($file = null) {
        if ($file === null) {
            $file = __DIR__ . '/logs/performance.log';
        }
        
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'summary' => $this->getSummary()
        ];
        
        file_put_contents(
            $file, 
            json_encode($log) . "\n", 
            FILE_APPEND | LOCK_EX
        );
    }
}

/**
 * Wrapper for monitored database queries
 */
class MonitoredQuery {
    private $conn;
    private $monitor;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->monitor = PerformanceMonitor::getInstance();
    }
    
    /**
     * Execute query with monitoring
     */
    public function query($sql) {
        $start = microtime(true);
        $result = $this->conn->query($sql);
        $duration = microtime(true) - $start;
        
        $rowCount = $result instanceof mysqli_result ? $result->num_rows : $this->conn->affected_rows;
        $this->monitor->logQuery($sql, $duration, $rowCount);
        
        return $result;
    }
    
    /**
     * Prepare statement with monitoring
     */
    public function prepare($sql) {
        return new MonitoredStatement($this->conn->prepare($sql), $sql, $this->monitor);
    }
}

/**
 * Wrapper for monitored prepared statements
 */
class MonitoredStatement {
    private $stmt;
    private $sql;
    private $monitor;
    
    public function __construct($stmt, $sql, $monitor) {
        $this->stmt = $stmt;
        $this->sql = $sql;
        $this->monitor = $monitor;
    }
    
    public function bind_param($types, &...$params) {
        return $this->stmt->bind_param($types, ...$params);
    }
    
    public function execute() {
        $start = microtime(true);
        $result = $this->stmt->execute();
        $duration = microtime(true) - $start;
        
        $this->monitor->logQuery($this->sql, $duration, $this->stmt->affected_rows);
        
        return $result;
    }
    
    public function get_result() {
        return $this->stmt->get_result();
    }
    
    public function close() {
        return $this->stmt->close();
    }
    
    public function __get($name) {
        return $this->stmt->$name;
    }
}

// Helper function
function perf() {
    return PerformanceMonitor::getInstance();
}
