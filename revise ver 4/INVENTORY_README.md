# L LE JOSE POS System - Inventory Module Setup Guide

## Overview
This inventory management system has been created for the L LE JOSE POS system with the following features:
- Product inventory management
- Stock tracking with low-stock alerts
- Add, Update, and Delete operations
- Search and filtering capabilities
- Category-based organization
- Inventory statistics (Total items, In stock, Total value)

## Files Created

### 1. **inventory.php** - Main Inventory Management Page
- Displays inventory table with all items
- Search functionality
- Category filtering
- Stock alert filtering
- Add new items modal
- Edit items (inline)
- Delete items with confirmation
- Responsive sidebar menu with statistics

### 2. **setup.php** - Database Setup Script
- Creates the `inventory` table automatically
- One-time setup script
- Provides feedback on database initialization

### 3. **inventory_schema.sql** - SQL Schema File
- SQL code for creating the inventory table manually (optional)
- Includes sample data (commented out)

### 4. **style.css** - Updated Styles
- Professional styling for the inventory page
- Responsive design (desktop, tablet, mobile)
- Sidebar navigation
- Table styling with hover effects
- Modal dialogs for add/edit operations
- Low-stock highlighting and alerts

## Database Setup Instructions

### Option 1: Automatic Setup (Recommended)
1. Visit `http://localhost/login_register/setup.php` in your browser
2. The system will automatically create the `inventory` table
3. You'll see a success message and can proceed to the dashboard

### Option 2: Manual Setup via phpMyAdmin
1. Open phpMyAdmin
2. Select your `users_db` database
3. Click on "SQL" tab
4. Copy and paste the contents of `inventory_schema.sql`
5. Click "Go" to execute

### Option 3: Direct SQL Execution
Run this SQL command in your database:
```sql
CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    sku VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    cost DECIMAL(10, 2) NOT NULL,
    quantity_in_stock INT NOT NULL DEFAULT 0,
    size VARCHAR(50) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## How to Use

### Accessing Inventory
1. Login to the system
2. Click on the "Inventory" card from the dashboard
3. You'll be taken to the inventory management page

### Adding Items
1. Click the "Add Item" button
2. Fill in the item details:
   - Item Name (required)
   - Category (Food, Beverage, Supplies, Equipment)
   - Price (selling price)
   - Cost (purchase cost)
   - Quantity in Stock
3. Click "Save Item"

### Editing Items
1. Click the Edit button (pencil icon) in the table row
2. Modal will open with item details pre-filled
3. Update the information
4. Click "Save Item"

### Deleting Items
1. Click the Delete button (trash icon) in the table row
2. Confirm deletion in the popup dialog
3. Item will be removed from inventory

### Searching Items
1. Use the search box to find items by name
2. Results update in real-time

### Filtering
- **Category Filter**: Select a category from the dropdown to show only items in that category
- **Stock Alert**: Click "Stock Alert" button to show only items with 10 or fewer units in stock
- These filters can be combined

### Sidebar Menu
The left sidebar includes:
- Products (currently showing)
- Supplier Contacts (placeholder for future feature)
- Valuation Report (placeholder for future feature)
- Inventory History (placeholder for future feature)
- Quick statistics showing total items, stock count, and total inventory value

## Database Structure

### Inventory Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key, auto-increment |
| item_name | VARCHAR(100) | Name of the item |
| sku | VARCHAR(50) | Product ID / SKU |
| category | VARCHAR(50) | Category (Food, Beverage, etc.) |
| price | DECIMAL(10,2) | Selling price in Philippine Pesos |
| cost | DECIMAL(10,2) | Cost/purchase price |
| quantity_in_stock | INT | Current quantity available |
| size | VARCHAR(50) | Optional size / variant information |
| image_path | VARCHAR(255) | Optional image file path |
| date_added | TIMESTAMP | When item was added |
| date_modified | TIMESTAMP | Last modification time |

## Features

### Low Stock Alerts
- Items with 10 or fewer units are highlighted in red/orange
- "Stock Alert" button filters to show only low-stock items
- Rows with low stock have a subtle background highlight

### Statistics
The sidebar displays:
- **Total Items**: Number of unique products
- **In Stock**: Total number of units across all items
- **Total Value**: Total monetary value of inventory (price × quantity)

### Responsive Design
The page is fully responsive and adapts to:
- Desktop screens (full sidebar)
- Tablet screens (collapsible sidebar)
- Mobile screens (hamburger menu)

## Security Notes
- All input is sanitized using `mysqli_real_escape_string`
- Session validation ensures only logged-in users can access
- HTML output is escaped using `htmlspecialchars()`

## Future Enhancements (Placeholders)
The sidebar includes menu items for:
- **Supplier Contacts**: Store and manage supplier information
- **Valuation Report**: Generate inventory valuation reports
- **Inventory History**: Track historical inventory changes

## Troubleshooting

### Database Connection Error
- Ensure your `config.php` has correct database credentials
- Check if MySQL server is running
- Verify database `users_db` exists

### Table Not Created
- Run `setup.php` again
- Or manually execute the SQL in phpMyAdmin

### Items Not Appearing in Table
- Ensure `inventory` table is created
- Add sample items using the "Add Item" button
- Check database connection

## Sample Data (Optional)
To add sample data, run this SQL:
```sql
INSERT INTO inventory (item_name, sku, category, price, cost, quantity_in_stock, size, image_path) VALUES
('Fried Rice', 'FR-001', 'Food', 150.00, 50.00, 45, 'Regular', NULL),
('Soda', 'SD-001', 'Beverage', 50.00, 15.00, 100, '330ml', NULL),
('Pasta', 'PA-001', 'Food', 120.00, 40.00, 30, 'Large', NULL),
('Coffee', 'CF-001', 'Beverage', 80.00, 20.00, 25, 'Medium', NULL);
```

## Support
For issues or questions, contact your system administrator.

---
Created: March 2026
Last Updated: March 2026
