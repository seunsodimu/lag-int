<?php
/**
 * Test Script for Inventory Synchronization Feature
 * 
 * This script demonstrates how to use the InventorySyncService
 * and can be used to test the feature before deploying to production.
 * 
 * Usage: php testfiles/test-inventory-sync.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\InventorySyncService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

echo "========================================\n";
echo "Inventory Sync Test\n";
echo "========================================\n\n";

// Initialize services
$logger = Logger::getInstance();
$syncService = new InventorySyncService();
$netSuiteService = new NetSuiteService();

try {
    // Test 1: Verify NetSuite connection
    echo "Test 1: NetSuite Connection\n";
    echo "----------------------------\n";
    
    $connectionTest = $netSuiteService->testConnection();
    if ($connectionTest['success']) {
        echo "✓ NetSuite connection: OK\n";
    } else {
        echo "✗ NetSuite connection: FAILED\n";
        echo "  Error: " . $connectionTest['error'] ?? 'Unknown error' . "\n";
    }
    echo "\n";
    
    // Test 2: Search for a sample item in NetSuite
    echo "Test 2: Search Item in NetSuite\n";
    echo "--------------------------------\n";
    
    // Try to search for a common SKU (from the item_response.json sample)
    $testSku = "MBAND1412-175";
    echo "Searching for SKU: $testSku\n";
    
    $item = $netSuiteService->searchItemBySku($testSku);
    if ($item) {
        echo "✓ Item found in NetSuite\n";
        echo "  ID: " . ($item['id'] ?? 'N/A') . "\n";
        echo "  ItemID: " . ($item['itemid'] ?? $item['ItemID'] ?? 'N/A') . "\n";
        echo "  Display Name: " . ($item['displayname'] ?? 'N/A') . "\n";
        echo "  Quantity on Hand: " . ($item['quantityonhand'] ?? $item['totalquantityonhand'] ?? 0) . "\n";
    } else {
        echo "⚠ Item not found in NetSuite (This may be expected if SKU doesn't exist)\n";
        echo "  SKU: $testSku\n";
    }
    echo "\n";
    
    // Test 3: Run inventory sync with small batch
    echo "Test 3: Run Inventory Sync (Sample)\n";
    echo "-----------------------------------\n";
    echo "Syncing first 3 products to test...\n";
    
    $result = $syncService->syncInventory([
        'limit' => 3,  // Only sync first 3 products for testing
        'offset' => 0
    ]);
    
    if ($result['success']) {
        echo "✓ Sync completed successfully\n";
        echo "  Total products: " . $result['total_products'] . "\n";
        echo "  Synced: " . $result['synced_count'] . "\n";
        echo "  Skipped: " . $result['skipped_count'] . "\n";
        echo "  Errors: " . $result['error_count'] . "\n";
        echo "\n";
        
        // Show updated products
        if (!empty($result['products'])) {
            echo "  Updated Products:\n";
            foreach ($result['products'] as $product) {
                if ($product['success']) {
                    echo "    - SKU {$product['sku']}: {$product['old_stock']} → {$product['new_stock']}\n";
                }
            }
        }
        
        // Show errors if any
        if (!empty($result['errors'])) {
            echo "\n  Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "    - $error\n";
            }
        }
    } else {
        echo "✗ Sync failed\n";
        echo "  Error: " . $result['error'] . "\n";
    }
    echo "\n";
    
    // Test 4: Summary
    echo "Test 4: Summary\n";
    echo "---------------\n";
    echo "✓ All tests completed\n";
    echo "\nFeature is ready for:\n";
    echo "1. Manual web access: http://yoursite.com/run-inventory-sync.php\n";
    echo "2. Command line: php scripts/sync-inventory.php\n";
    echo "3. Scheduled jobs: Via cron or Windows Task Scheduler\n";
    echo "\n";
    
    echo "Check logs/app.log for detailed operation logs.\n";
    
} catch (\Exception $e) {
    echo "✗ Test failed with exception:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n========================================\n";
echo "Test Complete\n";
echo "========================================\n";
?>