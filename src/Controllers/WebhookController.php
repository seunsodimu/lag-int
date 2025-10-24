<?php

namespace Laguna\Integration\Controllers;

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\EnhancedEmailService;
use Laguna\Integration\Models\Order;
use Laguna\Integration\Models\Customer;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Exceptions\StoreCustomerNotFoundException;

/**
 * Webhook Controller
 * 
 * Handles incoming webhooks from 3DCart and processes orders.
 */
class WebhookController {
    private $threeDCartService;
    private $netSuiteService;
    private $emailService;
    private $logger;
    private $config;
    
    public function __construct() {
        $this->threeDCartService = new ThreeDCartService();
        $this->netSuiteService = new NetSuiteService();
        $this->emailService = new EnhancedEmailService(true); // true = webhook context
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
    }
    
    /**
     * Handle incoming webhook from 3DCart
     */
    public function handleWebhook() {
        try {
            // Get raw POST data
            $rawPayload = file_get_contents('php://input');
            
            if (empty($rawPayload)) {
                $this->respondWithError('Empty payload', 400);
                return;
            }
            
            // Verify webhook signature if configured
            $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
            if (!empty($this->config['webhook']['secret_key']) && !empty($signature)) {
                if (!$this->threeDCartService->verifyWebhookSignature($rawPayload, $signature)) {
                    $this->logger->warning('Invalid webhook signature', [
                        'signature' => $signature,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    $this->respondWithError('Invalid signature', 401);
                    return;
                }
            }
            
            // Process webhook payload
            $webhookData = $this->threeDCartService->processWebhookPayload($rawPayload);
            
            // Extract order ID
            $orderId = $webhookData['OrderID'] ?? null;
            if (!$orderId) {
                $this->respondWithError('Missing OrderID in webhook', 400);
                return;
            }
            
            $this->logger->info('Processing webhook for order', ['order_id' => $orderId]);
            
            // Check if this is a test webhook with complete order data
            $isTestWebhook = $this->isTestWebhook($webhookData);
            
            $this->logger->info('Webhook classification', [
                'order_id' => $orderId,
                'is_test_webhook' => $isTestWebhook,
                'has_order_items' => isset($webhookData['OrderItemList']),
                'has_billing_email' => isset($webhookData['BillingEmail']),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            // Process the order
            if ($isTestWebhook) {
                $this->logger->info('Processing as test webhook with complete data');
                $result = $this->processOrderFromWebhookData($webhookData);
            } else {
                $this->logger->info('Processing as production webhook - fetching complete data from 3DCart API');
                $result = $this->processOrder($orderId);
            }
            
            if ($result['success']) {
                $this->respondWithSuccess('Order processed successfully', $result);
            } else {
                $this->respondWithError($result['error'], 500);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->emailService->sendErrorNotification(
                'Webhook processing failed: ' . $e->getMessage(),
                ['order_id' => $orderId ?? 'unknown']
            );
            
            $this->respondWithError('Internal server error', 500);
        }
    }
    
    /**
     * Process a single order
     */
    public function processOrder($orderId, $retryCount = 0) {
        $maxRetries = $this->config['order_processing']['retry_attempts'];
        
        try {
            $this->logger->logOrderEvent($orderId, 'processing_started', [
                'retry_count' => $retryCount
            ]);
            
            // Get order data from 3DCart
            $orderData = $this->threeDCartService->getOrder($orderId);
            $order = new Order($orderData);
            
            // Validate order data
            $validationErrors = $order->validate();
            if (!empty($validationErrors)) {
                throw new \Exception('Order validation failed: ' . implode(', ', $validationErrors));
            }
            
            // Get or create customer in NetSuite using new payment method-based logic
            $netSuiteCustomerId = $this->netSuiteService->findOrCreateCustomerByPaymentMethod($orderData);
            
            // Check if order already exists in NetSuite
            $existingOrder = $this->netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
            if ($existingOrder) {
                $this->logger->info('Order already exists in NetSuite', [
                    'order_id' => $orderId,
                    'netsuite_order_id' => $existingOrder['id']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Order already exists',
                    'netsuite_order_id' => $existingOrder['id']
                ];
            }
            
            // Create sales order in NetSuite
            $netSuiteOrder = $this->netSuiteService->createSalesOrder($orderData, $netSuiteCustomerId);
            
            $this->logger->logOrderEvent($orderId, 'processing_completed', [
                'netsuite_order_id' => $netSuiteOrder['id'],
                'customer_id' => $netSuiteCustomerId
            ]);
            
            // Update 3DCart order status to indicate successful processing
            $statusUpdateResult = $this->update3DCartOrderStatus($orderId, 'success', $netSuiteOrder['id']);
            
            // Log the status update result for debugging
            $this->logger->info('3DCart status update attempt completed', [
                'order_id' => $orderId,
                'netsuite_order_id' => $netSuiteOrder['id'],
                'status_update_successful' => $statusUpdateResult
            ]);
            
            // Send success notification using new notification system
            try {
                $this->emailService->sendNotification(
                    '3dcart_success_webhook',
                    '3DCart Order Successfully Processed (Webhook)',
                    [
                        'Order ID' => $orderId,
                        'NetSuite Order ID' => $netSuiteOrder['id'],
                        'Customer ID' => $netSuiteCustomerId,
                        'Order Total' => '$' . number_format($order->getTotal(), 2),
                        'Items Count' => count($order->getItems()),
                        'Processing Type' => 'Webhook'
                    ]
                );
            } catch (\Exception $emailEx) {
                // Log email failure but don't fail the order processing
                $this->logger->warning('Failed to send success notification email', [
                    'order_id' => $orderId,
                    'error' => $emailEx->getMessage()
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Order processed successfully',
                'netsuite_order_id' => $netSuiteOrder['id'],
                'customer_id' => $netSuiteCustomerId
            ];
            
        } catch (StoreCustomerNotFoundException $e) {
            // Special handling for Store Shipment orders where store customer is not found
            // Do NOT retry - send email notification with link to order manager
            $this->logger->error('Store customer not found for Store Shipment order', [
                'order_id' => $orderId,
                'customer_email' => $e->getCustomerEmail(),
                'error' => $e->getMessage()
            ]);
            
            // Build link to order in Order Status Manager
            $baseUrl = rtrim($this->config['app']['base_url'], '/');
            $orderLink = $baseUrl . '/public/order-status-manager.php?order_id=' . $orderId;
            
            // Send error notification with link to order
            try {
                $this->emailService->sendNotification(
                    'store_customer_not_found',
                    'Store Customer Not Found - Action Required',
                    [
                        'Order ID' => $orderId,
                        'Payment Method' => 'Store Shipment',
                        'Customer Email' => $e->getCustomerEmail(),
                        'Error Message' => $e->getMessage(),
                        'Action Required' => 'Create the store customer in NetSuite first, then retry processing this order',
                        'Order Link' => $orderLink,
                        'Processing Type' => 'Webhook'
                    ]
                );
            } catch (\Exception $emailEx) {
                // Log email failure but continue error handling
                $this->logger->warning('Failed to send store customer not found notification email', [
                    'order_id' => $orderId,
                    'error' => $emailEx->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount,
                'requires_manual_action' => true,
                'order_link' => $orderLink
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Order processing failed', [
                'order_id' => $orderId,
                'retry_count' => $retryCount,
                'error' => $e->getMessage()
            ]);
            
            // Retry logic (not for StoreCustomerNotFoundException)
            if ($retryCount < $maxRetries) {
                $this->logger->info('Retrying order processing', [
                    'order_id' => $orderId,
                    'retry_count' => $retryCount + 1
                ]);
                
                sleep($this->config['order_processing']['retry_delay']);
                return $this->processOrder($orderId, $retryCount + 1);
            }
            
            // Prepare sales order payload for debugging (capture what would have been sent to NetSuite)
            $salesOrderPayload = 'N/A';
            try {
                // Try to generate the sales order payload that would have been sent
                if (isset($netSuiteCustomerId) && isset($orderData)) {
                    // Create a minimal payload structure for debugging
                    $debugPayload = [
                        'entity' => ['id' => (int)$netSuiteCustomerId],
                        'subsidiary' => ['id' => $this->config['netsuite']['default_subsidiary_id'] ?? 1],
                        'externalId' => '3DCART_' . $orderId,
                        'custbodycustbody5' => $orderId,
                        'order_total' => $order->getTotal() ?? 'N/A',
                        'items_count' => count($order->getItems() ?? []),
                        'customer_email' => $orderData['BillingEmailAddress'] ?? 'N/A'
                    ];
                    $salesOrderPayload = json_encode($debugPayload, JSON_PRETTY_PRINT);
                }
            } catch (\Exception $payloadException) {
                $salesOrderPayload = 'Error generating payload: ' . $payloadException->getMessage();
            }
            
            // Send error notification using new notification system with sales order payload
            try {
                $this->emailService->sendNotification(
                    '3dcart_failed_webhook',
                    '3DCart Order Processing Failed (Webhook)',
                    [
                        'Order ID' => $orderId,
                        'Error Message' => $e->getMessage(),
                        'Retry Count' => $retryCount,
                        'Max Retries' => $maxRetries,
                        'Customer ID' => $netSuiteCustomerId ?? 'N/A',
                        'Order Total' => isset($order) ? '$' . number_format($order->getTotal(), 2) : 'N/A',
                        'Customer Email' => $orderData['BillingEmailAddress'] ?? 'N/A',
                        'Processing Type' => 'Webhook',
                        'Sales Order Payload' => $salesOrderPayload
                    ]
                );
            } catch (\Exception $emailEx) {
                // Log email failure but don't affect error handling
                $this->logger->warning('Failed to send failed order notification email', [
                    'order_id' => $orderId,
                    'error' => $emailEx->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount
            ];
        }
    }
    
    /**
     * Process a single order with a specific customer ID
     */
    public function processOrderWithCustomer($orderId, $customerId, $retryCount = 0) {
        $maxRetries = $this->config['order_processing']['retry_attempts'];
        
        try {
            $this->logger->logOrderEvent($orderId, 'processing_started_with_custom_customer', [
                'retry_count' => $retryCount,
                'customer_id' => $customerId
            ]);
            
            // Get order data from 3DCart
            $orderData = $this->threeDCartService->getOrder($orderId);
            $order = new Order($orderData);
            
            // Validate order data
            $validationErrors = $order->validate();
            if (!empty($validationErrors)) {
                throw new \Exception('Order validation failed: ' . implode(', ', $validationErrors));
            }
            
            // Check if order already exists in NetSuite
            $existingOrder = $this->netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
            if ($existingOrder) {
                $this->logger->info('Order already exists in NetSuite', [
                    'order_id' => $orderId,
                    'netsuite_order_id' => $existingOrder['id']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Order already exists',
                    'netsuite_order_id' => $existingOrder['id']
                ];
            }
            
            // Create sales order in NetSuite with the specified customer ID
            $netSuiteOrder = $this->netSuiteService->createSalesOrder($orderData, $customerId);
            
            $this->logger->logOrderEvent($orderId, 'processing_completed', [
                'netsuite_order_id' => $netSuiteOrder['id'],
                'customer_id' => $customerId
            ]);
            
            // Update 3DCart order status to indicate successful processing
            $statusUpdateResult = $this->update3DCartOrderStatus($orderId, 'success', $netSuiteOrder['id']);
            
            // Log the status update result for debugging
            $this->logger->info('3DCart status update attempt completed', [
                'order_id' => $orderId,
                'netsuite_order_id' => $netSuiteOrder['id'],
                'status_update_successful' => $statusUpdateResult
            ]);
            
            // Send success notification using new notification system
            try {
                $this->emailService->sendNotification(
                    '3dcart_success_manual',
                    '3DCart Order Successfully Processed (Manual with Custom Customer)',
                    [
                        'Order ID' => $orderId,
                        'NetSuite Order ID' => $netSuiteOrder['id'],
                        'Customer ID' => $customerId,
                        'Order Total' => '$' . number_format($order->getTotal(), 2),
                        'Items Count' => count($order->getItems()),
                        'Processing Type' => 'Manual with Custom Customer ID'
                    ]
                );
            } catch (\Exception $emailEx) {
                // Log email failure but don't fail the order processing
                $this->logger->warning('Failed to send success notification email for manual custom customer', [
                    'order_id' => $orderId,
                    'customer_id' => $customerId,
                    'error' => $emailEx->getMessage()
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Order processed successfully',
                'netsuite_order_id' => $netSuiteOrder['id'],
                'customer_id' => $customerId
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Order processing with custom customer failed', [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'retry_count' => $retryCount,
                'error' => $e->getMessage()
            ]);
            
            // Retry logic
            if ($retryCount < $maxRetries) {
                $this->logger->info('Retrying order processing with custom customer', [
                    'order_id' => $orderId,
                    'customer_id' => $customerId,
                    'retry_count' => $retryCount + 1
                ]);
                
                sleep($this->config['order_processing']['retry_delay']);
                return $this->processOrderWithCustomer($orderId, $customerId, $retryCount + 1);
            }
            
            // Send error notification
            try {
                $this->emailService->sendNotification(
                    '3dcart_failed_manual',
                    '3DCart Order Processing Failed (Manual with Custom Customer)',
                    [
                        'Order ID' => $orderId,
                        'Customer ID' => $customerId,
                        'Error Message' => $e->getMessage(),
                        'Retry Count' => $retryCount,
                        'Max Retries' => $maxRetries,
                        'Order Total' => isset($order) ? '$' . number_format($order->getTotal(), 2) : 'N/A',
                        'Processing Type' => 'Manual with Custom Customer ID'
                    ]
                );
            } catch (\Exception $emailEx) {
                // Log email failure but don't affect error handling
                $this->logger->warning('Failed to send failed order notification email for manual custom customer', [
                    'order_id' => $orderId,
                    'customer_id' => $customerId,
                    'error' => $emailEx->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount
            ];
        }
    }
    
    /**
     * Get existing customer or create new one in NetSuite
     */
    private function getOrCreateCustomer(Customer $customer, $orderId = null, $orderData = null) {
        $email = $customer->getEmail();
        
        // Try to find existing customer by email if email is available
        if (!empty($email)) {
            $existingCustomer = $this->netSuiteService->findCustomerByEmail($email);

            if ($existingCustomer) {
                $this->logger->info('Using existing customer found by email', [
                    'email' => $email,
                    'customer_id' => $existingCustomer['id']
                ]);
                return $existingCustomer['id'];
            }
        }
        
        // Try to find existing customer by phone number if phone is available
        $phone = $customer->getPhone();
        if (!empty($phone)) {
            $existingCustomer = $this->netSuiteService->findCustomerByPhone($phone);

            if ($existingCustomer) {
                $this->logger->info('Using existing customer found by phone', [
                    'phone' => $phone,
                    'customer_id' => $existingCustomer['id'],
                    'customer_email' => $existingCustomer['email'] ?? 'N/A'
                ]);
                return $existingCustomer['id'];
            }
        }
        
        if (empty($email)) {
            $this->logger->info('Customer has no valid email - will create new customer without email', [
                'customer_name' => $customer->getFullName(),
                'order_id' => $orderId
            ]);
        }
        
        // Create new customer if auto-creation is enabled
        if ($this->config['order_processing']['auto_create_customers']) {
            $validationErrors = $customer->validate();
            if (!empty($validationErrors)) {
                throw new \Exception('Customer validation failed: ' . implode(', ', $validationErrors));
            }
            
            // Prepare raw customer data for NetSuiteService to process
            // NetSuiteService will handle the proper formatting and address building
            $rawCustomerData = [
                'firstname' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'company' => $customer->getCompany(),
                'isPerson' => true
            ];
            
            // Add billing information from order data
            if (isset($orderData['BillingFirstName'])) {
                $rawCustomerData['BillingFirstName'] = $orderData['BillingFirstName'];
                $rawCustomerData['BillingLastName'] = $orderData['BillingLastName'];
                $rawCustomerData['BillingCompany'] = $orderData['BillingCompany'];
                $rawCustomerData['BillingAddress'] = $orderData['BillingAddress'];
                $rawCustomerData['BillingAddress2'] = $orderData['BillingAddress2'];
                $rawCustomerData['BillingCity'] = $orderData['BillingCity'];
                $rawCustomerData['BillingState'] = $orderData['BillingState'];
                $rawCustomerData['BillingZipCode'] = $orderData['BillingZipCode'];
                $rawCustomerData['BillingCountry'] = $orderData['BillingCountry'];
                $rawCustomerData['BillingPhoneNumber'] = $orderData['BillingPhoneNumber'];
                $rawCustomerData['BillingEmail'] = $orderData['BillingEmail'];
            }
            
            // Add shipping information from order data
            if (isset($orderData['ShipmentList'])) {
                $rawCustomerData['ShipmentList'] = $orderData['ShipmentList'];
            }
            
            // Make company name unique by adding order ID to avoid NetSuite unique constraint violations
            if (!empty($orderId) && !empty($rawCustomerData['company'])) {
                $originalCompanyName = $rawCustomerData['company'];
                $rawCustomerData['company'] = $orderId . ': ' . $originalCompanyName;
                
                $this->logger->info('Made company name unique for new customer', [
                    'email' => $email,
                    'order_id' => $orderId,
                    'original_company' => $originalCompanyName,
                    'unique_company' => $rawCustomerData['company']
                ]);
            }
            
            // Let NetSuiteService handle the proper formatting and validation
            $newCustomer = $this->netSuiteService->createCustomer($rawCustomerData);
            
            $this->logger->info('Created new customer', [
                'email' => $email,
                'customer_id' => $newCustomer['id']
            ]);
            
            return $newCustomer['id'];
        } else {
            throw new \Exception("Customer not found and auto-creation is disabled: {$email}");
        }
    }
    
    /**
     * Respond with success
     */
    private function respondWithSuccess($message, $data = []) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Respond with error
     */
    private function respondWithError($message, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Check if this is a test webhook with complete order data
     */
    private function isTestWebhook($webhookData) {
        // Check for explicit test indicators first
        $hasTestIndicator = isset($webhookData['_test']) || 
                           isset($webhookData['test']) ||
                           (isset($_SERVER['HTTP_USER_AGENT']) && 
                            (strpos($_SERVER['HTTP_USER_AGENT'], 'Postman') !== false ||
                             strpos($_SERVER['HTTP_USER_AGENT'], 'Test') !== false));
        
        // If explicit test indicator, it's definitely a test
        if ($hasTestIndicator) {
            return true;
        }
        
        // For production webhooks, be very strict about complete data
        // OrderItemList is the key indicator - real 3DCart webhooks rarely include this
        if (!isset($webhookData['OrderItemList']) || 
            !is_array($webhookData['OrderItemList']) || 
            empty($webhookData['OrderItemList'])) {
            return false; // No items = definitely not a test webhook
        }
        
        // If it has items, check other required fields
        $hasCompleteData = isset($webhookData['BillingEmail']) && 
                          !empty($webhookData['BillingEmail']) &&
                          filter_var($webhookData['BillingEmail'], FILTER_VALIDATE_EMAIL) &&
                          isset($webhookData['BillingFirstName']) &&
                          !empty($webhookData['BillingFirstName']) &&
                          isset($webhookData['OrderDate']) &&
                          isset($webhookData['OrderStatusID']);
        
        // Only treat as test webhook if it has items AND complete data
        return $hasCompleteData;
    }
    
    /**
     * Process order directly from webhook data (for testing)
     */
    public function processOrderFromWebhookData($orderData, $retryCount = 0) {
        $maxRetries = $this->config['order_processing']['retry_attempts'];
        $orderId = $orderData['OrderID'] ?? 'test';
        
        try {
            $this->logger->logOrderEvent($orderId, 'processing_started_from_webhook', [
                'retry_count' => $retryCount,
                'is_test' => true
            ]);
            
            // Create order object from webhook data
            $order = new Order($orderData);
            
            // Validate order data
            $validationErrors = $order->validate();
            if (!empty($validationErrors)) {
                throw new \Exception('Order validation failed: ' . implode(', ', $validationErrors));
            }
            
            // Get or create customer in NetSuite using new payment method-based logic
            $netSuiteCustomerId = $this->netSuiteService->findOrCreateCustomerByPaymentMethod($orderData);
            
            // Check if order already exists in NetSuite (skip for test orders)
            if (!isset($orderData['_test']) && !isset($orderData['test'])) {
                $existingOrder = $this->netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
                if ($existingOrder) {
                    $this->logger->info('Order already exists in NetSuite', [
                        'order_id' => $orderId,
                        'netsuite_order_id' => $existingOrder['id']
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => 'Order already exists',
                        'netsuite_order_id' => $existingOrder['id']
                    ];
                }
            }
            
            // For test orders, simulate NetSuite operations without actual API calls
            if (isset($orderData['_test']) || isset($orderData['test'])) {
                // Simulate customer lookup/creation
                $simulatedCustomerId = 'TEST_CUSTOMER_' . time();
                
                // Simulate order creation
                $simulatedOrderId = 'TEST_ORDER_' . time();
                
                $this->logger->info('Test order processed successfully (simulated)', [
                    'order_id' => $orderId,
                    'customer_email' => $customer->getEmail(),
                    'total' => $order->getTotal(),
                    'items_count' => count($order->getItems()),
                    'simulated_customer_id' => $simulatedCustomerId,
                    'simulated_order_id' => $simulatedOrderId
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Test order processed successfully (simulated)',
                    'test_mode' => true,
                    'order_summary' => $order->getSummary(),
                    'customer_summary' => $customer->getSummary(),
                    'simulated_netsuite_customer_id' => $simulatedCustomerId,
                    'simulated_netsuite_order_id' => $simulatedOrderId,
                    'validation_passed' => true,
                    'note' => 'This was a test order - no actual NetSuite records were created'
                ];
            }
            
            // Create sales order in NetSuite
            $netSuiteOrder = $this->netSuiteService->createSalesOrder($orderData, $netSuiteCustomerId);
            
            $netSuiteOrderId = ($netSuiteOrder && isset($netSuiteOrder['id'])) ? $netSuiteOrder['id'] : 'Unknown';
            
            $this->logger->logOrderEvent($orderId, 'processing_completed_from_webhook', [
                'netsuite_order_id' => $netSuiteOrderId,
                'customer_id' => $netSuiteCustomerId
            ]);
            
            // Send success notification
            $this->emailService->sendOrderNotification($orderId, 'Successfully Processed (Webhook)', [
                'NetSuite Order ID' => $netSuiteOrderId,
                'Customer ID' => $netSuiteCustomerId,
                'Order Total' => '$' . number_format($order->getTotal(), 2),
                'Items Count' => count($order->getItems()),
                'Source' => 'Direct Webhook Data'
            ]);
            
            return [
                'success' => true,
                'message' => 'Order processed successfully from webhook data',
                'netsuite_order_id' => $netSuiteOrderId,
                'customer_id' => $netSuiteCustomerId
            ];
            
        } catch (StoreCustomerNotFoundException $e) {
            // Store Shipment order with customer not found - requires manual action
            $orderData = $orderData ?? []; // Ensure $orderData is defined for error handling
            $this->logger->error('Store customer not found for Store Shipment order (webhook)', [
                'order_id' => $orderId,
                'customer_email' => $e->getCustomerEmail(),
                'error' => $e->getMessage()
            ]);
            
            // Build direct link to order in Order Status Manager
            $baseUrl = $this->config['app']['base_url'] ?? 'http://localhost/lag-int';
            $orderLink = $baseUrl . '/public/order-status-manager.php?order_id=' . urlencode($orderId);
            
            // Send notification with link to order
            $this->emailService->sendFailedOrderNotification('store_customer_not_found', [
                'order_id' => $orderId,
                'payment_method' => $orderData['BillingPaymentMethod'] ?? 'Store Shipment',
                'customer_email' => $e->getCustomerEmail(),
                'error_message' => $e->getMessage(),
                'action_required' => 'Create the store customer in NetSuite first, then retry this order',
                'order_link' => $orderLink
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'requires_manual_action' => true,
                'customer_email' => $e->getCustomerEmail()
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Order processing from webhook data failed', [
                'order_id' => $orderId,
                'retry_count' => $retryCount,
                'error' => $e->getMessage()
            ]);
            
            // Retry logic
            if ($retryCount < $maxRetries) {
                $this->logger->info('Retrying order processing from webhook data', [
                    'order_id' => $orderId,
                    'retry_count' => $retryCount + 1
                ]);
                
                sleep($this->config['order_processing']['retry_delay']);
                return $this->processOrderFromWebhookData($orderData, $retryCount + 1);
            }
            
            // Send error notification
            $this->emailService->sendOrderNotification($orderId, 'Processing Failed (Webhook)', [
                'Error' => $e->getMessage(),
                'Retry Count' => $retryCount,
                'Max Retries' => $maxRetries,
                'Source' => 'Direct Webhook Data'
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount
            ];
        }
    }
    
    /**
     * Process multiple orders (for batch processing)
     */
    public function processBatchOrders($orderIds) {
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($orderIds as $orderId) {
            $result = $this->processOrder($orderId);
            $results[$orderId] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }
        
        $this->logger->info('Batch processing completed', [
            'total_orders' => count($orderIds),
            'successful' => $successCount,
            'failed' => $failureCount
        ]);
        
        // Send batch summary notification
        $this->emailService->sendOrderNotification('Batch', 'Batch Processing Completed', [
            'Total Orders' => count($orderIds),
            'Successful' => $successCount,
            'Failed' => $failureCount,
            'Success Rate' => round(($successCount / count($orderIds)) * 100, 2) . '%'
        ]);
        
        return [
            'success' => $failureCount === 0,
            'results' => $results,
            'summary' => [
                'total' => count($orderIds),
                'successful' => $successCount,
                'failed' => $failureCount
            ]
        ];
    }
    
    /**
     * Update 3DCart order status after successful processing
     */
    private function update3DCartOrderStatus($orderId, $type, $netSuiteOrderId = null, $errorMessage = null) {
        try {
            // Only update status for successful processing
            if ($type !== 'success') {
                $this->logger->info('Skipping 3DCart status update - only success status updates are enabled', [
                    'order_id' => $orderId,
                    'type' => $type
                ]);
                return false;
            }
            
            // Check if status updates are enabled
            if (!$this->config['order_processing']['update_3dcart_status']) {
                $this->logger->info('3DCart status updates disabled, skipping status update', [
                    'order_id' => $orderId,
                    'type' => $type
                ]);
                return false;
            }
            
            // Set status to Processing (2) for successful orders
            $statusId = $this->config['order_processing']['success_status_id'];
            $comments = $this->config['order_processing']['status_comments'] 
                ? "Order successfully synced to NetSuite. NetSuite Order ID: {$netSuiteOrderId}"
                : '';
            
            $this->logger->info('Updating 3DCart order status to Processing', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'netsuite_order_id' => $netSuiteOrderId,
                'comments' => $comments
            ]);
            
            // Update the order status in 3DCart
            $result = $this->threeDCartService->updateOrderStatus($orderId, $statusId, $comments);
            
            $this->logger->info('3DCart order status updated successfully to Processing', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'netsuite_order_id' => $netSuiteOrderId,
                'api_result' => $result
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            // Log error but don't fail the entire process
            $this->logger->error('Failed to update 3DCart order status to Processing', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'netsuite_order_id' => $netSuiteOrderId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Optionally send notification about status update failure
            $this->emailService->sendErrorNotification(
                "Failed to update 3DCart order status to Processing for Order #{$orderId}: " . $e->getMessage(),
                [
                    'order_id' => $orderId,
                    'netsuite_order_id' => $netSuiteOrderId,
                    'error_details' => $e->getMessage()
                ]
            );
            
            return false;
        }
    }
}