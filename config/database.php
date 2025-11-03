<?php
// Database configuration for GoSocially
// Secure connection settings

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gosocially');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security settings
define('SECURE_SESSION', true);
define('HTTP_ONLY', true);
define('SESSION_LIFETIME', 7200); // 2 hours in seconds

// Application settings
define('SITE_NAME', 'GoSocially');
define('ADMIN_EMAIL', 'admin@gosocially.com');

// Error reporting (disable in production)
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Database connection class
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error and show user-friendly message
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function for database queries
function dbQuery($sql, $params = []) {
    $db = Database::getInstance()->getConnection();
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query failed: " . $e->getMessage() . " SQL: " . $sql);
        throw new Exception("Database error occurred.");
    }
}

// Helper function to get single row
function dbGetRow($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

// Helper function to get multiple rows
function dbGetAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

// Helper function for insert/update/delete
function dbExecute($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

// Helper function to get last insert ID
function dbLastInsertId() {
    return Database::getInstance()->getConnection()->lastInsertId();
}

// Backwards-compatibility: expose a global $pdo variable for older files
// that expect a PDO instance in the global scope (e.g., legacy code).
// Prefer using the Database class or db* helpers in new code.
$pdo = Database::getInstance()->getConnection();
?>