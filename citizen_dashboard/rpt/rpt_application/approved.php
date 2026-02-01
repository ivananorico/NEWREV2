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
    
    if (!empty($owner['barangay'])) {
        $address_parts[] = 'Brgy. ' . $owner['barangay'];
    }
    
    if (!empty($owner['district'])) {
        $address_parts[] = 'Dist. ' . $owner['district'];
    }
    
    if (!empty($owner['city'])) {
        $address_parts[] = $owner['city'];
    }
    
    if (!empty($owner['province'])) {
        $address_parts[] = $owner['province'];
    }
    
    if (!empty($owner['zip_code'])) {
        $address_parts[] = $owner['zip_code'];
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
            pr.*,
            po.* 
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
$base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url .= $_SERVER['HTTP_HOST'];
$bg_image_url = $base_url . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approved Applications - RPT Services | GoServePH</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #4a90e2;
    --secondary: #9aa5b1;
    --accent: #4caf50;
    --warning: #f59e0b;
    --danger: #ef4444;
}

body {
    background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
    position: relative;
    overflow-x: hidden;
    min-height: 100vh;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    display: flex;
    flex-direction: column;
}

/* Background image with blur */
body::after {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('<?php echo $bg_image_url; ?>') center/cover no-repeat;
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
        radial-gradient(circle at 20% 80%, rgba(76, 175, 80, 0.1) 0%, transparent 50%),
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

/* Glass effect header */
.glass-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border-radius: 16px;
}

/* Form card styling */
.form-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}

