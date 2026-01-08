<?php
// revenue2/backend/Market/MarketValidation/get_applications.php

// Start output buffering
ob_start();

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection
require_once '../../../db/Market/market_db.php';

try {
    // First, let's check if we can connect to the database
    $testQuery = "SELECT COUNT(*) as total FROM rental_registration";
    $testStmt = $pdo->prepare($testQuery);
    $testStmt->execute();
    $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Total applications in rental_registration: " . $testResult['total']);
    
    // Get all applications to debug
    $debugQuery = "
        SELECT 
            rr.id,
            rr.stall_rights_no,
            rr.application_status,
            rr.created_at,
            ro.first_name,
            ro.last_name
        FROM rental_registration rr
        LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
        ORDER BY rr.created_at DESC
    ";
    
    $debugStmt = $pdo->prepare($debugQuery);
    $debugStmt->execute();
    $debugResults = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Debug - All applications:");
    foreach ($debugResults as $app) {
        error_log("ID: {$app['id']}, Status: {$app['application_status']}, Name: {$app['first_name']} {$app['last_name']}");
    }
    
    // Now get applications EXCLUDING 'approved' status
    $query = "
        SELECT 
            rr.id,
            rr.stall_rights_no,
            rr.application_status,
            rr.monthly_rent,
            rr.stall_rights_amount,
            rr.security_bond,
            rr.total_amount_due,
            rr.stall_class,
            rr.interview_date,
            rr.payment_date,
            rr.created_at,
            rr.updated_at,
            rr.stall_name,
            ro.first_name,
            ro.last_name,
            ro.middle_name,
            ro.email,
            ro.mobile,
            ro.renter_code,
            ro.city,
            ro.province,
            ro.barangay,
            rs.business_name,
            rs.business_type,
            s.name as stall_location_name
        FROM rental_registration rr
        LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        WHERE rr.application_status != 'approved' 
        ORDER BY rr.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log what we found
    error_log("Filtered applications (excluding approved): " . count($applications));
    foreach ($applications as $app) {
        error_log("Filtered - ID: {$app['id']}, Status: {$app['application_status']}, Name: {$app['first_name']} {$app['last_name']}");
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $applications,
        'count' => count($applications),
        'debug_total' => $testResult['total'],
        'message' => 'Applications retrieved successfully'
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString(),
        'data' => []
    ]);
}

// Flush output buffer
ob_end_flush();
?>