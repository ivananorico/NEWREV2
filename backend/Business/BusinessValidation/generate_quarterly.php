<?php
// Your common CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use your existing database connection
require_once '../../../db/Business/business_db.php';

$response = ['status' => 'error', 'message' => ''];

try {
    // Get JSON data from request
    $json_data = file_get_contents("php://input");
    
    if (empty($json_data)) {
        throw new Exception("No data received");
    }
    
    $data = json_decode($json_data);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }
    
    if (!isset($data->business_permit_id) || !isset($data->total_annual_tax)) {
        throw new Exception("Missing required data: business_permit_id and total_annual_tax are required");
    }
    
    $business_permit_id = intval($data->business_permit_id);
    $total_annual_tax = floatval($data->total_annual_tax);
    $quarterly_amount = $total_annual_tax / 4;
    $current_year = date('Y');
    
    // Validate business permit exists
    $checkQuery = "SELECT id FROM business_permits WHERE id = ? AND status = 'Approved'";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$business_permit_id]);
    $permitExists = $checkStmt->fetch();
    
    if (!$permitExists) {
        throw new Exception("Business permit not found or not approved. ID: " . $business_permit_id);
    }
    
    // Check if quarterly taxes already exist
    $existingQuery = "SELECT id FROM business_quarterly_taxes WHERE business_permit_id = ? AND year = ?";
    $existingStmt = $pdo->prepare($existingQuery);
    $existingStmt->execute([$business_permit_id, $current_year]);
    
    if ($existingStmt->rowCount() > 0) {
        throw new Exception("Quarterly taxes already generated for this permit in " . $current_year);
    }
    
    // Define quarterly due dates
    $quarters = [
        ['Q1', $current_year . '-03-31'],
        ['Q2', $current_year . '-06-30'],
        ['Q3', $current_year . '-09-30'],
        ['Q4', $current_year . '-12-31']
    ];
    
    $pdo->beginTransaction();
    
    $quarterly_ids = [];
    foreach ($quarters as $quarter) {
        $query = "INSERT INTO business_quarterly_taxes 
                 (business_permit_id, quarter, year, due_date, total_quarterly_tax)
                 VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $business_permit_id,
            $quarter[0],
            $current_year,
            $quarter[1],
            $quarterly_amount
        ]);
        
        if (!$result) {
            throw new Exception("Failed to insert quarterly tax for " . $quarter[0]);
        }
        
        $quarterly_ids[] = [
            'quarter' => $quarter[0],
            'id' => $pdo->lastInsertId(),
            'amount' => $quarterly_amount,
            'due_date' => $quarter[1]
        ];
    }
    
    $pdo->commit();
    
    $response['status'] = 'success';
    $response['message'] = 'Quarterly taxes generated successfully';
    $response['quarters_generated'] = 4;
    $response['quarterly_amount'] = $quarterly_amount;
    $response['annual_amount'] = $total_annual_tax;
    $response['quarterly_ids'] = $quarterly_ids;
    $response['business_permit_id'] = $business_permit_id;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Error generating quarterly taxes: " . $e->getMessage());
}

echo json_encode($response);
?>