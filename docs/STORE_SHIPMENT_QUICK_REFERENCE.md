# Store Shipment Quick Reference Guide

## What is Store Shipment?

**Store Shipment** is a payment method where orders are shipped to a store location. The store customer must already exist in NetSuite before the order can be processed.

## Key Differences

| Feature | Store Shipment | Other Payment Methods |
|---------|---------------|----------------------|
| Customer Creation | ❌ Never auto-creates | ✅ Auto-creates if needed |
| Validation | ✅ Must exist in NetSuite | ⚠️ Creates if missing |
| Failure Behavior | 🛑 Stops immediately | 🔄 May retry |
| Email Notification | ✅ With order link | ✅ Standard notification |

## How It Works

### 1. Order Received
```
Payment Method: "Store Shipment"
QuestionList → QuestionID=1 → QuestionAnswer: "store@example.com"
```

### 2. System Validates
- ✅ Extracts email from QuestionList
- ✅ Validates email format
- ✅ Searches NetSuite for customer

### 3. Two Outcomes

#### ✅ Customer Found
```
→ Use customer ID
→ Process order normally
→ Create sales order in NetSuite
```

#### ❌ Customer NOT Found
```
→ Stop processing
→ Log error with details
→ Send email with order link
→ Return failure (no retry)
→ Manual action required
```

## Email Notification

When a Store Shipment customer is not found, administrators receive:

```
Subject: Failed Order Notification - Store Customer Not Found

Order ID: 1234567
Payment Method: Store Shipment
Customer Email: store@example.com
Error: Store customer 'store@example.com' does not exist in NetSuite

Action Required: Create the store customer in NetSuite first, then retry this order

View Order: [Click here to view order]
```

## Manual Resolution Steps

### Step 1: Check Email
1. Open the failed order notification email
2. Note the customer email address

### Step 2: Create Customer in NetSuite
1. Log into NetSuite
2. Navigate to: Lists → Relationships → Customers → New
3. Enter customer details with the exact email from the notification
4. Save the customer

### Step 3: Retry Order
1. Click the order link in the email (opens Order Status Manager)
2. Click "Retry Processing" button
3. Order should now process successfully

## Configuration

### Environment Variable (Production)
```bash
APP_BASE_URL=https://yourdomain.com/lag-int
```

### Default (Development)
```
http://localhost/lag-int
```

## Common Issues

### Issue: "No email provided"
**Cause**: QuestionList missing or empty  
**Solution**: Verify 3DCart webhook includes QuestionList with QuestionID=1

### Issue: "Invalid email format"
**Cause**: Email in QuestionAnswer is not valid  
**Solution**: Check QuestionAnswer value, ensure it's a valid email

### Issue: Order link doesn't work
**Cause**: Incorrect base_url configuration  
**Solution**: Set APP_BASE_URL environment variable

### Issue: Customer exists but still fails
**Cause**: Email mismatch between order and NetSuite  
**Solution**: Verify exact email match (case-insensitive search is used)

## Logging

All Store Shipment operations are logged with:
- Order ID
- Customer email
- Search results
- Success/failure status

**Log Location**: `logs/app-{date}.log`

**Search Logs**:
```bash
# Find Store Shipment processing
grep "Store Shipment" logs/app-*.log

# Find specific order
grep "order_id.*1234567" logs/app-*.log
```

## Testing

### Test Store Shipment Success
```json
{
  "OrderID": "TEST123",
  "BillingPaymentMethod": "Store Shipment",
  "QuestionList": [
    {
      "QuestionID": 1,
      "QuestionAnswer": "existing@customer.com"
    }
  ]
}
```
**Expected**: Order processes successfully

### Test Store Shipment Failure
```json
{
  "OrderID": "TEST456",
  "BillingPaymentMethod": "Store Shipment",
  "QuestionList": [
    {
      "QuestionID": 1,
      "QuestionAnswer": "nonexistent@customer.com"
    }
  ]
}
```
**Expected**: Order fails, email sent with link

## API Response

### Success Response
```json
{
  "success": true,
  "message": "Order processed successfully",
  "netsuite_order_id": "12345",
  "customer_id": "67890"
}
```

### Failure Response
```json
{
  "success": false,
  "error": "Store customer 'store@example.com' does not exist in NetSuite",
  "requires_manual_action": true,
  "customer_email": "store@example.com"
}
```

## Payment Method Routing

```
Order Received
    ↓
Check BillingPaymentMethod
    ↓
    ├─ "Dropship to Customer" → handleDropshipCustomer()
    │                            (Creates person under parent company)
    │
    ├─ "Store Shipment" → handleStoreShipmentCustomer()
    │                      (Validates customer exists, NO auto-create)
    │
    └─ All Others → handleRegularCustomer()
                    (Standard find/create logic)
```

## Code Locations

### Exception Class
```
src/Exceptions/StoreCustomerNotFoundException.php
```

### Customer Handling
```
src/Services/NetSuiteService.php
→ findOrCreateCustomerByPaymentMethod()
→ handleStoreShipmentCustomer()
```

### Exception Handling
```
src/Controllers/WebhookController.php
→ processOrder() - catch StoreCustomerNotFoundException
→ processOrderFromWebhookData() - catch StoreCustomerNotFoundException
```

### Configuration
```
config/config.php
→ app.base_url
```

## Quick Checklist

Before deploying:
- [ ] Set APP_BASE_URL environment variable
- [ ] Verify failed order email recipients configured
- [ ] Test with existing store customer (should succeed)
- [ ] Test with non-existent customer (should fail with email)
- [ ] Verify order link in email works
- [ ] Check logs for proper Store Shipment entries

## Support

**Check Logs First**:
```bash
tail -f logs/app-$(date +%Y-%m-%d).log | grep "Store Shipment"
```

**Common Log Messages**:
- ✅ "Found store customer for Store Shipment order"
- ❌ "Store customer not found in NetSuite"
- ⚠️ "Invalid or missing store customer email"

**Need Help?**
1. Gather order ID and customer email
2. Check logs for error details
3. Review STORE_SHIPMENT_IMPLEMENTATION.md
4. Contact development team with details