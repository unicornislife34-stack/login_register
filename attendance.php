<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

header('Content-Type: application/json');
include 'config.php';

try {
    $username = $_SESSION['username'];
    $action = $_POST['action'] ?? '';
    $date = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

function ensureAttendanceSchema($conn) {
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'break_started'");
    if (!$result || $result->num_rows === 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN break_started DATETIME NULL");
    }
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'break_total'");
    if (!$result || $result->num_rows === 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN break_total INT NOT NULL DEFAULT 0");
    }
}

ensureAttendanceSchema($conn);

if ($action === 'clock_in') {
    // Check if already clocked in today
    $check = $conn->prepare("SELECT id FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already clocked in today.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO attendance (employee_username, clock_in, date) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $now, $date);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Clocked in at ' . date('g:i A')]);
    } else {
        error_log('Clock in failed: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to clock in.']);
    }

} elseif ($action === 'clock_out') {
    // Check for active shift
    $check = $conn->prepare("SELECT id, break_started FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    $record = $result->fetch_assoc();
    if (!$record) {
        echo json_encode(['status' => 'error', 'message' => 'No active clock-in found for today.']);
        exit;
    }
    if ($record['break_started']) {
        echo json_encode(['status' => 'error', 'message' => 'End your break before clocking out.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE attendance SET clock_out = ? WHERE id = ?");
    $stmt->bind_param('si', $now, $record['id']);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Clocked out at ' . date('g:i A')]);
    } else {
        error_log('Clock out failed: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to clock out.']);
    }

} elseif ($action === 'start_break') {
    // Check for active shift
    $check = $conn->prepare("SELECT id, break_started FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    $record = $result->fetch_assoc();
    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'No active shift to start break.']);
        exit;
    }
    if ($record['break_started']) {
        echo json_encode(['success' => false, 'message' => 'Break already in progress.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE attendance SET break_started = ? WHERE id = ?");
    $stmt->bind_param('si', $now, $record['id']);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Break started at ' . date('g:i A')]);
    } else {
        error_log('Start break failed: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to start break.']);
    }

} elseif ($action === 'end_break') {
    // Check for active break
    $check = $conn->prepare("SELECT id, break_started, break_total FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    $record = $result->fetch_assoc();
    if (!$record) {
        echo json_encode(['status' => 'error', 'message' => 'No active shift to end break.']);
        exit;
    }
    if (!$record['break_started']) {
        echo json_encode(['status' => 'error', 'message' => 'No break is currently active.']);
        exit;
    }

    $breakStartTs = strtotime($record['break_started']);
    $breakSeconds = max(0, strtotime($now) - $breakStartTs);
    $newTotal = intval($record['break_total']) + $breakSeconds;
    $stmt = $conn->prepare("UPDATE attendance SET break_started = NULL, break_total = ? WHERE id = ?");
    $stmt->bind_param('ii', $newTotal, $record['id']);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Break ended. Total break time: ' . formatDuration($newTotal)]);
    } else {        error_log('End break failed: ' . $stmt->error);        echo json_encode(['success' => false, 'message' => 'Failed to end break.']);
    }

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

} catch (Exception $e) {
    error_log('Attendance action error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function formatDuration($seconds) {
    $seconds = max(0, intval($seconds));
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($hours > 0) {
        return "{$hours}h " . str_pad($minutes, 2, '0', STR_PAD_LEFT) . 'm';
    }
    return "{$minutes}m";
}
?>