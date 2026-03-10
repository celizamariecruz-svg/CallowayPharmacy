# Calloway Pharmacy Inventory Management System — Panelist Reviewer

**System:** Calloway Pharmacy IMS  
**Type:** Web-Based Pharmacy Inventory & Point-of-Sale System  
**Stack:** PHP 8 · MySQL/MariaDB · HTML/CSS/JS · Chart.js · PHPMailer · TCPDF  
**Deployment:** Azure App Service (CD via GitHub Actions) + Azure MySQL  
**URL:** https://callowaypharmacy.me  

---

## 1. System Overview

A full-featured web-based pharmacy management system covering inventory management, point-of-sale operations, online customer ordering, prescription enforcement, loyalty rewards, supplier/purchase order management, reporting/analytics, and automated email alerts — with role-based access control and audit logging.

---

## 2. User Roles & Access Control

| Role | Landing Page | Access Scope |
|------|-------------|--------------|
| **Admin** | Dashboard | Full system access (bypasses permission checks) |
| **Cashier** | POS | Point of Sale, Medicine Locator, Online Orders |
| **Inventory Manager** | Inventory | Products CRUD, Sales view, Reports view |
| **Customer** | Online Ordering | Browse products, place orders, track orders, loyalty |

### Permission System
- Granular permissions: `products.view`, `products.create`, `sales.create`, `sales.refund`, `reports.view`, `reports.export`, `users.view`, `users.create`, `settings.update`, `system.backup`, `system.logs`, etc.
- Stored in `roles`, `permissions`, `role_permissions` (junction) tables
- Checked via `Auth::hasPermission()` on every privileged action

---

## 3. Modules

### 3.1 Dashboard
- **Stats cards:** Total Products, Low Stock, Expiring Soon, Today's Sales, Monthly Sales, Total Customers
- **Thresholds read from settings table** (`low_stock_threshold`, `expiry_alert_days`) — consistent across all modules
- **Sales trend chart** (last 7 days) via Chart.js
- **Quick action links** to POS, Inventory, Medicine Locator, Reports
- **File-based caching** (2-minute TTL) via CacheManager for performance

### 3.2 Point of Sale (POS)
- Product catalog with **grid/list view**, category tabs, barcode/name search
- Cart management with quantity adjustment
- **Payment methods:** Cash, Loyalty Points (redeemable at 1 point = ₱1)
- **Loyalty member lookup** — search by name, email, or phone
- **Split payment:** Cash + Points combination
- **Expiry enforcement:** blocks sale of expired products, warns on near-expiry
- **Prescription enforcement:** flags Rx items, requires pharmacist approval before sale
- **Receipt generation:** 80mm thermal receipt PDF (TCPDF), printable and emailable
- **Stock auto-decrement:** creates `stock_movements` OUT record on sale
- **ESC/POS thermal printer support** via Python script
- Quick-cash buttons (exact amount, ₱500, ₱1000)
- Change calculation in real-time
- Scrollable payment modal (responsive to zoom)

### 3.3 Inventory Management
- Full **product CRUD** with image upload
- **Product fields:** name, generic name, brand name, category, strength, dosage form, price, cost price, stock quantity, reorder level, location, barcode, batch number, expiry date, Rx flag, sell-by-piece option
- **Stock movements:** IN (restock), OUT (sale/adjustment), ADJUSTMENT — each recording `previous_stock → new_stock`
- **Low stock alerts:** configurable threshold from system settings
- **Expiry monitoring:** products flagged by expired / expiring soon / valid
- **Category management:** create, edit, delete
- **Activity log** for inventory actions
- **Search, filter, pagination, CSV export**

### 3.4 Medicine Locator & Expiry Monitoring
- Unified view combining medicine search + expiry dashboard
- **Stats bar:** Total Medicines, Categories, Out of Stock, Low Stock, Expired, Expiring Soon, Valid Expiry
- **Color-coded expiry badges:** red (expired), orange (expiring soon), green (valid)
- Search by name, category, price range, location, generic/brand name
- Category filter dropdown
- **Configurable thresholds** from settings (`expiry_alert_days`, `low_stock_threshold`)
- Location field for physical shelf/aisle navigation

