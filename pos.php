<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'employee') {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    header('Content-Type: application/json');

    $orderLines = json_decode($_POST['order'] ?? '[]', true);
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $ticketNumber = $_POST['ticket_number'] ?? 'T' . time();

    if (!is_array($orderLines) || !$orderLines) {
        echo json_encode(['success' => false, 'message' => 'No order items received.']);
        exit;
    }

    $productIds = array_unique(array_map(function ($item) {
        return intval($item['id'] ?? 0);
    }, $orderLines));
    $productIds = array_filter($productIds);

    if (!$productIds) {
        echo json_encode(['success' => false, 'message' => 'Invalid order data.']);
        exit;
    }

    $idList = implode(',', $productIds);
    $inventoryRes = $conn->query("SELECT id, item_name, price, quantity_in_stock FROM inventory WHERE id IN ($idList)");
    $inventory = [];
    while ($row = $inventoryRes->fetch_assoc()) {
        $inventory[intval($row['id'])] = $row;
    }

    $insufficient = [];
    $totalAmount = 0;
    foreach ($orderLines as $line) {
        $id = intval($line['id'] ?? 0);
        $qty = intval($line['qty'] ?? 0);
        if ($id <= 0 || $qty <= 0 || !isset($inventory[$id])) {
            continue;
        }
        if ($qty > intval($inventory[$id]['quantity_in_stock'])) {
            $insufficient[] = [
                'id' => $id,
                'name' => $inventory[$id]['item_name'],
                'available' => intval($inventory[$id]['quantity_in_stock'])
            ];
        }
        $totalAmount += floatval($inventory[$id]['price']) * $qty;
    }

    if ($insufficient) {
        echo json_encode(['success' => false, 'message' => 'Some items are out of stock or insufficient quantity.', 'insufficient' => $insufficient]);
        exit;
    }

    $createOrdersTable = "CREATE TABLE IF NOT EXISTS orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user VARCHAR(100),
        total_amount DECIMAL(12,2) NOT NULL,
        ticket_number VARCHAR(50) UNIQUE,
        payment_method VARCHAR(50) DEFAULT 'cash',
        payment_details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($createOrdersTable);

    $createOrderItemsTable = "CREATE TABLE IF NOT EXISTS order_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        order_id INT NOT NULL,
        inventory_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        qty INT NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )";
    $conn->query($createOrderItemsTable);

    $conn->begin_transaction();
    try {
        $stmtOrder = $conn->prepare('INSERT INTO orders (user, total_amount, ticket_number, payment_method, payment_details) VALUES (?, ?, ?, ?, ?)');
        $username = $_SESSION['username'] ?? 'employee';
        $paymentDetails = json_encode(['method' => $paymentMethod]);
        $stmtOrder->bind_param('sdsss', $username, $totalAmount, $ticketNumber, $paymentMethod, $paymentDetails);
        $stmtOrder->execute();
        $orderId = $conn->insert_id;
        $stmtOrder->close();

        $stmtItem = $conn->prepare('INSERT INTO order_items (order_id, inventory_id, item_name, price, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
        $stmtUpdate = $conn->prepare('UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? WHERE id = ? AND quantity_in_stock >= ?');

        foreach ($orderLines as $line) {
            $id = intval($line['id'] ?? 0);
            $qty = intval($line['qty'] ?? 0);
            if ($id <= 0 || $qty <= 0 || !isset($inventory[$id])) {
                continue;
            }
            $price = floatval($inventory[$id]['price']);
            $name = $conn->real_escape_string($inventory[$id]['item_name']);
            $subtotal = $price * $qty;

            $stmtItem->bind_param('iisidd', $orderId, $id, $name, $price, $qty, $subtotal);
            $stmtItem->execute();

            $stmtUpdate->bind_param('iii', $qty, $id, $qty);
            $stmtUpdate->execute();
        }

        $stmtItem->close();
        $stmtUpdate->close();

        $conn->commit();
        echo json_encode(['success' => true, 'orderId' => $orderId, 'ticketNumber' => $ticketNumber, 'total' => $totalAmount]);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()]);
        exit;
    }
}

