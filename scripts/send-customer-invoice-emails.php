<?php
/**
 * Script to send customer invoice emails from a CSV file
 * 
 * Usage:
 * - Via command line: php send-customer-invoice-emails.php [path/to/csv]
 */

require_once __DIR__ . '/../vendor/autoload.php';
// Load configuration and environment variables
$config = require __DIR__ . '/../config/config.php';

use Laguna\Integration\Services\CustomerInvoiceEmailService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

$logger = Logger::getInstance();
$csvPath = $argv[1] ?? null;

$logger->info('Starting customer invoice email sending job', [
    'csv_path' => $csvPath ?: 'default'
]);

try {
    $service = new CustomerInvoiceEmailService($csvPath);
    $result = $service->sendEmails();

    if ($result['success'] !== false) {
        echo "Customer Invoice Email Sending Results:\n";
        echo "======================================\n";
        echo "Total processed: " . $result['total'] . "\n";
        echo "Successfully sent: " . $result['success'] . "\n";
        echo "Failed: " . $result['failed'] . "\n";
        echo "Job completed at: " . date('Y-m-d H:i:s') . "\n";

        if (!empty($result['details'])) {
            echo "\nDetailed Results:\n";
            echo "-----------------\n";
            foreach ($result['details'] as $detail) {
                echo "Customer: " . $detail['customer'] . " (" . $detail['email'] . ") - ";
                echo $detail['success'] ? "SUCCESS" : "FAILED (" . $detail['error'] . ")";
                echo "\n";
            }
        }
        
        $logger->info('Customer invoice email sending job completed', [
            'total' => $result['total'],
            'success' => $result['success'],
            'failed' => $result['failed']
        ]);
        
        exit(0);
    } else {
        echo "ERROR: " . $result['error'] . "\n";
        $logger->error('Customer invoice email sending job failed', [
            'error' => $result['error']
        ]);
        exit(1);
    }

} catch (\Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    $logger->error('Customer invoice email sending job crashed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
