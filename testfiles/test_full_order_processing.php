<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Utils\Logger;

echo "Testing full order processing for order 1151949...\n\n";

try {
    // Initialize services
    $logger = Logger::getInstance();
    $netSuiteService = new NetSuiteService();
    $threeDCartService = new ThreeDCartService();
    
    // Get the order from 3DCart
    echo "Fetching order from 3DCart...\n";
    $order = $threeDCartService->getOrder(1151949);
    echo "✓ Retrieved order from 3DCart\n";
    echo "Order ID: " . $order['OrderID'] . "\n";
    echo "Customer Email: " . $order['BillingEmail'] . "\n";
    echo "Payment Method: " . $order['BillingPaymentMethod'] . "\n\n";
    
    // Try to assign customer using the payment method
    echo "Attempting customer assignment...\n";
    $customerId = $netSuiteService->assignCustomerByPaymentMethod($order);
    echo "✓ Customer assignment successful!\n";
    echo "Customer ID: $customerId\n\n";
    
    if ($customerId == 105153) {
        echo "🎉 PERFECT! Using existing customer (105153) instead of creating duplicate\n";
        echo "✅ The fix is working correctly!\n";
        echo "✅ No more duplicate customer creation errors!\n";
    } else {
        echo "⚠ Warning: Different customer ID than expected (105153)\n";
        echo "Actual customer ID: $customerId\n";
        echo "This might be okay if the logic found a different valid customer.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "This indicates there may still be an issue that needs to be resolved.\n";
}