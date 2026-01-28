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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Business Tax Bill - <?php echo $permit['applicant_id']; ?></title>
    <style>
        body { 
            font-family: 'Arial', 'Helvetica', sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .bill-container { 
            max-width: 800px; 
            margin: 0 auto; 
            border: 3px double #1a237e; 
            padding: 40px; 
            position: relative; 
            min-height: 1123px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid #1a237e;
            padding-bottom: 20px;
        }
        .title { 
            font-size: 28px; 
            font-weight: bold; 
            text-transform: uppercase; 
            color: #1a237e; 
            margin-bottom: 5px; 
            letter-spacing: 1px;
        }
        .subtitle { 
            font-size: 18px; 
            color: #d32f2f; 
            margin-bottom: 10px; 
            font-weight: bold;
        }
        .document-type { 
            font-size: 24px; 
            color: #1a237e; 
            font-weight: bold; 
            text-align: center; 
            margin: 30px 0; 
            padding: 15px; 
            border: 2px solid #1a237e; 
            background: #f8f9ff; 
            text-transform: uppercase;
        }
        .bill-number {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 14px;
            color: #666;
        }
        .date-stamp {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 14px;
            color: #666;
        }
        .section { 
            margin: 30px 0; 
        }
        .section-title { 
            font-size: 20px; 
            font-weight: bold; 
            color: #1a237e; 
            border-bottom: 2px solid #1a237e; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
            text-transform: uppercase;
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 25px; 
            margin-bottom: 20px; 
        }
        .info-item { 
            margin-bottom: 12px; 
            padding-bottom: 8px;
            border-bottom: 1px dashed #ddd;
        }
        .label { 
            font-weight: bold; 
            color: #555; 
            display: block; 
            margin-bottom: 4px;
            font-size: 14px;
        }
        .value { 
            color: #222; 
            font-size: 16px;
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
            border: 1px solid #ddd;
        }
        .tax-table th, .tax-table td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
            vertical-align: middle;
        }
        .tax-table th { 
            background-color: #1a237e; 
            color: white; 
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
        }
        .tax-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .total-row { 
            font-weight: bold; 
            background-color: #e8f4ff !important; 
            font-size: 15px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .amount-box {
            background: #f8f9ff;
            border: 2px solid #1a237e;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }
        .grand-total {
            border-top: 3px double #1a237e;
            margin-top: 15px;
            padding-top: 15px;
            font-size: 20px;
            color: #1a237e;
            font-weight: bold;
        }
        .discount-box {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .footer { 
            margin-top: 60px; 
            text-align: center; 
            font-size: 12px; 
            color: #666; 
            border-top: 2px dashed #ccc;
            padding-top: 20px;
        }
        .official-seal {
            position: absolute;
            bottom: 100px;
            right: 60px;
            width: 120px;
            height: 120px;
            border: 3px solid #d32f2f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #d32f2f;
            opacity: 0.8;
            text-align: center;
            font-size: 14px;
            line-height: 1.3;
        }
        .signature-box {
            margin-top: 60px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 300px;
            margin: 40px auto 10px;
            padding-top: 20px;
        }
        .print-button { 
            display: block; 
            margin: 30px auto; 
            padding: 15px 30px; 
            background: #1a237e; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: bold;
        }
        .print-button:hover {
            background: #283593;
        }
        @media print {
            .print-button { 
                display: none; 
            }
            body { 
                margin: 0;
                padding: 0;
                background: white;
            }
            .bill-container {
                border: 3px double #000;
                box-shadow: none;
                max-width: 100%;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="bill-container">
        <!-- Watermark -->
        <div class="watermark">OFFICIAL BILL</div>
        
        <!-- Official Seal -->
        <div class="official-seal">
            <div>
                <div>OFFICIAL</div>
                <div>TAX BILL</div>
                <div>LGU</div>
            </div>
        </div>
        
        <!-- Bill Header -->
        <div class="header">
            <div class="bill-number">
                <strong>BILL NO:</strong> <?php echo $bill_number; ?>
            </div>
            <div class="date-stamp">
                <strong>DATE:</strong> <?php echo date('F j, Y'); ?>
            </div>
            
            <div class="title">Republic of the Philippines</div>
            <div class="title">Local Government Unit</div>
            <div class="subtitle">OFFICE OF THE CITY TREASURER</div>
            <div style="color: #666; font-size: 16px; margin-top: 10px;">
                Business Tax Division • Quezon City
            </div>
        </div>
        
        <div class="document-type">
            OFFICIAL BUSINESS TAX BILL
        </div>
        
        <!-- Business Information -->
        <div class="section">
            <div class="section-title">Business Information</div>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <div class="label">Business Permit ID:</div>
                        <div class="value highlight-value"><?php echo htmlspecialchars($permit['applicant_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Business Name:</div>
                        <div class="value"><?php echo htmlspecialchars($permit['business_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Owner Name:</div>
                        <div class="value"><?php echo htmlspecialchars($permit['owner_full_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Business Nature:</div>
                        <div class="value"><?php echo htmlspecialchars($permit['business_nature']); ?></div>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <div class="label">Tax Year:</div>
                        <div class="value highlight-value"><?php echo $current_year; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Business Address:</div>
                        <div class="value">
                            <?php echo htmlspecialchars($permit['business_barangay']) . ', ' . 
                                  htmlspecialchars($permit['business_city']) . ', ' . 
                                  htmlspecialchars($permit['business_province']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label">Contact Number:</div>
                        <div class="value"><?php echo htmlspecialchars($permit['contact_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Email Address:</div>
                        <div class="value"><?php echo htmlspecialchars($permit['email_address'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tax Calculation -->
        <div class="section">
            <div class="section-title">Tax Calculation Details</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Tax Calculation Basis:</div>
                    <div class="value highlight-value">
                        <?php echo str_replace('_', ' ', ucfirst($permit['tax_calculation_type'] ?? 'capital_investment')); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Taxable Amount:</div>
                    <div class="value highlight-value">₱<?php echo number_format($permit['taxable_amount'] ?? 0, 2); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Tax Rate Applied:</div>
                    <div class="value highlight-value"><?php echo number_format($permit['tax_rate'], 2); ?>%</div>
                </div>
                <div class="info-item">
                    <div class="label">Annual Business Tax:</div>
                    <div class="value highlight-value">₱<?php echo number_format($permit['total_tax'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Discount Information -->
        <?php if ($eligible_for_discount && $discount_percent > 0): ?>
        <div class="section">
            <div class="discount-box">
                <div style="text-align: center; margin-bottom: 15px;">
                    <div style="font-size: 20px; font-weight: bold; color: #155724;">EARLY PAYMENT DISCOUNT AVAILABLE</div>
                    <div style="color: #0c5460; margin-top: 5px;">Valid for January payments only</div>
                </div>
                <div style="display: flex; justify-content: space-around; text-align: center;">
                    <div>
                        <div style="font-size: 14px; color: #555;">Discount Rate</div>
                        <div style="font-size: 28px; font-weight: bold; color: #28a745;"><?php echo $discount_percent; ?>%</div>
                    </div>
                    <div>
                        <div style="font-size: 14px; color: #555;">Amount Saved</div>
                        <div style="font-size: 28px; font-weight: bold; color: #28a745;">-₱<?php echo number_format($discount_amount, 2); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 14px; color: #555;">Discounted Total</div>
                        <div style="font-size: 28px; font-weight: bold; color: #28a745;">₱<?php echo number_format($discounted_total, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quarterly Tax Breakdown -->
        <div class="section">
            <div class="section-title">Quarterly Tax Breakdown (<?php echo $current_year; ?>)</div>
            
            <?php if (!empty($quarterly_taxes)): ?>
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
                            $status = $tax['payment_status'];
                            $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                            $penalty_amount = $tax['penalty_amount'] ?? 0;
                            $total_amount = $tax_amount + $penalty_amount;
                            $due_date = new DateTime($tax['due_date']);
                            $is_overdue = ($status == 'overdue' || ($status == 'pending' && $due_date < new DateTime()));
                        ?>
                        <tr>
                            <td><strong><?php echo $tax['quarter']; ?></strong></td>
                            <td><?php echo $due_date->format('M d, Y'); ?>
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
                                        <?php echo date('M d, Y', strtotime($tax['payment_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTALS:</strong></td>
                            <td><strong>₱<?php echo number_format($quarter_totals['tax_amount'], 2); ?></strong></td>
                            <td><strong style="color: <?php echo $quarter_totals['penalty'] > 0 ? '#dc3545' : '#666'; ?>">
                                ₱<?php echo number_format($quarter_totals['penalty'], 2); ?>
                            </strong></td>
                            <td><strong style="color: #1a237e;">₱<?php echo number_format($quarter_totals['total'], 2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #666; font-style: italic;">
                    No quarterly tax records found for <?php echo $current_year; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Payment Summary -->
        <div class="section">
            <div class="section-title">Payment Summary</div>
            
            <div class="amount-box">
                <div class="amount-row">
                    <div style="font-size: 16px;">Annual Business Tax:</div>
                    <div style="font-size: 18px; font-weight: bold;">₱<?php echo number_format($permit['total_tax'] ?? 0, 2); ?></div>
                </div>
                
                <?php if ($quarter_totals['penalty'] > 0): ?>
                <div class="amount-row" style="color: #dc3545;">
                    <div style="font-size: 16px;">Late Payment Penalties:</div>
                    <div style="font-size: 18px; font-weight: bold;">+₱<?php echo number_format($quarter_totals['penalty'], 2); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($eligible_for_discount && $discount_amount > 0): ?>
                <div class="amount-row" style="color: #28a745;">
                    <div style="font-size: 16px;">Early Payment Discount (<?php echo $discount_percent; ?>%):</div>
                    <div style="font-size: 18px; font-weight: bold;">-₱<?php echo number_format($discount_amount, 2); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="amount-row grand-total">
                    <div style="font-size: 20px;">TOTAL AMOUNT DUE:</div>
                    <div style="font-size: 24px; color: #1a237e;">
                        ₱<?php echo number_format($grand_total - ($discount_amount ?? 0), 2); ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Due Date -->
            <div style="text-align: center; margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">
                <div style="font-size: 16px; font-weight: bold; color: #856404;">
                    <i class="fas fa-calendar-alt"></i> PAYMENT DUE DATE: 
                    <?php echo date('F j, Y', strtotime('+30 days')); ?>
                </div>
                <div style="color: #856404; margin-top: 5px;">
                    Please settle payment within 30 days to avoid additional penalties
                </div>
            </div>
        </div>
        
        <!-- Important Notes -->
        <?php if ($quarter_totals['penalty'] > 0): ?>
        <div class="section">
            <div class="warning-box">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <div style="font-size: 24px; color: #856404; margin-right: 15px;">⚠️</div>
                    <div style="font-size: 18px; font-weight: bold; color: #856404;">
                        IMPORTANT NOTICE
                    </div>
                </div>
                <div style="color: #856404; line-height: 1.6;">
                    <strong>Attention:</strong> Your account has accumulated late payment penalties. 
                    Failure to settle the outstanding amount may result in:
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <li>Additional interest charges</li>
                        <li>Suspension of business operations</li>
                        <li>Legal action for tax delinquency</li>
                        <li>Denial of business permit renewal</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment Instructions -->
        <div class="section">
            <div class="section-title">Payment Instructions</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div style="font-weight: bold; color: #1a237e; margin-bottom: 10px;">Accepted Payment Methods:</div>
                    <ul style="color: #555; line-height: 1.8;">
                        <li>Over-the-counter at City Treasurer's Office</li>
                        <li>GCash / PayMaya / Online Banking</li>
                        <li>Bank Transfer (BPI, BDO, Metrobank)</li>
                        <li>Credit/Debit Card Payment</li>
                    </ul>
                </div>
                <div>
                    <div style="font-weight: bold; color: #1a237e; margin-bottom: 10px;">Required for Payment:</div>
                    <ul style="color: #555; line-height: 1.8;">
                        <li>This official tax bill (printed copy)</li>
                        <li>Valid government ID</li>
                        <li>Business permit (original)</li>
                        <li>Previous receipt (if applicable)</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Signature & Footer -->
        <div class="signature-box">
            <div class="signature-line">
                <div style="font-weight: bold; font-size: 16px;">MARIA CRISTINA G. REYES</div>
                <div>City Treasurer</div>
                <div>Quezon City Treasury Office</div>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>THIS IS AN OFFICIAL TAX BILL - PLEASE RETAIN FOR YOUR RECORDS</strong></p>
            <p>For inquiries: Business Tax Division | Tel: (02) 1234-5678 | Email: business.tax@goserveph.gov.ph</p>
            <p>Business Hours: Monday-Friday, 8:00 AM - 5:00 PM</p>
            <p style="margin-top: 20px; color: #999; font-size: 11px;">
                Document ID: <?php echo $bill_number; ?> | Generated on: <?php echo date('F j, Y \a\t h:i:s A'); ?> | 
                Verification Code: <?php echo strtoupper(substr(md5($bill_number), 0, 12)); ?>
            </p>
        </div>
        
        <button class="print-button" onclick="window.print()">
            <i class="fas fa-print" style="margin-right: 10px;"></i> PRINT THIS BILL
        </button>
    </div>
    
    <script>
        // Auto-print option
        setTimeout(function() {
            if (window.location.href.indexOf('autoprint') > -1) {
                window.print();
            }
        }, 1000);
        
        // Add print styles dynamically
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                @page {
                    margin: 15mm;
                }
                body {
                    margin: 0;
                    padding: 0;
                }
                .bill-container {
                    border: 3px double #000 !important;
                    box-shadow: none !important;
                    margin: 0 !important;
                    padding: 20px !important;
                }
                .print-button {
                    display: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>