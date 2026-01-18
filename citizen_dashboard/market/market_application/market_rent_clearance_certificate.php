<?php
// revenue2/citizen_dashboard/market/market_application/market_rent_clearance_certificate.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
require_once '../../../db/Market/market_db.php';

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to format date
function formatDate($date, $format = 'F j, Y') {
    return ($date && $date != '0000-00-00' && $date != '0000-00-00 00:00:00') ? date($format, strtotime($date)) : '-';
}

// Get database connection
$pdo = getDatabaseConnection();

// Check if required parameters are provided
if (!isset($_GET['rent_stall_id']) || !isset($_GET['rent_total_id']) || !isset($_GET['year'])) {
    die("Invalid request. Missing required parameters.");
}

$rent_stall_id = intval($_GET['rent_stall_id']);
$rent_total_id = intval($_GET['rent_total_id']);
$year = intval($_GET['year']);

// Verify user owns this stall rental
$ownership_check = $pdo->prepare("
    SELECT rs.*, ro.*, rr.*, rt.*, s.name as stall_name, sr.class_name
    FROM rent_stall rs
    JOIN renter_owner ro ON rs.renter_id = ro.id
    JOIN rental_registration rr ON rs.registration_id = rr.id
    JOIN rent_totals rt ON rs.id = rt.rent_stall_id
    JOIN stalls s ON rs.stall_id = s.id
    JOIN stall_rights sr ON s.class_id = sr.class_id
    WHERE rs.id = ? AND rt.id = ? AND ro.user_id = ?
");
$ownership_check->execute([$rent_stall_id, $rent_total_id, $user_id]);
$rental = $ownership_check->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    die("Unauthorized access or stall rental not found.");
}

