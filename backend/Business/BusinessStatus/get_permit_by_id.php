<?php
// backend/Business/BusinessStatus/get_permit_by_id.php

// Enable error reporting for debugging (optional)
error_reporting(0);

// CORS headers for both localhost and domain
$allowed_origins = [
    "http://localhost:5173",
    "https://revenuetreasury.goserveph.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$dbPath = __DIR__ . '/../../../db/Business/business_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database configuration not found",
        "details" => "Config file missing"
    ]);
    exit();
}

require_once $dbPath;

try {
    // Get permit ID from URL parameter
    $id = $_GET['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Valid permit ID is required",
            "details" => "No ID provided or invalid ID format"
        ]);
        exit();
    }
    
    // Fetch business permit with detailed information
    $sql = "SELECT 
                bp.*,
                -- Calculate payment statistics
                COALESCE((
                    SELECT SUM(total_quarterly_tax) 
                    FROM business_quarterly_taxes 
                    WHERE business_permit_id = bp.id 
                    AND payment_status = 'paid'
                ), 0) as total_paid_tax,
                
                COALESCE((
                    SELECT SUM(total_quarterly_tax + COALESCE(penalty_amount, 0)) 
                    FROM business_quarterly_taxes 
                    WHERE business_permit_id = bp.id 
                    AND payment_status IN ('pending', 'overdue')
                ), 0) as total_pending_tax,
                
                COALESCE((
                    SELECT SUM(penalty_amount) 
                    FROM business_quarterly_taxes 
                    WHERE business_permit_id = bp.id
                ), 0) as total_penalty,
                
                COALESCE((
                    SELECT COUNT(*) 
                    FROM business_quarterly_taxes 
                    WHERE business_permit_id = bp.id 
                    AND payment_status IN ('pending', 'overdue')
                ), 0) as pending_quarters_count,
                
                COALESCE((
                    SELECT COUNT(*) 
                    FROM business_quarterly_taxes 
                    WHERE business_permit_id = bp.id
                ), 0) as total_quarters_count
                
            FROM business_permits bp
            WHERE bp.id = ?";
    
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare SQL statement");
    }
    
    $stmt->execute([$id]);
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permit) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Business permit not found",
            "details" => "No record found with ID: " . $id
        ]);
        exit();
    }
    
    // Fetch quarterly taxes with detailed information
    $taxSql = "SELECT 
                    bqt.*,
                    CASE 
                        WHEN bqt.payment_status = 'paid' THEN 'Paid'
                        WHEN bqt.payment_status = 'overdue' THEN 'Overdue'
                        ELSE 'Pending'
                    END as payment_status_display,
                    
                    DATE_FORMAT(bqt.due_date, '%M %d, %Y') as due_date_formatted,
                    DATE_FORMAT(bqt.payment_date, '%M %d, %Y') as payment_date_formatted,
                    
                    CASE 
                        WHEN bqt.payment_status = 'paid' THEN 'green'
                        WHEN bqt.payment_status = 'overdue' THEN 'red'
                        ELSE 'yellow'
                    END as status_color,
                    
                    CASE 
                        WHEN bqt.payment_status = 'paid' THEN 'check-circle'
                        WHEN bqt.payment_status = 'overdue' THEN 'alert-circle'
                        ELSE 'clock'
                    END as status_icon,
                    
                    (bqt.total_quarterly_tax + COALESCE(bqt.penalty_amount, 0)) as total_due,
                    DATEDIFF(CURDATE(), bqt.due_date) as days_late_display
                    
                FROM business_quarterly_taxes bqt
                WHERE bqt.business_permit_id = ?
                ORDER BY 
                    bqt.year DESC,
                    FIELD(bqt.quarter, 'Q4', 'Q3', 'Q2', 'Q1') DESC";
    
    $taxStmt = $pdo->prepare($taxSql);
    if (!$taxStmt) {
        throw new Exception("Failed to prepare quarterly taxes SQL");
    }
    
    $taxStmt->execute([$id]);
    $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $paidQuarters = array_filter($quarterlyTaxes, function($tax) {
        return $tax['payment_status'] === 'paid';
    });
    
    $overdueQuarters = array_filter($quarterlyTaxes, function($tax) {
        return $tax['payment_status'] === 'overdue';
    });
    
    $pendingQuarters = array_filter($quarterlyTaxes, function($tax) {
        return $tax['payment_status'] === 'pending';
    });
    
    $summary = [
        'total_quarters' => count($quarterlyTaxes),
        'paid_quarters' => count($paidQuarters),
        'overdue_quarters' => count($overdueQuarters),
        'pending_quarters' => count($pendingQuarters),
        'total_quarterly_tax' => array_sum(array_column($quarterlyTaxes, 'total_quarterly_tax')),
        'total_penalty' => array_sum(array_column($quarterlyTaxes, 'penalty_amount')),
        'total_collected' => array_sum(array_column($paidQuarters, 'total_quarterly_tax')),
        'total_due' => array_sum(array_map(function($tax) {
            return $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
        }, $quarterlyTaxes))
    ];
    
    // Format dates for display
    if ($permit['issue_date']) {
        $permit['issue_date_formatted'] = date('F d, Y', strtotime($permit['issue_date']));
    }
    
    if ($permit['approved_date']) {
        $permit['approved_date_formatted'] = date('F d, Y', strtotime($permit['approved_date']));
    }
    
    if ($permit['expiry_date']) {
        $permit['expiry_date_formatted'] = date('F d, Y', strtotime($permit['expiry_date']));
    }
    
    if ($permit['created_at']) {
        $permit['created_at_formatted'] = date('F d, Y', strtotime($permit['created_at']));
    }
    
    // Add address components
    $permit['full_address'] = trim(implode(', ', array_filter([
        $permit['street'],
        'Brgy. ' . $permit['barangay'],
        $permit['city'],
        $permit['province']
    ])));
    
    // Determine overall payment status
    if ($summary['paid_quarters'] === $summary['total_quarters']) {
        $permit['overall_payment_status'] = 'fully_paid';
        $permit['overall_status_text'] = 'Fully Paid';
        $permit['overall_status_color'] = 'green';
    } elseif ($summary['overdue_quarters'] > 0) {
        $permit['overall_payment_status'] = 'overdue';
        $permit['overall_status_text'] = 'Has Overdue Payments';
        $permit['overall_status_color'] = 'red';
    } elseif ($summary['pending_quarters'] > 0) {
        $permit['overall_payment_status'] = 'pending';
        $permit['overall_status_text'] = 'Has Pending Payments';
        $permit['overall_status_color'] = 'yellow';
    } else {
        $permit['overall_payment_status'] = 'unknown';
        $permit['overall_status_text'] = 'No Payment History';
        $permit['overall_status_color'] = 'gray';
    }
    
    // Calculate collection rate
    if ($summary['total_quarterly_tax'] > 0) {
        $permit['collection_rate'] = round(($summary['total_collected'] / $summary['total_quarterly_tax']) * 100, 1);
    } else {
        $permit['collection_rate'] = 0;
    }
    
    // Return success response with formatted data
    echo json_encode([
        "status" => "success",
        "message" => "Business permit details retrieved successfully",
        "data" => [
            "permit" => $permit,
            "quarterlyTaxes" => $quarterlyTaxes,
            "summary" => $summary,
            "timestamp" => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error occurred",
        "details" => "PDO Error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Application error occurred",
        "details" => $e->getMessage()
    ]);
}
?>