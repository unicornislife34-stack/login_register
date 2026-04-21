<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Ensure supplier and history tables exist
$suppliers_table_sql = "CREATE TABLE IF NOT EXISTS suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    phone_number VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $suppliers_table_sql);

$history_table_sql = "CREATE TABLE IF NOT EXISTS inventory_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id INT DEFAULT NULL,
    action VARCHAR(20) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    quantity INT DEFAULT 0,
    reason VARCHAR(255) DEFAULT NULL,
    changed_by VARCHAR(100) DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $history_table_sql);

function logInventoryHistory($conn, $inventory_id, $action, $item_name, $category = null, $quantity = 0, $reason = null) {
    $inventory_id = intval($inventory_id);
    $action = mysqli_real_escape_string($conn, $action);
    $item_name = mysqli_real_escape_string($conn, $item_name);
    $category = mysqli_real_escape_string($conn, $category);
    $quantity = intval($quantity);
    $reason = mysqli_real_escape_string($conn, $reason);

    $sql = "INSERT INTO inventory_history (inventory_id, action, item_name, category, quantity, reason) VALUES ($inventory_id, '$action', '$item_name', '$category', $quantity, '$reason')";
    mysqli_query($conn, $sql);
}

// Handle Add/Edit/Delete Item
$upload_dir = 'uploads/inventory_items/';

