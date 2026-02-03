<?php
// api/business-payments.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../../db/Business/business_db.php';

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    // Get query parameters with default values
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $quarter = isset($_GET['quarter']) ? $_GET['quarter'] : null; // Should be Q1, Q2, Q3, Q4
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    // Validate quarter parameter if provided
    if ($quarter) {
        // Remove any leading Qs to handle potential QQ1, QQ2, etc
        $quarter_num = preg_replace('/^Q+/i', '', $quarter);
        
        // Check if it's a valid quarter (1, 2, 3, or 4)
        if (!in_array($quarter_num, ['1', '2', '3', '4'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid quarter parameter. Must be Q1, Q2, Q3, or Q4'
            ]);
            exit();
        }
    }
    
    // Build query for quarterly tax payments
    $sql = "
        SELECT 
            qt.payment_date,
            bp.applicant_id as business_id,
            bp.business_name,
            CONCAT(
                CASE 
                    WHEN bp.owner_full_name LIKE '%,%' 
                    THEN SUBSTRING_INDEX(bp.owner_full_name, ',', 1)
                    ELSE bp.owner_full_name
                END,
                ' ',
                CASE 
                    WHEN bp.owner_full_name LIKE '%,%' 
                    THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(bp.owner_full_name, ',', -1), ' ', 1))
                    ELSE ''
                END
            ) as owner_name,
            'Quarterly Tax' as payment_type,
            CONCAT('Q', 
                CASE 
                    WHEN qt.quarter REGEXP '^Q+[1-4]$' 
                    THEN SUBSTRING(qt.quarter, -1)  -- Get last character if it starts with Q
                    ELSE qt.quarter 
                END, 
                ' ', qt.year) as period,
            qt.total_quarterly_tax as amount,
            qt.receipt_number,
            qt.payment_status as status
        FROM business_quarterly_taxes qt
        INNER JOIN business_permits bp ON qt.business_permit_id = bp.id
        WHERE qt.payment_status = 'paid'
        AND qt.year = :year
    ";
    
    // Parameters array
    $params = [':year' => $year];
    
    // Add quarter filter if specified
    if ($quarter) {
        $quarter_num = preg_replace('/^Q+/i', '', $quarter);
        $sql .= " AND (
            qt.quarter = :quarter OR 
            qt.quarter = CONCAT('Q', :quarter) OR 
            qt.quarter = CONCAT('QQ', :quarter)
        )";
        $params[':quarter'] = $quarter_num; // Store just the number (1, 2, 3, 4)
    }
    
    // Add date range filter
    if ($start_date && $end_date) {
        $sql .= " AND DATE(qt.payment_date) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    }
    
    // Order by payment date (newest first) and add limit
    $sql .= " ORDER BY qt.payment_date DESC LIMIT :limit";
    
    // Prepare and execute query
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $payments = $stmt->fetchAll();
    
    // Format the response
    $formattedPayments = [];
    $totalAmount = 0;
    
    foreach ($payments as $payment) {
        $totalAmount += floatval($payment['amount']);
        
        $formattedPayments[] = [
            'payment_date' => date('Y-m-d', strtotime($payment['payment_date'])),
            'business_id' => $payment['business_id'],
            'business_name' => $payment['business_name'],
            'owner_name' => $payment['owner_name'],
            'payment_type' => $payment['payment_type'],
            'period' => $payment['period'],
            'amount' => floatval($payment['amount']),
            'receipt_number' => $payment['receipt_number'],
            'status' => $payment['status']
        ];
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'total_payments' => count($formattedPayments),
        'total_amount' => $totalAmount,
        'data' => $formattedPayments
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}