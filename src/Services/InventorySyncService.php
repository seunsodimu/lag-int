<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;

/**
 * Inventory Synchronization Service
 * 
 * Synchronizes inventory between NetSuite and 3DCart
 * - Fetches products from 3DCart API v1
 * - Looks up each product in NetSuite by SKU
 * - Updates product stock in 3DCart with NetSuite quantity on hand
 */
class InventorySyncService {
    private $threeDCartClient;
    private $threeDCartV2Client;
    private $netSuiteService;
    private $logger;
    private $credentials;
    private $config;
    private $threeDCartV1BaseUrl = 'https://apirest.3dcart.com/3dCartWebAPI/v1/';
    private $threeDCartV2BaseUrl = 'https://apirest.3dcart.com/3dCartWebAPI/v2/';
    
    public function __construct() {
        $this->credentials = require __DIR__ . '/../../config/credentials.php';
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->logger = Logger::getInstance();
        $this->netSuiteService = new NetSuiteService();
        
        // Initialize Guzzle client for 3DCart v1 API
        $this->threeDCartClient = new Client([
            'base_uri' => $this->threeDCartV1BaseUrl,
            'timeout' => 60,
            'verify' => false, // Disable SSL verification for development - CHANGE IN PRODUCTION
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'SecureURL' => $this->credentials['3dcart']['secure_url'],
                'PrivateKey' => $this->credentials['3dcart']['private_key'],
                'Token' => $this->credentials['3dcart']['token'],
            ]
        ]);
        
