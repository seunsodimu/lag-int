# Store Shipment Customer Validation Implementation

## Overview
This document describes the implementation of Store Shipment customer validation for the 3DCart to NetSuite integration system. When an order uses the "Store Shipment" payment method, the system now validates that the store customer exists in NetSuite before processing the order.

## Business Requirements

### Store Shipment Orders
- **Payment Method**: "Store Shipment"
- **Customer Source**: Email address from QuestionList (QuestionID=1, QuestionAnswer contains email)
- **Validation Rule**: Store customer MUST exist in NetSuite
- **Failure Behavior**: Stop processing and notify administrators with direct link to order

### Key Differences from Other Payment Methods
1. **No Auto-Creation**: Unlike other payment methods, Store Shipment does NOT automatically create customers
2. **Strict Validation**: Order processing stops immediately if customer is not found
3. **Manual Action Required**: Administrators must create the customer in NetSuite first
4. **No Retry Logic**: System does not retry Store Shipment failures (manual intervention needed)

## Implementation Details

### 1. Configuration Changes

**File**: `config/config.php`

Added base URL configuration for building direct links to orders:

```php
'app' => [
    'name' => getenv('APP_NAME') ?: '3DCart to NetSuite Integration',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost/lag-int',
    // ... other settings
]
```

**Environment Variable**: `APP_BASE_URL` (optional, defaults to localhost)

### 2. Custom Exception Class

**File**: `src/Exceptions/StoreCustomerNotFoundException.php`

Created a specialized exception for Store Shipment customer validation failures:

```php
class StoreCustomerNotFoundException extends \Exception
{
    private $orderId;
    private $customerEmail;
    
    public function __construct($orderId, $customerEmail, $message = "")
    {
        $this->orderId = $orderId;
        $this->customerEmail = $customerEmail;
        
        $fullMessage = "Store customer not found for Store Shipment order";
        if ($message) {
            $fullMessage .= ": " . $message;
        }
        
        parent::__construct($fullMessage);
    }
    
    public function getOrderId() { return $this->orderId; }
    public function getCustomerEmail() { return $this->customerEmail; }
}
```

**Purpose**: 
- Distinguishes Store Shipment failures from other errors
- Carries order ID and customer email for detailed error reporting
- Enables specific exception handling without retry logic

### 3. NetSuite Service Enhancements

**File**: `src/Services/NetSuiteService.php`

#### 3.1 Modified Payment Method Router

Updated `findOrCreateCustomerByPaymentMethod()` to add Store Shipment handling:

```php
public function findOrCreateCustomerByPaymentMethod($orderData) {
    $paymentMethod = $orderData['BillingPaymentMethod'] ?? '';
    $orderId = $orderData['OrderID'] ?? 'unknown';
    
    $this->logger->info('Starting customer assignment by payment method', [
        'order_id' => $orderId,
        'payment_method' => $paymentMethod
    ]);
    
    // Route to appropriate handler based on payment method
    if ($paymentMethod === 'Dropship to Customer') {
        return $this->handleDropshipCustomer($orderData);
    } elseif ($paymentMethod === 'Store Shipment') {
        return $this->handleStoreShipmentCustomer($orderData);
    } else {
        return $this->handleRegularCustomer($orderData);
    }
}
```

#### 3.2 New Store Shipment Handler

Created `handleStoreShipmentCustomer()` method:

```php
private function handleStoreShipmentCustomer($orderData) {
    $orderId = $orderData['OrderID'] ?? 'unknown';
    
    $this->logger->info('Processing Store Shipment customer', [
        'order_id' => $orderId,
        'note' => 'Store customer must exist in NetSuite'
    ]);
    
    // Extract store customer email from QuestionList
    $storeCustomerEmail = null;
    if (isset($orderData['QuestionList']) && is_array($orderData['QuestionList'])) {
        foreach ($orderData['QuestionList'] as $question) {
            if (isset($question['QuestionID']) && $question['QuestionID'] == 1) {
                $storeCustomerEmail = trim($question['QuestionAnswer'] ?? '');
                break;
            }
        }
    }
    
    // Validate email exists
    if (empty($storeCustomerEmail) || !filter_var($storeCustomerEmail, FILTER_VALIDATE_EMAIL)) {
        $this->logger->error('Invalid or missing store customer email', [
            'order_id' => $orderId,
            'email_provided' => $storeCustomerEmail
        ]);
        throw new StoreCustomerNotFoundException(
            $orderId,
            $storeCustomerEmail ?: 'No email provided',
            'Store customer email is required in QuestionList (QuestionID=1)'
        );
    }
    
    $this->logger->info('Searching for store customer', [
        'order_id' => $orderId,
        'customer_email' => $storeCustomerEmail
    ]);
    
    // Search for existing store customer
    $existingCustomer = $this->findStoreCustomer($storeCustomerEmail);
    
    if ($existingCustomer) {
        $this->logger->info('Found store customer for Store Shipment order', [
            'order_id' => $orderId,
            'customer_id' => $existingCustomer['id'],
            'customer_email' => $storeCustomerEmail
        ]);
        return $existingCustomer['id'];
    }
    
    // Customer not found - throw exception (do NOT create)
    $this->logger->error('Store customer not found in NetSuite', [
        'order_id' => $orderId,
        'customer_email' => $storeCustomerEmail,
        'action_required' => 'Create customer in NetSuite first'
    ]);
    
    throw new StoreCustomerNotFoundException(
        $orderId,
        $storeCustomerEmail,
        "Store customer '{$storeCustomerEmail}' does not exist in NetSuite"
    );
}
```