### 3.5 Online Ordering
- **Customer-facing catalog** with product cards, search, category filter
- **Cart system** with quantity controls and real-time total
- **Guest browsing** allowed; **login required** to place order
- **Order workflow:** Pending → Confirmed → Preparing → Ready for Pickup → Completed (or Cancelled)
- **Prescription check** on order submission
- **POS notification** pushed to staff when new order arrives
- **Order tracking** by reference number (guest) or "My Orders" (logged in)
- **Pharmacist approval** for Rx-containing orders
- Payment method: Cash on Pickup

### 3.6 Reports & Analytics
18 data endpoints powering dashboard visualizations:

| Report | Description |
|--------|-------------|
| Key Metrics | Total sales, transaction count, avg transaction, revenue |
| Top Products | Best-selling by quantity and by profit margin |
| Category Sales | Revenue breakdown by product category |
| Sales Trend | Daily line chart over custom date range |
| Payment Mix | Cash vs Loyalty Points distribution |
| Inventory Risk | Low stock + expiring products summary |
| Dead Stock | Products with zero sales in the period |
| Slow Movers | Low-velocity products |
| Order Status | Online order status distribution |
| Rx Stats | Prescription medication analytics |
| Customer Stats | Customer purchase analytics |
| Loyalty Stats | Points earned, redeemed, active members |
| Inventory Value | Total stock valuation |
| Top Cashiers | Cashier sales performance ranking |

- **Date-range filtering** on all reports
- **CSV export:** individual report types or full data export
- **Permission-gated:** `reports.view`, `reports.export`

### 3.7 Supplier Management
- Full **supplier CRUD:** name, contact person, phone, email, address
- Card-based grid layout with search
- Linked to products via `supplier_id` FK

### 3.8 Purchase Orders
- **Create PO:** select supplier, add product line items (quantity, unit cost)
- **Status flow:** Pending → Ordered → Received → Cancelled
- **Receive PO:** records `received_quantity`, **auto-replenishes stock** via stock_movement IN records
- Tracks `order_date`, `expected_delivery`, `received_date`, total cost

### 3.9 Users & Access
- **User CRUD:** create, edit, delete accounts with role assignment
- **Tabs:** Users List, Active Sessions, Login History, Change Log
- **Stats:** total users, active today, roles breakdown
- **Password management:** bcrypt hashing (cost 12)
- **Customer self-registration** with email verification (6-digit code)
- **Forgot password** → token-based reset link via email

### 3.10 Loyalty & QR Rewards
- **QR generation** after purchase → one-time reward QR code
- **Points earn** via QR scan (configurable ratio per purchase amount)
- **Points redeem** at POS as partial/full payment (1 pt = ₱1)
- **Customer view:** My Points balance, Scan QR, QR history
- **Staff view:** customer lookup, manual point adjustment
- QR expiration enforcement, transaction types: EARN, REDEEM, QR_SCAN, BONUS, ADJUSTMENT

### 3.11 System Settings
Tabbed settings UI:

| Tab | Key Settings |
|-----|-------------|
| **Company** | Name, address, phone, email, website, logo |
| **Tax & Currency** | Tax rate (12%), currency (₱ PHP), tax toggle |
| **Email/SMTP** | SMTP host/port/user/password (AES-encrypted), from name/address |
| **Receipt** | Header, footer, logo toggle, barcode toggle, paper width (80mm) |
| **Alerts** | Low stock threshold (20), expiry alert days (30), email toggles, report frequency |
| **Backup** | Auto-backup toggle, frequency, retention days (30) |

### 3.12 Backup System
- **Manual backup:** admin creates on-demand from UI
- **Automatic backup:** daily via cron job
- **Method:** `mysqldump` primary, PHP-based fallback
- **gzip compression** of SQL dumps
- **Retention policy:** auto-cleanup after 30 days
- **Restore, download, delete** from UI
- Web-access blocked via `.htaccess`

### 3.13 Notification Tray
- **Real-time bell icon** with unread badge count
- Alert types: low stock warnings, expiry warnings, pending online orders
- Mark individual or all as read
- Visible to all staff roles (not customers)

---

## 4. Security Implementation

