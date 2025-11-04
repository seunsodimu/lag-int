# Inventory Synchronization Feature

## Overview

The Inventory Synchronization feature automatically updates product stock levels in 3DCart based on quantities on hand in NetSuite. This ensures your e-commerce inventory is always in sync with your inventory management system.

## How It Works

1. **Fetches products** from 3DCart API (v1 endpoint)
2. **Extracts SKU** from each product (from `SKUInfo->SKU`)
3. **Searches NetSuite** for matching items by SKU
4. **Retrieves quantity** on hand from NetSuite (`totalquantityonhand`)
5. **Updates 3DCart** product stock with the NetSuite quantity

## Features

- **Bidirectional lookup**: Searches multiple NetSuite item types (inventory, non-inventory, service items)
- **Smart skipping**: Skips products without SKUs or items not found in NetSuite
- **Change detection**: Only updates if stock quantity has changed
- **Detailed logging**: All operations logged for auditing and troubleshooting
- **Manual and automated execution**: Run via web interface or scheduled jobs

## Usage

### Manual Execution (Web Interface)

**URL**: `http://yoursite.com/run-inventory-sync.php`

Access from browser to get a visual interface:
- Display results in real-time
- Configure limits and offsets
- See detailed success/error reports

**Parameters**:
- `limit` (optional): Number of products to sync per run (default: 100)
- `offset` (optional): Starting product index for pagination (default: 0)

**Example URLs**:
```
http://yoursite.com/run-inventory-sync.php
http://yoursite.com/run-inventory-sync.php?limit=50&offset=100
```

### Command Line Execution

**Script**: `scripts/sync-inventory.php`

```bash
# Basic sync (100 products by default)
php scripts/sync-inventory.php

# Sync with custom limit
php scripts/sync-inventory.php --limit=50

# Sync with offset for pagination
php scripts/sync-inventory.php --offset=100

# Combine limit and offset
php scripts/sync-inventory.php --limit=50 --offset=100
```

### Scheduled Job (Cron/Task Scheduler)

**Setup for Linux/Unix**:
```bash
# Edit crontab
crontab -e

# Add one of these lines:
# Run daily at 2:00 AM
0 2 * * * /usr/bin/php /path/to/sync-inventory.php

# Run every 6 hours
0 */6 * * * /usr/bin/php /path/to/sync-inventory.php

# Run every hour
0 * * * * /usr/bin/php /path/to/sync-inventory.php
```

**Setup for Windows Task Scheduler**:
1. Open Task Scheduler
2. Create a new task
3. Set trigger (daily, hourly, etc.)
4. Add action:
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\lag-int\scripts\sync-inventory.php`
   - Start in: `C:\xampp\htdocs\lag-int`

## API Integration

### InventorySyncService

The core service for inventory synchronization:

```php
use Laguna\Integration\Services\InventorySyncService;

$syncService = new InventorySyncService();

// Sync with defaults
$result = $syncService->syncInventory();

// Sync with custom filters
$result = $syncService->syncInventory([
    'limit' => 50,
    'offset' => 100
]);

// Process results
if ($result['success']) {
    echo "Synced: " . $result['synced_count'] . " products\n";
    echo "Errors: " . $result['error_count'] . " products\n";
    
    // Review updated products
    foreach ($result['products'] as $product) {
        if ($product['success']) {
            echo "SKU {$product['sku']}: {$product['old_stock']} → {$product['new_stock']}\n";
        }
    }
}
```

### NetSuiteService Extension

New public method added to `NetSuiteService`:

```php
use Laguna\Integration\Services\NetSuiteService;

$netsuite = new NetSuiteService();

// Search for an item by SKU
$item = $netsuite->searchItemBySku('MBAND1412-175');

if ($item) {
    echo "Found item: " . ($item['displayname'] ?? 'N/A');
    echo "Quantity on hand: " . ($item['totalquantityonhand'] ?? 0);
} else {
    echo "Item not found";
}
```

## Response Format

### Sync Result Array

```php
[
    'success' => true,
    'start_time' => '2025-01-15 14:30:00',
    'end_time' => '2025-01-15 14:35:00',
    'total_products' => 150,
    'synced_count' => 45,      // Products with stock updated
    'skipped_count' => 100,    // Products skipped (no SKU, not found, etc)
    'error_count' => 5,        // Products with errors
    'products' => [
        [
            'success' => true,
            'sku' => 'MBAND1412-175',
            'product_id' => 12345,
            'old_stock' => 209,
            'new_stock' => 215
        ],
        // ... more products
    ],
    'errors' => [
        'Error message 1',
        'Error message 2',
        // ... more errors
    ]
]
```

## Logging

All operations are logged to `logs/app.log`:

- **INFO**: Successful sync operations
- **DEBUG**: Detailed operation information
- **WARNING**: Skipped products or partial matches
- **ERROR**: Failed operations with details

Example log entries:
```
2025-01-15 14:30:00 INFO Starting inventory synchronization [filters: limit=100, offset=0]
2025-01-15 14:30:01 DEBUG Retrieved products from 3DCart [count: 100]
2025-01-15 14:30:02 DEBUG Searching for item in NetSuite by SKU [sku: MBAND1412-175]
2025-01-15 14:30:03 INFO Product stock updated successfully [sku: MBAND1412-175, old_stock: 209, new_stock: 215]
2025-01-15 14:35:00 INFO Inventory synchronization completed [total: 150, synced: 45, skipped: 100, errors: 5]
```

## Configuration

### API Credentials

Credentials are loaded from `config/credentials.php`:

- **3DCart**: Stored in `$credentials['3dcart']`
  - Uses v1 API endpoint: `https://apirest.3dcart.com/3dCartWebAPI/v1/Products`
  - Requires: `private_key`, `token`, `secure_url`

