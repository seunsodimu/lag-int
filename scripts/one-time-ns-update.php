<?php
/**
 * One-time HubSpot to NetSuite Update Script
 * 
 * Uses data from docs/hs_ns_update.csv to retrieve contact data from HubSpot
 * and update corresponding NetSuite customer records.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\HubSpotService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;
use GuzzleHttp\Client;

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize services
$logger = Logger::getInstance();
$hubspotService = new HubSpotService();
$netsuiteService = new NetSuiteService();

// Get credentials for direct HubSpot API call
$credentials = require __DIR__ . '/../config/credentials.php';
$hubspotAccessToken = $credentials['hubspot']['access_token'];
$hubspotBaseUrl = $credentials['hubspot']['base_url'];

$httpClient = new Client([
    'base_uri' => $hubspotBaseUrl,
    'timeout' => 30,
    // 'verify' => false,
    'headers' => [
        'Authorization' => 'Bearer ' . $hubspotAccessToken,
        'Accept' => 'application/json'
    ]
]);

$csvFile = __DIR__ . '/../docs/hs_ns_update.csv';

if (!file_exists($csvFile)) {
    die("Error: CSV file not found at $csvFile\n");
}

$file = fopen($csvFile, 'r');
$header = fgetcsv($file); // Skip header (HubSpot ID)

$stats = [
    'total' => 0,
    'processed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'failed' => 0,
    'not_found' => 0
];

$logger->info('Starting one-time NetSuite update from HubSpot CSV', ['file' => $csvFile]);

while (($row = fgetcsv($file)) !== false) {
    if (empty(array_filter($row))) {
        continue;
    }

    $stats['total']++;
    
    // Handle scientific notation for HubSpot IDs from CSV
    $hubspotId = trim($row[0] ?? '');
    if (strpos(strtoupper($hubspotId), 'E+') !== false) {
        $hubspotId = sprintf("%.0f", (float)$hubspotId);
    }

    if (empty($hubspotId)) {
        continue;
    }

    $stats['processed']++;

    try {
        // Retrieve contact data from HubSpot using specified properties from cURL
        $properties = [
            'projected_value',
            'buying_time_frame',
            'ns_customer_id',
            'ns_entity_id',
            'buying_reason',
            'sales_readiness'
        ];
        
        $url = "/crm/v3/objects/contacts/{$hubspotId}?properties=" . implode('&properties=', $properties) . "&archived=false";
        $response = $httpClient->get($url);
        
        if ($response->getStatusCode() !== 200) {
            $logger->warning('HubSpot contact not found', ['hubspot_id' => $hubspotId]);
            $stats['not_found']++;
            continue;
        }

        $hsData = json_decode($response->getBody()->getContents(), true);
        $hsProperties = $hsData['properties'] ?? [];

        // Find NetSuite customer using existing logic (SuiteQL lookup by VID)
        $netsuiteCustomer = $hubspotService->findNetSuiteCustomerByHubSpotId($hubspotId, $netsuiteService);
        
        // If not found by VID, try using ns_customer_id from HubSpot properties
        if (!$netsuiteCustomer && !empty($hsProperties['ns_customer_id'])) {
            $nsId = $hsProperties['ns_customer_id'];
            $query = "SELECT id, buyingtimeframe, salesreadiness, buyingreason, custentity_projected_value FROM customer WHERE id = " . intval($nsId);
            $result = $netsuiteService->executeSuiteQLQuery($query);
            if (isset($result['items']) && count($result['items']) > 0) {
                $netsuiteCustomer = $result['items'][0];
            }
        }

        if (!$netsuiteCustomer) {
            $logger->warning('NetSuite customer not found for HubSpot contact', ['hubspot_id' => $hubspotId]);
            $stats['not_found']++;
            continue;
        }

        $netsuiteCustomerId = $netsuiteCustomer['id'];
        $updateData = [];

        // Properties to check and map
        $propToUpdate = [
            'sales_readiness',
            'buying_time_frame',
            'buying_reason',
            'projected_value'
        ];

        foreach ($propToUpdate as $hsProp) {
            $hsValue = trim($hsProperties[$hsProp] ?? '');
            if (empty($hsValue)) {
                continue;
            }

            $mapping = $hubspotService->mapHubSpotToNetSuite($hsProp, $hsValue);
            if (!$mapping) {
                continue;
            }

            $nsField = $mapping['field'];
            $nsValue = $mapping['value'];
            $currentValue = $netsuiteCustomer[$nsField] ?? null;

            // Only update if NetSuite field is currently empty or null
            $isEmpty = empty($currentValue) || in_array(strtolower((string)$currentValue), ['-none-', 'null']);
            
            if ($isEmpty) {
                $updateData[$nsField] = $nsValue;
            }
        }

        if (!empty($updateData)) {
            $result = $netsuiteService->updateCustomer($netsuiteCustomerId, $updateData);
            if ($result['success']) {
                $logger->info('Successfully updated NetSuite customer', [
                    'hubspot_id' => $hubspotId,
                    'ns_id' => $netsuiteCustomerId,
                    'updates' => array_keys($updateData)
                ]);
                $stats['updated']++;
            } else {
                $logger->error('Failed to update NetSuite customer', [
                    'hubspot_id' => $hubspotId,
                    'ns_id' => $netsuiteCustomerId,
                    'error' => $result['error'] ?? 'Unknown'
                ]);
                $stats['failed']++;
            }
        } else {
            $stats['skipped']++;
        }

    } catch (\Exception $e) {
        $logger->error('Error processing HubSpot ID', [
            'hubspot_id' => $hubspotId,
            'error' => $e->getMessage()
        ]);
        $stats['failed']++;
    }
}

fclose($file);

$logger->info('One-time NetSuite update completed', $stats);
echo "Update completed. Stats: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
