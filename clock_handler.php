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
$now = date('Y-m-d H:i:s');
$scheduledStart = date('Y-m-d 09:00:00');
$standardSeconds = 8 * 3600;

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

function humanDuration($seconds) {
    $seconds = max(0, intval($seconds));
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($hours > 0) {
        return "{$hours}h " . str_pad($minutes, 2, '0', STR_PAD_LEFT) . 'm';
    }
    return "{$minutes}m";
}

function buildStatusData($record, $conn) {
    global $now, $scheduledStart, $standardSeconds;
    $status = 'none';
    $statusLabel = 'Not clocked in';
    $statusDetail = 'Tap Clock In to start your shift.';
    $workDuration = '0m';
    $breakDuration = '0m';
    $lateLabel = '—';
    $overtimeLabel = '0m';
    $requiredEnd = '—';
    $summaryStatus = 'Not clocked in';
    $lastRecord = null;

    if (!$record) {
        return compact('status', 'statusLabel', 'statusDetail', 'workDuration', 'breakDuration', 'lateLabel', 'overtimeLabel', 'requiredEnd', 'summaryStatus', 'lastRecord');
    }

    $clockIn = $record['clock_in'];
    $clockOut = $record['clock_out'];
    $breakTotal = intval($record['break_total'] ?? 0);
    $breakStarted = $record['break_started'];
    $clockInTs = strtotime($clockIn);
    $currentTs = strtotime($now);
    $lateSeconds = max(0, $clockInTs - strtotime($scheduledStart));
    $lateMinutes = ceil($lateSeconds / 60);
    if ($lateSeconds > 0) {
        $lateLabel = "Late by {$lateMinutes}m";
    } else {
        $lateLabel = 'On time';
    }

    if ($clockOut && $clockOut !== '0000-00-00 00:00:00') {
        $status = 'out';
        $clockOutTs = strtotime($clockOut);
        $workedSeconds = max(0, $clockOutTs - $clockInTs - $breakTotal);
        $workDuration = humanDuration($workedSeconds);
        $breakDuration = humanDuration($breakTotal);
        $overtime = max(0, $workedSeconds - $standardSeconds);
        $overtimeLabel = humanDuration($overtime);
        $summaryStatus = 'Clocked out';
        $statusLabel = 'Clocked OUT';
        $statusDetail = "Shift ended at " . date('g:i A', $clockOutTs) . ".";
        if ($workedSeconds < $standardSeconds) {
            $earlyMinutes = ceil(($standardSeconds - $workedSeconds) / 60);
            $lateLabel = "Left early by {$earlyMinutes}m";
        }
        $requiredEnd = date('g:i A', $clockInTs + $standardSeconds + $breakTotal);
    } else {
        $activeBreak = false;
        $breakElapsed = 0;
        if ($breakStarted) {
            $breakStartedTs = strtotime($breakStarted);
            $breakElapsed = max(0, $currentTs - $breakStartedTs);
            $activeBreak = true;
        }
        $elapsed = max(0, $currentTs - $clockInTs - $breakTotal - $breakElapsed);
        $workDuration = humanDuration($elapsed);
        $breakDuration = humanDuration($breakTotal + $breakElapsed);
        $summaryStatus = $activeBreak ? 'On break' : 'Clocked in';
        $status = $activeBreak ? 'on_break' : 'in';
        $statusLabel = $activeBreak ? 'On Break' : 'Clocked IN';
        $statusDetail = $activeBreak ? 'Break is active. End break before clocking out.' : 'You are currently on the floor.';
        $requiredEnd = date('g:i A', $clockInTs + $standardSeconds + $breakTotal + $breakElapsed);
        $overtimeLabel = $elapsed > $standardSeconds ? humanDuration($elapsed - $standardSeconds) : '0m';
        $summaryStatus = $activeBreak ? 'On Break' : 'Clocked in';
        if ($breakStarted) {
            $statusDetail = 'Break started at ' . date('g:i A', $breakStartedTs) . '.';
        }
        if ($lateSeconds > 0) {
            $lateLabel = "Late by {$lateMinutes}m";
        }
    }

    return compact('status', 'statusLabel', 'statusDetail', 'workDuration', 'breakDuration', 'lateLabel', 'overtimeLabel', 'requiredEnd', 'summaryStatus');
}

