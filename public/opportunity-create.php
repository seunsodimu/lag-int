<?php
/**
 * Opportunity Creation Endpoint
 * 
 * Creates a new opportunity in NetSuite
 * 
 * Authentication: Bearer token (API key) or session
 * 
 * POST /opportunity-create.php
 * 
 * Request Headers:
 *   Authorization: Bearer <api_key>
 *   Content-Type: application/json
 * 
 * Request Body Example:
 * {
 *   "entity": {
 *     "id": 58746
 *   },
 *   "title": "New Sales Opportunity",
 *   "trandate": "2025-10-25",
 *   "expectedclosedate": "2025-12-31",
 *   "location": 7,
 *   "department": 2,
 *   "probability": 75.0,
 *   "projectedtotaltedTotal": 10000.00
 * }
 * 
 * Response Success (200):
 * {
 *   "success": true,
 *   "data": {
 *     "success": true,
 *     "status_code": 201,
 *     "data": {
 *       "id": "12345",
 *       ...
 *     }
 *   }
 * }
 * 
 * Response Error (401/400/500):
 * {
 *   "success": false,
 *   "error": "Error message",
 *   "status_code": 401
 * }
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use Laguna\Integration\Controllers\TaskOpportunityController;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json', true, 405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Only POST requests are supported.'
    ]);
    exit;
}

try {
    $controller = new TaskOpportunityController();
    $controller->createOpportunity();
} catch (Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to initialize controller: ' . $e->getMessage()
    ]);
}