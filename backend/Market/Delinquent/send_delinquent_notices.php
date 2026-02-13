<?php
// backend/Market/Delinquent/send_delinquent_notices.php

// ================================================
// ERROR REPORTING - TURN OFF FOR PRODUCTION
// ================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ================================================
// CORS HEADERS
// ================================================
$allowed_origins = [
    "http://localhost:5173",
    "http://localhost:3000",
    "https://revenuetreasury.goserveph.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// ================================================
// GET POST DATA
// ================================================
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['delinquents']) || !is_array($input['delinquents'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit();
}

$delinquents = $input['delinquents'];
$template = $input['template'] ?? 'standard';
$customMessage = $input['custom_message'] ?? '';

// ================================================
// LOAD PHPMailer - USING EXACT SAME PATH AS BUSINESS MODULE
// ================================================
// Business module uses: dirname(__DIR__, 3) . '/vendor/autoload.php'
// Let's use the same for Market
$vendorAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!file_exists($vendorAutoload)) {
    // Try alternative path
    $vendorAutoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
}

if (!file_exists($vendorAutoload)) {
    echo json_encode([
        'success' => false,
        'error' => 'PHPMailer not found. Tried paths: ' . dirname(__DIR__, 3) . '/vendor/autoload.php and ' . dirname(__DIR__, 4) . '/vendor/autoload.php'
    ]);
    exit();
}

require_once $vendorAutoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ================================================
// EMAIL CONFIGURATION - Using your working credentials
// ================================================
$smtp_config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'ivananorico123@gmail.com',
    'password' => 'jsqm woro uuma pwlx',
    'from_email' => 'market@caloocan.gov.ph',
    'from_name' => 'Caloocan City Market Administration'
];

