<?php
// get_stall_rights.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Use the same database connection path as your maps file
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    // First, let's check what tables exist in the database
    $tablesQuery = $pdo->query("SHOW TABLES");
    $tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);
    error_log("Available tables: " . print_r($tables, true));

    // Common table names for stall classes
    $possibleTableNames = [
        'stall_classes',
        'stall_class',
        'stall_category',
        'stall_categories',
        'classes',
        'stall_rights'  // Based on your function name
    ];

    $tableFound = null;
    $tableColumns = null;

    // Try to find the right table
    foreach ($possibleTableNames as $tableName) {
        if (in_array($tableName, $tables)) {
            $tableFound = $tableName;
            break;
        }
    }

    if (!$tableFound) {
        // If no table found, create a default one or return defaults
        echo json_encode([
            "status" => "success",
            "message" => "No stall classes table found, using defaults",
            "classes" => [
                ["class_id" => 1, "class_name" => "A", "price" => 10000.00],
                ["class_id" => 2, "class_name" => "B", "price" => 7500.00],
                ["class_id" => 3, "class_name" => "C", "price" => 5000.00]
            ]
        ]);
        exit;
    }

    // Get columns from the found table
    $columnsQuery = $pdo->query("DESCRIBE $tableFound");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
    error_log("Columns in $tableFound: " . print_r($columns, true));

    // Determine column names (common variations)
    $idColumn = null;
    $nameColumn = null;
    $priceColumn = null;

    foreach ($columns as $column) {
        if (stripos($column, 'id') !== false && !$idColumn) {
            $idColumn = $column;
        }
        if (stripos($column, 'name') !== false && !$nameColumn) {
            $nameColumn = $column;
        }
        if ((stripos($column, 'price') !== false || stripos($column, 'rate') !== false || stripos($column, 'cost') !== false) && !$priceColumn) {
            $priceColumn = $column;
        }
    }

    // Use defaults if columns not found
    if (!$idColumn) $idColumn = 'id';
    if (!$nameColumn) $nameColumn = 'name';
    if (!$priceColumn) $priceColumn = 'price';

    // Fetch stall classes
    $sql = "SELECT 
                $idColumn as class_id,
                $nameColumn as class_name,
                $priceColumn as price
            FROM $tableFound
            ORDER BY $priceColumn DESC";

    error_log("Executing query: $sql");
    
    $stmt = $pdo->query($sql);
    
    if ($stmt) {
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "status" => "success",
            "count" => count($classes),
            "table_found" => $tableFound,
            "classes" => $classes
        ]);
    } else {
        throw new Exception("Failed to fetch stall classes from table: $tableFound");
    }

} catch (Exception $e) {
    error_log("Error in get_stall_rights.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "debug_info" => [
            "file" => __FILE__,
            "line" => __LINE__
        ]
    ]);
}
?>