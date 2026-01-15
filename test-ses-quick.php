<?php
/**
 * Quick AWS SES Email Test
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\SESEmailService;
use Laguna\Integration\Utils\Logger;

$logger = Logger::getInstance();

echo "\n[TEST] AWS SES Email Service\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $sesService = new SESEmailService();
    
    echo "[1] Testing connection...\n";
    $test = $sesService->testConnection();
    echo "    Status: " . ($test['success'] ? "✓ PASS" : "✗ FAIL") . "\n";
    if (!$test['success']) {
        echo "    Error: " . ($test['error'] ?? 'Unknown') . "\n";
    }
    
    echo "\n[2] Sending test email...\n";
    $result = $sesService->sendTestEmail('seun.sodimu@gmail.com', 'order');
    echo "    Status: " . ($result['success'] ? "✓ PASS" : "✗ FAIL") . "\n";
    if (isset($result['error'])) {
        echo "    Error: " . $result['error'] . "\n";
    }
    if (isset($result['note'])) {
        echo "    Note: " . $result['note'] . "\n";
    }
    if (isset($result['method'])) {
        echo "    Method: " . $result['method'] . "\n";
    }
    
    echo "\n[3] Checking logs...\n";
    $logFile = __DIR__ . '/logs/app-' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        $lastLines = array_slice(file($logFile), -5);
        foreach ($lastLines as $line) {
            echo "    " . trim($line) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
