<?php
// business_application_status/business_tax_clearance_certificate.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$business_permit_id = $_GET['business_permit_id'] ?? 0;
$year = $_GET['year'] ?? date('Y');

require_once '../../../db/Business/business_db.php';
$pdo = getDatabaseConnection();

// Fetch business permit details
$query = "SELECT * FROM business_permits WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$business_permit_id, $user_id]);
$permit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permit) {
    die("Business permit not found or access denied.");
}

// Fetch quarterly taxes for the specified year
$tax_query = "SELECT * FROM business_quarterly_taxes 
              WHERE business_permit_id = ? 
              AND year = ? 
              AND payment_status = 'paid'
              ORDER BY FIELD(quarter, 'Q1', 'Q2', 'Q3', 'Q4')";
$tax_stmt = $pdo->prepare($tax_query);
$tax_stmt->execute([$business_permit_id, $year]);
$quarterly_taxes = $tax_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if all quarters are paid
$all_quarters_paid = count($quarterly_taxes) >= 4;

// Calculate total tax paid
$total_tax_paid = 0;
foreach ($quarterly_taxes as $tax) {
    $total_tax_paid += $tax['total_quarterly_tax'];
}

// Check if tax clearance already exists
$clearance_query = "SELECT * FROM business_tax_clearances 
                    WHERE business_permit_id = ? 
                    AND clearance_year = ?
                    ORDER BY issue_date DESC 
                    LIMIT 1";
$clearance_stmt = $pdo->prepare($clearance_query);
$clearance_stmt->execute([$business_permit_id, $year]);
$existing_clearance = $clearance_stmt->fetch(PDO::FETCH_ASSOC);

// Generate new certificate if all quarters are paid and no clearance exists
if ($all_quarters_paid && !$existing_clearance) {
    $certificate_number = 'BUS-CLR-' . date('Y') . '-' . str_pad($business_permit_id, 6, '0', STR_PAD_LEFT);
    
    $insert_query = "INSERT INTO business_tax_clearances 
                     (certificate_number, business_permit_id, clearance_year, issue_date, generated_by) 
                     VALUES (?, ?, ?, CURDATE(), ?)";
    $insert_stmt = $pdo->prepare($insert_query);
    $insert_stmt->execute([$certificate_number, $business_permit_id, $year, $user_id]);
    
    // Fetch the newly created clearance
    $existing_clearance = [
        'certificate_number' => $certificate_number,
        'issue_date' => date('Y-m-d'),
        'clearance_year' => $year
    ];
}

// If no clearance exists (even after trying to create), show error
if (!$existing_clearance) {
    die("Tax clearance certificate not available. All quarterly taxes must be paid first.");
}

// Parse owner name
$owner_name_parts = explode(',', $permit['owner_full_name']);
$full_name = isset($owner_name_parts[1]) 
    ? trim($owner_name_parts[1]) . ' ' . trim($owner_name_parts[0])
    : $permit['owner_full_name'];

// Format date
$issue_date = date('F j, Y', strtotime($existing_clearance['issue_date']));
$valid_until = date('F j, Y', strtotime($existing_clearance['issue_date'] . ' +1 year'));

// Generate verification code
$verification_code = strtoupper(substr(md5($existing_clearance['certificate_number'] . $business_permit_id), 0, 12));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Tax Clearance Certificate</title>
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
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><text x="200" y="200" text-anchor="middle" dy=".3em" font-family="Georgia" font-size="30" fill="%23000" opacity="0.05">OFFICIAL CERTIFICATE</text></svg>');
            background-repeat: repeat;
        }
        
        /* Border decoration */
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
        <div class="watermark">TAX CLEARANCE</div>
        
        <!-- Certificate Number -->
        <div class="certificate-number">
            <strong>CERTIFICATE NO:</strong> <?php echo $existing_clearance['certificate_number']; ?>
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
            <div class="division">Business Tax Division • Quezon City</div>
        </div>
        
        <!-- Title -->
        <div class="certificate-title">
            BUSINESS TAX CLEARANCE CERTIFICATE
        </div>
        
        <!-- Certification Text -->
        <div class="certificate-body">
            <div class="certification-text">
                <p style="font-size: 18px; line-height: 1.6; text-align: center;">
                    <strong>THIS IS TO CERTIFY THAT</strong>
                </p>
                <p style="text-align: center; font-size: 20px; color: #1a237e; margin: 20px 0; padding: 15px; background: #f8f9ff; border-radius: 5px;">
                    <strong><?php echo htmlspecialchars($full_name); ?></strong>
                </p>
                <p style="font-size: 18px; line-height: 1.6; text-align: center;">
                    owner/proprietor of <strong><?php echo htmlspecialchars($permit['business_name']); ?></strong>,
                    has fully paid all Business Taxes for the year <strong><?php echo $year; ?></strong>.
                </p>
            </div>
            
            <!-- Business Information -->
            <div class="business-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    BUSINESS INFORMATION
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Business Permit ID:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['applicant_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Business Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['business_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Owner Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($full_name); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Business Type:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['owner_type']); ?></div>
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
                        <div class="info-label">Business Contact:</div>
                        <div class="info-value"><?php echo htmlspecialchars($permit['contact_number'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Tax Payment Details -->
            <div class="business-info">
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
                        <?php if (!empty($quarterly_taxes)): ?>
                            <?php foreach ($quarterly_taxes as $tax): ?>
                            <tr>
                                <td><strong><?php echo $tax['quarter']; ?></strong></td>
                                <td><?php echo $tax['year']; ?></td>
                                <td>₱<?php echo number_format($tax['total_quarterly_tax'], 2); ?></td>
                                <td>
                                    <?php echo $tax['payment_date'] ? date('F j, Y', strtotime($tax['payment_date'])) : 'N/A'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($tax['receipt_number'] ?? 'N/A'); ?></td>
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
                    business permit renewal, loan applications, and other legal requirements.
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