$query = "SELECT id, item_name, category, price, quantity_in_stock AS stock_quantity, size AS sizes, sku AS product_sku, image_path FROM inventory ORDER BY item_name ASC";
$result = $conn->query($query);
$menu_items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$category_result = $conn->query("SELECT DISTINCT category FROM inventory ORDER BY category");
$categories = $category_result ? $category_result->fetch_all(MYSQLI_ASSOC) : [];
$low_stock_threshold = 5;
$lowStockItems = array_filter($menu_items, function ($item) use ($low_stock_threshold) {
    return intval($item['stock_quantity']) > 0 && intval($item['stock_quantity']) <= $low_stock_threshold;
});
$lowStockCount = count($lowStockItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Employee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="menu-page">
    <div class="pos-wrapper">
        <header class="pos-header">
            <div class="pos-header-left">
                <a href="employee_page.php" class="pos-back"><i class="fas fa-arrow-left"></i></a>
                <div class="pos-brand">
                    <h1>L LE JOSE</h1>
                </div>
            </div>
            <div class="pos-header-right">
                <?php if ($lowStockCount): ?>
                    <span class="low-stock-pill">Low stock</span>
                <?php endif; ?>
                <button type="button" class="pos-secondary-btn" onclick="document.getElementById('cartPanel').scrollIntoView({ behavior: 'smooth' });">
                    <i class="fas fa-receipt"></i> Order
                </button>
                <a href="logout.php" class="pos-secondary-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <main class="pos-main">
            <aside class="pos-sidebar">
                <h2>Category</h2>
                <ul class="category-list">
                    <li class="category-item active" data-category="">All</li>
                    <?php foreach ($categories as $category): ?>
                        <li class="category-item" data-category="<?= htmlspecialchars($category['category']) ?>"><?= htmlspecialchars($category['category']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <section class="pos-menu">
                <div class="pos-menu-header">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="menuSearchInput" placeholder="Search products..." autocomplete="off">
                    </div>
                </div>

                <div class="items-grid" id="itemsGrid">
                    <?php if (!$menu_items): ?>
                        <div class="no-items"><i class="fas fa-inbox"></i><p>No products available.</p></div>
                    <?php else: ?>
                        <?php foreach ($menu_items as $item): ?>
                            <div class="menu-card" data-category="<?= htmlspecialchars($item['category']) ?>">
                                <div class="card-image">
                                    <?php if (!empty($item['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                                    <?php else: ?>
                                        <div class="placeholder-image"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-info">
                                    <h3><?= htmlspecialchars($item['item_name']) ?></h3>
                                    <p class="category"><?= htmlspecialchars($item['category']) ?></p>
                                    <?php if (!empty($item['product_sku'])): ?><p class="sku">SKU: <?= htmlspecialchars($item['product_sku']) ?></p><?php endif; ?>
                                    <p class="price">₱<?= number_format($item['price'], 2) ?></p>
                                    <p class="stock">Stock: <?= intval($item['stock_quantity']) ?></p>
                                    <?php if (intval($item['stock_quantity']) <= 5 && intval($item['stock_quantity']) > 0): ?>
                                        <div class="low-stock-label">Low stock</div>
                                    <?php elseif (intval($item['stock_quantity']) <= 0): ?>
                                        <div class="out-of-stock-label">Out of stock</div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['sizes'])): ?><p class="size">Sizes: <?= htmlspecialchars($item['sizes']) ?></p><?php endif; ?>
                                </div>
                                <div class="quantity-controls">
                                    <button type="button" onclick="changeQuantity(<?= $item['id'] ?>, -1)">-</button>
                                    <div class="quantity-value" id="qty-<?= $item['id'] ?>">1</div>
                                    <button type="button" onclick="changeQuantity(<?= $item['id'] ?>, 1)">+</button>
                                </div>
                                <?php if (intval($item['stock_quantity']) > 0): ?>
                                    <button type="button" class="add-to-cart" onclick="addToCart(<?= $item['id'] ?>, <?= json_encode($item['item_name']) ?>, <?= $item['price'] ?>)">Add to Order</button>
                                <?php else: ?>
                                    <button type="button" class="add-to-cart disabled" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            <aside class="pos-order" id="cartPanel">
                <div class="order-header">
                    <h2>Order</h2>
                    <button id="clearOrderBtn" class="btn-clear">Clear</button>
                </div>
                <div class="order-items" id="orderItems">
                    <div class="order-empty"><i class="fas fa-shopping-cart"></i><p>No items in the order yet.</p></div>
                </div>
                <div class="order-summary">
                    <div class="summary-row"><span>Subtotal</span><span id="orderSubtotal">₱0.00</span></div>
                    <div class="summary-row"><span>Tax (12%)</span><span id="orderTax">₱0.00</span></div>
                    <div class="summary-row total-row"><span>Total</span><span id="orderTotal">₱0.00</span></div>
                    <div class="summary-row payment-row">
                        <span>Payment</span>
                        <select id="paymentMethodSelect">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <div class="order-actions">
                        <button id="checkoutBtn" class="btn-checkout">Complete Sale</button>
                    </div>
                </div>
            </aside>
        </main>

        <div class="pos-toast" id="posToast"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        const menuItems = <?= json_encode($menu_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let currentCategory = '';
        let order = {};
        const itemsGrid = document.getElementById('itemsGrid');
        const searchInput = document.getElementById('menuSearchInput');
        const categoryList = document.querySelectorAll('.category-item');
        const orderItemsEl = document.getElementById('orderItems');
        const orderSubtotalEl = document.getElementById('orderSubtotal');
        const orderTaxEl = document.getElementById('orderTax');
        const orderTotalEl = document.getElementById('orderTotal');
        const paymentMethodSelect = document.getElementById('paymentMethodSelect');
        const clearOrderBtn = document.getElementById('clearOrderBtn');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const toastEl = document.getElementById('posToast');

        function formatMoney(amount) {
            return parseFloat(amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        }

        function changeQuantity(id, delta) {
            const quantityEl = document.getElementById(`qty-${id}`);
            const current = parseInt(quantityEl.textContent, 10) || 1;
            const next = Math.max(1, current + delta);
            quantityEl.textContent = next;
        }

        function addToCart(id, name, price) {
            const qtyEl = document.getElementById(`qty-${id}`);
            const quantity = parseInt(qtyEl.textContent, 10) || 1;
            const key = id;
            if (!order[key]) {
                order[key] = { id, name, price, qty: 0 };
            }
            order[key].qty += quantity;
            qtyEl.textContent = '1';
            renderOrder();
            showToast(`${name} added to order`);
        }

        function removeOrderItem(key) {
            delete order[key];
            renderOrder();
        }

        function renderOrder() {
            const items = Object.values(order);
            if (!items.length) {
                orderItemsEl.innerHTML = `<div class="order-empty"><i class="fas fa-shopping-cart"></i><p>Your order is empty.</p></div>`;
                orderSubtotalEl.textContent = formatMoney(0);
                orderTaxEl.textContent = formatMoney(0);
                orderTotalEl.textContent = formatMoney(0);
                return;
            }
            let subtotal = 0;
            orderItemsEl.innerHTML = items.map(item => {
                const itemTotal = item.price * item.qty;
                subtotal += itemTotal;
                return `
                    <div class="order-row">
                        <div class="row-left">
                            <div class="item-name">${item.name}</div>
                            <div class="item-meta"><span>${formatMoney(item.price)}</span></div>
                        </div>
                        <div class="row-right">
                            <span class="qty">${item.qty}</span>
                            <button class="qty-btn" onclick="updateQty(${item.id}, -1)">-</button>
                            <button class="qty-btn" onclick="updateQty(${item.id}, 1)">+</button>
                            <button class="remove-btn" onclick="removeOrderItem(${item.id})">×</button>
                        </div>
                    </div>
                `;
            }).join('');
            const tax = subtotal * 0.12;
            orderSubtotalEl.textContent = formatMoney(subtotal);
            orderTaxEl.textContent = formatMoney(tax);
            orderTotalEl.textContent = formatMoney(subtotal + tax);
        }

        function updateQty(key, delta) {
            if (!order[key]) return;
            order[key].qty += delta;
            if (order[key].qty <= 0) {
                delete order[key];
            }
            renderOrder();
        }

        function clearOrder() {
            order = {};
            renderOrder();
        }

        function checkout() {
            const items = Object.values(order);
            if (!items.length) {
                showToast('Add products before checkout');
                return;
            }

            const paymentMethod = paymentMethodSelect.value || 'cash';
            const ticketNumber = 'T' + Date.now().toString().slice(-6);

            fetch('pos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=checkout&payment_method=' + encodeURIComponent(paymentMethod)
                    + '&ticket_number=' + encodeURIComponent(ticketNumber)
                    + '&order=' + encodeURIComponent(JSON.stringify(items))
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message || 'Checkout failed');
                    return;
                }
                showToast('Sale recorded');
                printReceipt(items, data.orderId, data.ticketNumber, paymentMethod);
                order = {};
                renderOrder();
                setTimeout(() => window.location.reload(), 500);
            })
            .catch(() => {
                showToast('Checkout failed');
            });
        }

        function printReceipt(items, orderId, ticketNumber, paymentMethod) {
            const subtotal = items.reduce((sum, item) => sum + item.price * item.qty, 0);
            const tax = subtotal * 0.12;
            const total = subtotal + tax;
            const rows = items.map(item => `
                <tr>
                    <td>${item.name}</td>
                    <td style="text-align:right;">${item.qty}</td>
                    <td style="text-align:right;">${formatMoney(item.price)}</td>
                    <td style="text-align:right;">${formatMoney(item.price * item.qty)}</td>
                </tr>
            `).join('');

            const receiptHtml = `
                <html>
                <head>
                    <title>Receipt</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; color: #222; }
                        h1 { margin: 0 0 10px; font-size: 24px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
                        th, td { padding: 8px 6px; border-bottom: 1px solid #ddd; }
                        th { text-align: left; }
                        .total-row td { font-weight: 700; }
                        .summary { margin-top: 18px; }
                    </style>
                </head>
                <body>
                    <h1>L LE JOSE</h1>
                    <p><strong>Order ID:</strong> ${orderId}</p>
                    <p><strong>Ticket:</strong> ${ticketNumber}</p>
                    <p><strong>Payment:</strong> ${paymentMethod}</p>
                    <table>
                        <thead>
                            <tr><th>Item</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Price</th><th style="text-align:right;">Total</th></tr>
                        </thead>
                        <tbody>${rows}</tbody>
                        <tfoot>
                            <tr><td colspan="3">Subtotal</td><td style="text-align:right;">${formatMoney(subtotal)}</td></tr>
                            <tr><td colspan="3">Tax (12%)</td><td style="text-align:right;">${formatMoney(tax)}</td></tr>
                            <tr class="total-row"><td colspan="3">Grand Total</td><td style="text-align:right;">${formatMoney(total)}</td></tr>
                        </tfoot>
                    </table>
                    <p style="margin-top: 20px;">Thank you for your purchase!</p>
                </body>
                </html>
            `;
            const receiptWindow = window.open('', '_blank');
            if (receiptWindow) {
                receiptWindow.document.write(receiptHtml);
                receiptWindow.document.close();
                receiptWindow.focus();
                receiptWindow.print();
            }
        }

        function showToast(message) {
            toastEl.textContent = message;
            toastEl.classList.add('show');
            setTimeout(() => toastEl.classList.remove('show'), 2000);
        }

        function filterProducts() {
            const search = searchInput.value.trim().toLowerCase();
            const activeCategory = document.querySelector('.category-item.active').dataset.category;
            document.querySelectorAll('.menu-card').forEach(card => {
                const name = card.querySelector('h3').textContent.toLowerCase();
                const category = card.dataset.category.toLowerCase();
                const matchesSearch = !search || name.includes(search) || category.includes(search);
                const matchesCategory = !activeCategory || category === activeCategory.toLowerCase();
                card.style.display = matchesSearch && matchesCategory ? 'grid' : 'none';
            });
        }

        categoryList.forEach(item => item.addEventListener('click', () => {
            categoryList.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            filterProducts();
        }));
        searchInput.addEventListener('input', filterProducts);
        clearOrderBtn.addEventListener('click', clearOrder);
        checkoutBtn.addEventListener('click', checkout);
        renderOrder();
    </script>
</body>
</html>
