<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

try {
    // Initialize services using the same pattern as the main application
    $credentials = require __DIR__ . '/config/credentials.php';
    $config = require __DIR__ . '/config/config.php';
    
    $logger = Logger::getInstance();
    $netSuiteService = new NetSuiteService();
    
    // Test the customer search for the problematic email
    $email = 'rogerb@ahturf.com';
    
    echo "Testing customer search for: $email\n\n";
    
    // Test the broader email search (this should find any existing customer)
    echo "Testing broader email search (any customer type):\n";
    $anyCustomer = $netSuiteService->findCustomerByEmail($email);
    
    if ($anyCustomer) {
        echo "SUCCESS: Found existing customer!\n";
        echo "   Customer ID: {$anyCustomer['id']}\n";
        echo "   isPerson: {$anyCustomer['isperson']}\n";
        echo "   Company: " . ($anyCustomer['companyName'] ?? 'N/A') . "\n";
        echo "   Email: " . ($anyCustomer['email'] ?? 'N/A') . "\n";
        echo "\nThis explains why customer creation was failing - customer already exists!\n";
        echo "The fix should now find this customer instead of trying to create a duplicate.\n";
    } else {
        echo "No customer found with this email - creation should proceed normally.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}