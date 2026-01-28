<?php
// revenue2/citizen_dashboard/market/market_application/market_rent_clearance_certificate.php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Market Renter';

// Include market database (which creates global $pdo variable)
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
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Rent Clearance Certificate</title>
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
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><text x="200" y="200" text-anchor="middle" dy=".3em" font-family="Georgia" font-size="30" fill="%23000" opacity="0.05">MARKET CLEARANCE</text></svg>');
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
        
        .stall-info {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .renter-info {
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
        
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 14px;
        }
        
        .payment-table th {
            background: #1a237e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .payment-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
        }
        
        .payment-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .total-row {
            background: #1a237e !important;
            color: white;
            font-weight: bold;
        }
        
        .total-row td {
            border-color: #1a237e;
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
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-top: 40px;
        }
        
        .signature-line {
            border-top: 2px solid #000;
            width: 300px;
            margin: 0 auto;
            padding-top: 15px;
            text-align: center;
        }
        
        .signature-name {
            font-size: 16px;
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
        
        .certificate-number {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #4a5568;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            z-index: 1000;
        }
        
        .back-button:hover {
            background: #2d3748;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="approved.php" class="back-button no-print">
        <i class="fas fa-arrow-left"></i> Back to Approved Applications
    </a>
    
    <div class="certificate-container">
        <!-- Watermark -->
        <div class="watermark">MARKET CLEARANCE</div>
        
        <!-- Certificate Number -->
        <div class="certificate-number">
            <strong>CERTIFICATE NO:</strong> <?php echo htmlspecialchars($certificate['certificate_number']); ?>
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
            <div class="office">OFFICE OF THE MARKET ADMINISTRATOR</div>
            <div class="division">Market Rent Division • Quezon City</div>
        </div>
        
        <!-- Title -->
        <div class="certificate-title">
            MARKET RENT CLEARANCE CERTIFICATE
        </div>
        
        <!-- Certification Text -->
        <div class="certificate-body">
            <div class="certification-text">
                <p style="font-size: 18px; line-height: 1.6; text-align: center;">
                    <strong>THIS IS TO CERTIFY THAT</strong>
                </p>
                <p style="text-align: center; font-size: 20px; color: #1a237e; margin: 20px 0; padding: 15px; background: #f8f9ff; border-radius: 5px;">
                    <strong><?php echo htmlspecialchars($renter_name); ?></strong>
                </p>
                <p style="font-size: 18px; line-height: 1.6; text-align: center;">
                    operating <strong><?php echo htmlspecialchars($certificate['business_name']); ?></strong> at <?php echo htmlspecialchars($certificate['market_name']); ?>,
                    has fully paid all Market Rent obligations for the year <strong><?php echo $certificate['clearance_year']; ?></strong>.
                </p>
            </div>
            
            <!-- Stall Information -->
            <div class="stall-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    STALL INFORMATION
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Stall Rights No:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['stall_rights_no']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Market Location:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['market_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Stall Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['stall_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Stall Class:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['class_name']); ?> Class</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Business Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['business_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Business Type:</div>
                        <div class="info-value"><?php echo !empty($certificate['business_type']) ? htmlspecialchars($certificate['business_type']) : 'Not specified'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Monthly Rent Rate:</div>
                        <div class="info-value">₱<?php echo number_format($certificate['monthly_rent'], 2); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contract Period:</div>
                        <div class="info-value"><?php echo $contract_start; ?> to <?php echo $contract_end; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Renter Information -->
            <div class="renter-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    RENTER INFORMATION
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($renter_name); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender:</div>
                        <div class="info-value"><?php echo !empty($certificate['gender']) ? ucfirst($certificate['gender']) : 'Not specified'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Birth Date:</div>
                        <div class="info-value"><?php echo $birth_date; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Mobile Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['mobile']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Telephone:</div>
                        <div class="info-value"><?php echo !empty($certificate['telephone']) ? htmlspecialchars($certificate['telephone']) : 'Not specified'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['email']); ?></div>
                    </div>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <div class="info-label">Complete Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars($full_address); ?></div>
                    </div>
                    <?php if (!empty($certificate['emergency_name']) && !empty($certificate['emergency_contact'])): ?>
                    <div class="info-item">
                        <div class="info-label">Emergency Contact Person:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['emergency_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Emergency Contact:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['emergency_contact']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="info-label">Renter Code:</div>
                        <div class="info-value"><?php echo htmlspecialchars($certificate['renter_code']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Rent Payment Details -->
            <div class="stall-info">
                <h3 style="color: #1a237e; border-bottom: 2px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px;">
                    RENT PAYMENT DETAILS
                </h3>
                
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Base Rent</th>
                            <th>Penalty</th>
                            <th>Discount</th>
                            <th>Total Paid</th>
                            <th>Payment Date</th>
                            <th>Receipt No.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($monthly_payments)): ?>
                            <?php foreach ($monthly_payments as $payment): ?>
                            <tr>
                                <td><strong><?php echo $month_names[$payment['billing_month']] ?? 'Month ' . $payment['billing_month']; ?></strong></td>
                                <td>₱<?php echo number_format($payment['base_rent'], 2); ?></td>
                                <td>₱<?php echo number_format($payment['penalty_amount'], 2); ?></td>
                                <td>₱<?php echo number_format($payment['discount_amount'], 2); ?></td>
                                <td>₱<?php echo number_format($payment['total_amount_due'], 2); ?></td>
                                <td><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #666; font-style: italic;">
                                    No monthly payment records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>TOTALS:</strong></td>
                            <td><strong>₱<?php echo number_format($total_base_rent, 2); ?></strong></td>
                            <td><strong>₱<?php echo number_format($total_penalty, 2); ?></strong></td>
                            <td><strong>₱<?php echo number_format($total_discount, 2); ?></strong></td>
                            <td colspan="3" style="text-align: left;">
                                <strong style="font-size: 18px;">₱<?php echo number_format($total_paid, 2); ?></strong>
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
                    </