- **NetSuite**: Stored in `$credentials['netsuite']`
  - Uses REST API for item searches
  - Supports multiple item types

### Performance Tuning

**Adjust batch size** to balance between API load and sync duration:

```bash
# Sync fewer products per run (less API load)
php scripts/sync-inventory.php --limit=25

# Sync more products per run (faster completion)
php scripts/sync-inventory.php --limit=200
```

**Pagination for large catalogs**:
```bash
# Run first batch (products 1-100)
php scripts/sync-inventory.php --limit=100 --offset=0

# Run second batch (products 101-200)
php scripts/sync-inventory.php --limit=100 --offset=100

# Run third batch (products 201-300)
php scripts/sync-inventory.php --limit=100 --offset=200
```

## Troubleshooting

### Products Not Updating

1. **Check logs**: Review `logs/app.log` for error details
2. **Verify SKU format**: Ensure 3DCart SKU matches NetSuite ItemID exactly
3. **Check item exists**: Use `/status.php` to verify NetSuite connection
4. **Review permissions**: Ensure API credentials have search permissions

### API Connection Errors

1. **3DCart API errors**:
   - Verify credentials in `config/credentials.php`
   - Test with: `http://yoursite.com/run-inventory-sync.php`
   - Check 3DCart API status

2. **NetSuite API errors**:
   - Verify OAuth credentials are current
   - Check OAuth token expiration
   - Review NetSuite API permissions

### Performance Issues

1. **Reduce batch size**: Use `--limit=25` instead of `--limit=100`
2. **Schedule during off-peak hours**: Run at night or early morning
3. **Monitor API rate limits**: Check logs for throttling errors
4. **Use pagination**: Split large syncs into multiple smaller runs

## Examples

### Example 1: Manual Admin Update

Access web interface and click "Start Synchronization":
```
http://lagunaedi.com/run-inventory-sync.php
```

Results display:
```
Inventory Synchronization Results
==================================
Start time: 2025-01-15 14:30:00
End time: 2025-01-15 14:35:00
Total products processed: 150
Products synced: 45
Products skipped: 100
Errors encountered: 5

Successfully Updated Products:
-------------------------------
SKU MBAND1412-175: 209 → 215
SKU ITEM-001: 50 → 48
SKU ITEM-002: 0 → 10
...
```

### Example 2: Scheduled Daily Sync

Cron job runs every night at 2 AM:
```bash
0 2 * * * /usr/bin/php /var/www/html/lag-int/scripts/sync-inventory.php >> /var/www/html/lag-int/logs/inventory-sync.log 2>&1
```

### Example 3: Programmatic Usage

```php
<?php
require_once 'vendor/autoload.php';

use Laguna\Integration\Services\InventorySyncService;

$syncService = new InventorySyncService();
$result = $syncService->syncInventory(['limit' => 50]);

if ($result['success']) {
    // Send notification
    mail('admin@example.com', 'Inventory Sync Complete', 
        "Updated: {$result['synced_count']} products\n" .
        "Errors: {$result['error_count']} products"
    );
}
?>
```

## Security Considerations

1. **Access Control**: 
   - Web endpoint only accessible from localhost by default
   - Add authentication for remote access
   - Monitor access logs for suspicious activity

2. **API Credentials**:
   - Store in `config/credentials.php` with 600 permissions
   - Never commit to version control
   - Rotate tokens regularly

3. **Data Validation**:
   - All SKU values are validated before API calls
   - API responses are strictly validated
   - Error messages are logged but not exposed to users

## Support

For issues or questions:
1. Check logs in `logs/app.log`
2. Review this documentation
3. Test API connections via `/status.php`
4. Contact system administrator

## Related Documentation

- [API Setup](API_CREDENTIALS.md)
- [NetSuite Integration](NETSUITE_INTEGRATION.md)
- [3DCart Integration](3DCART_INTEGRATION.md)
- [Logging & Troubleshooting](TROUBLESHOOTING.md)