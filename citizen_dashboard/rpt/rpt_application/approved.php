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
            po.address as owner_address,
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
        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            font-size: 0.75rem; 
            font-weight: 600; 
        }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-paid { background-color: #dbeafe; color: #1e40af; }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .info-section {
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        
        .value-highlight {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
        }
        
        .value-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .progress-container {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin: 1rem 0;
        }
        .progress-fill {
            height: 100%;
            background: #059669;
            border-radius: 3px;
        }
        
        .info-card-header { 
            display: flex; 
            align-items: center; 
            margin-bottom: 1.25rem; 
            padding-bottom: 0.75rem; 
            border-bottom: 2px solid #f3f4f6; 
        }
        .icon-circle { 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-right: 1rem; 
        }
        .info-label { 
            font-size: 0.875rem; 
            color: #6b7280; 
            font-weight: 500; 
            margin-bottom: 0.25rem; 
        }
        .info-value { 
            font-size: 1rem; 
            color: #111827; 
            font-weight: 500; 
        }
        
        .section-header {
            border-left: 5px solid #4a90e2;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center mb-4">
            <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-3 flex items-center">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Approved Applications</h1>
                <p class="text-gray-600">Your approved property tax assessments</p>
            </div>
        </div>

        <!-- Summary -->
        <?php if ($total_applications > 0): ?>
        <div class="flex items-center mb-6">
            <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo $total_applications; ?> Approved Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                <div class="text-sm text-gray-600">Ready for tax payment</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="card p-8 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Approved Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any approved applications yet.</p>
            <div class="space-x-3">
                <a href="pending.php" class="inline-flex items-center px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-clock mr-2"></i> Check Pending Applications
                </a>
                <a href="assessed.php" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-chart-bar mr-2"></i> Check Assessed Applications
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Applications List -->
        <div class="space-y-8">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
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
                ?>

                <!-- Application Card -->
                <div class="card overflow-hidden">
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['reference_number']; ?></span>
                                    <span class="status-badge status-approved">
                                        <i class="fas fa-check-circle mr-1"></i>Approved
                                    </span>
                                </div>
                                <div class="text-gray-600 flex items-center">
                                    <i class="fas fa-map-marker-alt mr-2"></i>
                                    <span><?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?></span>
                                </div>
                            </div>
                            <div class="mt-3 md:mt-0 text-right">
                                <div class="text-sm text-gray-500">Approved On</div>
                                <div class="font-medium text-gray-900"><?php echo date('F j, Y', strtotime($app['approval_date'])); ?></div>
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div>
                            <div class="progress-container">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Pending</span>
                                <span>For Inspection</span>
                                <span>Assessed</span>
                                <span class="font-bold text-green-600">Approved</span>
                            </div>
                        </div>
                    </div>

                    <!-- Property Info & Applicant Info Sections -->
                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Applicant Info -->
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-blue-100 text-blue-600">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Applicant</h3>
                                    <p class="text-sm text-gray-500">Your registered information</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo $full_name; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Birthdate</div>
                                    <div class="info-value"><?php echo isset($app['birthdate']) ? date('M j, Y', strtotime($app['birthdate'])) : '-'; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Contact</div>
                                    <div class="info-value"><?php echo $app['phone']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo $app['email']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Sex</div>
                                    <div class="info-value"><?php echo ucfirst($app['sex']); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Marital Status</div>
                                    <div class="info-value"><?php echo ucfirst($app['marital_status']); ?></div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="info-label">Address</div>
                                <div class="info-value text-sm"><?php echo $app['owner_address']; ?></div>
                            </div>
                        </div>

                        <!-- Property Info -->
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-green-100 text-green-600">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Property</h3>
                                    <p class="text-sm text-gray-500">Property details</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Location</div>
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
                                    <div class="info-label">City</div>
                                    <div class="info-value"><?php echo $app['city']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Province</div>
                                    <div class="info-value"><?php echo $app['province']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Zip Code</div>
                                    <div class="info-value"><?php echo $app['zip_code']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Building</div>
                                    <div class="info-value"><?php echo $app['has_building'] == 'yes' ? 'Has Building' : 'Vacant Land'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assessment Summary -->
                    <div class="p-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-chart-bar text-blue-600 mr-2"></i> Assessment Summary
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <!-- Land -->
                            <div class="info-section">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-map text-blue-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Land Assessment</div>
                                        <div class="text-sm text-gray-500">Property land details</div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">TDN:</span>
                                        <span class="font-medium font-mono"><?php echo $land_data['tdn'] ?? 'N/A'; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Property Type:</span>
                                        <span class="font-medium"><?php echo $land_data['property_type'] ?? 'N/A'; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Land Area:</span>
                                        <span class="font-medium"><?php echo $land_data['land_area_sqm'] ?? '0'; ?> sqm</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Annual Tax:</span>
                                        <span class="font-medium"><?php echo formatCurrency($total_land_tax); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Building -->
                            <div class="info-section">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-building text-green-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Building Assessment</div>
                                        <div class="text-sm text-gray-500"><?php echo $app['has_building'] == 'yes' ? 'Building details' : 'No building'; ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($building_data) && $app['has_building'] == 'yes'): ?>
                                    <?php foreach ($building_data as $index => $building): ?>
                                        <div class="<?php echo $index > 0 ? 'mt-4 pt-4 border-t border-gray-200' : ''; ?>">
                                            <?php if ($index > 0): ?>
                                                <div class="text-sm font-medium text-gray-700 mb-2">Building <?php echo $index + 1; ?></div>
                                            <?php endif; ?>
                                            <div class="space-y-3">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">TDN:</span>
                                                    <span class="font-medium font-mono"><?php echo $building['tdn'] ?? 'N/A'; ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Construction Type:</span>
                                                    <span class="font-medium"><?php echo $building['material_type']; ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Floor Area:</span>
                                                    <span class="font-medium"><?php echo $building['floor_area_sqm']; ?> sqm</span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Annual Tax:</span>
                                                    <span class="font-medium"><?php echo formatCurrency($building['annual_tax'] ?? 0); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <div class="text-gray-500 italic">No building on this property</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Total Tax -->
                            <div class="info-section bg-blue-50 border border-blue-100">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-blue-200 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-file-invoice-dollar text-blue-700 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Tax Summary</div>
                                        <div class="text-sm text-blue-600">Annual tax breakdown</div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex justify-between">
                                        <span class="text-gray-700">Land Tax:</span>
                                        <span class="font-medium"><?php echo formatCurrency($total_land_tax); ?></span>
                                    </div>
                                    <?php if ($total_building_tax > 0): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-700">Building Tax:</span>
                                        <span class="font-medium"><?php echo formatCurrency($total_building_tax); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex justify-between text-lg font-bold border-t border-blue-200 pt-4">
                                        <span class="text-blue-800">Total Annual Tax:</span>
                                        <span class="text-blue-700"><?php echo formatCurrency($total_annual_tax); ?></span>
                                    </div>
                                    <?php if (!empty($land_data['tdn'])): ?>
                                        <div class="text-xs text-gray-500 text-center mt-3 pt-3 border-t border-blue-200">
                                            TDN: <?php echo $land_data['tdn']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Annual Discount Offer -->
                        <?php if ($eligible_for_discount && $total_penalty == 0 && $paid_count == 0): ?>
                            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <i class="fas fa-gift text-green-600 text-xl mr-3"></i>
                                        <div>
                                            <div class="font-medium text-green-800">Annual Payment Discount Available</div>
                                            <div class="text-sm text-green-600">Pay all 4 quarters in January and save <?php echo $discount_percent; ?>%</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-green-700"><?php echo formatCurrency($discounted_total); ?></div>
                                        <div class="text-sm text-green-600">
                                            Save: <?php echo formatCurrency($discount_amount); ?>
                                            <span class="text-xs">(Until Jan 31)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Quarterly Taxes Table - Business Billing Style -->
                        <?php if (!empty($quarterly_taxes)): ?>
                            <div class="mt-8">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 section-header">
                                    <i class="fas fa-calendar-alt mr-2"></i>Quarterly Tax Payments (<?php echo $quarterly_taxes[0]['year'] ?? date('Y'); ?>)
                                </h4>
                                
                                <!-- Quarterly Taxes Table -->
                                <div class="overflow-x-auto mb-6">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quarter</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Amount</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penalty</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($quarterly_taxes as $tax): 
                                                $days_late = $tax['days_late'] ?? 0;
                                                $penalty_amount = $tax['penalty_amount'] ?? 0;
                                                $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                                                $total_due = $tax_amount + $penalty_amount;
                                            ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="font-semibold"><?php echo $tax['quarter']; ?></span>
                                                        <span class="text-gray-600"> <?php echo $tax['year']; ?></span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php echo date('M d, Y', strtotime($tax['due_date'])); ?>
                                                        <?php if ($days_late > 0): ?>
                                                            <div class="text-xs text-red-600 mt-1">
                                                                (<?php echo $days_late; ?> days late)
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php echo formatCurrency($tax_amount); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($penalty_amount > 0): ?>
                                                            <span class="text-red-600 font-semibold">
                                                                <?php echo formatCurrency($penalty_amount); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-green-600">₱0.00</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap font-bold text-lg">
                                                        <?php echo formatCurrency($total_due); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($tax['payment_status'] == 'paid'): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                <i class="fas fa-check-circle mr-1"></i> Paid
                                                            </span>
                                                        <?php elseif ($tax['payment_status'] == 'overdue'): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                <i class="fas fa-clock mr-1"></i> Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td colspan="2" class="px-6 py-4 font-bold text-right text-gray-900">
                                                    Totals:
                                                </td>
                                                <td class="px-6 py-4 font-bold text-gray-900">
                                                    <?php echo formatCurrency($total_annual_tax); ?>
                                                </td>
                                                <td class="px-6 py-4 font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                    <?php echo formatCurrency($total_penalty); ?>
                                                </td>
                                                <td class="px-6 py-4 font-bold text-xl text-blue-700">
                                                    <?php echo formatCurrency($grand_total); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if ($overdue_count > 0): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                        </span>
                                                    <?php elseif ($paid_count == 4): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <i class="fas fa-check-circle mr-1"></i> Paid
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            <i class="fas fa-clock mr-1"></i> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- Payment Summary -->
                                <div class="info-section">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <div class="text-sm text-gray-600">Total Annual Tax</div>
                                            <div class="text-xl font-bold text-gray-900"><?php echo formatCurrency($total_annual_tax); ?></div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-sm text-gray-600">Total Penalties</div>
                                            <div class="text-xl font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                <?php echo formatCurrency($total_penalty); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php if ($total_penalty > 0): ?>
                                                    2% monthly penalty applied
                                                <?php else: ?>
                                                    No penalties
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-sm text-gray-600">Payment Status</div>
                                            <div class="text-xl font-bold <?php echo $overdue_count > 0 ? 'text-red-600' : ($paid_count == 4 ? 'text-green-600' : 'text-yellow-600'); ?>">
                                                <?php echo $overdue_count > 0 ? 'Overdue' : ($paid_count == 4 ? 'Paid' : 'Pending'); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo $paid_count; ?> paid, 
                                                <?php echo $pending_count; ?> pending, 
                                                <?php echo $overdue_count; ?> overdue
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-sm text-gray-600">Total Amount Due</div>
                                            <div class="text-2xl font-bold text-blue-700">
                                                <?php echo formatCurrency($grand_total); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer -->
                    <div class="p-6 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="font-medium text-gray-900">Tax Assessment Details</div>
                                <div class="text-sm text-gray-600">
                                    <?php if ($paid_count == 4): ?>
                                        <span class="text-green-700">
                                            <i class="fas fa-check-circle mr-1"></i> All taxes paid for the year
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
                            <div>
                                <div class="text-sm text-gray-500">
                                    TDN: <span class="font-mono font-medium text-gray-700"><?php echo $land_data['tdn'] ?? 'N/A'; ?></span>
                                </div>
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