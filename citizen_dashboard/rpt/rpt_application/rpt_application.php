<?php
// revenue/citizen_dashboard/rpt/rpt_application/rpt_application.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_email = $_SESSION['user_email'] ?? '';

// Include database connection
require_once '../../../db/RPT/rpt_db.php';

// Get user's property applications with detailed tax data
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.id as registration_id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.has_building,
            pr.status,
            pr.correction_notes,
            pr.created_at,
            pi.scheduled_date,
            pi.assessor_name,
            pi.status as inspection_status,
            
            -- Tax data for approved properties
            pt.total_annual_tax,
            pt.land_annual_tax,
            pt.total_building_annual_tax,
            pt.approval_date,
            
            -- Land property details
            lp.tdn as land_tdn,
            lp.land_area_sqm,
            lp.land_market_value,
            lp.land_assessed_value,
            lp.basic_tax_amount as land_basic_tax,
            lp.sef_tax_amount as land_sef_tax,
            
            -- Building property details
            bp.tdn as building_tdn,
            bp.floor_area_sqm,
            bp.building_market_value,
            bp.building_assessed_value,
            bp.basic_tax_amount as building_basic_tax,
            bp.sef_tax_amount as building_sef_tax
            
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN property_inspections pi ON pr.id = pi.registration_id
        LEFT JOIN property_totals pt ON pr.id = pt.registration_id AND pt.status = 'active'
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
        WHERE po.user_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if we're getting data
    error_log("User ID: " . $user_id);
    error_log("Applications found: " . count($applications));
    
} catch(PDOException $e) {
    $error = "Error fetching applications: " . $e->getMessage();
    error_log("Database Error: " . $e->getMessage());
}

