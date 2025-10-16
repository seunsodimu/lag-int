# Testing Guide - NetSuite Customer ID and Address Options

## Quick Start Testing

### Prerequisites
1. Access to Order Status Manager (`public/order-status-manager.php`)
2. Valid NetSuite customer ID for testing
3. At least one unsynced 3DCart order in the system

---

## Test Case 1: Basic Customer ID Assignment

**Objective:** Verify that an order can be processed with a manually specified customer ID.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order (Status should not be "Synced to NetSuite")
3. Click on "3DCart Order Information" tab
4. Locate the "NetSuite Customer Options" section (light blue background)
5. Enter a valid NetSuite Customer ID (e.g., `12345`)
6. Leave both address checkboxes unchecked
7. Click "Sync to NetSuite" button
8. Confirm the action in the popup dialog

**Expected Result:**
- Confirmation dialog shows: "Customer ID: 12345"
- Order processes successfully
- Order is linked to customer 12345 in NetSuite
- Success message appears
- Order status updates to "Synced to NetSuite"

**Verification:**
- Check NetSuite: Order should appear under customer 12345
- Check logs: Should show "Processing order with custom customer ID: 12345"

---

## Test Case 2: Invalid Customer ID

**Objective:** Verify that invalid customer IDs are properly rejected.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order
3. Click on "3DCart Order Information" tab
4. Enter an invalid NetSuite Customer ID (e.g., `99999999`)
5. Click "Sync to NetSuite" button
6. Confirm the action

**Expected Result:**
- Error message appears: "Customer ID 99999999 does not exist in NetSuite. Please create the customer first."
- Order is NOT processed
- Order status remains unchanged

**Verification:**
- Check logs: Should show "Customer does not exist in NetSuite"
- Order should still be unsynced

---

## Test Case 3: Add Shipping Address Only

**Objective:** Verify that shipping address can be added to customer profile.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order with shipping information
3. Click on "3DCart Order Information" tab
4. Enter a valid NetSuite Customer ID
5. Check "Add shipping address to customer profile"
6. Leave "Add billing address" unchecked
7. Click "Sync to NetSuite" button
8. Confirm the action

**Expected Result:**
- Confirmation dialog shows:
  - "Customer ID: {id}"
  - "Add Shipping Address: Yes"
  - "Add Billing Address: No"
- Order processes successfully
- Shipping address is added to customer in NetSuite

**Verification:**
- Check NetSuite customer record: New address should appear with label "Shipping Address (3DCart Order #{OrderID})"
- Check logs: Should show "Successfully added addresses to customer"

---

## Test Case 4: Add Billing Address Only

**Objective:** Verify that billing address can be added to customer profile.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order with billing information
3. Click on "3DCart Order Information" tab
4. Enter a valid NetSuite Customer ID
5. Leave "Add shipping address" unchecked
6. Check "Add billing address to customer profile"
7. Click "Sync to NetSuite" button
8. Confirm the action

**Expected Result:**
- Confirmation dialog shows:
  - "Customer ID: {id}"
  - "Add Shipping Address: No"
  - "Add Billing Address: Yes"
- Order processes successfully
- Billing address is added to customer in NetSuite

**Verification:**
- Check NetSuite customer record: New address should appear with label "Billing Address (3DCart Order #{OrderID})"
- Check logs: Should show "Successfully added addresses to customer"

---

## Test Case 5: Add Both Addresses

**Objective:** Verify that both shipping and billing addresses can be added simultaneously.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order
3. Click on "3DCart Order Information" tab
4. Enter a valid NetSuite Customer ID
5. Check "Add shipping address to customer profile"
6. Check "Add billing address to customer profile"
7. Click "Sync to NetSuite" button
8. Confirm the action

**Expected Result:**
- Confirmation dialog shows:
  - "Customer ID: {id}"
  - "Add Shipping Address: Yes"
  - "Add Billing Address: Yes"
