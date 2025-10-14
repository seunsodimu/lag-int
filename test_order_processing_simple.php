<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Services\NetSuiteService;
use App\Services\ThreeDCartService;

echo "Testing full order processing for order 1151949...\n\n";

try {
    // Initialize services
    $netSuiteService = new NetSuiteService();
    $threeDCartService = new ThreeDCartService();
    
    // Get the order from 3DCart
    $order = $threeDCartService->getOrder(1151949);
    echo "✓ Retrieved order from 3DCart\n";
    
    // Try to assign customer using the payment method
    $customerId = $netSuiteService->assignCustomerByPaymentMethod($order);
    echo "✓ Customer assignment successful!\n";
    echo "Customer ID: $customerId\n";
    
    if ($customerId == 105153) {
        echo "✓ PERFECT! Using existing customer instead of creating duplicate\n";
        echo "The fix is working correctly!\n";
    } else {
        echo "⚠ Warning: Different customer ID than expected (105153)\n";
        echo "Actual customer ID: $customerId\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "This indicates there may still be an issue.\n";
}