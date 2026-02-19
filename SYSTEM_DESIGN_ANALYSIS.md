

📊 SYSTEM DESIGN ANALYSIS REPORT
=======================================================================

FINDINGS SUMMARY
----------------------------------------------------------------------
🔴 CRITICAL: 4
🟠 HIGH: 9
🟡 MEDIUM: 15
🟢 LOW: 3
TOTAL ISSUES: 31


🔴 CRITICAL SEVERITY ISSUES
----------------------------------------------------------------------

1. Incomplete Expiry Management
   Area: Inventory Management
   Type: REQUIRED FEATURE
   Problem: Expiry dates tracked but no enforcement of FIFO (First-In-First-Out) sales
   Impact:
     - Expired products sold to customers (legal liability)
     - Customer health risk
     - Regulatory non-compliance
     - Financial loss from expired stock
   Fix: Before processing sale: Check if product has expiry_date; if yes, ONLY allow sale of oldest batch first. If expiry < TODAY, BLOCK sale and mark for removal.
   Effort: HIGH (6-8 hours)

2. Payment Status Separate from Order Status
   Area: Order Workflow
   Type: REQUIRED FEATURE
   Problem: Order can be "Completed" with payment still "Pending"
   Impact:
     - Unpaid orders shipped to customers
     - Revenue recognition issues
     - Accounting reconciliation problems
     - Loss of unpaid inventory
   Fix: Add payment_status column (Unpaid, Pending, Completed, Failed). Block order completion until payment_status = Completed.
   Effort: HIGH (6-8 hours)

3. No Formalized Return/Refund System
   Area: Order Workflow
   Type: REQUIRED FEATURE
   Problem: Returned items have no formal workflow; refunds not tracked
   Impact:
     - Customer confusion on how to return
     - Unreturned items not restocked properly
     - Refunds not clearly documented
     - No return rate analytics
   Fix: Add returns module: Request Return → Receive Return → Inspect → Accept/Reject → Restock/Dispose → Issue Refund (with status at each step).
   Effort: HIGH (8-10 hours)

4. No Pharmacist Review Step for Prescription Medications
   Area: User Roles & Permissions
   Type: REQUIRED FEATURE
   Problem: Sales system doesn't differentiate between OTC and Rx drugs; no verification required
   Impact:
     - Controlled medications sold without pharmacist approval
     - Regulatory violation (pharmacy laws)
     - Potential abuse of prescription drugs
     - Legal liability for pharmacy
   Fix: Add is_prescription flag to products. Before sale: if is_prescription=true, flag order for pharmacist_review. Block completion until reviewed and approved by licensed pharmacist.
   Effort: HIGH (6-8 hours)

🟠 HIGH SEVERITY ISSUES
----------------------------------------------------------------------

1. Missing Automatic Reorder System
   Area: Inventory Management
   Type: REQUIRED FEATURE
   Problem: No automated purchase order creation when stock falls below reorder level
   Impact:
     - Stock runs out unexpectedly
     - Loss of customer sales
     - Manual reordering required (extra workload)
     - No alerts to managers
   Fix: Implement auto-PO system: When stock < reorder_level AND stock < min_required, automatically create PO for supplier
   Effort: MEDIUM (4-6 hours)

2. No Batch/Lot Number Tracking
   Area: Inventory Management
   Type: REQUIRED FEATURE
   Problem: Stock movements don't track which batch/lot a product came from
   Impact:
     - Cannot trace defective products back to batch
     - Recall management impossible
     - Supplier accountability unclear
     - Quality control gaps
   Fix: Add batch_number column to products and stock_movements. Track: purchase_batch → sale_batch for traceability.
   Effort: HIGH (8-10 hours)

3. No Delivery Tracking Post-Shipment
   Area: Order Workflow
   Type: REQUIRED FEATURE
   Problem: Online orders show "Ready" but no tracking of actual delivery
   Impact:
     - Cannot confirm customer received order
     - Dispute resolution impossible
     - Lost package invisibility
     - Customer dissatisfaction
   Fix: Add delivery_status column: Ready → OutForDelivery → Delivered → Signed (with timestamp). Update via delivery app or manual entry.
   Effort: MEDIUM (5-7 hours)

4. No Cancellation Window/Policy
   Area: Order Workflow
   Type: REQUIRED FEATURE
   Problem: Orders can be cancelled even after shipping has begun
   Impact:
     - Customer cancels after packaging starts
     - Wasted packing labor
     - Returned items not properly handled
     - No refund policy enforcement
   Fix: Define cancellation_window (e.g., 15 minutes after order). Block cancellation after this window. If cancelled post-shipment, mark as "Return in Progress".
   Effort: MEDIUM (4-6 hours)

