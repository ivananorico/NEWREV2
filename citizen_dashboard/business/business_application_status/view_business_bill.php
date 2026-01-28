<?php
// business_application_status/view_business_bill.php
session_start();
require_once '../../../db/Business/business_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if permit_id is provided
if (!isset($_GET['permit_id'])) {
    die("No permit ID provided.");
}

$permit_id = intval($_GET['permit_id']);
$user_id = $_SESSION['user_id'];

// Get database connection
$pdo = getDatabaseConnection();
if (!$pdo) {
    die("Database connection failed.");
}

// Fetch business permit details
$query = "SELECT * FROM business_permits WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$permit_id, $user_id]);
$permit = $stmt->fetch();

if (!$permit) {
    die("Business permit not found or access denied.");
}

// Fetch quarterly taxes for current year
$current_year = date('Y');
$tax_query = "SELECT * FROM business_quarterly_taxes 
              WHERE business_permit_id = ? 
              AND year = ? 
              ORDER BY FIELD(quarter, 'Q1', 'Q2', 'Q3', 'Q4')";
$tax_stmt = $pdo->prepare($tax_query);
$tax_stmt->execute([$permit_id, $current_year]);
$quarterly_taxes = $tax_stmt->fetchAll();

// Calculate totals
$total_tax = 0;
$total_penalty = 0;
$quarter_totals = [
    'tax_amount' => 0,
    'penalty' => 0,
    'total' => 0
];

foreach ($quarterly_taxes as $tax) {
    $tax_amount = $tax['total_quarterly_tax'] ?? 0;
    $penalty_amount = $tax['penalty_amount'] ?? 0;
    $total_amount = $tax_amount + $penalty_amount;
    
    $quarter_totals['tax_amount'] += $tax_amount;
    $quarter_totals['penalty'] += $penalty_amount;
    $quarter_totals['total'] += $total_amount;
}

$grand_total = ($permit['total_tax'] ?? 0) + $quarter_totals['penalty'];

// Check discount eligibility
function getDiscountPercentage($pdo) {
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM business_discount_config 
            WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
            AND effective_date <= CURDATE()
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        return $discount['discount_percent'] ?? 0.00;
    } catch(PDOException $e) {
        return 0.00;
    }
}

$discount_percent = getDiscountPercentage($pdo);
$eligible_for_discount = false;
$current_month = date('n');
if ($current_month == 1 && $quarter_totals['penalty'] == 0) {
    $eligible_for_discount = true;
}

$discount_amount = 0;
$discounted_total = 0;
if ($eligible_for_discount && $discount_percent > 0) {
    $annual_tax = $permit['total_tax'] ?? 0;
    $discount_amount = ($annual_tax * $discount_percent) / 100;
    $discounted_total = $annual_tax - $discount_amount;
}

