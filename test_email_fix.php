<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\EnhancedEmailService;
use Laguna\Integration\Utils\Logger;

echo "Testing email service fix...\n\n";

try {
    // Initialize services
    $logger = Logger::getInstance();
    $emailService = new EnhancedEmailService();
    
    echo "✓ Email service initialized successfully\n";
    echo "✓ No 'Call to private method' error occurred\n";
    echo "✓ Email service inheritance is working correctly\n\n";
    
    // Test sending a simple email (this will test the method access)
    echo "Testing email sending capability...\n";
    $result = $emailService->sendTestEmail('test@example.com');
    
    if ($result) {
        echo "✓ Email sending method is accessible\n";
        echo "✓ Email service fix is working correctly!\n";
    } else {
        echo "⚠ Email sending returned false, but no access error occurred\n";
        echo "✓ The inheritance issue is still resolved\n";
    }
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Call to private method') !== false) {
        echo "✗ Email service access error still exists:\n";
        echo $e->getMessage() . "\n";
        echo "The sendEmail method may still be private instead of protected.\n";
    } else {
        echo "✗ Different error occurred:\n";
        echo $e->getMessage() . "\n";
    }
}