        // Initialize Guzzle client for 3DCart v2 API (for batch updates)
        $this->threeDCartV2Client = new Client([
            'base_uri' => $this->threeDCartV2BaseUrl,
            'timeout' => 60,
            'verify' => false, // Disable SSL verification for development - CHANGE IN PRODUCTION
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'SecureURL' => $this->credentials['3dcart']['secure_url'],
                'PrivateKey' => $this->credentials['3dcart']['private_key'],
                'Token' => $this->credentials['3dcart']['token'],
            ]
        ]);
    }
    
    /**
     * Synchronize inventory from NetSuite to 3DCart
     * 
     * @param array $filters Optional filters for 3DCart products (e.g., ['limit' => 100, 'offset' => 0])
     * @return array Result array with sync statistics
     */
    public function syncInventory($filters = []) {
        $this->logger->info('Starting inventory synchronization', ['filters' => $filters]);
        
        $result = [
            'success' => false,
            'start_time' => date('Y-m-d H:i:s'),
            'total_products' => 0,
            'synced_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'products' => [],
            'errors' => []
        ];
        
        try {
            // Fetch products from 3DCart
            $products = $this->fetchProductsFrom3DCart($filters);
            $result['total_products'] = count($products);
            
            $this->logger->info('Retrieved products from 3DCart', [
                'count' => count($products)
            ]);
            
            // Collect products that need updating
            $productsToUpdate = [];
            
            // Process each product to collect updates
            foreach ($products as $product) {
                $syncResult = $this->syncSingleProduct($product);
                
                if ($syncResult['success']) {
                    // Product needs updating - collect it for batch update
                    $productsToUpdate[] = [
                        'product' => $product,
                        'syncResult' => $syncResult
                    ];
                } elseif ($syncResult['skipped']) {
                    $result['skipped_count']++;
                } else {
                    $result['error_count']++;
                    $result['errors'][] = $syncResult['error'];
                }
            }
            
            // Batch update products in 3DCart if there are any to update
            if (!empty($productsToUpdate)) {
                $batchUpdateResult = $this->batchUpdateProductsIn3DCart($productsToUpdate);
                
                if ($batchUpdateResult['success']) {
                    $result['synced_count'] = count($productsToUpdate);
                    $result['products'] = array_column($productsToUpdate, 'syncResult');
                    
                    $this->logger->info('Batch update completed successfully', [
                        'products_updated' => count($productsToUpdate)
                    ]);
                } else {
                    $result['error_count'] += count($productsToUpdate);
                    $result['errors'][] = $batchUpdateResult['error'];
                    
                    $this->logger->error('Batch update failed', [
                        'error' => $batchUpdateResult['error'],
                        'products_affected' => count($productsToUpdate)
                    ]);
                }
            }
            
            $result['success'] = true;
            $result['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->info('Inventory synchronization completed', [
                'total' => $result['total_products'],
                'synced' => $result['synced_count'],
                'skipped' => $result['skipped_count'],
                'errors' => $result['error_count']
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
            $result['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->error('Inventory synchronization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $result;
        }
    }
    
    /**
     * Fetch products from 3DCart API v1
     * 
     * @param array $filters Optional filters
     * @return array Array of products
     */
    private function fetchProductsFrom3DCart($filters = []) {
        try {
            $queryParams = array_merge([
                'limit' => 100,
                'offset' => 0
            ], $filters);
            
            $this->logger->debug('Fetching products from 3DCart v1 API', [
                'url' => $this->threeDCartV1BaseUrl . 'Products',
                'params' => $queryParams
            ]);
            
            $startTime = microtime(true);
            $response = $this->threeDCartClient->get('Products', [
                'query' => $queryParams
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart-v1', '/Products', 'GET', $response->getStatusCode(), $duration);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            // 3DCart API v1 returns the products array directly or nested depending on response
            if (isset($responseData['Products'])) {
                return $responseData['Products'];
            } elseif (is_array($responseData)) {
                return $responseData;
            } else {
                throw new \Exception('Unexpected 3DCart API response format');
            }
            
        } catch (RequestException $e) {
            $errorDetails = [
                'error' => $e->getMessage(),
                'url' => $this->threeDCartV1BaseUrl . 'Products',
                'filters' => $filters
            ];
            
            if ($e->hasResponse()) {
                $errorDetails['status_code'] = $e->getResponse()->getStatusCode();
                $errorDetails['response_body'] = $e->getResponse()->getBody()->getContents();
            }
            
            $this->logger->error('Failed to fetch products from 3DCart', $errorDetails);
            throw new \Exception('Failed to fetch products from 3DCart: ' . $e->getMessage());
        }
    }
    
    /**
     * Synchronize a single product
     * 
     * @param array $product Product data from 3DCart
     * @return array Sync result with success, skipped, or error status
     */
    private function syncSingleProduct($product) {
        try {
            // Extract SKU from product
            if (!isset($product['SKUInfo']) || !isset($product['SKUInfo']['SKU'])) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'sku' => 'unknown',
                    'reason' => 'No SKU found in product'
                ];
            }
            
            $sku = $product['SKUInfo']['SKU'];
            $productId = $product['id'] ?? $product['ProductID'] ?? null;
            
            $this->logger->debug('Processing product', [
                'sku' => $sku,
                'product_id' => $productId
            ]);
            
            // Search for item in NetSuite by SKU
            $netSuiteItem = $this->findItemInNetSuite($sku);
            
            if (!$netSuiteItem) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'sku' => $sku,
                    'product_id' => $productId,
                    'reason' => 'Item not found in NetSuite'
                ];
            }
            
            // Extract quantity on hand from NetSuite
            $quantityOnHand = $netSuiteItem['custitem82'] ?? $netSuiteItem['custitem82'] ?? 0;
            $newBackOrderMessage = $netSuiteItem['custitem73'] ?? '';
            
            $currentStock = $product['SKUInfo']['Stock'] ?? 0;
            $currentBackOrderMessage = $product['SKUInfo']['BackOrderMessage'] ?? '';
            
            $this->logger->debug('Retrieved NetSuite item data', [
                'sku' => $sku,
                'netsuite_id' => $netSuiteItem['id'] ?? null,
                'quantity_on_hand' => $quantityOnHand,
                'current_3dcart_stock' => $currentStock
            ]);
            
            // Check if update is needed
            // if ($currentStock == $quantityOnHand) {
            //     return [
            //         'success' => false,
            //         'skipped' => true,
            //         'sku' => $sku,
            //         'product_id' => $productId,
            //         'reason' => 'Stock already up to date',
            //         'stock' => $currentStock
            //     ];
            // }
            
            // Fetch price from NetSuite
            $price = null;
            $netsuitItemId = $netSuiteItem['id'] ?? null;
            if ($netsuitItemId) {
                $priceData = $this->netSuiteService->getItemPrice($netsuitItemId);
                if ($priceData) {
                    // Extract the price from the price data
                    // The response typically contains pricing info - extract the base price
                    if (is_array($priceData) && isset($priceData['price'])) {
                        $price = (float)$priceData['price'];
                    } elseif (is_array($priceData) && isset($priceData['items']) && count($priceData['items']) > 0) {
                        $priceItem = $priceData['items'][0];
                        $price = (float)($priceItem['price'] ?? $priceItem['unitPrice'] ?? null);
                    }
                    
                    $this->logger->debug('Retrieved price from NetSuite', [
                        'sku' => $sku,
                        'netsuite_id' => $netsuitItemId,
                        'price' => $price
                    ]);
                }
            }
            
            // Mark for batch update
            $this->logger->debug('Product marked for batch update', [
                'sku' => $sku,
                'product_id' => $productId,
                'old_stock' => $currentStock,
                'new_stock' => $quantityOnHand,
                'price' => $price,
                'old_backorder_message' => $currentBackOrderMessage,
                'new_backorder_message' => $newBackOrderMessage
            ]);
            
            return [
                'success' => true,
                'skipped' => false,
                'sku' => $sku,
                'product_id' => $productId,
                'old_stock' => $currentStock,
                'new_stock' => $quantityOnHand,
                'price' => $price,
                'netsuite_item_id' => $netsuitItemId,
                'old_backorder_message' => $currentBackOrderMessage,
                'new_backorder_message' => $newBackOrderMessage
            ];
            
        } catch (\Exception $e) {
            $sku = $product['SKUInfo']['SKU'] ?? 'unknown';
            $productId = $product['id'] ?? $product['ProductID'] ?? null;
            
            $this->logger->error('Error syncing product', [
                'sku' => $sku,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'skipped' => false,
                'sku' => $sku,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Find item in NetSuite by SKU
     * 
     * @param string $sku The SKU to search for
     * @return array|null Item data if found, null otherwise
     */
    private function findItemInNetSuite($sku) {
        try {
            $this->logger->debug('Searching for item in NetSuite by SKU', [
                'sku' => $sku
            ]);
            
            // Use the NetSuite service's public search method
            $item = $this->netSuiteService->searchItemBySku($sku);
            
            if ($item) {
                $this->logger->debug('Found item in NetSuite', [
                    'sku' => $sku,
                    'netsuite_id' => $item['id'] ?? null
                ]);
                return $item;
            }
            
            $this->logger->warning('Item not found in NetSuite', [
                'sku' => $sku
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error searching NetSuite for item', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Batch update multiple products in 3DCart using v2 API
     * 
     * @param array $productsToUpdate Array of products with sync results
     * @return array Result with success status
     */
    private function batchUpdateProductsIn3DCart($productsToUpdate) {
        try {
            // Prepare batch update payload
            $batchPayload = [];
            
            foreach ($productsToUpdate as $item) {
                $product = $item['product'];
                $syncResult = $item['syncResult'];
                $newStock = $syncResult['new_stock'];
                $price = $syncResult['price'] ?? null;
                
                // Extract CatalogID from product (this identifies the product in 3DCart)
                $catalogId = $product['SKUInfo']['CatalogID'] ?? $product['CatalogID'] ?? null;
                
                if (!$catalogId) {
                    $this->logger->warning('CatalogID not found for product', [
                        'sku' => $product['SKUInfo']['SKU'] ?? null,
                        'product_id' => $product['id'] ?? $product['ProductID'] ?? null
                    ]);
                    continue;
                }
                
                // Build SKU info with stock and price
                $skuInfo = [
                    'CatalogID' => (int)$catalogId,
                    'Stock' => (float)$newStock
                ];
                
                // Add price if available
                if ($price !== null) {
                    $skuInfo['Price'] = (float)$price;
                    $skuInfo['RetailPrice'] = (float)$price;
                }
                // add BackOrderMessage is custitem73 is available
                if(isset($product['custitem73'])){
                    $skuInfo['BackOrderMessage'] = $product['custitem73'];
                }
                
                $batchPayload[] = [
                    'SKUInfo' => $skuInfo
                ];
                
                $this->logger->debug('Added product to batch update', [
                    'catalog_id' => $catalogId,
                    'sku' => $product['SKUInfo']['SKU'] ?? null,
                    'new_stock' => $newStock,
                    'price' => $price,
                    'backordermessage' => $product['custitem73'] ?? null
                ]);
            }
            
            if (empty($batchPayload)) {
                throw new \Exception('No valid products to update after processing');
            }
            
            $this->logger->info('Sending batch update to 3DCart v2 API', [
                'products_count' => count($batchPayload),
                'endpoint' => $this->threeDCartV2BaseUrl . 'products'
            ]);
            
            $startTime = microtime(true);
            $response = $this->threeDCartV2Client->put('products', [
                'json' => $batchPayload
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart-v2', '/products', 'PUT', $response->getStatusCode(), $duration);
            
            $responseBody = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Batch update successful', [
                    'status_code' => $statusCode,
                    'products_updated' => count($batchPayload),
                    'response_body' => $responseBody
                ]);
                
                return [
                    'success' => true,
                    'products_updated' => count($batchPayload),
                    'status_code' => $statusCode
                ];
            } else {
                throw new \Exception("Unexpected status code: {$statusCode}. Response: {$responseBody}");
            }
            
        } catch (RequestException $e) {
            $errorDetails = [
                'products_count' => count($productsToUpdate),
                'error' => $e->getMessage()
            ];
            
            if ($e->hasResponse()) {
                $errorDetails['status_code'] = $e->getResponse()->getStatusCode();
                $errorDetails['response_body'] = $e->getResponse()->getBody()->getContents();
            }
            
            $this->logger->error('Batch update failed', $errorDetails);
            
            return [
                'success' => false,
                'error' => 'Batch update failed: ' . $e->getMessage(),
                'details' => $errorDetails
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Error during batch update', [
                'products_count' => count($productsToUpdate),
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send notification email with inventory sync summary
     * 
     * @param array $syncResult The result array from syncInventory()
     * @return array Email send result
     */
    public function sendSyncNotificationEmail($syncResult) {
        try {
            $notificationService = new NotificationSettingsService();
            $unifiedEmailService = new UnifiedEmailService();
            
            // Determine notification type based on result
            $isSuccess = $syncResult['success'] && $syncResult['error_count'] == 0;
            $notificationType = $isSuccess 
                ? NotificationSettingsService::TYPE_INVENTORY_SYNC_SUCCESS 
                : NotificationSettingsService::TYPE_INVENTORY_SYNC_FAILED;
            
            // Get recipients from notification settings
            $recipients = $notificationService->getRecipients($notificationType);
            
            if (empty($recipients)) {
                $this->logger->warning('No email recipients configured for inventory sync notifications');
                return ['success' => false, 'error' => 'No email recipients configured'];
            }
            
            // Build email content
            $subject = $this->buildNotificationSubject($syncResult, $isSuccess);
            $htmlContent = $this->buildNotificationEmailContent($syncResult, $isSuccess);
            
            // Send email
            $emailResult = $unifiedEmailService->sendEmail($subject, $htmlContent, $recipients);
            
            $this->logger->info('Inventory sync notification email sent', [
                'recipients' => count($recipients),
                'success' => $emailResult['success'] ?? false,
                'subject' => $subject
            ]);
            
            return $emailResult;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send inventory sync notification email', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to send notification email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Build email notification subject
     * 
     * @param array $syncResult Sync result array
     * @param bool $isSuccess Whether the sync was successful
     * @return string Email subject
     */
    private function buildNotificationSubject($syncResult, $isSuccess) {
        $prefix = '[3DCart Integration]';
        $status = $isSuccess ? 'SUCCESS' : 'FAILED';
        $timestamp = date('Y-m-d H:i:s');
        
        if ($isSuccess) {
            return "{$prefix} Inventory Sync {$status} - {$syncResult['synced_count']} products updated - {$timestamp}";
        } else {
            return "{$prefix} Inventory Sync {$status} - Action Required - {$timestamp}";
        }
    }
    
    /**
     * Build HTML email content for inventory sync notification
     * 
     * @param array $syncResult Sync result array
     * @param bool $isSuccess Whether the sync was successful
     * @return string HTML email content
     */
    private function buildNotificationEmailContent($syncResult, $isSuccess) {
        $statusColor = $isSuccess ? '#28a745' : '#dc3545';
        $statusLabel = $isSuccess ? 'SUCCESS' : 'FAILED';
        
        $duration = $this->calculateDuration(
            $syncResult['start_time'] ?? '',
            $syncResult['end_time'] ?? ''
        );
        
        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 700px; margin: 0 auto; padding: 20px; }
                .header { background: ' . $statusColor . '; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
                .header h2 { margin: 0; font-size: 24px; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; border: 1px solid #dee2e6; }
                .summary { background: white; padding: 15px; border-left: 4px solid ' . $statusColor . '; margin: 20px 0; }
                .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                .summary-row:last-child { border-bottom: none; }
                .summary-label { font-weight: bold; color: #555; }
                .summary-value { color: #333; font-weight: bold; }
                .stats { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin: 20px 0; }
                .stat-box { background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; text-align: center; }
                .stat-number { font-size: 28px; font-weight: bold; color: #007bff; }
                .stat-label { font-size: 12px; color: #666; margin-top: 5px; text-transform: uppercase; }
                .synced { border-left-color: #28a745; }
                .synced .stat-number { color: #28a745; }
                .skipped { border-left-color: #ffc107; }
                .skipped .stat-number { color: #ffc107; }
                .errors { border-left-color: #dc3545; }
                .errors .stat-number { color: #dc3545; }
                .products-list { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .products-list h4 { margin: 0 0 10px 0; color: #333; }
                .product-item { padding: 10px; border-bottom: 1px solid #eee; font-size: 13px; }
                .product-item:last-child { border-bottom: none; }
                .product-sku { font-weight: bold; color: #007bff; }
                .product-change { color: #28a745; margin: 5px 0; }
                .product-price { color: #ff6b6b; font-weight: 500; margin: 5px 0; }
                .error-box { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 15px 0; }
                .error-box h4 { margin: 0 0 10px 0; color: #721c24; }
                .error-item { background: white; padding: 10px; margin: 5px 0; border-left: 3px solid #dc3545; font-size: 13px; }
                .footer { background: white; padding: 15px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; margin-top: 20px; }
                .timestamp { color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>📊 Inventory Synchronization Report</h2>
                    <p style="margin: 10px 0 0 0;">Status: <strong>' . $statusLabel . '</strong></p>
                </div>
                
                <div class="content">
                    <div class="summary">
                        <div class="summary-row">
                            <span class="summary-label">Execution Time:</span>
                            <span class="summary-value">' . htmlspecialchars($syncResult['start_time'] ?? 'N/A') . '</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Duration:</span>
                            <span class="summary-value">' . $duration . '</span>
                        </div>
                    </div>
                    
                    <div class="stats">
                        <div class="stat-box">
                            <div class="stat-number">' . $syncResult['total_products'] . '</div>
                            <div class="stat-label">Total Products</div>
                        </div>
                        <div class="stat-box synced">
                            <div class="stat-number">' . $syncResult['synced_count'] . '</div>
                            <div class="stat-label">Updated</div>
                        </div>
                        <div class="stat-box skipped">
                            <div class="stat-number">' . $syncResult['skipped_count'] . '</div>
                            <div class="stat-label">Skipped</div>
                        </div>
                        <div class="stat-box errors">
                            <div class="stat-number">' . $syncResult['error_count'] . '</div>
                            <div class="stat-label">Errors</div>
                        </div>
                    </div>
        ';
        
        // Add products section if there are updates
        if (!empty($syncResult['products'])) {
            $html .= '<div class="products-list">
                <h4>✅ Successfully Updated Products (' . count($syncResult['products']) . ')</h4>';
            
            foreach ($syncResult['products'] as $product) {
                $oldStock = $product['old_stock'] ?? 'N/A';
                $newStock = $product['new_stock'] ?? 'N/A';
                $price = $product['price'] ?? null;
                $sku = htmlspecialchars($product['sku'] ?? 'Unknown');
                $oldBackOrderMessage = htmlspecialchars($product['old_backorder_message'] ?? '');
                $newBackOrderMessage = htmlspecialchars($product['new_backorder_message'] ?? '');
                
                $priceHtml = '';
                if ($price !== null) {
                    $priceHtml = '<div class="product-price">Price: $' . number_format($price, 2) . '</div>';
                }
                
                $html .= '
                    <div class="product-item">
                        <div class="product-sku">SKU: ' . $sku . '</div>
                        <div class="product-change">Stock Updated: ' . $oldStock . ' → ' . $newStock . '</div>
                        ' . $priceHtml . '
                        <div class="product-backorder-message">Old Back Order Message: ' . $oldBackOrderMessage . '</div>
                        <div class="product-backorder-message">New Back Order Message: ' . $newBackOrderMessage . '</div>
                    </div>
                ';
            }
            
            $html .= '</div>';
        }
        
        // Add errors section if there are errors
        if (!empty($syncResult['errors'])) {
            $html .= '<div class="error-box">
                <h4>⚠️ Errors Encountered (' . count($syncResult['errors']) . ')</h4>';
            
            foreach ($syncResult['errors'] as $error) {
                $html .= '<div class="error-item">' . htmlspecialchars($error) . '</div>';
            }
            
            $html .= '</div>';
        }
        
        // Add additional info
        if (isset($syncResult['error'])) {
            $html .= '<div class="error-box">
                <h4>⚠️ Critical Error</h4>
                <div class="error-item">' . htmlspecialchars($syncResult['error']) . '</div>
            </div>';
        }
        
        $html .= '
                    <div class="footer">
                        <p>This is an automated notification from your 3DCart to NetSuite integration system.</p>
                        <p class="timestamp">Report generated: ' . date('Y-m-d H:i:s T') . '</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Calculate duration between two timestamps
     * 
     * @param string $startTime Start time (Y-m-d H:i:s format)
     * @param string $endTime End time (Y-m-d H:i:s format)
     * @return string Formatted duration
     */
    private function calculateDuration($startTime, $endTime) {
        try {
            if (empty($startTime) || empty($endTime)) {
                return 'N/A';
            }
            
            $start = new \DateTime($startTime);
            $end = new \DateTime($endTime);
            $interval = $start->diff($end);
            
            $parts = [];
            if ($interval->h > 0) {
                $parts[] = $interval->h . 'h';
            }
            if ($interval->i > 0) {
                $parts[] = $interval->i . 'm';
            }
            if ($interval->s > 0 || empty($parts)) {
                $parts[] = $interval->s . 's';
            }
            
            return implode(' ', $parts);
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}