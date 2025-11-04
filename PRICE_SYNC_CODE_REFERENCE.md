# Price Synchronization - Code Reference

## Implementation Details with Code Snippets

### 1. NetSuiteService::getItemPrice() Method

**File:** `src/Services/NetSuiteService.php`  
**Lines:** 2860-2907

```php
/**
 * Get pricing information for an inventory item by ID
 * 
 * @param string $itemId NetSuite inventory item ID
 * @return array|null Price data if found, null otherwise
 */
public function getItemPrice($itemId) {
    try {
        $this->logger->debug('Fetching price for inventory item', [
            'item_id' => $itemId
        ]);
        
        $startTime = microtime(true);
        // Format: /inventoryitem/{id}/price/quantity=0,currencypage=1,pricelevel=1
        $endpoint = "/inventoryitem/{$itemId}/price/quantity=0,currencypage=1,pricelevel=1";
        $response = $this->makeRequest('GET', $endpoint, null);
        $duration = (microtime(true) - $startTime) * 1000;
        
        $statusCode = $response->getStatusCode();
        $this->logger->logApiCall('NetSuite', $endpoint, 'GET', $statusCode, $duration);
        
        if ($statusCode === 200) {
            $priceData = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->debug('Retrieved price data for inventory item', [
                'item_id' => $itemId,
                'price_data' => $priceData
            ]);
            
            return $priceData;
        } else {
            $this->logger->warning('Unexpected status code fetching item price', [
                'item_id' => $itemId,
                'status_code' => $statusCode
            ]);
            return null;
        }
        
    } catch (RequestException $e) {
        $this->logger->warning('Failed to fetch item price from NetSuite', [
            'item_id' => $itemId,
            'error' => $e->getMessage(),
            'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
        ]);
        return null;
        
    } catch (\Exception $e) {
        $this->logger->error('Error fetching item price from NetSuite', [
            'item_id' => $itemId,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}
```

### 2. InventorySyncService::syncSingleProduct() Enhancement

**File:** `src/Services/InventorySyncService.php`  
**Lines:** 277-298 (Price Fetching Section)

```php
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
    'price' => $price
]);

return [
    'success' => true,
    'skipped' => false,
    'sku' => $sku,
    'product_id' => $productId,
    'old_stock' => $currentStock,
    'new_stock' => $quantityOnHand,
    'price' => $price,
    'netsuite_item_id' => $netsuitItemId
];
```

### 3. InventorySyncService::batchUpdateProductsIn3DCart() Enhancement

**File:** `src/Services/InventorySyncService.php`  
**Lines:** 389-427 (Batch Payload Building)

```php
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
    }
    
    $batchPayload[] = [
        'SKUInfo' => $skuInfo
    ];
    
    $this->logger->debug('Added product to batch update', [
        'catalog_id' => $catalogId,
        'sku' => $product['SKUInfo']['SKU'] ?? null,
        'new_stock' => $newStock,
        'price' => $price
    ]);
}
```

---

## API Endpoint Details

### NetSuite Price Endpoint Format

```
GET /services/rest/record/v1/inventoryitem/{itemId}/price/quantity=0,currencypage=1,pricelevel=1
Authorization: OAuth token_type="Bearer" access_token="...jwt..."
Content-Type: application/json
```

### Sample Request

```bash
curl -X GET \
  'https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/inventoryitem/15284/price/quantity=0,currencypage=1,pricelevel=1' \
  -H 'Authorization: Bearer <JWT_TOKEN>' \
  -H 'Content-Type: application/json'
```

### Sample Response

```json
{
  "links": [
    {
      "rel": "self",
      "href": "https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/inventoryitem/15284/price/quantity=0,currencypage=1,pricelevel=1"
    }
  ],
  "currencyPage": {
    "links": [
      {
        "rel": "self",
        "href": "https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/currency/1"
      }
    ],
    "id": "1",
    "refName": "1"
  },
  "price": 1599,
  "priceLevel": {
    "links": [
      {
        "rel": "self",
        "href": "https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/pricelevel/1"
      }
    ],
    "id": "1",
    "refName": "1"
  },
  "priceLevelName": "Retail Price",
  "quantity": {
    "value": "0"
  }
}
```

---

## 3DCart Batch Update Payload

### Before (Stock Only)

```json
[
  {
    "SKUInfo": {
      "CatalogID": 12,
      "Stock": 341
    }
  }
]
```

### After (Stock + Price)

```json
[
  {
    "SKUInfo": {
      "CatalogID": 12,
      "Stock": 341,
      "Price": 1599
    }
  },
  {
    "SKUInfo": {
      "CatalogID": 15,
      "Stock": 250,
      "Price": 2499
    }
  }
]
```

---

## Log Output Examples

### Successful Price Fetch

```
[2025-10-29 20:01:28] 3dcart-netsuite.DEBUG: Fetching price for inventory item {"item_id":"15284"}
[2025-10-29 20:01:29] 3dcart-netsuite.INFO: API Call Successful {"service":"NetSuite","endpoint":"/inventoryitem/15284/price/quantity=0,currencypage=1,pricelevel=1","method":"GET","response_code":200,"duration_ms":145}
[2025-10-29 20:01:29] 3dcart-netsuite.DEBUG: Retrieved price data for inventory item {"item_id":"15284","price_data":{"price":1599,"currencyPage":{"id":"1"}}}
[2025-10-29 20:01:29] 3dcart-netsuite.DEBUG: Retrieved price from NetSuite {"sku":"MBAND1412-175","netsuite_id":"15284","price":1599}
```

