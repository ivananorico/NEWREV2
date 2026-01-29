<?php
// revenue2/citizen_dashboard/rpt/rpt_application/approved.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
require_once '../../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to build full address from owner details
function buildFullAddress($owner) {
    $address_parts = [];
    
    if (!empty($owner['house_number'])) {
        $address_parts[] = $owner['house_number'];
    }
    
    if (!empty($owner['street'])) {
        $address_parts[] = $owner['street'];
    }
    
    if (!empty($owner['owner_barangay'])) {
        $address_parts[] = $owner['owner_barangay'];
    }
    
    if (!empty($owner['owner_city'])) {
        $address_parts[] = $owner['owner_city'];
    }
    
    if (!empty($owner['owner_province'])) {
        $address_parts[] = $owner['owner_province'];
    }
    
    if (!empty($owner['owner_zip_code'])) {
        $address_parts[] = $owner['owner_zip_code'];
    }
    
    // If we have no specific parts, use the address field
    if (empty($address_parts) && !empty($owner['address'])) {
        return $owner['address'];
    }
    
    return implode(', ', $address_parts);
}

// Function to check if tax clearance exists for a year
function getTaxClearanceInfo($property_total_id, $year, $pdo) {
    $query = "
        SELECT tc.*,
               DATE_ADD(tc.issue_date, INTERVAL 1 YEAR) as valid_until
        FROM tax_clearances tc
        WHERE tc.property_total_id = ? 
        AND tc.clearance_year = ?
        ORDER BY tc.issue_date DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$property_total_id, $year]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to check if all quarters are paid for a specific year
function isAllQuartersPaidForYear($quarterly_taxes, $year) {
    if (empty($quarterly_taxes)) return false;
    
    $year_quarters = array_filter($quarterly_taxes, function($qt) use ($year) {
        return $qt['year'] == $year;
    });
    
    if (empty($year_quarters)) return false;
    
    foreach ($year_quarters as $tax) {
        if ($tax['payment_status'] != 'paid') {
            return false;
        }
    }
    
    return true;
}

// Function to check if eligible for annual discount (Jan 1-31 only)
function isEligibleForAnnualDiscount($quarterly_taxes) {
    $current_date = date('Y-m-d');
    $current_month = date('n'); // 1-12
    $current_day = date('j'); // 1-31
    
    // Check if current date is January 1-31
    if ($current_month != 1 || $current_day > 31) {
        return false;
    }
    
    // Check if all quarters are unpaid and not overdue
    foreach ($quarterly_taxes as $quarter) {
        if ($quarter['payment_status'] == 'paid' || $quarter['payment_status'] == 'overdue') {
            return false;
        }
    }
    
    return true;
}

// Function to get discount percentage from config
function getDiscountPercentage($pdo) {
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM discount_configurations 
            WHERE status = 'active' 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $discount['discount_percent'] ?? 10.00;
    } catch(PDOException $e) {
        return 10.00; // Default 10%
    }
}

// Fetch user's approved applications
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.city,
            pr.province,
            pr.zip_code,
            pr.has_building,
            pr.status,
            DATE(pr.created_at) as application_date,
            DATE(pr.updated_at) as approval_date,
            
            po.first_name,
            po.last_name,
            po.middle_name,
            po.suffix,
            po.email,
            po.phone,
            po.address,
            po.house_number,
            po.street,
            po.barangay as owner_barangay,
            po.district as owner_district,
            po.city as owner_city,
            po.province as owner_province,
            po.zip_code as owner_zip_code,
            po.birthdate,
            po.sex,
            po.marital_status
            
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
          AND pr.status = 'approved'
        ORDER BY pr.updated_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get base URL for background image