// Get clearance data
$clearance_query = $pdo->prepare("
    SELECT mrc.*,
           DATE_ADD(mrc.issue_date, INTERVAL 1 YEAR) as valid_until
    FROM market_rent_clearances mrc
    WHERE mrc.rent_stall_id = ? 
    AND mrc.rent_total_id = ?
    AND mrc.clearance_year = ?
    ORDER BY mrc.issue_date DESC
    LIMIT 1
");
$clearance_query->execute([$rent_stall_id, $rent_total_id, $year]);
$clearance = $clearance_query->fetch(PDO::FETCH_ASSOC);

if (!$clearance) {
    die("Rent clearance not found for the specified year.");
}

// Get rent payment details for the year
$rent_payments_stmt = $pdo->prepare("
    SELECT 
        mb.billing_month,
        mb.billing_year,
        mb.base_rent as amount,
        mb.payment_date,
        mb.receipt_number as receipt,
        CASE 
            WHEN mb.payment_status = 'paid' THEN 'Paid'
            ELSE 'Unpaid'
        END as status
    FROM monthly_rent_billing mb
    WHERE mb.rent_total_id = ? AND mb.billing_year = ?
    ORDER BY mb.billing_month
");
$rent_payments_stmt->execute([$rent_total_id, $year]);
$rent_payments = $rent_payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total paid for the year
$total_paid = 0;
foreach ($rent_payments as $payment) {
    if ($payment['status'] == 'Paid') {
        $total_paid += $payment['amount'];
    }
}

// Build full name
$full_name = trim($rental['first_name'] . ' ' . 
    (!empty($rental['middle_name']) ? $rental['middle_name'] . ' ' : '') . 
    $rental['last_name'] . 
    (!empty($rental['suffix']) ? ' ' . $rental['suffix'] : ''));

// Output the certificate HTML
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Market Rent Clearance Certificate - <?php echo $clearance['certificate_number']; ?></title>
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 0; 
            padding: 0; 
            background: white; 
        }
        .certificate-container { 
            max-width: 800px; 
            margin: 0 auto; 
            border: 3px double #000; 
            padding: 40px; 
            position: relative; 
            min-height: 1123px; /* A4 height */
            background: white;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        .title { 
            font-size: 24px; 
            font-weight: bold; 
            text-transform: uppercase; 
            color: #1a237e; 
            margin-bottom: 10px; 
        }
        .subtitle { 
            font-size: 16px; 
            color: #666; 
            margin-bottom: 20px; 
        }
        .cert-number { 
            font-size: 18px; 
            color: #d32f2f; 
            font-weight: bold; 
            text-align: center; 
            margin: 20px 0; 
            padding: 10px; 
            border: 2px solid #ccc; 
            background: #f9f9f9; 
        }
        .section { 
            margin: 25px 0; 
        }
        .section-title { 
            font-size: 18px; 
            font-weight: bold; 
            color: #1a237e; 
            border-bottom: 2px solid #1a237e; 
            padding-bottom: 8px; 
            margin-bottom: 15px; 
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
            margin-bottom: 15px; 
        }
        .info-item { 
            margin-bottom: 10px; 
        }
        .label { 
            font-weight: bold; 
            color: #555; 
            display: inline-block; 
            width: 150px; 
        }
        .value { 
            color: #333; 
        }
        .rent-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            font-size: 14px;
        }
        .rent-table th, .rent-table td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
        }
        .rent-table th { 
            background-color: #1a237e; 
            color: white; 
        }
        .total-row { 
            font-weight: bold; 
            background-color: #e8f4ff; 
        }
        .validity-badge { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 8px; 
            border: 2px solid #c3e6cb; 
            text-align: center; 
            margin: 20px 0; 
            font-size: 16px; 
        }
        .footer { 
            margin-top: 50px; 
            text-align: center; 
            font-size: 12px; 
            color: #666; 
        }
        .seal { 
            position: absolute; 
            top: 20px; 
            right: 20px; 
            width: 80px; 
            height: 80px;
            opacity: 0.2; 
        }
        .print-button { 
            display: block; 
            margin: 20px auto; 
            padding: 12px 24px; 
            background: #1a237e; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px; 
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0,0,0,0.1);
            font-weight: bold;
            white-space: nowrap;
            pointer-events: none;
            z-index: 1;
        }
        @media print {
            .print-button { 
                display: none; 
            }
            body { 
                margin: 0; 
            }
            .certificate-container {
                border: none;
                box-shadow: none;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <!-- Watermark -->
        <div class="watermark">OFFICIAL DOCUMENT</div>
        
        <!-- Official Seal -->
        <div class="seal" style="text-align: center; font-size: 60px;">⚖️</div>
        
        <div class="header">
            <div class="title">Republic of the Philippines</div>
            <div class="title">City of Quezon</div>
            <div class="subtitle">OFFICE OF THE MARKET SUPERINTENDENT</div>
        </div>
        
        <div class="cert-number">
            CERTIFICATE NO: <?php echo htmlspecialchars($clearance['certificate_number']); ?>
        </div>
        
        <div class="section">
            <div class="section-title">MARKET STALL RENT CLEARANCE CERTIFICATE</div>
            <p style="font-size: 16px; line-height: 1.6; text-align: justify;">
                This is to certify that <strong><?php echo htmlspecialchars($full_name); ?></strong>, 
                owner/operator of business at <strong><?php echo htmlspecialchars($rental['business_name'] ?? 'N/A'); ?></strong>, 
                has cleared all Market Stall Rent obligations for the year <strong><?php echo htmlspecialchars($year); ?></strong>.
            </p>
        </div>
        
        <div class="section">
            <div class="section-title">STALL RENTAL INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Stall Rights No:</span>
                    <span class="value"><?php echo htmlspecialchars($rental['stall_rights_no']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Renter Code:</span>
                    <span class="value"><?php echo htmlspecialchars($rental['renter_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Business Name:</span>
                    <span class="value"><?php echo htmlspecialchars($rental['business_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Stall Name:</span>
                    <span class="value"><?php echo htmlspecialchars($rental['stall_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Stall Class:</span>
                    <span class="value"><?php echo htmlspecialchars($rental['class_name']); ?> Class</span>
                </div>
                <div class="info-item">
                    <span class="label">Renter Name:</span>
                    <span class="value"><?php echo htmlspecialchars($full_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Monthly Rent:</span>
                    <span class="value"><?php echo formatCurrency($rental['monthly_rent'] ?? 0); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Renter Contact:</span>
                    <span class="value"><?php echo htmlspecialchars($rental['mobile'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">RENT PAYMENT DETAILS (YEAR <?php echo $year; ?>)</div>
            <table class="rent-table">
                <tr>
                    <th>Month</th>
                    <th>Year</th>
                    <th>Rent Amount</th>
                    <th>Payment Date</th>
                    <th>Receipt No.</th>
                    <th>Status</th>
                </tr>
                <?php if (!empty($rent_payments)): 
                    $month_names = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                ?>
                    <?php foreach ($rent_payments as $payment): ?>
                    <tr>
                        <td><?php echo $month_names[$payment['billing_month']] ?? $payment['billing_month']; ?></td>
                        <td><?php echo htmlspecialchars($payment['billing_year']); ?></td>
                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                        <td><?php echo htmlspecialchars($payment['receipt'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="<?php echo $payment['status'] == 'Paid' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $payment['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No rent payment records found for this year</td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="2"><strong>TOTAL RENT PAID (<?php echo $year; ?>):</strong></td>
                    <td colspan="4"><strong>₱<?php echo number_format($total_paid, 2); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">CERTIFICATE VALIDITY</div>
            <div class="validity-badge">
                <div><strong>ISSUED:</strong> <?php echo formatDate($clearance['issue_date']); ?></div>
                <div><strong>VALID UNTIL:</strong> <?php echo formatDate($clearance['valid_until'] ?? date('Y-12-31', strtotime("+1 year"))); ?></div>
            </div>
            <p style="text-align: center; font-size: 14px; color: #666;">
                This certificate is valid for one year from date of issue and may be used for stall renewal, 
                business permit applications, and other legal requirements related to market stall operations.
            </p>
        </div>
        
        <div class="section">
            <div style="margin-top: 50px; text-align: center;">
                <div style="border-top: 1px solid #000; width: 300px; margin: 0 auto; padding-top: 20px;">
                    <div style="font-weight: bold; font-size: 16px;">JUAN M. DELA CRUZ</div>
                    <div>Market Superintendent</div>
                    <div>Quezon City Market Administration Office</div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated document. No signature required.</p>
            <p>Verification Code: <?php echo strtoupper(substr(md5($clearance['certificate_number']), 0, 12)); ?></p>
            <p>Generated on: <?php echo date('F j, Y \a\t h:i:s A'); ?></p>
        </div>
        
        <button class="print-button" onclick="window.print()">
            <i class="fas fa-print" style="margin-right: 8px;"></i> Print Certificate
        </button>
    </div>
    
    <script>
        // Auto-print option (optional)
        setTimeout(function() {
            if (window.location.href.indexOf('autoprint') > -1) {
                window.print();
            }
        }, 1000);
    </script>
</body>
</html>