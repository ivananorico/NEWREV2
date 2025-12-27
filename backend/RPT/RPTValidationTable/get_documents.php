<?php
// get_documents.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include the RPT database connection - adjust the path as needed
require_once '../../../db/RPT/rpt_db.php';

// Get database connection
$pdo = getDatabaseConnection();

// Check if connection is successful
if (is_array($pdo) && isset($pdo['error'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $pdo['message']
    ]);
    exit();
}

try {
    // Get registration ID from query parameter
    $registration_id = $_GET['registration_id'] ?? null;
    
    if (!$registration_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Registration ID is required'
        ]);
        exit();
    }
    
    // Prepare and execute query - using correct table name: property_documents
    $stmt = $pdo->prepare("
        SELECT id, registration_id, document_type, file_name, 
               file_path, file_size, file_type, uploaded_by, created_at
        FROM property_documents 
        WHERE registration_id = ? 
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$registration_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the documents
    echo json_encode([
        'success' => true,
        'data' => [
            'documents' => $documents
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch documents',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
?>