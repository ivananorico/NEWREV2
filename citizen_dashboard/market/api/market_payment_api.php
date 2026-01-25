<?php
// revenue2/citizen_dashboard/market/api/market_payment_api.php

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: application/json');

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log the raw input
error_log("=== RAW INPUT ===");
error_log($input);
error_log("=== END RAW INPUT ===");

// If JSON input is empty, try form data
if (empty($data)) {
    $data = $_POST;
    error_log("Using POST data instead of JSON");
}

// Log all received data for debugging
error_log("=== RECEIVED DATA ===");
error_log(print_r($data, true));
error_log("=== END RECEIVED DATA ===");

// Extract data
$reference_id = $data['reference_id'] ?? null;  
$receipt_number = $data['receipt_number'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');
$amount = $data['amount'] ?? null;
$purpose = $data['purpose'] ?? null;
$client_system = $data['client_system'] ?? null;
$payment_id = $data['payment_id'] ?? null;
$payment_type = $data['payment_type'] ?? 'monthly'; // 'monthly' or 'annual'
$registration_id = $data['registration_id'] ?? null; // For annual payments
$year = $data['year'] ?? date('Y'); // For annual payments
$phone = $data['phone'] ?? null;

// Check if we have the ref parameter (from GCash form)
if (!$reference_id && isset($data['ref'])) {
    $reference_id = $data['ref'];
    error_log("Using ref parameter as reference_id: $reference_id");
}

// Log extracted values
error_log("=== EXTRACTED VALUES ===");
error_log("Payment Type: $payment_type");
error_log("Reference ID: $reference_id");
error_log("Receipt: $receipt_number");
error_log("Amount: $amount");
error_log("Client System: $client_system");
error_log("Payment Type Field: $payment_type");
error_log("Registration ID: $registration_id");
error_log("Year: $year");
error_log("=== END EXTRACTED VALUES ===");

// Validate required fields
if (!$reference_id || !$receipt_number) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required fields: reference_id and receipt_number are required',
        'received_data' => $data
    ]);
    exit();
}

// Verify this is for market rent system
// Check both client_system and ref parameter
$is_market_system = false;
if ($client_system === 'market_rent') {
    $is_market_system = true;
    error_log("Client system identified as market_rent");
} elseif (strpos($reference_id, 'annual_') === 0) {
    $is_market_system = true;
    error_log("Reference ID indicates market annual payment");
} elseif (is_numeric($reference_id)) {
    // Check if this is a monthly billing ID
    $is_market_system = true;
    error_log("Reference ID is numeric, assuming market monthly payment");
}

if (!$is_market_system) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'This API is for market rent system only.',
        'received_client_system' => $client_system,
        'reference_id' => $reference_id
    ]);
    exit();
}

// Include the market database connection
$market_db_path = __DIR__ . '/../../../db/Market/market_db.php';

if (!file_exists($market_db_path)) {
    error_log("Market database file NOT found at: " . $market_db_path);
    
    // Try alternative paths
    $alt_paths = [
        __DIR__ . '/../../../../db/Market/market_db.php',
        'C:/xampp/htdocs/revenue2/db/Market/market_db.php'
    ];
    
    foreach ($alt_paths as $path) {
        error_log("Checking alternative: " . $path);
        if (file_exists($path)) {
            $market_db_path = $path;
            error_log("Found market database file at: " . $path);
            break;
        }
    }
    
    if (!file_exists($market_db_path)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Market database configuration not found'
        ]);
        exit();
    }
}

include_once $market_db_path;

