# 🧪 Testing Guide: Rx Warning Modal & POS Pickup Payment

## Overview
This guide covers testing two critical features:
1. **Rx Warning Modal** - Alerts customers when ordering prescription medications
2. **POS Pickup Payment Integration** - Process payments for online pickup orders through POS

---

## ✅ Feature 1: Rx Warning Modal Testing

### Purpose
When customers order prescription medications online, they must be informed to bring their valid prescription for pickup.

### Test Steps

#### 1. Mark a Product as Prescription
```sql
-- Run in MySQL/phpMyAdmin
UPDATE products 
SET is_prescription = 1 
WHERE product_name LIKE '%Aspirin%' 
OR product_name LIKE '%Amoxicillin%'
LIMIT 1;
```

#### 2. Place an Order with the Rx Product
1. Open browser and go to the online ordering page
2. **Important:** Open Browser Console (F12 → Console tab)
3. Add the prescription product to cart
4. Fill in customer details and place order
5. **Watch the console** for debug output:
   ```
   Order data: {
     requires_prescription: true,
     has_rx_warning: true,
     has_rx_products: true,
     rx_warning: {...}
   }
   Showing Rx Warning Modal
   ```

#### 3. Expected Results
✓ Console shows "Showing Rx Warning Modal"
✓ Blue modal appears with:
  - Title: "⚕️ Prescription Medication Notice"
  - Warning message about bringing prescription
  - List of Rx products in the order
  - "What You Need to Bring" checklist
  - "I Understand" button

#### 4. If Modal Doesn't Show
Check console for:
- `requires_prescription: false` → Product not marked as Rx
- `has_rx_warning: false` → Server not sending warning
- JavaScript errors → Fix any syntax/runtime errors

