<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

/**
 * Order Status Synchronization Service
 * 
 * Handles synchronization of order status and tracking information
 * between 3DCart and NetSuite systems.
 */
class OrderStatusSyncService {
    private $threeDCartService;
    private $netSuiteService;
    private $logger;
    private $config;
    
    public function __construct() {
        $this->threeDCartService = new ThreeDCartService();
        $this->netSuiteService = new NetSuiteService();
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
    }
    
    /**
     * Get all orders by status using pagination
     * 
     * @param string|null $afterDate Optional date filter (YYYY-MM-DD format)
     * @return array All orders matching the criteria
     */
    private function getAllOrdersByStatus($afterDate = null) {
        $allOrders = [];
        $limit = 100;
        $offset = 0;
        $hasMoreOrders = true;
        
        $this->logger->info('Fetching all orders with pagination', [
            'after_date' => $afterDate,
            'limit_per_page' => $limit
        ]);
        
        while ($hasMoreOrders) {
            // Fetch orders for current page
            if ($afterDate) {
                $orders = $this->threeDCartService->getOrdersByStatusAfterDate(2, $afterDate, $limit, $offset);
            } else {
                $orders = $this->threeDCartService->getOrdersByStatus(2, $limit, $offset);
            }
            
            $orderCount = count($orders);
            
            $this->logger->info('Fetched page of orders', [
                'offset' => $offset,
                'count' => $orderCount
            ]);
            
            // Add orders to the collection
            if ($orderCount > 0) {
                $allOrders = array_merge($allOrders, $orders);
                $offset += $limit;
                
                // If we got fewer orders than the limit, we've reached the end
                if ($orderCount < $limit) {
                    $hasMoreOrders = false;
                }
            } else {
                // No more orders to fetch
                $hasMoreOrders = false;
            }
        }
        
        $this->logger->info('Completed fetching all orders', [
            'total_orders' => count($allOrders)
        ]);
        
        return $allOrders;
    }
    
