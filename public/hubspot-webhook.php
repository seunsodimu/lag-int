<?php
/**
 * HubSpot Webhook Endpoint
 * 
 * Receives webhook notifications from HubSpot for contact property changes
 * and processes lead synchronization to NetSuite.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\HubSpotService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize services
$logger = Logger::getInstance();
$hubspotService = new HubSpotService(true); // true = webhook context
$config = require __DIR__ . '/../config/config.php';
$credentials = require __DIR__ . '/../config/credentials.php';

// Log incoming request
$logger->info('HubSpot webhook endpoint accessed', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
]);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $logger->warning('Invalid request method for HubSpot webhook', [
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get raw POST data
    $rawPayload = file_get_contents('php://input');
    
    if (empty($rawPayload)) {
        throw new \Exception('Empty payload received');
    }
    
    // Log raw payload for debugging
    $logger->info('HubSpot webhook raw payload received', [
        'payload_length' => strlen($rawPayload),
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Unknown'
    ]);
    
    // Decode JSON payload
    $payload = json_decode($rawPayload, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON payload: ' . json_last_error_msg());
    }
    
    // HubSpot sends an array of webhook events
    if (!is_array($payload)) {
        throw new \Exception('Invalid payload format: expected array');
    }

    // Handle single object payload if sent (though HubSpot usually sends array)
    $events = isset($payload[0]) ? $payload : [$payload];

    $results = [];
    $hasError = false;

    foreach ($events as $event) {
        // Verify webhook signature if configured
        $signature = $_SERVER['HTTP_X_HUBSPOT_SIGNATURE'] ?? $_SERVER['HTTP_X_HUBSPOT_SIGNATURE_V2'] ?? null;
        // if ($signature && !empty($credentials['hubspot']['webhook_secret'])) {
        //     if (!$hubspotService->verifyWebhookSignature($rawPayload, $signature)) {
        //         http_response_code(401);
        //         $logger->warning('HubSpot webhook signature verification failed');
        //         echo json_encode(['error' => 'Signature verification failed']);
        //         exit;
        //     }
        // }
        
        // Log processed event
        $logger->info('Processing HubSpot webhook event', [
            'subscription_type' => $event['subscriptionType'] ?? 'Unknown',
            'object_id' => $event['objectId'] ?? 'Unknown',
            'property_name' => $event['propertyName'] ?? 'Unknown',
            'property_value' => $event['propertyValue'] ?? 'Unknown'
        ]);
        
        // Process the webhook event
        $result = $hubspotService->processWebhook($event);
        $results[] = $result;

        if (!$result['success']) {
            $hasError = true;
        }
    }
    
    if (!$hasError) {
        http_response_code(200);
        $logger->info('All HubSpot webhook events processed successfully');
        
        echo json_encode([
            'success' => true,
            'message' => 'Webhook events processed successfully',
            'results' => $results
        ]);
    } else {
        // If at least one failed, we might want to return 400 or still 200 depending on preference
        // Usually 200 is safer so HubSpot doesn't keep retrying all events
        http_response_code(200); 
        $logger->warning('Some HubSpot webhook events failed to process');
        
        echo json_encode([
            'success' => false,
            'message' => 'Some events failed to process',
            'results' => $results
        ]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    $error = 'HubSpot webhook processing error: ' . $e->getMessage();
    
    $logger->error($error, [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}