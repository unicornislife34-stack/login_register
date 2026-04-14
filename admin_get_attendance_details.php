<?php
// admin_get_attendance_details.php
header('Content-Type: application/json');
include 'config.php';

$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
if (!$employee_id) {
    echo json_encode(["error" => "No employee_id provided"]);
    exit;
}

$since = date('Y-m-d', strtotime('-30 days'));
$sql = "SELECT date, status, hours_spent, remarks, correction_requested, created_at FROM attendance WHERE employee_id = $employee_id AND date >= '$since' ORDER BY date DESC";
$res = $conn->query($sql);
$details = [];
while ($row = $res->fetch_assoc()) {
    $details[] = [
        'date' => $row['date'],
        'clock_in' => '', // Not available in your table
        'clock_out' => '', // Not available in your table
        'status' => $row['status'],
        'hours_spent' => $row['hours_spent'],
        'remarks' => $row['remarks'],
        'correction_requested' => $row['correction_requested'],
        'created_at' => $row['created_at']
    ];
}
echo json_encode(["details" => $details]);