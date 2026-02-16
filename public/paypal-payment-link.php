<?php
/**
 * PayPal Payment Link Generator Endpoint
 * 
 * This endpoint generates PayPal payment links for NetSuite Sales Orders
 * and updates the 'custbody_paypal_payment_url' field in NetSuite.
 * 
 * Endpoint: POST /paypal-payment-link.php
 * 
 * Request Body:
 * {
 *   "order_ids": ["12345", "67890"]
 * }
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\PayPalController;
use Laguna\Integration\Utils\Logger;

// Load environment variables from .env file
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize logger
$logger = Logger::getInstance();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    try {
        $controller = new PayPalController();
        $controller->updateSalesOrdersWithPayPalLinks();
    } catch (\Exception $e) {
        $logger->error('PayPal API endpoint error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'details' => $e->getMessage(),
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
    }
} else {
    // Show simple documentation for GET requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'PayPal Payment Link Generator API is active. Use POST to generate links.',
        'usage' => [
            'method' => 'POST',
            'endpoint' => '/paypal-payment-link.php',
            'body' => [
                'order_ids' => ['string or array of NetSuite internal IDs']
            ]
        ]
    ], JSON_PRETTY_PRINT);
}
