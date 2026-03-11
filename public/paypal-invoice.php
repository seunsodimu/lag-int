<?php
/**
 * PayPal Invoice Generator Endpoint
 * 
 * This endpoint generates PayPal invoices from NetSuite Sales Orders.
 * 
 * Endpoint: POST /paypal-invoice.php
 * 
 * Request Body:
 * {
 *   "order_ids": ["12345", "67890"],
 *   "environment": "sandbox", // Optional: "sandbox" or "production"
 *   "send_to_invoicer": false, // Optional: default false
 *   "send_to_recipient": true // Optional: default true
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
        $controller->createInvoicesForSalesOrders();
    } catch (\Exception $e) {
        $logger->error('PayPal Invoice API endpoint error', [
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
        'message' => 'PayPal Invoice Generator API is active. Use POST to generate invoices.',
        'usage' => [
            'method' => 'POST',
            'endpoint' => '/paypal-invoice.php',
            'body' => [
                'order_ids' => ['string or array of NetSuite internal IDs'],
                'environment' => 'sandbox or production',
                'send_to_invoicer' => 'bool',
                'send_to_recipient' => 'bool'
            ]
        ]
    ], JSON_PRETTY_PRINT);
}
