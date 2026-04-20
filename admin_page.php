<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - L LE JOSE POS</title>
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
<body class="admin-page">
    <div class="admin-wrapper">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="logo-section">
                    <h1 class="business-name">L LE JOSE</h1>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-container">
            <div class="dashboard-title text-center">
                <h2>ADMIN MANAGEMENT</h2>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid justify-center">
                <!-- Inventory Card -->
                <div class="dashboard-card inventory-card" onclick="window.location.href='inventory.php'">
                    <div class="card-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h3>Inventory</h3>
                    <p>Manage stock and items</p>
                    <div class="card-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>

                <!-- Menu Card -->
                <div class="dashboard-card menu-card" onclick="window.location.href='menu.php'">
                    <div class="card-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Menu</h3>
                    <p>Create and manage menu items</p>
                    <div class="card-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>

                <!-- Sales Report Card -->
                <div class="dashboard-card sales-card" onclick="window.location.href='sales_history.php'">
                    <div class="card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Sales History</h3>
                    <p>View order history and receipts</p>
                    <div class="card-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>

                <!-- Employee Card -->
                <div class="dashboard-card employee-card" onclick="window.location.href='employee.php'">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Employee</h3>
                    <p>Manage staff and roles</p>
                    <div class="card-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <footer class="bg-gray-800 text-white text-center py-4 mt-10">
        <p class="text-sm">© 2026 L LE JOSE - Point of Sale System. All rights reserved.</p>
    </footer>

    <script src="script.js"></script>
</body>
</html>