// Function to log email activity to file
function logEmailToFile($data) {
    $logDir = dirname(__DIR__, 3) . '/logs/market_email_logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/market_email_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Function to get email template for market stall rentals
function getEmailTemplate($type, $data, $customMessage = '') {
    $lgu_name = "Caloocan City Government";
    $market_contact = "market.admin@caloocan.gov.ph";
    $market_phone = "(02) 1234-5678";
    $market_address = "Caloocan City Public Market Administration Office";
    
    $amount = number_format(floatval($data['overdue_amount'] ?? 0), 2);
    $monthlyRent = number_format(floatval($data['monthly_rent'] ?? 0), 2);
    $dueDate = date('F j, Y', strtotime($data['due_date'] ?? 'now'));
    $daysLate = $data['days_overdue'] ?? 0;
    
    $businessName = $data['business_name'] ?? 'Not specified';
    $stallRightsNo = $data['stall_rights_no'] ?? 'N/A';
    $renterName = $data['full_name'] ?? 'Stall Renter';
    $stallName = $data['stall_name'] ?? 'N/A';
    $stallClass = $data['stall_class'] ?? 'N/A';
    
    // Get month name
    $monthNames = [
        '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
        '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
        '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
    ];
    $monthName = $monthNames[$data['billing_month']] ?? $data['billing_month'] ?? 'N/A';
    
    $templates = [
        'standard' => [
            'subject' => "Notice of Delinquent Stall Rental - $stallRightsNo",
            'body' => "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #4a90e2; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                        .details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
                        .amount-due { font-size: 24px; color: #dc3545; font-weight: bold; }
                        .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
                        table { width: 100%; border-collapse: collapse; }
                        td { padding: 8px; border-bottom: 1px solid #ddd; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Notice of Delinquent Stall Rental</h2>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>$renterName</strong>,</p>
                            
                            <p>This is to inform you that your stall rental for <strong>$businessName</strong> is currently delinquent:</p>
                            
                            <div class='details'>
                                <h3>Stall Details:</h3>
                                <table>
                                    <tr><td><strong>Stall Rights No:</strong></td><td>$stallRightsNo</td></tr>
                                    <tr><td><strong>Stall Name:</strong></td><td>$stallName</td></tr>
                                    <tr><td><strong>Stall Class:</strong></td><td>$stallClass</td></tr>
                                    <tr><td><strong>Business Name:</strong></td><td>$businessName</td></tr>
                                    <tr><td><strong>Billing Period:</strong></td><td>$monthName {$data['billing_year']}</td></tr>
                                    <tr><td><strong>Monthly Rent:</strong></td><td>₱$monthlyRent</td></tr>
                                    <tr><td><strong>Due Date:</strong></td><td>$dueDate</td></tr>
                                    <tr><td><strong>Days Overdue:</strong></td><td>$daysLate days</td></tr>
                                </table>
                                
                                <h3>Amount Due:</h3>
                                <table>
                                    <tr><td>Total Overdue Amount:</td><td class='amount-due'>₱$amount</td></tr>
                                </table>
                            </div>
                            
                            " . ($customMessage ? "<p><strong>Additional Message:</strong><br>$customMessage</p>" : "") . "
                            
                            <p>Please settle your account immediately to avoid further penalties and possible stall reclamation. You may pay at the Market Administration Office.</p>
                            
                            <p>Thank you for your prompt attention to this matter.</p>
                        </div>
                        <div class='footer'>
                            <p><strong>$lgu_name - Market Administration Office</strong><br>
                            $market_address<br>
                            Email: $market_contact | Phone: $market_phone<br>
                            Office Hours: Monday-Friday, 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                </body>
                </html>
            "
        ],
        'urgent' => [
            'subject' => "URGENT: Delinquent Stall Rental Notice - $stallRightsNo",
            'body' => "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #fff3f3; padding: 30px; border: 1px solid #ffcccc; }
                        .details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #dc3545; }
                        .amount-due { font-size: 24px; color: #dc3545; font-weight: bold; }
                        .warning { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
                        .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
                        table { width: 100%; border-collapse: collapse; }
                        td { padding: 8px; border-bottom: 1px solid #ddd; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>⚠️ URGENT: Delinquent Stall Rental Notice ⚠️</h2>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>$renterName</strong>,</p>
                            
                            <div class='warning'>
                                <strong>This is an URGENT NOTICE regarding your delinquent stall rental.</strong>
                                <p>Your account for <strong>$stallRightsNo</strong> is now <strong>$daysLate days overdue</strong>.</p>
                            </div>
                            
                            <div class='details'>
                                <h3>Stall Details:</h3>
                                <table>
                                    <tr><td><strong>Stall Rights No:</strong></td><td>$stallRightsNo</td></tr>
                                    <tr><td><strong>Stall Name:</strong></td><td>$stallName</td></tr>
                                    <tr><td><strong>Business Name:</strong></td><td>$businessName</td></tr>
                                    <tr><td><strong>Billing Period:</strong></td><td>$monthName {$data['billing_year']}</td></tr>
                                    <tr><td><strong>Monthly Rent:</strong></td><td>₱$monthlyRent</td></tr>
                                    <tr><td><strong>Original Due Date:</strong></td><td>$dueDate</td></tr>
                                    <tr><td><strong>Days Overdue:</strong></td><td><strong style='color: #dc3545;'>$daysLate days</strong></td></tr>
                                </table>
                                
                                <h3>Outstanding Balance:</h3>
                                <table>
                                    <tr><td>Total Overdue Amount:</td><td class='amount-due'>₱$amount</td></tr>
                                </table>
                            </div>
                            
                            <p><strong>Failure to settle this amount immediately may result in:</strong></p>
                            <ul>
                                <li>Additional daily penalties</li>
                                <li>Suspension of stall rights</li>
                                <li>Possible stall reclamation and reassignment</li>
                                <li>Legal action for collection</li>
                            </ul>
                            
                            " . ($customMessage ? "<p><strong>Additional Instructions:</strong><br>$customMessage</p>" : "") . "
                            
                            <p>Please settle your account within 5 days to avoid further escalation and possible stall loss.</p>
                        </div>
                        <div class='footer'>
                            <p><strong>$lgu_name - Market Administration Office</strong><br>
                            $market_address<br>
                            Email: $market_contact | Phone: $market_phone</p>
                        </div>
                    </div>
                </body>
                </html>
            "
        ],
        'final' => [
            'subject' => "FINAL NOTICE: Delinquent Stall Rental - Stall Reclamation Pending - $stallRightsNo",
            'body' => "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #721c24; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #f8d7da; padding: 30px; border: 1px solid #f5c6cb; }
                        .details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #721c24; }
                        .amount-due { font-size: 28px; color: #721c24; font-weight: bold; }
                        .legal-warning { background-color: #721c24; color: white; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center; }
                        .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
                        table { width: 100%; border-collapse: collapse; }
                        td { padding: 8px; border-bottom: 1px solid #ddd; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>⚖️ FINAL NOTICE - STALL RECLAMATION PENDING ⚖️</h2>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>$renterName</strong>,</p>
                            
                            <div class='legal-warning'>
                                <h3>This is your FINAL NOTICE</h3>
                                <p>Your stall rental for <strong>$stallRightsNo</strong> is now <strong>$daysLate days overdue</strong>. If payment is not received within 7 days, your stall will be reclaimed and reassigned.</p>
                            </div>
                            
                            <div class='details'>
                                <h3>Stall Details:</h3>
                                <table>
                                    <tr><td><strong>Stall Rights No:</strong></td><td>$stallRightsNo</td></tr>
                                    <tr><td><strong>Stall Name:</strong></td><td>$stallName</td></tr>
                                    <tr><td><strong>Business Name:</strong></td><td>$businessName</td></tr>
                                    <tr><td><strong>Billing Period:</strong></td><td>$monthName {$data['billing_year']}</td></tr>
                                    <tr><td><strong>Monthly Rent:</strong></td><td>₱$monthlyRent</td></tr>
                                    <tr><td><strong>Original Due Date:</strong></td><td>$dueDate</td></tr>
                                    <tr><td><strong>Days Overdue:</strong></td><td><strong style='color: #721c24;'>$daysLate days</strong></td></tr>
                                </table>
                                
                                <h3>Final Demand for Payment:</h3>
                                <table>
                                    <tr><td>Total Overdue Amount:</td><td class='amount-due'>₱$amount</td></tr>
                                </table>
                            </div>
                            
                            <p><strong>If payment is not received within 7 days, we will be compelled to:</strong></p>
                            <ul>
                                <li>Immediately reclaim your stall</li>
                                <li>Reassign the stall to a new renter</li>
                                <li>Forfeit all rights to the stall</li>
                                <li>File legal actions for collection of unpaid rentals</li>
                                <li>Blacklist you from future market stall applications</li>
                            </ul>
                            
                            " . ($customMessage ? "<p><strong>Special Instructions:</strong><br>$customMessage</p>" : "") . "
                            
                            <p>To avoid losing your stall, please settle your account immediately at the Market Administration Office.</p>
                        </div>
                        <div class='footer'>
                            <p><strong>$lgu_name - Market Administration Office</strong><br>
                            $market_address<br>
                            Email: $market_contact | Phone: $market_phone</p>
                        </div>
                    </div>
                </body>
                </html>
            "
        ]
    ];
    
    return $templates[$type] ?? $templates['standard'];
}

// Send emails
$sent_count = 0;
$failed_count = 0;
$errors = [];
$success_details = [];

foreach ($delinquents as $delinquent) {
    // Skip if no email
    if (empty($delinquent['email'])) {
        $failed_count++;
        $errors[] = "No email for {$delinquent['business_name']}";
        
        logEmailToFile([
            'status' => 'failed',
            'reason' => 'no_email',
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'stall_rights_no' => $delinquent['stall_rights_no'] ?? 'N/A',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        continue;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_config['username'];
        $mail->Password   = $smtp_config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_config['port'];
        $mail->Timeout    = 30;

        // Recipients
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addAddress($delinquent['email'], $delinquent['full_name'] ?? 'Stall Renter');
        $mail->addReplyTo($smtp_config['from_email'], 'Market Administration');

        // Content
        $template_data = getEmailTemplate($template, $delinquent, $customMessage);
        $mail->isHTML(true);
        $mail->Subject = $template_data['subject'];
        $mail->Body    = $template_data['body'];
        
        // Create plain text version
        $plainText = strip_tags(str_replace(['<br>', '</p>', '</h2>', '</h3>'], ["\n", "\n\n", "\n", "\n"], $template_data['body']));
        $mail->AltBody = $plainText;

        $mail->send();
        $sent_count++;
        
        $success_details[] = [
            'email' => $delinquent['email'],
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'stall_rights_no' => $delinquent['stall_rights_no'] ?? 'N/A',
            'amount' => $delinquent['overdue_amount'] ?? 0
        ];
        
        logEmailToFile([
            'status' => 'sent',
            'email' => $delinquent['email'],
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'stall_rights_no' => $delinquent['stall_rights_no'] ?? 'N/A',
            'template' => $template,
            'amount' => $delinquent['overdue_amount'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (Exception $e) {
        $failed_count++;
        $error_message = "Failed to send to {$delinquent['email']}: " . $mail->ErrorInfo;
        $errors[] = $error_message;
        
        logEmailToFile([
            'status' => 'failed',
            'reason' => 'smtp_error',
            'email' => $delinquent['email'],
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'stall_rights_no' => $delinquent['stall_rights_no'] ?? 'N/A',
            'error' => $mail->ErrorInfo,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Return response
echo json_encode([
    'success' => true,
    'sent_count' => $sent_count,
    'failed_count' => $failed_count,
    'errors' => $errors,
    'success_details' => $success_details,
    'total_processed' => count($delinquents),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>