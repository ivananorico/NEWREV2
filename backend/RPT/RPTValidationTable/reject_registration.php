<?php
// revenue2/backend/RPT/RPTValidationTable/reject_registration.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Create database connection using the config from rpt_db.php
function createPDOConnection() {
    $config = getDatabaseConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error but don't expose details to user
        error_log("Database connection failed: " . $e->getMessage());
        
        // Return user-friendly error
        return [
            'error' => true,
            'message' => 'Database connection failed. Please try again later.',
            'debug' => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getMessage() : null
        ];
    }
}

require_once '../../../db/RPT/rpt_db.php';

$pdo = createPDOConnection();

if (is_array($pdo) && isset($pdo['error'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $registration_id = $data['registration_id'] ?? null;
    $status = $data['status'] ?? 'needs_correction';
    $correction_notes = $data['correction_notes'] ?? '';
    
    if (!$registration_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Registration ID is required'
        ]);
        exit();
    }
    
    $stmt = $pdo->prepare("
        UPDATE property_registrations 
        SET status = ?, 
            correction_notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$status, $correction_notes, $registration_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Application marked as needs correction'
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>