/* Status badge */
.status-badge {
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.status-approved {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.status-pending {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.status-paid {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
}

.status-overdue {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.status-rejected {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

/* Progress bar */
.progress-container {
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Section header */
.section-header {
    border-left: 4px solid var(--primary);
    padding-left: 1rem;
    margin-bottom: 1.5rem;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    padding: 20px;
}

.modal-content {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 85vh;
    object-fit: contain;
    border-radius: 8px;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 1001;
}

.modal-close:hover {
    color: #ddd;
}

.modal-caption {
    margin: auto;
    display: block;
    width: 80%;
    text-align: center;
    color: white;
    padding: 10px 0;
    font-size: 0.9rem;
}

/* Back button */
.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s;
    background: rgba(74, 144, 226, 0.1);
}

.back-button:hover {
    background: rgba(74, 144, 226, 0.2);
    transform: translateX(-2px);
}

/* Timeline */
.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
}

.timeline-connector {
    position: absolute;
    top: 20px;
    left: 50%;
    right: -50%;
    height: 2px;
    background: #e5e7eb;
    z-index: 0;
}

.timeline-step.active .timeline-connector {
    background: #3b82f6;
}

/* Info box for property type highlighting */
.info-box-highlight {
    background: #f0f9ff;
    border: 2px solid #0ea5e9;
    padding: 0.875rem;
    border-radius: 8px;
}

/* Tax table styling */
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

/* Assessment grid */
.assessment-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.assessment-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    background: white;
}

.assessment-card.land {
    border-left: 4px solid #3b82f6;
}

.assessment-card.building {
    border-left: 4px solid #10b981;
}

.assessment-card.summary {
    border-left: 4px solid #8b5cf6;
}

.assessment-value {
    font-size: 1.25rem;
    font-weight: bold;
    margin-top: 0.5rem;
}

/* Tax clearance card */
.clearance-card {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.clearance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
}

/* Billing button */
.billing-btn {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
    text-decoration: none;
}

.billing-btn:hover {
    background: linear-gradient(135deg, #2563eb, #1e40af);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

@media (max-width: 768px) {
    .modal-content {
        max-width: 95%;
        max-height: 80vh;
    }
    
    .modal-close {
        right: 20px;
        font-size: 30px;
    }
    
    .assessment-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body class="bg-gray-50">
<?php include '../../navbar.php'; ?>

<!-- Main Content Wrapper -->
<div class="flex-1 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Page Header -->
        <div class="glass-header p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                <a href="../rpt_services.php" class="back-button self-start">
                    <i class="fas fa-arrow-left"></i> Back to RPT Services
                </a>
                
                <div class="text-center">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Approved Applications</h1>
                    <p class="text-gray-600">Successfully approved property tax assessments</p>
                </div>
                
                <div class="text-right">
                    <?php if ($total_applications > 0): ?>
                    <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-700 rounded-lg text-sm font-medium">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $total_applications; ?> Approved
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($total_applications > 0): ?>
            <div class="mt-4 p-4 bg-green-50 rounded-lg border border-green-100">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-green-500 mt-0.5 mr-3"></i>
                    <div>
                        <p class="text-sm text-green-700">
                            Your applications have been successfully approved! Property tax assessments are now complete. You can view tax details, proceed with payments, and download tax clearance certificates.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Error Message -->
        <?php if (isset($error_message)): ?>
            <div class="form-card mb-6 p-4 bg-red-50 border border-red-200">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-red-600 mr-3 mt-0.5"></i>
                    <div class="text-sm text-red-700"><?php echo $error_message; ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($total_applications === 0): ?>
            <!-- Empty State -->
            <div class="form-card p-12 text-center">
                <div class="text-gray-300 mb-6">
                    <i class="fas fa-check-circle text-6xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Approved Applications</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">You don't have any approved applications yet. Check your pending or assessed applications for status updates.</p>
                <div class="space-x-4">
                    <a href="assessed.php" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors">
                        <i class="fas fa-chart-line mr-2"></i> View Under Assessment
                    </a>
                    <a href="pending.php" 
                       class="inline-flex items-center px-6 py-3 bg-amber-500 text-white rounded-lg hover:bg-amber-600 font-medium transition-colors">
                        <i class="fas fa-clock mr-2"></i> View Pending
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Applications List -->
            <div class="space-y-8">
                <?php foreach ($applications as $app): ?>
                    <?php
                        $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
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
                        
                        // Check if current year quarters are all paid
                        $current_year = date('Y');
                        $current_year_all_paid = isAllQuartersPaidForYear($quarterly_taxes, $current_year);
                    ?>

                    <!-- Application Card -->
                    <div class="form-card overflow-hidden">
                        <!-- Card Header -->
                        <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-bold text-gray-900 text-lg"><?php echo $app['reference_number']; ?></span>
                                        <span class="status-badge status-approved">
                                            <i class="fas fa-check-circle"></i> Approved
                                        </span>
                                        <?php if ($overdue_count > 0): ?>
                                            <span class="status-badge status-overdue">
                                                <i class="fas fa-exclamation-triangle"></i> Overdue
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-gray-600 text-sm">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <div class="text-gray-600 text-sm">
                                        <i class="far fa-calendar mr-1"></i>
                                        Approved: <?php echo date('F j, Y', strtotime($app['updated_at'])); ?>
                                    </div>
                                    <?php if ($eligible_for_discount): ?>
                                        <div class="mt-2 text-xs text-green-600 font-medium">
                                            <i class="fas fa-gift mr-1"></i> Annual discount available
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Billing Section -->
                        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50/30">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="text-sm text-gray-500 mb-1">TDN</div>
                                    <div class="font-mono font-semibold text-gray-900"><?php echo $land_data['tdn'] ?? 'N/A'; ?></div>
                                </div>
                                
                                <div>
                                    <?php if (!$current_year_all_paid): ?>
                                        <a href="view_rpt_bill.php?registration_id=<?php echo $app['id']; ?>" 
                                           target="_blank"
                                           class="billing-btn inline-flex">
                                            <i class="fas fa-file-invoice mr-2"></i>
                                            View Billing Statement
                                            <?php if ($paid_count > 0): ?>
                                                <span class="ml-2 text-xs bg-white/20 text-white px-2 py-1 rounded-full">
                                                    <?php echo $paid_count; ?>/<?php echo count($quarterly_taxes); ?> paid
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-700 rounded-lg text-sm">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            All taxes paid for <?php echo $current_year; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Tax Clearance Section -->
                        <?php if (!empty($tax_clearances)): ?>
                        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50/30">
                            <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-file-certificate text-green-600 mr-2"></i>Available Tax Clearance Certificates
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($tax_clearances as $year => $clearance): 
                                    $valid_until = $clearance['valid_until'] ?? date('Y-12-31', strtotime("+1 year"));
                                    $is_valid = strtotime($valid_until) >= time();
                                ?>
                                    <div class="clearance-card">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-award text-green-600"></i>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-gray-900"><?php echo $year; ?> Clearance</div>
                                                    <div class="text-xs text-gray-500 font-mono"><?php echo $clearance['certificate_number']; ?></div>
                                                </div>
                                            </div>
                                            <?php if ($is_valid): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1"></i>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-exclamation mr-1"></i>Expired
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="text-sm text-gray-600 mb-4">
                                            <div class="flex justify-between mb-1">
                                                <span>Issued:</span>
                                                <span class="font-medium"><?php echo date('M j, Y', strtotime($clearance['issue_date'])); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Valid Until:</span>
                                                <span class="font-medium"><?php echo date('M j, Y', strtotime($valid_until)); ?></span>
                                            </div>
                                        </div>
                                        
                                        <a href="tax_clearance_certificate.php?property_total_id=<?php echo $total_tax_data['id']; ?>&year=<?php echo $year; ?>" 
                                           target="_blank"
                                           class="inline-flex items-center px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium transition-colors w-full justify-center">
                                            <i class="fas fa-download mr-2"></i> Download Certificate
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Progress Section -->
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                            <div class="flex items-center justify-between mb-3">
                                <div class="text-sm font-medium text-gray-900">Application Progress</div>
                                <div class="text-sm font-semibold text-green-600">Complete</div>
                            </div>
                            <div class="progress-container mb-2">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Pending</span>
                                <span>Inspection</span>
                                <span>Assessment</span>
                                <span class="font-medium text-green-600">Approved</span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-6">
                            <!-- Two Column Layout -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                                <!-- Applicant Information -->
                                <div>
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                                            Applicant Information
                                        </h3>
                                    </div>
                                    
                                    <div class="space-y-5">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Full Name</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $full_name; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Contact Number</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['phone']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Email Address</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['email']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Birthdate</div>
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?php echo isset($app['birthdate']) ? date('F j, Y', strtotime($app['birthdate'])) : 'Not provided'; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Gender</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo ucfirst($app['sex'] ?? 'Not provided'); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Marital Status</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo ucfirst($app['marital_status'] ?? 'Not provided'); ?></div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($app['tin_number'])): ?>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1 font-medium">Tax Identification Number</div>
                                            <div class="text-sm font-semibold text-gray-900"><?php echo $app['tin_number']; ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1 font-medium">Address</div>
                                            <div class="text-sm text-gray-700">
                                                <?php echo $owner_address; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Property Information -->
                                <div>
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-home mr-3 text-green-500"></i>
                                            Property Information
                                        </h3>
                                    </div>
                                    
                                    <div class="space-y-5">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Location</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['lot_location']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Barangay</div>
                                                <div class="text-sm font-semibold text-gray-900">Brgy. <?php echo $app['barangay']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">District</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['district']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">City</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['city']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Province</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['province']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">ZIP Code</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['zip_code']; ?></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Highlighted Property Type Box -->
                                        <div class="info-box-highlight">
                                            <div class="text-xs text-gray-500 mb-1 font-medium">Property Type</div>
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?php 
                                                if ($app['has_building'] == 'yes') {
                                                    echo '<span class="text-green-600"><i class="fas fa-building mr-1"></i> With Building</span>';
                                                } else {
                                                    echo '<span class="text-blue-600"><i class="fas fa-mountain mr-1"></i> Vacant Land</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Assessment Summary -->
                            <div class="mt-8 pt-8 border-t border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                                    <i class="fas fa-chart-bar text-blue-600 mr-2"></i>Assessment Summary
                                </h3>
                                
                                <div class="assessment-grid mb-8">
                                    <!-- Land Assessment -->
                                    <div class="assessment-card land">
                                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                                            <i class="fas fa-map text-blue-600 mr-2"></i>Land Assessment
                                        </h4>
                                        
                                        <div class="space-y-3">
                                            <div>
                                                <div class="text-xs text-gray-500">Property Type</div>
                                                <div class="font-medium text-gray-900"><?php echo $land_data['property_type'] ?? 'N/A'; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Land Area</div>
                                                <div class="font-medium text-gray-900"><?php echo $land_data['land_area_sqm'] ?? '0'; ?> sqm</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Market Value</div>
                                                <div class="assessment-value text-blue-600"><?php echo formatCurrency($land_data['land_market_value'] ?? 0); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Assessed Value</div>
                                                <div class="assessment-value text-green-600"><?php echo formatCurrency($land_data['land_assessed_value'] ?? 0); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Annual Land Tax</div>
                                                <div class="text-lg font-bold text-gray-900"><?php echo formatCurrency($total_land_tax); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Building Assessment -->
                                    <div class="assessment-card building">
                                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                                            <i class="fas fa-building text-green-600 mr-2"></i>Building Assessment
                                        </h4>
                                        
                                        <?php if (!empty($building_data) && $app['has_building'] == 'yes'): ?>
                                            <?php
                                                $building_total_floor_area = 0;
                                                $building_total_market_value = 0;
                                                $building_total_assessed_value = 0;
                                                
                                                foreach ($building_data as $building) {
                                                    $building_total_floor_area += $building['floor_area_sqm'] ?? 0;
                                                    $building_total_market_value += $building['building_market_value'] ?? 0;
                                                    $building_total_assessed_value += $building['building_assessed_value'] ?? 0;
                                                }
                                            ?>
                                            
                                            <div class="space-y-3">
                                                <div>
                                                    <div class="text-xs text-gray-500">Construction Type</div>
                                                    <div class="font-medium text-gray-900"><?php echo $building_data[0]['material_type'] ?? 'N/A'; ?></div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">Total Floor Area</div>
                                                    <div class="font-medium text-gray-900"><?php echo $building_total_floor_area; ?> sqm</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">Market Value</div>
                                                    <div class="assessment-value text-blue-600"><?php echo formatCurrency($building_total_market_value); ?></div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">Assessed Value</div>
                                                    <div class="assessment-value text-green-600"><?php echo formatCurrency($building_total_assessed_value); ?></div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">Total Building Tax</div>
                                                    <div class="text-lg font-bold text-gray-900"><?php echo formatCurrency($total_building_tax); ?></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <div class="text-gray-500 italic">
                                                    <i class="fas fa-building text-gray-300 text-3xl mb-2"></i>
                                                    <div class="text-sm text-gray-600">No building on this property</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Tax Summary -->
                                    <div class="assessment-card summary">
                                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                                            <i class="fas fa-file-invoice-dollar text-purple-600 mr-2"></i>Tax Summary
                                        </h4>
                                        
                                        <div class="space-y-3">
                                            <div>
                                                <div class="text-xs text-gray-500">Land Tax</div>
                                                <div class="font-medium text-blue-600"><?php echo formatCurrency($total_land_tax); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Building Tax</div>
                                                <div class="font-medium text-green-600"><?php echo formatCurrency($total_building_tax); ?></div>
                                            </div>
                                            <div class="pt-3 border-t">
                                                <div class="text-xs text-gray-500">Total Annual Tax</div>
                                                <div class="text-xl font-bold text-gray-900"><?php echo formatCurrency($total_annual_tax); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Quarterly Installment</div>
                                                <div class="font-medium text-gray-900"><?php echo formatCurrency($total_annual_tax / 4); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Paid Quarters</div>
                                                <div class="font-medium <?php echo $paid_count == count($quarterly_taxes) ? 'text-green-600' : 'text-amber-600'; ?>">
                                                    <?php echo $paid_count; ?>/<?php echo count($quarterly_taxes); ?>
                                                </div>
                                            </div>
                                            <?php if ($eligible_for_discount): ?>
                                                <div class="mt-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                                    <div class="flex justify-between items-center">
                                                        <div class="text-sm text-green-700">
                                                            <i class="fas fa-gift mr-1"></i> Annual Discount
                                                        </div>
                                                        <div class="text-sm font-bold text-green-700"><?php echo $discount_percent; ?>%</div>
                                                    </div>
                                                    <div class="text-xs text-green-600 mt-1">Available until January 31</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quarterly Taxes Table -->
                                <?php if (!empty($quarterly_taxes)): ?>
                                    <div class="mt-8">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
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
                                                                    <span class="status-badge status-paid" style="padding: 0.25rem 0.75rem;">
                                                                        <i class="fas fa-check-circle mr-1"></i> Paid
                                                                    </span>
                                                                <?php elseif ($tax['payment_status'] == 'overdue'): ?>
                                                                    <span class="status-badge status-overdue" style="padding: 0.25rem 0.75rem;">
                                                                        <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="status-badge status-pending" style="padding: 0.25rem 0.75rem;">
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
                                                                <span class="status-badge status-overdue" style="padding: 0.25rem 0.75rem;">
                                                                    <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                                </span>
                                                            <?php elseif ($paid_count == count($quarterly_taxes)): ?>
                                                                <span class="status-badge status-paid" style="padding: 0.25rem 0.75rem;">
                                                                    <i class="fas fa-check-circle mr-1"></i> Fully Paid
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="status-badge status-pending" style="padding: 0.25rem 0.75rem;">
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

                            <!-- Next Steps -->
                            <div class="mt-8 p-4 bg-blue-50 border border-blue-100 rounded-lg">
                                <div class="flex items-start">
                                    <div class="mr-3 text-blue-500">
                                        <i class="fas fa-file-invoice-dollar text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 mb-1">Next Steps</div>
                                        <div class="text-sm text-gray-700">
                                            • View your tax assessment details<br>
                                            • Proceed with quarterly tax payments<br>
                                            • Download tax clearance certificates (if eligible)<br>
                                            • Keep payment records for future reference
                                        </div>
                                    </div>
                                </div>
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
    </div>
</div>

<!-- Footer -->
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
                    <li><a href="../../citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                    <li><a href="../rpt_services.php" class="hover:text-[#4a90e2] transition-colors">RPT Services</a></li>
                    <li><a href="application_history.php" class="hover:text-[#4a90e2] transition-colors">Application History</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li class="flex items-start">
                        <i class="fas fa-phone mr-2 text-gray-400 mt-0.5"></i>
                        <span>(02) 8123-4567</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-envelope mr-2 text-gray-400 mt-0.5"></i>
                        <span>rpt@goserveph.gov.ph</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-clock mr-2 text-gray-400 mt-0.5"></i>
                        <span>Mon-Fri: 8AM - 5PM</span>
                    </li>
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
                    <a href="#" class="text-gray-400 hover:text-red-600 transition-colors">
                        <i class="fab fa-youtube"></i>
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

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="modal-caption"></div>
</div>

<script>
// Modal functionality
const modal = document.getElementById("imageModal");
const modalImg = document.getElementById("modalImage");
const captionText = document.getElementById("modalCaption");

function openModal(imageSrc, caption) {
    if (imageSrc) {
        modal.style.display = "block";
        modalImg.src = imageSrc;
        captionText.innerHTML = caption;
    } else {
        alert("No document available to view.");
    }
}

// Close modal when clicking X
document.getElementsByClassName("modal-close")[0].onclick = function() {
    modal.style.display = "none";
}

// Close modal when clicking outside image
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Close modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && modal.style.display === 'block') {
        modal.style.display = 'none';
    }
});
</script>
</body>
</html>