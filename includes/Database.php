<?php
/**
 * Database Connection and Utilities Class
 * Handles database connections, queries, and error management
 */

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $database;
    private $username;
    private $password;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        $this->connect();
    }
    
    /**
     * Get singleton instance of Database
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     * @throws Exception if connection fails
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }
    
    /**
     * Get PDO connection instance
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Test database connection with provided credentials
     * @param string $host
     * @param string $database
     * @param string $username
     * @param string $password
     * @return bool
     */
    public static function testConnection($host, $database, $username, $password) {
        try {
            $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo = null; // Close connection
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Execute a prepared statement
     * @param string $query
     * @param array $params
     * @return PDOStatement|false
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError("Query execution failed: " . $e->getMessage() . " Query: " . $query);
            return false;
        }
    }
    
    /**
     * Insert data and return last insert ID
     * @param string $query
     * @param array $params
     * @return string|false
     */
    public function insert($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->logError("Insert operation failed: " . $e->getMessage() . " Query: " . $query);
            return false;
        }
    }
    
    /**
     * Begin database transaction
     * @return bool
     */
    public function beginTransaction() {
        try {
            return $this->connection->beginTransaction();
        } catch (PDOException $e) {
            $this->logError("Failed to begin transaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Commit database transaction
     * @return bool
     */
    public function commit() {
        try {
            return $this->connection->commit();
        } catch (PDOException $e) {
            $this->logError("Failed to commit transaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rollback database transaction
     * @return bool
     */
    public function rollback() {
        try {
            return $this->connection->rollback();
        } catch (PDOException $e) {
            $this->logError("Failed to rollback transaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a table exists
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName) {
        try {
            $stmt = $this->connection->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError("Failed to check table existence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get database schema version for migrations
     * @return int
     */
    public function getSchemaVersion() {
        try {
            if (!$this->tableExists('schema_version')) {
                return 0;
            }
            
            $stmt = $this->connection->query("SELECT version FROM schema_version ORDER BY version DESC LIMIT 1");
            $result = $stmt->fetch();
            return $result ? (int)$result['version'] : 0;
        } catch (PDOException $e) {
            $this->logError("Failed to get schema version: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Log database errors
     * @param string $message
     */
    private function logError($message) {
        if (defined('LOG_ERRORS') && LOG_ERRORS) {
            $logFile = (defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/') . 'database_errors.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
            
            // Create logs directory if it doesn't exist
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>