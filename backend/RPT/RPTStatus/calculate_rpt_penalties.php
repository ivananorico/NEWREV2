<?php
// backend/RPT/RPTStatus/calculate_rpt_penalties.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$dbPath = __DIR__ . '/../../../db/RPT/rpt_db.php';
if (!file_exists($dbPath)) {
    echo json_encode([
        "status" => "warning",
        "message" => "Database config not found. Penalty calculation skipped.",
        "data" => ["skipped" => true]
    ]);
    exit();
}

require_once $dbPath;

try {
    // Get database connection using your function
    $pdo = getDatabaseConnection();
    
    // Check if connection returned an error array instead of PDO object
    if (is_array($pdo) && isset($pdo['error'])) {
        throw new Exception($pdo['message'] . (isset($pdo['debug']) ? " - " . $pdo['debug'] : ""));
    }
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get current penalty percentage (default to 2%)
    $penaltyPercent = 2.00;
    
    // Find all overdue RPT quarterly taxes
    $sql = "SELECT 
                id,
                property_total_id,
                quarter,
                year,
                total_quarterly_tax,
                penalty_amount,
                due_date,
                payment_status,
                days_late,
                DATEDIFF(CURDATE(), due_date) as days_overdue
            FROM quarterly_taxes 
            WHERE payment_status IN ('pending', 'overdue')
            AND due_date < CURDATE()";
    
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare SQL statement");
    }
    
    $stmt->execute();
    $overdueTaxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updatedCount = 0;
    $totalPenalty = 0;
    
    if (!empty($overdueTaxes)) {
        foreach ($overdueTaxes as $tax) {
            // Use existing days_late or calculate new
            $existingDaysLate = (int)($tax['days_late'] ?: 0);
            $calculatedDaysLate = max(0, (int)$tax['days_overdue']);
            
            // Use the greater of existing or calculated days late
            $daysLate = max($existingDaysLate, $calculatedDaysLate);
            
            if ($daysLate > 0) {
                // Calculate months late (round up)
                $monthsLate = ceil($daysLate / 30);
                
                // Get current values
                $currentTotalTax = (float)$tax['total_quarterly_tax'];
                $currentPenalty = (float)($tax['penalty_amount'] ?: 0);
                
                // Calculate new penalty (2% monthly penalty)
                $newPenalty = ($currentTotalTax * ($penaltyPercent / 100)) * $monthsLate;
                
                // Only update if new penalty is greater
                if ($newPenalty > $currentPenalty) {
                    // Update the quarterly tax record
                    $updateSql = "UPDATE quarterly_taxes 
                                 SET penalty_amount = ?,
                                     payment_status = 'overdue',
                                     days_late = ?
                                 WHERE id = ?";
                    
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([
                        $newPenalty,
                        $daysLate,
                        $tax['id']
                    ]);
                    
                    $updatedCount++;
                    $totalPenalty += ($newPenalty - $currentPenalty);
                }
            }
        }
    }
    
    // Update taxes that are due today to pending status if not already paid/overdue
    $todaySql = "UPDATE quarterly_taxes 
                 SET payment_status = 'pending'
                 WHERE due_date = CURDATE() 
                 AND payment_status NOT IN ('paid', 'overdue')";
    
    $todayStmt = $pdo->prepare($todaySql);
    if ($todayStmt) {
        $todayStmt->execute();
    }
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "RPT penalties calculated successfully",
        "data" => [
            "updated_records" => $updatedCount,
            "total_penalty_added" => $totalPenalty,
            "penalty_percent_used" => $penaltyPercent,
            "calculation_date" => date('Y-m-d H:i:s'),
            "overdue_taxes_found" => count($overdueTaxes)
        ]
    ]);
    
} catch (PDOException $e) {
    // Even if there's an error, return success so frontend continues
    error_log("RPT Penalty calculation PDO error: " . $e->getMessage());
    echo json_encode([
        "status" => "success",
        "message" => "RPT penalty calculation encountered an error but properties will still load",
        "error_details" => $e->getMessage(),
        "data" => [
            "completed" => true,
            "with_errors" => true
        ]
    ]);
} catch (Exception $e) {
    error_log("RPT Penalty calculation error: " . $e->getMessage());
    echo json_encode([
        "status" => "success", 
        "message" => "RPT penalty calculation skipped",
        "error_details" => $e->getMessage(),
        "data" => [
            "completed" => true,
            "with_errors" => true
        ]
    ]);
}
?>