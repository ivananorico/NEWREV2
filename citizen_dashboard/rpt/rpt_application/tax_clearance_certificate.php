<?php
// revenue2/citizen_dashboard/rpt/rpt_application/tax_clearance_certificate.php
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

// Function to format date
function formatDate($date, $format = 'F j, Y') {
    return ($date && $date != '0000-00-00' && $date != '0000-00-00 00:00:00') ? date($format, strtotime($date)) : '-';
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
        $address_parts[] = $owner['barangay'];
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
    
    // If we have no specific parts, use the address field
    if (empty($address_parts) && !empty($owner['address'])) {
        return $owner['address'];
    }
    
    return implode(', ', $address_parts);
}

// Check if required parameters are provided
if (!isset($_GET['property_total_id']) || !isset($_GET['year'])) {
    die("Invalid request. Missing required parameters.");
}

$property_total_id = intval($_GET['property_total_id']);
$year = intval($_GET['year']);

// Verify user owns this property
$ownership_check = $pdo->prepare("
    SELECT pt.*, pr.*, po.*
    FROM property_totals pt
    JOIN property_registrations pr ON pt.registration_id = pr.id
    JOIN property_owners po ON pr.owner_id = po.id
    WHERE pt.id = ? AND po.user_id = ?
");
$ownership_check->execute([$property_total_id, $user_id]);
$property = $ownership_check->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    die("Unauthorized access or property not found.");
}

// Get clearance data
$clearance_query = $pdo->prepare("
    SELECT tc.*,
           DATE_ADD(tc.issue_date, INTERVAL 1 YEAR) as valid_until
    FROM tax_clearances tc
    WHERE tc.property_total_id = ? 
    AND tc.clearance_year = ?
    ORDER BY tc.issue_date DESC
    LIMIT 1
");
$clearance_query->execute([$property_total_id, $year]);
$clearance = $clearance_query->fetch(PDO::FETCH_ASSOC);

if (!$clearance) {
    die("Tax clearance not found for the specified year.");
}

// Get tax payment details
$tax_payments_stmt = $pdo->prepare("
    SELECT qt.quarter, qt.year, qt.total_quarterly_tax as amount, 
           qt.payment_date, qt.receipt_number as receipt
    FROM quarterly_taxes qt
    WHERE qt.property_total_id = ? AND qt.year = ? AND qt.payment_status = 'paid'
    ORDER BY qt.quarter
");
$tax_payments_stmt->execute([$property_total_id, $year]);
$tax_payments = $tax_payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get land info
$land_stmt = $pdo->prepare("
    SELECT lp.tdn, lp.property_type, lp.land_area_sqm as land_area
    FROM land_properties lp
    WHERE lp.registration_id = ?
    LIMIT 1
");
$land_stmt->execute([$property['registration_id']]);
$land_info = $land_stmt->fetch(PDO::FETCH_ASSOC);

// Prepare data for certificate
$owner_info = [
    'full_name' => trim($property['first_name'] . ' ' . 
        (!empty($property['middle_name']) ? $property['middle_name'] . ' ' : '') . 
        $property['last_name'] . 
        (!empty($property['suffix']) ? ' ' . $property['suffix'] : '')),
    'address' => buildFullAddress($property),
    'phone' => $property['phone'] ?? 'N/A'
];

$tax_info = [
    'tdn' => $land_info['tdn'] ?? 'N/A',
    'property_type' => $land_info['property_type'] ?? 'N/A',
    'land_area' => $land_info['land_area'] ?? '0',
    'payments' => $tax_payments,
    'total_paid' => array_sum(array_column($tax_payments, 'amount'))
];

// Output the certificate HTML
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tax Clearance Certificate - <?php echo $clearance['certificate_number']; ?></title>
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
        .tax-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            font-size: 14px;
        }
        .tax-table th, .tax-table td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
        }
        .tax-table th { 
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
            <div class="subtitle">OFFICE OF THE CITY TREASURER</div>
        </div>
        
        <div class="cert-number">
            CERTIFICATE NO: <?php echo htmlspecialchars($clearance['certificate_number']); ?>
        </div>
        
        <div class="section">
            <div class="section-title">TAX CLEARANCE CERTIFICATE</div>
            <p style="font-size: 16px; line-height: 1.6; text-align: justify;">
                This is to certify that <strong><?php echo htmlspecialchars($owner_info['full_name']); ?></strong> has fully paid all Real Property Taxes for the year <strong><?php echo htmlspecialchars($year); ?></strong> for the property described below:
            </p>
        </div>
        
        <div class="section">
            <div class="section-title">PROPERTY INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Property Reference:</span>
                    <span class="value"><?php echo htmlspecialchars($property['reference_number']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Location:</span>
                    <span class="value"><?php echo htmlspecialchars($property['lot_location']); ?>, Brgy. <?php echo htmlspecialchars($property['barangay']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">TDN Number:</span>
                    <span class="value"><?php echo htmlspecialchars($tax_info['tdn']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Property Type:</span>
                    <span class="value"><?php echo htmlspecialchars($tax_info['property_type']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Land Area:</span>
                    <span class="value"><?php echo htmlspecialchars($tax_info['land_area']); ?> sqm</span>
                </div>
                <div class="info-item">
                    <span class="label">Owner:</span>
                    <span class="value"><?php echo htmlspecialchars($owner_info['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Address:</span>
                    <span class="value"><?php echo htmlspecialchars($owner_info['address']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">TAX PAYMENT DETAILS</div>
            <table class="tax-table">
                <tr>
                    <th>Quarter</th>
                    <th>Year</th>
                    <th>Amount Paid</th>
                    <th>Payment Date</th>
                    <th>Receipt No.</th>
                </tr>
                <?php if (!empty($tax_info['payments'])): ?>
                    <?php foreach ($tax_info['payments'] as $payment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['quarter']); ?></td>
                        <td><?php echo htmlspecialchars($payment['year']); ?></td>
                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                        <td><?php echo htmlspecialchars($payment['receipt']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No payment records found</td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="2"><strong>TOTAL TAX PAID:</strong></td>
                    <td colspan="3"><strong>₱<?php echo number_format($tax_info['total_paid'], 2); ?></strong></td>
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
                This certificate is valid for one year from date of issue and may be used for property transactions, business permits, and other legal requirements.
            </p>
        </div>
        
        <div class="section">
            <div style="margin-top: 50px; text-align: center;">
                <div style="border-top: 1px solid #000; width: 300px; margin: 0 auto; padding-top: 20px;">
                    <div style="font-weight: bold; font-size: 16px;">MARIA CRISTINA G. REYES</div>
                    <div>City Treasurer</div>
                    <div>Quezon City Treasury Office</div>
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