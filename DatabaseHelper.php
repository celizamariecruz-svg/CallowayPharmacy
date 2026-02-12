<?php
/**
 * Database Helper Class - Optimized Query Patterns
 * Provides prepared statements, caching, and optimized database operations
 */

class DatabaseHelper {
    private static $instance = null;
    private $conn;
    private $queryCache = [];
    private $cacheEnabled = true;
    private $cacheTimeout = 300; // 5 minutes
    private $preparedStatements = [];
    
    private function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance($connection = null) {
        if (self::$instance === null && $connection !== null) {
            self::$instance = new self($connection);
        }
        return self::$instance;
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Execute a prepared statement with automatic caching for SELECT queries
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @param string $types Parameter types (s=string, i=int, d=double, b=blob)
     * @param bool $cache Whether to cache results (only for SELECT)
     * @return array|bool Result array for SELECT, boolean for others
     */
    public function execute($query, $params = [], $types = '', $cache = true) {
        $isSelect = stripos(trim($query), 'SELECT') === 0;
        
        // Check cache for SELECT queries
        if ($isSelect && $cache && $this->cacheEnabled) {
            $cacheKey = $this->getCacheKey($query, $params);
            if (isset($this->queryCache[$cacheKey])) {
                $cached = $this->queryCache[$cacheKey];
                if (time() - $cached['time'] < $this->cacheTimeout) {
                    return $cached['data'];
                }
                unset($this->queryCache[$cacheKey]);
            }
        }
        
        // Get or create prepared statement
        $stmt = $this->getPreparedStatement($query);
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $this->conn->error);
            return false;
        }
        
        // Bind parameters if provided
        if (!empty($params)) {
            if (empty($types)) {
                $types = $this->inferTypes($params);
            }
            $stmt->bind_param($types, ...$params);
        }
        
        // Execute
        if (!$stmt->execute()) {
            error_log("Query execution failed: " . $stmt->error);
            return false;
        }
        
        // Get results
        if ($isSelect) {
            $result = $stmt->get_result();
            $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            
            // Cache results
            if ($cache && $this->cacheEnabled) {
                $cacheKey = $this->getCacheKey($query, $params);
                $this->queryCache[$cacheKey] = [
                    'data' => $data,
                    'time' => time()
                ];
            }
            
            return $data;
        }
        
