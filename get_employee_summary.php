<?php
// get_employee_summary.php
header('Content-Type: application/json');
include 'config.php';

$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
if (!$employee_id) {
    echo json_encode(["error" => "No employee_id provided"]);
    exit;
}

// Get summary for the last 30 days
$summary = [
    "Employee" => "",
    "Present" => 0,
    "Absent" => 0,
    "Late" => 0,
    "Hours" => 0
];

// Get employee name
$res = $conn->query("SELECT name FROM employee WHERE id = $employee_id");
if ($row = $res->fetch_assoc()) {
    $summary["Employee"] = $row['name'];
}

$since = date('Y-m-d', strtotime('-30 days'));
$sql = "SELECT status, SUM(hours_spent) as total_hours, COUNT(*) as count FROM attendance WHERE employee_id = $employee_id AND date >= '$since' GROUP BY status";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    if (strtolower($row['status']) === 'present') $summary['Present'] += $row['count'];
    if (strtolower($row['status']) === 'absent') $summary['Absent'] += $row['count'];
    if (strtolower($row['status']) === 'late') $summary['Late'] += $row['count'];
    $summary['Hours'] += floatval($row['total_hours']);
}

echo json_encode($summary);