5. Limited Audit Logging for Sensitive Operations
   Area: User Roles & Permissions
   Type: REQUIRED FEATURE
   Problem: Admin deletions, price changes, user modifications aren't comprehensively logged
   Impact:
     - Cannot detect fraudulent admin activity
     - Accountability gaps
     - Compliance violations
     - No forensic trail for disputes
   Fix: Enhance ActivityLogger: Log ALL admin operations with before/after values, user, timestamp, IP. Flag high-risk operations (delete, price change >10%).
   Effort: MEDIUM (5-7 hours)

6. No Automated Expiry Alerts
   Area: Reporting & Analytics
   Type: REQUIRED FEATURE
   Problem: Expiry monitoring exists but expiry_monitoring.php probably runs once, no scheduled alerts
   Impact:
     - Expired products sold to customers
     - Financial loss from write-offs
     - Regulatory violations
     - No proactive waste prevention
   Fix: Create backend task: Daily email to manager listing products expiring within 30/7/1 days. Alert on expired items immediately.
   Effort: MEDIUM (4-6 hours)

7. No Cash Flow Analysis
   Area: Reporting & Analytics
   Type: REQUIRED FEATURE
   Problem: Revenue tracking exists but no visibility into payment delays, receivables, or cash on hand
   Impact:
     - Poor financial planning
     - Cannot predict cash shortages
     - Collections management blind
     - No accounts receivable aging
   Fix: Add cash flow reports: Receivables aging, Payment trends, Delayed payments by customer, Days Sales Outstanding (DSO).
   Effort: MEDIUM (4-6 hours)

8. Missing Input Validation Rules
   Area: Data Integrity
   Type: REQUIRED FEATURE
   Problem: 91 instances of queries without input validation; no centralized validator
   Impact:
     - Invalid data corrupts database
     - Reports produce wrong results
     - System decisions based on bad data
     - SQL injection risks
   Fix: Create InputValidator class with rules: price>0, qty>=0, dates valid, emails valid, phone format valid. Apply to ALL inputs before DB insert.
   Effort: HIGH (8-10 hours, batch work)

9. Hard-coded Tax Rate (12%)
   Area: Business Rules
   Type: REQUIRED FEATURE
   Problem: Tax rate is 12% everywhere; no flexibility for different product categories or regions
   Impact:
     - Incorrect tax calculation for different items
     - Compliance violations if tax rates change
     - Cannot support multiple locations with different rates
     - Inflexible for e-commerce expansion
   Fix: Create tax_rules table: product_id, tax_category, tax_rate, effective_date. Use in sales calculation based on product tax category.
   Effort: MEDIUM (4-6 hours)

🟡 MEDIUM SEVERITY ISSUES
----------------------------------------------------------------------

1. No Physical Inventory Variance Tracking
   Area: Inventory Management
   Type: REQUIRED FEATURE
   Problem: No system to detect shrinkage, loss, theft, or counting errors
   Impact:
     - Inventory counts diverge from system
     - Undetected theft
     - Damaged stock not accounted for
     - Financial discrepancies
   Fix: Implement physical count module: Allow periodic stock counts, compare with system, flag variances > threshold, log adjustments with reason.
   Effort: MEDIUM (5-7 hours)

2. Minimal Supplier Tracking
   Area: Inventory Management
   Type: REQUIRED FEATURE
   Problem: Suppliers exist but no cost tracking, lead times, or preferred supplier logic
   Impact:
     - No visibility into procurement costs
     - Cannot optimize supplier selection
     - Lead time predictions unavailable
     - No supplier performance metrics
   Fix: Enhance supplier table: Add avg_cost_per_unit, lead_time_days, min_order_qty, preferred_status. Use in automatic PO selection.
   Effort: MEDIUM (4-6 hours)

3. Cannot Handle Partial Stock/Back-orders
   Area: Order Workflow
   Type: REQUIRED FEATURE
   Problem: If order has 5 items and only 3 in stock, order blocks until all 5 available
   Impact:
     - Lost sales to customers
     - Back-order queue manual management
     - Inventory inefficiency
     - Poor customer experience (long waits)
   Fix: Add order_item.fulfillment_status (Pending, Partial, Backordered, Fulfilled). Allow partial shipment of available items. Backorder remainder.
   Effort: HIGH (8-10 hours)

