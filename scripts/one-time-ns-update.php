<?php
/**
 * One-time NetSuite Update Script
 * 
 * Uses data from docs/ns_update.csv to update NetSuite customer records
 * following the existing integration logic.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\HubSpotService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize services
$logger = Logger::getInstance();
$hubspotService = new HubSpotService();
$netsuiteService = new NetSuiteService();

$csvFile = __DIR__ . '/../docs/ns_update.csv';

if (!file_exists($csvFile)) {
    die("Error: CSV file not found at $csvFile\n");
}

$file = fopen($csvFile, 'r');
$header = fgetcsv($file); // Skip header

$stats = [
    'total' => 0,
    'processed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'failed' => 0,
    'not_found' => 0
];

$logger->info('Starting one-time NetSuite update from CSV', ['file' => $csvFile]);

while (($row = fgetcsv($file)) !== false) {
    // Check if row is empty (all columns empty)
    if (empty(array_filter($row))) {
        continue;
    }

    $stats['total']++;
    
    $vid = trim($row[0] ?? '');
    if (empty($vid)) {
        $logger->warning('Skipping row with empty HubSpot VID', ['row' => $row]);
        continue;
    }

    $stats['processed']++;

    // Mapping CSV columns to HubSpot property names
    $data = [
        'sales_readiness' => trim($row[1] ?? ''),
        'buying_time_frame' => trim($row[2] ?? ''),
        'buying_reason' => trim($row[3] ?? ''),
        'projected_value' => trim($row[4] ?? '')
    ];

    try {
        // Find NetSuite customer
        $netsuiteCustomer = $hubspotService->findNetSuiteCustomerByHubSpotId($vid, $netsuiteService);
        
        if (!$netsuiteCustomer) {
            $logger->warning('NetSuite customer not found for HubSpot ID', ['vid' => $vid]);
            $stats['not_found']++;
            continue;
        }

        $netsuiteCustomerId = $netsuiteCustomer['id'];
        $updateData = [];

        foreach ($data as $hsProp => $hsValue) {
            if (empty($hsValue)) {
                continue;
            }

            $mapping = $hubspotService->mapHubSpotToNetSuite($hsProp, $hsValue);
            if (!$mapping) {
                $logger->warning('No mapping found for property', ['vid' => $vid, 'property' => $hsProp, 'value' => $hsValue]);
                continue;
            }

            $nsField = $mapping['field'];
            $nsValue = $mapping['value'];
            $currentValue = $netsuiteCustomer[$nsField] ?? null;

            // Follow existing logic: only update if current value is empty
            $isEmpty = empty($currentValue) || in_array(strtolower((string)$currentValue), ['-none-', 'null']);
            
            if ($isEmpty) {
                $updateData[$nsField] = $nsValue;
            } else {
                $logger->info('Skipping field update: NetSuite already has a value', [
                    'vid' => $vid,
                    'ns_id' => $netsuiteCustomerId,
                    'field' => $nsField,
                    'current' => $currentValue,
                    'new' => $nsValue
                ]);
            }
        }

        if (!empty($updateData)) {
            $result = $netsuiteService->updateCustomer($netsuiteCustomerId, $updateData);
            if ($result['success']) {
                $logger->info('Successfully updated NetSuite customer', [
                    'vid' => $vid,
                    'ns_id' => $netsuiteCustomerId,
                    'updates' => array_keys($updateData)
                ]);
                $stats['updated']++;
            } else {
                $logger->error('Failed to update NetSuite customer', [
                    'vid' => $vid,
                    'ns_id' => $netsuiteCustomerId,
                    'error' => $result['error'] ?? 'Unknown'
                ]);
                $stats['failed']++;
            }
        } else {
            $stats['skipped']++;
        }

    } catch (\Exception $e) {
        $logger->error('Error processing row', [
            'vid' => $vid,
            'error' => $e->getMessage()
        ]);
        $stats['failed']++;
    }
}

fclose($file);

$logger->info('One-time NetSuite update completed', $stats);
echo "Update completed. Stats: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
echo "Check logs for detailed information.\n";
