<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../Login/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Fetch all users with role 'user' (citizens)
    $query = "SELECT 
                id,
                email,
                first_name,
                middle_name,
                last_name,
                suffix,
                mobile,
                role,
                status
              FROM users 
              WHERE role = 'user' 
              ORDER BY last_name, first_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'users' => $users
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>