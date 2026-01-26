<?php
// backend/Business/BusinessStatus/get_permit_by_id.php

error_reporting(0);

$allowed_origins = [
    "http://localhost:5173",
    "https://revenuetreasury.goserveph.com"
];

if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $origin);
    }
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Expires");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
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

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to connect to database"
    ]);
    exit();
}

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
    
    // Fetch business permit - UPDATED to match your actual database schema
    $sql = "SELECT 
                bp.id,
                bp.applicant_id as business_permit_id,  -- Changed from business_permit_id to applicant_id
                bp.business_name,
                
                -- Owner Information
                bp.owner_full_name as owner_name,  -- Changed from full_name to owner_full_name
                bp.owner_type,
                bp.contact_number,
                bp.email_address,
                
                -- Business Information
                bp.business_nature as business_type,  -- Changed from business_type to business_nature
                bp.tax_calculation_type,
                bp.capital_investment,
                bp.taxable_amount,
                bp.tax_rate,
                bp.tax_amount,
                bp.regulatory_fees,
                bp.total_tax,
                
                -- Business Address (from your database)
                bp.business_barangay,
                bp.business_district,
                bp.business_city,
                bp.business_province,
                bp.business_zipcode,
                bp.trade_name,
                
                -- Dates (using correct column names from your schema)
                bp.application_date,
                bp.approved_date,
                bp.issue_date,
                bp.expiry_date,
                bp.permit_status as business_status,  -- Changed from status to permit_status
                bp.tax_status,
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
                ), 0) as total_quarters_count,
                
                -- Get next due date
                (
                    SELECT due_date 
                    FROM business_quarterly_taxes 
                    WHERE business_permit_id = bp.id 
                    AND payment_status IN ('pending', 'overdue')
                    ORDER BY due_date ASC 
                    LIMIT 1
                ) as next_due_date
                
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
        if (!$dateString || $dateString == '0000-00-00' || $dateString == '0000-00-00 00:00:00') return null;
        return date('F d, Y', strtotime($dateString));
    }
    
    // Format dates for display
    $permit['approved_date_formatted'] = formatDate($permit['approved_date']);
    $permit['issue_date_formatted'] = formatDate($permit['issue_date']);
    $permit['expiry_date_formatted'] = formatDate($permit['expiry_date']);
    $permit['application_date_formatted'] = formatDate($permit['application_date']);
    $permit['created_at_formatted'] = formatDate($permit['created_at']);
    $permit['updated_at_formatted'] = formatDate($permit['updated_at']);
    $permit['next_due_date_formatted'] = formatDate($permit['next_due_date']);
    
    // Build business address from available fields
    $businessAddressParts = [];
    if (!empty($permit['business_barangay'])) {
        $businessAddressParts[] = 'Brgy. ' . $permit['business_barangay'];
    }
    if (!empty($permit['business_district']) && $permit['business_district'] !== 'Unknown') {
        $businessAddressParts[] = $permit['business_district'];
    }
    if (!empty($permit['business_city'])) {
        $businessAddressParts[] = $permit['business_city'];
    }
    if (!empty($permit['business_province'])) {
        $businessAddressParts[] = $permit['business_province'];
    }
    if (!empty($permit['business_zipcode'])) {
        $businessAddressParts[] = $permit['business_zipcode'];
    }
    
    $permit['business_address'] = implode(', ', $businessAddressParts);
    
    // Calculate collection rate
    if ($permit['total_tax'] > 0) {
        $permit['collection_rate'] = round(($permit['total_paid_tax'] / $permit['total_tax']) * 100, 1);
    } else {
        $permit['collection_rate'] = 0;
    }
    
    // Determine overall payment status
    if ($permit['pending_quarters_count'] === 0 && $permit['total_quarters_count'] > 0) {
        $permit['overall_payment_status'] = 'fully_paid';
        $permit['overall_status_text'] = 'Fully Paid';
        $permit['overall_status_color'] = 'green';
    } elseif ($permit['total_pending_tax'] > 0) {
        // Check if any payments are overdue
        $overdueCheck = $pdo->prepare("
            SELECT COUNT(*) as overdue_count 
            FROM business_quarterly_taxes 
            WHERE business_permit_id = ? 
            AND payment_status = 'overdue'
        ");
        $overdueCheck->execute([$id]);
        $overdueResult = $overdueCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($overdueResult['overdue_count'] > 0) {
            $permit['overall_payment_status'] = 'overdue';
            $permit['overall_status_text'] = 'Overdue';
            $permit['overall_status_color'] = 'red';
        } else {
            $permit['overall_payment_status'] = 'pending';
            $permit['overall_status_text'] = 'Pending Payment';
            $permit['overall_status_color'] = 'yellow';
        }
    } else {
        $permit['overall_payment_status'] = 'no_tax';
        $permit['overall_status_text'] = 'No Tax Record';
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
        "details" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Application error occurred",
        "details" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}