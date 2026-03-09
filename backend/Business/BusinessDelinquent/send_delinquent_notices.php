<?php
// backend/Business/BusinessDelinquent/send_delinquent_notices.php

/**
 * ======================================
 * CORS (credentials-safe)
 * ======================================
 */
$allowed_origins = [
    "http://localhost:5173",
    "https://revenuetreasury.goserveph.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['delinquents']) || !is_array($input['delinquents'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit();
}

$delinquents = $input['delinquents'];
$template = $input['template'] ?? 'standard';
$customMessage = $input['custom_message'] ?? '';

// Load PHPMailer - adjust path as needed
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email configuration - REPLACE WITH YOUR ACTUAL EMAIL SETTINGS
$smtp_config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'ivananorico123@gmail.com', // Replace with your email
    'password' => 'jsqm woro uuma pwlx',     // Replace with your app password
    'from_email' => 'treasury@quezoncity.gov.ph',
    'from_name' => 'Quezon City Treasury Department - Business Tax Division'
];

// Function to log email activity to file
function logEmailToFile($data) {
    $logDir = dirname(__DIR__, 3) . '/logs/business_email_logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/business_email_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Function to get email template for business taxes
function getEmailTemplate($type, $data, $customMessage = '') {
    $lgu_name = "Quezon City Government";
    $treasury_contact = "business.tax@Quezon.gov.ph";
    $treasury_phone = "(02) 1234-5678";
    $treasury_address = "2nd Floor, City Hall Building, Quezon City - Business Tax Division";
    
    $amount = number_format(floatval($data['total_quarterly_tax'] ?? 0) + floatval($data['penalty_amount'] ?? 0), 2);
    $baseAmount = number_format(floatval($data['total_quarterly_tax'] ?? 0), 2);
    $penaltyAmount = number_format(floatval($data['penalty_amount'] ?? 0), 2);
    $dueDate = date('F j, Y', strtotime($data['due_date'] ?? 'now'));
    $daysLate = $data['days_late'] ?? 0;
    
    $businessName = $data['business_name'] ?? 'Not specified';
    $applicantId = $data['applicant_id'] ?? 'N/A';
    $ownerName = $data['owner_full_name'] ?? 'Business Owner';
    $quarter = $data['quarter'] ?? 'N/A';
    $year = $data['year'] ?? date('Y');
    $businessType = $data['business_nature'] ?? 'N/A';
    $businessAddress = $data['business_barangay'] ?? '';
    if (!empty($data['business_city'])) {
        $businessAddress .= ', ' . $data['business_city'];
    }
    
    $templates = [
        'standard' => [
            'subject' => "Notice of Delinquent Business Tax - $businessName",
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
                            <h2>Notice of Delinquent Business Tax</h2>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>$ownerName</strong>,</p>
                            
                            <p>This is to inform you that your Business Tax for <strong>$businessName</strong> is currently delinquent:</p>
                            
                            <div class='details'>
                                <h3>Business Details:</h3>
                                <table>
                                    <tr><td><strong>Business ID:</strong></td><td>$applicantId</td></tr>
                                    <tr><td><strong>Business Name:</strong></td><td>$businessName</td></tr>
                                    <tr><td><strong>Business Type:</strong></td><td>$businessType</td></tr>
                                    <tr><td><strong>Location:</strong></td><td>$businessAddress</td></tr>
                                    <tr><td><strong>Tax Period:</strong></td><td>$quarter $year</td></tr>
                                    <tr><td><strong>Due Date:</strong></td><td>$dueDate</td></tr>
                                    <tr><td><strong>Days Overdue:</strong></td><td>$daysLate days</td></tr>
                                </table>
                                
                                <h3>Amount Due:</h3>
                                <table>
                                    <tr><td>Base Tax Amount:</td><td>₱$baseAmount</td></tr>
                                    <tr><td>Penalty Incurred:</td><td>₱$penaltyAmount</td></tr>
                                    <tr><td class='amount-due'>Total Amount Due:</td><td class='amount-due'>₱$amount</td></tr>
                                </table>
                            </div>
                            
                            " . ($customMessage ? "<p><strong>Additional Message:</strong><br>$customMessage</p>" : "") . "
                            
                            <p>Please settle your account immediately to avoid further penalties and possible suspension of your business permit. You may pay at the City Treasurer's Office - Business Tax Division.</p>
                            
                            <p>Thank you for your prompt attention to this matter.</p>
                        </div>
                        <div class='footer'>
                            <p><strong>$lgu_name - Business Tax Division</strong><br>
                            $treasury_address<br>
                            Email: $treasury_contact | Phone: $treasury_phone<br>
                            Office Hours: Monday-Friday, 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                </body>
                </html>
            "
        ],
        'urgent' => [
            'subject' => "URGENT: Delinquent Business Tax Notice - $businessName",
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
                            <h2>⚠️ URGENT: Delinquent Business Tax Notice ⚠️</h2>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>$ownerName</strong>,</p>
                            
                            <div class='warning'>
                                <strong>This is an URGENT NOTICE regarding your delinquent Business Tax.</strong>
                                <p>Your account for <strong>$businessName</strong> is now <strong>$daysLate days overdue</strong>.</p>
                            </div>
                            
                            <div class='details'>
                                <h3>Business Details:</h3>
                                <table>
                                    <tr><td><strong>Business ID:</strong></td><td>$applicantId</td></tr>
                                    <tr><td><strong>Business Name:</strong></td><td>$businessName</td></tr>
                                    <tr><td><strong>Tax Period:</strong></td><td>$quarter $year</td></tr>
                                    <tr><td><strong>Original Due Date:</strong></td><td>$dueDate</td></tr>
                                    <tr><td><strong>Days Overdue:</strong></td><td><strong style='color: #dc3545;'>$daysLate days</strong></td></tr>
                                </table>
                                
                                <h3>Outstanding Balance:</h3>
                                <table>
                                    <tr><td>Base Tax:</td><td>₱$baseAmount</td></tr>
                                    <tr><td>Accrued Penalty:</td><td>₱$penaltyAmount</td></tr>
                                    <tr><td class='amount-due'>TOTAL AMOUNT DUE:</td><td class='amount-due'>₱$amount</td></tr>
                                </table>
                            </div>
                            
                            <p><strong>Failure to settle this amount immediately may result in:</strong></p>
                            <ul>
                                <li>Suspension or non-renewal of your Business Permit</li>
                                <li>Additional monthly penalties of 2%</li>
                                <li>Closure order for your business establishment</li>
                                <li>Legal action for collection</li>
                            </ul>
                            
                            " . ($customMessage ? "<p><strong>Additional Instructions:</strong><br>$customMessage</p>" : "") . "
                            
                            <p>Please settle your account within 5 days to avoid further escalation and possible business closure.</p>
                        </div>
                        <div class='footer'>
                            <p><strong>$lgu_name - Business Tax Division</strong><br>
                            $treasury_address<br>
                            Email: $treasury_contact | Phone: $treasury_phone</p>
                        </div>
                    </div>
                </body>
                </html>
            "
        ],
        'final' => [
            'subject' => "FINAL NOTICE: Delinquent Business Tax - Legal Action Pending - $businessName",
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
                            <h2>⚖️ FINAL NOTICE BEFORE LEGAL ACTION ⚖️</h2>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>$ownerName</strong>,</p>
                            
                            <div class='legal-warning'>
                                <h3>This is your FINAL NOTICE</h3>
                                <p>Your Business Tax for <strong>$businessName</strong> is now <strong>$daysLate days overdue</strong>. If payment is not received within 7 days, legal action will proceed and your business permit will be revoked.</p>
                            </div>
                            
                            <div class='details'>
                                <h3>Business Details:</h3>
                                <table>
                                    <tr><td><strong>Business ID:</strong></td><td>$applicantId</td></tr>
                                    <tr><td><strong>Business Name:</strong></td><td>$businessName</td></tr>
                                    <tr><td><strong>Tax Period:</strong></td><td>$quarter $year</td></tr>
                                    <tr><td><strong>Original Due Date:</strong></td><td>$dueDate</td></tr>
                                    <tr><td><strong>Days Overdue:</strong></td><td><strong style='color: #721c24;'>$daysLate days</strong></td></tr>
                                </table>
                                
                                <h3>Final Demand for Payment:</h3>
                                <table>
                                    <tr><td>Base Tax:</td><td>₱$baseAmount</td></tr>
                                    <tr><td>Accumulated Penalties:</td><td>₱$penaltyAmount</td></tr>
                                    <tr><td class='amount-due'>FINAL AMOUNT DUE:</td><td class='amount-due'>₱$amount</td></tr>
                                </table>
                            </div>
                            
                            <p><strong>If payment is not received within 7 days, we will be compelled to:</strong></p>
                            <ul>
                                <li>Revoke your Business Permit immediately</li>
                                <li>Issue a closure order for your business establishment</li>
                                <li>File legal actions for collection</li>
                                <li>Blacklist your business from future permits</li>
                            </ul>
                            
                            " . ($customMessage ? "<p><strong>Special Instructions:</strong><br>$customMessage</p>" : "") . "
                            
                            <p>To avoid legal action and business closure, please settle your account immediately at the City Treasurer's Office - Business Tax Division.</p>
                        </div>
                        <div class='footer'>
                            <p><strong>$lgu_name - Business Tax Division</strong><br>
                            $treasury_address<br>
                            Email: $treasury_contact | Phone: $treasury_phone</p>
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
    if (empty($delinquent['email_address'])) {
        $failed_count++;
        $errors[] = "No email for {$delinquent['business_name']}";
        
        // Log to file
        logEmailToFile([
            'status' => 'failed',
            'reason' => 'no_email',
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'applicant_id' => $delinquent['applicant_id'] ?? 'N/A',
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
        $mail->addAddress($delinquent['email_address'], $delinquent['owner_full_name'] ?? 'Business Owner');
        $mail->addReplyTo($smtp_config['from_email'], 'Business Tax Division');

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
        
        // Log success
        $success_details[] = [
            'email' => $delinquent['email_address'],
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'applicant_id' => $delinquent['applicant_id'] ?? 'N/A',
            'amount' => $delinquent['total_quarterly_tax'] ?? 0
        ];
        
        // Log to file
        logEmailToFile([
            'status' => 'sent',
            'email' => $delinquent['email_address'],
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'applicant_id' => $delinquent['applicant_id'] ?? 'N/A',
            'template' => $template,
            'amount' => $delinquent['total_quarterly_tax'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (Exception $e) {
        $failed_count++;
        $error_message = "Failed to send to {$delinquent['email_address']}: " . $mail->ErrorInfo;
        $errors[] = $error_message;
        
        // Log to file
        logEmailToFile([
            'status' => 'failed',
            'reason' => 'smtp_error',
            'email' => $delinquent['email_address'],
            'business' => $delinquent['business_name'] ?? 'Unknown',
            'applicant_id' => $delinquent['applicant_id'] ?? 'N/A',
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