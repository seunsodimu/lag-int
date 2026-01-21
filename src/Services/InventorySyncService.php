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
    private $netSuiteInventoryCache = null;
    
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
            // Fetch inventory items from NetSuite once at the beginning
            $this->netSuiteInventoryCache = $this->netSuiteService->callInventorySearchRestlet();
            
            if (!$this->netSuiteInventoryCache) {
                $this->logger->error('Failed to fetch inventory items from NetSuite RESTlet');
                $result['success'] = false;
                $result['error'] = 'Failed to fetch inventory items from NetSuite';
                $result['end_time'] = date('Y-m-d H:i:s');
                return $result;
            }
            
            $this->logger->info('Fetched inventory items from NetSuite', [
                'items_count' => count($this->netSuiteInventoryCache)
            ]);
            
            // Fetch products from 3DCart
            $products = $this->fetchProductsFrom3DCart($filters);
            
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
            
            // Extract quantity on hand and back order message from NetSuite RESTlet response
            $quantityOnHand = $netSuiteItem['quantityOnHand'] ?? $netSuiteItem['totalquantityonhand'] ?? 0;
            $newBackOrderMessage = $netSuiteItem['backOrderMessage'] ?? $netSuiteItem['custitem73'] ?? '';
            
            $currentStock = $product['SKUInfo']['Stock'] ?? 0;
            $currentBackOrderMessage = $product['SKUInfo']['BackOrderMessage'] ?? '';
            
            $this->logger->debug('Retrieved NetSuite item data', [
                'sku' => $sku,
                'netsuite_id' => $netSuiteItem['id'] ?? null,
                'quantity_on_hand' => $quantityOnHand,
                'current_3dcart_stock' => $currentStock
            ]);
            
            // Fetch price from NetSuite
            $price = null;
            $netsuitItemId = $netSuiteItem['id'] ?? null;
            if ($netsuitItemId) {
                $priceData = $this->netSuiteService->getItemPrice($netsuitItemId);
                if ($priceData) {
                    // Extract the price from the price data
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
     * Find item in NetSuite by SKU from cached inventory results
     * 
     * @param string $sku The SKU to search for
     * @return array|null Item data if found, null otherwise
     */
    private function findItemInNetSuite($sku) {
        try {
            $this->logger->debug('Searching for item in NetSuite by SKU from cache', [
                'sku' => $sku
            ]);
            
            // Use cached inventory items from NetSuite RESTlet (populated in syncInventory)
            if (!$this->netSuiteInventoryCache) {
                $this->logger->warning('NetSuite inventory cache is empty', [
                    'sku' => $sku
                ]);
                return null;
            }
            
            // Search through cached items to find matching SKU
            foreach ($this->netSuiteInventoryCache as $item) {
                $itemSku = $item['sku'] ?? null;
                
                if ($itemSku === $sku) {
                    $this->logger->debug('Found item in NetSuite cache', [
                        'sku' => $sku,
                        'netsuite_id' => $item['id'] ?? null,
                        'display_name' => $item['displayname'] ?? null
                    ]);
                    return $item;
                }
            }
            
            $this->logger->warning('Item not found in NetSuite cache', [
                'sku' => $sku,
                'items_in_cache' => count($this->netSuiteInventoryCache)
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error searching NetSuite cache for item', [
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
                $newBackOrderMessage = $syncResult['new_backorder_message'] ?? '';
                
                // Extract CatalogID from product
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
                // Add BackOrderMessage if available from NetSuite
                if (!empty($newBackOrderMessage)) {
                    $skuInfo['BackOrderMessage'] = $newBackOrderMessage;
                }
                
                $batchPayload[] = [
                    'SKUInfo' => $skuInfo
                ];
                
                $this->logger->debug('Added product to batch update', [
                    'catalog_id' => $catalogId,
                    'sku' => $product['SKUInfo']['SKU'] ?? null,
                    'new_stock' => $newStock,
                    'price' => $price,
                    'backordermessage' => $newBackOrderMessage
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
     * Send email notification for inventory sync results
     * 
     * @param array $syncResult The result array from syncInventory()
     * @return array Email send result
     */
    public function sendSyncNotificationEmail($syncResult) {
        try {
            $emailService = new EnhancedEmailService();
            
            // Send email using centralized logic in EnhancedEmailService
            $emailResult = $emailService->sendInventorySyncNotification($syncResult);
            
            $this->logger->info('Inventory sync notification email sent via EnhancedEmailService', [
                'success' => $emailResult['success'] ?? false
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
}
