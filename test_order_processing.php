<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Services\NetSuiteService;
use App\Services\ThreeDCartService;
use App\Services\DatabaseService;
use App\Services\LoggingService;

// Initialize services
$databaseService = new DatabaseService();
$loggingService = new LoggingService();
$threeDCartService = new ThreeDCartService($databaseService, $loggingService);
$netSuiteService = new NetSuiteService($databaseService, $loggingService);

echo "Testing full order processing for order 1151949...\n\n";

try {
    // Get the order from 3DCart
    $order = $threeDCartService->getOrder(1151949);
    echo "✓ Retrieved order from 3DCart\n";
    
    // Try to assign customer using the payment method
    $customerId = $netSuiteService->assignCustomerByPaymentMethod($order);
    echo "✓ Customer assignment successful!\n";
    echo "Customer ID: $customerId\n";
    
    if ($customerId == 105153) {
        echo "✓ PERFECT! Using existing customer instead of creating duplicate\n";
    } else {
        echo "⚠ Warning: Different customer ID than expected (105153)\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "This indicates the fix may not be fully applied yet.\n";
}