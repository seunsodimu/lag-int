<?php
/**
 * Inventory Synchronization Job
 * 
 * This script synchronizes inventory between NetSuite and 3DCart.
 * It retrieves products from 3DCart, looks up quantities in NetSuite,
 * and updates stock levels in 3DCart.
 * 
 * This script should be run periodically via cron job or task scheduler.
 * 
 * Usage:
 * - Via command line: php sync-inventory.php
 * - Via web browser: http://yoursite.com/run-inventory-sync.php
 * - Via cron job: 0 2 * * * /usr/bin/php /path/to/sync-inventory.php
 * 
 * Parameters:
 * - limit (optional): Limit the number of products to sync in one run
 *   - Command line: php sync-inventory.php --limit=50
 *   - Web browser: ?limit=50
 * - offset (optional): Start from a specific product offset (for pagination)
 *   - Command line: php sync-inventory.php --offset=100
 *   - Web browser: ?offset=100
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$config = require __DIR__ . '/../config/config.php';

use Laguna\Integration\Services\InventorySyncService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Parse command line arguments or web parameters
$filters = [];

// Check for command line arguments
if (isset($argv)) {
    for ($i = 1; $i < count($argv); $i++) {
        if (strpos($argv[$i], '--limit=') === 0) {
            $filters['limit'] = (int)substr($argv[$i], 8);
        } elseif (strpos($argv[$i], '--offset=') === 0) {
            $filters['offset'] = (int)substr($argv[$i], 9);
        }
    }
}

// Check for web parameters (if running via web browser)
if (!empty($_GET['limit'])) {
    $filters['limit'] = (int)$_GET['limit'];
}
if (!empty($_GET['offset'])) {
    $filters['offset'] = (int)$_GET['offset'];
}

// Set defaults if not specified
if (!isset($filters['limit'])) {
    $filters['limit'] = 100;
}
if (!isset($filters['offset'])) {
    $filters['offset'] = 0;
}

// Initialize services
$logger = Logger::getInstance();
$syncService = new InventorySyncService();

// Log job start
$logger->info('Starting inventory synchronization job', [
    'limit' => $filters['limit'],
    'offset' => $filters['offset']
]);

try {
    // Perform the inventory sync
    $result = $syncService->syncInventory($filters);
    
    if ($result['success']) {
        $logger->info('Inventory synchronization completed successfully', [
            'total_products' => $result['total_products'],
            'synced_count' => $result['synced_count'],
            'skipped_count' => $result['skipped_count'],
            'error_count' => $result['error_count']
        ]);
        
        // Output results for command line or web interface
        echo "Inventory Synchronization Results\n";
        echo "==================================\n";
        echo "Start time: " . $result['start_time'] . "\n";
        echo "End time: " . $result['end_time'] . "\n";
        echo "Total products processed: " . $result['total_products'] . "\n";
        echo "Products synced: " . $result['synced_count'] . "\n";
        echo "Products skipped: " . $result['skipped_count'] . "\n";
        echo "Errors encountered: " . $result['error_count'] . "\n";
        
        if (!empty($result['products'])) {
            echo "\nSuccessfully Updated Products:\n";
            echo "-------------------------------\n";
            foreach ($result['products'] as $product) {
                if ($product['success']) {
                    echo "SKU {$product['sku']}: {$product['old_stock']} → {$product['new_stock']}\n";
                }
            }
        }
        
        if (!empty($result['errors'])) {
            echo "\nErrors:\n";
            echo "-------\n";
            foreach ($result['errors'] as $error) {
                echo "ERROR: {$error}\n";
            }
        }
        
        echo "\n";
        
        // Send email notification with sync summary
        echo "Sending email notification...\n";
        $emailResult = $syncService->sendSyncNotificationEmail($result);
        if ($emailResult['success'] ?? false) {
            echo "✓ Email notification sent successfully\n";
        } else {
            echo "✗ Failed to send email notification: " . ($emailResult['error'] ?? 'Unknown error') . "\n";
            $logger->warning('Failed to send inventory sync notification email', [
                'error' => $emailResult['error'] ?? 'Unknown error'
            ]);
        }
        
        // Exit with success code
        exit(0);
        
    } else {
        $logger->error('Inventory synchronization failed', [
            'error' => $result['error']
        ]);
        
        echo "ERROR: Inventory synchronization failed\n";
        echo "Error: " . $result['error'] . "\n";
        echo "Check logs for more details.\n";
        
        // Send email notification about the failure
        echo "\nSending failure notification email...\n";
        $emailResult = $syncService->sendSyncNotificationEmail($result);
        if ($emailResult['success'] ?? false) {
            echo "✓ Failure notification sent successfully\n";
        } else {
            echo "✗ Failed to send failure notification: " . ($emailResult['error'] ?? 'Unknown error') . "\n";
            $logger->warning('Failed to send inventory sync failure notification email', [
                'error' => $emailResult['error'] ?? 'Unknown error'
            ]);
        }
        
        // Exit with error code
        exit(1);
    }
    
} catch (\Exception $e) {
    $logger->error('Inventory synchronization job crashed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo "FATAL ERROR: Inventory synchronization job crashed\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Check logs for full stack trace.\n";
    
    // Exit with error code
    exit(1);
}