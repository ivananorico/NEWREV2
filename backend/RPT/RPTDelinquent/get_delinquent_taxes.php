<?php
// revenue2/backend/RPT/RPTDelinquent/get_delinquent_taxes.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Connect to database using rpt_db.php
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    echo json_encode([
        'success' => false,
        'error' => 'Database configuration file not found',
        'path' => $dbPath
    ]);
    exit();
}

require_once $dbPath;

$dbConnection = getDatabaseConnection();

if (is_array($dbConnection) && isset($dbConnection['error'])) {
    echo json_encode([
        'success' => false,
        'error' => $dbConnection['message']
    ]);
    exit();
}

$pdo = $dbConnection;

try {
    // Get current date for calculations
    $currentDate = date('Y-m-d');
    $currentDateObj = new DateTime();
    
    // Query to get all delinquent taxes
    $sql = "
        SELECT 
            qt.id,
            qt.property_total_id,
            qt.quarter,
            qt.year,
            qt.due_date,
            qt.total_quarterly_tax,
            qt.penalty_amount,
            qt.payment_status,
            qt.payment_date,
            qt.days_late as db_days_late,
            qt.created_at,
            qt.discount_amount,
            qt.penalty_percent_used,
            
            pt.registration_id,
            pt.total_annual_tax,
            
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.city,
            pr.zip_code,
            
            po.id as owner_id,
            po.owner_code,
            po.first_name,
            po.last_name,
            po.middle_name,
            po.email,
            po.phone,
            po.address,
            CONCAT(po.first_name, ' ', po.last_name) as owner_name,
            
            lp.tdn,
            lp.property_type,
            lp.land_area_sqm
            
        FROM quarterly_taxes qt
        LEFT JOIN property_totals pt ON qt.property_total_id = pt.id
        LEFT JOIN property_registrations pr ON pt.registration_id = pr.id
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN land_properties lp ON pt.land_id = lp.id
        
        -- Get records that are either:
        -- 1. Marked as overdue, OR
        -- 2. Have penalty amount > 0, OR
        -- 3. Pending with due date in the past
        WHERE qt.payment_status = 'overdue' 
           OR qt.penalty_amount > 0
           OR (qt.payment_status = 'pending' AND qt.due_date < CURDATE())
        
        ORDER BY 
            qt.due_date ASC,
            qt.id DESC
    ";
    
    $stmt = $pdo->query($sql);
    $delinquents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate correct days late for each record
    foreach ($delinquents as &$delinquent) {
        // Calculate days late based on due date and current date
        if ($delinquent['due_date']) {
            $dueDate = new DateTime($delinquent['due_date']);
            $today = new DateTime(); // Current date
            
            if ($dueDate < $today) {
                // Calculate actual days late
                $interval = $today->diff($dueDate);
                $delinquent['days_late'] = $interval->days;
                
                // Override status to overdue if it's not already
                $delinquent['payment_status'] = 'overdue';
            } else {
                // Not late yet
                $delinquent['days_late'] = 0;
                
                // If it has penalty but due date is in future, keep as pending
                if ($delinquent['penalty_amount'] > 0) {
                    $delinquent['payment_status'] = 'pending';
                }
            }
        } else {
            $delinquent['days_late'] = intval($delinquent['db_days_late'] ?? 0);
        }
        
        // Calculate total amount due
        $baseTax = floatval($delinquent['total_quarterly_tax'] ?: 0);
        $penalty = floatval($delinquent['penalty_amount'] ?: 0);
        $discount = floatval($delinquent['discount_amount'] ?: 0);
        $delinquent['total_amount_due'] = round($baseTax + $penalty - $discount, 2);
        
        // Format dates
        if ($delinquent['due_date']) {
            $delinquent['due_date_formatted'] = date('M d, Y', strtotime($delinquent['due_date']));
        }
        
        if ($delinquent['created_at']) {
            $delinquent['created_at_formatted'] = date('M d, Y H:i', strtotime($delinquent['created_at']));
        }
        
        // Ensure TDN is set
        if (empty($delinquent['tdn'])) {
            $delinquent['tdn'] = 'TDN-' . $delinquent['id'];
        }
        
        // Ensure owner name
        if (empty($delinquent['owner_name'])) {
            $delinquent['owner_name'] = trim(($delinquent['first_name'] ?? '') . ' ' . ($delinquent['last_name'] ?? ''));
        }
    }
    
    // Calculate statistics
    $overdueCount = count(array_filter($delinquents, function($d) { 
        return $d['days_late'] > 0; 
    }));
    
    $totalAmount = array_sum(array_column($delinquents, 'total_amount_due'));
    $totalPenalties = array_sum(array_column($delinquents, 'penalty_amount'));
    
    echo json_encode([
        'success' => true,
        'data' => $delinquents,
        'count' => count($delinquents),
        'overdue_count' => $overdueCount,
        'total_amount' => $totalAmount,
        'total_penalties' => $totalPenalties,
        'timestamp' => date('Y-m-d H:i:s'),
        'current_date' => $currentDate
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Application error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>