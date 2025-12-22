<?php
// Determine if we're in local or production environment
$isProduction = $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';

if ($isProduction) {
    // Production database credentials (get these from your hosting provider)
    $host = 'localhost';
    $port = 3306; // Usually 3306 in production
    $dbname = 'reve_business';
    $user = 'reve_business';
    $pass = 'qi-iO4C%IIvtG-1j';
} else {
    // Local development credentials
    $host = 'localhost';
    $port = 3307;
    $dbname = 'business_tax';
    $user = 'root';
    $pass = '';
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
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