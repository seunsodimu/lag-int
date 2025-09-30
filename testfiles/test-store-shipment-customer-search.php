<?php
/**
 * Test script for Store Shipment customer search enhancement
 * Tests the new findCustomerByCompanyNameAndParent method
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

// Create NetSuite service
$netSuiteService = new NetSuiteService();
$logger = Logger::getInstance();

echo "<h1>Store Shipment Customer Search Enhancement Test</h1>\n";

// Test data simulating the failing order from the log
$storeShipmentOrderData = [
    'OrderID' => 'TEST_STORE_SHIPMENT_' . time(),
    'BillingPaymentMethod' => 'Store Shipment',
    'BillingEmail' => 'rocklerpurchasing@rockler.com',
    'BillingPhoneNumber' => '(763) 478-8201',
    'BillingCompany' => 'Rockler Corp.',
    'BillingFirstName' => 'Rockler',
    'BillingLastName' => 'Corp.',
    'BillingAddress' => '4365 Willow Dr.',
    'BillingAddress2' => 'Ste 500',
    'BillingCity' => 'Medina',
    'BillingState' => 'MN',
    'BillingZipCode' => '55340',
    'BillingCountry' => 'US',
    'QuestionList' => [
        [
            'QuestionID' => 1,
            'QuestionTitle' => 'Customer Email',
            'QuestionAnswer' => 'store53mgr@rockler.com'
        ]
    ],
    'ShipmentList' => [
        [
            'ShipmentID' => 0,
            'ShipmentFirstName' => 'Rockler Taylorsville',
            'ShipmentLastName' => '53',
            'ShipmentCompany' => '',
            'ShipmentAddress' => '5584 S. Redwood Rd',
            'ShipmentAddress2' => '',
            'ShipmentCity' => 'Taylorsville',
            'ShipmentState' => 'UT',
            'ShipmentZipCode' => '84123',
            'ShipmentCountry' => 'US',
            'ShipmentPhone' => '385-276-4676',
            'ShipmentEmail' => ''
        ]
    ]
];

echo "<h2>Test 1: Parent Company Customer Search</h2>\n";

// Use reflection to access private methods
$reflection = new ReflectionClass($netSuiteService);

// Test finding parent company customer
$method = $reflection->getMethod('findParentCompanyCustomer');
$method->setAccessible(true);
$parentCustomer = $method->invoke($netSuiteService, $storeShipmentOrderData);

echo "<h3>Parent Company Customer Search Result:</h3>\n";
if ($parentCustomer) {
    echo "<p><strong>Found Parent Customer:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>ID: " . ($parentCustomer['id'] ?? 'N/A') . "</li>\n";
    echo "<li>Company Name: " . ($parentCustomer['companyName'] ?? 'N/A') . "</li>\n";
    echo "<li>Email: " . ($parentCustomer['email'] ?? 'N/A') . "</li>\n";
    echo "<li>Phone: " . ($parentCustomer['phone'] ?? 'N/A') . "</li>\n";
    echo "</ul>\n";
    
    $parentCustomerId = $parentCustomer['id'];
    
    echo "<h2>Test 2: Company Name and Parent Search (New Enhancement)</h2>\n";
    
    // Test the new findCustomerByCompanyNameAndParent method
    $method = $reflection->getMethod('findCustomerByCompanyNameAndParent');
    $method->setAccessible(true);
    $existingCustomer = $method->invoke($netSuiteService, $storeShipmentOrderData, $parentCustomerId);
    
    echo "<h3>Company Name and Parent Search Result:</h3>\n";
    if ($existingCustomer) {
        echo "<p><strong>Found Existing Customer by Company Name:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>ID: " . ($existingCustomer['id'] ?? 'N/A') . "</li>\n";
        echo "<li>Company Name: " . ($existingCustomer['companyName'] ?? 'N/A') . "</li>\n";
        echo "<li>Email: " . ($existingCustomer['email'] ?? 'N/A') . "</li>\n";
        echo "<li>Phone: " . ($existingCustomer['phone'] ?? 'N/A') . "</li>\n";
        echo "<li>Is Person: " . ($existingCustomer['isperson'] ?? 'N/A') . "</li>\n";
        echo "</ul>\n";
        
        echo "<p><strong style='color: green;'>✅ SUCCESS: Found existing customer, should prevent duplicate creation!</strong></p>\n";
    } else {
        echo "<p><strong style='color: orange;'>⚠️ No existing customer found by company name and parent.</strong></p>\n";
        echo "<p>This means a new customer would be created (which might be correct if none exists).</p>\n";
    }
    
    echo "<h2>Test 3: Company Name Construction Logic</h2>\n";
    
    // Test how company name is constructed
    $shipment = $storeShipmentOrderData['ShipmentList'][0];
    $billingCompany = $storeShipmentOrderData['BillingCompany'] ?? '';
    $shipmentCompanyName = trim(($shipment['ShipmentFirstName'] ?? '') . ' ' . ($shipment['ShipmentLastName'] ?? ''));
    
    echo "<h3>Company Name Construction:</h3>\n";
    echo "<ul>\n";
    echo "<li>BillingCompany: '" . $billingCompany . "'</li>\n";
    echo "<li>ShipmentFirstName + ShipmentLastName: '" . $shipmentCompanyName . "'</li>\n";
    echo "<li>Final Company Name Used: '" . (!empty($billingCompany) ? $billingCompany : $shipmentCompanyName) . "'</li>\n";
    echo "</ul>\n";
    
    echo "<h2>Test 4: Full Customer Assignment Flow</h2>\n";
    
    // Test the complete customer assignment process
    try {
        $customerId = $netSuiteService->findOrCreateCustomerByPaymentMethod($storeShipmentOrderData);
        echo "<p><strong style='color: green;'>✅ SUCCESS: Customer assignment completed!</strong></p>\n";
        echo "<p>Customer ID: " . $customerId . "</p>\n";
    } catch (\Exception $e) {
        echo "<p><strong style='color: red;'>❌ ERROR: Customer assignment failed!</strong></p>\n";
        echo "<p>Error: " . $e->getMessage() . "</p>\n";
    }
    
} else {
    echo "<p><strong style='color: red;'>❌ No parent customer found!</strong></p>\n";
    echo "<p>Cannot test company name search without parent customer.</p>\n";
}

echo "<h2>Test Summary</h2>\n";
echo "<p>This test verifies the new enhancement that searches for existing customers by company name and parent ID before attempting to create a new customer.</p>\n";
echo "<p>The enhancement should prevent the 'customer record with this ID already exists' error seen in the logs.</p>\n";

?>