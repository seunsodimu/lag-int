# Daily Status Sync - Date Filtering Enhancement

## Overview

The daily status synchronization script has been enhanced to support date filtering, allowing you to process only orders created after a specified date. This is particularly useful for:

- Processing recent orders only
- Reducing processing time for large order volumes
- Targeted synchronization after system maintenance
- Historical data processing with specific date ranges

## Usage

### Command Line

```bash
# Process all orders (no date filter)
php daily-status-sync.php

# Process orders created after a specific date
php daily-status-sync.php 2025-09-01

# Process orders from the last 7 days
php daily-status-sync.php $(date -d '7 days ago' +%Y-%m-%d)

# Process orders from today
php daily-status-sync.php $(date +%Y-%m-%d)
```

### Web Browser

```
# Process all orders (no date filter)
http://localhost/lag-int/public/run-daily-status-sync.php

# Process orders created after a specific date
http://localhost/lag-int/public/run-daily-status-sync.php?after_date=2025-09-01

# Process orders from a recent date
http://localhost/lag-int/public/run-daily-status-sync.php?after_date=2025-09-30
```

### Remote Server

```bash
# SSH into your server
ssh username@your-server.com

# Navigate to project directory
cd /var/www/lag-int

# Run with date filter
php scripts/daily-status-sync.php 2025-09-25

# Run without date filter (all orders)
php scripts/daily-status-sync.php
```

## Date Format

- **Required Format**: `YYYY-MM-DD`
- **Examples**: 
  - `2025-09-01` (September 1, 2025)
  - `2025-12-25` (December 25, 2025)
  - `2024-01-15` (January 15, 2024)

### Invalid Formats (will cause errors):
- `09-01-2025` ❌
- `2025/09/01` ❌
- `Sep 1, 2025` ❌
- `2025-13-01` ❌ (invalid month)
- `2025-09-32` ❌ (invalid day)

## How It Works

### 1. Order Retrieval
When a date is provided, the script:
- Converts the date to 3DCart API format (`MM/dd/yyyy HH:mm:ss`)
- Adds `datestart` parameter to the 3DCart API call
- Retrieves only orders with `OrderDate >= specified_date`

### 2. Processing Logic
The enhanced script follows this flow:
1. **Parse Arguments**: Check for date parameter from command line or web request
2. **Validate Date**: Ensure date format is correct (YYYY-MM-DD)
3. **Retrieve Orders**: Get orders by status with optional date filter
4. **Process Each Order**: Check NetSuite status and update 3DCart if needed
5. **Report Results**: Show summary with date filter information

### 3. API Calls
```php
// Without date filter
GET /Orders?orderstatus=2&limit=100&offset=0

// With date filter (after 2025-09-01)
GET /Orders?orderstatus=2&datestart=09/01/2025 00:00:00&limit=100&offset=0
```

## Output Examples

### With Date Filter
```
Daily Order Status Synchronization Results:
==========================================
Date filter: Orders created after 2025-09-30
Total orders processed: 9
Orders updated: 0
Errors encountered: 0
Job completed at: 2025-10-02 09:58:15
```

### Without Date Filter
```
Daily Order Status Synchronization Results:
==========================================
Date filter: All orders (no date filter applied)
Total orders processed: 100
Orders updated: 0
Errors encountered: 0
Job completed at: 2025-10-02 09:58:15
```

## Performance Benefits

### Before Enhancement
- Always processed all orders with status "Processing"
- Could retrieve 100+ orders regardless of age
- Slower execution for large order volumes

### After Enhancement
- Can limit to recent orders only
- Faster execution when filtering by date
- More targeted processing

### Example Performance Comparison
```bash
# All orders (slower)
php daily-status-sync.php
# Result: 100 orders processed in ~45 seconds

# Recent orders only (faster)
php daily-status-sync.php 2025-09-30
# Result: 9 orders processed in ~8 seconds
```

## Use Cases

### 1. Daily Cron Job (Recent Orders Only)
```bash
# Process orders from the last 2 days
0 9 * * * /usr/bin/php /var/www/lag-int/scripts/daily-status-sync.php $(date -d '2 days ago' +%Y-%m-%d)
```

### 2. Manual Processing After Maintenance
```bash
# Process orders created after maintenance window
php daily-status-sync.php 2025-09-28
```

### 3. Historical Data Processing
```bash
# Process orders from a specific month
php daily-status-sync.php 2025-08-01
```

### 4. Troubleshooting Specific Date Range
```bash
# Check orders from when issues were reported
php daily-status-sync.php 2025-09-25
```

## Error Handling

### Invalid Date Format
```bash
$ php daily-status-sync.php "invalid-date"
ERROR: Invalid date format. Please use YYYY-MM-DD format.
Example: php daily-status-sync.php 2025-09-01
```

### Future Date
The script accepts future dates, but they will return no results since no orders exist yet.

### Very Old Dates
The script will process all orders since the specified date, which may take longer for very old dates.

## Logging

All date filtering activities are logged with context:

```
[2025-10-02 09:58:15] 3dcart-netsuite.INFO: Starting daily order status update process {"after_date":"2025-09-30"}
[2025-10-02 09:58:15] 3dcart-netsuite.INFO: Fetching orders by status after date {"status_id":2,"after_date":"2025-09-30","formatted_after_date":"09/30/2025 00:00:00","limit":100,"offset":0}
[2025-10-02 09:58:15] 3dcart-netsuite.INFO: Retrieved orders by status after date from 3DCart {"status_id":2,"after_date":"2025-09-30","count":9,"limit":100,"offset":0}
```

## Technical Implementation

### New Methods Added

1. **ThreeDCartService::getOrdersByStatusAfterDate()**
   - Retrieves orders by status with date filtering
   - Converts YYYY-MM-DD to 3DCart API format
   - Adds proper logging and error handling

2. **OrderStatusSyncService::processDailyStatusUpdates($afterDate)**
   - Enhanced to accept optional date parameter
   - Uses appropriate method based on date parameter
   - Logs date filter information

### Backward Compatibility
- All existing functionality remains unchanged
- Scripts without date parameters work exactly as before
- No breaking changes to existing integrations

## Best Practices

1. **Use Recent Dates**: For daily processing, use dates from the last few days
2. **Monitor Performance**: Very old dates may process many orders
3. **Test First**: Try with a recent date to verify functionality
4. **Log Review**: Check logs to confirm correct date filtering
5. **Cron Jobs**: Update cron jobs to use appropriate date filters

## Troubleshooting

### No Orders Found
If no orders are returned:
- Check if orders exist after the specified date
- Verify date format is correct (YYYY-MM-DD)
- Ensure orders have status "Processing" (status ID = 2)

### Unexpected Results
- Check 3DCart API logs for actual query parameters
- Verify timezone settings (script uses America/New_York)
- Review order dates in 3DCart admin panel

### Performance Issues
- Use more recent dates to limit order volume
- Consider processing in smaller batches
- Monitor server resources during execution