<?php
/**
 * Order Status Manager
 * 
 * Manual interface for managing order status synchronization between
 * 3DCart and NetSuite. Allows viewing order details and manual updates.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\OrderStatusSyncService;
use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Utils\UrlHelper;
use Laguna\Integration\Middleware\AuthMiddleware;

// Handle AJAX requests first (before authentication redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to catch any unexpected output
    ob_start();
    
    // Disable error display for AJAX requests to prevent JSON corruption
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    
    // Set JSON content type early
    header('Content-Type: application/json');
    
    // For AJAX requests, check authentication and return JSON error if not authenticated
    $auth = new AuthMiddleware();
    if (!$auth->isAuthenticated()) {
        // Clear any buffered output and send clean JSON
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required. Please log in.',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    $currentUser = $auth->getCurrentUser();
} else {
    // For regular page requests, use normal authentication with redirect
    $auth = new AuthMiddleware();
    $currentUser = $auth->requireAuth();
    if (!$currentUser) {
        exit; // Middleware handles redirect
    }
}

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize services
$syncService = new OrderStatusSyncService();
$threeDCartService = new ThreeDCartService();
$netSuiteService = new NetSuiteService();
$webhookController = new WebhookController();
$logger = Logger::getInstance();

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure we always return JSON, even on fatal errors
    header('Content-Type: application/json');
    
    // Capture any output that might interfere with JSON
    ob_start();
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'get_order_info':
                $orderId = $_POST['order_id'] ?? '';
                
                if (empty($orderId)) {
                    throw new Exception('Order ID is required');
                }
                
                $result = $syncService->getOrderInformation($orderId);
                
                // Clear any buffered output and send clean JSON
                ob_clean();
                echo json_encode($result);
                exit;
                
            case 'update_threedcart_fields':
                $orderId = $_POST['order_id'] ?? '';
                $updateData = $_POST['update_data'] ?? [];
                
                if (empty($orderId)) {
                    throw new Exception('Order ID is required');
                }
                
                if (empty($updateData)) {
                    throw new Exception('Update data is required');
                }
                
                // Check if order is already synced
                $orderInfo = $syncService->getOrderInformation($orderId);
                if (!$orderInfo['success']) {
                    throw new Exception('Failed to retrieve order information');
                }
                
                if ($orderInfo['is_synced']) {
                    throw new Exception('Cannot edit order that is already synced to NetSuite');
                }
                
                // Update the 3DCart order
                $result = $threeDCartService->updateOrderFields($orderId, $updateData);
                
                // Clear any buffered output and send clean JSON
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Order fields updated successfully',
                    'result' => $result
                ]);
                exit;
                
            case 'sync_to_netsuite':
                $orderId = $_POST['order_id'] ?? '';
                
                if (empty($orderId)) {
                    throw new Exception('Order ID is required');
                }
                
                // Get the updated 3DCart order
                $threeDCartOrder = $threeDCartService->getOrder($orderId);
                
                // Process the order through the webhook controller
                $result = $webhookController->processOrder($threeDCartOrder);
                
                // Clear any buffered output and send clean JSON
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Order synced to NetSuite successfully',
                    'result' => $result
                ]);
                exit;
                
            case 'update_from_netsuite':
                $orderId = $_POST['order_id'] ?? '';
                
                if (empty($orderId)) {
                    throw new Exception('Order ID is required');
                }
                
                $result = $syncService->updateOrderFromNetSuite($orderId);
                
                // Clear any buffered output and send clean JSON
                ob_clean();
                echo json_encode($result);
                exit;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (\Exception $e) {
        // Clear any buffered output and send clean JSON error
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['app']['name']; ?> - Order Status Manager</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .user-info {
            position: absolute;
            top: 20px;
            right: 30px;
            text-align: right;
            font-size: 0.9em;
        }
        .user-info a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            opacity: 0.9;
        }
        .user-info a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        .content {
            padding: 40px;
        }
        .search-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .order-section {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .order-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
        }
        .order-content {
            padding: 20px;
        }
        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .field-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .field-label {
            font-weight: 500;
            color: #666;
        }
        .field-value {
            color: #333;
        }
        .editable-field {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .status-synced {
            background: #d4edda;
            color: #155724;
        }
        .status-not-synced {
            background: #f8d7da;
            color: #721c24;
        }
        .status-can-update {
            background: #d1ecf1;
            color: #0c5460;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loading.show {
            display: block;
        }
        .hidden {
            display: none;
        }
        .back-link {
            margin-bottom: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                <a href="<?php echo UrlHelper::url('index.php'); ?>">🏠 Dashboard</a>
                <a href="<?php echo UrlHelper::url('logout.php'); ?>">🚪 Logout</a>
            </div>
            <h1>Order Status Manager</h1>
            <p>Manual order status synchronization between 3DCart and NetSuite</p>
        </div>
        
        <div class="content">
            <div class="back-link">
                <a href="<?php echo UrlHelper::url('index.php'); ?>">← Back to Dashboard</a>
            </div>
            
            <div class="search-section">
                <h3>Search Order</h3>
                <div class="form-group">
                    <label for="order-id">3DCart Order ID:</label>
                    <input type="text" id="order-id" placeholder="Enter 3DCart order ID (e.g., 1234567)">
                </div>
                <button type="button" class="btn" onclick="searchOrder()">Search Order</button>
            </div>
            
            <div class="loading" id="loading">
                <p>Loading order information...</p>
            </div>
            
            <div id="alert-container"></div>
            
            <div id="order-results" class="hidden">
                <div class="order-grid">
                    <!-- 3DCart Order Section -->
                    <div class="order-section">
                        <div class="order-header">
                            3DCart Order Information
                            <span id="sync-status-badge" class="status-badge"></span>
                        </div>
                        <div class="order-content" id="threedcart-content">
                            <!-- 3DCart order details will be populated here -->
                        </div>
                    </div>
                    
                    <!-- NetSuite Order Section -->
                    <div class="order-section">
                        <div class="order-header">
                            NetSuite Sales Order Information
                        </div>
                        <div class="order-content" id="netsuite-content">
                            <!-- NetSuite order details will be populated here -->
                        </div>
                    </div>
                </div>
                
                <div id="action-buttons" class="hidden" style="margin-top: 20px; text-align: center;">
                    <!-- Action buttons will be populated based on order status -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentOrderData = null;
        let editableFields = {};
        
        function searchOrder() {
            const orderId = document.getElementById('order-id').value.trim();
            
            if (!orderId) {
                showAlert('Please enter an order ID', 'danger');
                return;
            }
            
            showLoading(true);
            clearResults();
            
            fetch('order-status-manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_order_info&order_id=${encodeURIComponent(orderId)}`
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                
                if (data.success) {
                    currentOrderData = data;
                    displayOrderResults(data);
                } else {
                    showAlert(data.error || 'Failed to retrieve order information', 'danger');
                }
            })
            .catch(error => {
                showLoading(false);
                showAlert('Network error: ' + error.message, 'danger');
            });
        }
        
        function displayOrderResults(data) {
            const resultsDiv = document.getElementById('order-results');
            const threeDCartContent = document.getElementById('threedcart-content');
            const netSuiteContent = document.getElementById('netsuite-content');
            const syncStatusBadge = document.getElementById('sync-status-badge');
            const actionButtons = document.getElementById('action-buttons');
            
            // Set sync status badge
            if (data.is_synced) {
                syncStatusBadge.textContent = 'Synced';
                syncStatusBadge.className = 'status-badge status-synced';
            } else {
                syncStatusBadge.textContent = 'Not Synced';
                syncStatusBadge.className = 'status-badge status-not-synced';
            }
            
            // Display 3DCart order information
            display3DCartOrder(threeDCartContent, data.threedcart_order, data.can_edit_threedcart);
            
            // Display NetSuite order information
            displayNetSuiteOrder(netSuiteContent, data.netsuite_order);
            
            // Display action buttons
            displayActionButtons(actionButtons, data);
            
            resultsDiv.classList.remove('hidden');
        }
        
        function display3DCartOrder(container, order, canEdit) {
            if (!order) {
                container.innerHTML = '<p>Order not found in 3DCart</p>';
                return;
            }
            
            const editableFieldsList = [
                'BillingEmail',
                'BillingPhoneNumber'
            ];
            
            let html = `
                <div class="field-row">
                    <span class="field-label">Order ID:</span>
                    <span class="field-value">${order.OrderID || 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Order Date:</span>
                    <span class="field-value">${order.OrderDate || 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Status:</span>
                    <span class="field-value">${order.OrderStatusID || 'N/A'} (${getOrderStatusName(order.OrderStatusID)})</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Total:</span>
                    <span class="field-value">$${order.OrderTotal || '0.00'}</span>
                </div>
            `;
            
            // Billing Email (editable if not synced)
            if (canEdit && editableFieldsList.includes('BillingEmail')) {
                html += `
                    <div class="field-row">
                        <span class="field-label">Billing Email:</span>
                        <input type="email" class="editable-field" data-field="BillingEmail" value="${order.BillingEmail || ''}" onchange="updateEditableField('BillingEmail', this.value)">
                    </div>
                `;
            } else {
                html += `
                    <div class="field-row">
                        <span class="field-label">Billing Email:</span>
                        <span class="field-value">${order.BillingEmail || 'N/A'}</span>
                    </div>
                `;
            }
            
            // Billing Phone (editable if not synced)
            if (canEdit && editableFieldsList.includes('BillingPhoneNumber')) {
                html += `
                    <div class="field-row">
                        <span class="field-label">Billing Phone:</span>
                        <input type="tel" class="editable-field" data-field="BillingPhoneNumber" value="${order.BillingPhoneNumber || ''}" onchange="updateEditableField('BillingPhoneNumber', this.value)">
                    </div>
                `;
            } else {
                html += `
                    <div class="field-row">
                        <span class="field-label">Billing Phone:</span>
                        <span class="field-value">${order.BillingPhoneNumber || 'N/A'}</span>
                    </div>
                `;
            }
            
            // Question List (QuestionID = 1, editable if not synced)
            let questionAnswer = '';
            if (order.QuestionList && Array.isArray(order.QuestionList)) {
                const question1 = order.QuestionList.find(q => q.QuestionID == 1);
                questionAnswer = question1 ? question1.QuestionAnswer : '';
            }
            
            if (canEdit) {
                html += `
                    <div class="field-row">
                        <span class="field-label">Question Answer (ID=1):</span>
                        <input type="text" class="editable-field" data-field="QuestionAnswer1" value="${questionAnswer}" onchange="updateQuestionAnswer(1, this.value)">
                    </div>
                `;
            } else {
                html += `
                    <div class="field-row">
                        <span class="field-label">Question Answer (ID=1):</span>
                        <span class="field-value">${questionAnswer || 'N/A'}</span>
                    </div>
                `;
            }
            
            // Shipment Information (editable if not synced)
            if (order.ShipmentList && Array.isArray(order.ShipmentList) && order.ShipmentList.length > 0) {
                const shipment = order.ShipmentList[0];
                
                if (canEdit) {
                    html += `
                        <div class="field-row">
                            <span class="field-label">Ship First Name:</span>
                            <input type="text" class="editable-field" data-field="ShipmentFirstName" value="${shipment.ShipmentFirstName || ''}" onchange="updateShipmentField('ShipmentFirstName', this.value)">
                        </div>
                        <div class="field-row">
                            <span class="field-label">Ship Last Name:</span>
                            <input type="text" class="editable-field" data-field="ShipmentLastName" value="${shipment.ShipmentLastName || ''}" onchange="updateShipmentField('ShipmentLastName', this.value)">
                        </div>
                    `;
                } else {
                    html += `
                        <div class="field-row">
                            <span class="field-label">Ship First Name:</span>
                            <span class="field-value">${shipment.ShipmentFirstName || 'N/A'}</span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Ship Last Name:</span>
                            <span class="field-value">${shipment.ShipmentLastName || 'N/A'}</span>
                        </div>
                    `;
                }
                
                html += `
                    <div class="field-row">
                        <span class="field-label">Tracking Code:</span>
                        <span class="field-value">${shipment.ShipmentTrackingCode || 'N/A'}</span>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        function displayNetSuiteOrder(container, order) {
            if (!order) {
                container.innerHTML = '<p>Order not synced to NetSuite</p>';
                return;
            }
            
            const html = `
                <div class="field-row">
                    <span class="field-label">Sales Order ID:</span>
                    <span class="field-value">${order.id || 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Transaction ID:</span>
                    <span class="field-value">${order.tranid || 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Status:</span>
                    <span class="field-value">${order.status ? order.status.refName : 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">External ID:</span>
                    <span class="field-value">${order.externalId || 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Tracking Numbers:</span>
                    <span class="field-value">${order.linkedTrackingNumbers || 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Ship Date:</span>
                    <span class="field-value">${order.shipDate || 'N/A'}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Transaction Date:</span>
                    <span class="field-value">${order.tranDate || 'N/A'}</span>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function displayActionButtons(container, data) {
            let html = '';
            
            if (!data.is_synced) {
                // Order not synced - show sync button
                html += `
                    <button type="button" class="btn btn-success" onclick="syncToNetSuite()">
                        Sync to NetSuite
                    </button>
                `;
                
                // Show save changes button if there are editable fields
                html += `
                    <button type="button" class="btn btn-warning" onclick="saveChanges()" id="save-changes-btn" disabled>
                        Save Changes
                    </button>
                `;
            } else if (data.can_update_from_netsuite) {
                // Order synced and can be updated from NetSuite
                html += `
                    <button type="button" class="btn btn-success" onclick="updateFromNetSuite()">
                        Update 3DCart from NetSuite
                    </button>
                `;
            }
            
            container.innerHTML = html;
            container.classList.remove('hidden');
        }
        
        function updateEditableField(field, value) {
            editableFields[field] = value;
            enableSaveButton();
        }
        
        function updateQuestionAnswer(questionId, value) {
            if (!editableFields.QuestionList) {
                editableFields.QuestionList = [];
            }
            
            // Find existing question or create new one
            let question = editableFields.QuestionList.find(q => q.QuestionID == questionId);
            if (!question) {
                question = { QuestionID: questionId };
                editableFields.QuestionList.push(question);
            }
            
            question.QuestionAnswer = value;
            enableSaveButton();
        }
        
        function updateShipmentField(field, value) {
            if (!editableFields.ShipmentList) {
                editableFields.ShipmentList = [{}];
            }
            
            editableFields.ShipmentList[0][field] = value;
            enableSaveButton();
        }
        
        function enableSaveButton() {
            const saveBtn = document.getElementById('save-changes-btn');
            if (saveBtn) {
                saveBtn.disabled = false;
            }
        }
        
        function saveChanges() {
            if (Object.keys(editableFields).length === 0) {
                showAlert('No changes to save', 'info');
                return;
            }
            
            const orderId = currentOrderData.threedcart_order.OrderID;
            
            showLoading(true);
            
            fetch('order-status-manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_threedcart_fields&order_id=${encodeURIComponent(orderId)}&update_data=${encodeURIComponent(JSON.stringify(editableFields))}`
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                
                if (data.success) {
                    showAlert('Order fields updated successfully', 'success');
                    editableFields = {};
                    document.getElementById('save-changes-btn').disabled = true;
                } else {
                    showAlert(data.error || 'Failed to update order fields', 'danger');
                }
            })
            .catch(error => {
                showLoading(false);
                showAlert('Network error: ' + error.message, 'danger');
            });
        }
        
        function syncToNetSuite() {
            const orderId = currentOrderData.threedcart_order.OrderID;
            
            if (!confirm('Are you sure you want to sync this order to NetSuite?')) {
                return;
            }
            
            showLoading(true);
            
            fetch('order-status-manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=sync_to_netsuite&order_id=${encodeURIComponent(orderId)}`
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                
                if (data.success) {
                    showAlert('Order synced to NetSuite successfully', 'success');
                    // Refresh the order information
                    setTimeout(() => searchOrder(), 2000);
                } else {
                    showAlert(data.error || 'Failed to sync order to NetSuite', 'danger');
                }
            })
            .catch(error => {
                showLoading(false);
                showAlert('Network error: ' + error.message, 'danger');
            });
        }
        
        function updateFromNetSuite() {
            const orderId = currentOrderData.threedcart_order.OrderID;
            
            if (!confirm('Are you sure you want to update the 3DCart order status from NetSuite?')) {
                return;
            }
            
            showLoading(true);
            
            fetch('order-status-manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_from_netsuite&order_id=${encodeURIComponent(orderId)}`
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                
                if (data.success) {
                    if (data.result.updated) {
                        showAlert(`Order status updated successfully: ${data.result.reason}`, 'success');
                    } else {
                        showAlert(`No update needed: ${data.result.reason}`, 'info');
                    }
                    // Refresh the order information
                    setTimeout(() => searchOrder(), 2000);
                } else {
                    showAlert(data.error || 'Failed to update order from NetSuite', 'danger');
                }
            })
            .catch(error => {
                showLoading(false);
                showAlert('Network error: ' + error.message, 'danger');
            });
        }
        
        function getOrderStatusName(statusId) {
            const statusMap = {
                1: 'New',
                2: 'Processing',
                3: 'Partial',
                4: 'Shipped',
                5: 'Cancelled',
                6: 'Not Completed',
                7: 'Unpaid',
                8: 'Backordered',
                9: 'Pending Review',
                10: 'Partially Shipped'
            };
            
            return statusMap[statusId] || 'Unknown';
        }
        
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            
            container.innerHTML = '';
            container.appendChild(alertDiv);
            
            // Auto-hide success and info alerts after 5 seconds
            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 5000);
            }
        }
        
        function showLoading(show) {
            const loading = document.getElementById('loading');
            if (show) {
                loading.classList.add('show');
            } else {
                loading.classList.remove('show');
            }
        }
        
        function clearResults() {
            document.getElementById('order-results').classList.add('hidden');
            document.getElementById('alert-container').innerHTML = '';
            currentOrderData = null;
            editableFields = {};
        }
    </script>
</body>
</html>