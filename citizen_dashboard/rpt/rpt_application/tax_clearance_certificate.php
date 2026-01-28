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

// Calculate total tax paid
$total_tax_paid = 0;
foreach ($tax_payments as $tax) {
    $total_tax_paid += $tax['amount'];
}

// Prepare data
$owner_name = trim($property['first_name'] . ' ' . 
    (!empty($property['middle_name']) ? $property['middle_name'] . ' ' : '') . 
    $property['last_name'] . 
    (!empty($property['suffix']) ? ' ' . $property['suffix'] : ''));

$owner_address = buildFullAddress($property);
$issue_date = formatDate($clearance['issue_date']);
$valid_until = formatDate($clearance['valid_until'] ?? date('Y-12-31', strtotime("+1 year")));
$verification_code = strtoupper(substr(md5($clearance['certificate_number'] . $property_total_id), 0, 12));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real Property Tax Clearance Certificate</title>
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
            .certificate-container {
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
        
        .certificate-container {
            position: relative;
            width: 11in;
            min-height: 8.5in;
            background: #fff;
            border: 3px double #1a237e;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><text x="200" y="200" text-anchor="middle" dy=".3em" font-family="Georgia" font-size="30" fill="%23000" opacity="0.05">RPT CLEARANCE</text></svg>');
            background-repeat: repeat;
        }
        
        .certificate-container::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid #d4af37;
            pointer-events: none;
        }
        
        .certificate-header {
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
        
        .certificate-title {
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
        
        .certificate-body {
            margin: 40px 0;
            line-height: 1.8;
            font-size: 16px;
        }
        
        .certification-text {
            text-align: justify;
            margin-bottom: 40px;
            padding: 20px;
            border-left: 4px solid #1a237e;
            background: #f8f9ff;
        }
        
        .property-info {
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
        
        .validity-info {
            background: #e8f4ff;
            border: 2px solid #1a237e;
            border-radius: 5px;
            padding: 20px;
            margin: 30px 0;
            text-align: center;
        }
        
        .signature-section {
            margin-top: 60px;
            text-align: right;
        }
        
        .signature-line {
            border-top: 2px solid #000;
            width: 400px;
            margin: 40px auto 10px;
            padding-top: 15px;
            text-align: center;
            float: right;
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
            margin-top: 100px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            clear: both;
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
        
        .certificate-number {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <!-- Watermark -->
        <div class="watermark">RPT CLEARANCE</div>
        
        <!-- Certificate Number -->
        <div class="certificate-number">
            <strong>CERTIFICATE NO:</strong> <?php echo htmlspecialchars($clearance['certificate_number']); ?>
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
        <div class="certificate-header">
            <div class="republic">Republic of the Philippines</div>
            <div class="lgu-name">Local Government Unit</div>
            <div class="office">OFFICE OF THE CITY TREASURER</div>
            <div class="division">Real Property Tax Division • Quezon City</div>
        </div>
        
        <!-- Title -->
        <div class="certificate-title">
            REAL PROPERTY TAX CLEARANCE CERTIFICATE
        </div>
        
        <!-- Certification Text -->
        <div class="certificate-body">
            <div class="certification-text">
                <p style="font-size: 18px; line-height: 1.6; text-align: center;">
                    <strong>THIS IS TO CERTIFY THAT</strong>
                </p>
                <p style="text-align: center; font-size: 20px; color: #1a237e; margin: 20px 0; padding: 15px; background: #f8f9ff; border-radius: 5px;">
                    <strong><?php echo htmlspecialchars($owner_name); ?></strong>
                </p>
                <p style="font-size: 18px; line-height: 1.6; text-align: center;">
                    owner of the real property described below, has fully paid all Real Property Taxes for the year <strong><?php echo $year; ?></strong>.
                </p>
            </div>
            
            <!-- Property Information -->
            <div class="property-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    PROPERTY INFORMATION
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Property Reference:</div>
                        <div class="info-value"><?php echo htmlspecialchars($property['reference_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">TDN Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($land_info['tdn'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Property Type:</div>
                        <div class="info-value"><?php echo htmlspecialchars($land_info['property_type'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Land Area:</div>
                        <div class="info-value"><?php echo htmlspecialchars($land_info['land_area'] ?? '0'); ?> sqm</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Property Location:</div>
                        <div class="info-value"><?php echo htmlspecialchars($property['lot_location']); ?>, Brgy. <?php echo htmlspecialchars($property['barangay']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Owner Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($owner_name); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Owner Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars($owner_address); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Owner Contact:</div>
                        <div class="info-value"><?php echo htmlspecialchars($property['phone'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Tax Payment Details -->
            <div class="property-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    TAX PAYMENT DETAILS
                </h3>
                
                <table class="tax-table">
                    <thead>
                        <tr>
                            <th>Quarter</th>
                            <th>Year</th>
                            <th>Amount Paid</th>
                            <th>Payment Date</th>
                            <th>Receipt No.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tax_payments)): ?>
                            <?php foreach ($tax_payments as $payment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($payment['quarter']); ?></strong></td>
                                <td><?php echo htmlspecialchars($payment['year']); ?></td>
                                <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['receipt']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #666; font-style: italic;">
                                    No tax payment records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTAL TAX PAID:</strong></td>
                            <td colspan="3" style="text-align: left;">
                                <strong style="font-size: 18px;">₱<?php echo number_format($total_tax_paid, 2); ?></strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Certificate Validity -->
            <div class="validity-info">
                <h3 style="color: #1a237e; margin-bottom: 20px;">CERTIFICATE VALIDITY</h3>
                <div style="display: flex; justify-content: space-around; text-align: center;">
                    <div>
                        <div style="font-size: 14px; color: #666;">ISSUED:</div>
                        <div style="font-size: 18px; font-weight: bold; color: #1a237e;"><?php echo $issue_date; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 14px; color: #666;">VALID UNTIL:</div>
                        <div style="font-size: 18px; font-weight: bold; color: #1a237e;"><?php echo $valid_until; ?></div>
                    </div>
                </div>
                <p style="margin-top: 20px; color: #666; font-size: 14px; line-height: 1.6;">
                    This certificate is valid for one year from date of issue and may be used for 
                    property transactions, loan applications, business permits, and other legal requirements.
                </p>
            </div>
        </div>
        
        <!-- Signature -->
        <div class="signature-section">
            <div class="signature-line">
                <div class="signature-name">MARIA CRISTINA G. REYES</div>
                <div class="signature-title">City Treasurer</div>
                <div class="signature-title">Quezon City Treasury Office</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><em>This is a computer-generated document. No signature required.</em></p>
            <div class="verification-code">
                <strong>Verification Code:</strong> <?php echo $verification_code; ?>
            </div>
            <p style="margin-top: 10px;">
                <strong>Generated on:</strong> <?php echo date('F j, Y \a\t h:i:s A'); ?>
            </p>
        </div>
    </div>
    
    <button class="print-button no-print" onclick="window.print()">
        <i class="fas fa-print" style="margin-right: 10px;"></i> Print Certificate
    </button>
    
    <script>
        // Add Font Awesome for icons
        const faLink = document.createElement('link');
        faLink.rel = 'stylesheet';
        faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(faLink);
        
        // Auto-print if requested
        setTimeout(() => {
            if (window.location.search.includes('print=true')) {
                window.print();
            }
        }, 500);
        
        // Add print-specific styles
        const printStyles = `
            @media print {
                @page {
                    margin: 15mm;
                }
                body * {
                    visibility: hidden;
                }
                .certificate-container, .certificate-container * {
                    visibility: visible;
                }
                .certificate-container {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    box-shadow: none;
                    border: 3px double #000;
                }
                .no-print {
                    display: none !important;
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