    /**
     * Process daily status updates for orders with processing status
     */
    public function processDailyStatusUpdates($afterDate = null) {
        try {
            $this->logger->info('Starting daily order status update process', [
                'after_date' => $afterDate
            ]);
            
            // Get all 3DCart orders with processing status (status ID = 2) using pagination
            $processingOrders = $this->getAllOrdersByStatus($afterDate);
            
            $updatedCount = 0;
            $errorCount = 0;
            $results = [];
            
            foreach ($processingOrders as $order) {
                try {
                    $result = $this->processOrderStatusUpdate($order);
                    $results[] = $result;
                    
                    if ($result['updated']) {
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->logger->error('Error processing order status update', [
                        'order_id' => $order['OrderID'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    
                    $results[] = [
                        'order_id' => $order['OrderID'] ?? 'unknown',
                        'updated' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->logger->info('Completed daily order status update process', [
                'after_date' => $afterDate,
                'total_orders' => count($processingOrders),
                'updated_count' => $updatedCount,
                'error_count' => $errorCount
            ]);
            
            return [
                'success' => true,
                'total_orders' => count($processingOrders),
                'updated_count' => $updatedCount,
                'error_count' => $errorCount,
                'results' => $results,
                'after_date' => $afterDate
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to process daily status updates', [
                'after_date' => $afterDate,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process status update for a single order
     */
    public function processOrderStatusUpdate($order) {
        $orderId = $order['OrderID'];
        
        $this->logger->info('Processing order status update', [
            'order_id' => $orderId
        ]);
        
        // Check if order exists in NetSuite
        // External ID in NetSuite is formatted as "3DCART_{orderId}"
        $netSuiteSalesOrder = $this->netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
        
        if (!$netSuiteSalesOrder) {
            $this->logger->info('Order not found in NetSuite, skipping', [
                'order_id' => $orderId
            ]);
            
            return [
                'order_id' => $orderId,
                'updated' => false,
                'reason' => 'Order not synced to NetSuite'
            ];
        }
        
        // Get full NetSuite sales order details
        $fullSalesOrder = $this->netSuiteService->getSalesOrderById($netSuiteSalesOrder['id']);
        
        if (!$fullSalesOrder) {
            $this->logger->warning('Could not retrieve full sales order details', [
                'order_id' => $orderId,
                'netsuite_id' => $netSuiteSalesOrder['id']
            ]);
            
            return [
                'order_id' => $orderId,
                'updated' => false,
                'reason' => 'Could not retrieve NetSuite order details'
            ];
        }
        
        $netSuiteStatus = $fullSalesOrder['status']['refName'] ?? '';
        $trackingNumbers = $fullSalesOrder['linkedTrackingNumbers'] ?? '';
        $shipDate = $fullSalesOrder['shipDate'] ?? '';
        
        $this->logger->info('Retrieved NetSuite order status', [
            'order_id' => $orderId,
            'netsuite_status' => $netSuiteStatus,
            'has_tracking' => !empty($trackingNumbers),
            'has_ship_date' => !empty($shipDate)
        ]);
        
        // Determine if update is needed based on NetSuite status
        $newStatusId = null;
        $updateReason = '';
        
        if ($netSuiteStatus === 'Partially Fulfilled') {
            $newStatusId = 3; // 3DCart Partial status
            $updateReason = 'NetSuite status: Partially Fulfilled';
        } elseif ($netSuiteStatus === 'Billed') {
            $newStatusId = 4; // 3DCart Shipped status
            $updateReason = 'NetSuite status: Billed';
        }
        
        if ($newStatusId === null) {
            $this->logger->info('No status update needed', [
                'order_id' => $orderId,
                'netsuite_status' => $netSuiteStatus
            ]);
            
            return [
                'order_id' => $orderId,
                'updated' => false,
                'reason' => "NetSuite status '{$netSuiteStatus}' does not require 3DCart update"
            ];
        }
        
        // Update 3DCart order status and tracking
        $comments = "Status updated from NetSuite: {$netSuiteStatus}";
        
        $this->threeDCartService->updateOrderStatusAndTracking(
            $orderId,
            $newStatusId,
            $trackingNumbers,
            $comments,
            $shipDate
        );
        
        $this->logger->info('Successfully updated 3DCart order status', [
            'order_id' => $orderId,
            'new_status_id' => $newStatusId,
            'tracking_numbers' => $trackingNumbers,
            'ship_date' => $shipDate,
            'reason' => $updateReason
        ]);
        
        return [
            'order_id' => $orderId,
            'updated' => true,
            'new_status_id' => $newStatusId,
            'tracking_numbers' => $trackingNumbers,
            'ship_date' => $shipDate,
            'reason' => $updateReason
        ];
    }
    
    /**
     * Get order information for manual update form
     */
    public function getOrderInformation($orderId) {
        try {
            $this->logger->info('Retrieving order information for manual update', [
                'order_id' => $orderId
            ]);
            
            // Get 3DCart order
            $threeDCartOrder = $this->threeDCartService->getOrder($orderId);
            
            // Check if order exists in NetSuite
            // External ID in NetSuite is formatted as "3DCART_{orderId}"
            $netSuiteSalesOrder = $this->netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
            $fullNetSuiteOrder = null;
            
            if ($netSuiteSalesOrder) {
                $fullNetSuiteOrder = $this->netSuiteService->getSalesOrderById($netSuiteSalesOrder['id']);
            }
            
            $result = [
                'success' => true,
                'threedcart_order' => $threeDCartOrder,
                'netsuite_order' => $fullNetSuiteOrder,
                'is_synced' => $netSuiteSalesOrder !== null,
                'can_edit_threedcart' => $netSuiteSalesOrder === null, // Can only edit if not synced
                'can_update_from_netsuite' => false
            ];
            
            // Check if NetSuite order can be used to update 3DCart
            if ($fullNetSuiteOrder) {
                $netSuiteStatus = $fullNetSuiteOrder['status']['refName'] ?? '';
                $result['can_update_from_netsuite'] = in_array($netSuiteStatus, ['Billed', 'Partially Fulfilled']);
                $result['netsuite_status'] = $netSuiteStatus;
                $result['netsuite_tracking'] = $fullNetSuiteOrder['linkedTrackingNumbers'] ?? '';
                $result['netsuite_ship_date'] = $fullNetSuiteOrder['shipDate'] ?? '';
            }
            
            $this->logger->info('Retrieved order information', [
                'order_id' => $orderId,
                'is_synced' => $result['is_synced'],
                'can_edit_threedcart' => $result['can_edit_threedcart'],
                'can_update_from_netsuite' => $result['can_update_from_netsuite']
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve order information', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update 3DCart order from NetSuite (manual trigger)
     */
    public function updateOrderFromNetSuite($orderId) {
        try {
            $this->logger->info('Manual update of 3DCart order from NetSuite', [
                'order_id' => $orderId
            ]);
            
            // Get current 3DCart order
            $threeDCartOrder = $this->threeDCartService->getOrder($orderId);
            
            // Process the status update
            $result = $this->processOrderStatusUpdate($threeDCartOrder);
            
            if ($result['updated']) {
                $this->logger->info('Successfully updated 3DCart order from NetSuite', [
                    'order_id' => $orderId,
                    'new_status_id' => $result['new_status_id'],
                    'tracking_numbers' => $result['tracking_numbers']
                ]);
            }
            
            return [
                'success' => true,
                'result' => $result
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update 3DCart order from NetSuite', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}