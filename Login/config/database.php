<?php
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Get configuration based on environment
        $config = $this->getConfig();
        
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->db_name = $config['dbname'];
        $this->username = $config['user'];
        $this->password = $config['pass'];
    }

    private function getConfig() {
        // Check if we're on production (domain) or localhost
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // For your domain
        $isProduction = $host === 'revenuetreasury.goserveph.com' || 
                       $host === 'www.revenuetreasury.goserveph.com';
        
        if ($isProduction) {
            // PRODUCTION SETTINGS (Domain)
            return [
                'host' => 'localhost',
                'port' => 3306,  // Production usually uses default port
                'dbname' => 'reve_users',
                'user' => 'reve_users',
                'pass' => '8JioyEPxDfe44hEc'
            ];
        } else {
            // LOCALHOST SETTINGS (XAMPP)
            return [
                'host' => 'localhost',
                'port' => 3307,  // XAMPP default port
                'dbname' => 'users',
                'user' => 'root',  // XAMPP default user
                'pass' => ''       // XAMPP default password (empty)
            ];
        }
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            // Create DSN with port
            $dsn = "mysql:host=" . $this->host . 
                   ";port=" . $this->port . 
                   ";dbname=" . $this->db_name . 
                   ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // Log successful connection (for debugging)
            error_log("✅ Database connected to: " . $this->db_name . 
                     " on " . $this->host . ":" . $this->port);
            
        } catch(PDOException $exception) {
            // Log the error but don't expose details
            $error_msg = "Database connection failed: " . $exception->getMessage();
            error_log($error_msg);
            
            // For debugging on localhost, you can see the error
            if ($_SERVER['HTTP_HOST'] === 'localhost' || 
                $_SERVER['HTTP_HOST'] === '127.0.0.1') {
                echo "<script>console.error('Database Error: " . addslashes($exception->getMessage()) . "')</script>";
            }
        }
        
        return $this->conn;
    }

    // Helper method to check if database exists
    public function checkDatabaseExists() {
        try {
            // Try to connect to MySQL server without database
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port;
            $temp_conn = new PDO($dsn, $this->username, $this->password);
            $temp_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $temp_conn->query("SHOW DATABASES LIKE '" . $this->db_name . "'");
            return $stmt->rowCount() > 0;
            
        } catch(PDOException $e) {
            error_log("Check database error: " . $e->getMessage());
            return false;
        }
    }

    // Helper method to create database if it doesn't exist
    public function createDatabaseIfNotExists() {
        if (!$this->checkDatabaseExists()) {
            try {
                $dsn = "mysql:host=" . $this->host . ";port=" . $this->port;
                $temp_conn = new PDO($dsn, $this->username, $this->password);
                $temp_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $sql = "CREATE DATABASE IF NOT EXISTS `" . $this->db_name . "` 
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                $temp_conn->exec($sql);
                
                error_log("✅ Database created: " . $this->db_name);
                return true;
                
            } catch(PDOException $e) {
                error_log("❌ Create database error: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }
}
?>