# Inventory Sync Email Notifications

## Overview

Email notifications have been integrated into the inventory synchronization feature. After each inventory sync (whether successful or failed), a detailed HTML email report is automatically sent to configured recipients.

## Features

### 📧 Smart Notification System

- **Success Notifications**: Sent when sync completes with no errors
- **Failure Notifications**: Sent when sync encounters critical errors
- **Configurable Recipients**: Use existing notification settings system
- **Both Paths Supported**: Works for CLI execution, scheduled jobs, and web interface
- **Graceful Fallback**: Always falls back to default recipient if no one configured

### 📊 Email Report Contents

Each email includes:

1. **Status Header**
   - Clear success/failure indicator with color coding (green/red)
   - Executive summary with execution timestamp

2. **Sync Statistics**
   - Total products processed
   - Number of products successfully updated
   - Number of products skipped
   - Number of errors encountered
   - Execution duration

3. **Updated Products List** (if any)
   - SKU information
   - Product name
   - Stock changes (old → new quantity)

4. **Error Details** (if any)
   - Detailed error messages for each failed product
   - Critical error information if sync failed

5. **Footer**
   - Automated notification footer
   - Report generation timestamp

### 🎨 Professional HTML Email Template

- Responsive design that works on all email clients
- Color-coded status indicators
- Grid-based layout for statistics
- Properly formatted product and error lists
- Brand-consistent styling

## Configuration

### Setting Up Email Recipients

Use the existing Notification Settings UI to configure recipients:

1. Go to **http://yoursite.com/notification-settings.php**
2. Add recipients for:
   - **"Inventory Sync Success"** - Receives reports when sync completes successfully
   - **"Inventory Sync Failed"** - Receives alerts when sync encounters errors
3. Email addresses are automatically saved to the database

### Default Behavior

- **Default Recipient**: `web_dev@lagunatools.com` (always included)
- **No Configuration Required**: System works even if no custom recipients are configured
- **Flexible**: Add or remove recipients anytime via UI

## Usage

### Automatic Execution

Emails are automatically sent after every sync:

**Via Web Interface:**
```
1. Open http://yoursite.com/run-inventory-sync.php
2. Click "Start Synchronization"
3. Wait for completion
4. Email is automatically sent to configured recipients
```

**Via Command Line:**
```bash
# Default sync
php scripts/sync-inventory.php

# With pagination
php scripts/sync-inventory.php --limit=50 --offset=100
```
Email is automatically sent after completion.

**Via Scheduled Job:**
```bash
# Crontab entry - daily at 2 AM
0 2 * * * /usr/bin/php /path/to/scripts/sync-inventory.php

# Windows Task Scheduler
# Action: Start a program
# Program: C:\xampp\php\php.exe
# Arguments: C:\xampp\htdocs\lag-int\scripts\sync-inventory.php
```
Email is automatically sent after each scheduled execution.

## Technical Implementation

### New Notification Types

Two new notification types added to `NotificationSettingsService`:

```php
const TYPE_INVENTORY_SYNC_SUCCESS = 'inventory_sync_success';
const TYPE_INVENTORY_SYNC_FAILED = 'inventory_sync_failed';
```

### New Methods in InventorySyncService

**Public Method:**
```php
public function sendSyncNotificationEmail($syncResult)
```

Sends a notification email with sync summary. Automatically determines notification type (success/failed) based on sync results.

**Private Helper Methods:**
- `buildNotificationSubject()` - Creates email subject line
- `buildNotificationEmailContent()` - Generates HTML email body
- `calculateDuration()` - Formats sync duration

### Email Service Integration

Uses existing `UnifiedEmailService` which supports:
- Multiple email providers (Brevo, SendGrid, etc.)
- Configurable sender address
- Proper error handling
- Detailed logging

## Example Email Output

### Success Email Subject
```
[3DCart Integration] Inventory Sync SUCCESS - 42 products updated - 2024-01-15 14:30:45
```

### Failure Email Subject
```
[3DCart Integration] Inventory Sync FAILED - Action Required - 2024-01-15 14:30:45
```

## Error Handling

