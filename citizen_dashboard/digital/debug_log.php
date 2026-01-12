<?php
// revenue2/citizen_dashboard/digital/debug_log.php
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $logMessage .= " | Data: " . print_r($data, true);
        } else {
            $logMessage .= " | Data: " . $data;
        }
    }
    
    $logMessage .= PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function displayDebugInfo($title, $data) {
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        echo '<div style="background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin: 10px 0; font-family: monospace;">';
        echo '<strong style="color: #333;">' . $title . ':</strong><br>';
        echo '<pre style="font-size: 12px;">';
        print_r($data);
        echo '</pre>';
        echo '</div>';
    }
}
?>