function getLastRecord($conn, $username) {
    $stmt = $conn->prepare("SELECT clock_in, clock_out, break_total FROM attendance WHERE employee_username = ? AND clock_in IS NOT NULL ORDER BY date DESC, id DESC LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

ensureAttendanceSchema($conn);

if ($action === 'clock_in') {
    $check = $conn->prepare("SELECT id FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'already_in', 'message' => 'Already clocked in today.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO attendance (employee_username, clock_in, date) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $now, $date);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Clocked in at ' . date('g:i A'), 'currentTime' => $now]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to clock in.']);
    }

} elseif ($action === 'clock_out') {
    $check = $conn->prepare("SELECT id, break_started FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    $record = $result->fetch_assoc();
    if (!$record) {
        echo json_encode(['status' => 'no_open', 'message' => 'No active clock-in found for today.']);
        exit;
    }
    if ($record['break_started']) {
        echo json_encode(['status' => 'break_active', 'message' => 'End your break before clocking out.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE attendance SET clock_out = ? WHERE id = ?");
    $stmt->bind_param('si', $now, $record['id']);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Clocked out at ' . date('g:i A'), 'currentTime' => $now]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to clock out.']);
    }

} elseif ($action === 'start_break') {
    $check = $conn->prepare("SELECT id, break_started FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    $record = $result->fetch_assoc();
    if (!$record) {
        echo json_encode(['status' => 'no_open', 'message' => 'No active shift to start break.']);
        exit;
    }
    if ($record['break_started']) {
        echo json_encode(['status' => 'already_break', 'message' => 'Break already in progress.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE attendance SET break_started = ? WHERE id = ?");
    $stmt->bind_param('si', $now, $record['id']);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Break started at ' . date('g:i A'), 'currentTime' => $now]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to start break.']);
    }

} elseif ($action === 'end_break') {
    $check = $conn->prepare("SELECT id, break_started, break_total FROM attendance WHERE employee_username = ? AND date = ? AND clock_in IS NOT NULL AND (clock_out IS NULL OR clock_out = '0000-00-00 00:00:00')");
    $check->bind_param('ss', $username, $date);
    $check->execute();
    $result = $check->get_result();
    $record = $result->fetch_assoc();
    if (!$record) {
        echo json_encode(['status' => 'no_open', 'message' => 'No active shift to end break.']);
        exit;
    }
    if (!$record['break_started']) {
        echo json_encode(['status' => 'no_break', 'message' => 'No break is currently active.']);
        exit;
    }

    $breakStartTs = strtotime($record['break_started']);
    $breakSeconds = max(0, strtotime($now) - $breakStartTs);
    $newTotal = intval($record['break_total']) + $breakSeconds;
    $stmt = $conn->prepare("UPDATE attendance SET break_started = NULL, break_total = ? WHERE id = ?");
    $stmt->bind_param('ii', $newTotal, $record['id']);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Break ended. Total break time: ' . humanDuration($newTotal), 'currentTime' => $now]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to end break.']);
    }

} elseif ($action === 'status') {
    $stmt = $conn->prepare("SELECT id, clock_in, clock_out, break_started, break_total FROM attendance WHERE employee_username = ? AND date = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('ss', $username, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();

    $statusData = buildStatusData($record, $conn);
    $lastRecord = getLastRecord($conn, $username);
    $statusData['lastRecord'] = $lastRecord ? [
        'clock_in' => $lastRecord['clock_in'],
        'clock_out' => $lastRecord['clock_out'],
        'breakDuration' => humanDuration($lastRecord['break_total'])
    ] : null;
    $statusData['toast'] = null;
    $statusData['toastType'] = 'info';

    echo json_encode($statusData);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}
?>

