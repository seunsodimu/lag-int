<?php

namespace Laguna\Integration\Controllers;

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\PayPalService;
use Laguna\Integration\Utils\Logger;

class PayPalController {
    private $netsuiteService;
    private $paypalService;
    private $logger;

    public function __construct() {
        $this->netsuiteService = new NetSuiteService();
        $this->paypalService = new PayPalService();
        $this->logger = Logger::getInstance();
    }

    /**
     * Handle the request to update Sales Orders with PayPal links
     */
    public function updateSalesOrdersWithPayPalLinks() {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderIds = $input['order_ids'] ?? null;

        if (!$orderIds) {
            $this->sendResponse(false, 'Missing order_ids in request body', [], 400);
            return;
        }

        // Convert single ID to array
        if (!is_array($orderIds)) {
            $orderIds = [$orderIds];
        }

        $results = [];
        foreach ($orderIds as $orderId) {
            $results[] = $this->processOrder($orderId);
        }

        $this->sendResponse(true, 'Processing complete', $results);
    }

    /**
     * Process a single Sales Order
     */
    private function processOrder($orderId) {
        $this->logger->info("Processing PayPal link for Sales Order: $orderId");

        try {
            // 1. Get Sales Order from NetSuite
            $order = $this->netsuiteService->getSalesOrderById($orderId);
            
            if (!$order) {
                return [
                    'order_id' => $orderId,
                    'success' => false,
                    'error' => 'Sales Order not found in NetSuite'
                ];
            }

            // Extract tranId and totalAmount
            // In NetSuite REST API, these are usually 'tranId' and 'total'
            $tranId = $order['tranId'] ?? $orderId;
            $totalAmount = $order['total'] ?? 0;

            if ($totalAmount <= 0) {
                return [
                    'order_id' => $orderId,
                    'success' => false,
                    'error' => 'Order total amount is 0 or negative'
                ];
            }

            // 2. Generate PayPal link
            $paymentLink = $this->paypalService->createPaymentLink([
                'tranId' => $tranId,
                'totalAmount' => $totalAmount
            ]);

            if (!$paymentLink) {
                return [
                    'order_id' => $orderId,
                    'success' => false,
                    'error' => 'Failed to generate PayPal payment link'
                ];
            }

            // 3. Update NetSuite Sales Order
            $updateData = [
                'custbody_paypal_payment_url' => $paymentLink
            ];

            $updateResult = $this->netsuiteService->updateSalesOrder($orderId, $updateData);

            if ($updateResult['success']) {
                return [
                    'order_id' => $orderId,
                    'tran_id' => $tranId,
                    'success' => true,
                    'payment_link' => $paymentLink
                ];
            } else {
                return [
                    'order_id' => $orderId,
                    'success' => false,
                    'error' => 'Failed to update NetSuite: ' . ($updateResult['error'] ?? 'Unknown error')
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error("Error processing PayPal link for Order $orderId", [
                'error' => $e->getMessage()
            ]);
            return [
                'order_id' => $orderId,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send JSON response
     */
    private function sendResponse($success, $message, $data = [], $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'results' => $data,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
    }
}
