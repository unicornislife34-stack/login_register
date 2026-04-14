<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get selected columns from GET
$columns = $_GET['columns'] ?? [];
if (!$columns || !is_array($columns)) {
    die("No columns selected for export.");
}

// Optional: sanitize column names (prevent SQL injection)
$validColumns = ['ticket_number', 'user', 'items', 'total_amount', 'payment_method', 'created_at'];
$columns = array_intersect($columns, $validColumns);
if (!$columns) {
    die("No valid columns selected.");
}

// Filters (optional)
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

// Build query
$selectClause = [];
foreach ($columns as $col) {
    if ($col === 'items') {
        $selectClause[] = "(SELECT GROUP_CONCAT(oi.item_name SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) AS items";
    } else {
        $selectClause[] = "o.$col";
    }
}

$query = "SELECT " . implode(", ", $selectClause) . " FROM orders o WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $searchTerm = '%' . strtolower($search) . '%';
    $query .= " AND (LOWER(o.ticket_number) LIKE ? OR LOWER(o.user) LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
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

$query .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// CSV output
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sales_export_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, $columns);

// Data rows
while ($row = $result->fetch_assoc()) {
    $data = [];
    foreach ($columns as $col) {
        $data[] = $row[$col] ?? '';
    }
    fputcsv($output, $data);
}

fclose($output);
exit();