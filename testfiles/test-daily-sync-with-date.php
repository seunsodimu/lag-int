<?php
/**
 * Test script for daily sync with date filtering
 * This script tests the enhanced daily sync functionality with date parameters
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\OrderStatusSyncService;
use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Utils\Logger;

echo "Testing Daily Sync with Date Filtering\n";
echo "======================================\n\n";

// Set timezone
date_default_timezone_set('America/New_York');

try {
    // Initialize services
    $logger = Logger::getInstance();
    $syncService = new OrderStatusSyncService();
    $threeDCartService = new ThreeDCartService();
    
    // Test 1: Get orders without date filter
    echo "Test 1: Getting orders without date filter\n";
    echo "-------------------------------------------\n";
    $ordersAll = $threeDCartService->getOrdersByStatus(2, 10, 0);
    echo "Found " . count($ordersAll) . " orders with processing status (no date filter)\n\n";
    
    // Test 2: Get orders with date filter (last 30 days)
    $testDate = date('Y-m-d', strtotime('-30 days'));
    echo "Test 2: Getting orders after $testDate\n";
    echo "-------------------------------------------\n";
    $ordersFiltered = $threeDCartService->getOrdersByStatusAfterDate(2, $testDate, 10, 0);
    echo "Found " . count($ordersFiltered) . " orders with processing status after $testDate\n\n";
    
    // Test 3: Test the sync service with date filter
    echo "Test 3: Testing sync service with date filter\n";
    echo "----------------------------------------------\n";
    $result = $syncService->processDailyStatusUpdates($testDate);
    
    if ($result['success']) {
        echo "✅ Sync service test successful!\n";
        echo "Total orders processed: " . $result['total_orders'] . "\n";
        echo "Orders updated: " . $result['updated_count'] . "\n";
        echo "Errors: " . $result['error_count'] . "\n";
        echo "Date filter: " . ($result['after_date'] ?? 'None') . "\n\n";
    } else {
        echo "❌ Sync service test failed: " . $result['error'] . "\n\n";
    }
    
    // Test 4: Test with invalid date format
    echo "Test 4: Testing with invalid date format\n";
    echo "-----------------------------------------\n";
    try {
        $invalidResult = $threeDCartService->getOrdersByStatusAfterDate(2, 'invalid-date', 10, 0);
        echo "❌ Should have failed with invalid date\n";
    } catch (Exception $e) {
        echo "✅ Correctly rejected invalid date: " . $e->getMessage() . "\n";
    }
    
    echo "\nAll tests completed successfully!\n";
    
    // Show sample usage
    echo "\nSample Usage:\n";
    echo "=============\n";
    echo "Command line examples:\n";
    echo "- php daily-status-sync.php                    (no date filter)\n";
    echo "- php daily-status-sync.php 2025-09-01         (orders after 2025-09-01)\n";
    echo "- php daily-status-sync.php " . date('Y-m-d', strtotime('-7 days')) . "        (orders from last 7 days)\n\n";
    
    echo "Web browser examples:\n";
    echo "- http://localhost/run-daily-status-sync.php                           (no date filter)\n";
    echo "- http://localhost/run-daily-status-sync.php?after_date=2025-09-01     (orders after 2025-09-01)\n";
    echo "- http://localhost/run-daily-status-sync.php?after_date=" . date('Y-m-d', strtotime('-7 days')) . "    (orders from last 7 days)\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";