function saveUploadedImage($file, $upload_dir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        return null;
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = uniqid('inv_') . '.' . $ext;
    $target = rtrim($upload_dir, '/') . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $target;
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $sku = mysqli_real_escape_string($conn, $_POST['sku']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $price = floatval($_POST['price']);
        $cost = floatval($_POST['cost']);
        $quantity = intval($_POST['quantity']);
        $size = mysqli_real_escape_string($conn, $_POST['size']);

        $image_path = saveUploadedImage($_FILES['image'] ?? null, $upload_dir);
        $image_sql = $image_path ? "'$image_path'" : "NULL";
        $size_sql = $size !== '' ? "'$size'" : "NULL";

        $sql = "INSERT INTO inventory (item_name, sku, category, price, cost, quantity_in_stock, size, image_path, batch_number, date_added) 
                VALUES ('$item_name', '$sku', '$category', $price, $cost, $quantity, $size_sql, $image_sql, 1, NOW())";

        if (mysqli_query($conn, $sql)) {
            $insert_id = mysqli_insert_id($conn);
            logInventoryHistory($conn, $insert_id, 'add', $item_name, $category, $quantity, 'Item added');
            $success_msg = "Item added successfully!";
        } else {
            $error_msg = "Error adding item: " . mysqli_error($conn);
        }
    }

    if ($action === 'update') {
        $item_id = intval($_POST['item_id']);
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $sku = mysqli_real_escape_string($conn, $_POST['sku']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $price = floatval($_POST['price']);
        $cost = floatval($_POST['cost']);
        $quantity = intval($_POST['quantity']);
        $size = mysqli_real_escape_string($conn, $_POST['size']);

        $existing_image_path = null;
        $existing_result = mysqli_query($conn, "SELECT image_path FROM inventory WHERE id=$item_id");
        if ($existing_result && $row = mysqli_fetch_assoc($existing_result)) {
            $existing_image_path = $row['image_path'];
        }

        $new_image_path = saveUploadedImage($_FILES['image'] ?? null, $upload_dir);
        if ($new_image_path) {
            if ($existing_image_path && file_exists($existing_image_path)) {
                @unlink($existing_image_path);
            }
            $image_path_to_save = $new_image_path;
        } else {
            $image_path_to_save = $existing_image_path;
        }

        $image_sql = $image_path_to_save ? "'$image_path_to_save'" : "NULL";
        $size_sql = $size !== '' ? "'$size'" : "NULL";

        // Check if quantity is being increased to determine if we need a new batch
        $existing_quantity = 0;
        $existing_batch = 1;
        $existing_result = mysqli_query($conn, "SELECT quantity_in_stock, batch_number FROM inventory WHERE id=$item_id");
        if ($existing_result && $row = mysqli_fetch_assoc($existing_result)) {
            $existing_quantity = intval($row['quantity_in_stock']);
            $existing_batch = intval($row['batch_number']);
        }

        $new_batch = $existing_batch;
        if ($quantity > $existing_quantity) {
            $new_batch = $existing_batch + 1; // Increment batch for additional stock
        }

        $sql = "UPDATE inventory SET item_name='$item_name', sku='$sku', category='$category', price=$price, cost=$cost, quantity_in_stock=$quantity, size=$size_sql, image_path=$image_sql, batch_number=$new_batch 
                WHERE id=$item_id";

        if (mysqli_query($conn, $sql)) {
            logInventoryHistory($conn, $item_id, 'update', $item_name, $category, $quantity, 'Item updated');
            $success_msg = "Item updated successfully!";
        } else {
            $error_msg = "Error updating item: " . mysqli_error($conn);
        }
    }

    if ($action === 'delete') {
        $item_id = intval($_POST['item_id']);

        $existing_image_path = null;
        $existing_item = null;
        $existing_result = mysqli_query($conn, "SELECT item_name, category, quantity_in_stock, image_path FROM inventory WHERE id=$item_id");
        if ($existing_result && $row = mysqli_fetch_assoc($existing_result)) {
            $existing_image_path = $row['image_path'];
            $existing_item = $row;
        }
        if ($existing_image_path && file_exists($existing_image_path)) {
            @unlink($existing_image_path);
        }

        $sql = "DELETE FROM inventory WHERE id=$item_id";

        if (mysqli_query($conn, $sql)) {
            if ($existing_item) {
                logInventoryHistory($conn, $item_id, 'delete', $existing_item['item_name'], $existing_item['category'], $existing_item['quantity_in_stock'], 'Item deleted');
            }
            $success_msg = "Item deleted successfully!";
        } else {
            $error_msg = "Error deleting item: " . mysqli_error($conn);
        }
    }

    if ($action === 'bulk_delete') {
        $delete_ids = $_POST['delete_ids'] ?? [];
        if (!empty($delete_ids)) {
            $ids_string = implode(',', array_map('intval', $delete_ids));

            // Delete associated images and record history
            $image_result = mysqli_query($conn, "SELECT id, item_name, category, quantity_in_stock, image_path FROM inventory WHERE id IN ($ids_string)");
            while ($row = mysqli_fetch_assoc($image_result)) {
                if ($row['image_path'] && file_exists($row['image_path'])) {
                    @unlink($row['image_path']);
                }
                logInventoryHistory($conn, $row['id'], 'delete', $row['item_name'], $row['category'], $row['quantity_in_stock'], 'Bulk delete');
            }

            $sql = "DELETE FROM inventory WHERE id IN ($ids_string)";

            if (mysqli_query($conn, $sql)) {
                $success_msg = count($delete_ids) . " items deleted successfully!";
            } else {
                $error_msg = "Error deleting items: " . mysqli_error($conn);
            }
        }
    }

    if ($action === 'supplier_add') {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);

        $sql = "INSERT INTO suppliers (name, description, address, phone_number, email) VALUES ('$name', '$description', '$address', '$phone_number', '$email')";
        if (mysqli_query($conn, $sql)) {
            $success_msg = "Supplier added successfully!";
        } else {
            $error_msg = "Error adding supplier: " . mysqli_error($conn);
        }
    }

    if ($action === 'supplier_update') {
        $supplier_id = intval($_POST['supplier_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);

        $sql = "UPDATE suppliers SET name='$name', description='$description', address='$address', phone_number='$phone_number', email='$email' WHERE id=$supplier_id";
        if (mysqli_query($conn, $sql)) {
            $success_msg = "Supplier updated successfully!";
        } else {
            $error_msg = "Error updating supplier: " . mysqli_error($conn);
        }
    }

    if ($action === 'supplier_delete') {
        $supplier_id = intval($_POST['supplier_id']);
        $sql = "DELETE FROM suppliers WHERE id=$supplier_id";
        if (mysqli_query($conn, $sql)) {
            $success_msg = "Supplier deleted successfully!";
        } else {
            $error_msg = "Error deleting supplier: " . mysqli_error($conn);
        }
    }

    if ($action === 'supplier_bulk_delete') {
        $delete_ids = $_POST['supplier_delete_ids'] ?? [];
        if (!empty($delete_ids)) {
            $ids_string = implode(',', array_map('intval', $delete_ids));
            $sql = "DELETE FROM suppliers WHERE id IN ($ids_string)";
            if (mysqli_query($conn, $sql)) {
                $success_msg = count($delete_ids) . " suppliers deleted successfully!";
            } else {
                $error_msg = "Error deleting suppliers: " . mysqli_error($conn);
            }
        }
    }
}

// Get search and filter parameters

// Get search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : '';

// Build query
$query = "SELECT * FROM inventory WHERE 1=1";

if ($search) {
    $query .= " AND item_name LIKE '%$search%'";
}

if ($category_filter && $category_filter !== 'all') {
    $query .= " AND category = '$category_filter'";
}

// Check for low stock (less than 10)
if (isset($_GET['stock_alert'])) {
    $query .= " AND quantity_in_stock <= 10";
}

// Add sorting
$order_by = "date_added DESC"; // default
if ($sort_by) {
    switch ($sort_by) {
        case 'name_asc':
            $order_by = "item_name ASC";
            break;
        case 'name_desc':
            $order_by = "item_name DESC";
            break;
        case 'sku_asc':
            $order_by = "sku ASC";
            break;
        case 'sku_desc':
            $order_by = "sku DESC";
            break;
        case 'date_asc':
            $order_by = "date_added ASC";
            break;
        case 'date_desc':
            $order_by = "date_added DESC";
            break;
        case 'price_asc':
            $order_by = "price ASC";
            break;
        case 'price_desc':
            $order_by = "price DESC";
            break;
        case 'cost_asc':
            $order_by = "cost ASC";
            break;
        case 'cost_desc':
            $order_by = "cost DESC";
            break;
        case 'stock_asc':
            $order_by = "quantity_in_stock ASC";
            break;
        case 'stock_desc':
            $order_by = "quantity_in_stock DESC";
            break;
    }
}

$query .= " ORDER BY $order_by";

$result = mysqli_query($conn, $query);
$items = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get all categories for filter
$category_result = mysqli_query($conn, "SELECT DISTINCT category FROM inventory ORDER BY category");
$categories = mysqli_fetch_all($category_result, MYSQLI_ASSOC);

// Get inventory stats and valuation
$stats_result = mysqli_query($conn, "SELECT COUNT(*) as total_items,
    SUM(quantity_in_stock) as total_stock,
    SUM(cost * quantity_in_stock) as total_value,
    SUM(price * quantity_in_stock) as total_retail_value,
    SUM((price - cost) * quantity_in_stock) as total_profit
    FROM inventory");
$stats = mysqli_fetch_assoc($stats_result);
$stats['total_value'] = floatval($stats['total_value']);
$stats['total_retail_value'] = floatval($stats['total_retail_value']);
$stats['total_profit'] = floatval($stats['total_profit']);
$stats['profit_margin'] = $stats['total_value'] > 0 ? ($stats['total_profit'] / $stats['total_value']) * 100 : 0;

$valuation_result = mysqli_query($conn, "SELECT id, item_name, category, price, cost, quantity_in_stock FROM inventory ORDER BY item_name ASC");
$valuation_items = mysqli_fetch_all($valuation_result, MYSQLI_ASSOC);
foreach ($valuation_items as &$valuation_item) {
    $inventory_value = floatval($valuation_item['cost']) * intval($valuation_item['quantity_in_stock']);
    $retail_value = floatval($valuation_item['price']) * intval($valuation_item['quantity_in_stock']);
    $profit_value = $retail_value - $inventory_value;
    $valuation_item['inventory_value'] = $inventory_value;
    $valuation_item['retail_value'] = $retail_value;
    $valuation_item['profit_value'] = $profit_value;
    $valuation_item['profit_margin'] = $inventory_value > 0 ? ($profit_value / $inventory_value) * 100 : ($retail_value > 0 ? 100 : 0);
}
unset($valuation_item);

$top_selling_query = "SELECT i.id, i.item_name, i.category, COALESCE(SUM(oi.qty), 0) as total_qty_sold, COALESCE(SUM(oi.subtotal), 0) as total_sales_value FROM inventory i LEFT JOIN order_items oi ON oi.inventory_id = i.id GROUP BY i.id ORDER BY total_qty_sold DESC, total_sales_value DESC LIMIT 10";
$top_selling_result = mysqli_query($conn, $top_selling_query);
if (!$top_selling_result) {
    error_log("Top selling query failed: " . mysqli_error($conn));
    $top_selling = [];
} else {
    $top_selling = mysqli_fetch_all($top_selling_result, MYSQLI_ASSOC);
}

$bottom_selling_query = "SELECT i.id, i.item_name, i.category, COALESCE(SUM(oi.qty), 0) as total_qty_sold, COALESCE(SUM(oi.subtotal), 0) as total_sales_value FROM inventory i LEFT JOIN order_items oi ON oi.inventory_id = i.id GROUP BY i.id ORDER BY total_qty_sold ASC, total_sales_value ASC LIMIT 10";
$bottom_selling_result = mysqli_query($conn, $bottom_selling_query);
if (!$bottom_selling_result) {
    error_log("Bottom selling query failed: " . mysqli_error($conn));
    $bottom_selling = [];
} else {
    $bottom_selling = mysqli_fetch_all($bottom_selling_result, MYSQLI_ASSOC);
}

$low_stock_count = 0;
foreach ($items as $item) {
    if ($item['quantity_in_stock'] <= 10) {
        $low_stock_count++;
    }
}

// Suppliers list and search
$supplier_search = isset($_GET['supplier_search']) ? mysqli_real_escape_string($conn, $_GET['supplier_search']) : '';
$supplier_query = "SELECT * FROM suppliers WHERE 1=1";
if ($supplier_search) {
    $supplier_query .= " AND (name LIKE '%$supplier_search%' OR email LIKE '%$supplier_search%' OR phone_number LIKE '%$supplier_search%')";
}
$supplier_query .= " ORDER BY name ASC";
$supplier_result = mysqli_query($conn, $supplier_query);
$suppliers = mysqli_fetch_all($supplier_result, MYSQLI_ASSOC);

// Inventory history search
$history_search = isset($_GET['history_search']) ? mysqli_real_escape_string($conn, $_GET['history_search']) : '';
$history_query = "SELECT id, inventory_id, action, item_name, category, quantity, reason, changed_at FROM inventory_history WHERE 1=1";
if ($history_search) {
    $history_query .= " AND item_name LIKE '%$history_search%'";
}
$history_query .= " ORDER BY changed_at DESC LIMIT 200";
$history_result = mysqli_query($conn, $history_query);
$history_items = mysqli_fetch_all($history_result, MYSQLI_ASSOC);

// Prepare edit item data if requested (pre-fill the modal form)
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = mysqli_query($conn, "SELECT * FROM inventory WHERE id=$edit_id LIMIT 1");
    if ($edit_result) {
        $edit_item = mysqli_fetch_assoc($edit_result);
    }
}

// Prepare edit supplier data if requested
$edit_supplier = null;
if (isset($_GET['edit_supplier'])) {
    $edit_supplier_id = intval($_GET['edit_supplier']);
    $edit_supplier_result = mysqli_query($conn, "SELECT * FROM suppliers WHERE id=$edit_supplier_id LIMIT 1");
    if ($edit_supplier_result) {
        $edit_supplier = mysqli_fetch_assoc($edit_supplier_result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - L LE JOSE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="inventory-page">
    <div class="inventory-wrapper">
        <!-- Header -->
        <header class="inventory-header">
            <div class="header-top">
                <div class="logo-area">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>L LE JOSE</h1>
                </div>
                <div class="header-actions">
                    <div class="stock-alert-container">
                        <button class="stock-alert-btn" onclick="showStockAlert()" title="Stock Alert">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="lowStockCount" <?= $low_stock_count > 0 ? '' : 'style="display: none;"' ?>><?= $low_stock_count > 99 ? '99+' : $low_stock_count ?></span>
                        </button>
                    </div>
                    <a href="admin_page.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </header>

        <div class="inventory-container">
            <!-- Sidebar -->
            <aside class="inventory-sidebar" id="sidebar">
                <div class="sidebar-header">
                    <h3>Inventory Menu</h3>
                    <button class="close-sidebar" onclick="toggleSidebar()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <nav class="sidebar-menu">
                    <a href="#products" class="menu-item active" data-section="productsSection" onclick="setActiveMenu(event)">
                        <i class="fas fa-boxes"></i>
                        <span>Products</span>
                    </a>
                    <a href="#supplier" class="menu-item" data-section="supplierSection" onclick="setActiveMenu(event)">
                        <i class="fas fa-phone"></i>
                        <span>Supplier Contacts</span>
                    </a>
                    <a href="#valuation" class="menu-item" data-section="valuationSection" onclick="setActiveMenu(event)">
                        <i class="fas fa-chart-pie"></i>
                        <span>Valuation Report</span>
                    </a>
                    <a href="#history" class="menu-item" data-section="historySection" onclick="setActiveMenu(event)">
                        <i class="fas fa-history"></i>
                        <span>Inventory History</span>
                    </a>
                </nav>
                <div class="sidebar-stats">
                    <div class="stat">
                        <span class="stat-label">Total Items</span>
                        <span class="stat-number"><?= $stats['total_items'] ?? 0 ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">In Stock</span>
                        <span class="stat-number"><?= $stats['total_stock'] ?? 0 ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Total Value</span>
                        <span class="stat-number">₱<?= number_format($stats['total_value'] ?? 0, 2) ?></span>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="inventory-main">
                <div id="productsSection" class="page-section">
                    <div class="content-header">
                    <h2>Products Management</h2>
                    <div class="content-actions">
                        <div class="flex border border-gray-300 rounded-lg overflow-hidden bg-white">
                            <button type="button" class="view-btn bg-blue-500 text-white px-3 py-2 flex items-center justify-center min-w-[40px] transition-colors hover:bg-blue-600" id="viewListBtn" title="List view">
                                <i class="fas fa-list"></i>
                            </button>
                            <button type="button" class="view-btn bg-gray-200 text-gray-600 px-3 py-2 flex items-center justify-center min-w-[40px] transition-colors hover:bg-gray-300" id="viewTilesBtn" title="Tiles view">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                        <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                </div>

                <?php if (isset($success_msg)): ?>
                    <div id="successAlert" class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter Bar -->
                <div class="search-filter-bar">
                    <form method="GET" class="search-form">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" id="searchInput" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                            <div class="search-history" id="searchHistory" style="display: none;">
                                <div class="history-header">
                                    <span>Recent searches</span>
                                    <button type="button" onclick="clearSearchHistory()" class="clear-history">Clear</button>
                                </div>
                                <div id="historyList"></div>
                            </div>
                        </div>

                        <select name="sort_by" class="filter-select" onchange="this.form.submit()">
                            <option value="">Sort by...</option>
                            <option value="name_asc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                            <option value="name_desc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                            <option value="sku_asc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'sku_asc' ? 'selected' : '' ?>>SKU A-Z</option>
                            <option value="sku_desc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'sku_desc' ? 'selected' : '' ?>>SKU Z-A</option>
                            <option value="date_desc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                            <option value="date_asc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="price_desc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'price_desc' ? 'selected' : '' ?>>Price High-Low</option>
                            <option value="price_asc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'price_asc' ? 'selected' : '' ?>>Price Low-High</option>
                            <option value="cost_desc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'cost_desc' ? 'selected' : '' ?>>Cost High-Low</option>
                            <option value="cost_asc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'cost_asc' ? 'selected' : '' ?>>Cost Low-High</option>
                            <option value="stock_asc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'stock_asc' ? 'selected' : '' ?>>Stock Low-High</option>
                            <option value="stock_desc" <?= isset($_GET['sort_by']) && $_GET['sort_by'] === 'stock_desc' ? 'selected' : '' ?>>Stock High-Low</option>
                        </select>

                        <?php if ($search || $category_filter || isset($_GET['stock_alert'])): ?>
                            <a href="inventory.php" class="filter-btn clear-btn">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Inventory Table -->
                <div class="table-container" id="listView">
                    <div class="table-actions" id="bulkActions" style="display: none;">
                        <span id="selectedCount">0 selected</span>
                        <button class="btn-danger" onclick="bulkDelete()">Delete Selected</button>
                    </div>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()"></th>
                                <th>Image</th>
                                <th>Item Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Size</th>
                                <th>Price</th>
                                <th>Cost</th>
                                <th>In Stock</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($items) > 0): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr <?= $item['quantity_in_stock'] <= 10 ? 'class="low-stock"' : '' ?>>
                                        <td><input type="checkbox" class="item-checkbox" data-item-id="<?= $item['id'] ?>"></td>
                                        <td>
                                            <?php if (!empty($item['image_path'] ?? '')): ?>
                                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" class="item-thumb">
                                            <?php else: ?>
                                                <span class="no-image">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['sku'] ?? '') ?></td>
                                        <td><span class="category-badge"><?= htmlspecialchars($item['category']) ?></span></td>
                                        <td><?= htmlspecialchars($item['size'] ?? '') ?></td>
                                        <td>₱<?= number_format($item['price'], 2) ?></td>
                                        <td>₱<?= number_format($item['cost'], 2) ?></td>
                                        <td>
                                            <span class="stock-badge <?= $item['quantity_in_stock'] <= 10 ? 'low' : 'normal' ?>">
                                                <?= $item['quantity_in_stock'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($item['date_added'])) ?></td>
                                        <td class="action-buttons">
                                            <button class="btn-action btn-edit" onclick="editItem(<?= $item['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deleteItem(<?= $item['id'] ?>, '<?= addslashes($item['item_name']) ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="no-data">
                                        <i class="fas fa-inbox"></i>
                                        <p>No items found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Inventory Tiles (Grid View) -->
                <div class="tiles-container" id="tilesView" style="display: none;">
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <div class="tile-card <?= $item['quantity_in_stock'] <= 10 ? 'low-stock' : '' ?>">
                                <div class="tile-image">
                                    <?php if (!empty($item['image_path'] ?? '')): ?>
                                        <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                                    <?php else: ?>
                                        <div class="tile-placeholder">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="tile-content">
                                    <h3><?= htmlspecialchars($item['item_name']) ?></h3>
                                    <p class="tile-meta">
                                        <span class="meta-label">SKU:</span> <?= htmlspecialchars($item['sku'] ?? '—') ?>
                                        <span class="meta-label">Category:</span> <?= htmlspecialchars($item['category']) ?>
                                    </p>
                                    <p class="tile-meta">
                                        <span class="meta-label">Size:</span> <?= htmlspecialchars($item['size'] ?? '—') ?>
                                    </p>
                                    <p class="tile-price">₱<?= number_format($item['price'], 2) ?> <span class="tile-cost">(₱<?= number_format($item['cost'], 2) ?>)</span></p>
                                    <p class="tile-stock">
                                        <span class="stock-badge <?= $item['quantity_in_stock'] <= 10 ? 'low' : 'normal' ?>">
                                            <?= $item['quantity_in_stock'] ?> in stock
                                        </span>
                                    </p>
                                    <div class="tile-actions">
                                        <button class="btn-action btn-edit" onclick="editItem(<?= $item['id'] ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-delete" onclick="deleteItem(<?= $item['id'] ?>, '<?= addslashes($item['item_name']) ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-inbox"></i>
                            <p>No items found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div> <!-- end productsSection -->

            <div id="supplierSection" class="page-section" style="display:none;">
                <div class="content-header">
                    <h2>Supplier Contacts</h2>
                </div>

                <div class="supplier-search-bar">
                    <form method="GET" class="supplier-search-form">
                        <input type="hidden" name="tab" value="supplier">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="supplier_search" placeholder="Search suppliers..." value="<?= htmlspecialchars($supplier_search) ?>" autocomplete="off">
                        </div>
                        <div class="supplier-actions-inline">
                            <button type="button" class="btn-primary" onclick="onAddSupplier()">
                                <i class="fas fa-plus"></i> Add Supplier
                            </button>
                            <button type="button" class="btn-danger" onclick="supplierBulkDelete()">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button type="button" class="btn-secondary" onclick="supplierUpdateSelected()">
                                <i class="fas fa-edit"></i> Update
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (isset($success_msg)): ?>
                    <div id="successAlert" class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <div class="table-container">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllSuppliers" onchange="toggleSelectAllSuppliers(this)"></th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Address</th>
                                <th>Phone</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($suppliers) > 0): ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <tr>
                                        <td><input type="checkbox" class="supplier-checkbox" value="<?= $supplier['id'] ?>"></td>
                                        <td><?= htmlspecialchars($supplier['name']) ?></td>
                                        <td><?= htmlspecialchars($supplier['description']) ?></td>
                                        <td><?= htmlspecialchars($supplier['address']) ?></td>
                                        <td><?= htmlspecialchars($supplier['phone_number']) ?></td>
                                        <td><?= htmlspecialchars($supplier['email']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="no-data"><i class="fas fa-inbox"></i><p>No suppliers found</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <hr class="section-divider" />

                <div class="form-section" id="supplierForm" style="display: none;">
                    <h3 id="supplierFormTitle"><?= $edit_supplier ? 'Edit Supplier' : 'Add Supplier' ?></h3>
                    <form method="POST" id="supplierFormElement">
                        <input type="hidden" name="action" id="supplierFormAction" value="<?= $edit_supplier ? 'supplier_update' : 'supplier_add' ?>">
                        <input type="hidden" name="supplier_id" id="supplierIdInput" value="<?= $edit_supplier['id'] ?? '' ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" required value="<?= htmlspecialchars($edit_supplier['name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($edit_supplier['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone_number" value="<?= htmlspecialchars($edit_supplier['phone_number'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="address" value="<?= htmlspecialchars($edit_supplier['address'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description"><?= htmlspecialchars($edit_supplier['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-footer">
                            <button type="submit" class="btn-primary" id="supplierFormSubmit"><?= $edit_supplier ? 'Update' : 'Add' ?></button>
                            <button type="button" class="btn-secondary" onclick="onSupplierCancel()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="valuationSection" class="page-section" style="display:none;">
                <div class="content-header">
                    <h2>Valuation Report</h2>
                    <div class="content-actions">
                        <button type="button" class="btn-primary" onclick="exportProfitMarginCsv()">
                            <i class="fas fa-file-csv"></i> Export to Excel
                        </button>
                    </div>
                </div>
                <div class="table-container highlight-panel">
                    <h3>Summary</h3>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Total Products</th>
                                <th>Total Stock</th>
                                <th>Total Inventory Value</th>
                                <th>Total Retail Value</th>
                                <th>Potential Profit</th>
                                <th>Profit Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= $stats['total_items'] ?? 0 ?></td>
                                <td><?= $stats['total_stock'] ?? 0 ?></td>
                                <td>₱<?= number_format($stats['total_value'] ?? 0, 2) ?></td>
                                <td>₱<?= number_format($stats['total_retail_value'] ?? 0, 2) ?></td>
                                <td>₱<?= number_format($stats['total_profit'] ?? 0, 2) ?></td>
                                <td><?= number_format($stats['profit_margin'] ?? 0, 2) ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-container highlight-panel">
                    <div class="section-header">
                        <h3>Profit Margin Report</h3>
                        <button type="button" class="btn-secondary" onclick="showProfitCharts(event)">
                            <i class="fas fa-chart-bar"></i> View Detailed Report
                        </button>
                    </div>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Cost Value</th>
                                <th>Retail Value</th>
                                <th>Potential Profit</th>
                                <th>Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($valuation_items) > 0): ?>
                                <?php foreach ($valuation_items as $valuation_item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($valuation_item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($valuation_item['category'] ?? 'N/A') ?></td>
                                        <td><?= intval($valuation_item['quantity_in_stock']) ?></td>
                                        <td>₱<?= number_format($valuation_item['inventory_value'], 2) ?></td>
                                        <td>₱<?= number_format($valuation_item['retail_value'], 2) ?></td>
                                        <td>₱<?= number_format($valuation_item['profit_value'], 2) ?></td>
                                        <td><?= number_format($valuation_item['profit_margin'], 2) ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="no-data"><i class="fas fa-inbox"></i><p>No items available for valuation</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-container highlight-panel">
                    <h3>Stock Analysis Report</h3>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Batch Number</th>
                                <th>Date Delivered</th>
                                <th>Batch Expiration Date</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <div class="table-container highlight-panel">
                    <div class="section-header">
                        <h3>Top 10 Best Selling Products</h3>
                        <button type="button" class="btn-secondary" onclick="toggleTopSellingChart()">
                            <i class="fas fa-chart-bar"></i> View Bar Chart
                        </button>
                    </div>
                    <div id="topSellingTable">
                        <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Sales Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($top_selling) > 0): ?>
                                <?php foreach ($top_selling as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['item_name']) ?></td>
                                        <td><?= htmlspecialchars($product['category'] ?? 'N/A') ?></td>
                                        <td><?= intval($product['total_qty_sold']) ?></td>
                                        <td>₱<?= number_format($product['total_sales_value'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="no-data"><i class="fas fa-inbox"></i><p>No sales data available</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <div id="topSellingChart" style="display: none;">
                        <canvas id="topSellingChartCanvas"></canvas>
                    </div>
                </div>

                <div class="table-container highlight-panel">
                    <div class="section-header">
                        <h3>Bottom 10 Selling Products</h3>
                        <button type="button" class="btn-secondary" onclick="toggleBottomSellingChart()">
                            <i class="fas fa-chart-bar"></i> View Bar Chart
                        </button>
                    </div>
                    <div id="bottomSellingTable">
                        <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Sales Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($bottom_selling) > 0): ?>
                                <?php foreach ($bottom_selling as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['item_name']) ?></td>
                                        <td><?= htmlspecialchars($product['category'] ?? 'N/A') ?></td>
                                        <td><?= intval($product['total_qty_sold']) ?></td>
                                        <td>₱<?= number_format($product['total_sales_value'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="no-data"><i class="fas fa-inbox"></i><p>No sales data available</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <div id="bottomSellingChart" style="display: none;">
                        <canvas id="bottomSellingChartCanvas"></canvas>
                    </div>
                </div>
            </div>

            <div id="historySection" class="page-section" style="display:none;">
                <div class="content-header">
                    <h2>Inventory History</h2>
                </div>

                <div class="supplier-search-bar">
                    <form method="GET" class="supplier-search-form">
                        <input type="hidden" name="tab" value="history">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="history_search" placeholder="Search inventory history by item..." value="<?= htmlspecialchars($history_search) ?>" autocomplete="off">
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Reason</th>
                                <th>Changed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($history_items) > 0): ?>
                                <?php foreach ($history_items as $history): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($history['changed_at'])) ?></td>
                                        <td><?= htmlspecialchars($history['item_name']) ?></td>
                                        <td><?= htmlspecialchars($history['category'] ?? 'N/A') ?></td>
                                        <td><?= intval($history['quantity']) ?></td>
                                        <td><?= htmlspecialchars($history['reason'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($history['changed_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="no-data"><i class="fas fa-inbox"></i><p>No history records found</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Item</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
<form method="POST" class="item-form" id="itemForm" enctype="multipart/form-data">
        <input type="hidden" name="action" id="modalAction" value="add">
        <input type="hidden" name="item_id" id="itemId" value="">

        <div class="form-row">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" id="itemName" required>
            </div>
            <div class="form-group">
                <label>Product ID / SKU *</label>
                <input type="text" name="sku" id="itemSku" required>
                <div class="sku-suggestions" id="skuSuggestions" style="display: none;">
                    <small>Suggestions: <span id="skuList"></span></small>
                </div>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category" id="itemCategory" required>
                    <option value="">Select category</option>
                    <option value="Espresso">Espresso</option>
                    <option value="Non-Coffee">Non-Coffee</option>
                    <option value="Matcha">Matcha</option>
                    <option value="Frappe">Frappe</option>
                    <option value="Mini Croffle">Mini Croffle</option>
                    <option value="Sweets">Sweets</option>
                    <option value="Fruit Soda">Fruit Soda</option>
                    <option value="Waffle">Waffle</option>
                </select>
            </div>
            <div class="form-group">
                <label>Size</label>
                <input type="text" name="size" id="itemSize" placeholder="e.g. Small, Large, 500ml">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Price (₱) *</label>
                <input type="number" name="price" id="itemPrice" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Cost (₱) *</label>
                <input type="number" name="cost" id="itemCost" step="0.01" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Quantity in Stock *</label>
                <input type="number" name="quantity" id="itemQuantity" min="0" required>
            </div>
            <div class="form-group">
                <label>Image</label>
                <input type="file" name="image" id="itemImage" accept="image/*">
                <img id="itemImagePreview" src="" alt="Image preview" style="display:none; max-width: 100px; margin-top: 10px; border-radius: 6px;" />
            </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Save Confirmation Modal -->
    <div class="modal" id="saveConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Save</h3>
                <button class="modal-close" onclick="closeSaveConfirmModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to save this item?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeSaveConfirmModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="confirmSave()">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" id="deleteItemId" value="">
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-danger" id="deleteConfirmBtn">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Alert Modal -->
    <div class="modal" id="stockAlertModal">
        <div class="modal-content modal-stock-alert">
            <div class="modal-header">
                <h3>Stock Alert</h3>
                <button class="modal-close" onclick="closeStockAlertModal()">&times;</button>
            </div>
            <div class="stock-alert-content">
                <p>Items running low on stock:</p>
                <div class="low-stock-list" id="lowStockList">
                    <!-- Low stock items will be populated here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Undo Toast -->
    <div id="undoToast" class="toast" role="status" aria-live="polite">
        <span class="toast-message">Item deleted</span>
        <button type="button" class="toast-undo" aria-label="Undo delete">Undo</button>
    </div>

    <!-- Profit Charts Modal -->
    <div class="modal" id="profitChartsModal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3>Profit Margin Detailed Report</h3>
                <button class="modal-close" onclick="closeProfitChartsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="chart-tabs">
                    <button class="tab-btn active" onclick="showChartTab('bar')">Bar Chart</button>
                    <button class="tab-btn" onclick="showChartTab('pie')">Pie Chart</button>
                </div>
                <div id="barChartTab" class="chart-tab">
                    <canvas id="profitBarChart"></canvas>
                </div>
                <div id="pieChartTab" class="chart-tab" style="display: none;">
                    <canvas id="profitPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Debug: Check if script is loading
        console.log('Inventory script loaded');
        const profitMarginData = <?= json_encode($valuation_items, JSON_UNESCAPED_UNICODE) ?>;
        const topSellingData = <?= json_encode($top_selling, JSON_UNESCAPED_UNICODE) ?>;
        const bottomSellingData = <?= json_encode($bottom_selling, JSON_UNESCAPED_UNICODE) ?>;

        // Undo toast state (delay actual delete to allow undo)
        let _undoTimer = null;
        let _pendingDeleteForm = null;
        let _lastModalOpenAt = 0;

        function recordModalOpen() {
            _lastModalOpenAt = Date.now();
        }

        function showUndoToast(itemName) {
            const toast = document.getElementById('undoToast');
            const message = toast?.querySelector('.toast-message');
            const undoBtn = toast?.querySelector('.toast-undo');
            if (!toast || !message || !undoBtn) return;

            message.textContent = `"${itemName}" deleted`;
            toast.classList.add('show');

            if (_undoTimer) {
                clearTimeout(_undoTimer);
            }

            _undoTimer = setTimeout(() => {
                toast.classList.remove('show');
                _undoTimer = null;
                if (_pendingDeleteForm) {
                    _pendingDeleteForm.submit();
                    _pendingDeleteForm = null;
                }
            }, 6000);

            undoBtn.onclick = () => {
                if (_undoTimer) {
                    clearTimeout(_undoTimer);
                    _undoTimer = null;
                }
                toast.classList.remove('show');
                _pendingDeleteForm = null;
                const deleteBtn = document.getElementById('deleteConfirmBtn');
                if (deleteBtn) deleteBtn.disabled = false;
            };
        }

        function exportProfitMarginCsv() {
            if (!Array.isArray(profitMarginData) || profitMarginData.length === 0) {
                alert('No profit margin data available to export.');
                return;
            }

            const headers = ['Item', 'Category', 'Stock', 'Cost Value', 'Retail Value', 'Potential Profit', 'Margin (%)'];
            const rows = profitMarginData.map(item => [
                item.item_name || '',
                item.category || '',
                item.quantity_in_stock ?? 0,
                Number(item.inventory_value || 0).toFixed(2),
                Number(item.retail_value || 0).toFixed(2),
                Number(item.profit_value || 0).toFixed(2),
                Number(item.profit_margin || 0).toFixed(2)
            ]);

            const csvContent = [headers, ...rows].map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'profit_margin_details.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function toggleTopSellingChart() {
            const table = document.getElementById('topSellingTable');
            const chart = document.getElementById('topSellingChart');
            const btn = event.target.closest('button');
            const icon = btn.querySelector('i');

            if (chart.style.display === 'none') {
                chart.style.display = 'block';
                table.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-table"></i> View Table';
                renderTopSellingChart();
            } else {
                chart.style.display = 'none';
                table.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-chart-bar"></i> View Bar Chart';
            }
        }

        function toggleBottomSellingChart() {
            const table = document.getElementById('bottomSellingTable');
            const chart = document.getElementById('bottomSellingChart');
            const btn = event.target.closest('button');
            const icon = btn.querySelector('i');

            if (chart.style.display === 'none') {
                chart.style.display = 'block';
                table.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-table"></i> View Table';
                renderBottomSellingChart();
            } else {
                chart.style.display = 'none';
                table.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-chart-bar"></i> View Bar Chart';
            }
        }

        function renderTopSellingChart() {
            const ctx = document.getElementById('topSellingChartCanvas').getContext('2d');
            const labels = topSellingData.map(item => item.item_name);
            const data = topSellingData.map(item => parseFloat(item.total_sales_value));

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales Value (₱)',
                        data: data,
                        backgroundColor: 'rgba(102, 126, 234, 0.6)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Top 10 Best Selling Products by Sales Value'
                        }
                    }
                }
            });
        }

        function renderBottomSellingChart() {
            const ctx = document.getElementById('bottomSellingChartCanvas').getContext('2d');
            const labels = bottomSellingData.map(item => item.item_name);
            const data = bottomSellingData.map(item => parseFloat(item.total_sales_value));

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales Value (₱)',
                        data: data,
                        backgroundColor: 'rgba(231, 76, 60, 0.6)',
                        borderColor: 'rgba(231, 76, 60, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Bottom 10 Selling Products by Sales Value'
                        }
                    }
                }
            });
        }

        function showProfitCharts(event) {
            event.stopPropagation();
            const modal = document.getElementById('profitChartsModal');
            modal.style.display = 'block';
            modal.style.opacity = '1';
            modal.style.transition = 'opacity 0.25s ease';
            showChartTab('bar');
        }

        function closeProfitChartsModal() {
            const modal = document.getElementById('profitChartsModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 250);
        }

        function showChartTab(tab) {
            const barTab = document.getElementById('barChartTab');
            const pieTab = document.getElementById('pieChartTab');
            const barBtn = document.querySelector('.tab-btn[onclick*="bar"]');
            const pieBtn = document.querySelector('.tab-btn[onclick*="pie"]');

            if (tab === 'bar') {
                barTab.style.display = 'block';
                pieTab.style.display = 'none';
                barBtn.classList.add('active');
                pieBtn.classList.remove('active');
                renderProfitBarChart();
            } else {
                barTab.style.display = 'none';
                pieTab.style.display = 'block';
                barBtn.classList.remove('active');
                pieBtn.classList.add('active');
                renderProfitPieChart();
            }
        }

        function renderProfitBarChart() {
            const ctx = document.getElementById('profitBarChart').getContext('2d');
            const labels = profitMarginData.map(item => item.item_name);
            const data = profitMarginData.map(item => parseFloat(item.profit_value));

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Profit Value (₱)',
                        data: data,
                        backgroundColor: 'rgba(46, 204, 113, 0.6)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Profit Values by Product'
                        }
                    }
                }
            });
        }

        function renderProfitPieChart() {
            const ctx = document.getElementById('profitPieChart').getContext('2d');
            const labels = profitMarginData.map(item => item.item_name);
            const data = profitMarginData.map(item => parseFloat(item.profit_value));

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 205, 86, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(153, 102, 255, 0.6)',
                            'rgba(255, 159, 64, 0.6)',
                            'rgba(199, 199, 199, 0.6)',
                            'rgba(83, 102, 255, 0.6)',
                            'rgba(255, 99, 255, 0.6)',
                            'rgba(99, 255, 132, 0.6)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        title: {
                            display: true,
                            text: 'Profit Share by Product'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return context.label + ': ₱' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        function showSection(sectionId) {
            const sections = document.querySelectorAll('.page-section');
            sections.forEach(section => {
                section.style.display = section.id === sectionId ? 'block' : 'none';
            });
        }

        function setActiveMenu(e) {
            e.preventDefault();
            const item = e.currentTarget.closest('.menu-item');
            if (!item) return;

            document.querySelectorAll('.menu-item').forEach(menuItem => menuItem.classList.remove('active'));
            item.classList.add('active');

            const sectionId = item.getAttribute('data-section');
            if (sectionId) {
                showSection(sectionId);
                history.replaceState(null, '', '#' + sectionId.replace('Section', '').toLowerCase());
            }
        }

        function openAddModal() {
            recordModalOpen();
            document.getElementById('modalAction').value = 'add';
            document.getElementById('modalTitle').textContent = 'Add New Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemId').value = '';
            document.getElementById('itemImage').value = '';
            const preview = document.getElementById('itemImagePreview');
            preview.src = '';
            preview.style.display = 'none';
            const modal = document.getElementById('itemModal');
            if (modal) {
                modal.style.display = 'block';
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.25s ease';
                modal.style.animation = 'slideIn 0.3s ease';
            }
        }

        // Show preview when selecting a new image (if element exists)
        const itemImageEl = document.getElementById('itemImage');
        const itemImagePreview = document.getElementById('itemImagePreview');
        if (itemImageEl && itemImagePreview) {
            itemImageEl.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    itemImagePreview.src = URL.createObjectURL(file);
                    itemImagePreview.style.display = 'block';
                } else {
                    itemImagePreview.src = '';
                    itemImagePreview.style.display = 'none';
                }
            });
        }

        // Generate SKU suggestions based on category
        const itemCategoryEl = document.getElementById('itemCategory');
        if (itemCategoryEl) {
            itemCategoryEl.addEventListener('change', function() {
                const category = this.value;
                if (category) {
                    generateSkuSuggestions(category);
                } else {
                    const skuSuggestions = document.getElementById('skuSuggestions');
                    if (skuSuggestions) skuSuggestions.style.display = 'none';
                }
            });
        }

        function generateSkuSuggestions(category) {
            const prefix = category.substring(0, 3).toUpperCase();
            const suggestions = [];
            for (let i = 1; i <= 5; i++) {
                suggestions.push(`${prefix}-${String(i).padStart(3, '0')}`);
            }
            document.getElementById('skuList').textContent = suggestions.join(', ');
            document.getElementById('skuSuggestions').style.display = 'block';
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateBulkActions();
        }

        function toggleSelectAllSuppliers(source) {
            const supplierCheckboxes = document.querySelectorAll('.supplier-checkbox');
            supplierCheckboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }

        function supplierBulkDelete() {
            const checked = Array.from(document.querySelectorAll('.supplier-checkbox:checked'));
            if (checked.length === 0) {
                alert('Please select at least one supplier to delete.');
                return;
            }
            if (!confirm(`Are you sure you want to delete ${checked.length} supplier(s)?`)) {
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            checked.forEach(chk => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'supplier_delete_ids[]';
                input.value = chk.value;
                form.appendChild(input);
            });
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'supplier_bulk_delete';
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }

        function supplierUpdateSelected() {
            const checked = Array.from(document.querySelectorAll('.supplier-checkbox:checked'));
            if (checked.length !== 1) {
                alert('Please select exactly one supplier to update.');
                return;
            }
            window.location.href = `inventory.php?edit_supplier=${checked[0].value}#supplier`;
        }

        function onAddSupplier() {
            const supplierForm = document.getElementById('supplierForm');
            const formTitle = document.getElementById('supplierFormTitle');
            const actionInput = document.getElementById('supplierFormAction');
            const idInput = document.getElementById('supplierIdInput');

            if (supplierForm) {
                supplierForm.style.display = 'block';
            }
            if (formTitle) {
                formTitle.textContent = 'Add Supplier';
            }
            if (actionInput) {
                actionInput.value = 'supplier_add';
            }
            if (idInput) {
                idInput.value = '';
            }

            const fields = supplierForm.querySelectorAll('input[type=text], input[type=email], textarea');
            fields.forEach(field => {
                if (field.name !== 'action' && field.name !== 'supplier_id') {
                    field.value = '';
                }
            });
            supplierForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function onSupplierCancel() {
            const supplierForm = document.getElementById('supplierForm');
            if (supplierForm) {
                supplierForm.style.display = 'none';
            }
            window.location.href = 'inventory.php?tab=supplier';
        }

        function scrollToSupplierForm() {
            const supplierForm = document.getElementById('supplierForm');
            if (supplierForm) {
                supplierForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');

            if (checkedBoxes.length > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.textContent = `${checkedBoxes.length} selected`;
            } else {
                bulkActions.style.display = 'none';
            }
        }

        // Search history functionality
        const searchInput = document.getElementById('searchInput');
        const searchHistory = document.getElementById('searchHistory');
        const historyList = document.getElementById('historyList');

        function loadSearchHistory() {
            if (!historyList) return;
            const history = JSON.parse(localStorage.getItem('searchHistory') || '[]');
            historyList.innerHTML = '';
            if (history.length === 0) {
                historyList.innerHTML = '<div class="no-history">No recent searches</div>';
                return;
            }
            history.forEach(term => {
                const item = document.createElement('div');
                item.className = 'history-item';
                item.textContent = term;
                item.onclick = () => {
                    if (!searchInput) return;
                    searchInput.value = term;
                    if (searchHistory) searchHistory.style.display = 'none';
                    if (searchInput.form) searchInput.form.submit();
                };
                historyList.appendChild(item);
            });
        }

        function addToSearchHistory(term) {
            if (!term || !term.trim()) return;
            let history = JSON.parse(localStorage.getItem('searchHistory') || '[]');
            history = history.filter(h => h !== term); // Remove duplicates
            history.unshift(term); // Add to beginning
            history = history.slice(0, 10); // Keep only 10 items
            localStorage.setItem('searchHistory', JSON.stringify(history));
        }

        function clearSearchHistory() {
            localStorage.removeItem('searchHistory');
            loadSearchHistory();
        }

        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                loadSearchHistory();
                if (searchHistory) searchHistory.style.display = 'block';
            });

            searchInput.addEventListener('blur', function() {
                setTimeout(() => {
                    if (searchHistory) searchHistory.style.display = 'none';
                }, 150);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    addToSearchHistory(this.value);
                }
            });
        }

        // Auto-close modal after form submission (shows confirmation first)
        let _ignoreSaveConfirm = false;
        const itemForm = document.getElementById('itemForm');
        if (itemForm) {
            itemForm.addEventListener('submit', function(e) {
                if (_ignoreSaveConfirm) {
                    // Allow actual submission once confirmed
                    _ignoreSaveConfirm = false;
                    return;
                }
                e.preventDefault();
                showSaveConfirmModal();
            });
        }

        const deleteForm = document.getElementById('deleteForm');
        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const deleteBtn = document.getElementById('deleteConfirmBtn');
                if (deleteBtn) deleteBtn.disabled = true;

                closeDeleteModal();

                _pendingDeleteForm = deleteForm;
                const deleteNameEl = document.getElementById('deleteItemName');
                const itemName = deleteNameEl ? deleteNameEl.textContent : 'Item';
                showUndoToast(itemName);
            });
        }

        function confirmSave() {
            closeSaveConfirmModal();
            // Close the add/edit modal with animation
            closeModal();
            _ignoreSaveConfirm = true;
            setTimeout(() => {
                const form = document.getElementById('itemForm');
                if (form) form.submit();
            }, 320);
        }

        function editItem(itemId) {
            window.location.href = 'inventory.php?edit=' + itemId;
        }

        function deleteItem(itemId, itemName) {
            recordModalOpen();

            const deleteIdEl = document.getElementById('deleteItemId');
            const deleteNameEl = document.getElementById('deleteItemName');
            const deleteModal = document.getElementById('deleteModal');
            const deleteBtn = document.getElementById('deleteConfirmBtn');

            if (deleteIdEl) deleteIdEl.value = itemId;
            if (deleteNameEl) deleteNameEl.textContent = itemName;
            if (deleteBtn) deleteBtn.disabled = false;

            // If an undo toast was active, cancel it since user is making a new decision
            if (_undoTimer) {
                clearTimeout(_undoTimer);
                _undoTimer = null;
                const toast = document.getElementById('undoToast');
                if (toast) toast.classList.remove('show');
            }

            if (deleteModal) {
                deleteModal.style.display = 'block';
                deleteModal.style.opacity = '1';
                deleteModal.style.transition = 'opacity 0.25s ease';
            }
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            if (!modal) return;
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 250);
        }

        function showSaveConfirmModal() {
            recordModalOpen();
            const modal = document.getElementById('saveConfirmModal');
            if (modal) {
                modal.style.display = 'block';
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.25s ease';
            }
        }

        function closeSaveConfirmModal() {
            const modal = document.getElementById('saveConfirmModal');
            if (!modal) return;
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 250);
        }

        function showStockAlert() {
            recordModalOpen();
            const lowStockItems = <?php echo json_encode(array_filter($items, function($item) { return $item['quantity_in_stock'] <= 10; })); ?>;
            const listContainer = document.getElementById('lowStockList');

            if (!listContainer) return;

            if (!Array.isArray(lowStockItems) || lowStockItems.length === 0) {
                listContainer.innerHTML = '<p>No items are running low on stock.</p>';
            } else {
                // Suggested reorder date is 7 days from today
                const today = new Date();
                const reorderBy = new Date();
                reorderBy.setDate(today.getDate() + 7);

                // Build list items safely without using template literals in a way that can break if text contains backticks
                const ul = document.createElement('ul');
                ul.className = 'stock-alert-list';

                lowStockItems.forEach(item => {
                    const daysRemaining = Number(item.quantity_in_stock) || 0;
                    const weeksRemaining = Math.ceil(daysRemaining / 7);
                    const daysLabel = daysRemaining === 1 ? 'day' : 'days';
                    const weeksLabel = weeksRemaining === 1 ? 'week' : 'weeks';

                    const li = document.createElement('li');
                    li.className = 'stock-alert-item';

                    const title = document.createElement('strong');
                    title.textContent = item.item_name || 'Unnamed Item';
                    li.appendChild(title);

                    const skuSpan = document.createElement('span');
                    skuSpan.textContent = ` (${item.sku || 'No SKU'})`;
                    li.appendChild(skuSpan);

                    const stockLine = document.createElement('div');
                    stockLine.innerHTML = `Current stock: <span class="stock-badge ${item.quantity_in_stock <= 5 ? 'low' : 'normal'}">${item.quantity_in_stock}</span>`;
                    li.appendChild(stockLine);

                    const estimateLine = document.createElement('div');
                    estimateLine.textContent = `Estimated to last: ${daysRemaining} ${daysLabel} (~${weeksRemaining} ${weeksLabel})`;
                    li.appendChild(estimateLine);

                    const reorderLine = document.createElement('div');
                    reorderLine.textContent = `Suggested reorder by: ${reorderBy.toLocaleDateString()}`;
                    li.appendChild(reorderLine);

                    ul.appendChild(li);
                });

                listContainer.innerHTML = '';
                listContainer.appendChild(ul);
            }

            const modal = document.getElementById('stockAlertModal');
            if (modal) {
                modal.style.transition = 'opacity 0.25s ease';
                modal.style.opacity = '1';
                modal.style.display = 'block';
            }
        }

        function closeStockAlertModal() {
            const modal = document.getElementById('stockAlertModal');
            if (!modal) return;

            modal.style.transition = 'opacity 0.25s ease';
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 250);
        }

        function bulkDelete() {
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            if (checkedBoxes.length === 0) return;

            const itemIds = Array.from(checkedBoxes).map(cb => cb.dataset.itemId);
            if (confirm(`Are you sure you want to delete ${checkedBoxes.length} selected items?`)) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                itemIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete';
                form.appendChild(actionInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal() {
            const modal = document.getElementById('itemModal');
            if (!modal) return;

            modal.style.animation = 'slideOut 0.3s ease';
            modal.style.opacity = '0';

            setTimeout(() => {
                modal.style.display = 'none';
                modal.style.animation = '';
                modal.style.opacity = '';
            }, 300);
        }

        function toggleInventoryView(mode) {
            const listView = document.getElementById('listView');
            const tilesView = document.getElementById('tilesView');
            const listBtn = document.getElementById('viewListBtn');
            const tilesBtn = document.getElementById('viewTilesBtn');

            if (mode === 'tiles') {
                if (listView) listView.style.display = 'none';
                if (tilesView) tilesView.style.display = 'block';
                // Update button styles
                listBtn.className = 'view-btn bg-gray-200 text-gray-600 px-3 py-2 flex items-center justify-center min-w-[40px] transition-colors hover:bg-gray-300';
                tilesBtn.className = 'view-btn bg-blue-500 text-white px-3 py-2 flex items-center justify-center min-w-[40px] transition-colors hover:bg-blue-600';
            } else {
                if (listView) listView.style.display = 'block';
                if (tilesView) tilesView.style.display = 'none';
                // Update button styles
                listBtn.className = 'view-btn bg-blue-500 text-white px-3 py-2 flex items-center justify-center min-w-[40px] transition-colors hover:bg-blue-600';
                tilesBtn.className = 'view-btn bg-gray-200 text-gray-600 px-3 py-2 flex items-center justify-center min-w-[40px] transition-colors hover:bg-gray-300';
            }

            localStorage.setItem('inventoryViewMode', mode);
        }

        // Make function globally available
        window.toggleInventoryView = toggleInventoryView;

        function applySavedViewMode() {
            const saved = localStorage.getItem('inventoryViewMode') || 'list';
            toggleInventoryView(saved);
        }

        // If a save just happened, auto-close any open modals and fade the success message
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                // Ensure modal(s) are closed
                closeModal();
                closeSaveConfirmModal();

                // Fade out the message after a moment
                setTimeout(() => {
                    successAlert.classList.add('fade-out');
                }, 1800);
                setTimeout(() => {
                    if (successAlert.parentNode) successAlert.parentNode.removeChild(successAlert);
                }, 2400);
            }
        });

        function loadActiveSection() {
            let targetSection = 'productsSection';

            const hash = window.location.hash.substring(1);
            if (['products', 'supplier', 'valuation', 'history'].includes(hash)) {
                targetSection = hash + 'Section';
            } else {
                const params = new URLSearchParams(window.location.search);
                const tab = params.get('tab');
                if (['products', 'supplier', 'valuation', 'history'].includes(tab)) {
                    targetSection = tab + 'Section';
                }
            }

            const item = document.querySelector(`.menu-item[data-section="${targetSection}"]`);
            if (item) {
                document.querySelectorAll('.menu-item').forEach(menuItem => menuItem.classList.remove('active'));
                item.classList.add('active');
            }

            showSection(targetSection);
        }

        function setSupplierFormInitialState() {
            const supplierForm = document.getElementById('supplierForm');
            if (!supplierForm) return;
            const isEditing = <?= $edit_supplier ? 'true' : 'false' ?>;
            supplierForm.style.display = isEditing ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Apply saved view mode
            applySavedViewMode();
            loadActiveSection();
            setSupplierFormInitialState();
        });

        // Attach onclick handlers
        document.getElementById('viewListBtn').onclick = function() {
            toggleInventoryView('list');
        };
        document.getElementById('viewTilesBtn').onclick = function() {
            toggleInventoryView('tiles');
        };

        window.onclick = function(event) {
            const modal = document.getElementById('itemModal');
            const deleteModal = document.getElementById('deleteModal');
            const saveConfirmModal = document.getElementById('saveConfirmModal');
            const stockAlertModal = document.getElementById('stockAlertModal');
            const profitChartsModal = document.getElementById('profitChartsModal');

            const clickedInsideModal = event.target.closest('.modal-content');

            // Avoid closing immediately after opening (click comes from the button itself)
            if (Date.now() - _lastModalOpenAt < 250) return;

            // Close modals when clicking outside their content area
            if (!clickedInsideModal) {
                if (modal && modal.style.display === 'block') closeModal();
                if (deleteModal && deleteModal.style.display === 'block') closeDeleteModal();
                if (saveConfirmModal && saveConfirmModal.style.display === 'block') closeSaveConfirmModal();
                if (stockAlertModal && stockAlertModal.style.display === 'block') closeStockAlertModal();
                if (profitChartsModal && profitChartsModal.style.display === 'block') closeProfitChartsModal();
            }
        }

        // Populate edit form if editing an item
        <?php if ($edit_item && !isset($success_msg)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                recordModalOpen();
                document.getElementById('modalAction').value = 'update';
                document.getElementById('modalTitle').textContent = 'Edit Item';
                document.getElementById('itemId').value = '<?= $edit_item['id'] ?>';
                document.getElementById('itemName').value = '<?= addslashes($edit_item['item_name']) ?>';
                document.getElementById('itemSku').value = '<?= addslashes($edit_item['sku'] ?? '') ?>';
                document.getElementById('itemCategory').value = '<?= $edit_item['category'] ?>';
                document.getElementById('itemSize').value = '<?= addslashes($edit_item['size'] ?? '') ?>';
                document.getElementById('itemPrice').value = '<?= $edit_item['price'] ?>';
                document.getElementById('itemCost').value = '<?= $edit_item['cost'] ?>';
                document.getElementById('itemQuantity').value = '<?= $edit_item['quantity_in_stock'] ?>';

                const preview = document.getElementById('itemImagePreview');
                const imagePath = '<?= addslashes($edit_item['image_path'] ?? '') ?>';
                if (imagePath) {
                    preview.src = imagePath;
                    preview.style.display = 'block';
                } else {
                    preview.src = '';
                    preview.style.display = 'none';
                }

                const modal = document.getElementById('itemModal');
                if (modal) {
                    modal.style.display = 'block';
                    modal.style.opacity = '1';
                    modal.style.transition = 'opacity 0.25s ease';
                    modal.style.animation = 'slideIn 0.3s ease';
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>
