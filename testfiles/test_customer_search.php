<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\ConfigService;
use Laguna\Integration\Services\LoggerService;

try {
    // Initialize services
    $configService = new ConfigService();
    $config = $configService->getConfig();
    
    $loggerService = new LoggerService($config);
    $logger = $loggerService->getLogger();
    
    $netSuiteService = new NetSuiteService($config, $logger);
    
    // Test the customer search for the problematic email
    $email = 'rogerb@ahturf.com';
    
    echo "Testing customer search for: $email\n\n";
    
    // Test 1: Store customer search (original restrictive search)
    echo "1. Store customer search (isperson = 'F'):\n";
    $reflection = new ReflectionClass($netSuiteService);
    $findStoreCustomerMethod = $reflection->getMethod('findStoreCustomer');
    $findStoreCustomerMethod->setAccessible(true);
    $storeCustomer = $findStoreCustomerMethod->invoke($netSuiteService, $email);
    
    if ($storeCustomer) {
        echo "   Found store customer: ID = {$storeCustomer['id']}, isPerson = {$storeCustomer['isperson']}\n";
    } else {
        echo "   No store customer found\n";
    }
    
    // Test 2: Broader email search (new search)
    echo "\n2. Broader email search (any customer):\n";
    $anyCustomer = $netSuiteService->findCustomerByEmail($email);
    
    if ($anyCustomer) {
        echo "   Found customer: ID = {$anyCustomer['id']}, isPerson = {$anyCustomer['isperson']}, Company = " . ($anyCustomer['companyName'] ?? 'N/A') . "\n";
        echo "   This explains why customer creation failed - customer already exists!\n";
    } else {
        echo "   No customer found with this email\n";
    }
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}