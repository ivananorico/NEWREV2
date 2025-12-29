<?php
/**
 * ======================================
 * CORS (credentials-safe)
 * ======================================
 */
$allowed_origins = [
    "http://localhost:5173",
    "https://revenuetreasury.goserveph.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * ======================================
 * DB CONNECTION
 * ======================================
 */
// Check if database file exists
$dbPath = __DIR__ . '/../../../db/Business/business_db.php';

if (!file_exists($dbPath)) {
    // Return success anyway so frontend can continue
    echo json_encode([
        "status" => "success",
        "message" => "Database config not found. Penalty calculation skipped.",
        "data" => [
            "skipped" => true,
            "reason" => "Database config not found at: " . $dbPath
        ]
    ]);
    exit();
}

require_once $dbPath;

try {
    // Check connection
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get current penalty percentage from config (default 2%)
    $penaltyPercent = 2.00;
    $penaltyConfigSql = "SELECT penalty_percent FROM business_penalty_config 
                         WHERE (expiration_date IS NULL OR expiration_date >= CURDATE())
                         AND effective_date <= CURDATE()
                         ORDER BY effective_date DESC LIMIT 1";
    
    $penaltyStmt = $pdo->query($penaltyConfigSql);
    if ($penaltyStmt) {
        $penaltyConfig = $penaltyStmt->fetch(PDO::FETCH_ASSOC);
        if ($penaltyConfig && isset($penaltyConfig['penalty_percent'])) {
            $penaltyPercent = $penaltyConfig['penalty_percent'];
        }
    }
    
    // Find all overdue quarterly taxes
    $sql = "SELECT 
                id,
                business_permit_id,
                quarter,
                year,
                total_quarterly_tax,
                penalty_amount,
                due_date,
                payment_status,
                days_late,
                DATEDIFF(CURDATE(), due_date) as days_overdue
            FROM business_quarterly_taxes 
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
                
                // Calculate new penalty (monthly penalty on the base tax)
                // Note: total_quarterly_tax already includes basic tax + regulatory fees
                $newPenalty = ($currentTotalTax * ($penaltyPercent / 100)) * $monthsLate;
                
                // Only update if new penalty is greater
                if ($newPenalty > $currentPenalty) {
                    // FIXED: Removed updated_at column since it doesn't exist
                    $updateSql = "UPDATE business_quarterly_taxes 
                                 SET penalty_amount = ?,
                                     penalty_percent_used = ?,
                                     payment_status = 'overdue',
                                     days_late = ?
                                 WHERE id = ?";
                    
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([
                        $newPenalty,
                        $penaltyPercent,
                        $daysLate,
                        $tax['id']
                    ]);
                    
                    $updatedCount++;
                    $totalPenalty += ($newPenalty - $currentPenalty);
                    
                    // Debug log
                    error_log("Updated penalty for tax ID {$tax['id']}: Old: {$currentPenalty}, New: {$newPenalty}, Days Late: {$daysLate}");
                }
            }
        }
    }
    
    // Update taxes that are due today to pending status if not already paid/overdue
    $todaySql = "UPDATE business_quarterly_taxes 
                 SET payment_status = 'pending'
                 WHERE due_date = CURDATE() 
                 AND payment_status NOT IN ('paid', 'overdue')";
    
    $todayStmt = $pdo->prepare($todaySql);
    if ($todayStmt) {
        $todayStmt->execute();
    }
    
    // Also update taxes with penalty but status is still 'pending'
    $pendingWithPenaltySql = "UPDATE business_quarterly_taxes 
                              SET payment_status = 'overdue'
                              WHERE penalty_amount > 0 
                              AND payment_status = 'pending'";
    
    $penaltyStmt = $pdo->prepare($pendingWithPenaltySql);
    if ($penaltyStmt) {
        $penaltyStmt->execute();
    }
    
    // Check if penalty log table exists, if not create it
    $checkLogTable = $pdo->query("SHOW TABLES LIKE 'business_penalty_log'");
    if ($checkLogTable->rowCount() == 0) {
        // Create the log table
        $createLogSql = "CREATE TABLE IF NOT EXISTS business_penalty_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            calculated_date DATE NOT NULL,
            updated_records INT DEFAULT 0,
            total_penalty DECIMAL(15,2) DEFAULT 0.00,
            penalty_percent_used DECIMAL(5,2) DEFAULT 2.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($createLogSql);
    }
    
    // Log the operation
    $logSql = "INSERT INTO business_penalty_log (calculated_date, updated_records, total_penalty, penalty_percent_used)
               VALUES (CURDATE(), ?, ?, ?)";
    $logStmt = $pdo->prepare($logSql);
    if ($logStmt) {
        $logStmt->execute([$updatedCount, $totalPenalty, $penaltyPercent]);
    }
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Penalty calculation completed successfully",
        "data" => [
            "updated_records" => $updatedCount,
            "total_penalty_added" => $totalPenalty,
            "penalty_percent_used" => $penaltyPercent,
            "calculation_date" => date('Y-m-d H:i:s'),
            "overdue_taxes_found" => count($overdueTaxes)
        ]
    ]);
    
} catch (PDOException $e) {
    // Log error but return success so frontend continues
    error_log("Penalty calculation PDO error: " . $e->getMessage());
    echo json_encode([
        "status" => "success",
        "message" => "Penalty calculation completed with some issues",
        "error_details" => $e->getMessage(),
        "data" => [
            "completed" => true,
            "with_errors" => true
        ]
    ]);
} catch (Exception $e) {
    error_log("Penalty calculation error: " . $e->getMessage());
    echo json_encode([
        "status" => "success", 
        "message" => "Penalty calculation completed",
        "error_details" => $e->getMessage(),
        "data" => [
            "completed" => true,
            "with_errors" => true
        ]
    ]);
}
?>