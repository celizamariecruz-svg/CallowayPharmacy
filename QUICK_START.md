# 🚀 QUICK START GUIDE
**Calloway Pharmacy IMS - Get Started in 5 Minutes**

---

## ✅ Step 1: Verify Database Setup

The database has already been migrated! Verify by checking:
```
✓ 15 tables created
✓ 2 database views created
✓ Sample data loaded (roles, permissions, categories, suppliers)
✓ Default admin user created
```

---

## 🔐 Step 2: Login to System

**Open your browser and go to:**
```
http://localhost/CallowayPharmacyIMS/login.html
```

**Use these credentials:**
```
Username: admin
Password: admin123
```

**What happens:**
- ✅ Your credentials are verified against the database
- ✅ Session is created with your user info
- ✅ Activity is logged for audit trail
- ✅ You'll be redirected to index.html

---

## 🧪 Step 3: Test the System

**Open the Test Dashboard:**
```
http://localhost/CallowayPharmacyIMS/test_system.html
```

**Run these tests:**
1. Click "Test Login" - Verifies authentication works
2. Click "Get Products" - Tests inventory API
3. Click "Low Stock Alert" - Tests database views
4. Click "Get Categories" - Tests category retrieval

---

## 💳 Step 4: Use the POS System

**Open POS:**
```
http://localhost/CallowayPharmacyIMS/pos.php
```

**Features now active:**
- ✅ Authentication required (you must be logged in)
- ✅ Permission check (user needs 'pos.access' permission)
- ✅ Stock validation (prevents overselling)
- ✅ User tracking (cashier name recorded)

**Try making a sale:**
1. Add products to cart
2. Click CHECKOUT
3. Process payment
4. Stock is automatically deducted
5. Transaction logged to database

---

## 📦 Step 5: Use Inventory API

**Test with browser console (F12):**

```javascript
// Get all products
fetch('inventory_api.php?action=get_products')
  .then(r => r.json())
  .then(data => console.log(data));

// Get low stock items
fetch('inventory_api.php?action=low_stock_alert')
  .then(r => r.json())
  .then(data => console.log(data));

// Get expiring products
fetch('inventory_api.php?action=expiring_products')
  .then(r => r.json())
  .then(data => console.log(data));
```

---

## 🔑 Understanding Permissions

**Your admin account has ALL permissions. Create test users with different roles:**

**To create a new user, use this API call:**
```javascript
// Note: This would need a user management UI
// For now, add users directly to database or via Auth::registerUser()
```

**Role Capabilities:**

| Role | Can Do |
|------|--------|
| **Admin** | Everything |
| **Cashier** | POS, view products, view sales |
| **Inventory Staff** | Manage products, suppliers, stock |
| **Manager** | View reports, settings |

---

## 📊 Check Activity Logs

**All actions are logged! Query the database:**

```sql
SELECT 
    al.action,
    al.module,
    al.details,
    u.username,
    al.created_at
FROM activity_logs al
LEFT JOIN users u ON al.user_id = u.user_id
ORDER BY al.created_at DESC
LIMIT 20;
```

**You'll see:**
- Login attempts
- Product views
- Sales completed
- Stock movements
- User actions

---

## 🛠️ Common Operations

### Add New Product via API
```javascript
fetch('inventory_api.php?action=add_product', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        sku: 'MED-001',
        barcode: '1234567890',
        name: 'Test Medicine',
        selling_price: 50.00,
        cost_price: 30.00,
        stock_quantity: 100,
        expiry_date: '2025-12-31',
        category_id: 1,
        reorder_level: 10
    })
})
.then(r => r.json())
.then(data => console.log(data));
```

### Adjust Stock
```javascript
fetch('inventory_api.php?action=stock_movement', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        product_id: 1,
        movement_type: 'IN',
        quantity: 50,
        notes: 'Restocking from supplier'
    })
})
.then(r => r.json())
.then(data => console.log(data));
```

### View Stock Movements
```sql
SELECT 
    sm.*,
    p.name as product_name,
    u.username as created_by_user
FROM stock_movements sm
JOIN products p ON sm.product_id = p.product_id
LEFT JOIN users u ON sm.created_by = u.user_id
ORDER BY sm.created_at DESC
LIMIT 20;
```

---

## 🚨 Troubleshooting

### "Authentication required"
- ✅ Login first at `login.html`
- ✅ Check if session is active

### "Access Denied: Insufficient permissions"
- ✅ Check your role has the required permission
- ✅ Admin role has ALL permissions

### "Invalid security token"
- ✅ Refresh the page to get new CSRF token
- ✅ Don't reuse old tokens

### "Insufficient stock"
- ✅ This is working correctly!
- ✅ Check product's stock_quantity in database
- ✅ Add stock via inventory API

---

## 📁 Key Files Reference

| File | Purpose |
|------|---------|
| `Auth.php` | Authentication & authorization engine |
| `login_handler.php` | Process login requests |
| `logout.php` | Logout handler |
| `inventory_api.php` | Complete inventory REST API |
| `process_sale.php` | Enhanced sales processing |
| `pos.php` | POS frontend (now protected) |
| `test_system.html` | Interactive testing dashboard |

---

## 🎯 What to Test

**Priority 1 - Authentication:**
- ✅ Login with admin credentials
- ✅ Try wrong password (should fail)
- ✅ Try accessing POS without login (should redirect)
- ✅ Logout and verify session cleared

**Priority 2 - Inventory:**
- ✅ Get products list
- ✅ View low stock items
- ✅ View expiring products
- ✅ Get categories and suppliers

**Priority 3 - POS:**
- ✅ Access POS (must be logged in)
- ✅ Add items to cart
- ✅ Complete a sale
- ✅ Verify stock deducted
- ✅ Check sale recorded in database

**Priority 4 - Security:**
- ✅ Try API without login (should block)
- ✅ Verify CSRF token required
- ✅ Check activity logs populated
- ✅ Test session timeout (after 1 hour)

---

## 📞 Next Steps

### Today:
1. Login and explore the system
2. Run all tests in test_system.html
3. Make a test sale in POS
4. Check database for new records

### This Week:
1. Create additional user accounts
2. Test different role permissions
3. Add more products with SKU/barcode
4. Process multiple sales transactions

### This Month:
1. Implement receipt printing (Module 4)
2. Add supplier management UI
3. Create reporting dashboard
4. Enhance UI/UX design

---

## 💡 Pro Tips

**Tip 1:** Use browser DevTools (F12) to see API responses
**Tip 2:** Check activity_logs table to track all actions
**Tip 3:** Query stock_movements to see inventory history
**Tip 4:** Use low_stock_products view for quick alerts
**Tip 5:** Test concurrent transactions to verify locking works

---

## 🎉 You're Ready!

**Core modules are 100% functional:**
- ✅ Authentication working
- ✅ Authorization enforced
- ✅ Inventory API operational
- ✅ POS system enhanced
- ✅ Stock tracking active
- ✅ Audit trail logging

**Start using the system now!**

---

## 📚 More Resources

- **Full Documentation:** README_IMPLEMENTATION.md
- **Implementation Guide:** Implementation_Guide.html
- **Test Dashboard:** test_system.html
- **Thesis Documentation:** Chapter3_Part2.html

---

**Questions? Check the test dashboard for live examples!**

🏥 **Calloway Pharmacy IMS - Ready for Use!**
