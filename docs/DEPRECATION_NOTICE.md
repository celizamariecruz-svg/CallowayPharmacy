# 📦 Purchase Orders Feature - Deprecation Notice

**Date:** February 15, 2026  
**Status:** ⚠️ DEPRECATED - PICKUP-ONLY BUSINESS MODEL

---

## Business Model Clarification

**Calloway Pharmacy operates as PICKUP-ONLY:**
- No delivery service
- No shipment tracking needed
- Customers place online orders and pick up at physical store

---

## Deprecated Features

The following features/files are **NO LONGER NEEDED** and can be safely archived or removed:

### Files to Remove/Archive:
1. ✅ `purchase_orders.php` - Purchase order management UI
2. ✅ `purchase_order_api.php` - PO API endpoints
3. ✅ `install_features.php` - PO table creation script

### Database Tables (Optional Removal):
- `purchase_orders` - If you manually create inventory restocking records
- `purchase_order_items` - Related items table

**Note:** Only drop these tables if you are certain you don't need purchase order tracking for internal inventory management.

### UI References Removed:
- ✅ Sidebar navigation link to Purchase Orders (header-component.php)
- ✅ Home dashboard card for Purchase Orders (index.php)

---

## Updated System Design Priorities

### ❌ REMOVED from Priority List:
- ~~Automatic purchase order creation~~
- ~~Delivery tracking post-shipment~~
- ~~Partial shipment support~~

### ✅ UPDATED Priorities (Pickup-Only Model):

**CRITICAL:**
1. **Expiry Enforcement (FIFO)** - Prevent expired medication sales
2. **Payment Verification** - Block pickup until payment confirmed
3. **Return/Refund Workflow** - Handle returned items properly
4. **Rx Verification** - Pharmacist approval for prescription drugs

**HIGH:**
1. **Low Stock Alerts** - Email notifications when stock < reorder_level
2. **Batch/Lot Tracking** - Trace products back to supplier batch
3. **Pickup Confirmation** - Track when customer actually picks up order
4. **Cancellation Policy** - Define cancellation window (e.g., 15 min)
5. **Unclaimed Order Handling** - Auto-cancel orders not picked up after 48 hours

---

## Manual Inventory Restocking Process

Since you don't use automated purchase orders, here's the recommended manual workflow:

### Step 1: Monitor Stock Levels
- Use **Expiry Monitoring** page to track low stock
- Enable email alerts for products below reorder_level

### Step 2: Contact Suppliers
- Use **Supplier Management** page to view supplier contacts
- Manually call/email suppliers to place orders

### Step 3: Receive Stock
- When stock arrives, use **Inventory Management** to add new products
- Update quantities manually
- Record batch/lot numbers (if implementing tracking)

### Step 4: Update Costs
- Update `unit_cost` and `selling_price` as needed
- System will track new stock_movements automatically

---

## Optional: Keep PO Tables for History

If you want to keep internal records of what you ordered from suppliers (for accounting/audit purposes), you can keep the PO tables but just don't use the automated system.

**Manual PO Entry:**
1. Access database directly or create simple "Purchase Record" UI
2. Enter: Date, Supplier, Items, Quantities, Total Cost
3. Use for accounting reconciliation

---

## Files Still Active (Supplier Management)

✅ **Keep these supplier-related files:**
- `supplier_management.php` - Active supplier contact management
- Supplier contacts still needed for manual ordering

---

## Next Steps

1. **Test the system** without PO links (navigation removed)
2. **Optional:** Drop PO tables if you don't need historical records:
   ```sql
   DROP TABLE IF EXISTS purchase_order_items;
   DROP TABLE IF EXISTS purchase_orders;
   ```
3. **Focus on pickup-only priorities** (see COMPLETE_REMEDIATION_REPORT.md)

---

## Contact for Questions

If you need to restore purchase order functionality in the future (e.g., adding delivery service), the code is preserved in backups and can be re-enabled.
