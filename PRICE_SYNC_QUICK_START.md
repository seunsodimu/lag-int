# Price Synchronization - Quick Reference

## What Was Added

The batch inventory sync system now automatically fetches product prices from NetSuite and includes them in updates to 3DCart.

## Key Changes

### 1. New Method in NetSuiteService
```php
public function getItemPrice($itemId)
```
- Located in: `src/Services/NetSuiteService.php` (lines 2860-2907)
- Returns: Array with price data or null on failure
- Calls: `/inventoryitem/{itemId}/price/quantity=0,currencypage=1,pricelevel=1`

### 2. Enhanced Product Sync
```php
// In InventorySyncService::syncSingleProduct()
// Fetch price from NetSuite (lines 277-298)
if ($netsuitItemId) {
    $priceData = $this->netSuiteService->getItemPrice($netsuitItemId);
    // Extract and return price
}
```

### 3. Updated Batch Payload
```php
// In InventorySyncService::batchUpdateProductsIn3DCart()
// Build SKU info with optional price (lines 406-415)
$skuInfo = [
    'CatalogID' => (int)$catalogId,
    'Stock' => (float)$newStock,
    'Price' => (float)$price  // Added if available
];
```

## Testing

### Quick Test
```bash
# Run a sync that will fetch prices for out-of-date products
php scripts/sync-inventory.php --limit=10
```

### Verify in Logs
Look for entries like:
```
"Retrieved price from NetSuite", {"sku": "MBAND1412-175", "netsuite_id": "15284", "price": 1599}
```

## Troubleshooting

### No prices appearing in batch updates
- Check if any products actually needed stock updates (look for "Products synced: X")
- Only products with out-of-date stock will trigger price fetches

### Price endpoint errors (400, 404)
- Check NetSuite API permissions
- Verify item ID is correct
- Check logs for detailed error messages

### Viewing Recent Sync Logs
```bash
# Most recent entries showing prices and updates
tail -n 50 logs/app-*.log | grep -E "(price|Stock|Batch)"
```

## Monitoring

Email notifications now include price information. Check:
- Subject: `[3DCart Integration] Inventory Sync SUCCESS`
- Body: Updated products with stock changes and prices
- Sent to: Recipients configured in database or .env

## Rollback

If you need to disable price syncing:
1. Comment out lines 277-298 in `InventorySyncService.php`
2. Comment out lines 412-415 in `InventorySyncService.php`
3. System will continue with stock-only updates

No database changes or config changes needed.

## Files Modified

1. `src/Services/NetSuiteService.php` - Added getItemPrice() method
2. `src/Services/InventorySyncService.php` - Integrated price fetching
3. Email templates - Added price display (if using HTML emails)

## Performance

- Price fetch adds ~150ms per product
- Only for products requiring updates
- Batch update still one API call
- No impact on skipped products

## Support

For issues or questions:
1. Check `logs/` directory for detailed error logs
2. Review `PRICE_SYNC_IMPLEMENTATION.md` for full documentation
3. Test with `testfiles/test-price-integration.php`

---

**Status:** ✅ Ready for Production  
**Last Updated:** 2025-10-29  
**Tested:** ✓ Yes - Working correctly