-- Inventory Table for L LE JOSE POS System
-- Run this SQL to create the inventory table in your users_db database

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

-- If you already have an inventory table, you can add the new columns with:
-- ALTER TABLE inventory ADD COLUMN sku VARCHAR(50) NOT NULL DEFAULT '';
-- ALTER TABLE inventory ADD COLUMN size VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE inventory ADD COLUMN image_path VARCHAR(255) DEFAULT NULL;

-- Sample data (optional)
-- INSERT INTO inventory (item_name, category, price, cost, quantity_in_stock) VALUES
-- ('Fried Rice', 'Food', 150.00, 50.00, 45),
-- ('Soda', 'Beverage', 50.00, 15.00, 100),
-- ('Pasta', 'Food', 120.00, 40.00, 30),
-- ('Coffee', 'Beverage', 80.00, 20.00, 25);

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    address VARCHAR(255),
    phone_number VARCHAR(50),
    email VARCHAR(150),
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inventory history table
CREATE TABLE IF NOT EXISTS inventory_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id INT NULL,
    action VARCHAR(20) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    quantity INT DEFAULT 0,
    reason VARCHAR(255),
    changed_by VARCHAR(100),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
