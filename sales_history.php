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

// Determine active tab
$activeTab = $_GET['tab'] ?? 'completed';
$pageTitle = $activeTab === 'pending' ? 'Pending Orders' : 'Sales History';

// Handle pending tab
$pendingOrders = [];
if ($activeTab === 'pending') {
    $pending_query = "SELECT po.*, 
        (SELECT COUNT(*) FROM JSON_TABLE(po.order_data, '$[*]' COLUMNS (qty INT PATH '$.qty'))) as item_count
        FROM pending_orders po 
        WHERE po.expires_at > NOW() 
        ORDER BY po.updated_at DESC";
    $pending_result = $conn->query($pending_query);
    $pendingOrders = $pending_result ? $pending_result->fetch_all(MYSQLI_ASSOC) : [];
} 

// Get order details if requested (for both tabs)
$orderDetails = null;
$selectedOrder = null;
$selectedPendingOrder = null;
if (isset($_GET['order_id'])) {
    $orderId = intval($_GET['order_id']);
    
    if ($activeTab === 'pending') {
        $stmt = $conn->prepare("SELECT * FROM pending_orders WHERE id = ? AND expires_at > NOW()");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $selectedPendingOrder = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $orderDetails = json_decode($selectedPendingOrder['order_data'] ?? '[]', true);
    } else {
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
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - L LE JOSE</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <button type="submit" style="display:none;">Search</button>
                </form>
            </div>

            <div class="sales-dashboard">
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

                <div class="export-actions" style="margin: 20px 0; text-align: right;">
    <button type="button" onclick="toggleExportModal()" class="btn-primary" style="background: #27ae60; border: none; cursor: pointer;">
        <i class="fas fa-file-excel"></i> Export to Excel
    </button>
</div>

<div id="exportModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; justify-content:center; align-items:center; backdrop-filter: blur(2px);">
    <div style="background:#fff; padding:30px; border-radius:12px; width:95%; max-width:480px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); position: relative;">
        
        <div style="text-align: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #1a1a1a; font-size: 22px; font-weight: 600;">Export Data Options</h3>
            <p style="font-size: 14px; color: #666; margin-top: 8px; line-height: 1.5;">Select the summary reports to include in your Excel export.</p>
        </div>
        
        <form id="exportForm" action="export_summary.php" method="GET">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            <input type="hidden" name="payment_method" value="<?= htmlspecialchars($paymentMethod) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 30px;">
                <?php
                $options = [
                    'gross_sales' => 'Gross Sales',
                    'gross_profits' => 'Gross Profits',
                    'net_sales' => 'Net Sales',
                    'past_year_sales' => 'Past Year Sales',
                    'sales_growth' => 'Sales Growth',
                    'year_sales_trend' => 'Year Trend',
                    'daily_sales' => 'Daily Sales',
                    'budget_target' => 'Budget Target',
                    'stock_aging_report' => 'Stock Aging Report'
                ];
                foreach ($options as $val => $label): ?>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid #eee; border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#667eea'; this.style.background='#f0f4ff'" onmouseout="this.style.borderColor='#eee'; this.style.background='transparent'">
                        <input type="checkbox" name="columns[]" value="<?= $val ?>" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #667eea;">
                        <span style="font-size: 14px; color: #333; font-weight: 500;"><?= $label ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; gap: 12px; border-top: 1px solid #eee; pt: 20px; padding-top: 20px;">
                <button type="button" onclick="toggleExportModal()" style="flex: 1; padding: 12px; border: 1px solid #ddd; background: #fff; color: #666; border-radius: 8px; cursor: pointer; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='#fff'">
                    Cancel
                </button>
                <button type="submit" style="flex: 2; padding: 12px; border: none; background: #27ae60; color: white; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-file-download"></i> Generate Excel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Simple toggle function for the modal
function toggleExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal.style.display === 'none' || modal.style.display === '') {
        modal.style.display = 'flex';
    } else {
        modal.style.display = 'none';
    }
}

// Close modal if user clicks the dark background
window.onclick = function(event) {
    const modal = document.getElementById('exportModal');
    if (event.target == modal) {
        toggleExportModal();
    }
}
</script>

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
        </main>
    </div>
</body>
</html>