- Order processes successfully
- Both addresses are added to customer in NetSuite

**Verification:**
- Check NetSuite customer record: Two new addresses should appear
  - "Shipping Address (3DCart Order #{OrderID})"
  - "Billing Address (3DCart Order #{OrderID})"
- Check logs: Should show "total_addresses: 2"

---

## Test Case 6: Backward Compatibility (No Customer ID)

**Objective:** Verify that existing functionality still works when no customer ID is provided.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order
3. Click on "3DCart Order Information" tab
4. Leave "Customer NetSuite ID" field empty
5. Leave both address checkboxes unchecked
6. Click "Sync to NetSuite" button
7. Confirm the action

**Expected Result:**
- Confirmation dialog does NOT show customer ID information
- Order processes using existing automatic customer finding/creation logic
- Order processes successfully
- Customer is found or created automatically

**Verification:**
- Check logs: Should show normal customer finding/creation process
- Order should be synced successfully

---

## Test Case 7: UI Visibility Rules

**Objective:** Verify that NetSuite Customer Options section appears/disappears correctly.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order
3. Click on "3DCart Order Information" tab
4. Verify "NetSuite Customer Options" section is visible
5. Sync the order to NetSuite
6. Refresh the page
7. Select the same (now synced) order
8. Click on "3DCart Order Information" tab

**Expected Result:**
- Before sync: "NetSuite Customer Options" section is visible (light blue background)
- After sync: "NetSuite Customer Options" section is NOT visible
- Only synced order information is shown

**Verification:**
- Section should only appear when `canEdit = true` (order not synced)

---

## Test Case 8: Address Addition Failure (Non-Blocking)

**Objective:** Verify that address addition failures don't prevent order creation.

**Steps:**
1. Open Order Status Manager
2. Select an unsynced order with incomplete address data
3. Enter a valid NetSuite Customer ID
4. Check both address checkboxes
5. Click "Sync to NetSuite" button
6. Confirm the action

**Expected Result:**
- Order still processes successfully even if address addition fails
- Warning logged about address addition failure
- Order is created and linked to customer

**Verification:**
- Check logs: May show warnings about address addition
- Order should still be synced successfully
- Customer should have the order linked

---

## Test Case 9: Email Notifications

**Objective:** Verify that email notifications are sent correctly.

**Steps:**
1. Configure email settings in `config/config.php`
2. Process an order with customer ID (Test Case 1)
3. Check email inbox

**Expected Result:**
- Success email received with:
  - Order ID
  - Customer ID used
  - NetSuite sales order ID
  - Address options selected

**Verification:**
- Check email inbox for notification
- Email should contain all relevant information

---

## Test Case 10: Error Handling and Logging

**Objective:** Verify comprehensive error logging.

**Steps:**
1. Perform Test Cases 1-5
2. Check log file: `logs/app-{date}.log`

**Expected Result:**
Logs should contain:
- Customer validation attempts
- Customer existence confirmation
- Address addition operations
- Order processing steps
- API call durations
- Success/failure messages

**Verification:**
- Search logs for:
  - "Validating customer existence in NetSuite"
  - "Customer exists in NetSuite"
  - "Adding addresses to NetSuite customer"
  - "Successfully added addresses to customer"
  - "Processing order with custom customer ID"

---

## Performance Testing

### Test Case 11: Response Time

**Objective:** Verify acceptable response times.

**Steps:**
1. Process an order with customer ID and both addresses
2. Measure time from clicking "Sync to NetSuite" to success message

**Expected Result:**
- Total time: < 10 seconds (depending on NetSuite API response)
- Customer validation: < 2 seconds
- Address addition: < 3 seconds
- Order creation: < 5 seconds

**Verification:**
- Check logs for API call durations
- User should not experience significant delays

---

## Security Testing

### Test Case 12: Input Validation

**Objective:** Verify that inputs are properly validated.