**Key Features**:
- Extracts email from QuestionList (QuestionID=1)
- Validates email format
- Searches for existing customer using `findStoreCustomer()`
- Throws exception if customer not found (no auto-creation)
- Comprehensive logging at each step

### 4. Webhook Controller Exception Handling

**File**: `src/Controllers/WebhookController.php`

Added Store Shipment exception handling in TWO methods:

#### 4.1 In `processOrder()` Method

```php
} catch (StoreCustomerNotFoundException $e) {
    // Store Shipment order with customer not found - requires manual action
    $this->logger->error('Store customer not found for Store Shipment order', [
        'order_id' => $orderId,
        'customer_email' => $e->getCustomerEmail(),
        'error' => $e->getMessage()
    ]);
    
    // Build direct link to order in Order Status Manager
    $baseUrl = $this->config['app']['base_url'] ?? 'http://localhost/lag-int';
    $orderLink = $baseUrl . '/public/order-status-manager.php?order_id=' . urlencode($orderId);
    
    // Send notification with link to order
    $this->emailService->sendFailedOrderNotification('store_customer_not_found', [
        'order_id' => $orderId,
        'payment_method' => $order->getPaymentMethod(),
        'customer_email' => $e->getCustomerEmail(),
        'error_message' => $e->getMessage(),
        'action_required' => 'Create the store customer in NetSuite first, then retry this order',
        'order_link' => $orderLink
    ]);
    
    return [
        'success' => false,
        'error' => $e->getMessage(),
        'requires_manual_action' => true,
        'customer_email' => $e->getCustomerEmail()
    ];
    
} catch (\Exception $e) {
    // ... existing general exception handling with retry logic
}
```

#### 4.2 In `processOrderFromWebhookData()` Method

Same exception handling logic added for webhook-based order processing.

**Important Notes**:
- Exception catch MUST be placed BEFORE the general `\Exception` catch block
- No retry logic for Store Shipment failures (unlike other errors)
- Returns `requires_manual_action: true` flag
- Builds direct link using GET parameter: `?order_id={orderId}`

### 5. Email Notification

**Notification Type**: `store_customer_not_found`

**Email Content**:
- Order ID
- Payment Method (Store Shipment)
- Customer Email that was searched
- Error Message
- Action Required instructions
- Direct link to Order Status Manager

**Example Email**:
```
Subject: Failed Order Notification - Store Customer Not Found

Order ID: 1234567
Payment Method: Store Shipment
Customer Email: store@example.com
Error: Store customer 'store@example.com' does not exist in NetSuite

Action Required: Create the store customer in NetSuite first, then retry this order

View Order: http://yourdomain.com/lag-int/public/order-status-manager.php?order_id=1234567
```

## Order Status Manager Integration

### GET Parameter Support

The Order Status Manager now supports direct linking via GET parameter:

**URL Format**: `order-status-manager.php?order_id={orderId}`

**Example**: `http://localhost/lag-int/public/order-status-manager.php?order_id=1234567`

This allows email recipients to click a link and immediately view the problematic order.

## Payment Method Comparison

| Payment Method | Customer Handling | Auto-Create | Validation |
|---------------|------------------|-------------|------------|
| **Dropship to Customer** | Creates person customer under parent company | Yes | Validates parent company exists |
| **Store Shipment** | Uses existing store customer | **NO** | **Requires customer exists** |
| **All Others** | Regular customer find/create logic | Yes | Standard validation |

## Error Flow Diagram

```
Store Shipment Order Received
    ↓
Extract email from QuestionList (QuestionID=1)
    ↓
Validate email format
    ↓
Search for customer in NetSuite
    ↓
    ├─ Found? → Use customer ID → Process order
    │
    └─ Not Found? → Throw StoreCustomerNotFoundException
                        ↓
                    Log error with details
                        ↓
                    Build order link (GET parameter)
                        ↓
                    Send email to failed order recipients
                        ↓
                    Return failure (NO RETRY)
                        ↓
                    Manual Action Required:
                    1. Create customer in NetSuite
                    2. Click link in email to view order
                    3. Retry order processing
```

