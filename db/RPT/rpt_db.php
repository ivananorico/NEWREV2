<?php
$host = 'localhost';
$port = 3307;         // your MySQL port from dump
$dbname = 'reve_rpt';      // changed from market_rent to rpt
$user = 'reve_rpt';
$pass = '9A^jzp1k*J192zp+';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch as associative array
        PDO::ATTR_EMULATE_PREPARES => false, // use real prepared statements
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage()
    ]);
    exit;
}
?>