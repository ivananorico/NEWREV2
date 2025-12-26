<?php
// revenue2/Login/api/test_db.php
header('Content-Type: application/json');

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo json_encode([
        'success' => true,
        'message' => 'Database connected successfully!',
        'database' => 'reve_users',
        'host' => 'localhost'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed!',
        'error' => 'Check your database credentials'
    ]);
}
?>