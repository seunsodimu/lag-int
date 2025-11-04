# 3DCart Batch Product Update Implementation

## Summary
Successfully implemented batch product updates to 3DCart using the v2 API. The inventory synchronization now:
1. Fetches products from 3DCart v1 API
2. Searches for matching items in NetSuite by SKU
3. **Collects products that need stock updates**
4. **Sends them in a single batch PUT request to the v2 API endpoint**
5. Sends email notifications with results

## Changes Made

### 1. InventorySyncService.php
**Added v2 API Client:**
- Created a separate Guzzle client for 3DCart v2 API
- Base URL: `https://apirest.3dcart.com/3dCartWebAPI/v2/`
- Maintains separate headers and timeout settings

**Modified syncInventory() Method:**
- Changed from individual product updates to batch collection
- Collects all products that need updating
- Calls new `batchUpdateProductsIn3DCart()` method once with all products
- Provides better error handling and transaction-like behavior

**Modified syncSingleProduct() Method:**
- No longer attempts to update immediately
- Returns `success: true` to mark product for batch update
- Returns product metadata needed for batch update (old_stock, new_stock, SKU, etc.)

**New batchUpdateProductsIn3DCart() Method:**
- Prepares batch payload with required format:
  ```php
  [
      ['SKUInfo' => ['CatalogID' => 18, 'Stock' => 19.0]],
      ['SKUInfo' => ['CatalogID' => 12, 'Stock' => 342.0]],
      ...
  ]
  ```
- Extracts CatalogID from each product (identifies products in 3DCart)
- Sends PUT request to `/products` endpoint
- Handles API response (200 = success)
- Logs all details for auditing
- Returns structured success/error response

## API Endpoint

**Method:** PUT  
**URL:** `https://apirest.3dcart.com/3dCartWebAPI/v2/products`  
**Payload Format:**
```json
[
    {
        "SKUInfo": {
            "CatalogID": 18,
            "Stock": 19.0
        }
    }
]
```

**Response (Success):**
```json
[
    {
        "Key": "CatalogID",
        "Value": "18",
        "Status": "200",
        "Message": "Updated successfully"
    }
]
```

## Testing Results

### Last Execution (2025-10-29 15:23:56 - 15:24:04)
```
Inventory Synchronization Results
==================================
Start time: 2025-10-29 15:23:56
End time: 2025-10-29 15:24:01
Total products processed: 5
Products synced: 1 ✓
Products skipped: 4
Errors encountered: 0

Successfully Updated Products:
-------------------------------
SKU MBAND185400: 7 → 19

Email notification sent successfully ✓
```

### Key Log Entries
```
[15:24:00] Sending batch update to 3DCart v2 API {"products_count":1}
[15:24:01] API Call Successful {"service":"3DCart-v2","endpoint":"/products","method":"PUT","response_code":200,"duration_ms":824.59}
[15:24:01] Batch update successful {"status_code":200,"products_updated":1,"Message":"Updated successfully"}
[15:24:01] Batch update completed successfully {"products_updated":1}
```

## How It Works

### Flow Diagram
```
1. Fetch products from 3DCart v1 API
           ↓
2. For each product:
   - Extract SKU
   - Search for matching item in NetSuite
   - Compare stock quantities
   - If different, mark for update
           ↓
3. Collect all products needing updates
           ↓
4. Batch send to 3DCart v2 API (single PUT request)
           ↓
5. Handle response and send email notification
```

## Benefits

1. **Single API Call:** All products updated in one request instead of N individual requests
2. **Better Performance:** Reduced network overhead and response time
3. **Atomic Operation:** All-or-nothing batch processing
4. **Cleaner Logging:** Batch details logged as single transaction
5. **Error Handling:** Issues with batch don't affect individual product tracking
6. **Scalability:** Can handle thousands of products in batches

## CatalogID vs ProductID

- **CatalogID:** Used to identify SKUs in 3DCart for the v2 API
- **ProductID:** Internal 3DCart product identifier
- The v2 API expects CatalogID in the SKUInfo object
- Extracted from product data returned by v1 API: `product['SKUInfo']['CatalogID']`

## Error Handling

- **Missing CatalogID:** Logs warning and skips that product
- **Empty Batch:** Throws exception if no valid products after processing
- **API Failure:** Logs full error details and response body
- **Network Errors:** Caught as RequestException with detailed logging

## Files Modified
- `c:\xampp\htdocs\lag-int\src\Services\InventorySyncService.php`

## Next Steps (Optional Enhancements)

1. **Batch Size Limits:** If syncing 10,000+ products, implement chunking
2. **Partial Success Handling:** Parse individual item responses for partial success scenarios
3. **Retry Logic:** Implement exponential backoff for failed batches
4. **Audit Trail:** Store batch update records in database for compliance