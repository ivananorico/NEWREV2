<?php
// C:\xampp\htdocs\revenue2\test_market_payment.php
echo "<h2>Test Market Payment API</h2>";

// First, let's check if we can access the database directly
echo "<h3>1. Testing Database Connection</h3>";

$market_db_path = __DIR__ . '/db/Market/market_db.php';
echo "Database path: $market_db_path<br>";

if (file_exists($market_db_path)) {
    echo "✓ Database file found<br>";
    
    include_once $market_db_path;
    
    if (isset($pdo)) {
        echo "✓ \$pdo variable exists<br>";
        
        try {
            $test = $pdo->query('SELECT 1 as test');
            $result = $test->fetch(PDO::FETCH_ASSOC);
            echo "✓ Database connection successful<br>";
            
            // Check current status of registration ID 3
            $check_stmt = $pdo->prepare("SELECT id, application_status, payment_date, stall_rights_no FROM rental_registration WHERE id = 3");
            $check_stmt->execute();
            $current_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<h4>Current status of registration ID 3:</h4>";
            echo "<pre>" . print_r($current_status, true) . "</pre>";
            
            // Simulate what GCash would send
            $test_data = [
                'reference_id' => 3, // Your registration ID
                'receipt_number' => 'TEST-' . date('YmdHis'),
                'amount' => 7000.00,
                'purpose' => 'Market Stall Payment',
                'paid_at' => date('Y-m-d H:i:s'),
                'client_system' => 'market',
                'payment_method' => 'gcash',
                'payment_status' => 'paid',
                'payment_id' => 'GCASH-TEST-' . date('YmdHis')
            ];

            echo "<h3>2. Test Data to Send:</h3>";
            echo "<pre>" . print_r($test_data, true) . "</pre>";

            // Test the API
            $api_url = 'http://localhost/revenue2/citizen_dashboard/market/api/stall_rights_pay_api.php';

            echo "<h3>3. Testing API with cURL:</h3>";

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            curl_close($ch);
            
            echo "HTTP Status: $http_code<br>";
            echo "Response: <pre>" . htmlspecialchars($response) . "</pre><br>";
            
            if ($curl_error) {
                echo "cURL Error: $curl_error<br>";
            }
            
            // Check status again after test
            $check_stmt->execute();
            $new_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<h4>Status after test:</h4>";
            echo "<pre>" . print_r($new_status, true) . "</pre>";
            
            // Test direct update
            echo "<h3>4. Testing Direct Database Update:</h3>";
            
            $test_receipt = 'DIRECT-TEST-' . date('YmdHis');
            
            $direct_update = $pdo->prepare("
                UPDATE rental_registration 
                SET application_status = 'paid',
                    payment_date = NOW(),
                    reference_number = :receipt
                WHERE id = 3
            ");
            
            $direct_update->execute([':receipt' => $test_receipt]);
            $direct_rows = $direct_update->rowCount();
            
            echo "Direct update rows affected: $direct_rows<br>";
            
            // Check final status
            $check_stmt->execute();
            $final_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<h4>Final status after direct update:</h4>";
            echo "<pre>" . print_r($final_status, true) . "</pre>";
            
        } catch (PDOException $e) {
            echo "✗ Database error: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "✗ \$pdo variable NOT set after including market_db.php<br>";
    }
} else {
    echo "✗ Database file NOT found<br>";
}

echo "<hr>";

// Also test the GCash flow manually
echo "<h3>5. Manual GCash Flow Test:</h3>";
echo "<form method='post' action='citizen_dashboard/digital/index.php'>
    <input type='hidden' name='system' value='market'>
    <input type='hidden' name='ref' value='3'>
    <input type='hidden' name='amount' value='7000'>
    <input type='hidden' name='purpose' value='Market Stall Payment'>
    <input type='hidden' name='callback' value='http://localhost/revenue2/citizen_dashboard/market/api/stall_rights_pay_api.php'>
    <input type='hidden' name='data' value='{\"registration_id\":3,\"stall_rights_no\":\"STALL-20260104-8165\"}'>
    <button type='submit'>Test GCash Payment Flow</button>
</form>";

// Check XAMPP error logs location
echo "<h3>6. XAMPP Error Logs:</h3>";
echo "PHP Error Log: C:\\xampp\\php\\logs\\php_error_log<br>";
echo "Apache Error Log: C:\\xampp\\apache\\logs\\error.log<br>";
?>