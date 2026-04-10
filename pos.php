<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'employee') {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// Simple POS - fetch menu items
$menu_result = $conn->query("SELECT * FROM menu_items ORDER BY name");
$menu_items = $menu_result ? $menu_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Employee Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pos-container { max-width: 1200px; margin: 20px auto; padding: 20px; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .pos-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .pos-title { font-size: 2.5rem; color: #333; }
        .cart-total { font-size: 1.5rem; font-weight: 600; color: #667eea; }
        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .menu-item { border: 2px solid #eee; border-radius: 12px; padding: 20px; text-align: center; transition: all 0.3s; cursor: pointer; }
        .menu-item:hover { border-color: #667eea; transform: translateY(-5px); box-shadow: 0 10px 25px rgba(102,126,234,0.15); }
        .menu-item img { width: 120px; height: 120px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; }
        .item-name { font-weight: 600; margin-bottom: 5px; }
        .item-price { color: #667eea; font-size: 1.2rem; font-weight: 600; }
        .cart { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-top: 20px; }
        .cart h3 { margin-bottom: 15px; }
        .checkout-btn { background: #28a745; color: white; border: none; padding: 15px 30px; font-size: 1.3rem; border-radius: 10px; cursor: pointer; width: 100%; margin-top: 15px; }
        .back-btn { background: #6c757d; color: white; padding: 12px 24px; border-radius: 25px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="pos-container">
        <div class="pos-header">
            <h1 class="pos-title">🛒 POS System</h1>
            <div class="cart-total">
                Cart Total: ₱<span id="cartTotal">0.00</span>
            </div>
        </div>

        <div class="items-grid" id="menuItems">
            <?php foreach ($menu_items as $item): ?>
            <div class="menu-item" onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">
                <?php if (!empty($item['image_path'])): ?>
                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php endif; ?>
                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="item-price">₱<?= number_format($item['price'], 2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cart">
            <h3>🛍️ Shopping Cart</h3>
            <div id="cartItems"></div>
            <button onclick="checkout()" class="checkout-btn">Complete Sale</button>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="employee_page.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        let cart = [];

        function addToCart(id, name, price) {
            const existing = cart.find(item => item.id === id);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({ id, name, price, quantity: 1 });
            }
            updateCart();
        }

        function updateCart() {
            let total = 0;
            const cartHtml = cart.map(item => 
                `<div style="display:flex;justify-content:space-between;margin:10px 0;">
                    <span>${item.name} x${item.quantity}</span>
                    <span>₱${(item.price * item.quantity).toFixed(2)}</span>
                </div>`
            ).join('');
            
            cart.forEach(item => total += item.price * item.quantity);
            
            document.getElementById('cartItems').innerHTML = cartHtml || '<p>Cart is empty</p>';
            document.getElementById('cartTotal').textContent = total.toFixed(2);
        }

        function checkout() {
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }
            // In production: POST to sales/receipt.php
            const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
            alert(`Sale completed! Total: ₱${total.toFixed(2)}\nReceipt would be generated here.`);
            cart = [];
            updateCart();
        }
    </script>
</body>
</html>
