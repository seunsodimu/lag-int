<?php
/**
 * Daily Order Status Synchronization Job
 * 
 * This script should be run daily via cron job or task scheduler.
 * It retrieves 3DCart orders with processing status and updates them
 * based on their corresponding NetSuite sales order status.
 * 
 * Usage:
 * - Via command line: php daily-status-sync.php
 * - Via web browser: http://yoursite.com/scripts/daily-status-sync.php
 * - Via cron job: 0 9 * * * /usr/bin/php /path/to/daily-status-sync.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\OrderStatusSyncService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize services
$logger = Logger::getInstance();
$syncService = new OrderStatusSyncService();

// Log job start
$logger->info('Starting daily order status synchronization job');

try {
    // Process the daily status updates
    $result = $syncService->processDailyStatusUpdates();
    
    if ($result['success']) {
        $logger->info('Daily order status synchronization completed successfully', [
            'total_orders' => $result['total_orders'],
            'updated_count' => $result['updated_count'],
            'error_count' => $result['error_count']
        ]);
        
        // Output results for command line or web interface
        echo "Daily Order Status Synchronization Results:\n";
        echo "==========================================\n";
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