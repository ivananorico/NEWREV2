<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include your PDO DB connection
require_once '../../db/Business/business_db.php';

// ===== Get the database connection =====
$pdo = getDatabaseConnection(); // Call the function to get connection

// Check if connection was successful
if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    importBusinessPermitFromPostman();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// ==================== IMPORT BUSINESS PERMIT FROM POSTMAN ====================
function importBusinessPermitFromPostman() {
    global $pdo;
    
    // Get POST data from Postman
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No JSON data received']);
        exit();
    }

    // Validate required fields based on YOUR database structure
    $required_fields = ['applicant_id', 'business_name', 'owner_full_name', 'business_nature'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
        ]);
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        $applicant_id = $input['applicant_id'];
        
        // First, check if permit already exists (prevent duplication)
        $checkSql = "SELECT id FROM business_permits WHERE applicant_id = :applicant_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':applicant_id' => $applicant_id]);
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            $pdo->commit();
            echo json_encode([
                'success' => false,
                'message' => 'Business permit already exists with applicant_id: ' . $applicant_id,
                'record_id' => $existingRecord['id']
            ]);
            exit();
        }
        
        // Calculate tax based on capital investment
        $capital_investment = floatval($input['capital_investment'] ?? 0);
        
        // Determine tax rate based on capital (from your capital_investment_tax_config table)
        if ($capital_investment <= 5000) {
            $tax_rate = 20.00;
        } elseif ($capital_investment <= 10000) {
            $tax_rate = 25.00;
        } elseif ($capital_investment <= 15000) {
            $tax_rate = 25.00;
        } elseif ($capital_investment <= 20000) {
            $tax_rate = 25.00;
        } else {
            $tax_rate = 25.00; // Default for > 20,000
        }
        
        $tax_amount = ($capital_investment * $tax_rate) / 100;
        $regulatory_fees = 499.98 + 500 + 300; // Mayor + Sanitary + Registration
        $total_tax = $tax_amount + $regulatory_fees;
        
        // Dates
        $issue_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime('+1 year'));
        $application_date = $input['application_date'] ?? $issue_date;
        
        // Insert into business_permits table (CORRECTED for your database structure)
        $sql = "INSERT INTO business_permits (
            applicant_id, 
            business_name, 
            owner_full_name, 
            owner_type, 
            business_nature, 
            trade_name, 
            business_barangay, 
            business_district, 
            business_city, 
            business_province, 
            business_zipcode, 
            contact_number, 
            email_address, 
            capital_investment, 
            tax_calculation_type,
            taxable_amount, 
            tax_rate, 
            tax_amount, 
            regulatory_fees, 
            total_tax,
            application_date, 
            permit_status, 
            tax_status, 
            issue_date, 
            expiry_date,
            created_at,
            updated_at
        ) VALUES (
            :applicant_id, 
            :business_name, 
            :owner_full_name, 
            :owner_type, 
            :business_nature, 
            :trade_name, 
            :business_barangay, 
            :business_district, 
            :business_city, 
            :business_province, 
            :business_zipcode, 
            :contact_number, 
            :email_address, 
            :capital_investment, 
            :tax_calculation_type,
            :taxable_amount, 
            :tax_rate, 
            :tax_amount, 
            :regulatory_fees, 
            :total_tax,
            :application_date, 
            :permit_status, 
            :tax_status, 
            :issue_date, 
            :expiry_date,
            NOW(),
            NOW()
        )";

        $stmt = $pdo->prepare($sql);
        
        $params = [
            // Required fields
            ':applicant_id' => htmlspecialchars($applicant_id),
            ':business_name' => htmlspecialchars($input['business_name']),
            ':owner_full_name' => htmlspecialchars($input['owner_full_name']),
            ':owner_type' => htmlspecialchars($input['owner_type'] ?? 'Individual'),
            ':business_nature' => htmlspecialchars($input['business_nature']),
            
            // Optional fields with defaults
            ':trade_name' => htmlspecialchars($input['trade_name'] ?? null),
            ':business_barangay' => htmlspecialchars($input['business_barangay'] ?? $input['barangay'] ?? ''),
            ':business_district' => htmlspecialchars($input['business_district'] ?? $input['district'] ?? ''),
            ':business_city' => htmlspecialchars($input['business_city'] ?? $input['city_municipality'] ?? $input['city'] ?? ''),
            ':business_province' => htmlspecialchars($input['business_province'] ?? $input['province'] ?? ''),
            ':business_zipcode' => htmlspecialchars($input['business_zipcode'] ?? $input['zip_code'] ?? $input['zipcode'] ?? ''),
            ':contact_number' => htmlspecialchars($input['contact_number'] ?? ''),
            ':email_address' => htmlspecialchars($input['email_address'] ?? ''),
            
            // Financial fields
            ':capital_investment' => $capital_investment,
            ':tax_calculation_type' => 'capital_investment',
            ':taxable_amount' => $capital_investment,
            ':tax_rate' => $tax_rate,
            ':tax_amount' => $tax_amount,
            ':regulatory_fees' => $regulatory_fees,
            ':total_tax' => $total_tax,
            
            // Dates
            ':application_date' => $application_date,
            ':permit_status' => htmlspecialchars($input['permit_status'] ?? 'PENDING'),
            ':tax_status' => htmlspecialchars($input['tax_status'] ?? 'Pending'),
            ':issue_date' => $issue_date,
            ':expiry_date' => $expiry_date
        ];

        $stmt->execute($params);
        $recordId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Business permit imported successfully from Postman',
            'record_id' => $recordId,
            'applicant_id' => $applicant_id,
            'business_name' => $input['business_name'],
            'tax_calculation' => [
                'capital_investment' => $capital_investment,
                'tax_rate' => $tax_rate . '%',
                'tax_amount' => $tax_amount,
                'regulatory_fees' => $regulatory_fees,
                'total_tax' => $total_tax,
                'issue_date' => $issue_date,
                'expiry_date' => $expiry_date
            ]
        ]);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        
        // Log the error
        error_log("Postman Import Error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to import business permit: ' . $e->getMessage(),
            'error_details' => $e->getMessage()
        ]);
    }
}
?>