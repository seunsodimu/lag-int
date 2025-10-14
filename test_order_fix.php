<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

try {
    // Initialize services
    $logger = Logger::getInstance();
    $netSuiteService = new NetSuiteService();
    
    // Simulate the order data that was causing the issue
    $orderData = [
        'OrderID' => '1151949',
        'BillingEmail' => 'rogerb@ahturf.com',
        'BillingPhoneNumber' => '4068966619',
        'BillingCompany' => 'A & H Turf',
        'BillingFirstName' => 'Roger',
        'BillingLastName' => 'Burckley',
        'BillingAddress' => '468 South Moore',
        'BillingCity' => 'Billings',
        'BillingState' => 'MT',
        'BillingZipCode' => '59101',
        'BillingCountry' => 'US',
        'ShipmentList' => [
            [
                'ShipmentFirstName' => 'Roger',
                'ShipmentLastName' => 'Burckley',
                'ShipmentCompany' => 'A & H Turf',
                'ShipmentAddress' => '468 South Moore',
                'ShipmentCity' => 'Billings',
                'ShipmentState' => 'MT',
                'ShipmentZipCode' => '59101',
                'ShipmentCountry' => 'US',
                'ShipmentPhone' => '4068966619'
            ]
        ]
    ];
    
    echo "Testing customer assignment for order 1151949...\n\n";
    
    // Test the customer assignment logic (this should now find the existing customer)
    $customerId = $netSuiteService->findOrCreateCustomerByPaymentMethod($orderData, 'Store Shipment');
    
    if ($customerId) {
        echo "SUCCESS: Customer assignment completed!\n";
        echo "Customer ID: $customerId\n";
        echo "This should be the existing customer (105153) instead of trying to create a new one.\n";
    } else {
        echo "FAILED: Customer assignment returned null\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This might indicate the issue still exists.\n";
}