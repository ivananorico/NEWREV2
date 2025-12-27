<?php
// ================================================
// UPDATE REGISTRATION STATUS API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = getDatabaseConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $errorMsg]);
    exit();
}

// Determine HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Route based on method
switch ($method) {
    case 'POST':
        updateRegistrationStatus($pdo);
        break;
    case 'OPTIONS':
        http_response_code(200);
        exit();
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}

// ==========================
// FUNCTIONS
// ==========================

function updateRegistrationStatus($pdo) {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Log received data for debugging
    error_log("Received update request: " . print_r($data, true));

    // Validate required fields
    $requiredFields = ['registration_id', 'status'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required field: " . $field]);
            return;
        }
    }

    // Validate status
    $validStatuses = ['pending', 'for_inspection', 'needs_correction', 'resubmitted', 'assessed', 'approved', 'rejected'];
    $status = trim($data['status']);
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid status. Must be one of: " . implode(', ', $validStatuses)]);
        return;
    }

    $registration_id = intval($data['registration_id']);
    
    // Debug: Check if registration exists
    try {
        $checkStmt = $pdo->prepare("SELECT id, status FROM property_registrations WHERE id = ?");
        $checkStmt->execute([$registration_id]);
        $existingRegistration = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingRegistration) {
            error_log("Registration ID {$registration_id} not found in database");
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Registration not found or no changes made"]);
            return;
        }
        
        error_log("Found registration ID {$registration_id} with current status: " . $existingRegistration['status']);
        
    } catch (PDOException $e) {
        error_log("Database error checking registration: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        return;
    }

    try {
        // Handle different status updates with appropriate logic
        switch ($status) {
            case 'needs_correction':
                // For needs_correction, we expect correction_notes
                $correction_notes = isset($data['correction_notes']) ? trim($data['correction_notes']) : '';
                if (empty($correction_notes)) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Correction notes are required for 'needs_correction' status"]);
                    return;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE property_registrations 
                    SET status = ?, 
                        correction_notes = ?,
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $success = $stmt->execute([
                    $status,
                    $correction_notes,
                    $registration_id
                ]);
                break;
                
            case 'resubmitted':
                // For resubmitted, clear correction notes
                $stmt = $pdo->prepare("
                    UPDATE property_registrations 
                    SET status = ?, 
                        correction_notes = NULL,
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $success = $stmt->execute([
                    $status,
                    $registration_id
                ]);
                break;
                
            default:
                // For other statuses, just update status
                $stmt = $pdo->prepare("
                    UPDATE property_registrations 
                    SET status = ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $success = $stmt->execute([
                    $status,
                    $registration_id
                ]);
                break;
        }

        if ($success && $stmt->rowCount() > 0) {
            // Get updated registration details for response
            $selectStmt = $pdo->prepare("
                SELECT id, reference_number, status, correction_notes, updated_at, created_at
                FROM property_registrations 
                WHERE id = ?
            ");
            $selectStmt->execute([$registration_id]);
            $registration = $selectStmt->fetch(PDO::FETCH_ASSOC);

            // Log successful update
            error_log("Successfully updated registration ID {$registration_id} to status: {$status}");
            
            echo json_encode([
                "success" => true,
                "status" => "success",
                "message" => "Registration status updated to '{$status}' successfully",
                "data" => [
                    "registration" => $registration,
                    "id" => $registration_id,
                    "status" => $status,
                    "action" => "updated"
                ]
            ]);
        } else {
            // Check if status was already the same
            if ($existingRegistration['status'] === $status) {
                error_log("Registration ID {$registration_id} already has status: {$status}");
                echo json_encode([
                    "success" => true,
                    "status" => "success",
                    "message" => "Status already set to '{$status}'",
                    "data" => [
                        "id" => $registration_id,
                        "status" => $status,
                        "action" => "no_change"
                    ]
                ]);
            } else {
                error_log("Failed to update registration ID {$registration_id}. No rows affected.");
                echo json_encode(["success" => false, "message" => "No changes made. Registration may not exist or data is identical."]);
            }
        }
    } catch (PDOException $e) {
        error_log("Database update error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to update registration status: " . $e->getMessage()]);
    }
}
?>