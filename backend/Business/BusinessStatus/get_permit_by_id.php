<?php
// backend/Business/BusinessStatus/get_permit_by_id.php

error_reporting(0);

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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dbPath = __DIR__ . '/../../../db/Business/business_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database configuration not found"
    ]);
    exit();
}

require_once $dbPath;

try {
    $id = $_GET['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Valid permit ID is required"
        ]);
        exit();
    }
    
    // Fetch business permit with ALL owner and business details
    $sql = "SELECT 
                bp.id,
                bp.business_permit_id,
                bp.business_name,
                
                -- Owner Information
                bp.full_name as owner_name,
                bp.sex,
                bp.date_of_birth,
                bp.marital_status,
                
                -- Personal Address
                bp.personal_street,
                bp.personal_barangay,
                bp.personal_district,
                bp.personal_city,
                bp.personal_province,
                bp.personal_zipcode,
                bp.personal_contact,
                bp.personal_email,
                
                -- Business Information
                bp.business_type,
                bp.tax_calculation_type,
                bp.taxable_amount,
                bp.tax_rate,
                bp.tax_amount,
                bp.regulatory_fees,
                bp.total_tax,
                
                -- Business Address
                bp.business_street,
                bp.business_barangay,
                bp.business_district,
                bp.business_city,
                bp.business_province,
                bp.business_zipcode,
                
                -- Dates
                bp.approved_date,
                bp.issue_date,
                bp.expiry_date,
                bp.status as business_status,
                bp.created_at,
                bp.updated_at,
                
                -- Calculate payment statistics
                COALESCE((
                    SELECT SUM(total_quarterly_tax) 
                    FROM business_quarterly_taxes 
                    WHERE business_permit_id = bp.id 
                    AND payment_status = 'paid'
                ), 0) as total_paid_tax,
                
                COALESCE((
                    SELECT SUM(total_quarterly_tax) 
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
            "message" => "Business permit not found"
        ]);
        exit();
    }
    
    // Format dates
    function formatDate($dateString) {
        if (!$dateString || $dateString == '0000-00-00') return null;
        return date('F d, Y', strtotime($dateString));
    }
    
    $permit['date_of_birth_formatted'] = formatDate($permit['date_of_birth']);
    $permit['approved_date_formatted'] = formatDate($permit['approved_date']);
    $permit['issue_date_formatted'] = formatDate($permit['issue_date']);
    $permit['expiry_date_formatted'] = formatDate($permit['expiry_date']);
    $permit['created_at_formatted'] = formatDate($permit['created_at']);
    $permit['updated_at_formatted'] = formatDate($permit['updated_at']);
    
    // Build full addresses
    $permit['personal_address'] = trim(implode(', ', array_filter([
        $permit['personal_street'],
        'Brgy. ' . $permit['personal_barangay'],
        $permit['personal_city'],
        $permit['personal_province'],
        $permit['personal_zipcode']
    ])));
    
    $permit['business_address'] = trim(implode(', ', array_filter([
        $permit['business_street'],
        'Brgy. ' . $permit['business_barangay'],
        $permit['business_city'],
        $permit['business_province'],
        $permit['business_zipcode']
    ])));
    
    // Calculate collection rate
    if ($permit['total_tax'] > 0) {
        $permit['collection_rate'] = round(($permit['total_paid_tax'] / $permit['total_tax']) * 100, 1);
    } else {
        $permit['collection_rate'] = 0;
    }
    
    // Determine overall status
    if ($permit['pending_quarters_count'] === 0) {
        $permit['overall_payment_status'] = 'fully_paid';
        $permit['overall_status_text'] = 'Fully Paid';
        $permit['overall_status_color'] = 'green';
    } elseif ($permit['total_pending_tax'] > 0) {
        $permit['overall_payment_status'] = 'pending';
        $permit['overall_status_text'] = 'Pending Payment';
        $permit['overall_status_color'] = 'yellow';
    } else {
        $permit['overall_payment_status'] = 'unknown';
        $permit['overall_status_text'] = 'No Payment Record';
        $permit['overall_status_color'] = 'gray';
    }
    
    // Fetch quarterly taxes
    $taxSql = "SELECT 
                    id,
                    quarter,
                    year,
                    due_date,
                    total_quarterly_tax,
                    penalty_amount,
                    payment_status,
                    payment_date,
                    receipt_number,
                    days_late,
                    created_at
                FROM business_quarterly_taxes 
                WHERE business_permit_id = ?
                ORDER BY year DESC,
                    CASE quarter 
                        WHEN 'Q1' THEN 1 
                        WHEN 'Q2' THEN 2 
                        WHEN 'Q3' THEN 3 
                        WHEN 'Q4' THEN 4 
                    END DESC";
    
    $taxStmt = $pdo->prepare($taxSql);
    if (!$taxStmt) {
        throw new Exception("Failed to prepare quarterly taxes SQL");
    }
    
    $taxStmt->execute([$id]);
    $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format quarterly tax dates
    foreach ($quarterlyTaxes as &$tax) {
        $tax['due_date_formatted'] = formatDate($tax['due_date']);
        $tax['payment_date_formatted'] = formatDate($tax['payment_date']);
        $tax['created_at_formatted'] = formatDate($tax['created_at']);
    }
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Business permit details retrieved successfully",
        "data" => [
            "permit" => $permit,
            "quarterlyTaxes" => $quarterlyTaxes,
            "timestamp" => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error occurred",
        "details" => $e->getMessage()
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