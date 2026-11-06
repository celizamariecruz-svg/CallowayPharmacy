# 🚀 QUICK START TESTING GUIDE

**All critical fixes have been implemented!** Here's how to test them.

---

## ✅ PRE-FLIGHT CHECKLIST

### 1. Database Migration (✅ Already Run)
```bash
✓ php migrations/migrate_006_critical_fixes.php
```

**Verify tables exist:**
```sql
SHOW TABLES LIKE 'returns';
SHOW TABLES LIKE 'return_items';
SHOW TABLES LIKE 'rx_approval_log';
DESCRIBE products;  -- Check for is_prescription, batch_number columns
DESCRIBE online_orders;  -- Check for requires_rx_approval, picked_up_at columns
```

---

## 🧪 TEST SCENARIOS

### Scenario 1: Test Expired Product Blocking

**Step 1:** Mark a test product as expired
```sql
UPDATE products 
SET expiry_date = '2024-01-01' 
WHERE product_id = 1 
LIMIT 1;
```

**Step 2:** Try to order it from Online Ordering
1. Go to `onlineordering.php`
2. Add the expired product to cart
3. Click "Place Order"

**Expected Result:** ❌ Order should be blocked with error message:
```
"EXPIRED: Cannot sell expired product [name] (expired X days ago)"
```

**Clean Up:**
```sql
UPDATE products 
SET expiry_date = DATE_ADD(NOW(), INTERVAL 6 MONTH) 
WHERE product_id = 1;
```

---

### Scenario 2: Test Prescription (Rx) Verification

**Step 1:** Mark a product as prescription medication
```sql
UPDATE products 
SET is_prescription = 1 
WHERE name LIKE '%aspirin%' 
LIMIT 1;
```

**Step 2:** Order the Rx product  
1. Go to `onlineordering.php`
2. Add the Rx product to cart
3. Complete checkout

**Expected Results:**
✅ Order placed successfully  
✅ Warning modal appears: "⚕️ Prescription Medication Notice"  
✅ Modal lists prescription requirements

**Step 3:** Check database
```sql
SELECT order_id, requires_rx_approval 
FROM online_orders 
ORDER BY order_id DESC 
LIMIT 1;
```

**Expected:** `requires_rx_approval = 1`

---

### Scenario 3: Test Pharmacist Approval

**Step 1:** Access pharmacist interface
```
http://localhost/CALLOWAYBACKUP1/pharmacist_approval.php
```

**Expected:**
✅ Shows pending Rx orders  
✅ Displays Rx product names  
✅ Has Approve/Reject buttons

**Step 2:** Approve an order
1. Enter notes: "Prescription verified - Valid until 2026-06-01"
2. Click "Approve"

**Expected:**
✅ Success message: "Prescription order approved"  
✅ Order removed from pending list

**Step 3:** Verify in database
```sql
SELECT 
    o.order_id,
    o.pharmacist_approved_by,
    o.pharmacist_approved_at,
    o.rx_notes,
    l.action,
    l.created_at
FROM online_orders o
LEFT JOIN rx_approval_log l ON o.order_id = l.order_id
WHERE o.requires_rx_approval = 1
ORDER BY o.order_id DESC
LIMIT 5;
```

**Expected:**
- `pharmacist_approved_by` = your user_id
- `pharmacist_approved_at` = current timestamp
- `rx_notes` = your entered notes
- Entry in `rx_approval_log` with `action = 'Approved'`

---

### Scenario 4: Test Expiry Warnings (Near Expiry)

**Step 1:** Set product to expire soon
```sql
UPDATE products 
SET expiry_date = DATE_ADD(NOW(), INTERVAL 15 DAY) 
WHERE product_id = 2 
LIMIT 1;
```

**Step 2:** Try to order it

**Expected Result:** ✅ Order allowed BUT warning in console/logs:
```
"NOTICE: Product [name] expires in 15 days"
```

---

### Scenario 5: Test View Expiring Products

**Query the FIFO view:**
```sql
SELECT * FROM v_expiring_products 
ORDER BY days_until_expiry ASC;
```

**Expected Columns:**
- `product_id`
- `name`
- `batch_number`
- `expiry_date`
- `stock_quantity`
- `days_until_expiry`
- `expiry_status` (EXPIRED, CRITICAL, WARNING, OK)

---

## 🎯 QUICK VERIFICATION COMMANDS

### Check All New Features