// Generate bill number
$bill_number = 'BUS-BILL-' . date('Ymd') . '-' . str_pad($permit_id, 6, '0', STR_PAD_LEFT);
$verification_code = strtoupper(substr(md5($bill_number . $permit_id), 0, 12));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Tax Bill - <?php echo $permit['applicant_id']; ?></title>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            @page {
                margin: 15mm;
                size: A4 landscape;
            }
            body {
                margin: 0;
                padding: 0;
                font-size: 12pt !important;
            }
            .bill-container {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                margin: 0 !important;
                padding: 20px !important;
                min-height: auto !important;
            }
        }
        
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .bill-container {
            position: relative;
            width: 11in;
            min-height: 8.5in;
            background: #fff;
            border: 3px double #1a237e;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><text x="200" y="200" text-anchor="middle" dy=".3em" font-family="Georgia" font-size="30" fill="%23000" opacity="0.05">OFFICIAL BILL</text></svg>');
            background-repeat: repeat;
        }
        
        .bill-container::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid #d4af37;
            pointer-events: none;
        }
        
        .bill-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 20px;
            border-bottom: 2px solid #1a237e;
        }
        
        .official-seal {
            position: absolute;
            right: 0;
            top: 0;
            width: 100px;
            height: 100px;
            border: 3px solid #d4af37;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #d4af37;
            font-size: 14px;
            text-align: center;
            line-height: 1.2;
            background: white;
        }
        
        .republic {
            font-size: 18px;
            font-weight: bold;
            color: #1a237e;
            margin-bottom: 5px;
        }
        
        .lgu-name {
            font-size: 28px;
            font-weight: bold;
            color: #1a237e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 10px 0;
        }
        
        .office {
            font-size: 20px;
            color: #d32f2f;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .division {
            font-size: 16px;
            color: #666;
        }
        
        .bill-title {
            text-align: center;
            font-size: 32px;
            color: #1a237e;
            font-weight: bold;
            margin: 40px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
            border: 3px double #d4af37;
            padding: 20px;
            background: #f8f9ff;
        }
        
        .bill-body {
            margin: 40px 0;
            line-height: 1.8;
            font-size: 16px;
        }
        
        .bill-number {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .bill-date {
            position: absolute;
            top: 20px;
            right: 120px;
            font-size: 14px;
            color: #666;
        }
        
        .business-info {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        
        .info-item {
            margin-bottom: 12px;
        }
        
        .info-label {
            font-weight: bold;
            color: #1a237e;
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
        }
        
        .info-value {
            color: #333;
            font-size: 16px;
            padding: 5px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .highlight-value {
            color: #1a237e;
            font-weight: bold;
            font-size: 18px;
        }
        
        .tax-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 14px;
        }
        
        .tax-table th {
            background: #1a237e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .tax-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
        }
        
        .tax-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .total-row {
            background: #1a237e !important;
            color: white;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .payment-summary {
            background: #e8f4ff;
            border: 2px solid #1a237e;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .grand-total {
            border-top: 3px double #1a237e;
            padding-top: 15px;
            font-size: 24px;
            color: #1a237e;
            font-weight: bold;
        }
        
        .discount-box {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 3px solid #28a745;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
            text-align: center;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 3px solid #ffc107;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .payment-instructions {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .instruction-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .signature-section {
            margin-top: 60px;
            text-align: center;
        }
        
        .signature-line {
            border-top: 2px solid #000;
            width: 400px;
            margin: 40px auto 10px;
            padding-top: 15px;
            text-align: center;
        }
        
        .signature-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a237e;
        }
        
        .signature-title {
            font-size: 14px;
            color: #666;
        }
        
        .footer {
            margin-top: 80px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .verification-code {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            font-family: monospace;
            letter-spacing: 1px;
            margin-top: 10px;
        }
        
        .print-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1a237e;
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #283593;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(26, 35, 126, 0.05);
            font-weight: bold;
            white-space: nowrap;
            pointer-events: none;
            z-index: 1;
        }
        
        .due-date-notice {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 3px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="bill-container">
        <!-- Watermark -->
        <div class="watermark">OFFICIAL BILL</div>
        
        <!-- Bill Number -->
        <div class="bill-number">
            <strong>BILL NO:</strong> <?php echo $bill_number; ?>
        </div>
        
        <!-- Bill Date -->
        <div class="bill-date">
            <strong>DATE:</strong> <?php echo date('F j, Y'); ?>
        </div>
        
        <!-- Official Seal -->
        <div class="official-seal">
            <div>
                OFFICIAL<br>
                SEAL<br>
                LGU
            </div>
        </div>
        
        <!-- Header -->
        <div class="bill-header">
            <div class="republic">Republic of the Philippines</div>
            <div class="lgu-name">Local Government Unit</div>
            <div class="office">OFFICE OF THE CITY TREASURER</div>
            <div class="division">Business Tax Division • Quezon City</div>
        </div>
        
        <!-- Title -->
        <div class="bill-title">
            OFFICIAL BUSINESS TAX BILL
        </div>
        
        <!-- Bill Content -->
        <div class="bill-body">
            <!-- Business Information -->
            <div class="business-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    BUSINESS INFORMATION
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Business Permit ID:</div>
                        <div class="info-value highlight-value"><?php echo htmlspecialchars($permit['applicant_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Business Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['business_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Owner Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['owner_full_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Business Nature:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['business_nature']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tax Year:</div>
                        <div class="info-value highlight-value"><?php echo $current_year; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Business Address:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($permit['business_barangay']) . ', ' . 
                                  htmlspecialchars($permit['business_city']) . ', ' . 
                                  htmlspecialchars($permit['business_province']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contact Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['contact_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['email_address'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Tax Calculation -->
            <div class="business-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    TAX CALCULATION DETAILS
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Tax Calculation Basis:</div>
                        <div class="info-value highlight-value">
                            <?php echo str_replace('_', ' ', ucfirst($permit['tax_calculation_type'] ?? 'capital_investment')); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Taxable Amount:</div>
                        <div class="info-value highlight-value">₱<?php echo number_format($permit['taxable_amount'] ?? 0, 2); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tax Rate Applied:</div>
                        <div class="info-value highlight-value"><?php echo number_format($permit['tax_rate'], 2); ?>%</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Annual Business Tax:</div>
                        <div class="info-value highlight-value">₱<?php echo number_format($permit['total_tax'] ?? 0, 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Discount Information -->
            <?php if ($eligible_for_discount && $discount_percent > 0): ?>
            <div class="discount-box">
                <h3 style="color: #155724; margin-bottom: 20px; font-size: 24px;">
                    🎯 EARLY PAYMENT DISCOUNT AVAILABLE
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 25px 0;">
                    <div>
                        <div style="font-size: 14px; color: #0c5460;">Discount Rate</div>
                        <div style="font-size: 32px; font-weight: bold; color: #28a745;"><?php echo $discount_percent; ?>%</div>
                    </div>
                    <div>
                        <div style="font-size: 14px; color: #0c5460;">Amount Saved</div>
                        <div style="font-size: 32px; font-weight: bold; color: #28a745;">-₱<?php echo number_format($discount_amount, 2); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 14px; color: #0c5460;">Discounted Total</div>
                        <div style="font-size: 32px; font-weight: bold; color: #28a745;">₱<?php echo number_format($discounted_total, 2); ?></div>
                    </div>
                </div>
                <p style="color: #0c5460; font-style: italic; margin-top: 15px;">
                    Valid for January payments only. Penalty-free payments required.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Quarterly Tax Breakdown -->
            <div class="business-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    QUARTERLY TAX BREAKDOWN (<?php echo $current_year; ?>)
                </h3>
                
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
                        <?php if (!empty($quarterly_taxes)): ?>
                            <?php foreach ($quarterly_taxes as $tax): 
                                $status = $tax['payment_status'];
                                $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                                $penalty_amount = $tax['penalty_amount'] ?? 0;
                                $total_amount = $tax_amount + $penalty_amount;
                                $due_date = new DateTime($tax['due_date']);
                                $is_overdue = ($status == 'overdue' || ($status == 'pending' && $due_date < new DateTime()));
                            ?>
                            <tr>
                                <td><strong><?php echo $tax['quarter']; ?></strong></td>
                                <td>
                                    <?php echo $due_date->format('F d, Y'); ?>
                                    <?php if ($is_overdue && $status == 'pending'): ?>
                                        <div style="color: #dc3545; font-size: 11px;">(OVERDUE)</div>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?php echo number_format($tax_amount, 2); ?></td>
                                <td>
                                    <?php if ($penalty_amount > 0): ?>
                                        <span style="color: #dc3545; font-weight: bold;">₱<?php echo number_format($penalty_amount, 2); ?></span>
                                    <?php else: ?>
                                        ₱0.00
                                    <?php endif; ?>
                                </td>
                                <td><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php echo strtoupper($status); ?>
                                    </span>
                                    <?php if ($status == 'paid' && !empty($tax['payment_date'])): ?>
                                        <div style="font-size: 11px; color: #666;">
                                            Paid: <?php echo date('M d, Y', strtotime($tax['payment_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #666; font-style: italic;">
                                    No quarterly tax records found for <?php echo $current_year; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTALS:</strong></td>
                            <td><strong>₱<?php echo number_format($quarter_totals['tax_amount'], 2); ?></strong></td>
                            <td><strong style="color: <?php echo $quarter_totals['penalty'] > 0 ? '#dc3545' : 'white'; ?>">
                                ₱<?php echo number_format($quarter_totals['penalty'], 2); ?>
                            </strong></td>
                            <td><strong>₱<?php echo number_format($quarter_totals['total'], 2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Payment Summary -->
            <div class="payment-summary">
                <h3 style="color: #1a237e; margin-bottom: 25px; text-align: center; font-size: 24px;">
                    PAYMENT SUMMARY
                </h3>
                
                <div class="payment-row">
                    <div style="font-size: 18px;">Annual Business Tax:</div>
                    <div style="font-size: 20px; font-weight: bold;">₱<?php echo number_format($permit['total_tax'] ?? 0, 2); ?></div>
                </div>
                
                <?php if ($quarter_totals['penalty'] > 0): ?>
                <div class="payment-row" style="color: #dc3545;">
                    <div style="font-size: 18px;">Late Payment Penalties:</div>
                    <div style="font-size: 20px; font-weight: bold;">+₱<?php echo number_format($quarter_totals['penalty'], 2); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($eligible_for_discount && $discount_amount > 0): ?>
                <div class="payment-row" style="color: #28a745;">
                    <div style="font-size: 18px;">Early Payment Discount (<?php echo $discount_percent; ?>%):</div>
                    <div style="font-size: 20px; font-weight: bold;">-₱<?php echo number_format($discount_amount, 2); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="payment-row grand-total">
                    <div style="font-size: 24px;">TOTAL AMOUNT DUE:</div>
                    <div style="font-size: 28px; color: #1a237e;">
                        ₱<?php echo number_format($grand_total - ($discount_amount ?? 0), 2); ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Due Date -->
            <div class="due-date-notice">
                <div style="font-size: 20px; font-weight: bold; color: #856404;">
                    ⏰ PAYMENT DUE DATE: <?php echo date('F j, Y', strtotime('+30 days')); ?>
                </div>
                <p style="color: #856404; margin-top: 10px; font-size: 16px;">