**Steps:**
1. Try entering non-numeric customer ID (e.g., "ABC123")
2. Try entering SQL injection attempts
3. Try entering XSS attempts

**Expected Result:**
- Non-numeric IDs: Handled gracefully
- SQL injection: Prevented by parameterized queries
- XSS: Prevented by proper escaping

**Verification:**
- No errors or security vulnerabilities
- Invalid inputs handled safely

---

## Troubleshooting Guide

### Issue: "Customer ID does not exist"

**Possible Causes:**
- Customer ID is incorrect
- Customer was deleted from NetSuite
- NetSuite API connection issue

**Solutions:**
1. Verify customer ID in NetSuite
2. Create customer if needed
3. Check NetSuite API credentials
4. Check logs for detailed error

### Issue: Addresses not appearing in NetSuite

**Possible Causes:**
- Address data incomplete in 3DCart order
- NetSuite API permissions issue
- Address validation failed

**Solutions:**
1. Check 3DCart order for complete address data
2. Verify NetSuite API permissions for customer updates
3. Check logs for address addition errors
4. Verify address fields meet NetSuite requirements

### Issue: Order not syncing

**Possible Causes:**
- NetSuite API connection issue
- Customer validation failed
- Order data incomplete

**Solutions:**
1. Check NetSuite API credentials
2. Verify customer ID is valid
3. Check order data completeness
4. Review logs for specific error messages

---

## Log Analysis

### Key Log Messages to Look For:

**Success Indicators:**
```
"Customer exists in NetSuite"
"Successfully added addresses to customer"
"Sales order created successfully"
"Order synced to NetSuite successfully"
```

**Warning Indicators:**
```
"Customer does not exist in NetSuite"
"No valid addresses found to add to customer"
"Address addition failed but continuing with order"
```

**Error Indicators:**
```
"Failed to validate customer existence"
"Failed to add addresses to customer"
"Failed to create sales order"
"Failed to process order"
```

---

## Regression Testing Checklist

After any code changes, verify:

- [ ] Existing orders can still be synced without customer ID
- [ ] Customer finding/creation logic still works
- [ ] Email notifications still sent
- [ ] 3DCart status updates still work
- [ ] UI displays correctly for synced/unsynced orders
- [ ] All error messages are user-friendly
- [ ] Logging captures all operations
- [ ] No performance degradation

---

## Test Data Requirements

### Valid Test Customer IDs:
- Obtain from NetSuite sandbox/production
- Ensure customers are "person" type (isPerson = true)
- Verify customers are active

### Test Orders:
- At least 3 unsynced orders
- Orders with complete address data
- Orders with incomplete address data
- Orders with various payment methods

### Test Environment:
- Access to NetSuite sandbox (recommended)
- Access to 3DCart test store
- Email testing capability
- Log file access

---

## Success Criteria

All tests pass when:

✅ Valid customer IDs are accepted and orders process correctly
✅ Invalid customer IDs are rejected with clear error messages
✅ Shipping addresses are added when requested
✅ Billing addresses are added when requested
✅ Both addresses can be added simultaneously
✅ Backward compatibility maintained (no customer ID works)
✅ UI shows/hides correctly based on order status
✅ Email notifications sent for success/failure
✅ Comprehensive logging captures all operations
✅ No security vulnerabilities
✅ Acceptable performance (< 10 seconds total)
✅ Error handling prevents data corruption

---

## Reporting Issues

When reporting issues, include:

1. **Test case number** that failed
2. **Steps to reproduce** the issue
3. **Expected result** vs **actual result**
4. **Screenshots** of error messages
5. **Log entries** related to the issue
6. **Order ID** and **Customer ID** used
7. **Timestamp** of the test
8. **Environment** (sandbox/production)

---

## Contact

For questions or issues during testing:
- Check logs first: `logs/app-{date}.log`
- Review this testing guide
- Contact development team with detailed information

---

**Last Updated:** 2024
**Version:** 1.0.0
**Status:** Ready for Testing ✅