| Layer | Mechanism | Details |
|-------|-----------|---------|
| **Authentication** | bcrypt (cost 12) | `password_hash()` / `password_verify()` |
| **Session Mgmt** | Secure sessions | `httponly`, `samesite=Strict`, 1-hour timeout, ID regeneration every 30 min |
| **Session Fixation** | `session_regenerate_id(true)` | New session ID on login |
| **CSRF Protection** | 32-byte random token | `hash_equals()` timing-safe validation, 1-hour expiry |
| **XSS Prevention** | `htmlspecialchars()` | All user output escaped; CSP header set |
| **SQL Injection** | Prepared statements | All DB queries use `bind_param()` / parameterized queries |
| **Encryption at Rest** | AES-256-CBC | SMTP passwords encrypted in database |
| **Rate Limiting** | 5 attempts / 15-min lockout | Login brute-force protection |
| **File Security** | `.htaccess` rules | Blocks directory listing, sensitive files, setup scripts, SQL files |
| **URL Filtering** | Apache rules | Blocks `UNION SELECT`, `<script>`, `base64_encode`, `GLOBALS` in query strings |
| **HTTP Methods** | Restricted | Only GET, POST, HEAD allowed |
| **Upload Security** | PHP exec blocked | No `.php` execution in `/uploads/` directory |
| **Security Headers** | Apache | X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy |
| **Prescription Control** | RxEnforcement.php | Blocks sale of Rx items without pharmacist approval |
| **Expiry Control** | ExpiryEnforcement.php | Blocks sale of expired products |

---

## 5. Database Design

**27 tables** across these domains:

| Domain | Tables |
|--------|--------|
| **Products** | `products`, `categories`, `stock_movements` |
| **Sales** | `sales`, `sale_items`, `sale_payments`, `returns`, `return_items` |
| **Online Orders** | `online_orders`, `online_order_items`, `pos_notifications` |
| **Users & Auth** | `users`, `roles`, `permissions`, `role_permissions`, `employees` |
| **Suppliers** | `suppliers`, `purchase_orders`, `purchase_order_items` |
| **Loyalty** | `loyalty_members`, `loyalty_points_log`, `reward_qr_codes` |
| **Audit** | `activity_logs`, `login_sessions`, `change_logs`, `rx_approval_log` |
| **System** | `settings` |

### Key Relationships
- `products.category_id` → `categories.category_id`
- `products.supplier_id` → `suppliers.supplier_id`
- `sale_items.sale_id` → `sales.sale_id`
- `sale_items.product_id` → `products.product_id`
- `users.role_id` → `roles.role_id`
- `role_permissions` → `roles` + `permissions` (junction)
- `online_order_items.product_id` → `products.product_id`
- `purchase_order_items.product_id` → `products.product_id`
- `loyalty_points_log.member_id` → `loyalty_members.member_id`
- `stock_movements.product_id` → `products.product_id`

### Stock Tracking (Full Audit Trail)
Every stock change records: `product_id`, `type` (IN/OUT/ADJUSTMENT), `quantity`, `previous_stock`, `new_stock`, `reference_type` (sale/purchase_order/manual), `reference_id`, `notes`, `created_by`, `created_at`

---

## 6. Automated Features

| Feature | Trigger | What It Does |
|---------|---------|-------------|
| **Low Stock Email Alert** | Daily at 2 PM (auto-trigger) | Emails list of products below stock threshold |
| **Expiry Warning Email** | Daily at 2 PM (auto-trigger) | Emails products expiring within configured days |
| **Daily Sales Report** | Daily at 2 PM (auto-trigger) | Emails sales summary, top products, revenue |
| **Weekly/Monthly Reports** | Configurable | Same as daily but with period aggregation |
| **Auto Backup** | Daily via cron | Creates gzip-compressed SQL backup |
| **Backup Cleanup** | During backup | Removes backups older than retention period |
| **Behavior Tree Engine** | On inventory check | AI-inspired entity model transitions products through lifecycle stages (Normal → Low Stock → Critical → Expiring → Expired → Out of Stock) |

### Email Auto-Trigger (Deployed Version)
- `cron_web.php` — web-accessible cron endpoint
- Auto-fires when admin user loads any page after 2 PM
- `cron_last_run` setting prevents duplicate daily runs
- Dual auth: session-based (admin) or token-based (external cron)
- Fallback: `register_shutdown_function()` if `shell_exec` unavailable

---

