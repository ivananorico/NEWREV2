<?php

class EmailService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.brevo.com/v3/smtp/email';

    public function __construct()
    {
        /**
         * DO NOT hardcode API keys.
         * Set BREVO_API_KEY in environment variables.
         */
        $this->apiKey = getenv('BREVO_API_KEY');

        if (empty($this->apiKey)) {
            error_log('Brevo API key is not set in environment variables.');
        }
    }

    public function sendOTP(string $email, string $name, string $otpCode): bool
    {
        error_log("Attempting to send OTP to: $email");

        $emailData = [
            'sender' => [
                'name'  => 'GoServePH',
                'email' => 'ivananorico123@gmail.com' // must be verified in Brevo
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

        return $this->sendViaBrevoAPI($emailData);
    }

    private function sendViaBrevoAPI(array $emailData): bool
    {
        if (empty($this->apiKey)) {
            error_log('Email not sent: Missing Brevo API key.');
            return false;
        }

        $ch = curl_init();

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
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true // ENABLE in production
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log('CURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        error_log("Brevo API HTTP Code: $httpCode");
        error_log("Brevo API Response: $response");

        return $httpCode === 201;
    }

    private function getOTPEmailTemplate(string $name, string $otpCode): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<body>
    <h2>GoServePH Verification Code</h2>
    <p>Hello {$name},</p>
    <p>Your verification code is: <strong>{$otpCode}</strong></p>
    <p>This code will expire in 10 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>
</body>
</html>
HTML;
    }
}
