<?php
// Enable CORS with proper headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include your database connection
require_once '../../../db/Business/business_db.php';

try {
    // Get all business permits with PENDING status
    // Using correct column names from your business_permits table
    $sql = "SELECT 
                id,
                business_permit_id,
                business_name,
                owner_name,
                business_type,
                tax_calculation_type,
                taxable_amount,
                tax_rate,
                tax_amount,
                regulatory_fees,
                total_tax,
                tax_calculated,
                tax_approved,
                approved_date,
                address,
                contact_number,
                phone,
                issue_date,
                expiry_date,
                status,
                created_at,
                updated_at,
                user_id
            FROM business_permits 
            WHERE status = 'Pending'
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'status' => 'success',
        'message' => 'Pending business permits retrieved successfully',
        'permits' => $permits,
        'count' => count($permits)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'permits' => [],
        'count' => 0
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'permits' => [],
        'count' => 0
    ]);
}
?>