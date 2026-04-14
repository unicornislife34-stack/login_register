<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Ensure orders/tickets tables exist so checkout can persist sales
$createOrdersTable = "CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user VARCHAR(100),
    total_amount DECIMAL(12,2) NOT NULL,
    ticket_number VARCHAR(20) UNIQUE,
    payment_method VARCHAR(20) DEFAULT 'cash',
    payment_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $createOrdersTable);

// Ensure the orders table has expected columns for payments/tickets
$requiredOrderCols = [
    'ticket_number' => "ALTER TABLE orders ADD COLUMN ticket_number VARCHAR(20) UNIQUE",
    'payment_method' => "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'cash'",
    'payment_details' => "ALTER TABLE orders ADD COLUMN payment_details TEXT"
];
foreach ($requiredOrderCols as $col => $sql) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE '$col'");
    if ($res && mysqli_num_rows($res) === 0) {
        mysqli_query($conn, $sql);
    }
}

$createOrderItemsTable = "CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    inventory_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    modifiers TEXT,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";
mysqli_query($conn, $createOrderItemsTable);

// Checkout endpoint (AJAX)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'checkout'
) {
    header('Content-Type: application/json');

    $order = json_decode($_POST['order'] ?? '[]', true);
    if (!$order || !is_array($order)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order data.']);
        exit;
    }

    $insufficient = [];
    foreach ($order as $line) {
        $id = intval($line['id'] ?? 0);
        $qty = intval($line['qty'] ?? 0);
        if ($id <= 0 || $qty <= 0) {
            continue;
        }

        $res = mysqli_query($conn, "SELECT quantity_in_stock FROM inventory WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $available = $row ? intval($row['quantity_in_stock']) : 0;
        if ($qty > $available) {
            $insufficient[] = ['id' => $id, 'available' => $available];
        }
    }

    if ($insufficient) {
        echo json_encode(['success' => false, 'message' => 'Some items are out of stock.', 'insufficient' => $insufficient]);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $total = 0;
        foreach ($order as $line) {
            $total += floatval($line['price'] ?? 0) * intval($line['qty'] ?? 0);
        }

        $username = $_SESSION['username'] ?? 'guest';
        $ticketNumber = $_POST['ticket_number'] ?? 'T' . time();
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $paymentDetails = json_decode($_POST['payment_details'] ?? '[]', true);
        if (!is_array($paymentDetails)) {
            $paymentDetails = [];
        }

        $stmt = $conn->prepare('INSERT INTO orders (user, total_amount, ticket_number, payment_method, payment_details) VALUES (?, ?, ?, ?, ?)');
        $paymentDetailsJson = json_encode($paymentDetails);
        $stmt->bind_param('sdsss', $username, $total, $ticketNumber, $paymentMethod, $paymentDetailsJson);
        $stmt->execute();
        $orderId = $conn->insert_id;
        $stmt->close();

        $stmtItem = $conn->prepare('INSERT INTO order_items (order_id, inventory_id, item_name, price, qty, modifiers, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($order as $line) {
            $inventoryId = intval($line['id'] ?? 0);
            $qty = intval($line['qty'] ?? 0);
            if ($inventoryId <= 0 || $qty <= 0) {
                continue;
            }
            $name = $conn->real_escape_string($line['item_name'] ?? '');
            $price = floatval($line['price'] ?? 0);
            $modifiers = json_encode($line['modifiers'] ?? []);
            $subtotal = $price * $qty;

            $stmtItem->bind_param('iisddsd', $orderId, $inventoryId, $name, $price, $qty, $modifiers, $subtotal);
            $stmtItem->execute();

            $stmtUpdate = $conn->prepare('UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? WHERE id = ? AND quantity_in_stock >= ?');
            $stmtUpdate->bind_param('iii', $qty, $inventoryId, $qty);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
        $stmtItem->close();

        mysqli_commit($conn);

        echo json_encode(['success' => true, 'orderId' => $orderId]);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Menu now reads from inventory table - editing disabled to prevent duplication
// All inventory management should be done through the Inventory page
/*
    Menu editing is disabled here - inventory is managed on the Inventory page.
    The POST handler is intentionally commented out to prevent duplication.
*/

// Get all menu items
$query = "SELECT id, item_name, category, price, cost, quantity_in_stock as stock_quantity, size as sizes, sku as product_sku, image_path FROM inventory ORDER BY date_added DESC";
$result = mysqli_query($conn, $query);
$menu_items = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get unique categories
$category_result = mysqli_query($conn, "SELECT DISTINCT category FROM inventory ORDER BY category");
$categories = mysqli_fetch_all($category_result, MYSQLI_ASSOC);

// Get menu item for editing if specified
$edit_item = null;
if (isset($_GET['edit'])) {
    $item_id = intval($_GET['edit']);
    $edit_result = mysqli_query($conn, "SELECT id, item_name, category, price, cost, quantity_in_stock as stock_quantity, size as sizes, sku as product_sku, image_path FROM inventory WHERE id=$item_id");
    $edit_item = mysqli_fetch_assoc($edit_result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - L LE JOSE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="menu-page">
    <div class="pos-wrapper">
        <header class="pos-header">
            <div class="pos-header-left">
                <a href="admin_page.php" class="pos-back">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="pos-brand">
                    <h1>L LE JOSE</h1>
                    <p>MENU</p>
                </div>
            </div>
            <div class="pos-header-right">
                <div class="cart-badge" title="Items in cart">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="cartCount">0</span>
                </div>
                <a href="sales_history.php" class="pos-secondary-btn">
                    <i class="fas fa-history"></i> Sales
                </a>
                <a href="inventory.php" class="pos-secondary-btn">
                    <i class="fas fa-boxes"></i> Inventory
                </a>
                <a href="logout.php" class="pos-secondary-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <main class="pos-main">
            <aside class="pos-sidebar">
                <h2>Category</h2>
                <ul class="category-list" id="categoryList">
                    <li class="category-item active" data-category="">All</li>
                    <?php foreach ($categories as $cat): ?>
                        <li class="category-item" data-category="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="pos-sidebar-note">
                    Tip: Click an item to add it to the order.
                </div>
            </aside>

            <section class="pos-menu">
                <div class="pos-menu-header">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="menuSearchInput" placeholder="Search items..." autocomplete="off">
                    </div>
                </div>

                <div class="items-grid" id="itemsGrid"></div>

                <div class="pos-footer">
                    <span class="footer-note">Tip: Use the order panel to adjust quantity or remove items.</span>
                </div>
            </section>

            <aside class="pos-order">
                <div class="order-header">
                    <h2>Order</h2>
                    <button id="clearOrderBtn" class="btn-clear">Clear</button>
                </div>
                <div class="order-items" id="orderItems">
                    <div class="order-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your order is empty.</p>
                    </div>
                </div>
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span id="orderSubtotal">₱0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (12%)</span>
                        <span id="orderTax">₱0.00</span>
                    </div>
                    <div class="summary-row total-row">
                        <span>Total</span>
                        <span id="orderTotal">₱0.00</span>
                    </div>
                    <div class="order-actions">
                        <button id="printReceiptBtn" class="btn-secondary">Print</button>
                        <button id="checkoutBtn" class="btn-checkout">Checkout</button>
                    </div>
                </div>
            </aside>
        </main>

        <div class="pos-toast" id="posToast"></div>

        <div class="modal" id="modifierModal" aria-hidden="true">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modifierTitle">Customize Item</h3>
                    <button type="button" class="modal-close" id="modifierCloseBtn" aria-label="Close">×</button>
                </div>
                <div class="modal-body" id="modifierBody">
                    <p class="modal-note">Select a size or add toppings before adding to the order.</p>
                    <div class="form-group">
                        <label for="modifierSize">Size</label>
                        <select id="modifierSize">
                            <option value="">Default</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Toppings</label>
                        <div class="modifier-toppings" id="modifierToppings"></div>
                        <div class="form-group" style="margin-top: 12px;">
                            <label for="customToppings">Custom Toppings (comma-separated)</label>
                            <input type="text" id="customToppings" placeholder="e.g., Extra syrup, Less sugar">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="modifierCancelBtn">Cancel</button>
                    <button type="button" class="btn-primary" id="modifierAddBtn">Add to order</button>
                </div>
            </div>
        </div>

        <div class="modal" id="paymentModal" aria-hidden="true">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Payment</h3>
                    <button type="button" class="modal-close" id="paymentCloseBtn" aria-label="Close">×</button>
                </div>
                <div class="modal-body">
                    <div class="payment-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="paymentSubtotal">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (12%)</span>
                            <span id="paymentTax">₱0.00</span>
                        </div>
                        <div class="summary-row total-row">
                            <span>Total</span>
                            <span id="paymentTotal">₱0.00</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="paymentMethodSelect">Payment method</label>
                        <select id="paymentMethodSelect">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amountTenderedInput">Amount tendered</label>
                        <div class="flex-row">
                            <input type="number" id="amountTenderedInput" min="0" step="0.01" value="0" />
                            <button type="button" id="clearTenderedBtn" class="btn-secondary">Clear</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Change</label>
                        <div id="changeAmount" style="font-weight:700;">₱0.00</div>
                    </div>
                    <div class="form-group">
                        <label>Remaining balance</label>
                        <div id="remainingAmount" style="font-weight:700;">₱0.00</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="paymentCancelBtn">Cancel</button>
                    <button type="button" class="btn-primary" id="paymentConfirmBtn">Confirm Payment</button>
                </div>
            </div>
        </div>

    </div>

    <script>
        const menuItems = <?= json_encode($menu_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const LOW_STOCK_THRESHOLD = 5; // Show low-stock warnings when inventory is at or below this level

        let currentCategory = '';
        let order = {};

        const orderItemsEl = document.getElementById('orderItems');
        const orderTotalEl = document.getElementById('orderTotal');
        const clearOrderBtn = document.getElementById('clearOrderBtn');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const printReceiptBtn = document.getElementById('printReceiptBtn');
        const toastEl = document.getElementById('posToast');
        const modifierModal = document.getElementById('modifierModal');
        const modifierCloseBtn = document.getElementById('modifierCloseBtn');
        const modifierCancelBtn = document.getElementById('modifierCancelBtn');
        const modifierAddBtn = document.getElementById('modifierAddBtn');
        const paymentModal = document.getElementById('paymentModal');
        const paymentCloseBtn = document.getElementById('paymentCloseBtn');
        const paymentCancelBtn = document.getElementById('paymentCancelBtn');
        const paymentConfirmBtn = document.getElementById('paymentConfirmBtn');
        const paymentMethodSelect = document.getElementById('paymentMethodSelect');
        const amountTenderedInput = document.getElementById('amountTenderedInput');
        const clearTenderedBtn = document.getElementById('clearTenderedBtn');
        const changeAmountEl = document.getElementById('changeAmount');
        const remainingAmountEl = document.getElementById('remainingAmount');

        const itemsGrid = document.getElementById('itemsGrid');
        const searchInput = document.getElementById('menuSearchInput');
        const categoryList = document.getElementById('categoryList');
        const cartCountEl = document.getElementById('cartCount');
        const orderSubtotalEl = document.getElementById('orderSubtotal');
        const orderTaxEl = document.getElementById('orderTax');

        function formatMoney(amount) {
            return parseFloat(amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        }

        function getCartCount() {
            return Object.values(order).reduce((sum, item) => sum + (item.qty || 0), 0);
        }

        function renderCartCount() {
            const count = getCartCount();
            cartCountEl.textContent = count;
            cartCountEl.classList.toggle('has-items', count > 0);
        }

        function buildOrderKey(itemId, size, toppings) {
            const normalizedSize = size ? size.trim() : '';
            const normalizedToppings = (toppings || []).slice().sort().join(',');
            return `${itemId}::${normalizedSize}::${normalizedToppings}`;
        }

        let currentModifierItem = null;
        let currentModifierKey = null;

        function openModifierModal(item, existingKey = null) {
            currentModifierItem = item;
            currentModifierKey = existingKey;

            const title = document.getElementById('modifierTitle');
            title.textContent = `Customize: ${item.item_name}`;

            const sizeSelect = document.getElementById('modifierSize');
            sizeSelect.innerHTML = '<option value="">Default</option>';
            const sizes = (item.sizes || '').split(',').map(s => s.trim()).filter(s => s);
            sizes.forEach(size => {
                const option = document.createElement('option');
                option.value = size;
                option.textContent = size;
                sizeSelect.appendChild(option);
            });

            const toppingsContainer = document.getElementById('modifierToppings');
            toppingsContainer.innerHTML = '';
            const toppings = ['Extra sugar', 'Extra ice', 'Whipped cream', 'Chocolate drizzle'];
            toppings.forEach(topping => {
                const id = `topping-${topping.replace(/\s+/g, '-')}`;
                const label = document.createElement('label');
                label.innerHTML = `<input type="checkbox" value="${topping}" id="${id}"> ${topping}`;
                toppingsContainer.appendChild(label);
            });

            // If editing existing order line, prefill modifiers
            if (existingKey && order[existingKey]) {
                const existing = order[existingKey];
                if (existing.modifiers) {
                    sizeSelect.value = existing.modifiers.size || '';
                    (existing.modifiers.toppings || []).forEach(t => {
                        const checkbox = document.getElementById(`topping-${t.replace(/\s+/g, '-')}`);
                        if (checkbox) checkbox.checked = true;
                    });
                    // For custom toppings, put them in the input
                    const presetToppings = ['Extra sugar', 'Extra ice', 'Whipped cream', 'Chocolate drizzle'];
                    const custom = (existing.modifiers.toppings || []).filter(t => !presetToppings.includes(t));
                    document.getElementById('customToppings').value = custom.join(', ');
                }
            }

            document.getElementById('modifierModal').classList.add('show');
        }

        function closeModifierModal() {
            document.getElementById('modifierModal').classList.remove('show');
            currentModifierItem = null;
            currentModifierKey = null;
        }

        function getSelectedModifiers() {
            const size = document.getElementById('modifierSize').value;
            const toppings = Array.from(document.querySelectorAll('#modifierToppings input[type="checkbox"]:checked')).map(cb => cb.value);
            const customToppings = document.getElementById('customToppings').value.trim().split(',').map(t => t.trim()).filter(t => t);
            toppings.push(...customToppings);
            return { size, toppings };
        }

        function addToOrderWithModifiers(item, modifiers) {
            const key = buildOrderKey(item.id, modifiers.size, modifiers.toppings);
            if (!order[key]) {
                order[key] = {
                    ...item,
                    qty: 0,
                    modifiers: modifiers
                };
            }

            const maxQty = item.stock_quantity ?? 0;
            if (maxQty <= 0) {
                showToast('Item is out of stock');
                return;
            }

            const nextQty = (order[key].qty || 0) + 1;
            if (nextQty > maxQty) {
                showToast('Maximum stock reached for this item');
                return;
            }

            order[key].qty = nextQty;

            if (maxQty <= LOW_STOCK_THRESHOLD) {
                showToast(`Low stock: only ${maxQty} left`);
            } else {
                showToast(`${item.item_name} added to order`);
            }

            renderOrder();
        }

        function renderMenuItems() {
            console.log('renderMenuItems called, menuItems length:', menuItems.length, 'currentCategory:', currentCategory);
            const search = searchInput.value.trim().toLowerCase();
            const filtered = menuItems.filter(item => {
                const name = (item.item_name || '').toLowerCase();
                const category = (item.category || '').toLowerCase();
                const matchesSearch = !search || name.includes(search) || category.includes(search);
                const matchesCategory = !currentCategory || category === currentCategory.toLowerCase();
                return matchesSearch && matchesCategory;
            });

            console.log('filtered length:', filtered.length);
            itemsGrid.innerHTML = '';

            if (!filtered.length) {
                itemsGrid.innerHTML = '<div class="no-items"><i class="fas fa-inbox"></i><p>No items found.</p></div>';
                return;
            }

            filtered.forEach(item => {
                const card = document.createElement('div');
                card.className = 'menu-card';
                card.innerHTML = `
                    <div class="card-image">
                        ${item.image_path && item.image_path !== '' ? `<img src="${item.image_path}" alt="${item.item_name}">` : `<div class="placeholder-image"><i class="fas fa-image"></i></div>`}
                    </div>
                    <div class="card-info">
                        <h3>${item.item_name}</h3>
                        <p class="category">${item.category}</p>
                        <p class="price">${formatMoney(item.price)}</p>
                        <p class="stock">Stock: ${item.stock_quantity ?? 0}</p>
                    </div>
                `;
                const stockQty = item.stock_quantity ?? 0;
                const outOfStock = stockQty <= 0;
                const lowStock = stockQty > 0 && stockQty <= LOW_STOCK_THRESHOLD;

                if (outOfStock) {
                    card.classList.add('out-of-stock');
                    card.innerHTML += '<div class="out-of-stock-label">Out of stock</div>';
                } else {
                    card.addEventListener('click', () => openModifierModal(item));

                    if (lowStock) {
                        card.innerHTML += '<div class="low-stock-label">Low stock</div>';
                    }
                }

                itemsGrid.appendChild(card);
            });
        }

        function renderCategories() {
            categoryList.querySelectorAll('.category-item').forEach(el => {
                el.classList.toggle('active', el.getAttribute('data-category') === currentCategory);
            });
        }

        function setCategory(category) {
            currentCategory = category;
            renderCategories();
            renderMenuItems();
        }

        function addToOrder(item) {
            const id = item.id;
            if (!order[id]) {
                order[id] = {
                    ...item,
                    qty: 0
                };
            }
            order[id].qty += 1;
            if (order[id].qty > (item.stock_quantity ?? 0)) {
                order[id].qty = item.stock_quantity ?? order[id].qty;
            }
            showToast(`${item.item_name} added to order`);
            renderOrder();
        }

        function updateOrderQuantity(key, delta) {
            if (!order[key]) return;
            order[key].qty += delta;
            if (order[key].qty <= 0) {
                delete order[key];
            }
            renderOrder();
        }

        function removeOrderItem(key) {
            delete order[key];
            renderOrder();
        }

        function clearOrder() {
            order = {};
            renderOrder();
            showToast('Order cleared');
        }

        function renderOrder() {
            const entries = Object.entries(order);
            orderItemsEl.innerHTML = '';

            if (!entries.length) {
                orderItemsEl.innerHTML = `
                    <div class="order-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your order is empty.</p>
                    </div>
                `;
                orderSubtotalEl.textContent = formatMoney(0);
                orderTaxEl.textContent = formatMoney(0);
                orderTotalEl.textContent = formatMoney(0);
                renderCartCount();
                return;
            }

            let subtotal = 0;
            entries.forEach(([key, item]) => {
                const itemSubtotal = item.qty * parseFloat(item.price);
                subtotal += itemSubtotal;

                const modifiers = item.modifiers || {};
                const modifiersParts = [];
                if (modifiers.size) modifiersParts.push(`Size: ${modifiers.size}`);
                if (modifiers.toppings && modifiers.toppings.length) modifiersParts.push(`Toppings: ${modifiers.toppings.join(', ')}`);
                const modifiersText = modifiersParts.length ? `<div class="item-modifiers">${modifiersParts.join(' · ')}</div>` : '';

                const row = document.createElement('div');
                row.className = 'order-row';
                row.innerHTML = `
                    <div class="row-left">
                        <div class="item-name">${item.item_name}</div>
                        ${modifiersText}
                        <div class="item-meta">
                            <span>${formatMoney(item.price)}</span>
                        </div>
                    </div>
                    <div class="row-right">
                        <button class="qty-btn" data-action="decrease">-</button>
                        <span class="qty">${item.qty}</span>
                        <button class="qty-btn" data-action="increase">+</button>
                        <button class="edit-btn" title="Edit">✎</button>
                        <button class="remove-btn" title="Remove">×</button>
                    </div>
                `;

                row.querySelector('[data-action="decrease"]').addEventListener('click', () => updateOrderQuantity(key, -1));
                row.querySelector('[data-action="increase"]').addEventListener('click', () => updateOrderQuantity(key, 1));
                row.querySelector('.edit-btn').addEventListener('click', () => openModifierModal(item, key));
                row.querySelector('.remove-btn').addEventListener('click', () => removeOrderItem(key));

                orderItemsEl.appendChild(row);
            });

            const taxRate = 0.12;
            const tax = subtotal * taxRate;
            const total = subtotal + tax;

            orderSubtotalEl.textContent = formatMoney(subtotal);
            orderTaxEl.textContent = formatMoney(tax);
            orderTotalEl.textContent = formatMoney(total);
            renderCartCount();
        }

        function showToast(message) {
            toastEl.textContent = message;
            toastEl.classList.add('show');
            clearTimeout(toastEl.dataset.timeout);
            toastEl.dataset.timeout = setTimeout(() => {
                toastEl.classList.remove('show');
            }, 2200);
        }

        function printReceipt(orderLines, orderId = '', ticketNumber = '', paymentDetails = []) {
            const date = new Date().toLocaleString();
            let subtotal = 0;
            orderLines.forEach(item => {
                subtotal += item.qty * parseFloat(item.price);
            });
            const tax = subtotal * 0.12;
            const total = subtotal + tax;
            const rows = orderLines.map(item => {
                const modifiers = item.modifiers || {};
                const modifierParts = [];
                if (modifiers.size) modifierParts.push(`Size: ${modifiers.size}`);
                if (modifiers.toppings && modifiers.toppings.length) modifierParts.push(`Toppings: ${modifiers.toppings.join(', ')}`);
                const modText = modifierParts.length ? `<div style="font-size:12px;color:#555;">${modifierParts.join(' · ')}</div>` : '';
                return `
                    <tr>
                        <td style="padding:6px 0;">${item.item_name}${modText}</td>
                        <td style="padding:6px 0;text-align:right;">${item.qty}</td>
                        <td style="padding:6px 0;text-align:right;">${formatMoney(item.price)}</td>
                        <td style="padding:6px 0;text-align:right;">${formatMoney(item.qty * parseFloat(item.price))}</td>
                    </tr>
                `;
            }).join('');

            const paymentRows = (paymentDetails || []).map(pd => {
                const methodLabel = pd.method ? pd.method.charAt(0).toUpperCase() + pd.method.slice(1) : 'Payment';
                const amt = parseFloat(pd.amount) || 0;
                const tendered = pd.tendered != null ? parseFloat(pd.tendered) : null;
                const change = pd.change != null ? parseFloat(pd.change) : null;

                let rows = `<tr><td colspan="3" style="font-size:12px;color:#555;">${methodLabel}</td><td style="text-align:right; font-size:12px;color:#555;">${formatMoney(amt)}</td></tr>`;
                if (tendered !== null) {
                    rows += `<tr><td colspan="3" style="font-size:12px;color:#555;">Tendered</td><td style="text-align:right; font-size:12px;color:#555;">${formatMoney(tendered)}</td></tr>`;
                }
                if (change !== null) {
                    rows += `<tr><td colspan="3" style="font-size:12px;color:#555;">Change</td><td style="text-align:right; font-size:12px;color:#555;">${formatMoney(change)}</td></tr>`;
                }
                return rows;
            }).join('');

            const receiptHtml = `
                <!doctype html>
                <html>
                <head>
                    <title>Receipt</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 20px; color: #222; }
                        h1 { margin: 0; font-size: 22px; }
                        p { margin: 6px 0; }
                        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
                        th, td { padding: 6px 8px; }
                        th { text-align: left; border-bottom: 1px solid #ddd; }
                        td { border-bottom: 1px solid rgba(0,0,0,0.06); }
                        .total { font-weight: 700; }
                        .footer { margin-top: 18px; font-size: 12px; color: #555; }
                    </style>
                </head>
                <body>
                    <h1>L LE JOSE</h1>
                    <p>Ticket #: ${ticketNumber || 'N/A'}</p>
                    <p>Order #: ${orderId || 'N/A'}</p>
                    <p>${date}</p>
                    <p>Payment details:</p>
                    <table>
                        <tbody>
                            ${paymentRows}
                        </tbody>
                    </table>
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style="text-align:right;">Qty</th>
                                <th style="text-align:right;">Price</th>
                                <th style="text-align:right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">Subtotal</td>
                                <td style="text-align:right;">${formatMoney(subtotal)}</td>
                            </tr>
                            <tr>
                                <td colspan="3">Tax (12%)</td>
                                <td style="text-align:right;">${formatMoney(tax)}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="total">Grand Total</td>
                                <td class="total" style="text-align:right;">${formatMoney(total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="footer">Thank you for your purchase!</div>
                </body>
                </html>
            `;

            const w = window.open('', '_blank');
            if (!w) return;
            w.document.write(receiptHtml);
            w.document.close();
            w.focus();
            w.print();
        }

        let paymentMethod = 'cash';
        let amountTendered = 0;


        function updateTenderedAmounts() {
            const total = parseFloat(document.getElementById('paymentTotal').textContent.replace(/[^\d.]/g, '')) || 0;
            amountTendered = parseFloat(amountTenderedInput.value) || 0;

            const change = Math.max(0, amountTendered - total);
            const remaining = Math.max(0, total - amountTendered);

            changeAmountEl.textContent = formatMoney(change);
            remainingAmountEl.textContent = formatMoney(remaining);
        }

        function initPaymentModal() {
            paymentMethodSelect.value = paymentMethod;
            amountTenderedInput.value = '0.00';
            updateTenderedAmounts();
        }

        function confirmPayment() {
            const total = parseFloat(document.getElementById('paymentTotal').textContent.replace(/[^\d.]/g, '')) || 0;
            amountTendered = parseFloat(amountTenderedInput.value) || 0;
            const change = Math.max(0, amountTendered - total);
            const remaining = Math.max(0, total - amountTendered);

            if (remaining > 0) {
                showToast('Payment must cover the full total');
                return;
            }

            const orderLines = Object.values(order);
            const orderPayload = orderLines.map(item => ({
                id: item.id,
                item_name: item.item_name,
                price: item.price,
                qty: item.qty,
                modifiers: item.modifiers || {}
            }));

            // Generate ticket number
            const ticketNumber = 'T' + Date.now().toString().slice(-6);

            const paymentMethod = paymentMethodSelect.value || 'cash';
            const paymentDetails = [{ method: paymentMethod, amount: total, tendered: amountTendered, change }];

            fetch('menu.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=checkout&order=' + encodeURIComponent(JSON.stringify(orderPayload)) + '&payment_method=' + encodeURIComponent(paymentMethod) + '&ticket_number=' + encodeURIComponent(ticketNumber) + '&payment_details=' + encodeURIComponent(JSON.stringify(paymentDetails))
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message || 'Checkout failed');
                    return;
                }

                // Update local stock levels for UI
                orderLines.forEach(item => {
                    const inventoryItem = menuItems.find(m => m.id === item.id);
                    if (inventoryItem) {
                        inventoryItem.stock_quantity = Math.max(0, (inventoryItem.stock_quantity ?? 0) - item.qty);
                    }
                });

                closePaymentModal();
                showToast('Checkout complete');
                printReceipt(orderPayload, data.orderId, ticketNumber, paymentDetails);
                clearOrder();
                renderMenuItems();
            })
            .catch(() => {
                showToast('Checkout failed');
            });
        }

        function openPaymentModal() {
            const orderLines = Object.values(order);
            if (!orderLines.length) {
                showToast('Add items to the order first');
                return;
            }

            const subtotal = orderLines.reduce((sum, item) => sum + (item.qty * parseFloat(item.price)), 0);
            const tax = subtotal * 0.12;
            const total = subtotal + tax;

            document.getElementById('paymentSubtotal').textContent = formatMoney(subtotal);
            document.getElementById('paymentTax').textContent = formatMoney(tax);
            document.getElementById('paymentTotal').textContent = formatMoney(total);

            paymentMethod = 'cash';
            paymentMethodSelect.value = paymentMethod;
            amountTenderedInput.value = total.toFixed(2);
            updateTenderedAmounts();

            document.getElementById('paymentModal').classList.add('show');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('show');
        }

        // Event listeners
        categoryList.addEventListener('click', (event) => {
            const item = event.target.closest('.category-item');
            if (!item) return;
            setCategory(item.getAttribute('data-category') || '');
        });

        searchInput.addEventListener('input', renderMenuItems);
        clearOrderBtn.addEventListener('click', clearOrder);
        checkoutBtn.addEventListener('click', openPaymentModal);
        printReceiptBtn.addEventListener('click', () => {
            const orderLines = Object.values(order);
            if (!orderLines.length) {
                showToast('Add items to the order first');
                return;
            }
            printReceipt(orderLines);
        });

        modifierCloseBtn.addEventListener('click', closeModifierModal);
        modifierCancelBtn.addEventListener('click', closeModifierModal);
        modifierAddBtn.addEventListener('click', () => {
            if (!currentModifierItem) {
                closeModifierModal();
                return;
            }
            const modifiers = getSelectedModifiers();
            addToOrderWithModifiers(currentModifierItem, modifiers);
            closeModifierModal();
        });

        paymentCloseBtn.addEventListener('click', closePaymentModal);
        paymentCancelBtn.addEventListener('click', closePaymentModal);
        paymentConfirmBtn.addEventListener('click', confirmPayment);
        paymentMethodSelect.addEventListener('change', (e) => {
            paymentMethod = e.target.value;
        });
        amountTenderedInput.addEventListener('input', updateTenderedAmounts);
        clearTenderedBtn.addEventListener('click', () => {
            amountTenderedInput.value = '0.00';
            updateTenderedAmounts();
        });
        window.addEventListener('click', (event) => {
            if (event.target === modifierModal) {
                closeModifierModal();
            }
            if (event.target === paymentModal) {
                closePaymentModal();
            }
        });

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModifierModal();
                closePaymentModal();
            }
        });

        // Initial render
        renderMenuItems();
        renderCategories();
        renderCartCount();
        initPaymentModal();
    </script>
</body>
</html>