try {
    // Get database connection from market_db.php
    global $pdo; // Use the global $pdo from market_db.php
    
    if (!$pdo) {
        throw new Exception('Failed to connect to Market database.');
    }
    
    // Test the connection
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ============================================
    // CHECK IF THIS IS AN ANNUAL PAYMENT
    // ============================================
    $is_annual_payment = false;
    
    // Check multiple ways to identify annual payment
    if ($payment_type === 'annual') {
        $is_annual_payment = true;
        error_log("Payment type is 'annual'");
    } elseif (strpos($reference_id, 'annual_') === 0) {
        $is_annual_payment = true;
        error_log("Reference ID starts with 'annual_'");
    } elseif (isset($data['annual']) && $data['annual'] == '1') {
        $is_annual_payment = true;
        error_log("Annual flag is set");
    }
    
    if ($is_annual_payment) {
        error_log("Processing as ANNUAL payment");
        
        // Extract registration_id and year from reference_id
        if (strpos($reference_id, 'annual_') === 0) {
            $parts = explode('_', $reference_id);
            if (count($parts) >= 3) {
                $registration_id = $parts[1];
                $year = $parts[2];
                error_log("Extracted from reference_id - Registration ID: $registration_id, Year: $year");
            }
        }
        
        // If registration_id not set, try to get from data
        if (!$registration_id && isset($data['registration_id'])) {
            $registration_id = $data['registration_id'];
            error_log("Using registration_id from data: $registration_id");
        }
        
        if (!$registration_id) {
            error_log("ERROR: No registration_id found for annual payment");
            echo json_encode([
                'status' => 'error',
                'message' => 'Registration ID is required for annual payments',
                'reference_id' => $reference_id,
                'data_received' => $data
            ]);
            exit();
        }
        
        if (!$year) {
            $year = date('Y');
            error_log("Using current year: $year");
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // 1. FIND THE ANNUAL PAYMENT RECORD
            error_log("Looking for annual payment record with registration_id: $registration_id, year: $year");
            
            $annual_check_query = "
                SELECT 
                    map.*,
                    rt.id as rent_total_id,
                    rs.id as rent_stall_id,
                    rs.business_name,
                    rs.stall_rights_no,
                    ro.renter_code
                FROM market_annual_payments map
                JOIN rent_totals rt ON map.registration_id = rt.registration_id
                JOIN rent_stall rs ON rt.registration_id = rs.registration_id
                JOIN renter_owner ro ON rs.renter_id = ro.id
                WHERE map.registration_id = :registration_id
                AND map.payment_year = :year
                AND map.status = 'active'
            ";
            
            $annual_stmt = $pdo->prepare($annual_check_query);
            $annual_stmt->execute([
                ':registration_id' => $registration_id,
                ':year' => $year
            ]);
            $annual_payment = $annual_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$annual_payment) {
                error_log("ERROR: Annual payment record not found");
                throw new Exception("Annual payment record not found for registration $registration_id and year $year");
            }
            
            error_log("Found annual payment: ID={$annual_payment['id']}, Base Amount={$annual_payment['base_amount']}, Final Amount={$annual_payment['final_amount']}");
            
            // 2. CHECK IF MONTHLY BILLS ARE ALREADY PAID
            $rent_total_id = $annual_payment['rent_total_id'];
            
            $check_monthly_paid_query = "
                SELECT COUNT(*) as paid_count 
                FROM monthly_rent_billing 
                WHERE rent_total_id = :rent_total_id 
                AND billing_year = :year 
                AND payment_status = 'paid'
            ";
            
            $check_paid_stmt = $pdo->prepare($check_monthly_paid_query);
            $check_paid_stmt->execute([
                ':rent_total_id' => $rent_total_id,
                ':year' => $year
            ]);
            $paid_status = $check_paid_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($paid_status['paid_count'] > 0) {
                error_log("ERROR: Some months already paid. Paid count: {$paid_status['paid_count']}");
                throw new Exception("Cannot process annual payment. Some months are already paid.");
            }
            
            // 3. UPDATE ANNUAL PAYMENT RECORD WITH NEW RECEIPT
            // In market system, annual payment may already have a receipt from stall rights
            // We need to add the rent payment receipt
            $new_receipt_info = "Rent Payment: $receipt_number";
            $current_receipt = $annual_payment['receipt_number'] ?? '';
            
            if (!empty($current_receipt)) {
                $updated_receipt = $current_receipt . " | " . $new_receipt_info;
            } else {
                $updated_receipt = $new_receipt_info;
            }
            
            $annual_update_query = "
                UPDATE market_annual_payments 
                SET receipt_number = :updated_receipt,
                    transaction_id = :payment_id,
                    updated_at = NOW()
                WHERE id = :annual_id
            ";
            
            $annual_update_stmt = $pdo->prepare($annual_update_query);
            $annual_update_stmt->execute([
                ':updated_receipt' => $updated_receipt,
                ':payment_id' => $payment_id,
                ':annual_id' => $annual_payment['id']
            ]);
            
            $annual_rows_updated = $annual_update_stmt->rowCount();
            
            error_log("Annual payment record updated: $annual_rows_updated rows");
            
            // 4. GET ALL MONTHLY BILLINGS
            $monthly_query = "
                SELECT 
                    mrb.id,
                    mrb.payment_status,
                    mrb.base_rent,
                    mrb.total_amount_due,
                    mrb.billing_month,
                    mrb.billing_year
                FROM monthly_rent_billing mrb
                WHERE mrb.rent_total_id = :rent_total_id
                AND mrb.billing_year = :year
                ORDER BY mrb.billing_month
            ";
            
            $monthly_stmt = $pdo->prepare($monthly_query);
            $monthly_stmt->execute([
                ':rent_total_id' => $rent_total_id,
                ':year' => $year
            ]);
            $monthly_billings = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($monthly_billings)) {
                throw new Exception("No monthly billings found for year $year");
            }
            
            error_log("Found " . count($monthly_billings) . " monthly billings");
            
            // 5. UPDATE ALL MONTHLY BILLINGS
            $update_monthly_query = "
                UPDATE monthly_rent_billing 
                SET payment_status = 'paid',
                    payment_date = :paid_at,
                    receipt_number = :receipt_number
                WHERE rent_total_id = :rent_total_id
                AND billing_year = :year
                AND payment_status != 'paid'
            ";
            
            $monthly_update_stmt = $pdo->prepare($update_monthly_query);
            $monthly_update_stmt->execute([
                ':paid_at' => $paid_at,
                ':receipt_number' => $receipt_number,
                ':rent_total_id' => $rent_total_id,
                ':year' => $year
            ]);
            
            $monthly_rows_updated = $monthly_update_stmt->rowCount();
            
            error_log("Monthly billings updated: $monthly_rows_updated rows");
            
            if ($monthly_rows_updated === 0) {
                error_log("WARNING: No monthly bills were updated (maybe already paid?)");
            }
            
            // 6. LOG PAYMENTS FOR EACH MONTH
            $logged_count = 0;
            foreach ($monthly_billings as $billing) {
                if ($billing['payment_status'] != 'paid') {
                    $log_query = "
                        INSERT INTO market_payment_logs 
                        (monthly_billing_id, receipt_number, amount_paid, payment_date, payment_method, renter_code, stall_rights_no, business_name, created_at)
                        VALUES 
                        (:monthly_billing_id, :receipt_number, :amount_paid, :payment_date, 'online', :renter_code, :stall_rights_no, :business_name, NOW())
                    ";
                    
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([
                        ':monthly_billing_id' => $billing['id'],
                        ':receipt_number' => $receipt_number,
                        ':amount_paid' => $billing['total_amount_due'],
                        ':payment_date' => $paid_at,
                        ':renter_code' => $annual_payment['renter_code'],
                        ':stall_rights_no' => $annual_payment['stall_rights_no'],
                        ':business_name' => $annual_payment['business_name']
                    ]);
                    
                    $logged_count++;
                }
            }
            
            error_log("Payment logs created: $logged_count records");
            
            // 7. GENERATE MARKET RENT CLEARANCE
            $clearance_generated = false;
            $certificate_number = null;
            
            try {
                createMarketClearanceTable($pdo);
                
                // Check if clearance already exists
                $check_clearance_query = "
                    SELECT id FROM market_rent_clearances 
                    WHERE rent_total_id = :rent_total_id 
                    AND clearance_year = :year
                ";
                $clearance_stmt = $pdo->prepare($check_clearance_query);
                $clearance_stmt->execute([
                    ':rent_total_id' => $rent_total_id,
                    ':year' => $year
                ]);
                
                if ($clearance_stmt->rowCount() == 0) {
                    $certificate_number = 'MRC-' . date('Y') . '-' . str_pad($rent_total_id, 6, '0', STR_PAD_LEFT) . '-' . $year;
                    
                    $insert_clearance_query = "
                        INSERT INTO market_rent_clearances 
                        (certificate_number, rent_stall_id, rent_total_id, clearance_year, issue_date, created_at)
                        VALUES (:cert_number, :rent_stall_id, :rent_total_id, :year, :issue_date, NOW())
                    ";
                    
                    $insert_clearance_stmt = $pdo->prepare($insert_clearance_query);
                    $insert_clearance_stmt->execute([
                        ':cert_number' => $certificate_number,
                        ':rent_stall_id' => $annual_payment['rent_stall_id'],
                        ':rent_total_id' => $rent_total_id,
                        ':year' => $year,
                        ':issue_date' => $paid_at
                    ]);
                    
                    $clearance_generated = true;
                    error_log("Market rent clearance generated: $certificate_number");
                } else {
                    error_log("Market rent clearance already exists");
                }
            } catch (Exception $clearance_error) {
                error_log("Clearance generation error: " . $clearance_error->getMessage());
            }
            
            // Commit transaction
            $pdo->commit();
            
            error_log("=== ANNUAL PAYMENT SUCCESS ===");
            
            // Prepare response
            $month_names = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
            ];
            
            $paid_months = [];
            foreach ($monthly_billings as $billing) {
                $paid_months[] = $month_names[$billing['billing_month']] ?? $billing['billing_month'];
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Annual market rent payment processed successfully',
                'payment_type' => 'annual',
                'reference_id' => $reference_id,
                'annual_payment_id' => $annual_payment['id'],
                'registration_id' => $registration_id,
                'year' => $year,
                'stall_rights_no' => $annual_payment['stall_rights_no'],
                'business_name' => $annual_payment['business_name'],
                'receipt_number' => $receipt_number,
                'total_amount' => $amount,
                'months_paid' => $monthly_rows_updated,
                'months_list' => $paid_months,
                'payment_date' => $paid_at,
                'annual_updated' => $annual_rows_updated,
                'monthly_updated' => $monthly_rows_updated,
                'logs_created' => $logged_count
            ];
            
            if ($clearance_generated) {
                $response['market_rent_clearance'] = [
                    'generated' => true,
                    'certificate_number' => $certificate_number,
                    'year' => $year
                ];
            }
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        exit(); // Stop execution for annual payments
    }
    
    // ============================================
    // HANDLE MONTHLY PAYMENT
    // ============================================
    error_log("Processing as MONTHLY payment with reference ID: $reference_id");
    
    // For monthly payments, reference_id should be monthly_billing_id
    $monthly_billing_id = $reference_id;
    
    // Check if the monthly billing exists
    $check_query = "
        SELECT 
            mrb.*,
            rt.registration_id,
            rt.monthly_rent,
            rs.id as rent_stall_id,
            rs.business_name,
            rs.stall_rights_no,
            ro.renter_code
        FROM monthly_rent_billing mrb
        INNER JOIN rent_totals rt ON mrb.rent_total_id = rt.id
        INNER JOIN rent_stall rs ON rt.registration_id = rs.registration_id
        INNER JOIN renter_owner ro ON rs.renter_id = ro.id
        WHERE mrb.id = :monthly_billing_id
    ";
    
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([':monthly_billing_id' => $monthly_billing_id]);
    $monthly_billing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$monthly_billing) {
        error_log("ERROR: Monthly rent billing not found with ID: $monthly_billing_id");
        throw new Exception("Monthly rent billing not found: $monthly_billing_id");
    }
    
    error_log("Found monthly billing: ID={$monthly_billing['id']}, Status={$monthly_billing['payment_status']}");
    
    if ($monthly_billing['payment_status'] == 'paid') {
        error_log("Monthly billing already paid");
        echo json_encode([
            'status' => 'warning',
            'message' => 'This monthly rent is already paid',
            'receipt_number' => $monthly_billing['receipt_number']
        ]);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update monthly billing
        $update_query = "
            UPDATE monthly_rent_billing 
            SET payment_status = 'paid',
                payment_date = :paid_at,
                receipt_number = :receipt_number
            WHERE id = :monthly_billing_id
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([
            ':paid_at' => $paid_at,
            ':receipt_number' => $receipt_number,
            ':monthly_billing_id' => $monthly_billing_id
        ]);
        
        $rows_updated = $stmt->rowCount();
        
        error_log("Monthly billing updated: $rows_updated rows");
        
        // Log the payment
        $log_query = "
            INSERT INTO market_payment_logs 
            (monthly_billing_id, receipt_number, amount_paid, payment_date, payment_method, renter_code, stall_rights_no, business_name, created_at)
            VALUES 
            (:monthly_billing_id, :receipt_number, :amount_paid, :payment_date, 'online', :renter_code, :stall_rights_no, :business_name, NOW())
        ";
        
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([
            ':monthly_billing_id' => $monthly_billing_id,
            ':receipt_number' => $receipt_number,
            ':amount_paid' => $monthly_billing['total_amount_due'],
            ':payment_date' => $paid_at,
            ':renter_code' => $monthly_billing['renter_code'],
            ':stall_rights_no' => $monthly_billing['stall_rights_no'],
            ':business_name' => $monthly_billing['business_name']
        ]);
        
        error_log("Payment log created");
        
        // Check for rent clearance
        $rent_total_id = $monthly_billing['rent_total_id'];
        $year = $monthly_billing['billing_year'];
        
        $check_all_paid_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
            FROM monthly_rent_billing 
            WHERE rent_total_id = :rent_total_id 
            AND billing_year = :year
        ";
        
        $check_all_paid_stmt = $pdo->prepare($check_all_paid_query);
        $check_all_paid_stmt->execute([
            ':rent_total_id' => $rent_total_id,
            ':year' => $year
        ]);
        $paid_status = $check_all_paid_stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Clearance check: {$paid_status['paid']}/{$paid_status['total']} months paid");
        
        $clearance_generated = false;
        
        if ($paid_status['total'] == 12 && $paid_status['paid'] == 12) {
            try {
                createMarketClearanceTable($pdo);
                
                $check_clearance_query = "
                    SELECT id FROM market_rent_clearances 
                    WHERE rent_total_id = :rent_total_id 
                    AND clearance_year = :year
                ";
                $clearance_stmt = $pdo->prepare($check_clearance_query);
                $clearance_stmt->execute([
                    ':rent_total_id' => $rent_total_id,
                    ':year' => $year
                ]);
                
                if ($clearance_stmt->rowCount() == 0) {
                    $certificate_number = 'MRC-' . date('Y') . '-' . str_pad($rent_total_id, 6, '0', STR_PAD_LEFT) . '-' . $year;
                    
                    $insert_clearance_query = "
                        INSERT INTO market_rent_clearances 
                        (certificate_number, rent_stall_id, rent_total_id, clearance_year, issue_date, created_at)
                        VALUES (:cert_number, :rent_stall_id, :rent_total_id, :year, :issue_date, NOW())
                    ";
                    
                    $insert_clearance_stmt = $pdo->prepare($insert_clearance_query);
                    $insert_clearance_stmt->execute([
                        ':cert_number' => $certificate_number,
                        ':rent_stall_id' => $monthly_billing['rent_stall_id'],
                        ':rent_total_id' => $rent_total_id,
                        ':year' => $year,
                        ':issue_date' => $paid_at
                    ]);
                    
                    $clearance_generated = true;
                    error_log("Clearance generated: $certificate_number");
                }
            } catch (Exception $e) {
                error_log("Clearance error: " . $e->getMessage());
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        error_log("=== MONTHLY PAYMENT SUCCESS ===");
        
        // Prepare response
        $month_names = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $month_name = $month_names[$monthly_billing['billing_month']] ?? 'Unknown';
        
        $response = [
            'status' => 'success',
            'message' => 'Monthly rent payment processed',
            'payment_type' => 'monthly',
            'reference_id' => $reference_id,
            'monthly_billing_id' => $monthly_billing_id,
            'month_year' => $month_name . ' ' . $monthly_billing['billing_year'],
            'stall_rights_no' => $monthly_billing['stall_rights_no'],
            'business_name' => $monthly_billing['business_name'],
            'receipt_number' => $receipt_number,
            'amount_paid' => $monthly_billing['total_amount_due'],
            'base_rent' => $monthly_billing['base_rent'],
            'penalty_amount' => $monthly_billing['penalty_amount'],
            'payment_date' => $paid_at,
            'rows_updated' => $rows_updated
        ];
        
        if ($clearance_generated) {
            $response['market_rent_clearance'] = [
                'generated' => true,
                'certificate_number' => $certificate_number,
                'year' => $year
            ];
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
} catch (Exception $e) {
    error_log("General Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'API error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
}

function createMarketClearanceTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS market_rent_clearances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            certificate_number VARCHAR(50) NOT NULL UNIQUE,
            rent_stall_id INT NOT NULL,
            rent_total_id INT NOT NULL,
            clearance_year YEAR(4) NOT NULL,
            issue_date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_clearance_year (clearance_year),
            INDEX idx_issue_date (issue_date)
        )";
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("Clearance table error: " . $e->getMessage());
    }
}
?>