        return true;
    }
    
    /**
     * Execute a single row query
     */
    public function fetchOne($query, $params = [], $types = '') {
        $result = $this->execute($query, $params, $types);
        return is_array($result) && count($result) > 0 ? $result[0] : null;
    }
    
    /**
     * Execute a scalar query (returns single value)
     */
    public function fetchScalar($query, $params = [], $types = '') {
        $row = $this->fetchOne($query, $params, $types);
        return $row ? reset($row) : null;
    }
    
    /**
     * Insert and return last insert ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->escapeIdentifier($table),
            implode(', ', array_map([$this, 'escapeIdentifier'], $columns)),
            implode(', ', $placeholders)
        );
        
        if ($this->execute($query, $values, '', false)) {
            $this->invalidateTableCache($table);
            return $this->conn->insert_id;
        }
        return false;
    }
    
    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = $this->escapeIdentifier($column) . ' = ?';
            $values[] = $value;
        }
        
        $values = array_merge($values, $whereParams);
        
        $query = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->escapeIdentifier($table),
            implode(', ', $setParts),
            $where
        );
        
        $result = $this->execute($query, $values, '', false);
        if ($result) {
            $this->invalidateTableCache($table);
        }
        return $result;
    }
    
    /**
     * Delete records
     */
    public function delete($table, $where, $params = []) {
        $query = sprintf(
            "DELETE FROM %s WHERE %s",
            $this->escapeIdentifier($table),
            $where
        );
        
        $result = $this->execute($query, $params, '', false);
        if ($result) {
            $this->invalidateTableCache($table);
        }
        return $result;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * Execute multiple statements in a transaction
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get or create prepared statement (caches statements)
     */
    private function getPreparedStatement($query) {
        $key = md5($query);
        
        if (!isset($this->preparedStatements[$key])) {
            $stmt = $this->conn->prepare($query);
            if ($stmt) {
                $this->preparedStatements[$key] = $stmt;
            } else {
                return false;
            }
        }
        
        return $this->preparedStatements[$key];
    }
    
    /**
     * Generate cache key for query
     */
    private function getCacheKey($query, $params) {
        return md5($query . serialize($params));
    }
    
    /**
     * Infer parameter types from values
     */
    private function inferTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_null($param)) {
                $types .= 's';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
    
    /**
     * Escape identifier (table/column name)
     */
    private function escapeIdentifier($identifier) {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    /**
     * Invalidate cache for a specific table
     */
    public function invalidateTableCache($table) {
        foreach ($this->queryCache as $key => $value) {
            if (stripos($key, $table) !== false) {
                unset($this->queryCache[$key]);
            }
        }
    }
    
    /**
     * Clear all cache
     */
    public function clearCache() {
        $this->queryCache = [];
    }
    
    /**
     * Enable/disable caching
     */
    public function setCacheEnabled($enabled) {
        $this->cacheEnabled = $enabled;
    }
    
    /**
     * Get affected rows from last query
     */
    public function affectedRows() {
        return $this->conn->affected_rows;
    }
    
    /**
     * Get last error
     */
    public function getError() {
        return $this->conn->error;
    }
    
    /**
     * Escape string for use in queries
     */
    public function escape($value) {
        return $this->conn->real_escape_string($value);
    }
    
    /**
     * Close all prepared statements on destruct
     */
    public function __destruct() {
        foreach ($this->preparedStatements as $stmt) {
            if ($stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
        }
    }
}

/**
 * Query Builder Class for complex queries
 */
class QueryBuilder {
    private $db;
    private $table;
    private $select = '*';
    private $where = [];
    private $whereParams = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    private $joins = [];
    private $groupBy = [];
    
    public function __construct(DatabaseHelper $db, $table) {
        $this->db = $db;
        $this->table = $table;
    }
    
    public function select($columns) {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }
    
    public function where($condition, $params = []) {
        $this->where[] = $condition;
        $this->whereParams = array_merge($this->whereParams, (array)$params);
        return $this;
    }
    
    public function orderBy($column, $direction = 'ASC') {
        $this->orderBy[] = "$column $direction";
        return $this;
    }
    
    public function limit($limit, $offset = null) {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }
    
    public function join($table, $condition, $type = 'INNER') {
        $this->joins[] = "$type JOIN $table ON $condition";
        return $this;
    }
    
    public function leftJoin($table, $condition) {
        return $this->join($table, $condition, 'LEFT');
    }
    
    public function groupBy($columns) {
        $this->groupBy = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    public function build() {
        $query = "SELECT {$this->select} FROM {$this->table}";
        
        if (!empty($this->joins)) {
            $query .= ' ' . implode(' ', $this->joins);
        }
        
        if (!empty($this->where)) {
            $query .= ' WHERE ' . implode(' AND ', $this->where);
        }
        
        if (!empty($this->groupBy)) {
            $query .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        if (!empty($this->orderBy)) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        
        if ($this->limit !== null) {
            $query .= ' LIMIT ' . (int)$this->limit;
            if ($this->offset !== null) {
                $query .= ' OFFSET ' . (int)$this->offset;
            }
        }
        
        return $query;
    }
    
    public function get() {
        return $this->db->execute($this->build(), $this->whereParams);
    }
    
    public function first() {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }
    
    public function count() {
        $originalSelect = $this->select;
        $this->select = 'COUNT(*) as count';
        $result = $this->first();
        $this->select = $originalSelect;
        return $result ? (int)$result['count'] : 0;
    }
}

// Initialize global database helper
function db() {
    global $conn;
    return DatabaseHelper::getInstance($conn);
}

// Quick query builder factory
function query($table) {
    return new QueryBuilder(db(), $table);
}
