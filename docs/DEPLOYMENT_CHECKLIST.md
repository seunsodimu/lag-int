# Store Shipment Feature - Deployment Checklist

## ✅ Pre-Deployment Verification

### 1. File Syntax Check
All files have been verified for PHP syntax errors:

```bash
# Verify all modified files
php -l src/Services/NetSuiteService.php
php -l src/Controllers/WebhookController.php
php -l src/Exceptions/StoreCustomerNotFoundException.php
php -l config/config.php
```

**Status**: ✅ All files pass syntax check

### 2. Files Modified/Created

#### Created Files:
- ✅ `src/Exceptions/StoreCustomerNotFoundException.php` - Custom exception class
- ✅ `STORE_SHIPMENT_IMPLEMENTATION.md` - Comprehensive documentation
- ✅ `STORE_SHIPMENT_QUICK_REFERENCE.md` - Quick reference guide
- ✅ `DEPLOYMENT_CHECKLIST.md` - This file

#### Modified Files:
- ✅ `config/config.php` - Added base_url configuration
- ✅ `src/Services/NetSuiteService.php` - Added handleStoreShipmentCustomer() method
- ✅ `src/Controllers/WebhookController.php` - Added exception handling in 2 methods
- ✅ `IMPLEMENTATION_SUMMARY.md` - Updated with Store Shipment references

### 3. Configuration Requirements

#### Environment Variables (Production)
```bash
# Required for production deployment
APP_BASE_URL=https://yourdomain.com/lag-int

# Already configured (verify these exist)
FAILED_ORDER_RECIPIENTS=admin@example.com,manager@example.com
```

#### Default Values (Development)
```
APP_BASE_URL: http://localhost/lag-int (default)
```

## 🚀 Deployment Steps

### Step 1: Backup Current System
```bash
# Backup modified files before deployment
cp config/config.php config/config.php.backup
cp src/Services/NetSuiteService.php src/Services/NetSuiteService.php.backup
cp src/Controllers/WebhookController.php src/Controllers/WebhookController.php.backup
```

### Step 2: Deploy Files
```bash
# Upload/copy all modified and new files to production server
# Ensure proper permissions (644 for PHP files, 755 for directories)
```

### Step 3: Set Environment Variables
```bash
# In production .env file or server configuration
APP_BASE_URL=https://your-production-domain.com/lag-int
```

### Step 4: Clear Caches (if applicable)
```bash
# Clear any PHP opcache or application cache
# Restart PHP-FPM if needed
```

### Step 5: Verify Autoloader
```bash
# Ensure the new exception class is autoloaded
composer dump-autoload
```

## 🧪 Post-Deployment Testing

### Test 1: Verify Configuration
```bash
# Check that base_url is correctly set
php -r "require 'config/config.php'; echo \$config['app']['base_url'];"
```

**Expected Output**: Your production URL

### Test 2: Test Store Shipment with Existing Customer

**Test Order Data**:
```json
{
  "OrderID": "TEST_STORE_001",
  "BillingPaymentMethod": "Store Shipment",
  "QuestionList": [
    {
      "QuestionID": 1,
      "QuestionAnswer": "existing-store@customer.com"
    }
  ],
  "BillingFirstName": "Test",
  "BillingLastName": "Customer",
  "BillingEmail": "billing@test.com"
}
```

**Expected Result**: 
- ✅ Order processes successfully
- ✅ Log shows: "Found existing store customer for Store Shipment"
- ✅ Sales order created in NetSuite

### Test 3: Test Store Shipment with Non-Existent Customer

**Test Order Data**:
```json
{
  "OrderID": "TEST_STORE_002",
  "BillingPaymentMethod": "Store Shipment",
  "QuestionList": [
    {
      "QuestionID": 1,
      "QuestionAnswer": "nonexistent@customer.com"
    }
  ],
  "BillingFirstName": "Test",
  "BillingLastName": "Customer",
  "BillingEmail": "billing@test.com"
}
```

**Expected Result**:
- ❌ Order fails with StoreCustomerNotFoundException
- ✅ Log shows: "Store customer not found for Store Shipment order"
- ✅ Email sent to failed order recipients
- ✅ Email contains direct link to order
- ✅ Response includes: `requires_manual_action: true`
- ✅ No retry attempts made

### Test 4: Test Store Shipment with Invalid Email

**Test Order Data**:
```json
{
  "OrderID": "TEST_STORE_003",
  "BillingPaymentMethod": "Store Shipment",
  "QuestionList": [
    {
      "QuestionID": 1,
      "QuestionAnswer": "not-an-email"
    }
  ]
}
```

**Expected Result**:
- ❌ Order fails immediately
- ✅ Log shows: "Store Shipment order missing valid customer email"
- ✅ Email sent with error details

### Test 5: Test Order Link Functionality

**Steps**:
1. Trigger a Store Shipment failure (Test 3)
2. Check email inbox for failed order notification
3. Click the order link in the email

**Expected Result**:
- ✅ Link format: `{base_url}/public/order-status-manager.php?order_id=TEST_STORE_002`
- ✅ Order Status Manager opens with correct order displayed
- ✅ Order details are visible

### Test 6: Backward Compatibility

