<?php
// revenue2/citizen_dashboard/rpt/rpt_application/view_rpt_bill.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$registration_id = $_GET['registration_id'] ?? 0;

require_once '../../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to format date
function formatDate($date, $format = 'F j, Y') {
    return ($date && $date != '0000-00-00' && $date != '0000-00-00 00:00:00') ? date($format, strtotime($date)) : '-';
}

// Fetch application details
$application = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.*,
            po.first_name,
            po.last_name,
            po.middle_name,
            po.suffix,
            po.address as owner_address,
            po.city as owner_city,
            po.province as owner_province,
            po.phone,
            po.email
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE pr.id = ? AND po.user_id = ?
    ");
    $stmt->execute([$registration_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching application: " . $e->getMessage());
}

if (!$application) {
    die("Application not found or access denied.");
}

// Fetch land assessment data
$land_data = [];
try {
    $land_stmt = $pdo->prepare("
        SELECT lp.*, lc.classification
        FROM land_properties lp
        JOIN land_configurations lc ON lp.land_config_id = lc.id
        WHERE lp.registration_id = ?
    ");
    $land_stmt->execute([$registration_id]);
    $land_data = $land_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching land data: " . $e->getMessage());
}

// Fetch building assessment data
$building_data = [];
$total_building_tax = 0;
if ($application['has_building'] == 'yes') {
    try {
        $building_stmt = $pdo->prepare("
            SELECT bp.*, pc.material_type
            FROM building_properties bp
            JOIN property_configurations pc ON bp.property_config_id = pc.id
            WHERE bp.land_id = ?
        ");
        $building_stmt->execute([$land_data['id'] ?? 0]);
        $building_data = $building_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($building_data as $building) {
            $total_building_tax += $building['annual_tax'] ?? 0;
        }
    } catch(PDOException $e) {
        die("Error fetching building data: " . $e->getMessage());
    }
}

// Fetch total tax data
$total_tax_data = [];
try {
    $total_stmt = $pdo->prepare("
        SELECT * FROM property_totals 
        WHERE registration_id = ?
    ");
    $total_stmt->execute([$registration_id]);
    $total_tax_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching total tax data: " . $e->getMessage());
}

// Fetch quarterly taxes for current year
$quarterly_taxes = [];
$current_year = date('Y');
if (!empty($total_tax_data)) {
    try {
        $quarterly_stmt = $pdo->prepare("
            SELECT * FROM quarterly_taxes 
            WHERE property_total_id = ? AND year = ?
            ORDER BY FIELD(quarter, 'Q1', 'Q2', 'Q3', 'Q4')
        ");
        $quarterly_stmt->execute([$total_tax_data['id'], $current_year]);
        $quarterly_taxes = $quarterly_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Error fetching quarterly taxes: " . $e->getMessage());
    }
}

// Calculate totals
$total_land_tax = $land_data['annual_tax'] ?? 0;
$total_annual_tax = $total_land_tax + $total_building_tax;
$quarterly_amount = $total_annual_tax / 4;

// Get discount percentage
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
        return 10.00;
    }
}

// Check if eligible for annual discount
function isEligibleForAnnualDiscount($quarterly_taxes) {
    $current_date = date('Y-m-d');
    $current_month = date('n');
    $current_day = date('j');
    
    // Check if current date is January 1-31
    if ($current_month != 1 || $current_day > 31) {
        return false;
    }
    
    // Check if all quarters are unpaid
    foreach ($quarterly_taxes as $quarter) {
        if ($quarter['payment_status'] == 'paid' || $quarter['payment_status'] == 'overdue') {
            return false;
        }
    }
    
    return true;
}

$eligible_for_discount = isEligibleForAnnualDiscount($quarterly_taxes);
$discount_percent = getDiscountPercentage($pdo);
$discount_amount = $eligible_for_discount ? ($total_annual_tax * ($discount_percent / 100)) : 0;
$discounted_total = $eligible_for_discount ? ($total_annual_tax - $discount_amount) : $total_annual_tax;

// Generate billing reference number
$billing_reference = "RPT-BILL-" . date('Ymd-His') . "-" . rand(1000, 9999);
$bill_date = date('F j, Y');
$due_date = date('F j, Y', strtotime('+30 days'));

// Get owner name
$owner_name = trim($application['first_name'] . ' ' . 
    (!empty($application['middle_name']) ? $application['middle_name'] . ' ' : '') . 
    $application['last_name'] . 
    (!empty($application['suffix']) ? ' ' . $application['suffix'] : ''));

// Generate a unique bill number
$bill_number = "RPT-" . date('Y') . "-" . str_pad($registration_id, 6, '0', STR_PAD_LEFT) . "-" . date('md');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPT Billing Statement - <?php echo $application['reference_number']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                font-size: 12pt !important;
            }
            .paper-form {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                margin: 0 !important;
                padding: 20px !important;
            }
            .print-section {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            @page {
                margin: 0.5in;
            }
        }
        
        :root {
            --primary: #1a56db;
            --secondary: #6b7280;
            --accent: #047857;
        }

        body {
            background: #f9fafb;
            font-family: 'Georgia', 'Times New Roman', serif;
            padding: 20px;
        }

        .paper-form {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 2px;
            box-shadow: 
                0 1px 3px rgba(0,0,0,0.03),
                0 4px 12px rgba(0,0,0,0.05),
                inset 0 0 0 1px rgba(255,255,255,0.9);
            max-width: 8.5in;
            margin: 0 auto;
            padding: 40px;
            position: relative;
            font-family: 'Georgia', 'Times New Roman', serif;
        }

        /* Watermark effect */
        .paper-form::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 5rem;
            color: rgba(229, 231, 235, 0.1);
            pointer-events: none;
            white-space: nowrap;
            font-weight: bold;
            z-index: 0;
        }

        /* Official header */
        .official-header {
            border-bottom: 2px solid #1a56db;
            padding-bottom: 20px;
            margin-bottom: 30px;
            position: relative;
        }

        .official-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 200px;
            height: 2px;
            background: #047857;
        }

        /* Official seal */
        .official-seal {
            width: 100px;
            height: 100px;
            border: 2px solid #dc2626;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            right: 0;
            top: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .official-seal::before {
            content: 'OFFICIAL';
            position: absolute;
            color: #dc2626;
            font-size: 0.7rem;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            top: 40px;
        }

        /* Bill number stamp */
        .bill-number {
            background: #1a56db;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            letter-spacing: 1px;
            display: inline-block;
            margin-bottom: 10px;
        }

        /* Amount display */
        .amount {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #047857;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1a56db;
        }

        /* Table styles */
        .bill-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-family: 'Georgia', 'Times New Roman', serif;
        }

        .bill-table th {
            background: #f3f4f6;
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #d1d5db;
            font-weight: 600;
            color: #374151;
        }

        .bill-table td {
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
        }

        .bill-table tr:nth-child(even) {
            background: #fafafa;
        }

        .bill-table .total-row {
            background: #1e40af !important;
            color: white;
            font-weight: bold;
        }

        .bill-table .total-row td {
            border-color: #1e3a8a !important;
        }

        /* Section styling */
        .bill-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background: #f8fafc;
            position: relative;
        }

        .bill-section::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #1a56db, #10b981);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #d1d5db;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background: #1a56db;
        }

        /* Payment instructions */
        .payment-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #0ea5e9;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }

        /* Grid for details */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 15px 0;
        }

        .detail-item {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
        }

        /* Important notice */
        .notice-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            position: relative;
        }

        .notice-box::before {
            content: '⚠';
            position: absolute;
            left: 15px;
            top: 15px;
            font-weight: bold;
            color: #92400e;
        }

        .notice-content {
            margin-left: 30px;
        }

        /* Signature line */
        .signature-line {
            border-top: 2px solid #d1d5db;
            width: 300px;
            margin: 40px auto;
            padding-top: 10px;
            text-align: center;
        }

        /* Footer */
        .bill-footer {
            border-top: 2px solid #d1d5db;
            padding-top: 20px;
            margin-top: 40px;
            font-size: 0.875rem;
            color: #6b7280;
            text-align: center;
        }

        /* Action buttons */
        .action-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 10px;
            z-index: 100;
        }

        .action-btn {
            background: white;
            border: 2px solid #1a56db;
            color: #1a56db;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #1a56db;
            color: white;
            transform: scale(1.1);
        }

        /* QR Code area */
        .qr-area {
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            border: 1px dashed #d1d5db;
            border-radius: 4px;
        }

        /* Barcode area */
        .barcode {
            font-family: 'Libre Barcode 128', cursive;
            font-size: 2rem;
            text-align: center;
            margin: 20px 0;
            letter-spacing: 2px;
        }

        /* Discount banner */
        .discount-banner {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="paper-form">
        <!-- Watermark -->
        <div class="watermark">BILLING STATEMENT</div>
        
        <!-- Official Header -->
        <div class="official-header">
            <div class="official-seal">
                <svg class="w-12 h-12 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                </svg>
            </div>
            
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold" style="color: #1a56db;">GoServePH</h1>
                <div class="text-lg text-gray-600">Local Government Unit</div>
                <div class="text-sm text-gray-500">Treasurer's Office</div>
            </div>
            
            <div class="bill-number"><?php echo $bill_number; ?></div>
            
            <h2 class="text-2xl font-bold text-center mt-4">REAL PROPERTY TAX BILLING STATEMENT</h2>
            <div class="text-center text-gray-600 mt-2">Official Tax Assessment and Billing Document</div>
        </div>

        <!-- Bill Information -->
        <div class="bill-section print-section">
            <div class="section-title">
                <i class="fas fa-info-circle mr-2"></i>Bill Information
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Bill Number</div>
                    <div class="detail-value"><?php echo $bill_number; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Billing Date</div>
                    <div class="detail-value"><?php echo $bill_date; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Due Date</div>
                    <div class="detail-value"><?php echo $due_date; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Assessment Year</div>
                    <div class="detail-value"><?php echo $current_year; ?></div>
                </div>
            </div>
        </div>

        <!-- Taxpayer Information -->
        <div class="bill-section print-section">
            <div class="section-title">
                <i class="fas fa-user mr-2"></i>Taxpayer Information
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Taxpayer Name</div>
                    <div class="detail-value"><?php echo $owner_name; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Contact Number</div>
                    <div class="detail-value"><?php echo $application['phone']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value"><?php echo $application['email']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Tax Declaration No. (TDN)</div>
                    <div class="detail-value"><?php echo $land_data['tdn'] ?? 'N/A'; ?></div>
                </div>
            </div>
            
            <div class="mt-4">
                <div class="detail-label">Complete Address</div>
                <div class="detail-value"><?php echo $application['owner_address']; ?>, <?php echo $application['owner_city']; ?>, <?php echo $application['owner_province']; ?></div>
            </div>
        </div>

        <!-- Property Information -->
        <div class="bill-section print-section">
            <div class="section-title">
                <i class="fas fa-home mr-2"></i>Property Information
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Property Location</div>
                    <div class="detail-value"><?php echo $application['lot_location']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Barangay</div>
                    <div class="detail-value"><?php echo $application['barangay']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">City/Municipality</div>
                    <div class="detail-value"><?php echo $application['city']; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Property Type</div>
                    <div class="detail-value"><?php echo $application['has_building'] == 'yes' ? 'With Building' : 'Vacant Land'; ?></div>
                </div>
            </div>
        </div>

        <!-- Tax Assessment Details -->
        <div class="bill-section print-section">
            <div class="section-title">
                <i class="fas fa-chart-bar mr-2"></i>Tax Assessment Details
            </div>
            
            <table class="bill-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Assessment Details</th>
                        <th>Annual Tax</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Land Tax -->
                    <tr>
                        <td>LAND TAX</td>
                        <td>
                            <?php echo $land_data['classification'] ?? 'N/A'; ?> - 
                            <?php echo $land_data['land_area_sqm'] ?? '0'; ?> sqm
                        </td>
                        <td class="amount"><?php echo formatCurrency($total_land_tax); ?></td>
                    </tr>
                    
                    <!-- Building Tax -->
                    <?php if (!empty($building_data)): ?>
                    <?php foreach ($building_data as $index => $building): ?>
                    <tr>
                        <td>BUILDING <?php echo $index + 1; ?></td>
                        <td>
                            <?php echo $building['material_type'] ?? 'N/A'; ?> - 
                            <?php echo $building['floor_area_sqm'] ?? '0'; ?> sqm
                        </td>
                        <td class="amount"><?php echo formatCurrency($building['annual_tax'] ?? 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Subtotal -->
                    <tr>
                        <td colspan="2" class="text-right font-bold">SUBTOTAL:</td>
                        <td class="amount font-bold"><?php echo formatCurrency($total_annual_tax); ?></td>
                    </tr>
                    
                    <!-- Discount (if applicable) -->
                    <?php if ($eligible_for_discount): ?>
                    <tr style="background: #d1fae5;">
                        <td colspan="2" class="text-right font-bold text-green-800">
                            ANNUAL PAYMENT DISCOUNT (<?php echo $discount_percent; ?>%):
                        </td>
                        <td class="amount font-bold text-green-800">-<?php echo formatCurrency($discount_amount); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Total Annual Tax -->
                    <tr class="total-row">
                        <td colspan="2" class="text-right font-bold text-white">
                            TOTAL ANNUAL REAL PROPERTY TAX:
                        </td>
                        <td class="text-white font-bold text-xl">
                            <?php echo formatCurrency($eligible_for_discount ? $discounted_total : $total_annual_tax); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="mt-4 text-center">
                <div class="text-lg font-bold">Quarterly Installment: <span class="amount"><?php echo formatCurrency($total_annual_tax / 4); ?></span></div>
                <div class="text-sm text-gray-600">Payable every quarter (4 installments per year)</div>
            </div>
        </div>

        <?php if ($eligible_for_discount): ?>
        <!-- Discount Banner -->
        <div class="discount-banner print-section">
            <i class="fas fa-gift mr-2"></i>
            SPECIAL OFFER: Save <?php echo $discount_percent; ?>% (<?php echo formatCurrency($discount_amount); ?>) 
            by paying the full annual tax before January 31, <?php echo $current_year; ?>
        </div>
        <?php endif; ?>

        <!-- Quarterly Payment Schedule -->
        <div class="bill-section print-section">
            <div class="section-title">
                <i class="fas fa-calendar-alt mr-2"></i>Quarterly Payment Schedule for <?php echo $current_year; ?>
            </div>
            
            <table class="bill-table">
                <thead>
                    <tr>
                        <th>Quarter</th>
                        <th>Due Date</th>
                        <th>Amount Due</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($quarterly_taxes)): ?>
                        <?php foreach ($quarterly_taxes as $tax): 
                            $due_date_formatted = date('F j, Y', strtotime($tax['due_date']));
                            $is_past_due = strtotime($tax['due_date']) < time() && $tax['payment_status'] != 'paid';
                        ?>
                        <tr class="<?php echo $is_past_due ? 'bg-red-50' : ''; ?>">
                            <td class="font-bold"><?php echo $tax['quarter']; ?></td>
                            <td>
                                <?php echo $due_date_formatted; ?>
                                <?php if ($is_past_due): ?>
                                    <div class="text-xs text-red-600">(Past Due)</div>
                                <?php endif; ?>
                            </td>
                            <td class="amount"><?php echo formatCurrency($tax['total_quarterly_tax']); ?></td>
                            <td>
                                <?php if ($tax['payment_status'] == 'paid'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                        <i class="fas fa-check mr-1"></i> Paid
                                    </span>
                                <?php elseif ($tax['payment_status'] == 'overdue'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                        <i class="fas fa-exclamation mr-1"></i> Overdue
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-500">
                                No quarterly taxes have been generated for this year.
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr class="total-row">
                        <td colspan="2" class="text-right font-bold text-white">TOTAL FOR <?php echo $current_year; ?>:</td>
                        <td colspan="2" class="text-white font-bold text-xl">
                            <?php echo formatCurrency($total_annual_tax); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Payment Instructions -->
        <div class="payment-box print-section">
            <div class="section-title" style="border-color: #0ea5e9;">
                <i class="fas fa-credit-card mr-2"></i>Payment Instructions
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-bold text-gray-900 mb-3">Bank Payment</h4>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-university mr-2 mt-1 text-blue-600"></i>
                            <span>Bank Name: <strong>Local Government Bank</strong></span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-hashtag mr-2 mt-1 text-blue-600"></i>
                            <span>Account Number: <strong>1234-5678-9012-3456</strong></span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-building mr-2 mt-1 text-blue-600"></i>
                            <span>Account Name: <strong>LGU Treasury Office</strong></span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-sticky-note mr-2 mt-1 text-blue-600"></i>
                            <span>Reference: <strong><?php echo $bill_number; ?></strong></span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-gray-900 mb-3">Online Payment</h4>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-mobile-alt mr-2 mt-1 text-green-600"></i>
                            <span>GCash: <strong>0917-123-4567</strong></span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-wallet mr-2 mt-1 text-green-600"></i>
                            <span>PayMaya: <strong>0918-765-4321</strong></span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-globe mr-2 mt-1 text-green-600"></i>
                            <span>Website: <strong>pay.goserveph.gov.ph</strong></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-white border border-gray-200 rounded">
                <div class="text-center font-bold text-lg mb-2">TOTAL AMOUNT DUE</div>
                <div class="text-center total-amount text-3xl">
                    <?php echo formatCurrency($eligible_for_discount ? $discounted_total : $total_annual_tax); ?>
                </div>
                <div class="text-center text-sm text-gray-600 mt-2">
                    Due by: <?php echo $due_date; ?>
                </div>
            </div>
        </div>

        <!-- Important Notices -->
        <div class="notice-box print-section">
            <div class="notice-content">
                <h4 class="font-bold text-gray-900 mb-2">Important Notices</h4>
                <ul class="space-y-1 text-sm">
                    <li>• Payments made after the due date will incur a 2% monthly penalty.</li>
                    <li>• Keep this billing statement for your records and present it when making payments.</li>
                    <li>• For payment verification, please allow 3-5 business days for processing.</li>
                    <li>• Unpaid taxes may result in penalties, interest, and legal action.</li>
                    <li>• For inquiries, contact the Treasurer's Office at (02) 8123-4567.</li>
                </ul>
            </div>
        </div>

        <!-- Barcode and QR Code Area -->
        <div class="print-section">
            <div class="barcode">*<?php echo str_replace('-', '', $bill_number); ?>*</div>
            
            <div class="qr-area">
                <div class="text-sm text-gray-600 mb-2">Scan to verify this bill online:</div>
                <div class="inline-block p-4 border border-gray-300 rounded">
                    <!-- Placeholder for QR code - in production, generate actual QR code -->
                    <div class="w-32 h-32 bg-gray-100 border border-gray-300 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-xs font-bold"><?php echo substr($bill_number, 0, 10); ?></div>
                            <div class="text-xs"><?php echo substr($bill_number, 10); ?></div>
                            <div class="text-xs mt-2">Scan to Verify</div>
                        </div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 mt-2">https://verify.goserveph.gov.ph/bill/<?php echo $bill_number; ?></div>
            </div>
        </div>

        <!-- Signature Area -->
        <div class="print-section">
            <div class="signature-line">
                <div class="text-sm text-gray-600">Authorized Signature</div>
                <div class="text-xs text-gray-500 mt-1">Treasurer's Office</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bill-footer print-section">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <div class="font-bold">Office Hours</div>
                    <div>Monday - Friday: 8:00 AM - 5:00 PM</div>
                    <div>Saturday: 8:00 AM - 12:00 PM</div>
                </div>
                
                <div>
                    <div class="font-bold">Contact Information</div>
                    <div>Phone: (02) 8123-4567</div>
                    <div>Email: rpt@goserveph.gov.ph</div>
                    <div>Website: goserveph.gov.ph</div>
                </div>
                
                <div>
                    <div class="font-bold">Address</div>
                    <div>City Hall Building</div>
                    <div>Main Street, City Center</div>
                    <div>Quezon City, Metro Manila</div>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-300">
                <div class="text-xs text-gray-500 text-center">
                    This is an official billing statement. Please retain for your records.<br>
                    Document generated on: <?php echo date('F d, Y h:i A'); ?><br>
                    System Reference: <?php echo $billing_reference; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons no-print">
        <div class="action-btn" onclick="window.print()" title="Print Bill">
            <i class="fas fa-print text-xl"></i>
        </div>
        <a href="approved.php" class="action-btn" title="Back to Applications">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <div class="action-btn" onclick="downloadPDF()" title="Download as PDF">
            <i class="fas fa-download text-xl"></i>
        </div>
    </div>

    <script>
        function downloadPDF() {
            // For production, implement actual PDF generation
            alert('PDF download feature would be implemented here.\nFor now, please use the print function and save as PDF.');
        }

        // Auto-print option
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'true') {
            window.print();
        }

        // Add print-specific styles
        const printStyles = `
            @media print {
                body * {
                    visibility: hidden;
                }
                .paper-form, .paper-form * {
                    visibility: visible;
                }
                .paper-form {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    box-shadow: none;
                    border: none;
                }
                .action-buttons {
                    display: none;
                }
            }
        `;

        const styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>