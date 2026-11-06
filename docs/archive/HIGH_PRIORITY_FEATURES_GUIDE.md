# High Priority Features - Setup Guide

This guide covers the installation and configuration of the three high-priority features added to Calloway Pharmacy IMS:

1. **Email Notifications System**
2. **Receipt PDF Generator**
3. **Purchase Order System**

## 📦 Installation

### Step 1: Install Dependencies

Run Composer to install PHPMailer and TCPDF:

```bash
composer install
```

If you don't have Composer installed, download it from [getcomposer.org](https://getcomposer.org/)

### Step 2: Run Database Setup

Navigate to the installation page in your browser:

```
http://localhost:8000/install_features.php
```

This will create the necessary database tables:
- `purchase_orders`
- `purchase_order_items`
- `settings` (for email configuration)

## 📧 Email Notifications System

### Features
- **Low Stock Alerts** - Automatic emails when products reach reorder level
- **Expiry Warnings** - Alerts for products expiring in 30 days
- **Daily Sales Summary** - Daily report of sales performance
- **Password Reset** - Email password reset links
- **Welcome Emails** - Onboarding emails for new users

### Configuration

1. Go to **Settings** page in the system
2. Configure SMTP settings:
   - **SMTP Host**: smtp.gmail.com (or your provider)
   - **SMTP Port**: 587 (TLS) or 465 (SSL)
   - **SMTP Username**: your-email@gmail.com
   - **SMTP Password**: your-app-password
   - **From Email**: noreply@callowaypharmacy.com
   - **From Name**: Calloway Pharmacy

#### Gmail Setup
If using Gmail, you need to:
1. Enable 2-Factor Authentication
2. Generate an App Password
3. Use the App Password in SMTP settings

### Automated Emails

Set up a scheduled task to run `email_cron.php` daily:

**Windows Task Scheduler:**
```
Program: C:\php\php.exe
Arguments: C:\xampp\htdocs\CallowayPharmacyIMS\email_cron.php
Trigger: Daily at 8:00 AM
```

**Linux Cron:**
```bash
0 8 * * * php /path/to/CallowayPharmacyIMS/email_cron.php
```

### Manual Testing

Run the cron job manually to test:
```bash
php email_cron.php
```

## 🧾 Receipt PDF Generator

### Features
- Professional PDF receipts for all transactions
- Company branding with logo
- QR code for verification
- Email receipts to customers
- Print and download options

### Usage

**From POS System:**
After completing a sale, you'll see options to:
- **View Receipt** - Opens PDF in browser
- **Download Receipt** - Saves PDF file
- **Email Receipt** - Sends to customer email

**From Reports:**
Click the receipt icon next to any transaction to regenerate the receipt.

### API Endpoints

```php
// View receipt inline
receipt_generator.php?sale_id=123&action=view

// Download receipt
receipt_generator.php?sale_id=123&action=download

// Email receipt
receipt_generator.php?sale_id=123&action=email&email=customer@example.com
```

### Customization

Edit `receipt_generator.php` to customize:
- Company information (name, address, phone)
- Receipt layout and styling
- QR code content
- Footer text

## 📋 Purchase Order System

### Features
- Create purchase orders for suppliers
- Track PO status (Pending → Ordered → Received → Cancelled)
- Automatic inventory updates when receiving stock
- Auto-generate PO numbers
- Supplier-specific product selection
- Purchase history and reporting

### Workflow

**1. Create Purchase Order**
- Navigate to **Purchase Orders** page
- Click **Create Purchase Order**
- Select supplier
- Add products with quantities and unit costs
- Add notes (optional)
- Submit

**2. Order Status**
- **Pending**: PO created, not yet ordered
- **Ordered**: PO sent to supplier, awaiting delivery
- **Received**: Products received, inventory updated
- **Cancelled**: PO cancelled

**3. Receive Stock**
- Find the PO in "Ordered" status
- Click **Receive** button
- Confirm to update inventory
- System automatically adds quantities to stock

**4. View Purchase History**
- Use the tabs to filter by status
- View all PO details including items and totals

### Auto-Reorder (Coming Soon)

Future enhancement: System will auto-suggest purchase orders when products fall below reorder level.

## 🔗 Integration Points

### Dashboard
- Low stock warnings trigger email alerts
- Expiring products trigger email warnings
- Purchase orders can be created directly from alerts

### Inventory Management
- Set reorder levels for each product
- Low stock products highlighted
- Quick link to create purchase orders

### Supplier Management
- Suppliers must be created before purchase orders
- Each product linked to a supplier
- Supplier contact info used in PO communication

### Reports
- Receipt generation for all transactions
- Purchase order history and analytics
- Email daily sales summaries

## 🛠️ Troubleshooting

### Emails Not Sending

1. **Check SMTP Settings**
   - Verify host, port, username, password
   - Test with a simple email client

2. **Check PHP Error Log**
   ```bash
   tail -f C:\xampp\php\logs\php_error_log
   ```

3. **Gmail Issues**
   - Use App Password, not regular password
   - Enable "Less secure app access" (not recommended)
   - Check Google Account security settings

### PDF Generation Issues

1. **TCPDF Not Installed**
   ```bash
   composer require tecnickcom/tcpdf
   ```

2. **Memory Limit**
   Edit `php.ini`:
   ```
   memory_limit = 256M
   ```

3. **Font Issues**
   TCPDF includes fonts, but ensure write permissions on temp folder

### Purchase Orders Not Saving

1. **Foreign Key Constraints**
   - Ensure suppliers table has data
   - Check product_id exists in products table

2. **Database Permissions**
   - User must have INSERT, UPDATE permissions
   - Check MySQL user grants

## 📚 File Reference

### Email System
- `email_service.php` - Main email class
- `email_cron.php` - Automated email scheduler

### PDF Receipts
- `receipt_generator.php` - PDF generation and API

### Purchase Orders
- `purchase_orders.php` - UI interface
- `purchase_order_api.php` - Backend API
- `purchase_orders_schema.sql` - Database schema

### Installation
- `composer.json` - PHP dependencies
- `install_features.php` - Database setup script

## 🎯 Next Steps

After setup, you should:

1. ✅ Test email sending
2. ✅ Generate a sample receipt
3. ✅ Create a test purchase order
4. ✅ Set reorder levels for products
5. ✅ Configure daily email schedule
6. ✅ Train staff on new features

## 🔐 Security Notes

- Store SMTP credentials securely in database
- Use environment variables for production
- Implement rate limiting on email sending
- Validate all user inputs in PO forms
- Restrict PO access to authorized users only

## 📞 Support

For issues or questions:
- Check error logs: `php_error_log`
- Review database logs
- Test with simple examples first
- Contact system administrator

---

**Version**: 1.0  
**Last Updated**: December 2025  
**Author**: Calloway Pharmacy Development Team
