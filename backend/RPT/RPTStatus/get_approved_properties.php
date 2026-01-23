<?php
// revenue2/backend/RPT/RPTStatus/get_approved_properties.php
// ================================================
// GET APPROVED PROPERTIES API - SIMPLIFIED VERSION
// ================================================

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration - Hardcoded to avoid dependency issues
$host = 'localhost:3307';
$dbname = 'rpt';
$username = 'root';
$password = '';

try {
    // Create database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed: " . $e->getMessage(),
        "details" => "Check database credentials"
    ]);
    exit();
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getApprovedProperties($pdo);
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}

function getApprovedProperties($pdo) {
    try {
        // Get current quarter and year
        $currentMonth = date('n');
        $currentQuarter = ceil($currentMonth / 3);
        $currentYear = date('Y');
        $quarterStr = 'Q' . $currentQuarter;
        
        // Get approved properties with simplified query
        $query = "
            SELECT 
                pr.id,
                pr.reference_number,
                pr.lot_location,
                pr.barangay,
                pr.district,
                pr.status,
                pr.created_at,
                pr.updated_at,
                pr.has_building,
                po.first_name,
                po.last_name,
                CONCAT(po.first_name, ' ', po.last_name) as owner_name,
                po.email,
                po.phone,
                lp.land_area_sqm,
                lp.land_market_value,
                lp.land_assessed_value,
                lp.property_type,
                lp.tdn as land_tdn,
                pt.total_annual_tax,
                pt.id as property_total_id,
                -- Get building count
                (SELECT COUNT(*) FROM building_properties bp 
                 WHERE bp.land_id = lp.id AND bp.status = 'active') as building_count
            FROM property_registrations pr
            LEFT JOIN property_owners po ON pr.owner_id = po.id
            LEFT JOIN land_properties lp ON pr.id = lp.registration_id
            LEFT JOIN property_totals pt ON pr.id = pt.registration_id
            WHERE pr.status = 'approved'
            ORDER BY pr.created_at DESC
        ";
        
        $stmt = $pdo->query($query);
        $properties = $stmt->fetchAll();
        
        // Process properties to add payment status
        $processedProperties = [];
        foreach ($properties as $property) {
            // Determine payment status
            $paymentInfo = determinePaymentStatus($property, $pdo, $currentYear, $currentQuarter);
            
            $property['payment_status'] = $paymentInfo['status'];
            $property['payment_status_label'] = $paymentInfo['label'];
            $property['is_delinquent'] = $paymentInfo['is_delinquent'];
            
            $processedProperties[] = $property;
        }
        
        echo json_encode([
            "success" => true,
            "status" => "success",
            "data" => $processedProperties,
            "count" => count($processedProperties),
            "current_quarter" => $quarterStr,
            "current_year" => $currentYear
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Query failed: " . $e->getMessage(),
            "trace" => $e->getTraceAsString()
        ]);
    }
}

function determinePaymentStatus($property, $pdo, $currentYear, $currentQuarter) {
    // Default status
    $defaultStatus = [
        'status' => 'pending',
        'label' => 'Current Quarter',
        'is_delinquent' => false
    ];
    
    // If no property_total_id, return default
    if (empty($property['property_total_id'])) {
        return $defaultStatus;
    }
    
    try {
        // Check for quarterly taxes
        $query = "
            SELECT payment_status, due_date, quarter, year 
            FROM quarterly_taxes 
            WHERE property_total_id = :property_total_id
            AND year = :currentYear
            AND quarter = :quarterStr
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':property_total_id' => $property['property_total_id'],
            ':currentYear' => $currentYear,
            ':quarterStr' => 'Q' . $currentQuarter
        ]);
        
        $currentTax = $stmt->fetch();
        
        if ($currentTax) {
            // Check payment status
            switch ($currentTax['payment_status']) {
                case 'paid':
                    return [
                        'status' => 'paid',
                        'label' => 'Paid',
                        'is_delinquent' => false
                    ];
                case 'overdue':
                    return [
                        'status' => 'overdue',
                        'label' => 'Delinquent',
                        'is_delinquent' => true
                    ];
                case 'pending':
                    // Check if overdue based on due date
                    $dueDate = new DateTime($currentTax['due_date']);
                    $now = new DateTime();
                    
                    if ($now > $dueDate) {
                        return [
                            'status' => 'overdue',
                            'label' => 'Delinquent',
                            'is_delinquent' => true
                        ];
                    } else {
                        return [
                            'status' => 'pending',
                            'label' => 'Current Quarter',
                            'is_delinquent' => false
                        ];
                    }
                default:
                    return $defaultStatus;
            }
        } else {
            // No tax record found for current quarter
            // Check if property is newly registered
            $createdDate = new DateTime($property['created_at']);
            $createdMonth = (int)$createdDate->format('n');
            $createdQuarter = ceil($createdMonth / 3);
            
            if ($createdQuarter == $currentQuarter) {
                return [
                    'status' => 'next-quarter',
                    'label' => 'Next Quarter',
                    'is_delinquent' => false
                ];
            }
            
            return $defaultStatus;
        }
        
    } catch (Exception $e) {
        // Return default status on error
        return $defaultStatus;
    }
}
?>