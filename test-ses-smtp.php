<?php
/**
 * AWS SES SMTP Test Script
 * 
 * This script tests the AWS SES SMTP connection and sends a test email.
 * Run from command line: php test-ses-smtp.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\SESEmailService;
use Laguna\Integration\Utils\Logger;

// Force CLI mode
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$logger = Logger::getInstance();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         AWS SES SMTP Configuration Test                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Initialize SES service
    $sesService = new SESEmailService();
    
    echo "✓ SES Service initialized\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Test connection
    echo "Testing SMTP Connection...\n";
    $connectionTest = $sesService->testConnection();
    
    echo "  Status: " . ($connectionTest['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    
    if (isset($connectionTest['service'])) {
        echo "  Service: " . $connectionTest['service'] . "\n";
    }
    
    if (isset($connectionTest['smtp_host'])) {
        echo "  SMTP Host: " . $connectionTest['smtp_host'] . "\n";
    }
    
    if (isset($connectionTest['region'])) {
        echo "  Region: " . $connectionTest['region'] . "\n";
    }
    
    if (!$connectionTest['success']) {
        echo "  Error: " . $connectionTest['error'] . "\n";
        if (isset($connectionTest['hint'])) {
            echo "  Hint: " . $connectionTest['hint'] . "\n";
        }
    }
    
    echo "\n";
    
    // Check account status
    echo "Checking Account Status...\n";
    $status = $sesService->checkAccountStatus();
    
    echo "  Status: " . ($status['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    
    if (isset($status['method'])) {
        echo "  Method: " . $status['method'] . "\n";
    }
    
    if (isset($status['quota'])) {
        echo "  Max 24-hour send: " . $status['quota']['max_24_hour_send'] . "\n";
        echo "  Sent in last 24h: " . $status['quota']['sent_last_24_hour'] . "\n";
        echo "  Percentage used: " . $status['quota']['percentage_used'] . "%\n";
    } else if (isset($status['note'])) {
        echo "  Note: " . $status['note'] . "\n";
    }
    
    if (!$status['success']) {
        echo "  Error: " . $status['error'] . "\n";
    }
    
    echo "\n";
    
    // Prompt for test email
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "Send a test email? (y/n): ";
    
    $input = trim(fgets(STDIN));
    
    if (strtolower($input) === 'y') {
        echo "\nEnter email address for test email: ";
        $testEmail = trim(fgets(STDIN));
        
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            echo "✗ Invalid email address\n";
            exit(1);
        }
        
        echo "\nSelect test type:\n";
        echo "  1) Basic\n";
        echo "  2) Order\n";
        echo "  3) Error\n";
        echo "  4) Connection\n";
        echo "Enter choice (1-4): ";
        
        $choice = trim(fgets(STDIN));
        
        $typeMap = [
            '1' => 'basic',
            '2' => 'order',
            '3' => 'error',
            '4' => 'connection'
        ];
        
        $testType = $typeMap[$choice] ?? 'basic';
        
        echo "\nSending test email to: $testEmail (type: $testType)\n";
        
        $result = $sesService->sendTestEmail($testEmail, $testType);
        
        echo "  Status: " . ($result['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "  Message ID: " . ($result['message_id'] ?? 'N/A') . "\n";
        echo "  Status Code: " . ($result['status_code'] ?? 'N/A') . "\n";
        
        if (!$result['success']) {
            echo "  Error: " . $result['error'] . "\n";
        } else {
            echo "\n✓ Test email sent successfully!\n";
            echo "Check your inbox at: $testEmail\n";
        }
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "Configuration file: .env\n";
echo "Service class: src/Services/SESEmailService.php\n";
echo "Status page: public/email-provider-config.php\n\n";
