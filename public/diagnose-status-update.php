<?php
/**
 * 3DCart Status Update Diagnostic Tool
 * 
 * This tool helps diagnose why 3DCart orders are not updating to Processing status
 * after successful NetSuite synchronization.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize services
$logger = Logger::getInstance();
$threeDCartService = new ThreeDCartService();
$config = require __DIR__ . '/../config/config.php';

// Handle POST request for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_status_update' && !empty($orderId)) {
        try {
            echo "<div class='alert alert-info'>Testing status update for Order #{$orderId}...</div>";
            
            // Get current order status
            $orderData = $threeDCartService->getOrder($orderId);
            echo "<div class='alert alert-secondary'>";
            echo "<strong>Current Order Status:</strong> {$orderData['OrderStatusID']}<br>";
            echo "<strong>Order Total:</strong> \${$orderData['OrderTotal']}<br>";
            echo "<strong>Customer:</strong> {$orderData['BillingFirstName']} {$orderData['BillingLastName']}";
            echo "</div>";
            
            // Test status update
            $statusId = $config['order_processing']['success_status_id'];
            $comments = "Test status update - " . date('Y-m-d H:i:s');
            
            echo "<div class='alert alert-warning'>Attempting to update status to {$statusId} (Processing)...</div>";
            
            $result = $threeDCartService->updateOrderStatus($orderId, $statusId, $comments);
            
            echo "<div class='alert alert-success'>";
            echo "<strong>Status Update Successful!</strong><br>";
            echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
            echo "</div>";
            
            // Verify the update
            $updatedOrderData = $threeDCartService->getOrder($orderId);
            echo "<div class='alert alert-info'>";
            echo "<strong>Updated Order Status:</strong> {$updatedOrderData['OrderStatusID']}<br>";
            echo "<strong>Status Changed:</strong> " . ($updatedOrderData['OrderStatusID'] == $statusId ? 'YES' : 'NO');
            echo "</div>";
            
        } catch (\Exception $e) {
            echo "<div class='alert alert-danger'>";
            echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3DCart Status Update Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .config-item { margin-bottom: 10px; }
        .status-good { color: #28a745; }
        .status-bad { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🔧 3DCart Status Update Diagnostics</h1>
        <p class="text-muted">Diagnose why 3DCart orders are not updating to Processing status after NetSuite sync</p>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>📋 Current Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="config-item">
                            <strong>Webhook Enabled:</strong> 
                            <span class="<?php echo $config['webhook']['enabled'] ? 'status-good' : 'status-bad'; ?>">
                                <?php echo $config['webhook']['enabled'] ? 'YES' : 'NO'; ?>
                            </span>
                        </div>
                        
                        <div class="config-item">
                            <strong>3DCart Status Updates:</strong> 
                            <span class="<?php echo $config['order_processing']['update_3dcart_status'] ? 'status-good' : 'status-bad'; ?>">
                                <?php echo $config['order_processing']['update_3dcart_status'] ? 'ENABLED' : 'DISABLED'; ?>
                            </span>
                        </div>
                        
                        <div class="config-item">
                            <strong>Success Status ID:</strong> 
                            <span class="status-good"><?php echo $config['order_processing']['success_status_id']; ?></span>
                            (2 = Processing)
                        </div>
                        
                        <div class="config-item">
                            <strong>Status Comments:</strong> 
                            <span class="<?php echo $config['order_processing']['status_comments'] ? 'status-good' : 'status-warning'; ?>">
                                <?php echo $config['order_processing']['status_comments'] ? 'ENABLED' : 'DISABLED'; ?>
                            </span>
                        </div>
                        
                        <div class="config-item">
                            <strong>Environment:</strong> 
                            <span class="status-warning"><?php echo $_ENV['NETSUITE_ENVIRONMENT'] ?? 'unknown'; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>🧪 Test Status Update</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="order_id" class="form-label">3DCart Order ID</label>
                                <input type="text" class="form-control" id="order_id" name="order_id" 
                                       placeholder="e.g., 1108410" required>
                                <div class="form-text">Enter a valid 3DCart order ID to test status updates</div>
                            </div>
                            <input type="hidden" name="action" value="test_status_update">
                            <button type="submit" class="btn btn-primary">Test Status Update</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>🔍 Common Issues & Solutions</h5>
                    </div>
                    <div class="card-body">
                        <h6>1. Webhook Not Enabled</h6>
                        <p class="small">If webhooks are disabled, orders won't be processed at all.</p>
                        <p class="small"><strong>Solution:</strong> Set <code>WEBHOOK_ENABLED=true</code> in .env</p>
                        
                        <h6>2. Status Updates Disabled</h6>
                        <p class="small">Orders sync to NetSuite but don't update in 3DCart.</p>
                        <p class="small"><strong>Solution:</strong> Set <code>UPDATE_3DCART_STATUS=true</code> in .env</p>
                        
                        <h6>3. Invalid Status ID</h6>
                        <p class="small">3DCart rejects the status update due to invalid status ID.</p>
                        <p class="small"><strong>Solution:</strong> Verify status ID 2 exists in your 3DCart store</p>
                        
                        <h6>4. API Authentication Issues</h6>
                        <p class="small">3DCart API credentials are invalid or expired.</p>
                        <p class="small"><strong>Solution:</strong> Check 3DCart API credentials in credentials.php</p>
                        
                        <h6>5. Rate Limiting</h6>
                        <p class="small">Too many API calls causing 429 errors.</p>
                        <p class="small"><strong>Solution:</strong> Implement retry logic with delays</p>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>📊 Monitoring Tools</h5>
                    </div>
                    <div class="card-body">
                        <a href="status.php" class="btn btn-outline-primary btn-sm">System Status</a>
                        <a href="../logs/" class="btn btn-outline-secondary btn-sm">View Logs</a>
                        <a href="order-status-manager.php" class="btn btn-outline-info btn-sm">Order Manager</a>
                        <a href="test-webhook.php" class="btn btn-outline-warning btn-sm">Test Webhook</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>📝 Troubleshooting Steps</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li><strong>Verify Configuration:</strong> Check that all settings above show as "ENABLED" or "YES"</li>
                            <li><strong>Test Status Update:</strong> Use the form above to test updating a specific order</li>
                            <li><strong>Check Logs:</strong> Look for error messages in the application logs</li>
                            <li><strong>Verify 3DCart API:</strong> Ensure your 3DCart API credentials are valid</li>
                            <li><strong>Test Webhook:</strong> Send a test webhook to verify the entire flow</li>
                            <li><strong>Monitor Email Notifications:</strong> Check for error notifications in your email</li>
                        </ol>
                        
                        <div class="alert alert-info mt-3">
                            <strong>💡 Pro Tip:</strong> After making configuration changes, test with a real order to ensure the entire flow works correctly.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>