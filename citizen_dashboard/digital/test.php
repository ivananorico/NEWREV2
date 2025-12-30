<?php
// revenue2/citizen_dashboard/digital/test.php
require_once 'config.php';

echo "<h1>Digital Payment System Test</h1>";

// Test database connection
try {
    $pdo = getDigitalDBConnection();
    if ($pdo) {
        echo "<p style='color:green'>✓ Database connected</p>";
        
        // Check table
        $stmt = $pdo->query("SHOW TABLES LIKE 'payment_transactions'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color:green'>✓ Table exists</p>";
        } else {
            echo "<p style='color:orange'>✗ Table doesn't exist</p>";
        }
    } else {
        echo "<p style='color:red'>✗ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test API endpoints
echo "<h2>API Endpoints:</h2>";
echo "<ul>";
echo "<li><a href='payment_api.php?action=check_connection' target='_blank'>Check Connection</a></li>";
echo "<li><a href='payment_api.php?action=create_table' target='_blank'>Create Table</a></li>";
echo "</ul>";

// Quick test form
?>
<h2>Quick Test:</h2>
<button onclick="testRequestOTP()">Test OTP Request</button>
<pre id="result"></pre>

<script>
async function testRequestOTP() {
    const data = {
        amount: 1000,
        purpose: 'Test Payment',
        phone: '09123456789',
        payment_method: 'gcash',
        is_annual: true,
        client_system: 'TEST'
    };
    
    const result = document.getElementById('result');
    result.innerHTML = 'Testing...';
    
    try {
        const response = await fetch('payment_api.php?action=request_otp', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const json = await response.json();
        result.innerHTML = JSON.stringify(json, null, 2);
        
        if (json.success && json.debug && json.debug.test_otp) {
            alert('Test OTP: ' + json.debug.test_otp);
        }
    } catch (error) {
        result.innerHTML = 'Error: ' + error.message;
    }
}
</script>