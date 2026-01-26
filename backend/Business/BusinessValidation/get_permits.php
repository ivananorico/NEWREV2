<?php
// get_permits.php
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

// Set CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to include database connection
if (file_exists('../../../db/Business/business_db.php')) {
    require_once '../../../db/Business/business_db.php';
    $pdo = getDatabaseConnection();
} else {
    // Fallback direct connection
    $isLocal = (isset($_SERVER['HTTP_HOST']) && 
               (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false));
    
    if ($isLocal) {
        $host = 'localhost:3307';
        $dbname = 'business_tax';
        $user = 'root';
        $pass = '';
    } else {
        $host = 'localhost';
        $dbname = 'reve_business';
        $user = 'reve_business';
        $pass = 'eIuEFsbOpmlo-hpu';
    }
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed',
            'error' => $e->getMessage()
        ]);
        exit();
    }
}

if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to database',
        'permits' => [],
        'count' => 0
    ]);
    exit();
}

try {
    // Simple query
    $stmt = $pdo->query("
        SELECT id, applicant_id as business_permit_id, business_name, 
               owner_full_name as owner_name, business_nature as business_type,
               contact_number, email_address as owner_email, capital_investment,
               permit_status as status, created_at
        FROM business_permits 
        ORDER BY created_at DESC
    ");
    
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Business permits retrieved',
        'permits' => $permits,
        'count' => count($permits)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'permits' => [],
        'count' => 0
    ]);
}
?>