<?php
// revenue2/backend/Market/MarketValidation/set_interview.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection
require_once '../../../db/Market/market_db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON data',
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

// Get application ID
$app_id = 0;
if (isset($data['application_id'])) {
    $app_id = intval($data['application_id']);
}

if ($app_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid application ID'
    ]);
    exit;
}

// Get interviewer name
$interviewer = isset($data['interviewer']) ? trim($data['interviewer']) : '';

if (empty($interviewer)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Interviewer name is required'
    ]);
    exit;
}

$interview_notes = isset($data['interview_notes']) ? trim($data['interview_notes']) : '';

// Get interview date if provided, otherwise use current date + 1 week
$interview_date = isset($data['interview_date']) ? trim($data['interview_date']) : '';
if (empty($interview_date)) {
    // Default to one week from now
    $interview_date = date('Y-m-d H:i:s', strtotime('+1 week'));
} else {
    // Validate and format the date
    $interview_date = date('Y-m-d H:i:s', strtotime($interview_date));
}

// Get interview location if provided
$interview_location = isset($data['interview_location']) ? trim($data['interview_location']) : 'Market Administration Office, 2nd Floor, Public Market Building';

// Get contact person and number for interview
$contact_person = isset($data['contact_person']) ? trim($data['contact_person']) : 'Market Administrator';
$contact_number = isset($data['contact_number']) ? trim($data['contact_number']) : '(02) 1234-5680';

try {
    // Database connection is already available from market_db.php
    
    // Check if application exists and get current status
    $stmt = $pdo->prepare("SELECT id, application_status, user_id FROM rental_registration WHERE id = ?");
    $stmt->execute([$app_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Application not found',
            'application_id' => $app_id
        ]);
        exit;
    }
    
    // Check if current status is appropriate for scheduling interview
    $current_status = $existing['application_status'];
    $allowed_statuses = ['pending', 'need_correction', 'resubmitted'];
    
    if (!in_array($current_status, $allowed_statuses)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot schedule interview for application with status: ' . $current_status,
            'current_status' => $current_status,
            'allowed_statuses' => $allowed_statuses
        ]);
        exit;
    }
    
    // Update application status to 'interview_scheduled'
    $sql = "UPDATE rental_registration SET 
            application_status = 'interview_scheduled',
            interviewer = :interviewer,
            interview_notes = :interview_notes,
            interview_date = :interview_date,
            updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':interviewer' => $interviewer,
        ':interview_notes' => $interview_notes,
        ':interview_date' => $interview_date,
        ':id' => $app_id
    ]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        // Also update the renter_owner status if needed
        $update_renter_sql = "UPDATE renter_owner SET 
                            status = 'active',
                            updated_at = NOW()
                            WHERE registration_id = :id";
        $update_renter_stmt = $pdo->prepare($update_renter_sql);
        $update_renter_stmt->execute([':id' => $app_id]);
        
        // Get user email and mobile for notification (if needed in future)
        $user_info_sql = "SELECT email, mobile FROM renter_owner WHERE registration_id = :id";
        $user_info_stmt = $pdo->prepare($user_info_sql);
        $user_info_stmt->execute([':id' => $app_id]);
        $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Format the response data
        $formatted_date = date('F j, Y', strtotime($interview_date));
        $formatted_time = date('h:i A', strtotime($interview_date));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Application interview scheduled successfully',
            'data' => [
                'application_id' => $app_id,
                'new_status' => 'interview_scheduled',
                'interviewer' => $interviewer,
                'interview_date' => $interview_date,
                'formatted_interview_date' => $formatted_date,
                'formatted_interview_time' => $formatted_time,
                'interview_datetime_display' => $formatted_date . ' at ' . $formatted_time,
                'interview_location' => $interview_location,
                'contact_person' => $contact_person,
                'contact_number' => $contact_number,
                'previous_status' => $current_status,
                'interview_notes' => $interview_notes,
                'user_notification_info' => [
                    'email' => $user_info['email'] ?? '',
                    'mobile' => $user_info['mobile'] ?? ''
                ]
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'info',
            'message' => 'No changes made',
            'application_id' => $app_id
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error',
        'error' => $e->getMessage(),
        'error_details' => $e->errorInfo ?? []
    ]);
}
?>