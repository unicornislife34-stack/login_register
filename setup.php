<?php
/**
 * Database Setup Script
 * This script initializes the inventory and menu tables in the database
 * Run this once to set up the required tables
 */

include 'config.php';

$success_messages = [];
$error_messages = [];

// SQL to create inventory table
$inventory_sql = "CREATE TABLE IF NOT EXISTS inventory (
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
)";

if (mysqli_query($conn, $inventory_sql)) {
    $success_messages[] = "✓ Inventory table created successfully!";
} else {
    $error_messages[] = "Error creating inventory table: " . mysqli_error($conn);
}

// SQL to create menu_items table
$menu_sql = "CREATE TABLE IF NOT EXISTS menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    cost DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    sizes VARCHAR(255),
    product_sku VARCHAR(50),
    image_path VARCHAR(255),
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $menu_sql)) {
    $success_messages[] = "✓ Menu items table created successfully!";
} else {
    $error_messages[] = "Error creating menu items table: " . mysqli_error($conn);
}

// SQL to create employee table
$employee_sql = "CREATE TABLE IF NOT EXISTS employee (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'employee',
    attendance_days INT(11) DEFAULT 0,
    salary DECIMAL(10,2) DEFAULT 0.00,
    date_hired TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $employee_sql)) {
    $success_messages[] = "✓ Employee table created successfully!";
} else {
    $error_messages[] = "Error creating employee table: " . mysqli_error($conn);
}

// SQL to create attendance table
$attendance_sql = "CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_username VARCHAR(100) NOT NULL,
    clock_in DATETIME NULL,
    clock_out DATETIME NULL,
    date DATE NOT NULL,
    break_started DATETIME NULL,
    break_total INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $attendance_sql)) {
    $success_messages[] = "✓ Attendance table created successfully!";
} else {
    $error_messages[] = "Error creating attendance table: " . mysqli_error($conn);
}

// Create uploads directories if they don't exist
if (!is_dir('uploads')) {
    mkdir('uploads', 0755);
}

if (!is_dir('uploads/menu_items')) {
    mkdir('uploads/menu_items', 0755, true);
}

if (!is_dir('uploads/inventory_items')) {
    mkdir('uploads/inventory_items', 0755, true);
}

$success_messages[] = "✓ Upload directories created!";

// Ensure the inventory table has the expected columns (for earlier versions)
function ensureColumn($conn, $table, $column, $definition) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && mysqli_num_rows($res) === 0) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

ensureColumn($conn, 'inventory', 'sku', "VARCHAR(50) NOT NULL DEFAULT ''");
ensureColumn($conn, 'inventory', 'size', "VARCHAR(50) DEFAULT NULL");
ensureColumn($conn, 'inventory', 'image_path', "VARCHAR(255) DEFAULT NULL");

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - L LE JOSE</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .setup-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            text-align: center;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
            text-align: center;
        }

        .messages {
            margin-bottom: 20px;
        }

        .message-item {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        a, button {
            flex: 1;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #e8e8e8;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .info-text {
            color: #666;
            font-size: 14px;
            margin-top: 15px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🔧 Database Setup</h1>
        
        <div class="messages">
            <?php foreach ($success_messages as $msg): ?>
                <div class="message-item success">
                    <span style="font-size: 18px;">✓</span>
                    <?= $msg ?>
                </div>
            <?php endforeach; ?>
            
            <?php foreach ($error_messages as $msg): ?>
                <div class="message-item error">
                    <span style="font-size: 18px;">✕</span>
                    <?= $msg ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="info-text">
            All required database tables and directories have been initialized successfully. You can now start using the POS system!
        </p>

        <div class="button-group">
            <a href="admin_page.php" class="btn-primary">Go to Dashboard</a>
        </div>
    </div>
</body>
</html>
