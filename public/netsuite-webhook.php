<?php
/**
 * NetSuite to HubSpot Webhook Endpoint
 * 
 * Receives webhook notifications from NetSuite when customer properties change
 * and synchronizes those changes back to HubSpot.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\HubSpotService;
use Laguna\Integration\Utils\Logger;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize services
$logger = Logger::getInstance();
$hubspotService = new HubSpotService();

// Log incoming request
$logger->info('NetSuite to HubSpot webhook accessed', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
]);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Authentication using NS_WEBHOOK_SECRET
$secretHeader = $_SERVER['HTTP_X_NETSUITE_SECRET'] ?? $_SERVER['X_NETSUITE_SECRET'] ?? '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

// Support "Bearer <secret>" format
if (empty($secretHeader) && !empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $secretHeader = $matches[1];
}

$expectedSecret = $_ENV['NS_WEBHOOK_SECRET'] ?? '';

if (empty($secretHeader) || $secretHeader !== $expectedSecret) {
    http_response_code(401);
    $logger->warning('Unauthorized NetSuite webhook attempt', [
        'has_secret_header' => !empty($_SERVER['HTTP_X_NETSUITE_SECRET']),
        'has_auth_header' => !empty($_SERVER['HTTP_AUTHORIZATION'])
    ]);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get raw POST data
    $rawPayload = file_get_contents('php://input');
    $payload = json_decode($rawPayload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON payload: ' . json_last_error_msg());
    }

    if (empty($payload)) {
        throw new \Exception('Empty payload received');
    }

    $logger->info('NetSuite webhook payload received', ['payload' => $payload]);

    // Required field: custentity_hs_vid (HubSpot Contact ID)
    if (empty($payload['custentity_hs_vid'])) {
        throw new \Exception('Missing required field: custentity_hs_vid');
    }

    $hubspotContactId = $payload['custentity_hs_vid'];
    
    // Map NetSuite fields to HubSpot properties using service
    $hubspotProperties = $hubspotService->mapNetSuiteToHubSpot($payload);

    if (empty($hubspotProperties)) {
        $logger->info('No mappable fields found in payload', ['payload' => $payload]);
        echo json_encode(['success' => true, 'message' => 'No fields to update']);
        exit;
    }

    // Update HubSpot
    $result = $hubspotService->updateContact($hubspotContactId, $hubspotProperties);

    if ($result['success']) {
        http_response_code(200);
        $logger->info('Successfully updated HubSpot contact from NetSuite webhook', [
            'contact_id' => $hubspotContactId,
            'updated_fields' => array_keys($hubspotProperties)
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'HubSpot contact updated successfully',
            'contact_id' => $hubspotContactId,
            'updated_fields' => array_keys($hubspotProperties)
        ]);
    } else {
        throw new \Exception('Failed to update HubSpot: ' . ($result['error'] ?? 'Unknown error'));
    }

} catch (\Exception $e) {
    http_response_code(500);
    $logger->error('NetSuite to HubSpot webhook failed', [
        'error' => $e->getMessage()
    ]);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
