<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
include 'helpers.php';

// Get orders with search/filter
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

$query = "SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    // Search by ticket, user, or ordered item name (case-insensitive)
    $searchTerm = '%' . strtolower($search) . '%';
    $query .= " AND (LOWER(o.ticket_number) LIKE ? OR LOWER(o.user) LIKE ? OR LOWER(oi.item_name) LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($dateFrom) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo) {
    $query .= " AND DATE(o.created_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

if ($paymentMethod) {
    $query .= " AND o.payment_method = ?";
    $params[] = $paymentMethod;
    $types .= 's';
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Sales summary (based on current filter results)
$totalOrders = count($orders);
$totalItems = 0;
$totalSales = 0.0;
foreach ($orders as $o) {
    $totalItems += intval($o['item_count']);
    $totalSales += floatval($o['total_amount']);
}

// Chart data queries - same filters
// Yearly
$yearly_query = "SELECT YEAR(created_at) as year, SUM(total_amount) as sales FROM orders WHERE 1=1" . ($dateFrom ? " AND YEAR(created_at) >= YEAR('$dateFrom')" : "") . ($dateTo ? " AND YEAR(created_at) <= YEAR('$dateTo')" : "") . " GROUP BY YEAR(created_at) ORDER BY year ASC";
$yearly_result = $conn->query($yearly_query);
$yearly_data = $yearly_result ? $yearly_result->fetch_all(MYSQLI_ASSOC) : [];

// Daily (last 30 days)
$daily_query = "SELECT DATE(created_at) as date, SUM(total_amount) as sales FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . ($dateFrom ? " AND DATE(created_at) >= '$dateFrom'" : "") . ($dateTo ? " AND DATE(created_at) <= '$dateTo'" : "") . " GROUP BY DATE(created_at) ORDER BY date ASC";
$daily_result = $conn->query($daily_query);
$daily_data = $daily_result ? $daily_result->fetch_all(MYSQLI_ASSOC) : [];

// Budget target (example 1M monthly)
$budget_target = 1000000;
$budget_percent = $totalSales / $budget_target * 100;

// Get order details if requested
$orderDetails = null;
$selectedOrder = null;
if (isset($_GET['order_id'])) {
    $orderId = intval($_GET['order_id']);

    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $selectedOrder = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT oi.*, i.item_name as current_name FROM order_items oi LEFT JOIN inventory i ON oi.inventory_id = i.id WHERE oi.order_id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $orderDetails = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - L LE JOSE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>
        const chartData = <?= json_encode([
            'yearly' => $yearly_data ?? [],
            'daily' => $daily_data ?? [],
            'totalSales' => $totalSales ?? 0,
            'budgetPercent' => $budget_percent ?? 0
        ]) ?>; 

        // Dynamic charts from real data
        document.addEventListener('DOMContentLoaded', function() {
            // Yearly line chart
            if (chartData.yearly.length > 0) {
                const labels = chartData.yearly.map(item => item.year);
                const data = chartData.yearly.map(item => parseFloat(item.sales));
                const ctx = document.getElementById('yearlyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sales',
                            data: data,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102,126,234,0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }

            // Daily bar chart
            if (chartData.daily.length > 0) {
                const labels2 = chartData.daily.map(item => item.date);
                const data2 = chartData.daily.map(item => parseFloat(item.sales));
                const ctx2 = document.getElementById('dailyChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: labels2,
                        datasets: [{
                            label: 'Daily Sales',
                            data: data2,
                            backgroundColor: 'rgba(102,126,234,0.7)'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }

            // Budget doughnut
            const ctx3 = document.getElementById('budgetChart').getContext('2d');
            new Chart(ctx3, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [chartData.budgetPercent, 100 - chartData.budgetPercent],
                        backgroundColor: ['#667eea', '#f0f0f0'],
                        cutout: '70%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: false }
                },
                plugins: [{
                    beforeDraw: function(chart) {
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.font = 'bold 28px Poppins';
                        ctx.fillStyle = '#667eea';
                        ctx.fillText(Math.round(chartData.budgetPercent) + '%', chart.width/2, chart.height/2);
                        ctx.restore();
                    }
                }]
            });
        });
        </script>

</head>
<body class="admin-page">
    <div class="admin-wrapper">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="admin_page.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1>L LE JOSE</h1>
                </div>

                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <main class="dashboard-container">
            <div class="content-header">
                <h2>Sales History</h2>
                <a href="menu.php" class="btn-primary">
                    <i class="fas fa-plus"></i> New Order
                </a>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter-bar">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="search" name="search" placeholder="Search by ticket or user..." value="<?= htmlspecialchars($search) ?>" autocomplete="off" spellcheck="false">
                    </div>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    <select name="payment_method">
                        <option value="">All Payments</option>
                        <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="credit" <?= $paymentMethod === 'credit' ? 'selected' : '' ?>>Credit</option>
                    </select>
                    <a href="sales_history.php" class="clear-btn">Clear</a>
                </form>
            </div>

            <!-- Sales Dashboard -->
            <div class="sales-dashboard">
                <!-- KPIs -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-value">₱<?= number_format($totalSales, 0) ?></div>
                        <div class="kpi-label">Gross Sales</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">₱<?= number_format($totalSales * 0.9, 0) ?></div>
                        <div class="kpi-label">Gross Profits</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">₱<?= number_format($totalSales, 0) ?></div>
                        <div class="kpi-label">Net Sales</div>
                    </div>
                    <div class="kpi-card">
                        <?php 
                        $past_year_sales = 0;
                        $past_query = "SELECT SUM(total_amount) as past FROM orders WHERE YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 YEAR))" . ($dateFrom ? " AND YEAR(created_at) >= YEAR('$dateFrom')" : "") . ($dateTo ? " AND YEAR(created_at) <= YEAR('$dateTo')" : "");
                        $past_result = $conn->query($past_query);
                        if ($past_result) $past_year_sales = $past_result->fetch_assoc()['past'] ?? 0;
                        ?>
                        <div class="kpi-value">₱<?= number_format($past_year_sales, 0) ?></div>
                        <div class="kpi-label">Past Year Sales</div>
                    </div>
                    <div class="kpi-card">
                        <?php $growth = $past_year_sales > 0 ? (($totalSales - $past_year_sales) / $past_year_sales * 100) : 0; ?>
                        <div class="kpi-value growth"><?= round($growth, 0) ?>%</div>
                        <div class="kpi-label">Sales Growth</div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h4 class="chart-title">Yearly Sales Trend</h4>
                        <div class="chart-container">
                            <canvas id="yearlyChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h4 class="chart-title">Daily Sales</h4>
                        <div class="chart-container">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h4 class="chart-title">Budget</h4>
                        <div class="chart-container">
                            <canvas id="budgetChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Sales Summary (preserved) -->
            <div class="sales-summary" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                <div class="summary-card" style="flex:1; min-width:180px; padding:14px 16px; background:#fff; border:1px solid #ddd; border-radius:8px;">
                    <div style="font-size:12px; color:#666; margin-bottom:4px;">Orders</div>
                    <div style="font-size:20px; font-weight:700;"><?= number_format($totalOrders) ?></div>
                </div>
                <div class="summary-card" style="flex:1; min-width:180px; padding:14px 16px; background:#fff; border:1px solid #ddd; border-radius:8px;">
                    <div style="font-size:12px; color:#666; margin-bottom:4px;">Items Sold</div>
                    <div style="font-size:20px; font-weight:700;"><?= number_format($totalItems) ?></div>
                </div>
                <div class="summary-card" style="flex:1; min-width:180px; padding:14px 16px; background:#fff; border:1px solid #ddd; border-radius:8px;">
                    <div style="font-size:12px; color:#666; margin-bottom:4px;">Total Sales</div>
                    <div style="font-size:20px; font-weight:700;">₱<?= number_format($totalSales, 2) ?></div>
                </div>
            </div>

            <?php if ($orderDetails): ?>
                <!-- Order Details Modal -->
                <div class="modal show">
                    <div class="modal-content" style="position: relative;">
                        <a href="sales_history.php" class="modal-close" style="position: absolute; top: 20px; right: 24px; font-size: 28px; color: #999; text-decoration: none; z-index: 10;">×</a>
                        <div style="padding: 80px 24px 24px 24px;">
                            <h3 style="margin: 0 0 24px 0; font-size: 22px; font-weight: 600; color: #1a1a1a;">Order Details - <?= htmlspecialchars($selectedOrder['ticket_number'] ?? '') ?></h3>
                            <div style="display: flex; gap: 24px; align-items: flex-start;">
                                <div style="flex: 1; min-width: 0;">
                                    <table class="receipt-table">
                                        <thead>
                                            <tr>
                                                <th style="padding-bottom: 12px;">Item</th>
                                                <th class="qty-col" style="padding-bottom: 12px;">Qty</th>
                                                <th class="price-col" style="padding-bottom: 12px;">Price</th>
                                                <th class="total-col" style="padding-bottom: 12px;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orderDetails as $item): ?>
                                                <tr>
                                                    <td style="padding: 12px 8px 12px 0;">
                                                        <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                                        <?php if ($modifiersText = formatModifiers($item['modifiers'] ?? '')): ?>
                                                            <div class="modifiers"><?= $modifiersText ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="qty-col" style="padding: 12px 8px 12px 0;"><?= $item['qty'] ?></td>
                                                    <td class="price-col" style="padding: 12px 8px 12px 0;">₱<?= number_format($item['price'], 2) ?></td>
                                                    <td class="total-col" style="padding: 12px 8px 12px 0;">₱<?= number_format($item['subtotal'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div style="flex-shrink: 0; padding-top: 32px; display: flex; flex-direction: column; gap: 16px; align-items: flex-end; min-width: 160px;">
                                    <a href="receipt.php?order_id=<?= $selectedOrder['id'] ?>" target="_blank" class="btn-primary" style="font-size: 14px; padding: 12px 24px; white-space: nowrap; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-print"></i> Print Receipt
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Orders Table -->
            <div class="table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>User</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['ticket_number']) ?></td>
                                    <td><?= htmlspecialchars($order['user']) ?></td>
                                    <td><?= $order['item_count'] ?></td>
                                    <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="category-badge" style="background: <?= $order['payment_method'] === 'cash' ? '#d4edda' : '#cce5ff' ?>; color: <?= $order['payment_method'] === 'cash' ? '#155724' : '#004085' ?>;">
                                            <?= ucfirst($order['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <a href="?order_id=<?= $order['id'] ?>&<?= http_build_query($_GET) ?>" class="btn-action btn-edit" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="receipt.php?order_id=<?= $order['id'] ?>" target="_blank" class="btn-action btn-print" title="Print receipt">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>No orders found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <script>
        // Yearly Sales Line Chart (upward from current year)
        document.addEventListener('DOMContentLoaded', function() {
            const yearlyCtx = document.getElementById('yearlyChart')?.getContext('2d');
            if (yearlyCtx) {
                    // Placeholder - no sample data
                    const ctx = document.createElement('canvas').getContext('2d');
                    ctx.fillStyle = '#e0e0e0';
                    ctx.fillRect(0, 0, 300, 300);
                    ctx.fillStyle = '#999';
                    ctx.font = '20px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('Chart Data', 150, 150);
                    ctx.fillText('Coming Soon', 150, 180);
            }

            // Daily Sales Bar + Conversion Overlay (today upward)
            const dailyCtx = document.getElementById('dailyChart')?.getContext('2d');
            if (dailyCtx) {
                // Placeholder - no sample data
                    const ctx2 = document.createElement('canvas').getContext('2d');
                    ctx2.fillStyle = '#e0e0e0';
                    ctx2.fillRect(0, 0, 300, 300);
                    ctx2.fillStyle = '#999';
                    ctx2.font = '20px sans-serif';
                    ctx2.textAlign = 'center';
                    ctx2.textBaseline = 'middle';
                    ctx2.fillText('Daily Chart', 150, 150);
                    ctx2.fillText('Data Loading...', 150, 180);
            }

            // Budget Circular (36%)
            const budgetCtx = document.getElementById('budgetChart')?.getContext('2d');
            if (budgetCtx) {
                // Placeholder - no sample data
                    const ctx3 = document.createElement('canvas').getContext('2d');
                    ctx3.fillStyle = '#e0e0e0';
                    ctx3.fillRect(0, 0, 300, 300);
                    ctx3.fillStyle = '#999';
                    ctx3.font = '20px sans-serif';
                    ctx3.textAlign = 'center';
                    ctx3.textBaseline = 'middle';
                    ctx3.fillText('Budget Chart', 150, 150);
                    ctx3.fillText('Coming Soon', 150, 180);
            }
        });
        </script>
        </main>
    </div>
</body>
</html>