## 7. Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.x |
| **Database** | MySQL 8.0 / MariaDB |
| **Frontend** | HTML5, CSS3 (Custom Properties), Vanilla JavaScript |
| **Charts** | Chart.js |
| **PDF** | TCPDF (receipt generation) |
| **Email** | PHPMailer (SMTP via Gmail) |
| **QR Codes** | QRCode.js (client-side generation) |
| **Encryption** | AES-256-CBC (OpenSSL), bcrypt |
| **Thermal Printing** | ESC/POS via Python |
| **Web Server** | Apache (`.htaccess` security rules) |
| **Deployment** | Azure App Service + Azure MySQL |
| **CI/CD** | GitHub Actions (push to `main` → auto-deploy) |
| **Version Control** | Git / GitHub |

---

## 8. Infrastructure Architecture

```
┌─────────────┐     HTTPS      ┌──────────────────────────┐
│ Web Browser  │ ──────────────→ │  Azure App Service (PHP) │
│ (Client)     │ ←────────────── │  Apache + .htaccess      │
└─────────────┘                 └──────────┬───────────────┘
                                           │
                    ┌──────────────────────┬┴──────────────────────┐
                    │                      │                       │
              ┌─────▼──────┐     ┌─────────▼────────┐   ┌────────▼────────┐
              │ Azure MySQL │     │ Gmail SMTP       │   │ File Storage    │
              │ (Database)  │     │ (Email Alerts)   │   │ (Backups, Logs, │
              └────────────┘     └──────────────────┘   │  Uploads)       │
                                                         └─────────────────┘
              ┌─────────────────────────────────────────────┐
              │ Scheduled Jobs (auto-triggered):            │
              │  • email_cron.php (daily 2PM via web hook)  │
              │  • backup_cron.php (daily via Task Sched.)  │
              └─────────────────────────────────────────────┘
```

---

## 9. Key API Endpoints Summary

| API File | # Endpoints | Domain |
|----------|------------|--------|
| `pos_api.php` | 2 | Create sale, recent sales |
| `inventory_api.php` | 14 | Product CRUD, stock moves, categories, alerts |
| `online_order_api.php` | 9 | Order management, notifications |
| `api_orders.php` | 2 | Customer order tracking |
| `user_api.php` | 11 | User CRUD, roles, sessions, logs |
| `supplier_api.php` | 5 | Supplier CRUD |
| `purchase_order_api.php` | 4 | PO CRUD, receive |
| `reward_qr_api.php` | 7 | QR generation, redemption, points |
| `get_reports_data.php` | 18 | All report data types |
| `notification_api.php` | 3 | Notification tray |
| `settings_api.php` | 2 | Settings CRUD |
| `cron_web.php` | 1 | Auto email trigger |
| **Total** | **~78** | |

---

## 10. File Structure (Key Files)

```
├── index.php                  # Role-based routing entry point
├── login.php / login_handler  # Authentication
├── dashboard.php              # Admin dashboard
├── pos.php / pos_api.php      # Point of Sale
├── inventory_management.php   # Inventory UI
├── inventory_api.php          # Inventory API (14 endpoints)
├── medicine-locator.php       # Medicine search + expiry monitor
├── onlineordering.php         # Customer storefront
├── order_status.php           # Customer order tracking
├── online_order_api.php       # Staff order management API
├── reports.php                # Reports & Analytics UI
├── get_reports_data.php       # Reports data API (18 types)
├── user_management.php        # Users & Access (4 tabs)
├── user_api.php               # User management API
├── settings_enhanced.php      # System Settings (6 tabs)
├── loyalty_qr.php             # Loyalty & QR rewards
├── supplier_management.php    # Supplier CRUD
├── purchase_orders.php        # Purchase order workflow
├── backup_manager.php         # Backup UI
├── Auth.php                   # Authentication & session management
├── CSRF.php                   # CSRF token protection
├── Security.php               # Rate limiting, session hardening
├── CryptoManager.php          # AES-256-CBC encryption
├── ActivityLogger.php         # Full audit trail service
├── ExpiryEnforcement.php      # Blocks expired product sales
├── RxEnforcement.php          # Prescription enforcement
├── BehaviorTreeEngine.php     # AI-inspired product lifecycle
├── CacheManager.php           # File-based caching
├── email_service.php          # PHPMailer email service (9 types)
├── email_cron.php             # Automated email alerts
├── cron_web.php               # Web-triggered cron for deployment
├── BackupManager.php          # Backup/restore logic
├── receipt_generator.php      # PDF receipt (TCPDF)
├── config.php                 # Environment config
├── db_connection.php          # Database connection
├── .htaccess                  # Apache security rules
└── migrations/                # 5 database migration files
```