```sql
-- 1. Products with Rx flag
SELECT product_id, name, is_prescription, batch_number 
FROM products 
WHERE is_prescription = 1;

-- 2. Orders requiring approval
SELECT order_id, customer_name, requires_rx_approval, 
       pharmacist_approved_by, pharmacist_approved_at
FROM online_orders 
WHERE requires_rx_approval = 1;

-- 3. Rx approval audit log
SELECT * FROM rx_approval_log 
ORDER BY created_at DESC 
LIMIT 10;

-- 4. Expiring products summary
SELECT expiry_status, COUNT(*) as count, SUM(stock_quantity) as units
FROM v_expiring_products
GROUP BY expiry_status;

-- 5. Returns (should be empty for now)
SELECT * FROM returns;

-- 6. Pickup tracking
SELECT order_id, customer_name, picked_up_at, picked_up_by
FROM online_orders
WHERE picked_up_at IS NOT NULL;
```

---

## 🐛 TROUBLESHOOTING

### Problem: Migration script errors

**Solution:** Check if migration already ran
```sql
SHOW COLUMNS FROM products LIKE 'is_prescription';
```
If it returns a row, migration already succeeded. Safe to ignore error.

---

### Problem: Pharmacist approval page shows "Access denied"

**Solution:** Check your user role
```sql
SELECT u.user_id, u.username, r.role_name 
FROM users u 
INNER JOIN roles r ON u.role_id = r.role_id 
WHERE u.user_id = YOUR_USER_ID;
```

If role is not 'Pharmacist', 'Admin', or 'Manager', update it:
```sql
-- Get role_id for Pharmacist role
SELECT role_id FROM roles WHERE role_name = 'Pharmacist';

-- Update your user
UPDATE users SET role_id = [pharmacist_role_id] WHERE user_id = YOUR_USER_ID;
```

---

### Problem: Expiry enforcement not working

**Check if ExpiryEnforcement.php is loaded:**
```php
// In order_handler.php around line 103
require_once 'ExpiryEnforcement.php';  // Should be present
```

**Check error logs:**
```bash
tail -f C:\xampp\apache\logs\error.log
```

---

### Problem: Rx warning modal doesn't appear

**Check browser console for JavaScript errors:**
1. Open browser DevTools (F12)
2. Go to Console tab
3. Place order with Rx product
4. Look for showRxWarningModal errors

**Verify response contains rx_warning:**
Check Network tab → order_handler.php response:
```json
{
    "success": true,
    "order_id": 123,
    "requires_prescription": true,
    "rx_warning": {
        "title": "⚕️ Prescription Medication Notice",
        "message": "...",
        "requirements": [...]
    },
    "rx_products": ["Aspirin 500mg", ...]
}
```

---

## 📋 DEMO FOR PANEL

### Recommended Demo Flow:

1. **Show Expiry Enforcement**
   - "Here's a product that expired last month"
   - Try to order → System blocks it
   - "This prevents legal liability from selling expired medications"

2. **Show Rx Verification**
   - "This is a prescription medication like antibiotics"
   - Order it → Warning modal appears
   - "Customer is warned to bring prescription and ID"

3. **Show Pharmacist Interface**
   - Log in as pharmacist
   - Go to `pharmacist_approval.php`
   - "Orders with Rx products appear here for verification"
   - Approve order → Show audit log entry

4. **Show Database Tracking**
   - Run queries from "Quick Verification Commands" section
   - "Every Rx approval is logged for regulatory compliance"
   - Show `rx_approval_log` table with pharmacist_id and timestamp

5. **Show Expiry View**
   - `SELECT * FROM v_expiring_products`
   - "We can proactively manage inventory to reduce waste"

---

## ✅ SUCCESS CRITERIA

You're ready when:

- [ ] Expired products are blocked from orders
- [ ] Rx products trigger warning modal
- [ ] Orders with Rx are flagged in database
- [ ] Pharmacist approval interface works
- [ ] Approval/rejection creates audit log entries
- [ ] View shows expiring products correctly
- [ ] All new database columns exist
- [ ] No PHP errors in Apache error log

---

## 🎉 YOU'RE READY!

All critical compliance features are now implemented:
- ✅ Expiry enforcement (legal liability protection)
- ✅ Rx verification (regulatory compliance)
- ✅ Pharmacist approval (professional oversight)
- ✅ Audit trails (regulatory requirements)
- ✅ Customer education (prescription requirements)

**Confidence Level:** 🟢 **HIGH**

---

**Need Help?**
- Check `IMPLEMENTATION_SUMMARY.md` for feature details
- Check `PANEL_DEFENSE_GUIDE.md` for Q&A prep
- Check `SYSTEM_DESIGN_ANALYSIS.md` for remaining gaps

**Good luck with your thesis defense! 🎓**
