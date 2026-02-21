<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../Login/config/database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email'])) {
        throw new Exception('Email is required');
    }
    
    $email = $data['email'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Find user by email
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
              WHERE email = :email AND role = 'user'
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode([
            'status' => 'success',
            'user' => $user,
            'found' => true
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'found' => false,
            'message' => 'No user found with this email'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>