// Get base URL for background image - FIXED: Using reliable method
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}
$base_url = $protocol . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Applications - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #10b981;
            --background: #fbfbfb;
        }

        body {
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: Inter, system-ui, sans-serif;
        }

        /* Background image with blur */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?php echo $bg_image_path; ?>') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
            z-index: -2;
            filter: blur(1px);
        }
        
        /* Animated background particles */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(74, 144, 226, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(253, 168, 17, 0.05) 0%, transparent 50%);
            animation: backgroundFloat 20s ease-in-out infinite;
            z-index: -1;
        }
        
        @keyframes backgroundFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        .simple-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .status-paid {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .info-box {
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 6px;
        }

        .simple-progress {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }

        .simple-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #10b981 100%);
            border-radius: 3px;
        }

        .simple-doc-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .simple-doc-card:hover {
            border-color: #3b82f6;
            cursor: pointer;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }

        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.9); 
        }
        .modal-content { 
            margin: auto; 
            display: block; 
            max-width: 90%; 
            max-height: 90vh; 
            border-radius: 8px; 
        }
        .modal-close { 
            position: absolute; 
            top: 20px; 
            right: 35px; 
            color: white; 
            font-size: 40px; 
            font-weight: bold; 
            cursor: pointer; 
            z-index: 1001; 
        }
        .modal-close:hover { 
            color: #fbbf24; 
        }
        .modal-caption { 
            text-align: center; 
            color: white; 
            padding: 10px 20px; 
            position: absolute; 
            bottom: 0; 
            width: 100%; 
            background: rgba(0,0,0,0.7); 
        }

        .header-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
        }

        .lgu-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 0.75rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all .25s ease;
        }

        .clearance-card-compact {
            background: #fff;
            border: 1px solid #d1fae5;
            border-radius: 6px;
            padding: 0.75rem;
            transition: all 0.2s ease;
            border-left: 3px solid #10b981;
        }
        
        .clearance-card-compact:hover {
            background: #f0fdf4;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
        }

        .assessment-section {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
            height: 100%;
        }

        .tax-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .download-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .download-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .download-btn-compact {
            background: #10b981;
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .download-btn-compact:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .notification-slide {
            animation: slideIn .3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @media (max-width: 768px) {
            .responsive-grid {
                grid-template-columns: 1fr;
            }
        }

        .tax-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .tax-table th {
            background: #f9fafb;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tax-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tax-table tr:hover {
            background: #f9fafb;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 1rem 0;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
            transform: translateY(-50%);
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-circle {
            width: 24px;
            height: 24px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 50%;
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .step.active .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .step.active .step-label {
            color: #111827;
            font-weight: 600;
        }

        .assessment-grid-item {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.25rem;
            transition: all 0.3s ease;
        }

        .assessment-grid-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .land-assessment-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
        }

        .building-assessment-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #bbf7d0;
        }

        .tax-summary-card {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            border: 1px solid #ddd6fe;
        }

        .assessment-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .assessment-value-item {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
            padding: 0.75rem;
        }

        /* Billing Button Styles */
        .billing-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .billing-btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .billing-btn-locked {
            background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .billing-btn-locked:hover {
            background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
            transform: none;
            box-shadow: none;
        }
        
        .tooltip-container {
            position: relative;
            display: inline-block;
        }
        
        .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #1f2937;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 10;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="container mx-auto px-6 py-10 flex-grow max-w-6xl">
    <!-- Page Header -->
    <div class="header-box mb-8">
        <div class="flex items-center">
            <a href="../rpt_services.php" class="inline-flex items-center text-gray-600 hover:text-[var(--primary)] mr-6">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    Approved Applications
                </h1>
                <p class="text-gray-600 text-lg">
                    Your approved property tax assessments
                </p>
            </div>
        </div>
        <div class="h-1 w-20 rounded-full mt-4" style="background-color: #10b981;"></div>
        
        <?php if ($total_applications > 0): ?>
        <div class="flex items-center mt-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <div>
                <div class="font-semibold text-gray-900"><?php echo $total_applications; ?> Approved Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                <div class="text-sm text-gray-600 mt-1">Ready for tax payment and certificate download</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="notification-slide mb-6 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="lgu-card p-12 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-check-circle text-5xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Approved Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any approved applications yet.</p>
            <div class="space-x-4">
                <a href="pending.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm transition-colors">
                    <i class="fas fa-clock mr-2"></i>Pending Applications
                </a>
                <a href="assessed.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Under Assessment
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Applications List -->
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . 
                        (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                        $app['last_name'] . 
                        (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    $owner_address = buildFullAddress($app);
                    
                    // Get land assessment data
                    $land_data = [];
                    try {
                        $land_stmt = $pdo->prepare("
                            SELECT lp.*, lc.classification
                            FROM land_properties lp
                            JOIN land_configurations lc ON lp.land_config_id = lc.id
                            WHERE lp.registration_id = ?
                        ");
                        $land_stmt->execute([$app['id']]);
                        $land_data = $land_stmt->fetch(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {}
                    
                    // Get building assessment data
                    $building_data = [];
                    if ($app['has_building'] == 'yes') {
                        try {
                            $building_stmt = $pdo->prepare("
                                SELECT bp.*, pc.material_type
                                FROM building_properties bp
                                JOIN property_configurations pc ON bp.property_config_id = pc.id
                                WHERE bp.land_id = ?
                            ");
                            $building_stmt->execute([$land_data['id'] ?? 0]);
                            $building_data = $building_stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch(PDOException $e) {}
                    }
                    
                    // Get total tax data
                    $total_tax_data = [];
                    try {
                        $total_stmt = $pdo->prepare("
                            SELECT * FROM property_totals 
                            WHERE registration_id = ?
                        ");
                        $total_stmt->execute([$app['id']]);
                        $total_tax_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {}
                    
                    // Get quarterly taxes
                    $quarterly_taxes = [];
                    $tax_years = [];
                    if (!empty($total_tax_data)) {
                        try {
                            $quarterly_stmt = $pdo->prepare("
                                SELECT * FROM quarterly_taxes 
                                WHERE property_total_id = ?
                                ORDER BY year DESC, FIELD(quarter, 'Q1', 'Q2', 'Q3', 'Q4')
                            ");
                            $quarterly_stmt->execute([$total_tax_data['id']]);
                            $quarterly_taxes = $quarterly_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Get unique years
                            $tax_years = array_unique(array_column($quarterly_taxes, 'year'));
                        } catch(PDOException $e) {}
                    }
                    
                    // Get tax clearances for each year
                    $tax_clearances = [];
                    if (!empty($total_tax_data)) {
                        foreach ($tax_years as $year) {
                            $clearance = getTaxClearanceInfo($total_tax_data['id'], $year, $pdo);
                            if ($clearance) {
                                $tax_clearances[$year] = $clearance;
                            }
                        }
                    }
                    
                    // Calculate totals
                    $total_land_tax = $land_data['annual_tax'] ?? 0;
                    $total_building_tax = 0;
                    $total_penalty = 0;
                    $paid_count = 0;
                    $pending_count = 0;
                    $overdue_count = 0;
                    
                    foreach ($building_data as $building) {
                        $total_building_tax += $building['annual_tax'] ?? 0;
                    }
                    
                    foreach ($quarterly_taxes as $quarter) {
                        $total_penalty += $quarter['penalty_amount'] ?? 0;
                        if ($quarter['payment_status'] == 'paid') $paid_count++;
                        elseif ($quarter['payment_status'] == 'overdue') $overdue_count++;
                        else $pending_count++;
                    }
                    
                    $total_annual_tax = $total_land_tax + $total_building_tax;
                    $grand_total = $total_annual_tax + $total_penalty;
                    
                    // Check discount eligibility
                    $eligible_for_discount = isEligibleForAnnualDiscount($quarterly_taxes);
                    $discount_percent = getDiscountPercentage($pdo);
                    $discount_amount = $eligible_for_discount ? ($total_annual_tax * ($discount_percent / 100)) : 0;
                    $discounted_total = $eligible_for_discount ? ($total_annual_tax - $discount_amount) : $grand_total;
                    
                    // Check if current year quarters are all paid
                    $current_year = date('Y');
                    $current_year_all_paid = isAllQuartersPaidForYear($quarterly_taxes, $current_year);
                ?>

                <!-- Application Card -->
                <div class="lgu-card overflow-hidden">
                    <!-- Header with Billing Button -->
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="font-semibold text-gray-900"><?php echo $app['reference_number']; ?></span>
                                    <span class="status-badge status-approved">
                                        <i class="fas fa-check-circle mr-1"></i>Approved
                                    </span>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                </div>
                            </div>
                            
                            <!-- Billing Statement Button -->
                            <div class="tooltip-container">
                                <?php if (!$current_year_all_paid): ?>
                                    <!-- Active button when NOT all quarters are paid for current year -->
                                    <a href="view_rpt_bill.php?registration_id=<?php echo $app['id']; ?>" 
                                       target="_blank"
                                       class="billing-btn">
                                        <i class="fas fa-file-invoice mr-2"></i>
                                        View Billing Statement
                                        <?php if ($paid_count > 0): ?>
                                            <span class="ml-2 text-xs bg-blue-800 text-white px-2 py-1 rounded-full">
                                                <?php echo $paid_count; ?>/<?php echo count($quarterly_taxes); ?> paid
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <!-- Locked button when ALL quarters are paid for current year -->
                                    <button class="billing-btn-locked" disabled>
                                        <i class="fas fa-lock mr-2"></i>
                                        Billing Statement
                                        <span class="ml-2 text-xs bg-green-600 text-white px-2 py-1 rounded-full">
                                            <i class="fas fa-check mr-1"></i> All paid
                                        </span>
                                    </button>
                                    <div class="tooltip-text">
                                        <div class="font-semibold mb-1">All Taxes Paid for <?php echo $current_year; ?></div>
                                        <p class="text-gray-300 text-xs">All quarterly taxes have been fully paid for the current year.</p>
                                        <p class="text-green-400 text-xs mt-1 flex items-center">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Tax clearance certificate is available below
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Progress Steps -->
                        <div class="progress-steps">
                            <div class="step active">
                                <div class="step-circle"><i class="fas fa-clock"></i></div>
                                <div class="step-label">Pending</div>
                            </div>
                            <div class="step active">
                                <div class="step-circle"><i class="fas fa-search"></i></div>
                                <div class="step-label">Inspection</div>
                            </div>
                            <div class="step active">
                                <div class="step-circle"><i class="fas fa-chart-line"></i></div>
                                <div class="step-label">Assessment</div>
                            </div>
                            <div class="step active">
                                <div class="step-circle"><i class="fas fa-check"></i></div>
                                <div class="step-label">Approved</div>
                            </div>
                        </div>
                    </div>

                    <!-- COMPACT TAX CLEARANCE SECTION -->
                    <?php if (!empty($tax_clearances)): ?>
                    <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50/30">
                        <h3 class="font-medium text-gray-900 mb-3 flex items-center text-sm">
                            <i class="fas fa-file-certificate text-green-600 mr-2 text-sm"></i>Available Tax Clearance Certificates
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php foreach ($tax_clearances as $year => $clearance): 
                                $valid_until = $clearance['valid_until'] ?? date('Y-12-31', strtotime("+1 year"));
                                $is_valid = strtotime($valid_until) >= time();
                            ?>
                                <div class="clearance-card-compact">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-award text-green-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <div class="text-xs font-medium text-gray-900"><?php echo $year; ?> Clearance</div>
                                                <div class="text-[10px] text-gray-500 font-mono"><?php echo substr($clearance['certificate_number'], -8); ?></div>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($is_valid): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1 text-[10px]"></i>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-exclamation mr-1 text-[10px]"></i>Expired
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between text-xs text-gray-600 mb-3">
                                        <div class="space-y-1">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-alt mr-1 text-[10px] text-gray-400"></i>
                                                <span>Issued: <?php echo date('M j, Y', strtotime($clearance['issue_date'])); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-check mr-1 text-[10px] text-gray-400"></i>
                                                <span>Valid: <?php echo date('M j, Y', strtotime($valid_until)); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <a href="tax_clearance_certificate.php?property_total_id=<?php echo $total_tax_data['id']; ?>&year=<?php echo $year; ?>" 
                                           target="_blank"
                                           class="download-btn-compact">
                                            <i class="fas fa-download mr-1 text-[10px]"></i> Download
                                        </a>
                                        <span class="text-[10px] text-gray-500">
                                            <i class="fas fa-external-link-alt mr-1"></i> New window
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Information Sections -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Owner Information -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                    <i class="fas fa-user text-blue-600 mr-2"></i>Owner Information
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Full Name</div>
                                            <div class="text-sm font-medium"><?php echo $full_name; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Contact</div>
                                            <div class="text-sm font-medium"><?php echo $app['phone']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Email</div>
                                            <div class="text-sm font-medium"><?php echo $app['email']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Birthdate</div>
                                            <div class="text-sm font-medium"><?php echo isset($app['birthdate']) ? date('M j, Y', strtotime($app['birthdate'])) : '-'; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Gender</div>
                                            <div class="text-sm font-medium"><?php echo ucfirst($app['sex'] ?? '-'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Marital Status</div>
                                            <div class="text-sm font-medium"><?php echo ucfirst($app['marital_status'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Address</div>
                                        <div class="text-sm">
                                            <?php echo $owner_address; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Property Information -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                    <i class="fas fa-home text-green-600 mr-2"></i>Property Information
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Location</div>
                                            <div class="text-sm font-medium"><?php echo $app['lot_location']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Barangay</div>
                                            <div class="text-sm font-medium">Brgy. <?php echo $app['barangay']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">District</div>
                                            <div class="text-sm font-medium"><?php echo $app['district']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">City</div>
                                            <div class="text-sm font-medium"><?php echo $app['city']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Province</div>
                                            <div class="text-sm font-medium"><?php echo $app['province']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Zip Code</div>
                                            <div class="text-sm font-medium"><?php echo $app['zip_code']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Property Type</div>
                                        <div class="text-sm font-medium"><?php echo $app['has_building'] == 'yes' ? 'With Building' : 'Vacant Land'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PERFECTLY ALIGNED ASSESSMENT SECTIONS -->
                        <div class="mt-8 pt-8 border-t border-gray-200">
                            <h3 class="font-medium text-gray-900 mb-6 flex items-center">
                                <i class="fas fa-chart-bar text-blue-600 mr-2"></i>Assessment Summary
                            </h3>
                            
                            <!-- Perfect Grid for Assessments -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                
                                <!-- Land Assessment -->
                                <div class="assessment-section land-assessment-card">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-base font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-map text-blue-600 mr-2"></i>Land Assessment
                                        </h4>
                                        <span class="text-xs font-medium text-blue-600">
                                            <?php echo $land_data['tdn'] ?? 'TDN: N/A'; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Property Details Grid -->
                                    <div class="mb-6">
                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Property Type</div>
                                                <div class="text-base font-bold text-gray-900"><?php echo $land_data['property_type'] ?? 'N/A'; ?></div>
                                            </div>
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Land Area</div>
                                                <div class="text-base font-bold text-gray-900"><?php echo $land_data['land_area_sqm'] ?? '0'; ?> sqm</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Valuation Details Grid -->
                                    <div class="mb-6">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Market Value</div>
                                                <div class="text-base font-bold text-blue-600"><?php echo formatCurrency($land_data['land_market_value'] ?? 0); ?></div>
                                            </div>
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Assessed Value</div>
                                                <div class="text-base font-bold text-green-600"><?php echo formatCurrency($land_data['land_assessed_value'] ?? 0); ?></div>
                                            </div>
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Assessment Level</div>
                                                <div class="text-base font-bold text-purple-600"><?php echo $land_data['assessment_level'] ?? '0'; ?>%</div>
                                            </div>
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Total Assessment</div>
                                                <div class="text-base font-bold text-gray-900"><?php echo formatCurrency($land_data['total_assessed_value'] ?? 0); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tax Breakdown -->
                                    <div class="border-t pt-4">
                                        <div class="text-sm font-medium text-gray-700 mb-3">Tax Breakdown</div>
                                        <div class="grid grid-cols-3 gap-3">
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500">Basic Tax</div>
                                                <div class="text-sm font-bold text-gray-900"><?php echo formatCurrency($land_data['basic_tax_amount'] ?? 0); ?></div>
                                            </div>
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500">SEF Tax</div>
                                                <div class="text-sm font-bold text-gray-900"><?php echo formatCurrency($land_data['sef_tax_amount'] ?? 0); ?></div>
                                            </div>
                                            <div class="bg-blue-100 p-3 rounded-lg border border-blue-200">
                                                <div class="text-xs font-medium text-blue-600">Annual Land Tax</div>
                                                <div class="text-base font-bold text-blue-600"><?php echo formatCurrency($total_land_tax); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Building Assessment -->
                                <div class="assessment-section building-assessment-card">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <h4 class="text-base font-semibold text-gray-900 flex items-center">
                                                <i class="fas fa-building text-green-600 mr-2"></i>Building Assessment
                                            </h4>
                                        </div>
                                        <?php if (!empty($building_data) && isset($building_data[0]['tdn'])): ?>
                                            <div class="text-right">
                                                <div class="text-xs font-medium text-green-600">
                                                    <?php echo $building_data[0]['tdn']; ?>
                                                </div>
                                                <?php if (isset($building_data[0]['year_built'])): ?>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        Built: <?php echo $building_data[0]['year_built']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs font-medium text-gray-500">
                                                No Building
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($building_data) && $app['has_building'] == 'yes'): ?>
                                        <!-- Property Details Grid -->
                                        <div class="mb-6">
                                            <div class="grid grid-cols-2 gap-3 mb-4">
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500 mb-1">Construction Type</div>
                                                    <div class="text-base font-bold text-gray-900"><?php echo $building_data[0]['material_type'] ?? 'N/A'; ?></div>
                                                </div>
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500 mb-1">Floor Area</div>
                                                    <div class="text-base font-bold text-gray-900"><?php echo $building_data[0]['floor_area_sqm'] ?? '0'; ?> sqm</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Calculate building totals -->
                                        <?php
                                            $building_total_floor_area = 0;
                                            $building_total_market_value = 0;
                                            $building_total_assessed_value = 0;
                                            $building_total_assessment_level = 0;
                                            
                                            foreach ($building_data as $building) {
                                                $building_total_floor_area += $building['floor_area_sqm'] ?? 0;
                                                $building_total_market_value += $building['building_market_value'] ?? 0;
                                                $building_total_assessed_value += $building['building_assessed_value'] ?? 0;
                                                $building_total_assessment_level += $building['assessment_level'] ?? 0;
                                            }
                                            
                                            $avg_assessment_level = count($building_data) > 0 ? $building_total_assessment_level / count($building_data) : 0;
                                        ?>

                                        <!-- Valuation Details Grid -->
                                        <div class="mb-6">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500 mb-1">Market Value</div>
                                                    <div class="text-base font-bold text-blue-600"><?php echo formatCurrency($building_total_market_value); ?></div>
                                                </div>
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500 mb-1">Assessed Value</div>
                                                    <div class="text-base font-bold text-green-600"><?php echo formatCurrency($building_total_assessed_value); ?></div>
                                                </div>
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500 mb-1">Avg. Assessment</div>
                                                    <div class="text-base font-bold text-purple-600"><?php echo number_format($avg_assessment_level, 1); ?>%</div>
                                                </div>
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500 mb-1">Total Assessment</div>
                                                    <div class="text-base font-bold text-gray-900"><?php echo formatCurrency($building_total_assessed_value); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tax Breakdown -->
                                        <div class="border-t pt-4">
                                            <div class="text-sm font-medium text-gray-700 mb-3">Tax Breakdown</div>
                                            <div class="grid grid-cols-3 gap-3">
                                                <?php
                                                    $building_total_basic_tax = 0;
                                                    $building_total_sef_tax = 0;
                                                    
                                                    foreach ($building_data as $building) {
                                                        $building_total_basic_tax += $building['basic_tax_amount'] ?? 0;
                                                        $building_total_sef_tax += $building['sef_tax_amount'] ?? 0;
                                                    }
                                                ?>
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500">Total Basic Tax</div>
                                                    <div class="text-sm font-bold text-gray-900"><?php echo formatCurrency($building_total_basic_tax); ?></div>
                                                </div>
                                                <div class="assessment-value-item">
                                                    <div class="text-xs font-medium text-gray-500">Total SEF Tax</div>
                                                    <div class="text-sm font-bold text-gray-900"><?php echo formatCurrency($building_total_sef_tax); ?></div>
                                                </div>
                                                <div class="bg-green-100 p-3 rounded-lg border border-green-200">
                                                    <div class="text-xs font-medium text-green-600">Total Building Tax</div>
                                                    <div class="text-base font-bold text-green-600"><?php echo formatCurrency($total_building_tax); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-8">
                                            <div class="text-gray-500 italic mb-4">
                                                <i class="fas fa-building text-gray-300 text-4xl"></i>
                                            </div>
                                            <div class="text-sm text-gray-600">No building on this property</div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Tax Summary -->
                                <div class="assessment-section tax-summary-card">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-base font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-file-invoice-dollar text-purple-600 mr-2"></i>Tax Summary
                                        </h4>
                                        <span class="text-xs font-medium text-purple-600">
                                            Annual Tax
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Land Tax</div>
                                                <div class="text-base font-bold text-blue-600"><?php echo formatCurrency($total_land_tax); ?></div>
                                            </div>
                                            <div class="assessment-value-item">
                                                <div class="text-xs font-medium text-gray-500 mb-1">Building Tax</div>
                                                <div class="text-base font-bold text-green-600"><?php echo formatCurrency($total_building_tax); ?></div>
                                            </div>
                                        </div>

                                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 mt-4">
                                            <div class="text-sm font-medium text-gray-700 mb-2">Total Annual Tax</div>
                                            <div class="text-2xl font-bold text-blue-700"><?php echo formatCurrency($total_annual_tax); ?></div>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <div class="flex justify-between items-center">
                                                <div class="text-sm text-gray-600">Quarterly Installment:</div>
                                                <div class="text-sm font-bold text-gray-900"><?php echo formatCurrency($total_annual_tax / 4); ?></div>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <div class="text-sm text-gray-600">Paid Quarters:</div>
                                                <div class="text-sm font-bold <?php echo $paid_count == count($quarterly_taxes) ? 'text-green-600' : 'text-yellow-600'; ?>">
                                                    <?php echo $paid_count; ?>/<?php echo count($quarterly_taxes); ?>
                                                </div>
                                            </div>
                                            <?php if ($eligible_for_discount): ?>
                                                <div class="bg-green-50 p-3 rounded-lg border border-green-200 mt-3">
                                                    <div class="flex justify-between items-center">
                                                        <div class="text-sm text-green-700">
                                                            <i class="fas fa-gift mr-1"></i> Annual Discount:
                                                        </div>
                                                        <div class="text-sm font-bold text-green-700"><?php echo $discount_percent; ?>%</div>
                                                    </div>
                                                    <div class="text-xs text-green-600 mt-1">Available until January 31</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quarterly Taxes Table -->
                            <?php if (!empty($quarterly_taxes)): ?>
                                <div class="mt-8">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4">
                                        <i class="fas fa-calendar-alt mr-2"></i>Quarterly Tax Payments
                                    </h4>
                                    
                                    <div class="overflow-x-auto">
                                        <table class="tax-table">
                                            <thead>
                                                <tr>
                                                    <th>Quarter</th>
                                                    <th>Due Date</th>
                                                    <th>Tax Amount</th>
                                                    <th>Penalty</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($quarterly_taxes as $tax): 
                                                    $days_late = $tax['days_late'] ?? 0;
                                                    $penalty_amount = $tax['penalty_amount'] ?? 0;
                                                    $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                                                    $total_due = $tax_amount + $penalty_amount;
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <div class="font-medium"><?php echo $tax['quarter']; ?></div>
                                                            <div class="text-xs text-gray-500"><?php echo $tax['year']; ?></div>
                                                        </td>
                                                        <td>
                                                            <div><?php echo date('M d, Y', strtotime($tax['due_date'])); ?></div>
                                                            <?php if ($days_late > 0): ?>
                                                                <div class="text-xs text-red-600">
                                                                    <?php echo $days_late; ?> days late
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="font-medium"><?php echo formatCurrency($tax_amount); ?></td>
                                                        <td>
                                                            <?php if ($penalty_amount > 0): ?>
                                                                <span class="text-red-600 font-medium">
                                                                    <?php echo formatCurrency($penalty_amount); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-green-600">₱0.00</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="font-bold"><?php echo formatCurrency($total_due); ?></td>
                                                        <td>
                                                            <?php if ($tax['payment_status'] == 'paid'): ?>
                                                                <span class="tax-status-badge bg-green-100 text-green-800">
                                                                    <i class="fas fa-check-circle mr-1"></i> Paid
                                                                </span>
                                                            <?php elseif ($tax['payment_status'] == 'overdue'): ?>
                                                                <span class="tax-status-badge bg-red-100 text-red-800">
                                                                    <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="tax-status-badge bg-yellow-100 text-yellow-800">
                                                                    <i class="fas fa-clock mr-1"></i> Pending
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="bg-gray-50">
                                                <tr>
                                                    <td colspan="2" class="font-bold text-right">Totals:</td>
                                                    <td class="font-bold"><?php echo formatCurrency($total_annual_tax); ?></td>
                                                    <td class="font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : ''; ?>">
                                                        <?php echo formatCurrency($total_penalty); ?>
                                                    </td>
                                                    <td class="font-bold text-lg text-blue-700"><?php echo formatCurrency($grand_total); ?></td>
                                                    <td>
                                                        <?php if ($overdue_count > 0): ?>
                                                            <span class="tax-status-badge bg-red-100 text-red-800">
                                                                <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                            </span>
                                                        <?php elseif ($paid_count == count($quarterly_taxes)): ?>
                                                            <span class="tax-status-badge bg-green-100 text-green-800">
                                                                <i class="fas fa-check-circle mr-1"></i> Fully Paid
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="tax-status-badge bg-yellow-100 text-yellow-800">
                                                                <i class="fas fa-clock mr-1"></i> Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-6 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="text-sm font-medium text-gray-900 mb-1">Tax Assessment Status</div>
                                <div class="text-sm text-gray-600">
                                    <?php if (!empty($tax_clearances)): ?>
                                        <span class="text-green-700 font-medium">
                                            <i class="fas fa-file-certificate mr-1"></i> Tax clearance certificate(s) available for download
                                        </span>
                                    <?php elseif ($paid_count == count($quarterly_taxes) && !empty($quarterly_taxes)): ?>
                                        <span class="text-green-700">
                                            <i class="fas fa-check-circle mr-1"></i> All taxes paid! Tax clearance will be generated automatically.
                                        </span>
                                    <?php elseif ($overdue_count > 0): ?>
                                        <span class="text-red-700 font-medium">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> 
                                            <?php echo $overdue_count; ?> overdue payment<?php echo $overdue_count > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php elseif ($eligible_for_discount): ?>
                                        <span class="text-green-700 font-medium">
                                            <i class="fas fa-gift mr-1"></i> Annual discount available until January 31
                                        </span>
                                    <?php else: ?>
                                        Your property assessment has been approved. Quarterly tax payments are now due.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500">
                                TDN: <span class="font-mono font-medium text-gray-700"><?php echo $land_data['tdn'] ?? 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="bg-white border-t border-gray-200 mt-16">
    <div class="container mx-auto px-6 py-12 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Brand -->
            <div class="col-span-1">
                <div class="flex items-center mb-4 text-2xl font-bold">
                    <span style="color: #4a90e2;">Go</span><span style="color: #4caf50;">Serve</span><span style="color: #4a90e2;">PH</span>
                </div>
                <p class="text-gray-600 leading-relaxed">
                    The official digital gateway of your Local Government Unit, providing efficient and transparent government services.
                </p>
            </div>
            
            <!-- Portal Links -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Portal</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Services</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> support@goserveph.gov.ph</li>
                    <li><i class="fas fa-clock mr-2 text-gray-400"></i> Mon-Fri: 8AM - 5PM</li>
                </ul>
            </div>

            <!-- Social -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Connect</h4>
                <div class="flex space-x-4 text-2xl">
                    <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="border-t border-gray-200 mt-10 pt-8">
            <p class="text-sm text-gray-500 text-center">
                &copy; 2026 GoServePH Local Government Unit. Republic of the Philippines.
            </p>
        </div>
    </div>
</footer>

</body>
</html>