4. All Authenticated Users Can View All Products/Prices
   Area: User Roles & Permissions
   Type: REQUIRED FEATURE
   Problem: permissioncheck for "view products" was commented out globally
   Impact:
     - Competitors can view pricing strategy
     - Cost prices exposed to customers
     - Profit margin calculations visible
     - Supplier cost data transparency
   Fix: Re-enable product view permissions. Create roles: Customer (public prices only), Staff (cost prices), Manager (full data), Admin (everything).
   Effort: MEDIUM (4-6 hours)

5. No Distinct Customer Role - Only Staff Roles Exist
   Area: User Roles & Permissions
   Type: REQUIRED FEATURE
   Problem: System roles are: Admin, Manager, Staff, Pharmacist. No separate "Customer" role for online ordering
   Impact:
     - Customers can access staff dashboards if they guess URLs
     - No customer-specific features (order history, preferences)
     - Loyalty program can't be segmented by customer
     - Data security issue (access control)
   Fix: Add "Customer" role. Restrict customer view to: public product catalog, their order history, loyalty points, account settings only.
   Effort: MEDIUM (5-7 hours)

6. Limited Sales Analysis
   Area: Reporting & Analytics
   Type: REQUIRED FEATURE
   Problem: Basic top_products report exists but lacks: slow-moving inventory, dead stock, seasonal trends
   Impact:
     - Cannot optimize inventory mix
     - Slow-moving items consume shelf space
     - Markdowns not data-driven
     - Purchasing decisions are guesses
   Fix: Add reports: (1) Dead Stock (no sales in 90 days), (2) Slow Movers (< 5 units/month), (3) Seasonal Analysis, (4) Margin by Product.
   Effort: MEDIUM (5-7 hours)

7. No Supplier Analytics
   Area: Reporting & Analytics
   Type: REQUIRED FEATURE
   Problem: Suppliers tracked minimally; no performance metrics
   Impact:
     - Cannot evaluate supplier reliability
     - No cost comparison between suppliers
     - Delivery delays not tracked
     - Poor negotiating position
   Fix: Add supplier metrics: On-time delivery %, Quality issues %, Avg cost vs market, Lead time performance, Purchase frequency.
   Effort: MEDIUM (5-7 hours)

8. Missing Foreign Key Cascade Rules
   Area: Data Integrity
   Type: DESIGN ISSUE
   Problem: Foreign keys exist but some use RESTRICT when CASCADE would be appropriate
   Impact:
     - Orphaned records in database
     - Data cleanup difficult
     - Inconsistent state possible
     - Manual cleanup required
   Fix: Review FK constraints: Should products deletion CASCADE to sales/stock_movements? Should categories? Document cascade rules.
   Effort: LOW (2-3 hours)

9. No Historical Price Tracking
   Area: Data Integrity
   Type: DESIGN ISSUE
   Problem: Products table has current price_history but no changelog of when prices changed
   Impact:
     - Cannot calculate historical margins
     - Price analysis impossible
     - Audit trail for pricing decisions missing
     - Cannot detect price manipulation
   Fix: Add price_history table: product_id, old_price, new_price, changed_by, changed_at. Trigger on products.price UPDATE.
   Effort: LOW (3-4 hours)

10. Medicine Locator Feature Incomplete
   Area: Feature Completeness
   Type: REQUIRED FEATURE
   Problem: medicine-locator.php exists but integration with main product search unclear
   Impact:
     - Customers can't find medicines by active ingredient
     - No alternative medicine suggestions
     - Poor discoverability
   Fix: Complete integration: Add "Search by Active Ingredient" to main catalog. Link to medicine-locator functionality. Add drug interaction checker.
   Effort: MEDIUM (5-7 hours)

11. Basic Search Only (No Filters)
   Area: Feature Completeness
   Type: REQUIRED FEATURE
   Problem: Search doesn't filter by: category, price range, expiry date, brand
   Impact:
     - Customer experience poor
     - High bounce rate
     - Lost sales to competitors with better search
   Fix: Enhance product search: Add filters (category, brand, price, doseage), sorting (popularity, price, rating), faceted search.
   Effort: MEDIUM (5-7 hours, with frontend work)

