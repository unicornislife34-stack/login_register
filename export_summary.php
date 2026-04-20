<?php
session_start();
if (!isset($_SESSION['username'])) { exit(); }
include 'config.php';

// Set headers for Excel (XLS) format
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Financial_Summary_" . date('Y-m-d') . ".xls");

$selected_cols = $_GET['columns'] ?? [];
if (empty($selected_cols)) { die("No data selected."); }

// 1. Fetch Basic Totals for calculations
$res = $conn->query("SELECT SUM(total_amount) as total FROM orders");
$row = $res->fetch_assoc();
$gross_sales = $row['total'] ?? 0;

// 3. Fetch Stock Aging Data
$aging_res = $conn->query("
    SELECT 
        item_name,
        batch_number,
        quantity_in_stock,
        DATEDIFF(NOW(), date_added) as days_in_stock,
        CASE 
            WHEN DATEDIFF(NOW(), date_added) <= 7 THEN 'New (Week 1)'
            WHEN DATEDIFF(NOW(), date_added) <= 14 THEN 'Week 2'
            WHEN DATEDIFF(NOW(), date_added) <= 21 THEN 'Week 3'
            WHEN DATEDIFF(NOW(), date_added) <= 30 THEN 'Month 1'
            ELSE CONCAT('Over ', FLOOR(DATEDIFF(NOW(), date_added)/30), ' months')
        END as aging_category
    FROM inventory 
    WHERE quantity_in_stock > 0 
    ORDER BY date_added ASC
");
$aging_data = [];
while ($row = $aging_res->fetch_assoc()) {
    $aging_data[] = $row;
}

// Calculations
$gross_profits = $gross_sales * 0.9; // Matches your UI logic
$net_sales = $gross_sales;
$growth = $past_year_sales > 0 ? (($gross_sales - $past_year_sales) / $past_year_sales * 100) : 0;
$budget_target = 1000000;

// Mapping the data to keys
$data_map = [
    'gross_sales' => "₱" . number_format($gross_sales, 2),
    'gross_profits' => "₱" . number_format($gross_profits, 2),
    'net_sales' => "₱" . number_format($net_sales, 2),
    'past_year_sales' => "₱" . number_format($past_year_sales, 2),
    'sales_growth' => round($growth, 2) . "%",
    'year_sales_trend' => "See Dashboard",
    'daily_sales' => "₱" . number_format($gross_sales / 30, 2), // Rough average
    'budget_target' => "₱" . number_format($budget_target, 2),
    'stock_aging_report' => count($aging_data) . " items analyzed"
];

echo "<table border='1'>";
echo "<tr>";
foreach ($selected_cols as $col) {
    echo "<th style='background-color:#eee;'>" . ucwords(str_replace('_', ' ', $col)) . "</th>";
}
echo "</tr>";

// Special handling for stock aging report
if (in_array('stock_aging_report', $selected_cols)) {
    foreach ($aging_data as $item) {
        echo "<tr>";
        foreach ($selected_cols as $col) {
            if ($col === 'stock_aging_report') {
                echo "<td>" . htmlspecialchars($item['item_name'] . " (Batch " . $item['batch_number'] . ") - " . $item['aging_category'] . " - " . $item['days_in_stock'] . " days") . "</td>";
            } else {
                echo "<td>" . ($data_map[$col] ?? 'N/A') . "</td>";
            }
        }
        echo "</tr>";
    }
} else {
    echo "<tr>";
    foreach ($selected_cols as $col) {
        echo "<td>" . ($data_map[$col] ?? 'N/A') . "</td>";
    }
    echo "</tr>";
}
echo "</table>";