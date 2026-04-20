<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

$query = "SELECT id, item_name, category, price, quantity_in_stock AS stock_quantity, size AS sizes, sku AS product_sku, image_path FROM inventory ORDER BY item_name ASC";
$result = mysqli_query($conn, $query);
$menu_items = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
$category_result = mysqli_query($conn, "SELECT DISTINCT category FROM inventory ORDER BY category");
$categories = $category_result ? mysqli_fetch_all($category_result, MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Menu - L LE JOSE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
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
                    <p>BUSINESS MENU</p>
                </div>
            </div>
            <div class="pos-header-right">
                <button type="button" class="pos-secondary-btn low-stock-alert" id="lowStockAlertBtn">
                    <i class="fas fa-exclamation-triangle"></i> Low stock alert
                </button>
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
            </aside>

            <section class="pos-menu">
                <div class="pos-menu-header">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="menuSearchInput" placeholder="Search items..." autocomplete="off">
                    </div>
                </div>

                <div class="items-grid" id="itemsGrid"></div>

            </section>
        </main>
    </div>

    <div class="pos-toast" id="posToast"></div>

    <script>
        const menuItems = <?= json_encode($menu_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const LOW_STOCK_THRESHOLD = 10;
        const itemsGrid = document.getElementById('itemsGrid');
        const searchInput = document.getElementById('menuSearchInput');
        const categoryList = document.getElementById('categoryList');
        const toastEl = document.getElementById('posToast');

        function formatMoney(amount) {
            return parseFloat(amount || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
        }

        function showToast(message) {
            if (!toastEl) return;
            toastEl.textContent = message;
            toastEl.classList.add('show');
            clearTimeout(toastEl.dataset.timeout);
            toastEl.dataset.timeout = setTimeout(() => toastEl.classList.remove('show'), 2200);
        }

        function handleLowStockAlert() {
            const lowStockItems = menuItems
                .map(item => ({
                    name: item.item_name,
                    qty: parseInt(item.stock_quantity, 10) || 0
                }))
                .filter(item => item.qty > 0 && item.qty <= LOW_STOCK_THRESHOLD);

            if (!lowStockItems.length) {
                showToast('No low stock products at the moment.');
                return;
            }

            const messages = lowStockItems.map(item => `${item.name} is low stock (${item.qty})`);
            showToast(messages.slice(0, 2).join(' • '));
        }

        function renderCategories() {
            Array.from(categoryList.querySelectorAll('.category-item')).forEach(el => {
                el.classList.toggle('active', el.getAttribute('data-category') === currentCategory);
            });
        }

        let currentCategory = '';

        function setCategory(category) {
            currentCategory = category;
            renderCategories();
            renderMenuItems();
        }

        function renderMenuItems() {
            const search = searchInput.value.trim().toLowerCase();
            const filtered = menuItems.filter(item => {
                const name = (item.item_name || '').toLowerCase();
                const category = (item.category || '').toLowerCase();
                const matchesSearch = !search || name.includes(search) || category.includes(search);
                const matchesCategory = !currentCategory || category === currentCategory.toLowerCase();
                return matchesSearch && matchesCategory;
            });

            itemsGrid.innerHTML = '';
            if (!filtered.length) {
                itemsGrid.innerHTML = '<div class="no-items"><i class="fas fa-inbox"></i><p>No products found.</p></div>';
                return;
            }

            filtered.forEach(item => {
                const stockQty = parseInt(item.stock_quantity, 10) || 0;
                const lowStock = stockQty > 0 && stockQty <= LOW_STOCK_THRESHOLD;
                const outOfStock = stockQty <= 0;

                const card = document.createElement('div');
                card.className = 'menu-card';
                card.innerHTML = `
                    <div class="card-image">
                        ${item.image_path ? `<img src="${item.image_path}" alt="${item.item_name}">` : `<div class="placeholder-image"><i class="fas fa-image"></i></div>`}
                    </div>
                    <div class="card-info">
                        <h3>${item.item_name}</h3>
                        <p class="category">${item.category}</p>
                        ${item.product_sku ? `<p class="sku">SKU: ${item.product_sku}</p>` : ''}
                        <p class="price">${formatMoney(item.price)}</p>
                        <p class="stock">Stock: ${stockQty}</p>
                        ${lowStock ? '<div class="low-stock-label">Low stock</div>' : ''}
                        ${outOfStock ? '<div class="out-of-stock-label">Out of stock</div>' : ''}
                    </div>
                `;
                itemsGrid.appendChild(card);
            });
        }

        categoryList.addEventListener('click', event => {
            const item = event.target.closest('.category-item');
            if (!item) return;
            setCategory(item.getAttribute('data-category') || '');
        });

        searchInput.addEventListener('input', renderMenuItems);
        document.getElementById('lowStockAlertBtn').addEventListener('click', handleLowStockAlert);

        renderCategories();
        renderMenuItems();
    </script>
</body>
</html>