---

## 11. Potential Panelist Questions & Answers

### Architecture & Design
**Q: Why PHP instead of a framework like Laravel?**  
A: The system was built with vanilla PHP for full transparency — no framework magic. Every component (routing, auth, CSRF, ORM-like helpers) was hand-implemented, demonstrating deep understanding of web fundamentals. This also simplifies deployment and avoids framework overhead.

**Q: How do you handle concurrent stock updates?**  
A: Stock adjustments use MySQL's atomic `UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?` with a `stock_movements` audit trail recording `previous_stock` and `new_stock` for every transaction.

**Q: How does data consistency work across modules?**  
A: All modules read threshold values (`low_stock_threshold`, `expiry_alert_days`) from a central `settings` table. Dashboard, inventory, and medicine locator use identical query logic to ensure consistent counts.

### Security
**Q: How do you prevent SQL injection?**  
A: Every database query uses prepared statements with `bind_param()`. No raw string concatenation in SQL. Additionally, `.htaccess` blocks common SQL injection URL patterns.

**Q: How are passwords stored?**  
A: bcrypt with cost factor 12 via `password_hash(PASSWORD_BCRYPT)`. SMTP credentials are AES-256-CBC encrypted in the database via CryptoManager.

**Q: How do you handle CSRF?**  
A: 32-byte cryptographically random tokens (`bin2hex(random_bytes(32))`) stored in session, validated with timing-safe `hash_equals()`. Forms use hidden inputs; AJAX uses `X-CSRF-Token` header.

**Q: How do you prevent brute force attacks?**  
A: Rate limiting allows max 5 login attempts, then 15-minute lockout. Apache mod_evasive provides additional DoS protection.

### Business Logic
**Q: How does the prescription workflow work?**  
A: Products marked `requires_prescription = 1` are flagged at POS and online checkout. RxEnforcement.php blocks the sale until a pharmacist approves it. The approval is logged in `rx_approval_log` with pharmacist ID, timestamp, and notes.

**Q: How does the loyalty program work?**  
A: Purchases generate QR reward codes. Customers scan to earn points (configurable ratio). Points are redeemable at POS (1 pt = ₱1). The system supports EARN, REDEEM, QR_SCAN, BONUS, and ADJUSTMENT transaction types. All tracked in `loyalty_points_log`.

**Q: What happens when a purchase order is received?**  
A: The system records `received_quantity` on each PO item, creates stock_movement IN records, and auto-increments `stock_quantity` on the products. This is atomic — if stock update fails, the PO isn't marked received.

### Deployment & Operations
**Q: How do daily email reports work on the deployed version?**  
A: A web-accessible cron endpoint (`cron_web.php`) auto-fires when any admin loads any page after 2 PM. It checks `cron_last_run` in the settings table to prevent double-sends. Supports both session auth (admin) and token auth (external cron service).

**Q: How is the system deployed?**  
A: Continuous deployment via GitHub Actions. Push to `main` branch → GitHub Actions builds → deploys to Azure App Service. Database is on Azure MySQL. HTTPS enforced.

**Q: How do backups work?**  
A: Automatic daily backups via cron (primary: `mysqldump`, fallback: PHP-based SQL export). gzip compressed. 30-day retention with auto-cleanup. Admin can create, restore, download, and delete backups from the UI.

### Testing & Data Integrity
**Q: How do you ensure data integrity for financial transactions?**  
A: Sales use auto-generated reference numbers (server-side `TX-{timestamp}-{random}`), never trusting client input. `sale_items` links to `sale_id` with cascading constraints. Stock decrements are atomic. Every stock change is audited in `stock_movements` with before/after values.

**Q: What happens if a product expires?**  
A: ExpiryEnforcement.php blocks the sale at POS. The BehaviorTreeEngine transitions the product through lifecycle stages (Normal → Low Stock → Critical → Expiring Soon → Expired → Out of Stock). Email alerts warn the owner daily about expiring products.

---

*Document generated: March 8, 2026*
