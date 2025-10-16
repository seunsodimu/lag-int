<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\EnhancedEmailService;

try {
    echo "Testing EnhancedEmailService...\n";
    
    // Create an instance of the service
    $emailService = new EnhancedEmailService(true); // webhook context
    
    // Check if the sendNotification method exists
    if (method_exists($emailService, 'sendNotification')) {
        echo "✅ SUCCESS: sendNotification method exists!\n";
        
        // Test the method with a sample call (won't actually send email if disabled in config)
        $result = $emailService->sendNotification(
            '3dcart',
            'TEST123',
            false, // failure
            [
                'Test' => 'This is a test notification',
                'Order ID' => 'TEST123',
                'Error' => 'Test error message'
            ],
            'This is a test error message'
        );
        
        echo "✅ Method call successful!\n";
        echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        
    } else {
        echo "❌ ERROR: sendNotification method does not exist!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}