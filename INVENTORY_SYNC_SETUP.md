# Inventory Sync Feature - Quick Setup Guide

## What Was Added

A complete inventory synchronization feature that updates 3DCart product stock from NetSuite quantities.

### New Files Created

1. **Service**: `src/Services/InventorySyncService.php`
   - Core service handling inventory sync logic
   - Fetches products from 3DCart v1 API
   - Searches NetSuite for items by SKU
   - Updates product stock in 3DCart

2. **Job Script**: `scripts/sync-inventory.php`
   - Command-line executable script
   - Can be run manually or via cron job
   - Supports limit and offset parameters

3. **Web Endpoint**: `public/run-inventory-sync.php`
   - Admin-friendly web interface
   - One-click synchronization
   - Visual interface for browser access
   - JSON API endpoint for automated calls

4. **Documentation**: `documentation/INVENTORY_SYNC.md`
   - Comprehensive feature documentation
   - Usage examples and troubleshooting

### NetSuiteService Enhancement

Added new public method to `src/Services/NetSuiteService.php`:
```php
public function searchItemBySku($sku)
```
- Searches NetSuite for items by SKU
- Returns item data including `totalquantityonhand`
- Tries multiple item types (inventory, non-inventory, service)

## Quick Start

### Option 1: Manual Web Interface (Easiest)

1. Open browser to: `http://yoursite.com/run-inventory-sync.php`
2. Click "Start Synchronization" button
3. View results in real-time

### Option 2: Command Line

```bash
# Run with defaults
php scripts/sync-inventory.php

# Run with custom settings
php scripts/sync-inventory.php --limit=50 --offset=0
```

### Option 3: Scheduled Job (Recommended)

**Linux/Unix Cron**:
```bash
# Add to crontab (daily at 2 AM)
0 2 * * * /usr/bin/php /path/to/lag-int/scripts/sync-inventory.php
```

**Windows Task Scheduler**:
1. Create new task
2. Program: `C:\xampp\php\php.exe`
3. Arguments: `C:\xampp\htdocs\lag-int\scripts\sync-inventory.php`

## How It Works

```
3DCart Products (v1 API)
    ↓ (Fetch with SKU)
    │
    ├─→ Search NetSuite by SKU
    │
    ├─→ Get totalquantityonhand
    │
    └─→ Update SKUInfo→Stock in 3DCart
```

## Key Features

✅ **Automatic Lookups**: Uses `findOrCreateItem()` logic to find NetSuite items  
✅ **SKU Matching**: Matches 3DCart SKU to NetSuite ItemID  
✅ **Smart Updates**: Only updates if stock quantity changed  
✅ **Error Handling**: Logs all operations and errors  
✅ **Pagination Support**: Handle large catalogs with limit/offset  
✅ **Change Tracking**: Detailed before/after stock levels  

## Response Example

```json
{
  "success": true,
  "total_products": 150,
  "synced_count": 45,
  "skipped_count": 100,
  "error_count": 5,
  "products": [
    {
      "success": true,
      "sku": "MBAND1412-175",
      "old_stock": 209,
      "new_stock": 215
    }
  ]
}
```

## Monitoring

Check logs in `logs/app.log` for:
- Sync start/completion timestamps
- Products synced vs skipped
- Detailed error messages
- API performance metrics

Example log:
```
2025-01-15 14:30:00 INFO Starting inventory synchronization
2025-01-15 14:30:01 DEBUG Retrieved products from 3DCart [count: 100]
2025-01-15 14:35:00 INFO Inventory synchronization completed [synced: 45, skipped: 100, errors: 5]
```

## Troubleshooting

**Problem**: Products not updating
- Solution: Check SKU format matches between 3DCart and NetSuite

**Problem**: API errors
- Solution: Verify credentials in `config/credentials.php`

**Problem**: Performance issues
- Solution: Reduce `limit` parameter to sync fewer products per run

## API Details

### 3DCart API v1
- **Endpoint**: `https://apirest.3dcart.com/3dCartWebAPI/v1/Products`
- **Method**: GET (fetch), PUT (update)
- **Auth**: PrivateKey, Token headers

### NetSuite API
- **Endpoints**: /inventoryItem, /noninventoryItem, /serviceItem, /item
- **Method**: GET (search)
- **Auth**: OAuth 1.0

## File Locations

```
lag-int/
├── src/Services/
│   ├── InventorySyncService.php (NEW)
│   └── NetSuiteService.php (UPDATED - new searchItemBySku method)
├── scripts/
│   └── sync-inventory.php (NEW)
├── public/
│   └── run-inventory-sync.php (NEW)
├── documentation/
│   └── INVENTORY_SYNC.md (NEW)
└── INVENTORY_SYNC_SETUP.md (THIS FILE)
```

## Testing

Test the feature without side effects:
```bash
# Test with small batch
php scripts/sync-inventory.php --limit=5

# Access web interface
http://localhost/run-inventory-sync.php

# Check logs for results
tail -f logs/app.log
```

## Support & More Info

- Full docs: See `documentation/INVENTORY_SYNC.md`
- API credentials setup: See `documentation/API_CREDENTIALS.md`
- Troubleshooting: See `documentation/TROUBLESHOOTING.md`

---

**Created**: January 2025  
**Feature**: Inventory Synchronization (NetSuite ↔ 3DCart)  
**Status**: Ready for production