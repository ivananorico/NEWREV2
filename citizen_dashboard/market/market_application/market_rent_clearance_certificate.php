<?php
// revenue2/citizen_dashboard/market/market_application/market_rent_clearance_certificate.php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Market Renter';

// Include market database (which creates global $pdo)
require_once '../../../db/Market/market_db.php';

// Check if we have global $pdo variable
if (!isset($pdo) || !$pdo) {
    die("Database connection failed");
}

// Get certificate number from query string - support multiple parameter formats
$certificate_number = null;

// Option 1: Direct certificate parameter
if (isset($_GET['certificate'])) {
    $certificate_number = $_GET['certificate'];
} 
// Option 2: Alternative parameters from approved.php
elseif (isset($_GET['rent_stall_id']) && isset($_GET['rent_total_id']) && isset($_GET['year'])) {
    try {
        $rent_stall_id = $_GET['rent_stall_id'];
        $rent_total_id = $_GET['rent_total_id'];
        $year = $_GET['year'];
        
        // Get certificate number from database using alternative parameters
        $cert_query = "
            SELECT certificate_number 
            FROM market_rent_clearances 
            WHERE rent_stall_id = :rent_stall_id 
            AND rent_total_id = :rent_total_id 
            AND clearance_year = :year
            AND EXISTS (
                SELECT 1 FROM rent_stall rs
                JOIN renter_owner ro ON rs.renter_id = ro.id
                WHERE rs.id = market_rent_clearances.rent_stall_id
                AND ro.user_id = :user_id
            )
            ORDER BY issue_date DESC 
            LIMIT 1
        ";
        
        $cert_stmt = $pdo->prepare($cert_query);
        $cert_stmt->execute([
            ':rent_stall_id' => $rent_stall_id,
            ':rent_total_id' => $rent_total_id,
            ':year' => $year,
            ':user_id' => $user_id
        ]);
        
        $cert_result = $cert_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cert_result && !empty($cert_result['certificate_number'])) {
            $certificate_number = $cert_result['certificate_number'];
        } else {
            die("Certificate not found or you don't have permission to view it.");
        }
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

if (!$certificate_number) {
    die("Certificate number is required. Please access this page from the Approved Applications section.");
}

try {
    // Get clearance certificate details - fixed address fields
    $query = "
        SELECT 
            mrc.*,
            rs.business_name,
            rs.business_type,
            rs.stall_rights_no,
            rs.monthly_rent,
            s.name as stall_name,
            s.class_id,
            sr.class_name,
            ro.first_name,
            ro.middle_name,
            ro.last_name,
            ro.suffix,
            ro.email,
            ro.mobile,
            ro.telephone,
            ro.house_number,
            ro.street,
            ro.barangay,
            ro.city,
            ro.province,
            ro.zip_code,
            ro.birth_date,
            ro.gender,
            ro.emergency_name,
            ro.emergency_contact,
            ro.renter_code,
            m.name as market_name,
            rt.id as rent_total_id,
            rt.start_date as contract_start,
            rt.end_date as contract_end
        FROM market_rent_clearances mrc
        INNER JOIN rent_stall rs ON mrc.rent_stall_id = rs.id
        INNER JOIN stalls s ON rs.stall_id = s.id
        INNER JOIN stall_rights sr ON s.class_id = sr.class_id
        INNER JOIN renter_owner ro ON rs.renter_id = ro.id
        INNER JOIN maps m ON rs.map_id = m.id
        LEFT JOIN rent_totals rt ON mrc.rent_total_id = rt.id
        WHERE mrc.certificate_number = :certificate_number
        AND ro.user_id = :user_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':certificate_number' => $certificate_number,
        ':user_id' => $user_id
    ]);
    
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        die("Certificate not found or you don't have permission to view it.");
    }
    
    // Build full address from separate fields
    $address_parts = [];
    if (!empty($certificate['house_number'])) $address_parts[] = $certificate['house_number'];
    if (!empty($certificate['street'])) $address_parts[] = $certificate['street'];
    if (!empty($certificate['barangay'])) $address_parts[] = 'Brgy. ' . $certificate['barangay'];
    if (!empty($certificate['city'])) $address_parts[] = $certificate['city'];
    if (!empty($certificate['province'])) $address_parts[] = $certificate['province'];
    if (!empty($certificate['zip_code'])) $address_parts[] = $certificate['zip_code'];
    $full_address = implode(', ', $address_parts);
    
    // Get all monthly payments for this year
    $monthly_query = "
        SELECT 
            mrb.billing_month,
            mrb.total_amount_due,
            mrb.payment_date,
            mrb.receipt_number,
            mrb.base_rent,
            mrb.penalty_amount,
            mrb.discount_amount,
            mrb.payment_status
        FROM monthly_rent_billing mrb
        WHERE mrb.rent_total_id = :rent_total_id
        AND mrb.billing_year = :year
        AND mrb.payment_status = 'paid'
        ORDER BY mrb.billing_month
    ";
    
    $monthly_stmt = $pdo->prepare($monthly_query);
    $monthly_stmt->execute([
        ':rent_total_id' => $certificate['rent_total_id'],
        ':year' => $certificate['clearance_year']
    ]);
    $monthly_payments = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total paid
    $total_paid = 0;
    $total_base_rent = 0;
    $total_penalty = 0;
    $total_discount = 0;
    foreach ($monthly_payments as $payment) {
        $total_paid += $payment['total_amount_due'];
        $total_base_rent += $payment['base_rent'];
        $total_penalty += $payment['penalty_amount'];
        $total_discount += $payment['discount_amount'];
    }
    
    // Format data for display
    $issue_date = date('F d, Y', strtotime($certificate['issue_date']));
    $valid_until = date('F d, Y', strtotime($certificate['issue_date'] . ' +1 year'));
    
    // Format renter name with suffix
    $renter_name = trim($certificate['first_name'] . ' ' . 
        (!empty($certificate['middle_name']) ? $certificate['middle_name'] . ' ' : '') . 
        $certificate['last_name'] . 
        (!empty($certificate['suffix']) ? ' ' . $certificate['suffix'] : ''));
    
    // Format birth date
    $birth_date = !empty($certificate['birth_date']) ? 
                  date('F d, Y', strtotime($certificate['birth_date'])) : 'Not specified';
    
    // Contract dates
    $contract_start = !empty($certificate['contract_start']) ? 
                      date('F d, Y', strtotime($certificate['contract_start'])) : 'Not specified';
    $contract_end = !empty($certificate['contract_end']) ? 
                    date('F d, Y', strtotime($certificate['contract_end'])) : 'Not specified';
    
    // Month names
    $month_names = [
        1 => 'First Quarter', 2 => 'Second Quarter', 3 => 'Third Quarter', 4 => 'Fourth Quarter',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Market Rent Clearance Certificate - <?php echo $certificate['certificate_number']; ?></title>
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        .certificate-container { 
            max-width: 800px; 
            margin: 0 auto; 
            border: 3px double #000; 
            padding: 40px; 
            position: relative; 
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
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
        .payment-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            font-size: 14px;
        }
        .payment-table th, .payment-table td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
        }
        .payment-table th { 
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
        .print-button:hover {
            background: #283593;
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
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 16px;
            background: #4a5568;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .back-button:hover {
            background: #2d3748;
        }
        @media print {
            .print-button, .back-button { 
                display: none; 
            }
            body { 
                margin: 0; 
                padding: 0;
                background: white;
            }
            .certificate-container {
                border: none;
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }
        }
        .address-item {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="approved.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Approved Applications
    </a>
    
    <div class="certificate-container">
        <!-- Watermark -->
        <div class="watermark">RENT CLEARANCE</div>
        
        <!-- Official Seal -->
        <div class="seal" style="text-align: center; font-size: 60px;">🏬</div>
        
        <div class="header">
            <div class="title">Republic of the Philippines</div>
            <div class="title">City of Quezon</div>
            <div class="subtitle">OFFICE OF THE MARKET ADMINISTRATOR</div>
        </div>
        
        <div class="cert-number">
            CERTIFICATE NO: <?php echo htmlspecialchars($certificate['certificate_number']); ?>
        </div>
        
        <div class="section">
            <div class="section-title">MARKET RENT CLEARANCE CERTIFICATE</div>
            <p style="font-size: 16px; line-height: 1.6; text-align: justify;">
                This is to certify that <strong><?php echo htmlspecialchars($renter_name); ?></strong> has fully paid all Market Rent obligations for the year <strong><?php echo htmlspecialchars($certificate['clearance_year']); ?></strong> for the stall described below:
            </p>
        </div>
        
        <div class="section">
            <div class="section-title">STALL INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Stall Rights No:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['stall_rights_no']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Market Location:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['market_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Stall Name:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['stall_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Stall Class:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['class_name']); ?> Class</span>
                </div>
                <div class="info-item">
                    <span class="label">Business Name:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['business_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Business Type:</span>
                    <span class="value"><?php echo !empty($certificate['business_type']) ? htmlspecialchars($certificate['business_type']) : 'Not specified'; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Monthly Rent:</span>
                    <span class="value">₱<?php echo number_format($certificate['monthly_rent'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Contract Period:</span>
                    <span class="value"><?php echo $contract_start; ?> to <?php echo $contract_end; ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">RENTER INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Full Name:</span>
                    <span class="value"><?php echo htmlspecialchars($renter_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Gender:</span>
                    <span class="value"><?php echo !empty($certificate['gender']) ? ucfirst($certificate['gender']) : 'Not specified'; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Birth Date:</span>
                    <span class="value"><?php echo $birth_date; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Mobile Number:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['mobile']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Telephone:</span>
                    <span class="value"><?php echo !empty($certificate['telephone']) ? htmlspecialchars($certificate['telephone']) : 'Not specified'; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Email Address:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['email']); ?></span>
                </div>
                <div class="info-item address-item">
                    <span class="label">Complete Address:</span>
                    <span class="value"><?php echo htmlspecialchars($full_address); ?></span>
                </div>
                <?php if (!empty($certificate['emergency_name']) && !empty($certificate['emergency_contact'])): ?>
                <div class="info-item">
                    <span class="label">Emergency Contact Person:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['emergency_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Emergency Contact:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['emergency_contact']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="label">Renter Code:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['renter_code']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">RENT PAYMENT DETAILS</div>
            <table class="payment-table">
                <tr>
                    <th>Month</th>
                    <th>Base Rent</th>
                    <th>Penalty</th>
                    <th>Discount</th>
                    <th>Total Paid</th>
                    <th>Payment Date</th>
                    <th>Receipt No.</th>
                </tr>
                <?php if (!empty($monthly_payments)): ?>
                    <?php foreach ($monthly_payments as $payment): ?>
                    <tr>
                        <td><?php echo $month_names[$payment['billing_month']] ?? 'Month ' . $payment['billing_month']; ?></td>
                        <td>₱<?php echo number_format($payment['base_rent'], 2); ?></td>
                        <td>₱<?php echo number_format($payment['penalty_amount'], 2); ?></td>
                        <td>₱<?php echo number_format($payment['discount_amount'], 2); ?></td>
                        <td>₱<?php echo number_format($payment['total_amount_due'], 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No payment records found</td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td><strong>TOTALS:</strong></td>
                    <td><strong>₱<?php echo number_format($total_base_rent, 2); ?></strong></td>
                    <td><strong>₱<?php echo number_format($total_penalty, 2); ?></strong></td>
                    <td><strong>₱<?php echo number_format($total_discount, 2); ?></strong></td>
                    <td colspan="3"><strong>₱<?php echo number_format($total_paid, 2); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">CERTIFICATE VALIDITY</div>
            <div class="validity-badge">
                <div><strong>ISSUED:</strong> <?php echo $issue_date; ?></div>
                <div><strong>VALID UNTIL:</strong> <?php echo $valid_until; ?></div>
            </div>
            <p style="text-align: center; font-size: 14px; color: #666;">
                This certificate is valid for one year from date of issue and may be used for business permit renewals, stall transfer applications, and other market-related transactions.
            </p>
        </div>
        
        <div class="section">
            <div style="margin-top: 50px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                    <div style="text-align: center;">
                        <div style="border-top: 1px solid #000; width: 250px; margin: 0 auto; padding-top: 20px;">
                            <div style="font-weight: bold; font-size: 16px;">JUAN DELA CRUZ</div>
                            <div>Market Administrator</div>
                            <div>Market Management Office</div>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="border-top: 1px solid #000; width: 250px; margin: 0 auto; padding-top: 20px;">
                            <div style="font-weight: bold; font-size: 16px;">MARIA CRISTINA G. REYES</div>
                            <div>City Treasurer</div>
                            <div>Quezon City Treasury Office</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated document. No signature required.</p>
            <p>Verification Code: <?php echo strtoupper(substr(md5($certificate['certificate_number']), 0, 12)); ?></p>
            <p>Generated on: <?php echo date('F j, Y \a\t h:i:s A'); ?></p>
        </div>
        
        <button class="print-button" onclick="window.print()">
            <i class="fas fa-print" style="margin-right: 8px;"></i> Print Certificate
        </button>
    </div>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
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