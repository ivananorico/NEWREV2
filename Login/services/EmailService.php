<?php

class EmailService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    private string $logFile;

    public function __construct()
    {
        // Hardcode the API key directly
        $this->apiKey = 'PUT KEY HERE';
        
        // Path to debug log file
        $this->logFile = __DIR__ . '/email_debug.log';

        // Initialize log file if it doesn't exist
        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, "=== Email Debug Log ===\n\n");
        }

        $this->log("[INIT] API key loaded: " . (!empty($this->apiKey) ? 'YES' : 'NO'));
        $this->log("[INIT] API key length: " . strlen($this->apiKey));
        $this->log("[INIT] Server time: " . date('Y-m-d H:i:s'));
        $this->log("[INIT] PHP version: " . PHP_VERSION);
    }

    public function sendOTP(string $email, string $name, string $otpCode): bool
    {
        $this->log("[SENDOTP] Attempting to send OTP to: $email");
        $this->log("[SENDOTP] OTP Code: $otpCode");
        $this->log("[SENDOTP] Recipient Name: $name");

        $emailData = [
            'sender' => [
                'name'  => 'GoServePH',
                'email' => 'ivananorico123@gmail.com'
            ],
            'to' => [
                [
                    'email' => $email,
                    'name'  => $name
                ]
            ],
            'subject'     => 'Your GoServePH Verification Code',
            'htmlContent' => $this->getOTPEmailTemplate($name, $otpCode),
            'textContent' => "Your GoServePH verification code is: $otpCode. This code will expire in 10 minutes."
        ];

        $result = $this->sendViaBrevoAPI($emailData);

        $this->log("[SENDOTP] Result: " . ($result ? 'SUCCESS' : 'FAILED'));

        return $result;
    }

    private function sendViaBrevoAPI(array $emailData): bool
    {
        if (empty($this->apiKey)) {
            $this->log("[ERROR] Missing Brevo API key.");
            return false;
        }

        $this->log("[DEBUG] Sending request to Brevo API");
        $this->log("[DEBUG] Request URL: " . $this->apiUrl);
        $this->log("[DEBUG] Request data size: " . strlen(json_encode($emailData)) . " bytes");
        
        // Check if cURL is available
        if (!function_exists('curl_version')) {
            $this->log("[ERROR] cURL is not installed/enabled on this server");
            return false;
        }

        // Log cURL version for debugging
        $curlVersion = curl_version();
        $this->log("[DEBUG] cURL Version: " . ($curlVersion['version'] ?? 'Unknown'));
        $this->log("[DEBUG] SSL Version: " . ($curlVersion['ssl_version'] ?? 'Unknown'));

        $ch = curl_init();

        // Try different SSL settings if needed
        $sslVerifyPeer = true;
        $sslVerifyHost = 2;
        
        // For testing, you might need to disable SSL verification on some local setups
        // If connection fails, try setting these to false/0
        if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
            strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false) {
            $sslVerifyPeer = false;
            $sslVerifyHost = 0;
            $this->log("[DEBUG] Localhost detected - disabling SSL verification");
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($emailData),
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $sslVerifyPeer,
            CURLOPT_SSL_VERIFYHOST => $sslVerifyHost,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        // Add verbose output to a temporary file for debugging
        $verboseLog = tempnam(sys_get_temp_dir(), 'curl_verbose_');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, fopen($verboseLog, 'w+'));

        $this->log("[DEBUG] cURL options set. Executing request...");
        $startTime = microtime(true);
        
        $response = curl_exec($ch);
        $requestTime = microtime(true) - $startTime;
        
        // Read verbose log
        $verboseContent = file_get_contents($verboseLog);
        @unlink($verboseLog);
        
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $this->log("[CURL ERROR #$errno] $error");
            $this->log("[CURL ERROR DETAIL] Request time: {$requestTime}s");
            
            // Get detailed curl info
            $info = curl_getinfo($ch);
            $this->log("[CURL INFO] Total time: " . ($info['total_time'] ?? 'N/A') . "s");
            $this->log("[CURL INFO] Connect time: " . ($info['connect_time'] ?? 'N/A') . "s");
            $this->log("[CURL INFO] Primary IP: " . ($info['primary_ip'] ?? 'N/A'));
            $this->log("[CURL INFO] Primary port: " . ($info['primary_port'] ?? 'N/A'));
            $this->log("[CURL INFO] Local IP: " . ($info['local_ip'] ?? 'N/A'));
            $this->log("[CURL INFO] Local port: " . ($info['local_port'] ?? 'N/A'));
            
            // Log verbose output if available
            if (!empty($verboseContent)) {
                $this->log("[CURL VERBOSE] " . substr($verboseContent, 0, 1000));
            }
            
            curl_close($ch);
            
            // Try alternative approach if curl fails
            $this->log("[DEBUG] Trying alternative connection method...");
            return $this->tryAlternativeMethod($emailData);
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);
        
        curl_close($ch);

        $this->log("[API RESPONSE] HTTP Code: $httpCode");
        $this->log("[API RESPONSE] Request time: {$requestTime}s");
        $this->log("[API RESPONSE] Total time: " . ($info['total_time'] ?? 'N/A') . "s");
        $this->log("[API RESPONSE] Connect time: " . ($info['connect_time'] ?? 'N/A') . "s");
        $this->log("[API RESPONSE] Size: " . ($info['size_download'] ?? 'N/A') . " bytes");
        
        // Log response (limit length)
        if (!empty($response)) {
            $responseData = json_decode($response, true);
            if (is_array($responseData)) {
                $this->log("[API RESPONSE] Body (JSON): " . json_encode($responseData, JSON_PRETTY_PRINT));
            } else {
                $responsePreview = substr($response, 0, 1000);
                $this->log("[API RESPONSE] Body (raw): " . $responsePreview);
            }
        } else {
            $this->log("[API RESPONSE] Body: (empty)");
        }
        
        $this->log("[EMAIL] Sender: " . $emailData['sender']['email']);
        
        // Brevo returns 201 for successful email sends
        if ($httpCode === 201) {
            $this->log("[SUCCESS] Email queued successfully!");
            return true;
        } elseif ($httpCode === 401) {
            $this->log("[ERROR] Unauthorized - Invalid API key");
            return false;
        } elseif ($httpCode === 400) {
            $this->log("[ERROR] Bad request - Check your email data");
            return false;
        } else {
            $this->log("[ERROR] Unexpected HTTP code: $httpCode");
            return false;
        }
    }

    private function tryAlternativeMethod(array $emailData): bool
    {
        $this->log("[ALTERNATIVE] Trying file_get_contents method...");
        
        // Prepare the context for file_get_contents
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'api-key: ' . $this->apiKey
                ]),
                'content' => json_encode($emailData),
                'timeout' => 15,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];
        
        $context = stream_context_create($options);
        
        try {
            $startTime = microtime(true);
            $response = @file_get_contents($this->apiUrl, false, $context);
            $requestTime = microtime(true) - $startTime;
            
            if ($response === false) {
                $this->log("[ALTERNATIVE ERROR] file_get_contents failed");
                $this->log("[ALTERNATIVE ERROR] Last error: " . error_get_last()['message'] ?? 'Unknown');
                return false;
            }
            
            // Get HTTP response code
            $httpResponseCode = null;
            if (isset($http_response_header[0])) {
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
                $httpResponseCode = $matches[1] ?? null;
            }
            
            $this->log("[ALTERNATIVE RESPONSE] HTTP Code: " . ($httpResponseCode ?? 'Unknown'));
            $this->log("[ALTERNATIVE RESPONSE] Request time: {$requestTime}s");
            $this->log("[ALTERNATIVE RESPONSE] Body: " . substr($response, 0, 500));
            
            return ($httpResponseCode == 201);
            
        } catch (Exception $e) {
            $this->log("[ALTERNATIVE EXCEPTION] " . $e->getMessage());
            return false;
        }
    }

    private function getOTPEmailTemplate(string $name, string $otpCode): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
            background-color: #f9f9f9;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            letter-spacing: 5px;
            margin: 20px 0;
            padding: 15px;
            background-color: #ffffff;
            border: 2px dashed #3498db;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .header {
            color: #2c3e50;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="header">GoServePH</h1>
        <h2>Verification Code</h2>
        <p>Hello <strong>{$name}</strong>,</p>
        <p>Use the verification code below to complete your registration:</p>
        
        <div class="otp-code">{$otpCode}</div>
        
        <p>This code will expire in <strong>10 minutes</strong>.</p>
        <p>If you didn't request this code, please ignore this email.</p>
        
        <div class="footer">
            <p>Thank you,<br>The GoServePH Team</p>
            <p><small>This is an automated message, please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    // Helper function to write debug info to a file
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
    
    // Public method to test connectivity
    public function testConnection(): bool
    {
        $this->log("[TEST] Testing Brevo API connection...");
        
        $testData = [
            'sender' => [
                'name'  => 'Test',
                'email' => 'test@example.com'
            ],
            'to' => [
                [
                    'email' => 'test@example.com',
                    'name'  => 'Test User'
                ]
            ],
            'subject'     => 'Test Connection',
            'htmlContent' => '<p>Test email to check connection</p>'
        ];
        
        return $this->sendViaBrevoAPI($testData);
    }
    
    // Getter for API key (for testing)
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
    
    // Method to test basic connectivity
    public function testBasicConnectivity(): array
    {
        $results = [];
        
        // Test 1: Check cURL
        $results['curl_installed'] = function_exists('curl_version');
        if ($results['curl_installed']) {
            $curlVersion = curl_version();
            $results['curl_version'] = $curlVersion['version'] ?? 'Unknown';
            $results['ssl_support'] = !empty($curlVersion['ssl_version']);
        }
        
        // Test 2: Check if we can resolve the domain
        $results['dns_resolution'] = gethostbyname('api.brevo.com') !== 'api.brevo.com';
        
        // Test 3: Check if port 443 is open
        $fp = @fsockopen('ssl://api.brevo.com', 443, $errno, $errstr, 5);
        $results['port_443_open'] = (bool)$fp;
        if ($fp) {
            fclose($fp);
        }
        
        // Test 4: Check API key format
        $results['api_key_length'] = strlen($this->apiKey);
        $results['api_key_starts_with'] = substr($this->apiKey, 0, 7);
        
        // Log results
        foreach ($results as $key => $value) {
            $this->log("[CONNECTIVITY TEST] $key: " . (is_bool($value) ? ($value ? 'YES' : 'NO') : $value));
        }
        
        return $results;
    }
}