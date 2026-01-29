<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Database configuration
$host = 'localhost:3307';
$dbname = 'rpt';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SIMPLE QUERY: Get all pending and overdue taxes
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
            qt.days_late,
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
            
            lp.tdn as land_tdn,
            lp.property_type,
            lp.land_area_sqm,
            
            COALESCE(
                (SELECT GROUP_CONCAT(tdn) FROM building_properties WHERE land_id = lp.id),
                'None'
            ) as building_tdns
            
        FROM quarterly_taxes qt
        LEFT JOIN property_totals pt ON qt.property_total_id = pt.id
        LEFT JOIN property_registrations pr ON pt.registration_id = pr.id
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN land_properties lp ON pt.land_id = lp.id
        
        WHERE qt.payment_status IN ('pending', 'overdue')
        
        ORDER BY 
            qt.payment_status DESC, -- overdue first
            qt.due_date ASC,
            qt.id DESC
    ";
    
    $stmt = $pdo->query($sql);
    $delinquents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate additional information for each delinquent
    $currentDate = new DateTime();
    foreach ($delinquents as &$delinquent) {
        // Calculate days late if not already set
        if ($delinquent['due_date']) {
            $dueDate = new DateTime($delinquent['due_date']);
            $interval = $currentDate->diff($dueDate);
            
            // If due date is in the past, calculate days late
            if ($dueDate < $currentDate) {
                $delinquent['days_late'] = $interval->days;
                // Auto-mark as overdue if not already
                if ($delinquent['payment_status'] == 'pending') {
                    $delinquent['payment_status'] = 'overdue';
                }
            } else {
                $delinquent['days_late'] = 0;
            }
        }
        
        // Calculate penalty if overdue and no penalty set
        if ($delinquent['payment_status'] == 'overdue' && 
            (!$delinquent['penalty_amount'] || $delinquent['penalty_amount'] == 0)) {
            
            if ($delinquent['total_quarterly_tax'] && $delinquent['due_date']) {
                $dueDate = new DateTime($delinquent['due_date']);
                if ($dueDate < $currentDate) {
                    $daysLate = $currentDate->diff($dueDate)->days;
                    $monthsLate = ceil($daysLate / 30);
                    $penaltyRate = $delinquent['penalty_percent_used'] ? $delinquent['penalty_percent_used'] / 100 : 0.02;
                    $delinquent['penalty_amount'] = number_format(
                        $delinquent['total_quarterly_tax'] * $penaltyRate * $monthsLate, 
                        2, '.', ''
                    );
                }
            }
        }
        
        // Calculate total amount due
        $baseTax = floatval($delinquent['total_quarterly_tax'] ?: 0);
        $penalty = floatval($delinquent['penalty_amount'] ?: 0);
        $discount = floatval($delinquent['discount_amount'] ?: 0);
        $delinquent['total_amount_due'] = number_format($baseTax + $penalty - $discount, 2, '.', '');
        
        // Format dates
        if ($delinquent['due_date']) {
            $delinquent['due_date_formatted'] = date('M d, Y', strtotime($delinquent['due_date']));
        }
        
        if ($delinquent['created_at']) {
            $delinquent['created_at_formatted'] = date('M d, Y H:i', strtotime($delinquent['created_at']));
        }
        
        // Generate TDN if not exists
        if (empty($delinquent['tdn'])) {
            $delinquent['tdn'] = $delinquent['land_tdn'] ?: 'TDN-' . $delinquent['id'];
        }
        
        // Ensure owner name
        if (empty($delinquent['owner_name'])) {
            $delinquent['owner_name'] = trim($delinquent['first_name'] . ' ' . $delinquent['last_name']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $delinquents,
        'count' => count($delinquents),
        'timestamp' => date('Y-m-d H:i:s'),
        'current_date' => $currentDate->format('Y-m-d'),
        'query' => 'payment_status IN (pending, overdue)'
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