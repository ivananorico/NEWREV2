<?php
// approve_application.php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../db/Market/market_db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$app_id = intval($data['application_id'] ?? 0);

if ($app_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid application ID']);
    exit;
}

try {
    // Check current status and get application details
    $stmt = $pdo->prepare("
        SELECT rr.id, rr.application_status, rr.stall_id, rr.map_id, 
               rr.monthly_rent, rr.stall_rights_no, rr.reference_number,
               ro.id as renter_id,
               rs.business_name
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        WHERE rr.id = ?
    ");
    $stmt->execute([$app_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        echo json_encode(['status' => 'error', 'message' => 'Application not found']);
        exit;
    }
    
    if ($existing['application_status'] !== 'paid') {
        echo json_encode(['status' => 'error', 'message' => 'Application is not in paid status']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Update rental_registration to 'approved'
    $sql = "UPDATE rental_registration SET 
            application_status = 'approved',
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$app_id]);
    
    $renter_id = $existing['renter_id'];
    $monthly_rent = $existing['monthly_rent'];
    $business_name = $existing['business_name'] ?? 'Not Specified';
    $stall_rights_no = $existing['stall_rights_no'];
    $payment_reference = $existing['reference_number'] ?? null;
    
    // 2. Check if rent_stall already exists
    $stmt = $pdo->prepare("SELECT id FROM rent_stall WHERE registration_id = ?");
    $stmt->execute([$app_id]);
    $existing_rent_stall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_rent_stall) {
        // Create rent_stall record
        $sql = "INSERT INTO rent_stall (
                renter_id, registration_id, stall_id, map_id, 
                business_name, stall_rights_no, start_date, 
                monthly_rent, status
            ) VALUES (
                :renter_id, :reg_id, :stall_id, :map_id,
                :business_name, :stall_rights_no,
                CURDATE(),
                :monthly_rent, 'active'
            )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':renter_id' => $renter_id,
            ':reg_id' => $app_id,
            ':stall_id' => $existing['stall_id'],
            ':map_id' => $existing['map_id'],
            ':business_name' => $business_name,
            ':stall_rights_no' => $stall_rights_no,
            ':monthly_rent' => $monthly_rent
        ]);
        
        $rent_stall_id = $pdo->lastInsertId();
    } else {
        // Update existing rent_stall to active
        $rent_stall_id = $existing_rent_stall['id'];
        $sql = "UPDATE rent_stall SET 
                status = 'active',
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rent_stall_id]);
    }
    
    // 3. Create ONE rent_totals record for the entire year
    $start_date = date('Y-01-01'); // January 1 of current year
    $end_date = date('Y-12-31');   // December 31 of current year
    
    // Calculate the total number of months in contract
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $total_months = ($interval->y * 12) + $interval->m;
    
    // Add one month if there are any days
    if ($interval->d > 0 || $interval->m > 0 || $interval->y > 0) {
        $total_months += 1;
    }
    
    // Calculate monthly_totals (monthly rent × number of months)
    $monthly_totals = $monthly_rent * $total_months;
    
    $sql = "INSERT INTO rent_totals (
            rent_stall_id, renter_id, registration_id,
            monthly_rent, monthly_totals, start_date, end_date, status
        ) VALUES (
            :rent_stall_id, :renter_id, :reg_id,
            :monthly_rent, :monthly_totals, :start_date, :end_date, 'active'
        )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':rent_stall_id' => $rent_stall_id,
        ':renter_id' => $renter_id,
        ':reg_id' => $app_id,
        ':monthly_rent' => $monthly_rent,
        ':monthly_totals' => $monthly_totals,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    $rent_total_id = $pdo->lastInsertId();
    
    // 4. Generate 12 monthly rent bills for the entire year
    $current_year = date('Y');
    $billing_ids = [];
    $total_annual_amount = 0;
    
    for ($month = 1; $month <= 12; $month++) {
        // Calculate due date (15th of each month)
        $due_date = date('Y-m-15', strtotime("$current_year-$month-01"));
        
        // For current month, due date should be 15th of next month
        $current_month = date('n');
        if ($month == $current_month) {
            $due_date = date('Y-m-15', strtotime('+1 month'));
        }
        // For past months (if application approved later in the year)
        else if ($month < $current_month) {
            // If it's a past month this year, set due date as 15th of next month after that month
            $due_date = date('Y-m-15', strtotime("$current_year-$month-01 +1 month"));
        }
        
        $sql = "INSERT INTO monthly_rent_billing (
                rent_total_id, billing_month, billing_year, due_date,
                base_rent, total_amount_due, payment_status
            ) VALUES (
                :rent_total_id, :billing_month, :billing_year, :due_date,
                :base_rent, :total_amount_due, 'pending'
            )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rent_total_id' => $rent_total_id,
            ':billing_month' => $month,
            ':billing_year' => $current_year,
            ':due_date' => $due_date,
            ':base_rent' => $monthly_rent,
            ':total_amount_due' => $monthly_rent
        ]);
        
        $billing_id = $pdo->lastInsertId();
        $billing_ids[] = [
            'month' => $month,
            'billing_id' => $billing_id,
            'due_date' => $due_date,
            'amount' => $monthly_rent
        ];
        
        $total_annual_amount += $monthly_rent;
    }
    
    // 5. CREATE ANNUAL PAYMENT RECORD
    // Generate annual reference number
    $annual_reference = 'ANNUAL-' . date('Ymd-His') . '-' . $app_id;
    
    // Check if discount should be applied (for January applications)
    $current_month = date('n');
    $current_day = date('d');
    
    $discount_percent = 0;
    $discount_amount = 0;
    
    // Apply discount only for January applications (1-31)
    if ($current_month == 1 && $current_day <= 31) {
        // Get discount percentage from config
        $stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM market_discount_config 
            WHERE effective_date <= CURDATE() 
                AND (expiration_date IS NULL OR expiration_date >= CURDATE())
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $discount_config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($discount_config) {
            $discount_percent = floatval($discount_config['discount_percent']);
            $discount_amount = ($total_annual_amount * $discount_percent) / 100;
        }
    }
    
    // Calculate final amount after discount
    $final_annual_amount = $total_annual_amount - $discount_amount;
    
    // Insert into market_annual_payments
    $sql = "INSERT INTO market_annual_payments (
            reference_number, property_total_id, registration_id, payment_year,
            base_amount, discount_percent, discount_amount, final_amount,
            payment_status, payment_date, receipt_number, transaction_id,
            status
        ) VALUES (
            :reference, :property_total_id, :registration_id, :payment_year,
            :base_amount, :discount_percent, :discount_amount, :final_amount,
            'paid', CURDATE(), :receipt_number, :transaction_id,
            'active'
        )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':reference' => $annual_reference,
        ':property_total_id' => $rent_total_id,
        ':registration_id' => $app_id,
        ':payment_year' => $current_year,
        ':base_amount' => $total_annual_amount,
        ':discount_percent' => $discount_percent,
        ':discount_amount' => $discount_amount,
        ':final_amount' => $final_annual_amount,
        ':receipt_number' => $payment_reference, // Use the original payment reference
        ':transaction_id' => $annual_reference // Use annual reference as transaction ID
    ]);
    
    $annual_payment_id = $pdo->lastInsertId();
    
    // 6. Update stall status to 'occupied'
    $sql = "UPDATE stalls SET 
            status = 'occupied',
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$existing['stall_id']]);
    
    // 7. Update renter_owner status to 'active'
    $sql = "UPDATE renter_owner SET 
            status = 'active',
            updated_at = NOW()
            WHERE registration_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$app_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Application approved and annual contract created',
        'data' => [
            'application_id' => $app_id,
            'new_status' => 'approved',
            'rent_stall_id' => $rent_stall_id,
            'rent_total_id' => $rent_total_id,
            'annual_payment_id' => $annual_payment_id,
            'annual_reference' => $annual_reference,
            'monthly_rent' => $monthly_rent,
            'monthly_totals' => $monthly_totals,
            'total_months' => $total_months,
            'annual_total' => [
                'base_amount' => $total_annual_amount,
                'discount_percent' => $discount_percent,
                'discount_amount' => $discount_amount,
                'final_amount' => $final_annual_amount
            ],
            'contract_period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'months' => $total_months,
                'year' => $current_year
            ],
            'billing_count' => 12,
            'billing_ids' => $billing_ids
        ]
    ]);
    
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>