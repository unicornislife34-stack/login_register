<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

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

        $sql = "INSERT INTO inventory (item_name, sku, category, price, cost, quantity_in_stock, size, image_path, date_added) 
                VALUES ('$item_name', '$sku', '$category', $price, $cost, $quantity, $size_sql, $image_sql, NOW())";

        if (mysqli_query($conn, $sql)) {
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

        $sql = "UPDATE inventory SET item_name='$item_name', sku='$sku', category='$category', price=$price, cost=$cost, quantity_in_stock=$quantity, size=$size_sql, image_path=$image_sql 
                WHERE id=$item_id";

        if (mysqli_query($conn, $sql)) {
            $success_msg = "Item updated successfully!";
        } else {
            $error_msg = "Error updating item: " . mysqli_error($conn);
        }
    }

    if ($action === 'delete') {
        $item_id = intval($_POST['item_id']);

        $existing_image_path = null;
        $existing_result = mysqli_query($conn, "SELECT image_path FROM inventory WHERE id=$item_id");
        if ($existing_result && $row = mysqli_fetch_assoc($existing_result)) {
            $existing_image_path = $row['image_path'];
        }
        if ($existing_image_path && file_exists($existing_image_path)) {
            @unlink($existing_image_path);
        }

        $sql = "DELETE FROM inventory WHERE id=$item_id";

        if (mysqli_query($conn, $sql)) {
            $success_msg = "Item deleted successfully!";
        } else {
            $error_msg = "Error deleting item: " . mysqli_error($conn);
        }
    }

    if ($action === 'bulk_delete') {
        $delete_ids = $_POST['delete_ids'] ?? [];
        if (!empty($delete_ids)) {
            $ids_string = implode(',', array_map('intval', $delete_ids));

            // Delete associated images
            $image_result = mysqli_query($conn, "SELECT image_path FROM inventory WHERE id IN ($ids_string)");
            while ($row = mysqli_fetch_assoc($image_result)) {
                if ($row['image_path'] && file_exists($row['image_path'])) {
                    @unlink($row['image_path']);
                }
            }

            $sql = "DELETE FROM inventory WHERE id IN ($ids_string)";

            if (mysqli_query($conn, $sql)) {
                $success_msg = count($delete_ids) . " items deleted successfully!";
            } else {
                $error_msg = "Error deleting items: " . mysqli_error($conn);
            }
        }
    }
}

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

// Get inventory stats
$stats_result = mysqli_query($conn, "SELECT COUNT(*) as total_items, SUM(quantity_in_stock) as total_stock, SUM(price * quantity_in_stock) as total_value FROM inventory");
$stats = mysqli_fetch_assoc($stats_result);

$low_stock_count = 0;
foreach ($items as $item) {
    if ($item['quantity_in_stock'] <= 10) {
        $low_stock_count++;
    }
}

// Prepare edit item data if requested (pre-fill the modal form)
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = mysqli_query($conn, "SELECT * FROM inventory WHERE id=$edit_id LIMIT 1");
    if ($edit_result) {
        $edit_item = mysqli_fetch_assoc($edit_result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - L LE JOSE</title>
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
                    <a href="#products" class="menu-item active" onclick="setActiveMenu(event)">
                        <i class="fas fa-boxes"></i>
                        <span>Products</span>
                    </a>
                    <a href="#supplier" class="menu-item" onclick="setActiveMenu(event)">
                        <i class="fas fa-phone"></i>
                        <span>Supplier Contacts</span>
                    </a>
                    <a href="#valuation" class="menu-item" onclick="setActiveMenu(event)">
                        <i class="fas fa-chart-pie"></i>
                        <span>Valuation Report</span>
                    </a>
                    <a href="#history" class="menu-item" onclick="setActiveMenu(event)">
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
                <div class="content-header">
                    <h2>Products Management</h2>
                    <div class="content-actions">
                        <div class="view-toggle" role="group" aria-label="View toggle">
                            <button type="button" class="view-btn active" id="viewListBtn" title="List view">
                                <i class="fas fa-list"></i>
                            </button>
                            <button type="button" class="view-btn" id="viewTilesBtn" title="Tiles view">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                        <button class="btn-primary" onclick="openAddModal()">
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

    <script src="script.js"></script>
    <script>
        // Debug: Check if script is loading
        console.log('Inventory script loaded');

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

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        function setActiveMenu(e) {
            e.preventDefault();
            document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
            e.target.closest('.menu-item').classList.add('active');
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
                if (listBtn) listBtn.classList.remove('active');
                if (tilesBtn) tilesBtn.classList.add('active');
            } else {
                if (listView) listView.style.display = 'block';
                if (tilesView) tilesView.style.display = 'none';
                if (listBtn) listBtn.classList.add('active');
                if (tilesBtn) tilesBtn.classList.remove('active');
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

        document.addEventListener('DOMContentLoaded', function() {
            // Apply saved view mode
            applySavedViewMode();
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

            const clickedInsideModal = event.target.closest('.modal-content');

            // Avoid closing immediately after opening (click comes from the button itself)
            if (Date.now() - _lastModalOpenAt < 250) return;

            // Close modals when clicking outside their content area
            if (!clickedInsideModal) {
                if (modal && modal.style.display === 'block') closeModal();
                if (deleteModal && deleteModal.style.display === 'block') closeDeleteModal();
                if (saveConfirmModal && saveConfirmModal.style.display === 'block') closeSaveConfirmModal();
                if (stockAlertModal && stockAlertModal.style.display === 'block') closeStockAlertModal();
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
