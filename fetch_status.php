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

$username = $_SESSION['username'];
$date = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$scheduledStart = date('Y-m-d 09:00:00');
$standardSeconds = 8 * 3600;

function formatDuration($seconds) {
    $seconds = max(0, intval($seconds));
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($hours > 0) {
        return "{$hours}h " . str_pad($minutes, 2, '0', STR_PAD_LEFT) . 'm';
    }
    return "{$minutes}m";
}

function getCurrentStatus($conn, $username, $date) {
    $stmt = $conn->prepare("SELECT id, clock_in, clock_out, break_started, break_total FROM attendance WHERE employee_username = ? AND date = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('ss', $username, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

$record = getCurrentStatus($conn, $username, $date);

$status = 'Not Clocked In';
$statusDetail = 'Tap Clock In to start your shift.';
$workDuration = '0m';
$breakDuration = '0m';
$lateLabel = '';
$overtimeLabel = '0m';
$summaryStatus = 'Not clocked in';
$buttonState = [
    'canClockIn' => true,
    'canClockOut' => false,
    'canStartBreak' => false,
    'canEndBreak' => false
];

if ($record) {
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
        $status = 'Clocked Out';
        $clockOutTs = strtotime($clockOut);
        $workedSeconds = max(0, $clockOutTs - $clockInTs - $breakTotal);
        $workDuration = formatDuration($workedSeconds);
        $breakDuration = formatDuration($breakTotal);
        $overtime = max(0, $workedSeconds - $standardSeconds);
        $overtimeLabel = formatDuration($overtime);
        $summaryStatus = 'Clocked out';
        $statusDetail = "Shift ended at " . date('g:i A', $clockOutTs) . ".";
        if ($workedSeconds < $standardSeconds) {
            $earlyMinutes = ceil(($standardSeconds - $workedSeconds) / 60);
            $lateLabel = "Left early by {$earlyMinutes}m";
        }
        $buttonState = [
            'canClockIn' => true,
            'canClockOut' => false,
            'canStartBreak' => false,
            'canEndBreak' => false
        ];
    } else {
        $activeBreak = false;
        $breakElapsed = 0;
        if ($breakStarted) {
            $breakStartedTs = strtotime($breakStarted);
            $breakElapsed = max(0, $currentTs - $breakStartedTs);
            $activeBreak = true;
        }
        $elapsed = max(0, $currentTs - $clockInTs - $breakTotal - $breakElapsed);
        $workDuration = formatDuration($elapsed);
        $breakDuration = formatDuration($breakTotal + $breakElapsed);
        $summaryStatus = $activeBreak ? 'On Break' : 'Working';
        $status = $activeBreak ? 'On Break' : 'Working';
        $statusDetail = $activeBreak ? 'Break is active. End break before clocking out.' : 'You are currently on the floor.';
        $overtimeLabel = $elapsed > $standardSeconds ? formatDuration($elapsed - $standardSeconds) : '0m';
        $buttonState = [
            'canClockIn' => false,
            'canClockOut' => !$activeBreak,
            'canStartBreak' => !$activeBreak,
            'canEndBreak' => $activeBreak
        ];
        if ($breakStarted) {
            $statusDetail = 'Break started at ' . date('g:i A', $breakStartedTs) . '.';
        }
    }
}

echo json_encode([
    'status' => $status,
    'statusDetail' => $statusDetail,
    'workDuration' => $workDuration,
    'breakDuration' => $breakDuration,
    'lateLabel' => $lateLabel,
    'overtimeLabel' => $overtimeLabel,
    'summaryStatus' => $summaryStatus,
    'buttonState' => $buttonState
]);
?>