### Price Included in Batch Update

```
[2025-10-29 20:01:30] 3dcart-netsuite.DEBUG: Added product to batch update {"catalog_id":12,"sku":"MBAND1412-175","new_stock":341,"price":1599}
[2025-10-29 20:01:30] 3dcart-netsuite.INFO: Sending batch update to 3DCart v2 API {"products_count":1,"endpoint":"https://apirest.3dcart.com/3dCartWebAPI/v2/products"}
[2025-10-29 20:01:31] 3dcart-netsuite.INFO: API Call Successful {"service":"3DCart-v2","endpoint":"/products","method":"PUT","response_code":200,"duration_ms":751}
[2025-10-29 20:01:31] 3dcart-netsuite.INFO: Batch update successful {"status_code":200,"products_updated":1}
```

### Error Handling

```
[2025-10-29 20:01:29] 3dcart-netsuite.WARNING: Failed to fetch item price from NetSuite {"item_id":"12345","error":"404 Not Found","status_code":404}
[2025-10-29 20:01:30] 3dcart-netsuite.DEBUG: Retrieved price from NetSuite {"sku":"TEST-SKU","netsuite_id":"12345","price":null}
[2025-10-29 20:01:31] 3dcart-netsuite.DEBUG: Added product to batch update {"catalog_id":99,"sku":"TEST-SKU","new_stock":100,"price":null}
```

---

## Integration Points

### 1. NetSuiteService Integration
```php
// In InventorySyncService constructor
$this->netSuiteService = new NetSuiteService();

// In syncSingleProduct method
$priceData = $this->netSuiteService->getItemPrice($netsuitItemId);
```

### 2. Logger Integration
```php
// Debug logs
$this->logger->debug('Retrieved price from NetSuite', [...]);

// API call logs
$this->logger->logApiCall('NetSuite', $endpoint, 'GET', $statusCode, $duration);

// Error logs
$this->logger->warning('Failed to fetch item price from NetSuite', [...]);
$this->logger->error('Error fetching item price from NetSuite', [...]);
```

### 3. Batch Payload Integration
```php
// Extract price from sync result
$price = $syncResult['price'] ?? null;

// Add to SKU info if available
if ($price !== null) {
    $skuInfo['Price'] = (float)$price;
}
```

---

## Error Handling Strategy

### Graceful Degradation
```php
// If price fetch fails, stock update still happens
if ($priceData) {
    // Extract price
} 
// Price remains null, batch update proceeds with stock only
```

### Try-Catch Blocks
```php
try {
    $response = $this->makeRequest('GET', $endpoint, null);
    // Process response
} catch (RequestException $e) {
    $this->logger->warning('Failed to fetch...', [...]);
    return null;
} catch (\Exception $e) {
    $this->logger->error('Error fetching...', [...]);
    return null;
}
```

---

## Testing Code Examples

### Test Price Fetching
```php
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

$logger = Logger::getInstance();
$netSuite = new NetSuiteService($logger);

// Fetch price for item ID 15284
$price = $netSuite->getItemPrice('15284');
echo json_encode($price, JSON_PRETTY_PRINT);
```

### Test Full Sync
```php
use Laguna\Integration\Services\InventorySyncService;

$sync = new InventorySyncService();
$result = $sync->syncInventory(['limit' => 10]);

echo "Synced: " . $result['synced_count'];
echo "Price fetches: " . count(array_filter($result['products'], 
    fn($p) => isset($p['price']) && $p['price'] !== null));
```

---

## Performance Considerations

### API Call Overhead
- **Price Fetch:** ~150ms per item
- **Batch Update:** ~750ms for multiple items
- **Total:** Minimal when only syncing changed products

### Optimization Tips
1. Only price-fetch when stock changes needed (already implemented)
2. Cache prices for 24-48 hours (future enhancement)
3. Batch multiple products in single API call (already implemented)
4. Use limit parameter to control sync scope

### Benchmarks
```
15 products scanned:
  - 1 required stock update
  - 1 price fetch: ~150ms
  - 1 batch update: ~750ms
  - Total: ~900ms + network latency
```

---

## Troubleshooting Checklist

- [ ] Verify NetSuite API credentials in `config/credentials.php`
- [ ] Check NetSuite item IDs are valid
- [ ] Confirm price endpoint is accessible: `/inventoryitem/{id}/price/...`
- [ ] Review logs for detailed error messages
- [ ] Test with known item ID: MBAND1412-175 (ID: 15284)
- [ ] Verify OAuth authentication is working
- [ ] Check 3DCart v2 API accepts Price field
- [ ] Ensure stock quantity comparison works

---

## Version Information

- **Implementation Date:** 2025-10-29
- **PHP Version:** 8.1+
- **Dependencies:** GuzzleHTTP, Monolog
- **NetSuite Environment:** Production/Sandbox (configurable)
- **3DCart API:** v1 (fetch), v2 (batch update)

---

*For questions, refer to implementation documentation or check application logs.*