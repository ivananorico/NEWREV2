<?php
/** revenue2/Login/config/database.php */
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $config = $this->getConfig();
        
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->db_name = $config['dbname'];
        $this->username = $config['user'];
        $this->password = $config['pass'];
    }

    private function getConfig() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // ============================================
        // PRODUCTION - DOMAIN SETUP (goserveph.com)
        // ============================================
        if (strpos($host, 'goserveph.com') !== false) {
            return [
                'host' => 'localhost',
                'port' => 3306,
                'dbname' => 'reve_users',
                'user' => 'reve_users',
                'pass' => 'sr7ExFuyk@h-9#bh'
            ];
        }
        
        // ============================================
        // PHYSICAL SERVER (192.168.1.10)
        // ============================================
        else if ($host === '192.168.1.10' || $host === '192.168.1.10:80') {
            return [
                'host' => 'localhost',
                'port' => 3306,  // Physical server MySQL port
                'dbname' => 'reve_users',  // Your production DB name
                'user' => 'reve_users',     // Your production username
                'pass' => 'sr7ExFuyk@h-9#bh' // Your production password
            ];
        }
        
        // ============================================
        // LOCALHOST (127.0.0.1, localhost) - YOUR XAMPP WITH PORT 3307
        // ============================================
        else {
            return [
                'host' => 'localhost',
                'port' => 3307,  // ✅ YOUR LOCAL MySQL port is 3307
                'dbname' => 'reve_users', // Use same DB name everywhere
                'user' => 'root',
                'pass' => ''  // XAMPP default password is empty
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
            
            // Log successful connection
            error_log("✅ Database connected to: " . $this->db_name . 
                     " on " . $this->host . ":" . $this->port);
            
        } catch(PDOException $exception) {
            // Log the error
            $error_msg = "Database connection failed: " . $exception->getMessage();
            error_log($error_msg);
            
            // Show error only on localhost for debugging
            if ($_SERVER['HTTP_HOST'] === 'localhost' || 
                $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
                $_SERVER['HTTP_HOST'] === '192.168.1.10') {
                echo "<script>console.error('Database Error: " . addslashes($exception->getMessage()) . "')</script>";
            }
        }
        
        return $this->conn;
    }

    /**
     * Check if database exists
     */
    public function checkDatabaseExists() {
        try {
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

    /**
     * Create database if it doesn't exist
     */
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