// Also get quarterly taxes separately
$quarterly_taxes = [];
if (!empty($applications)) {
    $registration_ids = array_column($applications, 'registration_id');
    $placeholders = str_repeat('?,', count($registration_ids) - 1) . '?';
    
    try {
        $stmt = $pdo->prepare("
            SELECT qt.*, pt.registration_id 
            FROM quarterly_taxes qt
            JOIN property_totals pt ON qt.property_total_id = pt.id
            WHERE pt.registration_id IN ($placeholders)
            ORDER BY qt.year DESC, 
                CASE qt.quarter 
                    WHEN 'Q1' THEN 1 
                    WHEN 'Q2' THEN 2 
                    WHEN 'Q3' THEN 3 
                    WHEN 'Q4' THEN 4 
                END DESC
        ");
        $stmt->execute($registration_ids);
        $all_quarterly_taxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group quarterly taxes by registration_id
        foreach ($all_quarterly_taxes as $tax) {
            $quarterly_taxes[$tax['registration_id']][] = $tax;
        }
    } catch(PDOException $e) {
        error_log("Error fetching quarterly taxes: " . $e->getMessage());
    }
}

// Group applications by registration_id for better organization
$grouped_applications = [];
foreach ($applications as $app) {
    $reg_id = $app['registration_id'];
    if (!isset($grouped_applications[$reg_id])) {
        $grouped_applications[$reg_id] = $app;
        $grouped_applications[$reg_id]['quarterly_taxes'] = $quarterly_taxes[$reg_id] ?? [];
    }
}

// Function to format currency
function formatCurrency($amount) {
    if ($amount === null || $amount === '') return '₱0.00';
    return '₱' . number_format($amount, 2);
}

// Function to format date
function formatDate($date) {
    if (!$date || $date == '0000-00-00') return 'Not set';
    return date('F j, Y', strtotime($date));
}

// Function to get status color - UPDATED WITH NEW STATUSES
function getStatusColor($status) {
    $colors = [
        'pending' => 'bg-blue-100 text-blue-800 border-blue-200',
        'for_inspection' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'needs_correction' => 'bg-orange-100 text-orange-800 border-orange-200',
        'assessed' => 'bg-purple-100 text-purple-800 border-purple-200',
        'approved' => 'bg-green-100 text-green-800 border-green-200',
        'rejected' => 'bg-red-100 text-red-800 border-red-200',
        'resubmitted' => 'bg-indigo-100 text-indigo-800 border-indigo-200'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
}

// Function to get status icon - UPDATED WITH NEW STATUSES
function getStatusIcon($status) {
    $icons = [
        'pending' => 'fas fa-clock',
        'for_inspection' => 'fas fa-search',
        'needs_correction' => 'fas fa-exclamation-triangle',
        'assessed' => 'fas fa-calculator',
        'approved' => 'fas fa-check-circle',
        'rejected' => 'fas fa-times-circle',
        'resubmitted' => 'fas fa-redo'
    ];
    return $icons[$status] ?? 'fas fa-question-circle';
}

// Function to get progress percentage - UPDATED WITH NEW STATUSES
function getProgressPercentage($status) {
    switch($status) {
        case 'pending': return 25;
        case 'for_inspection': return 50;
        case 'needs_correction': return 25; // Back to correction
        case 'assessed': return 75;
        case 'approved': return 100;
        case 'rejected': return 0; // Reset to start
        case 'resubmitted': return 25; // Back to pending stage
        default: return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid;
        }
        
        .progress-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4a90e2, #357abd);
            transition: width 0.3s ease;
        }
        
        .info-box {
            background: #f8fafc;
            border-left: 4px solid #4a90e2;
            padding: 12px 16px;
            border-radius: 0 6px 6px 0;
        }
        
        .tax-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #eff6ff 100%);
            border: 1px solid #dbeafe;
            border-radius: 8px;
        }
        
        .quarter-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .quarter-card:hover {
            border-color: #4a90e2;
            transform: translateY(-2px);
        }
        
        .quarter-paid {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        
        .quarter-pending {
            background: #fefce8;
            border-color: #fef08a;
        }
        
        .quarter-overdue {
            background: #fef2f2;
            border-color: #fecaca;
        }
        
        /* New status styles */
        .status-rejected {
            background: #fee2e2;
            border-color: #fecaca;
        }
        
        .status-resubmitted {
            background: #e0e7ff;
            border-color: #c7d2fe;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <a href="../rpt_services.php" class="text-gray-600 hover:text-gray-900 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">My Applications</h1>
                    <p class="text-gray-600">Track your property registration applications</p>
                </div>
            </div>
            
            <!-- Stats - UPDATED WITH NEW STATUS COUNTS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-file-alt text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Applications</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo count($grouped_applications); ?></p>
                        </div>
                    </div>
                </div>
                
                <?php 
                $approved_count = array_filter($grouped_applications, function($app) {
                    return $app['status'] == 'approved';
                });
                $pending_count = array_filter($grouped_applications, function($app) {
                    return $app['status'] == 'pending' || $app['status'] == 'for_inspection' || $app['status'] == 'resubmitted';
                });
                $rejected_count = array_filter($grouped_applications, function($app) {
                    return $app['status'] == 'rejected';
                });
                ?>
                
                <div class="card p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Approved</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo count($approved_count); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-50 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">In Progress</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo count($pending_count); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Rejected</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo count($rejected_count); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-6 card p-4 border-l-4 border-red-500 bg-red-50">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <div>
                        <p class="font-medium text-red-800">Error Loading Applications</p>
                        <p class="text-red-600 text-sm"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($grouped_applications)): ?>
            <div class="card p-12 text-center">
                <div class="max-w-md mx-auto">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-folder-open text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-3">No Applications Found</h3>
                    <p class="text-gray-500 mb-8">You haven't submitted any property registration applications yet.</p>
                    <a href="../rpt_registration/rpt_registration.php" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Register New Property
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($grouped_applications as $application): ?>
                    <div class="card overflow-hidden">
                        <!-- Application Header -->
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-3 md:mb-0">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-home text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900"><?php echo $application['lot_location']; ?></h3>
                                            <p class="text-sm text-gray-600">Ref: <?php echo $application['reference_number']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span class="status-badge <?php echo getStatusColor($application['status']); ?>">
                                        <i class="<?php echo getStatusIcon($application['status']); ?> mr-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        <i class="far fa-calendar mr-1"></i>
                                        <?php echo date('M d, Y', strtotime($application['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Application Details -->
                        <div class="p-6">
                            <!-- Property Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-3">Property Details</h4>
                                    <div class="space-y-2">
                                        <div class="flex">
                                            <span class="text-gray-600 w-32">Location:</span>
                                            <span class="font-medium"><?php echo $application['lot_location']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="text-gray-600 w-32">Barangay:</span>
                                            <span class="font-medium"><?php echo $application['barangay']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="text-gray-600 w-32">Type:</span>
                                            <span class="font-medium">
                                                <?php echo $application['has_building'] == 'yes' ? 'Land with Building' : 'Vacant Land'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-3">Application Progress</h4>
                                    <div class="progress-bar mb-2">
                                        <?php 
                                        $progress = getProgressPercentage($application['status']);
                                        ?>
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-600">
                                        <span>Submitted</span>
                                        <span>Inspection</span>
                                        <span>Assessment</span>
                                        <span>Approved</span>
                                    </div>
                                </div>
                            </div>

                            <!-- REJECTED APPLICATION NOTICE -->
                            <?php if ($application['status'] == 'rejected'): ?>
                                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r">
                                    <div class="flex items-start">
                                        <i class="fas fa-times-circle text-red-600 mt-1 mr-3"></i>
                                        <div>
                                            <h5 class="font-medium text-red-800 mb-1">Application Rejected</h5>
                                            <p class="text-sm text-red-700">Your application has been rejected. Please review the requirements and resubmit.</p>
                                            <?php if ($application['correction_notes']): ?>
                                                <p class="text-sm text-red-600 mt-2">
                                                    <span class="font-semibold">Reason:</span> <?php echo $application['correction_notes']; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- RESUBMITTED APPLICATION NOTICE -->
                            <?php if ($application['status'] == 'resubmitted'): ?>
                                <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 mb-6 rounded-r">
                                    <div class="flex items-start">
                                        <i class="fas fa-redo text-indigo-600 mt-1 mr-3"></i>
                                        <div>
                                            <h5 class="font-medium text-indigo-800 mb-1">Application Resubmitted</h5>
                                            <p class="text-sm text-indigo-700">Your application has been resubmitted and is back in the review queue.</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Inspection Notice -->
                            <?php if ($application['status'] == 'for_inspection' && $application['scheduled_date']): ?>
                                <div class="info-box mb-6">
                                    <div class="flex items-start">
                                        <i class="fas fa-calendar-check text-blue-600 mt-1 mr-3"></i>
                                        <div>
                                            <h5 class="font-medium text-gray-900 mb-1">Inspection Scheduled</h5>
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Date:</span> <?php echo formatDate($application['scheduled_date']); ?>
                                                <?php if ($application['assessor_name'] && $application['assessor_name'] != 'To be assigned'): ?>
                                                    • <span class="font-medium">Assessor:</span> <?php echo $application['assessor_name']; ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Correction Notes (for needs_correction status) -->
                            <?php if ($application['status'] == 'needs_correction' && $application['correction_notes']): ?>
                                <div class="bg-orange-50 border-l-4 border-orange-500 p-4 mb-6 rounded-r">
                                    <div class="flex items-start">
                                        <i class="fas fa-exclamation-circle text-orange-600 mt-1 mr-3"></i>
                                        <div>
                                            <h5 class="font-medium text-orange-800 mb-1">Correction Required</h5>
                                            <p class="text-sm text-orange-700"><?php echo $application['correction_notes']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Tax Assessment Section -->
                            <?php if ($application['status'] == 'approved'): ?>
                                <div class="border-t border-gray-200 pt-6 mt-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-lg font-semibold text-gray-900">Tax Assessment</h4>
                                        <span class="text-sm text-green-600">
                                            <i class="far fa-calendar-check mr-1"></i>
                                            Approved: <?php echo date('M d, Y', strtotime($application['approval_date'])); ?>
                                        </span>
                                    </div>

                                    <!-- Tax Declaration Numbers -->
                                    <div class="mb-6">
                                        <h5 class="font-medium text-gray-700 mb-3">Tax Declaration Numbers</h5>
                                        <div class="flex flex-wrap gap-3">
                                            <div class="px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                                                <p class="text-xs text-blue-600">Land TDN</p>
                                                <p class="font-mono font-bold text-blue-800"><?php echo $application['land_tdn'] ?: 'Pending'; ?></p>
                                            </div>
                                            <?php if ($application['has_building'] == 'yes' && $application['building_tdn']): ?>
                                                <div class="px-4 py-2 bg-green-50 border border-green-200 rounded-lg">
                                                    <p class="text-xs text-green-600">Building TDN</p>
                                                    <p class="font-mono font-bold text-green-800"><?php echo $application['building_tdn']; ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Tax Breakdown -->
                                    <div class="mb-6">
                                        <h5 class="font-medium text-gray-700 mb-3">Annual Tax Breakdown</h5>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="tax-card p-4">
                                                <div class="flex items-center mb-2">
                                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                                                        <i class="fas fa-map text-blue-600 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm text-gray-600">Land Tax</p>
                                                        <p class="text-lg font-bold text-gray-900"><?php echo formatCurrency($application['land_annual_tax']); ?></p>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-gray-500"><?php echo number_format($application['land_area_sqm'], 2); ?> sqm</p>
                                            </div>
                                            
                                            <?php if ($application['has_building'] == 'yes' && $application['total_building_annual_tax']): ?>
                                                <div class="tax-card p-4">
                                                    <div class="flex items-center mb-2">
                                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-2">
                                                            <i class="fas fa-home text-green-600 text-sm"></i>
                                                        </div>
                                                        <div>
                                                            <p class="text-sm text-gray-600">Building Tax</p>
                                                            <p class="text-lg font-bold text-gray-900"><?php echo formatCurrency($application['total_building_annual_tax']); ?></p>
                                                        </div>
                                                    </div>
                                                    <p class="text-xs text-gray-500"><?php echo number_format($application['floor_area_sqm'], 2); ?> sqm</p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-4 text-white">
                                                <p class="text-sm mb-1">Total Annual Tax</p>
                                                <p class="text-xl font-bold"><?php echo formatCurrency($application['total_annual_tax']); ?></p>
                                                <p class="text-xs opacity-90 mt-1">Paid Quarterly</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quarterly Payments -->
                                    <?php if (!empty($application['quarterly_taxes'])): ?>
                                        <div>
                                            <h5 class="font-medium text-gray-700 mb-3">Quarterly Payments</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                <?php foreach ($application['quarterly_taxes'] as $quarter): 
                                                    $is_paid = $quarter['payment_status'] == 'paid';
                                                    $is_overdue = $quarter['payment_status'] == 'overdue';
                                                    $card_class = $is_paid ? 'quarter-paid' : ($is_overdue ? 'quarter-overdue' : 'quarter-pending');
                                                ?>
                                                    <div class="quarter-card p-4 <?php echo $card_class; ?>">
                                                        <div class="flex justify-between items-start mb-3">
                                                            <div>
                                                                <p class="font-bold text-gray-900 text-lg"><?php echo $quarter['quarter']; ?></p>
                                                                <p class="text-sm text-gray-600"><?php echo $quarter['year']; ?></p>
                                                            </div>
                                                            <span class="px-2 py-1 text-xs rounded-full 
                                                                <?php echo $is_paid ? 'bg-green-100 text-green-800' : ($is_overdue ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                                <?php echo ucfirst($quarter['payment_status']); ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <p class="text-sm text-gray-600">Amount Due</p>
                                                            <p class="font-bold text-gray-900"><?php echo formatCurrency($quarter['total_quarterly_tax']); ?></p>
                                                        </div>
                                                        
                                                        <div class="mb-4">
                                                            <p class="text-sm text-gray-600">Due Date</p>
                                                            <p class="font-medium text-gray-800"><?php echo date('M d, Y', strtotime($quarter['due_date'])); ?></p>
                                                        </div>
                                                        
                                                        <?php if ($is_paid): ?>
                                                            <div class="flex items-center text-green-600 text-sm">
                                                                <i class="fas fa-check-circle mr-1"></i>
                                                                Paid on <?php echo date('M d', strtotime($quarter['payment_date'])); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-3 rounded transition-colors duration-200">
                                                                Pay Now
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Payment Instructions -->
                                    <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <h6 class="font-medium text-gray-700 mb-2">Payment Instructions</h6>
                                        <ul class="text-sm text-gray-600 space-y-1">
                                            <li>• Present your Tax Declaration Number (TDN) at the Treasurer's Office</li>
                                            <li>• Pay at Municipal Hall, 8:00 AM - 5:00 PM, Monday to Friday</li>
                                            <li>• Bring a valid government ID for verification</li>
                                            <li>• Late payments incur penalties as per ordinance</li>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Actions - UPDATED FOR NEW STATUSES -->
                            <div class="mt-6 pt-6 border-t border-gray-200 flex justify-end">
                                <?php if ($application['status'] == 'needs_correction'): ?>
                                    <button class="bg-orange-600 hover:bg-orange-700 text-white font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-edit mr-2"></i>
                                        Update Application
                                    </button>
                                <?php elseif ($application['status'] == 'rejected'): ?>
                                    <a href="../rpt_registration/rpt_registration.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-redo mr-2"></i>
                                        Resubmit Application
                                    </a>
                                <?php elseif ($application['status'] == 'approved'): ?>
                                    <div class="flex space-x-3">
                                        <button class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                            <i class="fas fa-print mr-2"></i>
                                            Print Assessment
                                        </button>
                                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                            <i class="fas fa-file-invoice-dollar mr-2"></i>
                                            View Tax Bill
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2 px-6 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-eye mr-2"></i>
                                        View Details
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Help Section - UPDATED WITH NEW STATUSES -->
        <div class="mt-8 card p-6">
            <div class="flex items-start mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-question-circle text-blue-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 mb-2">Need Help?</h3>
                    <p class="text-gray-600 text-sm mb-4">Understanding your application status:</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="status-badge <?php echo getStatusColor('pending'); ?> mr-2">Pending</span>
                    </div>
                    <p class="text-sm text-gray-600">Your application is under initial review by municipal staff.</p>
                </div>
                
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="status-badge <?php echo getStatusColor('for_inspection'); ?> mr-2">For Inspection</span>
                    </div>
                    <p class="text-sm text-gray-600">Property inspection has been scheduled with an assessor.</p>
                </div>
                
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="status-badge <?php echo getStatusColor('approved'); ?> mr-2">Approved</span>
                    </div>
                    <p class="text-sm text-gray-600">Application approved. TDN issued and tax bill available.</p>
                </div>

                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="status-badge <?php echo getStatusColor('rejected'); ?> mr-2">Rejected</span>
                    </div>
                    <p class="text-sm text-gray-600">Application rejected. Please check notes and resubmit.</p>
                </div>
                
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="status-badge <?php echo getStatusColor('resubmitted'); ?> mr-2">Resubmitted</span>
                    </div>
                    <p class="text-sm text-gray-600">Application resubmitted and back in review queue.</p>
                </div>
                
                <div class="p-3 border border-gray-200 rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="status-badge <?php echo getStatusColor('needs_correction'); ?> mr-2">Correction</span>
                    </div>
                    <p class="text-sm text-gray-600">Additional information or corrections required.</p>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    For further assistance, please contact the Municipal Assessor's Office or visit the Municipal Hall during office hours.
                </p>
            </div>
        </div>
    </div>

    <script>
    // Simple interactions
    document.querySelectorAll('.quarter-card button').forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.textContent.includes('Pay Now')) {
                if (confirm('You will be redirected to the payment page. Continue?')) {
                    // Here you would redirect to payment page
                    console.log('Redirecting to payment...');
                }
            }
        });
    });
    
    document.querySelectorAll('[class*="fa-print"]').forEach(button => {
        button.closest('button')?.addEventListener('click', function() {
            alert('Print functionality will be available soon.');
        });
    });
    </script>
</body>
</html>