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

// 2. Fetch Past Year Sales
$past_res = $conn->query("SELECT SUM(total_amount) as past FROM orders WHERE YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 YEAR))");
$past_row = $past_res->fetch_assoc();
$past_year_sales = $past_row['past'] ?? 0;

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
    'budget' => "₱" . number_format($budget_target, 2)
];

echo "<table border='1'>";
echo "<tr>";
foreach ($selected_cols as $col) {
    echo "<th style='background-color:#eee;'>" . ucwords(str_replace('_', ' ', $col)) . "</th>";
}
echo "</tr><tr>";
foreach ($selected_cols as $col) {
    echo "<td>" . ($data_map[$col] ?? 'N/A') . "</td>";
}
echo "</tr></table>";