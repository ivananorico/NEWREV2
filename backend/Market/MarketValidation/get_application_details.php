    <?php
    // revenue2/backend/Market/MarketValidation/get_application_details.php

    // Start output buffering
    ob_start();

    // Set CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json; charset=utf-8');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Disable in production

    // Simple debug log
    function debugLog($message, $data = null) {
        if (isset($_GET['debug'])) {
            $logFile = __DIR__ . '/debug.log';
            $logMessage = date('Y-m-d H:i:s') . " - $message";
            if ($data !== null) {
                $logMessage .= " - " . (is_array($data) ? json_encode($data) : $data);
            }
            file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
        }
    }

    debugLog("=== REQUEST START ===");
    debugLog("Query string", $_SERVER['QUERY_STRING'] ?? 'none');
    debugLog("GET params", $_GET);

    // Validate ID parameter
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Application ID is required',
            'code' => 'MISSING_ID'
        ]);
        exit;
    }

    $application_id = intval($_GET['id']);
    if ($application_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid application ID',
            'code' => 'INVALID_ID',
            'received_id' => $_GET['id']
        ]);
        exit;
    }

    debugLog("Processing ID", $application_id);

    try {
        // Database configuration
        $config = [
            'host' => 'localhost:3307',
            'dbname' => 'market_rent',
            'username' => 'root',
            'password' => ''
        ];
        
        debugLog("Connecting to DB", $config['dbname']);
        
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        debugLog("Database connected successfully");

        // Main application query with all necessary joins
        $query = "
            SELECT 
                -- Rental Registration (main application)
                rr.*,
                
                -- Renter Owner (applicant information)
                ro.first_name,
                ro.middle_name,
                ro.last_name,
                ro.suffix,
                ro.email,
                ro.mobile,
                ro.telephone,
                ro.house_number,
                ro.street,
                ro.barangay,
                ro.city,
                ro.province,
                ro.zip_code,
                ro.birth_date,
                ro.gender,
                ro.emergency_name,
                ro.emergency_contact,
                ro.renter_code,
                ro.status AS renter_status,
                
                -- Business/Rent Stall Information
                rs.business_name,
                rs.business_type,
                rs.start_date,
                rs.end_date,
                rs.status AS business_status,
                
                -- Stall Information
                s.name AS stall_location_name,
                s.class_id,
                s.status AS stall_status,
                s.price AS stall_price,
                
                -- Stall Class Information
                sr.class_name,
                sr.description AS stall_class_description
                
            FROM rental_registration rr
            
            -- Join Renter Owner
            LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
            
            -- Join Rent Stall
            LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
            
            -- Join Stall
            LEFT JOIN stalls s ON rr.stall_id = s.id
            
            -- Join Stall Rights
            LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
            
            WHERE rr.id = :id
            LIMIT 1
        ";
        
        debugLog("Executing query for ID", $application_id);
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':id', $application_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $application = $stmt->fetch();
        
        if (!$application) {
            debugLog("No application found", $application_id);
            
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Application not found',
                'code' => 'NOT_FOUND',
                'requested_id' => $application_id
            ]);
            exit;
        }
        
        debugLog("Application found", [
            'id' => $application['id'],
            'stall_rights_no' => $application['stall_rights_no'] ?? 'N/A',
            'status' => $application['application_status'] ?? 'N/A'
        ]);
        
        // Calculate derived fields
        $application['full_name'] = trim(
            ($application['first_name'] ?? '') . ' ' .
            (!empty($application['middle_name']) ? $application['middle_name'] . ' ' : '') .
            ($application['last_name'] ?? '') .
            (!empty($application['suffix']) ? ' ' . $application['suffix'] : '')
        );
        
        // Build address
        $addressParts = [];
        if (!empty($application['house_number'])) $addressParts[] = $application['house_number'];
        if (!empty($application['street'])) $addressParts[] = $application['street'];
        if (!empty($application['barangay'])) $addressParts[] = 'Brgy. ' . $application['barangay'];
        if (!empty($application['city'])) $addressParts[] = $application['city'];
        if (!empty($application['province'])) $addressParts[] = $application['province'];
        if (!empty($application['zip_code'])) $addressParts[] = $application['zip_code'];
        
        $application['full_address'] = implode(', ', $addressParts);
        
        // Status mapping for display
        $statusMap = [
            'pending' => 'Pending Interview',
            'interview_scheduled' => 'Interview Scheduled',
            'interviewed' => 'Interview Completed',
            'paying' => 'Payment Required',
            'paid' => 'Payment Completed',
            'need_correction' => 'Needs Correction',
            'resubmitted' => 'Resubmitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected'
        ];
        
        $application['status_display'] = $statusMap[$application['application_status']] ?? $application['application_status'];
        
        // Format financial amounts
        $application['monthly_rent_formatted'] = '₱' . number_format($application['monthly_rent'] ?? 0, 2);
        $application['stall_rights_amount_formatted'] = '₱' . number_format($application['stall_rights_amount'] ?? 0, 2);
        $application['security_bond_formatted'] = '₱' . number_format($application['security_bond'] ?? 0, 2);
        $application['total_amount_due_formatted'] = '₱' . number_format($application['total_amount_due'] ?? 0, 2);
        
        // Format dates
        $application['created_at_formatted'] = $application['created_at'] ? 
            date('F j, Y g:i A', strtotime($application['created_at'])) : 'N/A';
        
        $application['updated_at_formatted'] = $application['updated_at'] ? 
            date('F j, Y g:i A', strtotime($application['updated_at'])) : 'N/A';
        
        $application['interview_date_formatted'] = $application['interview_date'] ? 
            date('F j, Y g:i A', strtotime($application['interview_date'])) : null;
        
        $application['payment_date_formatted'] = $application['payment_date'] ? 
            date('F j, Y g:i A', strtotime($application['payment_date'])) : null;
        
        $application['birth_date_formatted'] = $application['birth_date'] ? 
            date('F j, Y', strtotime($application['birth_date'])) : 'N/A';
        
        // Calculate total amount due (stall rights + security bond)
        $application['total_amount_due_calculated'] = 
            ($application['stall_rights_amount'] ?? 0) + 
            ($application['security_bond'] ?? 0);
        
        // Prepare response
        $response = [
            'status' => 'success',
            'message' => 'Application retrieved successfully',
            'data' => $application,
            'metadata' => [
                'retrieved_at' => date('Y-m-d H:i:s'),
                'id' => $application_id
            ]
        ];
        
        debugLog("Success response prepared");
        
        echo json_encode($response);
        
    } catch(PDOException $e) {
        debugLog("Database error", $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed',
            'code' => 'DB_ERROR',
            'error' => $e->getMessage()
        ]);
    } catch(Exception $e) {
        debugLog("General error", $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'An unexpected error occurred',
            'code' => 'GENERAL_ERROR',
            'error' => $e->getMessage()
        ]);
    }

    debugLog("=== REQUEST END ===\n");
    ob_end_flush();