#### 5. Verify Backend
Check that [order_handler.php](order_handler.php#L103-L127) integrated RxEnforcement:
- Line 103-127: RxEnforcement initialization
- Line 640-660: Rx warning added to response

---

## ✅ Feature 2: POS Pickup Payment Integration

### Purpose
When customers arrive to pick up their online order, staff can load the order into POS and process payment with proper change calculation.

### Test Steps

#### 1. Create a Test Online Order
1. Go to online ordering page
2. Add items to cart (avoid Rx items for faster testing)
3. Select "Cash on Pickup" payment method
4. Place order → Note the order reference (e.g., ONL-000042)

#### 2. Simulate Order Ready for Pickup
1. Login to POS
2. Go to **Online Orders** tab
3. Find your test order (should be in "Pending" status)
4. Click **Confirm** → **Start Preparing** → **Mark Ready**
5. Order should now be in "Ready" status

#### 3. Process Pickup Payment
1. In "Ready" status, you should see TWO buttons:
   - **🧾 Process Pickup Payment** (new feature - primary button)
   - **Complete (No Payment)** (gray button - old method)
2. Click **"Process Pickup Payment"**
3. Confirm dialog: "Load [Customer Name]'s order (ONL-XXXXXX) into POS cart for payment?"
4. Click **"Yes, Load to Cart"**

#### 4. Verify Cart Loading
✓ POS automatically switches to "POS" tab
✓ Cart populated with order items
✓ Quantities match the online order
✓ Prices match the online order
✓ Toast message shows: "Order loaded! Total: ₱XXX - Customer: [Name]"
✓ Payment panel opens automatically

#### 5. Complete Payment
1. Enter amount tendered (e.g., ₱500 for ₱234 order)
2. Change should calculate automatically (₱266 in example)
3. Select payment method (Cash/Card/GCash)
4. Click **"Complete Sale"**

#### 6. Verify Pickup Marking
✓ Toast shows: "✅ Order marked as picked up!"
✓ Receipt modal appears with sale details
✓ Order disappears from "Ready" tab
✓ Order appears in "Done" tab (if implemented)

#### 7. Database Verification
```sql
-- Check that order was properly marked as picked up
SELECT 
    order_id,
    order_ref,
    customer_name,
    total_amount,
    status,
    picked_up_at,
    picked_up_by,
    pos_sale_id
FROM online_orders
WHERE order_id = [YOUR_ORDER_ID];
```

**Expected:**
- `status` = 'Completed'
- `picked_up_at` = timestamp of payment completion
- `picked_up_by` = user_id of staff member
- `pos_sale_id` = sale_id from sales table

```sql
-- Verify POS sale was created
SELECT 
    sale_id,
    sale_reference,
    total,
    payment_method,
    cashier,
    created_at
FROM sales
WHERE sale_id = [pos_sale_id];
```

**Expected:**
- Sale record exists
- `total` matches online order total
- `sale_reference` starts with "ONL-" or "TX-"
- `cashier` = staff username

---

## 🔄 Complete Workflow Scenarios

### Scenario A: Rx Order with POS Payment
1. Customer orders Amoxicillin (Rx product) online
2. **Rx Warning Modal** appears → Customer clicks "I Understand"
3. Order goes to **Pharmacist Approval** queue
4. Pharmacist reviews prescription → Approves order
5. Staff prepares order → Marks as "Ready"
6. Customer arrives at counter
7. Staff clicks **"Process Pickup Payment"**
8. Items load to POS cart
9. Staff processes payment (₱500 tendered for ₱350 order = ₱150 change)
10. System marks order as picked up by current user
11. Receipt prints

### Scenario B: Non-Rx Order with Quick Pickup
1. Customer orders vitamins (non-Rx) online
2. No Rx modal (correct behavior)
3. Staff confirms → prepares → marks ready
4. Customer arrives
5. If customer already paid online: Click **"Complete (No Payment)"**
6. If customer paying at counter: Click **"Process Pickup Payment"**
7. Complete payment through POS
8. Order marked as picked up

### Scenario C: Partial Rx Order
1. Customer orders 3 items: Aspirin (Rx), Vitamin C, Bandages
2. **Rx Warning Modal** shows with only "Aspirin" listed
3. "What You Need to Bring" checklist appears
4. Pharmacist approval required for entire order
5. After approval, normal pickup workflow

---

## 🐛 Troubleshooting

### Issue: Rx Modal Not Appearing

**Check 1: Product Configuration**
```sql
SELECT product_id, product_name, is_prescription 
FROM products 
WHERE is_prescription = 1;
```
If no results, no products are marked as Rx.

**Check 2: Browser Console**
Press F12 → Console tab → Look for:
- "Order data: {...}" log entry
- `requires_prescription: true` in the logged data
- "Showing Rx Warning Modal" message
- Any JavaScript errors (red text)

**Check 3: Server Response**
Open Network tab (F12 → Network)
→ Find "order_handler.php" request
→ Click → Response tab
→ Look for `"requires_prescription": true` and `"rx_warning": {...}`

**Check 4: Modal Function Exists**
Console → Type: `typeof showRxWarningModal`
→ Should return "function", not "undefined"

### Issue: POS Cart Not Loading

**Check 1: Order Status**
Only "Ready" orders show "Process Pickup Payment" button.
→ Confirm order, prepare, then mark ready.

**Check 2: Network Errors**
F12 → Network tab → Look for failed requests to:
- `online_order_api.php?action=get_order_details`
- `pos_api.php?action=get_product`

**Check 3: Console Errors**
Look for JavaScript errors like:
- "Cannot read property 'items' of undefined"
- "Fetch failed"
- "product.product_name is undefined"

**Fix:** Ensure products exist and have proper data.

### Issue: Order Not Marked as Picked Up

**Check 1: Session Authentication**
POS must have active user session.
→ Log out and log back in if needed.

**Check 2: Database Column**
```sql
SHOW COLUMNS FROM online_orders LIKE 'picked_up%';
```
Should show:
- `picked_up_at`
- `picked_up_by`
- `pos_sale_id`

If missing, run: `php migrations/migrate_007_pos_sale_link.php`

**Check 3: API Endpoint**
Check [online_order_api.php](online_order_api.php) has `case 'mark_picked_up'`.

---

## 📊 Success Metrics

After implementing and testing, verify:

✅ **Rx Modal**
- [ ] Appears for all orders with prescription items
- [ ] Shows complete product list
- [ ] Displays clear requirements
- [ ] Can be dismissed with "I Understand"
- [ ] Doesn't appear for non-Rx orders

✅ **POS Pickup Payment**
- [ ] "Process Pickup Payment" button appears for Ready orders
- [ ] Cart loads with correct items and quantities
- [ ] Prices match online order
- [ ] Payment calculates change correctly
- [ ] Order marked as picked up after payment
- [ ] `picked_up_by` records staff member
- [ ] `pos_sale_id` links to POS sale
- [ ] Receipt generation works

✅ **Integration**
- [ ] Rx orders require pharmacist approval before pickup
- [ ] Non-Rx orders can be immediately processed
- [ ] Staff can still use "Mark Complete (No Payment)" for pre-paid orders
- [ ] Notification updates after pickup
- [ ] Online orders panel refreshes correctly

---

## 🎯 Panel Defense Points

When defending to thesis panel:

**Q: How do customers know to bring their prescription?**
→ "When ordering prescription medications, a prominent modal appears **immediately after order placement** informing them to bring valid prescription, government ID, and be prepared for pharmacist verification."

**Q: How do you process payment for pickup orders?**
→ "When customers arrive, staff in the POS system clicks **'Process Pickup Payment'** which loads the order into the POS cart. Payment is processed through the regular POS workflow with **proper change calculation, receipt printing, and audit logging** of who released the medication."

**Q: How do you prevent expired medications from being sold?**
→ "The system has **ExpiryEnforcement** that blocks any product past expiry date from being added to cart (both online and POS). FIFO logic ensures oldest stock is sold first."

**Q: What if someone tries to buy prescription drugs without approval?**
→ "Orders with Rx items are **automatically flagged** and held in pending until a licensed pharmacist reviews and approves. The customer is shown the Rx warning modal during checkout, and **no pickup is allowed** until pharmacist approval is recorded in the system."

---

## 🔍 Code References

| Feature | File | Lines |
|---------|------|-------|
| Rx Modal Function | [onlineordering.php](onlineordering.php#L4186) | 4186-4250 |
| Rx Modal Trigger | [onlineordering.php](onlineordering.php#L4025-L4029) | 4025-4029 |
| Rx Server Check | [order_handler.php](order_handler.php#L103-L127) | 103-127 |
| POS Pickup Function | [pos.php](pos.php#L2498-L2570) | 2498-2570 |
| POS Payment Complete | [pos.php](pos.php#L2000-L2050) | 2000-2050 |
| Mark Picked Up API | [online_order_api.php](online_order_api.php#L340-L420) | 340-420 |
| Migration 007 | [migrate_007_pos_sale_link.php](migrations/migrate_007_pos_sale_link.php) | Full file |

---

**Last Updated:** February 13, 2026
**Features Verified:** Rx Warning Modal ✅ | POS Pickup Payment ✅