- **No Recipients Configured**: Logs warning, continues execution
- **Email Service Failure**: Logs error, doesn't affect sync operation
- **Template Errors**: Gracefully degrades, includes basic info
- **Disabled Notifications**: System continues to work normally

## Logging

All email notification activities are logged:

- **INFO Level**: Email sent successfully
- **WARNING Level**: No recipients configured
- **ERROR Level**: Email send failure or template errors

Check `logs/app.log` for details:
```
[2024-01-15 14:30:45] INFO: Inventory sync notification email sent
[2024-01-15 14:30:45] WARNING: No email recipients configured for inventory sync notifications
[2024-01-15 14:30:45] ERROR: Failed to send inventory sync notification email
```

## Customization

### Modifying Email Template

Edit the `buildNotificationEmailContent()` method in `InventorySyncService`:

```php
private function buildNotificationEmailContent($syncResult, $isSuccess)
{
    // Customize HTML here
    $html = '...';
    return $html;
}
```

### Custom Recipients Per Sync

If you want to send to different recipients:

```php
// Override recipients
$customRecipients = ['admin@example.com', 'manager@example.com'];
$emailService = new UnifiedEmailService();
$result = $emailService->sendEmail(
    $subject,
    $htmlContent,
    $customRecipients
);
```

## Testing

### Send a Test Email

Use the existing email testing feature:

1. Go to **http://yoursite.com/test-email.php**
2. Select "Integration Report" template
3. Enter your email address
4. Click "Send Test Email"

### Test Notification Configuration

1. Go to **http://yoursite.com/notification-settings.php**
2. Add your test email to "Inventory Sync Success"
3. Run inventory sync: `php scripts/sync-inventory.php --limit=1`
4. Check your inbox for the notification

## Files Modified

### 1. `src/Services/NotificationSettingsService.php`
- Added `TYPE_INVENTORY_SYNC_SUCCESS` constant
- Added `TYPE_INVENTORY_SYNC_FAILED` constant
- Added notification types to `getNotificationTypes()` method

### 2. `src/Services/InventorySyncService.php`
- Added `sendSyncNotificationEmail()` public method
- Added `buildNotificationSubject()` private method
- Added `buildNotificationEmailContent()` private method
- Added `calculateDuration()` private method

### 3. `scripts/sync-inventory.php`
- Added email notification call after successful sync
- Added email notification call after failed sync
- Added output messages showing notification status

## Troubleshooting

### Email Not Sent

1. **Check Recipients**: Verify recipients are configured in Notification Settings
2. **Check Logs**: Look in `logs/app.log` for errors
3. **Verify Email Service**: Run `http://yoursite.com/email-provider-config.php`
4. **Test Email Provider**: Use test email feature to verify service works

### Email Format Issues

1. **Check Email Client**: Some email clients have limited HTML support
2. **View Source**: Check raw email source for encoding issues
3. **Use Different Client**: Try testing in Gmail, Outlook, etc.

### No Recipients Found

If you see "No email recipients configured" warning:

1. Go to **http://yoursite.com/notification-settings.php**
2. Add recipients for "Inventory Sync Success" and "Inventory Sync Failed"
3. Re-run sync to test

## Best Practices

### 1. Configure Multiple Recipients
Add team members who should be notified:
- Admin team
- Operations team
- Finance team

### 2. Monitor for Failures
- Set up alerts for "Inventory Sync Failed" notifications
- Review error messages in emails
- Check logs for underlying issues

### 3. Regular Testing
- Test notification emails monthly
- Update recipient list as team changes
- Monitor email delivery (check spam folder)

### 4. Scheduled Syncs
- Schedule sync during off-hours
- Configure morning email notification delivery
- Review daily reports for trends

## Summary

The inventory sync feature now includes comprehensive email notifications that:

✅ Automatically send after each sync (success or failure)  
✅ Contain detailed, professional reports  
✅ Use existing notification settings system  
✅ Work across all execution methods (web, CLI, cron)  
✅ Include proper error handling and logging  
✅ Support unlimited recipients per notification type  
✅ Gracefully handle missing configuration  

No additional configuration is required - the system works out of the box with the default recipient. Customize recipients anytime via the Notification Settings UI.