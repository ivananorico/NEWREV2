<?php
// Correct path to autoload.php - go up 2 levels from Login/services/ to reach vendor/
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private PHPMailer $mailer;
    private string $logFile;
    private bool $debug = false; // Set to true for development

    public function __construct()
    {
        // Initialize PHPMailer
        $this->mailer = new PHPMailer(true);
        
        // Path to debug log file
        $this->logFile = __DIR__ . '/email_debug.log';

        // Initialize log file if it doesn't exist
        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, "=== Email Debug Log ===\n\n");
        }

        $this->log("[INIT] PHPMailer initialized successfully");
        $this->log("[INIT] Server time: " . date('Y-m-d H:i:s'));
        $this->log("[INIT] PHP version: " . PHP_VERSION);
        $this->log("[INIT] Autoload path: " . __DIR__ . '/../../vendor/autoload.php');
        
        // Configure mail settings
        $this->configureMailer();
    }

    /**
     * Configure PHPMailer for Gmail SMTP
     */
    private function configureMailer(): void
    {
        try {
            // Server settings
            $this->mailer->SMTPDebug = $this->debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
            $this->mailer->isSMTP();
            $this->mailer->Host       = 'smtp.gmail.com';
            $this->mailer->SMTPAuth   = true;
            
            // 🔐 IMPORTANT: Use Gmail App Password
            // Generate at: https://myaccount.google.com/apppasswords
            $this->mailer->Username   = 'ivananorico123@gmail.com';
            $this->mailer->Password   = 'jsqm woro uuma pwlx'; // Your 16-character app password
            
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = 587;
            $this->mailer->Timeout    = 30; // Timeout in seconds
            
            // For localhost testing - disable SSL verification
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Default sender
            $this->mailer->setFrom('ivananorico123@gmail.com', 'GoServePH');
            $this->mailer->addReplyTo('ivananorico123@gmail.com', 'GoServePH Support');
            
            // Default content settings
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            $this->mailer->WordWrap = 50;
            
            $this->log("[CONFIG] Mailer configured successfully for: " . $this->mailer->Username);
            
        } catch (Exception $e) {
            $this->log("[CONFIG ERROR] " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send OTP verification code via email
     */
    public function sendOTP(string $email, string $name, string $otpCode): bool
    {
        $this->log("[SENDOTP] Attempting to send OTP to: $email");
        $this->log("[SENDOTP] OTP Code: $otpCode");
        $this->log("[SENDOTP] Recipient Name: $name");

        try {
            // Reset recipients (clear previous)
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
            
            // Recipient
            $this->mailer->addAddress($email, $name);
            $this->mailer->addCC('ivananorico123@gmail.com'); // BCC to admin for tracking
            
            // Content
            $this->mailer->Subject = '🔐 GoServePH - Your Verification Code';
            $this->mailer->Body    = $this->getOTPEmailTemplate($name, $otpCode);
            $this->mailer->AltBody = $this->getOTPPlainText($name, $otpCode);

            // Add headers
            $this->mailer->addCustomHeader('X-Mailer', 'GoServePH Mailer');
            $this->mailer->addCustomHeader('X-Priority', '3');
            
            // Send email
            $startTime = microtime(true);
            $result = $this->mailer->send();
            $requestTime = microtime(true) - $startTime;
            
            $this->log("[SUCCESS] Email sent in " . round($requestTime, 2) . "s");
            $this->log("[SUCCESS] Message ID: " . $this->mailer->getLastMessageID());
            
            return true;
            
        } catch (Exception $e) {
            $this->log("[ERROR] Failed to send email: " . $this->mailer->ErrorInfo);
            $this->log("[ERROR] Exception: " . $e->getMessage());
            $this->log("[ERROR] Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Send plain text email
     */
    public function sendPlainEmail(string $to, string $name, string $subject, string $message): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = nl2br($message);
            $this->mailer->AltBody = $message;
            
            $result = $this->mailer->send();
            $this->log("[PLAIN] Email sent to: $to");
            return $result;
            
        } catch (Exception $e) {
            $this->log("[ERROR] Plain email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * HTML email template for OTP
     */
    private function getOTPEmailTemplate(string $name, string $otpCode): string
    {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoServePH Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            line-height: 1.6;
            color: #1e293b;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }
        .content {
            padding: 40px 32px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 16px;
        }
        .greeting strong {
            color: #2563eb;
        }
        .otp-container {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px dashed #2563eb;
            border-radius: 12px;
            padding: 24px;
            margin: 32px 0;
            text-align: center;
        }
        .otp-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .otp-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 48px;
            font-weight: 700;
            color: #2563eb;
            letter-spacing: 8px;
            word-break: break-all;
            background: white;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .expiry {
            margin-top: 16px;
            color: #64748b;
            font-size: 14px;
        }
        .expiry i {
            font-style: normal;
            background: #fee2e2;
            color: #dc2626;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .instructions {
            background: #f8fafc;
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }
        .instructions h3 {
            color: #1e293b;
            font-size: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .instructions ul {
            list-style: none;
            padding: 0;
        }
        .instructions li {
            padding: 8px 0;
            padding-left: 24px;
            position: relative;
            color: #475569;
        }
        .instructions li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
        .footer {
            background: #f1f5f9;
            padding: 24px 32px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .social-links {
            margin-top: 16px;
        }
        .social-links a {
            color: #64748b;
            text-decoration: none;
            margin: 0 8px;
            font-size: 14px;
        }
        .social-links a:hover {
            color: #2563eb;
        }
        @media only screen and (max-width: 600px) {
            body { padding: 20px 10px; }
            .otp-code { font-size: 32px; letter-spacing: 4px; }
            .content { padding: 24px 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>GoServePH</h1>
            <p>Your Gateway to Government Services</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Hello <strong>{$name}</strong>,
            </div>
            
            <p style="color: #475569; margin-bottom: 24px;">
                Thank you for registering with GoServePH! Please use the verification code below to complete your account setup.
            </p>
            
            <div class="otp-container">
                <div class="otp-label">Verification Code</div>
                <div class="otp-code">{$otpCode}</div>
                <div class="expiry">
                    <span>⏰ This code will expire in </span>
                    <i>10 minutes</i>
                </div>
            </div>
            
            <div class="instructions">
                <h3>📋 Next Steps:</h3>
                <ul>
                    <li>Enter this 6-digit code on the verification page</li>
                    <li>Complete your profile information</li>
                    <li>Start accessing government services online</li>
                </ul>
            </div>
            
            <p style="color: #64748b; font-size: 14px; margin-top: 32px; padding: 16px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
                <strong>⚠️ Security Notice:</strong> Never share this code with anyone. Our staff will never ask for your verification code.
            </p>
        </div>
        
        <div class="footer">
            <p>© {$year} GoServePH. All rights reserved.</p>
            <p>Government Services Management System</p>
            <p style="margin-top: 8px;">
                <small>This is an automated message, please do not reply to this email.</small>
            </p>
            <div class="social-links">
                <a href="#">Help Center</a> • 
                <a href="#">Privacy Policy</a> • 
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Plain text alternative for OTP email
     */
    private function getOTPPlainText(string $name, string $otpCode): string
    {
        return "GoServePH - Your Verification Code\n\n" .
               "Hello $name,\n\n" .
               "Thank you for registering with GoServePH! Your verification code is:\n\n" .
               "$otpCode\n\n" .
               "This code will expire in 10 minutes.\n\n" .
               "Next Steps:\n" .
               "1. Enter this 6-digit code on the verification page\n" .
               "2. Complete your profile information\n" .
               "3. Start accessing government services online\n\n" .
               "SECURITY NOTICE: Never share this code with anyone.\n\n" .
               "© " . date('Y') . " GoServePH. All rights reserved.\n" .
               "Government Services Management System\n\n" .
               "This is an automated message, please do not reply.";
    }

    /**
     * Log messages to file
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        // Also log to PHP error log if debug is enabled
        if ($this->debug) {
            error_log("EmailService: $message");
        }
    }

    /**
     * Test email configuration
     */
    public function testConnection(): array
    {
        $this->log("[TEST] Testing email configuration...");
        $result = ['success' => false, 'message' => ''];
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress('ivananorico123@gmail.com', 'Test User');
            $this->mailer->Subject = '✅ GoServePH - SMTP Test Successful';
            $this->mailer->Body    = '<h1>Test Email</h1><p>Your GoServePH email configuration is working perfectly!</p>';
            $this->mailer->AltBody = 'Your GoServePH email configuration is working perfectly!';
            
            $sent = $this->mailer->send();
            
            if ($sent) {
                $result['success'] = true;
                $result['message'] = 'Test email sent successfully!';
                $this->log("[TEST] SUCCESS - Test email sent");
            } else {
                $result['message'] = 'Failed to send test email';
                $this->log("[TEST] FAILED - Could not send");
            }
            
        } catch (Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
            $this->log("[TEST ERROR] " . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Get error info
     */
    public function getError(): string
    {
        return $this->mailer->ErrorInfo;
    }

    /**
     * Enable/disable debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
        $this->mailer->SMTPDebug = $debug ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
        $this->log("[DEBUG] Debug mode set to: " . ($debug ? 'ON' : 'OFF'));
    }

    /**
     * Get log file contents
     */
    public function getLogs(): string
    {
        if (file_exists($this->logFile)) {
            return file_get_contents($this->logFile);
        }
        return "No log file found.";
    }

    /**
     * Clear log file
     */
    public function clearLogs(): bool
    {
        return file_put_contents($this->logFile, "=== Email Debug Log ===\n\n") !== false;
    }
}