**Test Other Payment Methods**:
```json
{
  "OrderID": "TEST_REGULAR_001",
  "BillingPaymentMethod": "Credit Card",
  "BillingEmail": "customer@example.com",
  "BillingFirstName": "Regular",
  "BillingLastName": "Customer"
}
```

**Expected Result**:
- ✅ Order processes normally (existing behavior)
- ✅ Customer auto-created if not found
- ✅ No changes to existing logic

## 📊 Monitoring

### Log Files to Monitor
```bash
# Watch application logs for Store Shipment activity
tail -f logs/app-$(date +%Y-%m-%d).log | grep "Store Shipment"

# Watch for Store Shipment failures
tail -f logs/app-$(date +%Y-%m-%d).log | grep "Store customer not found"
```

### Key Log Messages

**Success**:
```
[INFO] Processing Store Shipment customer
[INFO] Found existing store customer for Store Shipment
```

**Failure**:
```
[ERROR] Store customer not found for Store Shipment order
[ERROR] Store Shipment order missing valid customer email
```

### Email Notifications

Monitor failed order email inbox for:
- Subject: "Failed Order Notification - Store Customer Not Found"
- Contains order ID and customer email
- Contains direct link to order

## 🔍 Troubleshooting

### Issue: Parse Error on Deployment

**Symptoms**: PHP parse error mentioning NetSuiteService.php

**Solution**:
```bash
# Verify file syntax
php -l src/Services/NetSuiteService.php

# If errors, restore backup and re-deploy
cp src/Services/NetSuiteService.php.backup src/Services/NetSuiteService.php
```

### Issue: Exception Class Not Found

**Symptoms**: `Class 'LagunaIntegrations\Exceptions\StoreCustomerNotFoundException' not found`

**Solution**:
```bash
# Regenerate autoloader
composer dump-autoload

# Verify file exists
ls -la src/Exceptions/StoreCustomerNotFoundException.php
```

### Issue: Order Link Doesn't Work

**Symptoms**: Clicking email link shows 404 or wrong page

**Solution**:
```bash
# Check base_url configuration
php -r "require 'config/config.php'; var_dump(\$config['app']['base_url']);"

# Update APP_BASE_URL environment variable
# Ensure it matches your actual domain
```

### Issue: No Email Sent on Failure

**Symptoms**: Store Shipment fails but no email received

**Solution**:
```bash
# Check failed order recipients configuration
php -r "require 'config/config.php'; var_dump(\$config['email']['failed_order_recipients']);"

# Check email service logs
tail -f logs/app-*.log | grep "sendFailedOrderNotification"
```

### Issue: Customer Exists But Still Fails

**Symptoms**: Customer is in NetSuite but order still fails

**Solution**:
1. Verify exact email match (case-insensitive)
2. Check customer is searchable in NetSuite
3. Review `findStoreCustomer()` query logic
4. Check logs for SuiteQL query results

## 📋 Rollback Plan

If issues occur after deployment:

### Step 1: Restore Backup Files
```bash
cp config/config.php.backup config/config.php
cp src/Services/NetSuiteService.php.backup src/Services/NetSuiteService.php
cp src/Controllers/WebhookController.php.backup src/Controllers/WebhookController.php
```

### Step 2: Remove New Exception File
```bash
rm src/Exceptions/StoreCustomerNotFoundException.php
```

### Step 3: Regenerate Autoloader
```bash
composer dump-autoload
```

### Step 4: Clear Caches
```bash
# Clear PHP opcache
# Restart PHP-FPM if needed
```

### Step 5: Verify System
```bash
# Test a regular order to ensure system is working
# Check logs for any errors
```

## ✅ Sign-Off Checklist

Before marking deployment as complete:

- [ ] All files deployed successfully
- [ ] No PHP syntax errors
- [ ] Environment variables configured
- [ ] Autoloader regenerated
- [ ] Test 1: Configuration verified
- [ ] Test 2: Store Shipment with existing customer works
- [ ] Test 3: Store Shipment with non-existent customer fails correctly
- [ ] Test 4: Invalid email handled properly
- [ ] Test 5: Order link in email works
- [ ] Test 6: Backward compatibility confirmed
- [ ] Logs show correct messages
- [ ] Email notifications received
- [ ] Documentation accessible to team
- [ ] Backup files retained

## 📞 Support Contacts

**For Deployment Issues**:
- Check logs first: `logs/app-{date}.log`
- Review documentation: `STORE_SHIPMENT_IMPLEMENTATION.md`
- Quick reference: `STORE_SHIPMENT_QUICK_REFERENCE.md`

**Emergency Rollback**:
- Follow rollback plan above
- Document issues encountered
- Contact development team

## 📚 Documentation Files

All documentation is available in the project root:

1. **STORE_SHIPMENT_IMPLEMENTATION.md** - Complete technical documentation
2. **STORE_SHIPMENT_QUICK_REFERENCE.md** - Quick reference guide
3. **IMPLEMENTATION_SUMMARY.md** - Overall feature summary
4. **TESTING_GUIDE.md** - Comprehensive testing procedures
5. **DEPLOYMENT_CHECKLIST.md** - This file

---

**Deployment Date**: _________________

**Deployed By**: _________________

**Verified By**: _________________

**Status**: ⬜ Success  ⬜ Issues (document below)

**Notes**:
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________