<?php
session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

include 'config.php';

$username = $_SESSION['username'];
$action = $_POST['action'] ?? '';
$date = date('Y-m-d');

if ($action === 'clock_in') {
    // Check if already clocked in today
    $check = $conn->prepare("SELECT id FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'already_in', 'message' => 'Already clocked in today']);
        exit;
    }
    
    // Insert new record
    $clock_time = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO attendance (employee_username, clock_in, date) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $clock_time, $date);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Clocked in at ' . $clock_time]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to clock in']);
    }
    
} elseif ($action === 'clock_out') {
    // Find today's open record
    $check = $conn->prepare("SELECT id FROM attendance WHERE employee_username = ? AND date = ? AND clock_out IS NULL");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'no_open', 'message' => 'No open clock-in today']);
        exit;
    }
    
    $id = $result->fetch_assoc()['id'];
    $clock_time = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("UPDATE attendance SET clock_out = ? WHERE id = ?");
    $stmt->bind_param('si', $clock_time, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Clocked out at ' . $clock_time]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to clock out']);
    }
    
} elseif ($action === 'status') {
    // Get today status
    $stmt = $conn->prepare("SELECT clock_in, clock_out FROM attendance WHERE employee_username = ? AND date = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('ss', $username, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    
    if ($record && $record['clock_in'] && !$record['clock_out']) {
        echo json_encode(['status' => 'in', 'clock_in' => $record['clock_in']]);
    } elseif ($record && $record['clock_in'] && $record['clock_out']) {
        echo json_encode(['status' => 'out', 'clock_in' => $record['clock_in'], 'clock_out' => $record['clock_out']]);
    } else {
        echo json_encode(['status' => 'none']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}
?>

