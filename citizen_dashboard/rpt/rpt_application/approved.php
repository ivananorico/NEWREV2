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
            po.tin_number,
            po.house_number,
            po.street,
            po.barangay as owner_barangay,
            po.district as owner_district,
            po.city as owner_city,
            po.province as owner_province,
            po.zip_code as owner_zip_code
            
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
        .status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .status-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .status-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
        .info-card-header { display: flex; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid #f3f4f6; }
        .icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; }
        .info-label { font-size: 0.875rem; color: #6b7280; font-weight: 500; margin-bottom: 0.25rem; }
        .info-value { font-size: 1rem; color: #111827; font-weight: 500; }
        .empty-state { text-align: center; padding: 3rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .empty-icon { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1.5rem; }
        .progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin: 0.75rem 0; }
        .progress-fill { height: 100%; background: #3b82f6; border-radius: 3px; }
        .document-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; text-align: center; transition: all 0.2s; background: white; }
        .document-card:hover { border-color: #3b82f6; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .value-card { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 12px; padding: 1.5rem; text-align: center; }
        .tax-card { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; border-radius: 12px; padding: 1.5rem; }
        .quarter-card { border: 2px solid #10b981; border-radius: 12px; padding: 1.5rem; }
        .quarter-card-overdue { border: 2px solid #ef4444; background-color: #fef2f2; }
        .quarter-card-paid { border: 2px solid #10b981; background-color: #f0fdf4; }
        .discount-banner { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center mb-3">
            <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Approved Applications</h1>
                <p class="text-gray-600">Applications with completed assessment and approval</p>
            </div>
        </div>

        <!-- Status Summary -->
        <?php if ($total_applications > 0): ?>
            <div class="mt-6 flex items-center">
                <div class="mr-4">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                    <div class="text-sm text-gray-500">Approved Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                </div>
                <div class="h-8 w-px bg-gray-300"></div>
                <div class="ml-4">
                    <div class="status-badge status-approved"><i class="fas fa-check-circle mr-2"></i>Approved</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Approved Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any applications that have been approved yet.</p>
            <div class="space-x-4">
                <a href="pending.php" class="inline-flex items-center px-5 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    <i class="fas fa-clock mr-2"></i>Check Pending Applications
                </a>
                <a href="assessed.php" class="inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-chart-bar mr-2"></i>Check Assessed Applications
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
                    // Get land assessment data
                    $land_data = [];
                    try {
                        $land_stmt = $pdo->prepare("
                            SELECT lp.*, lc.classification, lc.market_value as unit_market_value
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
                                SELECT bp.*, pc.classification, pc.material_type, pc.unit_cost
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
                    if (!empty($total_tax_data)) {
                        try {
                            $quarterly_stmt = $pdo->prepare("
                                SELECT * FROM quarterly_taxes 
                                WHERE property_total_id = ?
                                ORDER BY FIELD(quarter, 'Q1', 'Q2', 'Q3', 'Q4'), year
                            ");
                            $quarterly_stmt->execute([$total_tax_data['id']]);
                            $quarterly_taxes = $quarterly_stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch(PDOException $e) {}
                    }
                    
                    // Calculate totals
                    $total_land_value = $land_data['land_market_value'] ?? 0;
                    $total_land_tax = $land_data['annual_tax'] ?? 0;
                    $total_building_value = 0;
                    $total_building_tax = 0;
                    $total_penalty = 0;
                    $total_discount = 0;
                    
                    foreach ($building_data as $building) {
                        $total_building_value += $building['building_market_value'] ?? 0;
                        $total_building_tax += $building['annual_tax'] ?? 0;
                    }
                    
                    foreach ($quarterly_taxes as $quarter) {
                        $total_penalty += $quarter['penalty_amount'] ?? 0;
                        $total_discount += $quarter['discount_amount'] ?? 0;
                    }
                    
                    $total_property_value = $total_land_value + $total_building_value;
                    $total_annual_tax = $total_land_tax + $total_building_tax;
                    $grand_total = $total_annual_tax + $total_penalty - $total_discount;
                    
                    // Check discount eligibility (Jan 1-31 only)
                    $eligible_for_discount = isEligibleForAnnualDiscount($quarterly_taxes);
                    $discount_percent = getDiscountPercentage($pdo);
                    $discount_amount = $eligible_for_discount ? ($total_annual_tax * ($discount_percent / 100)) : 0;
                    $discounted_total = $eligible_for_discount ? ($total_annual_tax - $discount_amount) : $grand_total;

                    $documents = [];
                    try {
                        $doc_stmt = $pdo->prepare("SELECT document_type, file_name, file_path FROM property_documents WHERE registration_id = ?");
                        $doc_stmt->execute([$app['id']]);
                        $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {}

                    $doc_labels = [
                        'barangay_certificate' => 'Barangay Certificate',
                        'ownership_proof' => 'Proof of Ownership',
                        'valid_id' => 'Valid ID',
                        'survey_plan' => 'Survey Plan'
                    ];

                    $docs_by_type = [];
                    foreach ($documents as $doc) $docs_by_type[$doc['document_type']] = $doc;
                ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Approval Status Card -->
                    <div class="value-card">
                        <div class="flex items-center justify-center mb-4">
                            <i class="fas fa-award text-3xl mr-3"></i>
                            <div>
                                <div class="text-lg font-bold">Application Approved</div>
                                <div class="text-sm opacity-90">Ready for Tax Payment</div>
                            </div>
                        </div>
                        <div class="text-sm opacity-90 mb-4">
                            Your property has been successfully assessed and approved. You may now proceed with tax payments.
                        </div>
                        <div class="text-lg font-bold">
                            Approved on <?php echo date('F j, Y', strtotime($app['approval_date'])); ?>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['reference_number']; ?></span>
                                <span class="status-badge status-approved">
                                    <i class="fas fa-check-circle mr-1"></i>Approved
                                </span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                <span><?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Approved On</div>
                            <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($app['approval_date'])); ?></div>
                        </div>
                    </div>

                    <!-- PROGRESS BAR - Step 4 of 4 (100%) -->
                    <div class="px-6 py-4 bg-blue-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-blue-800">Application Progress</div>
                            <div class="text-sm text-blue-700">Step 4 of 4</div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-blue-600 mt-1">
                            <span>Pending</span>
                            <span>For Inspection</span>
                            <span>Assessed</span>
                            <span class="font-bold">Approved</span>
                        </div>
                    </div>

                    <!-- Property Assessment Summary -->
                    <?php if (!empty($land_data)): ?>
                        <div class="tax-card m-6">
                            <div class="text-lg font-bold mb-6 text-center">Property Assessment Summary</div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Land Assessment -->
                                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4">
                                    <div class="text-center mb-3">
                                        <i class="fas fa-map text-2xl mb-2"></i>
                                        <div class="font-medium">Land Assessment</div>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span>Area:</span>
                                            <span class="font-medium"><?php echo $land_data['land_area_sqm']; ?> sqm</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span>Type:</span>
                                            <span class="font-medium"><?php echo $land_data['property_type']; ?></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span>Market Value:</span>
                                            <span class="font-medium"><?php echo formatCurrency($land_data['land_market_value']); ?></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span>Assessed Value:</span>
                                            <span class="font-medium"><?php echo formatCurrency($land_data['land_assessed_value']); ?></span>
                                        </div>
                                        <div class="flex justify-between text-sm font-bold border-t border-white/30 pt-2">
                                            <span>Annual Tax:</span>
                                            <span class="text-yellow-300"><?php echo formatCurrency($land_data['annual_tax']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Building Assessment -->
                                <?php if (!empty($building_data) && $app['has_building'] == 'yes'): ?>
                                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-building text-2xl mb-2"></i>
                                            <div class="font-medium">Building Assessment</div>
                                        </div>
                                        <?php foreach ($building_data as $index => $building): ?>
                                            <div class="mb-3 last:mb-0">
                                                <div class="font-medium text-sm mb-2">Building <?php echo $index + 1; ?></div>
                                                <div class="space-y-2">
                                                    <div class="flex justify-between text-sm">
                                                        <span>Floor Area:</span>
                                                        <span class="font-medium"><?php echo $building['floor_area_sqm']; ?> sqm</span>
                                                    </div>
                                                    <div class="flex justify-between text-sm">
                                                        <span>Type:</span>
                                                        <span class="font-medium"><?php echo $building['material_type']; ?></span>
                                                    </div>
                                                    <div class="flex justify-between text-sm">
                                                        <span>Market Value:</span>
                                                        <span class="font-medium"><?php echo formatCurrency($building['building_market_value']); ?></span>
                                                    </div>
                                                    <div class="flex justify-between text-sm">
                                                        <span>Assessed Value:</span>
                                                        <span class="font-medium"><?php echo formatCurrency($building['building_assessed_value']); ?></span>
                                                    </div>
                                                    <div class="flex justify-between text-sm">
                                                        <span>Annual Tax:</span>
                                                        <span class="font-medium"><?php echo formatCurrency($building['annual_tax']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-building text-2xl mb-2"></i>
                                            <div class="font-medium">Building Assessment</div>
                                        </div>
                                        <div class="text-center py-4">
                                            <div class="text-sm opacity-90">No building on this property</div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Total Tax Summary -->
                                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4">
                                    <div class="text-center mb-3">
                                        <i class="fas fa-file-invoice-dollar text-2xl mb-2"></i>
                                        <div class="font-medium">Tax Summary</div>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="flex justify-between text-sm">
                                            <span>Total Land Tax:</span>
                                            <span class="font-medium"><?php echo formatCurrency($total_land_tax); ?></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span>Total Building Tax:</span>
                                            <span class="font-medium"><?php echo formatCurrency($total_building_tax); ?></span>
                                        </div>
                                        <div class="flex justify-between text-lg font-bold border-t border-white/30 pt-3">
                                            <span>Total Annual Tax:</span>
                                            <span class="text-yellow-300 text-xl"><?php echo formatCurrency($total_annual_tax); ?></span>
                                        </div>
                                        <?php if (!empty($total_tax_data)): ?>
                                            <div class="text-xs opacity-80 text-center mt-2">
                                                Tax Declaration No: <?php echo $land_data['tdn'] ?? 'N/A'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quarterly Tax Breakdown -->
                    <?php if (!empty($quarterly_taxes)): ?>
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Quarterly Tax Payments (2025)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <?php 
                                $overdue_count = 0;
                                $pending_count = 0;
                                $paid_count = 0;
                                
                                foreach ($quarterly_taxes as $quarter): 
                                    $days_late = $quarter['days_late'] ?? 0;
                                    $penalty_amount = $quarter['penalty_amount'] ?? 0;
                                    $total_due = $quarter['total_quarterly_tax'] + $penalty_amount;
                                    
                                    if ($quarter['payment_status'] == 'overdue') $overdue_count++;
                                    elseif ($quarter['payment_status'] == 'paid') $paid_count++;
                                    else $pending_count++;
                                ?>
                                    <div class="quarter-card <?php echo $quarter['payment_status'] == 'overdue' ? 'quarter-card-overdue' : ($quarter['payment_status'] == 'paid' ? 'quarter-card-paid' : ''); ?>">
                                        <div class="text-center mb-3">
                                            <div class="text-2xl font-bold <?php echo $quarter['payment_status'] == 'overdue' ? 'text-red-700' : ($quarter['payment_status'] == 'paid' ? 'text-green-700' : 'text-green-700'); ?>">
                                                <?php echo $quarter['quarter']; ?>
                                            </div>
                                            <div class="text-sm text-gray-600"><?php echo $quarter['year']; ?></div>
                                        </div>
                                        
                                        <!-- Penalty Display (read-only) -->
                                        <?php if ($days_late > 0): ?>
                                            <div class="mb-3 p-2 bg-red-100 rounded-lg">
                                                <div class="text-sm text-red-700">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    <strong><?php echo $days_late; ?> days late</strong>
                                                </div>
                                                <div class="text-sm text-red-700">
                                                    Penalty: <?php echo formatCurrency($penalty_amount); ?>
                                                </div>
                                                <div class="text-xs text-red-600 mt-1">
                                                    (2% monthly penalty)
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="space-y-2">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-gray-900 mb-1">
                                                    <?php echo formatCurrency($total_due); ?>
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    <?php echo formatCurrency($quarter['total_quarterly_tax']); ?> 
                                                    <?php if ($penalty_amount > 0): ?>
                                                        + <?php echo formatCurrency($penalty_amount); ?> penalty
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-center mt-4">
                                                <div class="text-sm font-medium text-gray-700 mb-1">Due Date</div>
                                                <div class="text-lg font-bold <?php echo $days_late > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                    <?php echo date('M j, Y', strtotime($quarter['due_date'])); ?>
                                                    <?php if ($days_late > 0): ?>
                                                        <span class="text-sm">(<?php echo $days_late; ?> days ago)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-center mt-3">
                                                <?php if ($quarter['payment_status'] == 'paid'): ?>
                                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                                        <i class="fas fa-check-circle mr-1"></i> Paid
                                                    </span>
                                                <?php elseif ($quarter['payment_status'] == 'overdue'): ?>
                                                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                                                        <i class="fas fa-clock mr-1"></i> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Summary with Penalties -->
                            <div class="mt-6 bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg p-6 border border-gray-200">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div class="bg-white p-4 rounded-lg shadow-sm">
                                        <div class="text-sm text-gray-500">Total Annual Tax</div>
                                        <div class="text-xl font-bold text-gray-900"><?php echo formatCurrency($total_annual_tax); ?></div>
                                    </div>
                                    
                                    <?php if ($total_penalty > 0): ?>
                                    <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-red-500">
                                        <div class="text-sm text-gray-500">Total Penalties</div>
                                        <div class="text-xl font-bold text-red-600"><?php echo formatCurrency($total_penalty); ?></div>
                                        <div class="text-xs text-red-500">2% monthly penalty</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="bg-white p-4 rounded-lg shadow-sm">
                                        <div class="text-sm text-gray-500">Payment Status</div>
                                        <div class="text-xl font-bold 
                                            <?php echo $overdue_count > 0 ? 'text-red-600' : ($paid_count == 4 ? 'text-green-600' : 'text-yellow-600'); ?>">
                                            <?php echo $overdue_count > 0 ? 'Overdue' : ($paid_count == 4 ? 'Paid' : 'Pending'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $paid_count; ?> paid, 
                                            <?php echo $pending_count; ?> pending, 
                                            <?php echo $overdue_count; ?> overdue
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-blue-500">
                                        <div class="text-sm text-gray-500">Total Amount Due</div>
                                        <div class="text-2xl font-bold text-blue-700">
                                            <?php echo formatCurrency($grand_total); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Discount Offer (Only January 1-31, no penalties, nothing paid) -->
                                <?php if ($eligible_for_discount && $total_penalty == 0 && $paid_count == 0): ?>
                                    <div class="mt-4 p-4 discount-banner rounded-lg text-white">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <i class="fas fa-gift text-2xl text-yellow-300 mr-3"></i>
                                                <div>
                                                    <div class="font-bold text-xl">🎉 ANNUAL PAYMENT DISCOUNT AVAILABLE!</div>
                                                    <div class="text-sm opacity-90">
                                                        <strong>January Special:</strong> Pay all 4 quarters at once between <strong>January 1-31</strong> 
                                                        and get <strong><?php echo $discount_percent; ?>% discount</strong> on total annual tax!
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-2xl font-bold text-yellow-300">
                                                    Pay only: <?php echo formatCurrency($discounted_total); ?>
                                                </div>
                                                <div class="text-sm opacity-90">
                                                    Save: <?php echo formatCurrency($discount_amount); ?>
                                                    <br>
                                                    <span class="text-xs">(Offer valid until Jan 31 only)</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <a href="payment.php?ref=<?php echo $app['reference_number']; ?>&id=<?php echo $app['id']; ?>&annual=1" 
                                               class="inline-flex items-center px-6 py-3 bg-yellow-400 text-gray-900 font-bold rounded-lg hover:bg-yellow-500 transition shadow-lg">
                                                <i class="fas fa-credit-card mr-2"></i> PAY ANNUAL WITH <?php echo $discount_percent; ?>% DISCOUNT
                                            </a>
                                        </div>
                                    </div>
                                <?php elseif ($total_penalty == 0 && $paid_count == 0): ?>
                                    <!-- Regular payment option (no discount) -->
                                    <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-medium text-gray-900">Regular Payment Options</div>
                                                <div class="text-sm text-gray-600">
                                                    <?php if (date('n') > 1): ?>
                                                        <span class="text-red-600">⚠️ Annual discount only available January 1-31</span>
                                                    <?php else: ?>
                                                        Pay quarterly or wait until January for annual discount
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="space-x-3">
                                                <a href="payment.php?ref=<?php echo $app['reference_number']; ?>&id=<?php echo $app['id']; ?>&quarter=Q1" 
                                                   class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                                    <i class="fas fa-credit-card mr-2"></i> Pay Q1 Now
                                                </a>
                                                <a href="payment.php?ref=<?php echo $app['reference_number']; ?>&id=<?php echo $app['id']; ?>" 
                                                   class="inline-flex items-center px-5 py-2.5 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition">
                                                    <i class="fas fa-list mr-2"></i> View All Payments
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-user"></i></div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Applicant Information</h3>
                                    <p class="text-sm text-gray-500">Property owner details</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo $full_name; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Contact Number</div>
                                    <div class="info-value"><?php echo $app['phone']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo $app['email']; ?></div>
                                </div>
                                <?php if (!empty($app['tin_number'])): ?>
                                <div>
                                    <div class="info-label">TIN Number</div>
                                    <div class="info-value"><?php echo $app['tin_number']; ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <div class="info-label">Address</div>
                                <div class="info-value text-sm">
                                    <?php 
                                    $address_parts = [];
                                    if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                    if (!empty($app['street'])) $address_parts[] = $app['street'];
                                    if (!empty($app['owner_barangay'])) $address_parts[] = 'Brgy. ' . $app['owner_barangay'];
                                    if (!empty($app['owner_district'])) $address_parts[] = 'Dist. ' . $app['owner_district'];
                                    if (!empty($app['owner_city'])) $address_parts[] = $app['owner_city'];
                                    if (!empty($app['owner_province'])) $address_parts[] = $app['owner_province'];
                                    if (!empty($app['owner_zip_code'])) $address_parts[] = $app['owner_zip_code'];
                                    echo implode(', ', $address_parts);
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-green-100 text-green-600"><i class="fas fa-home"></i></div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Property Details</h3>
                                    <p class="text-sm text-gray-500">Location and features</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Lot Location</div>
                                    <div class="info-value"><?php echo $app['lot_location']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Barangay</div>
                                    <div class="info-value">Brgy. <?php echo $app['barangay']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">District</div>
                                    <div class="info-value"><?php echo $app['district']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Property Type</div>
                                    <div class="info-value"><?php echo $app['has_building'] == 'yes' ? 'With Building' : 'Vacant Land'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Instructions -->
                    <div class="px-6 py-4 bg-blue-50 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="font-medium text-gray-900">Ready for Payment</div>
                                <div class="text-sm text-gray-600">
                                    <?php if ($eligible_for_discount && $total_penalty == 0 && $paid_count == 0): ?>
                                        <span class="text-green-700 font-bold">🎁 January Special: Get <?php echo $discount_percent; ?>% discount on annual payment!</span>
                                    <?php elseif ($total_penalty > 0): ?>
                                        <span class="text-red-700 font-bold">⚠️ Overdue payments incur 2% monthly penalty</span>
                                    <?php else: ?>
                                        Your property assessment has been approved. You may now proceed to pay your RPT taxes.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <?php if ($eligible_for_discount && $total_penalty == 0 && $paid_count == 0): ?>
                                    <a href="payment.php?ref=<?php echo $app['reference_number']; ?>&id=<?php echo $app['id']; ?>&annual=1" 
                                       class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition shadow-md">
                                        <i class="fas fa-gift mr-2"></i> Pay Annual with <?php echo $discount_percent; ?>% Discount
                                    </a>
                                <?php else: ?>
                                    <a href="payment.php?ref=<?php echo $app['reference_number']; ?>&id=<?php echo $app['id']; ?>" 
                                       class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                        <i class="fas fa-credit-card mr-2"></i> Pay Taxes Now
                                    </a>
                                <?php endif; ?>
                                <button class="inline-flex items-center px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-print mr-2"></i> Print Assessment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>