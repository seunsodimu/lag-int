<?php
/**
 * Daily Order Status Synchronization Job
 * 
 * This script should be run daily via cron job or task scheduler.
 * It retrieves 3DCart orders with processing status and updates them
 * based on their corresponding NetSuite sales order status.
 * 
 * Usage:
 * - Via command line: php daily-status-sync.php [YYYY-MM-DD]
 * - Via web browser: http://yoursite.com/scripts/daily-status-sync.php?after_date=YYYY-MM-DD
 * - Via cron job: 0 9 * * * /usr/bin/php /path/to/daily-status-sync.php
 * 
 * Parameters:
 * - after_date (optional): Only process orders created after this date (YYYY-MM-DD format)
 *   - Command line: php daily-status-sync.php 2025-09-01
 *   - Web browser: ?after_date=2025-09-01
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\OrderStatusSyncService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Parse command line arguments or web parameters
$afterDate = null;

// Check for command line argument
if (isset($argv[1])) {
    $afterDate = $argv[1];
}

// Check for web parameter (if running via web browser)
if (!$afterDate && isset($_GET['after_date'])) {
    $afterDate = $_GET['after_date'];
}

// Validate date format if provided
if ($afterDate) {
    $dateTime = DateTime::createFromFormat('Y-m-d', $afterDate);
    if (!$dateTime || $dateTime->format('Y-m-d') !== $afterDate) {
        echo "ERROR: Invalid date format. Please use YYYY-MM-DD format.\n";
        echo "Example: php daily-status-sync.php 2025-09-01\n";
        exit(1);
    }
}

// Initialize services
$logger = Logger::getInstance();
$syncService = new OrderStatusSyncService();

// Log job start
$logger->info('Starting daily order status synchronization job', [
    'after_date' => $afterDate
]);

try {
    // Process the daily status updates
    $result = $syncService->processDailyStatusUpdates($afterDate);
    
    if ($result['success']) {
        $logger->info('Daily order status synchronization completed successfully', [
            'total_orders' => $result['total_orders'],
            'updated_count' => $result['updated_count'],
            'error_count' => $result['error_count']
        ]);
        
        // Output results for command line or web interface
        echo "Daily Order Status Synchronization Results:\n";
        echo "==========================================\n";
        if ($afterDate) {
            echo "Date filter: Orders created after " . $afterDate . "\n";
        } else {
            echo "Date filter: All orders (no date filter applied)\n";
        }
        echo "Total orders processed: " . $result['total_orders'] . "\n";
        echo "Orders updated: " . $result['updated_count'] . "\n";
        echo "Errors encountered: " . $result['error_count'] . "\n";
        echo "Job completed at: " . date('Y-m-d H:i:s') . "\n";
        
        if (!empty($result['results'])) {
            echo "\nDetailed Results:\n";
            echo "-----------------\n";
            foreach ($result['results'] as $orderResult) {
                echo "Order {$orderResult['order_id']}: ";
                if ($orderResult['updated']) {
                    echo "UPDATED - {$orderResult['reason']}\n";
                    if (!empty($orderResult['tracking_numbers'])) {
                        echo "  Tracking: {$orderResult['tracking_numbers']}\n";
                    }
                } else {
                    echo "SKIPPED - {$orderResult['reason']}\n";
                }
            }
        }
        
        // Exit with success code
        exit(0);
        
    } else {
        $logger->error('Daily order status synchronization failed', [
            'error' => $result['error']
        ]);
        
        echo "ERROR: Daily order status synchronization failed\n";
        echo "Error: " . $result['error'] . "\n";
        echo "Check logs for more details.\n";
        
        // Exit with error code
        exit(1);
    }
    
} catch (\Exception $e) {
    $logger->error('Daily order status synchronization job crashed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo "FATAL ERROR: Daily order status synchronization job crashed\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Check logs for full stack trace.\n";
    
    // Exit with error code
    exit(1);
}