12. No Discount Management System
   Area: Business Rules
   Type: REQUIRED FEATURE
   Problem: No support for: bulk discounts, promotions, coupon codes, seasonal sales
   Impact:
     - Cannot run marketing campaigns
     - Cannot offer bulk pricing
     - Lost competitive advantage
     - Revenue left on table
   Fix: Add discount engine: Discount rules (bulk qty, date range, customer type), coupon codes, automatic promo application, discount reporting.
   Effort: HIGH (8-10 hours)

13. No Minimum Order Requirements
   Area: Business Rules
   Type: REQUIRED FEATURE
   Problem: Customers can order single items with high delivery cost overhead
   Impact:
     - High fulfillment cost for small orders
     - Reduced profitability
     - Delivery vehicle inefficiency
   Fix: Add min_order_amount setting. If order < threshold, either block or upsell additional items.
   Effort: LOW (2-3 hours)

14. Stock Deducted at Order Placement, Not Confirmation
   Area: Workflow Consistency
   Type: DESIGN ISSUE
   Problem: Stock is reserved/deducted immediately when order is Pending, before payment confirmation
   Impact:
     - Customer cancels after stock deducted, stock released later
     - Inventory inaccuracy if cancellation logic fails
     - Stock appears out but order might not complete
   Fix: Consider changing to: Deduct stock only when order status = Confirmed (not Pending). Or require payment before Confirmed status.
   Effort: MEDIUM (5-7 hours refactoring)

15. Missing Key Confirmation Emails
   Area: Workflow Consistency
   Type: REQUIRED FEATURE
   Problem: Emails exist but: order confirmation, shipment notification, delivery confirmation are missing or incomplete
   Impact:
     - Customers unsure about order status
     - High support ticket volume
     - Customer anxiety about lost packages
   Fix: Add emails at each status: OrderConfirmed (with order details + tracking link), Preparing, ReadyPickup, OutForDelivery, Delivered (with receipt).
   Effort: MEDIUM (4-6 hours)

🟢 LOW SEVERITY ISSUES
----------------------------------------------------------------------

1. Behavior Tree Engine Underutilized
   Area: Feature Completeness
   Type: DESIGN ISSUE
   Problem: BehaviorTreeEngine.php exists but appears unused in main workflow
   Impact:
     - Dead code in codebase
     - Maintenance burden
     - Confusion about intent
   Fix: Either remove BehaviorTreeEngine or define its purpose. If unused, consider deletion to reduce code complexity.
   Effort: LOW (1-2 hours investigation)

2. No Mobile App
   Area: Feature Completeness
   Type: DESIGN ISSUE
   Problem: Web-only system; many transactions would be better on mobile
   Impact:
     - Reduced customer engagement
     - Missed convenience market segment
     - Lower order volume
   Fix: Future release: Build mobile app with REST API backend. Prioritize: order history, quick reorder, loyalty scan, delivery tracking.
   Effort: VERY HIGH (40+ hours, separate project)

3. Mixed Status Terminology
   Area: Workflow Consistency
   Type: DESIGN ISSUE
   Problem: Orders use: Pending, Confirmed, Preparing, Ready, Completed, Cancelled. Sales use: completed, refunded. Inconsistent across modules.
   Impact:
     - Confusion in reporting
     - Status mapping errors
     - Harder to debug workflows
   Fix: Standardize status enums across system. Document state machine: Pending → Confirmed → Preparing → Ready → Delivered → Completed.
   Effort: LOW (2-3 hours, refactoring)

======================================================================
🎯 PRIORITY ROADMAP
----------------------------------------------------------------------

PHASE 0 (CRITICAL - FIX IMMEDIATELY):
  • Incomplete Expiry Management (HIGH (6-8 hours))
  • Payment Status Separate from Order Status (HIGH (6-8 hours))
  • No Formalized Return/Refund System (HIGH (8-10 hours))
  • No Pharmacist Review Step for Prescription Medications (HIGH (6-8 hours))

PHASE 1 (HIGH - NEXT 2 WEEKS):
  • Missing Automatic Reorder System (MEDIUM (4-6 hours))
  • No Batch/Lot Number Tracking (HIGH (8-10 hours))
  • No Delivery Tracking Post-Shipment (MEDIUM (5-7 hours))
  • No Cancellation Window/Policy (MEDIUM (4-6 hours))
  • Limited Audit Logging for Sensitive Operations (MEDIUM (5-7 hours))
  ... and 4 more

PHASE 2 (MEDIUM - NEXT MONTH):
  15 medium-priority improvements identified

======================================================================
⏱️  ESTIMATED TOTAL EFFORT: 60-80 hours of development
✅ Scan complete. See detailed report below.
=======================================================================
