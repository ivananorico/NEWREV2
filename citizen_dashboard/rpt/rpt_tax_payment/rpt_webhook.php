<?php
// rpt_webhook.php - FIXED PATH VERSION
// Location: revenue2/citizen_dashboard/rpt/rpt_tax_payment/rpt_webhook.php

header('Content-Type: application/json');

// Get input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log for debugging
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Webhook called\n", FILE_APPEND);
file_put_contents('webhook_log.txt', "Input: " . $input . "\n", FILE_APPEND);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

// Check required fields
$required = ['client_reference', 'receipt_number', 'paid_at', 'status'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        file_put_contents('webhook_log.txt', "Missing field: $field\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        exit;
    }
}

// Only process paid
if ($data['status'] !== 'paid') {
    echo json_encode(['status' => 'ignored', 'message' => 'Not paid']);
    exit;
}

// Extract ID from client_reference
$client_ref = $data['client_reference'];
file_put_contents('webhook_log.txt', "Client ref: $client_ref\n", FILE_APPEND);

// Try to extract ID
if (preg_match('/RPT-(?:TAX-)?(\d+)/', $client_ref, $matches)) {
    $tax_id = intval($matches[1]);
} elseif (preg_match('/\d+/', $client_ref, $matches)) {
    $tax_id = intval($matches[0]);
} else {
    file_put_contents('webhook_log.txt', "Cannot extract ID from: $client_ref\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Invalid client reference: $client_ref"]);
    exit;
}

file_put_contents('webhook_log.txt', "Extracted tax ID: $tax_id\n", FILE_APPEND);

// Connect to RPT database - CORRECT PATH: Go up 4 levels
// From: revenue2/citizen_dashboard/rpt/rpt_tax_payment/
// To: revenue2/db/RPT/
$db_path = __DIR__ . '/../../../../db/RPT/rpt_db.php';
file_put_contents('webhook_log.txt', "Looking for DB at: $db_path\n", FILE_APPEND);

// Check if file exists
if (!file_exists($db_path)) {
    file_put_contents('webhook_log.txt', "DB file not found at: $db_path\n", FILE_APPEND);
    
    // List all possible paths for debugging
    $possible_paths = [
        __DIR__ . '/../../../../db/RPT/rpt_db.php',  // Correct one
        __DIR__ . '/../../../db/RPT/rpt_db.php',     // One level less
        __DIR__ . '/../../db/RPT/rpt_db.php',        // Two levels less
        __DIR__ . '/../db/RPT/rpt_db.php',           // Three levels less
    ];
    
    $found = false;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $db_path = $path;
            $found = true;
            file_put_contents('webhook_log.txt', "Found DB at: $path\n", FILE_APPEND);
            break;
        }
    }
    
    if (!$found) {
        // Try direct connection if file doesn't exist
        file_put_contents('webhook_log.txt', "No DB file found, trying direct connection\n", FILE_APPEND);
        
        $host = 'localhost:3307';
        $dbname = 'rpt';
        $username = 'root';
        $password = '';
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            file_put_contents('webhook_log.txt', "Direct DB connection successful\n", FILE_APPEND);
        } catch (PDOException $e) {
            file_put_contents('webhook_log.txt', "Direct DB connection failed: " . $e->getMessage() . "\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }
    } else {
        // Include the found file
        require_once $db_path;
        
        // Check if $pdo exists after including
        if (!isset($pdo)) {
            file_put_contents('webhook_log.txt', "$pdo variable not set after including rpt_db.php\n", FILE_APPEND);
            // Try direct connection
            $host = 'localhost:3307';
            $dbname = 'rpt';
            $username = 'root';
            $password = '';
            
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                file_put_contents('webhook_log.txt', "Direct connection also failed: " . $e->getMessage() . "\n", FILE_APPEND);
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
                exit;
            }
        }
    }
} else {
    // Include the existing file
    require_once $db_path;
}

// Ensure $pdo exists
if (!isset($pdo)) {
    file_put_contents('webhook_log.txt', "$pdo still not set, trying final direct connection\n", FILE_APPEND);
    
    $host = 'localhost:3307';
    $dbname = 'rpt';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        file_put_contents('webhook_log.txt', "Final direct connection successful\n", FILE_APPEND);
    } catch (PDOException $e) {
        file_put_contents('webhook_log.txt', "Final DB connection failed: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
}

try {
    // Check if tax exists
    file_put_contents('webhook_log.txt', "Checking if tax ID $tax_id exists...\n", FILE_APPEND);
    
    $check = $pdo->prepare("SELECT id, payment_status, receipt_number FROM quarterly_taxes WHERE id = ?");
    $check->execute([$tax_id]);
    $tax = $check->fetch();
    
    if (!$tax) {
        file_put_contents('webhook_log.txt', "Tax ID $tax_id not found\n", FILE_APPEND);
        
        // List available IDs
        $list = $pdo->query("SELECT id, quarter, year, payment_status FROM quarterly_taxies LIMIT 10");
        $available = $list->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents('webhook_log.txt', "Available: " . print_r($available, true) . "\n", FILE_APPEND);
        
        http_response_code(404);
        echo json_encode([
            'status' => 'error', 
            'message' => "Tax ID $tax_id not found",
            'available_ids' => array_column($available, 'id')
        ]);
        exit;
    }
    
    file_put_contents('webhook_log.txt', "Found tax: " . print_r($tax, true) . "\n", FILE_APPEND);
    
    // If already paid with same receipt
    if ($tax['payment_status'] === 'paid' && $tax['receipt_number'] === $data['receipt_number']) {
        file_put_contents('webhook_log.txt', "Already paid with same receipt\n", FILE_APPEND);
        echo json_encode([
            'status' => 'success', 
            'message' => 'Already paid',
            'already_paid' => true
        ]);
        exit;
    }
    
    // Update the tax
    file_put_contents('webhook_log.txt', "Updating tax ID $tax_id...\n", FILE_APPEND);
    
    $stmt = $pdo->prepare("
        UPDATE quarterly_taxes 
        SET payment_status = 'paid', 
            payment_date = ?,
            receipt_number = ?
        WHERE id = ?
    ");
    
    $payment_date = date('Y-m-d', strtotime($data['paid_at']));
    $stmt->execute([
        $payment_date,
        $data['receipt_number'],
        $tax_id
    ]);
    
    $rows = $stmt->rowCount();
    file_put_contents('webhook_log.txt', "Rows updated: $rows\n", FILE_APPEND);
    
    if ($rows > 0) {
        // Verify update
        $verify = $pdo->prepare("SELECT * FROM quarterly_taxes WHERE id = ?");
        $verify->execute([$tax_id]);
        $updated = $verify->fetch(PDO::FETCH_ASSOC);
        
        file_put_contents('webhook_log.txt', "Verified update: " . print_r($updated, true) . "\n", FILE_APPEND);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Payment recorded',
            'tax_id' => $tax_id,
            'rows_updated' => $rows,
            'new_status' => 'paid',
            'receipt' => $data['receipt_number']
        ]);
    } else {
        file_put_contents('webhook_log.txt', "No rows updated. Current status: " . $tax['payment_status'] . "\n", FILE_APPEND);
        echo json_encode([
            'status' => 'warning', 
            'message' => 'No update needed',
            'current_status' => $tax['payment_status'],
            'existing_receipt' => $tax['receipt_number']
        ]);
    }
    
} catch (PDOException $e) {
    file_put_contents('webhook_log.txt', "Database error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

file_put_contents('webhook_log.txt', "=== WEBHOOK ENDED ===\n\n", FILE_APPEND);
?>