## Testing Checklist

### Test Case 1: Store Shipment with Existing Customer
- **Setup**: Order with payment method "Store Shipment", valid email in QuestionList
- **Expected**: Customer found, order processes successfully
- **Verify**: Check logs for "Found store customer for Store Shipment order"

### Test Case 2: Store Shipment with Non-Existent Customer
- **Setup**: Order with payment method "Store Shipment", email not in NetSuite
- **Expected**: Order fails, email sent with link
- **Verify**: 
  - Check logs for "Store customer not found in NetSuite"
  - Verify email received with correct order link
  - Verify no retry attempts
  - Verify response has `requires_manual_action: true`

### Test Case 3: Store Shipment with Invalid Email
- **Setup**: Order with payment method "Store Shipment", invalid email format
- **Expected**: Order fails immediately with validation error
- **Verify**: Check logs for "Invalid or missing store customer email"

### Test Case 4: Store Shipment with Missing QuestionList
- **Setup**: Order with payment method "Store Shipment", no QuestionList data
- **Expected**: Order fails with missing email error
- **Verify**: Exception thrown with "No email provided"

### Test Case 5: Order Link Functionality
- **Setup**: Trigger Store Shipment failure, receive email
- **Expected**: Click link in email opens Order Status Manager with correct order
- **Verify**: URL contains `?order_id=` parameter, order displays correctly

### Test Case 6: Backward Compatibility
- **Setup**: Orders with other payment methods (not Store Shipment)
- **Expected**: No changes to existing behavior
- **Verify**: Dropship and regular orders process as before

## Configuration Requirements

### Environment Variables

```bash
# Required for production
APP_BASE_URL=https://yourdomain.com/lag-int

# Email recipients for failed orders (already configured)
FAILED_ORDER_RECIPIENTS=admin@example.com,manager@example.com
```

### Database Requirements

No database changes required. Uses existing:
- `orders` table for order data
- `order_logs` table for logging

## Logging Strategy

All Store Shipment operations include comprehensive logging:

1. **Start Processing**: Payment method identified as Store Shipment
2. **Email Extraction**: Email found/not found in QuestionList
3. **Email Validation**: Valid/invalid email format
4. **Customer Search**: Search initiated with email
5. **Customer Found**: Customer ID and details
6. **Customer Not Found**: Error with email and order ID
7. **Exception Thrown**: Full exception details
8. **Email Sent**: Notification sent with order link

**Log Level**: INFO for success, ERROR for failures

## Security Considerations

1. **Email Validation**: All emails validated with `FILTER_VALIDATE_EMAIL`
2. **URL Encoding**: Order IDs properly encoded in URLs
3. **SQL Injection**: SuiteQL queries use proper escaping
4. **Access Control**: Order Status Manager requires authentication
5. **Error Messages**: No sensitive data exposed in error messages

## Maintenance Notes

### Adding New Payment Methods

To add a new payment method with custom customer handling:

1. Add new condition in `findOrCreateCustomerByPaymentMethod()`
2. Create new handler method (e.g., `handleNewPaymentMethod()`)
3. Add appropriate logging
4. Update documentation

### Modifying Store Shipment Logic

If Store Shipment requirements change:

1. Update `handleStoreShipmentCustomer()` in NetSuiteService.php
2. Update exception handling in WebhookController.php
3. Update email notification template
4. Update this documentation
5. Add new test cases

### Troubleshooting

**Issue**: Store Shipment orders failing with "No email provided"
- **Cause**: QuestionList missing or QuestionID=1 not present
- **Solution**: Verify 3DCart webhook includes QuestionList data

**Issue**: Email sent but link doesn't work
- **Cause**: Incorrect base_url configuration
- **Solution**: Set APP_BASE_URL environment variable correctly

**Issue**: Customer exists but still fails
- **Cause**: Email mismatch or customer not searchable
- **Solution**: Check `findStoreCustomer()` query logic and customer email in NetSuite

## Files Modified

1. **config/config.php** - Added base_url configuration
2. **src/Exceptions/StoreCustomerNotFoundException.php** - New exception class
3. **src/Services/NetSuiteService.php** - Added handleStoreShipmentCustomer() method
4. **src/Controllers/WebhookController.php** - Added exception handling in 2 methods

## Related Documentation

- **IMPLEMENTATION_SUMMARY.md** - Customer NetSuite ID and address features
- **TESTING_GUIDE.md** - Comprehensive testing procedures
- **README.md** - General system documentation

## Version History

- **v1.0** (2025-01-14) - Initial implementation of Store Shipment customer validation

## Support

For issues or questions:
1. Check logs in `logs/app-{date}.log`
2. Review this documentation
3. Contact development team with order ID and error details