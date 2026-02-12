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
        // PRODUCTION - DOMAIN ONLY (goserveph.com)
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
        // IP ADDRESS (192.168.1.10) - YOUR PHYSICAL SERVER
        // ============================================
        else if ($host === '192.168.1.10') {
            return [
                'host' => 'localhost',
                'port' => 3306,      // ✅ Your physical server MySQL on 3306
                'dbname' => 'users',
                'user' => 'root',
                'pass' => ''
            ];
        }
        
        // ============================================
        // LOCALHOST (localhost, 127.0.0.1) - YOUR DEV MACHINE
        // ============================================
        else {
            return [
                'host' => 'localhost',
                'port' => 3307,      // ✅ YOUR LOCAL MySQL is on 3307!
                'dbname' => 'users',
                'user' => 'root',
                'pass' => ''
            ];
        }
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . 
                   ";port=" . $this->port . 
                   ";dbname=" . $this->db_name . 
                   ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            error_log("✅ Database connected: " . $this->db_name . " on port " . $this->port);
            
        } catch(PDOException $exception) {
            error_log("❌ Database connection failed: " . $exception->getMessage());
            
            // Show error in console for debugging
            $current_host = $_SERVER['HTTP_HOST'] ?? '';
            echo "<script>console.error('Database Error: " . addslashes($exception->getMessage()) . "')</script>";
        }
        
        return $this->conn;
    }
}
?>