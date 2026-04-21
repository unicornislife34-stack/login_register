<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Dashboard attendance settings
$shiftStartHour = 8;
$shiftStartMinute = 0;
$shiftLengthHours = 8;
$allowedBreakSeconds = 30 * 60;

// Helper formatters
function formatHoursMinutes($seconds)
{
    $seconds = max(0, (int) $seconds);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return "{$hours}h {$minutes}m";
}

function formatClockDisplay($timestamp)
{
    return $timestamp ? date('g:i A', $timestamp) : '--';
}

function formatAverageClockTime($secondsFromMidnight)
{
    if ($secondsFromMidnight === null) {
        return '--';
    }

    $hours = floor($secondsFromMidnight / 3600);
    $minutes = floor(($secondsFromMidnight % 3600) / 60);
    $base = strtotime(sprintf('%02d:%02d:00', $hours, $minutes));
    return date('g:i A', $base);
}

function fetchOneAssocPrepared($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row;
}

function fetchAllAssocPrepared($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    return $rows;
}

// Get employee details
$username = $_SESSION['username'];
$employee = fetchOneAssocPrepared(
    $conn,
    "SELECT * FROM employee WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1",
    's',
    [$username]
);

if (!$employee) {
    $employee = ['name' => $username, 'role' => 'Staff Member', 'id' => 0];
}

// Get today's latest attendance
$today = date('Y-m-d');
$todayShiftStart = strtotime($today . sprintf(' %02d:%02d:00', $shiftStartHour, $shiftStartMinute));

$todayAttendance = fetchOneAssocPrepared(
    $conn,
    "SELECT * FROM attendance WHERE employee_username = ? AND DATE(clock_in) = ? ORDER BY clock_in DESC LIMIT 1",
    'ss',
    [$username, $today]
);

$hasClockIn = $todayAttendance && !empty($todayAttendance['clock_in']);
$hasClockOut = $todayAttendance && !empty($todayAttendance['clock_out']) && $todayAttendance['clock_out'] !== '0000-00-00 00:00:00';
$isClockedIn = $hasClockIn && !$hasClockOut;

$clockInTime = $hasClockIn ? strtotime($todayAttendance['clock_in']) : null;
$clockOutTime = $hasClockOut ? strtotime($todayAttendance['clock_out']) : null;

$breakTotalSeconds = 0;
$breakStartedAt = null;
$isOnBreak = false;

if ($todayAttendance) {
    $breakTotalSeconds = isset($todayAttendance['break_total']) && is_numeric($todayAttendance['break_total']) ? (int) $todayAttendance['break_total'] : 0;
    $breakStartedAt = !empty($todayAttendance['break_started']) ? strtotime($todayAttendance['break_started']) : null;
    $isOnBreak = $isClockedIn && !empty($breakStartedAt);
}

$currentBreakSeconds = $breakTotalSeconds;
if ($isOnBreak && $breakStartedAt) {
    $currentBreakSeconds += max(0, time() - $breakStartedAt);
}

$requiredWorkSeconds = $shiftLengthHours * 3600;
$workDurationSeconds = 0;

if ($clockInTime) {
    $workEndReference = $isClockedIn ? time() : ($clockOutTime ?: time());
    $workDurationSeconds = max(0, $workEndReference - $clockInTime - $currentBreakSeconds);
}

$remainingWorkSeconds = max(0, $requiredWorkSeconds - $workDurationSeconds);
$overtimeSeconds = max(0, $workDurationSeconds - $requiredWorkSeconds);
$progressPercent = min(100, ($requiredWorkSeconds > 0 ? ($workDurationSeconds / $requiredWorkSeconds) * 100 : 0));
$estimatedClockOutTimestamp = ($isClockedIn && $remainingWorkSeconds > 0) ? (time() + $remainingWorkSeconds) : null;

$lateMinutes = 0;
$isLate = false;

if ($clockInTime && $clockInTime > $todayShiftStart) {
    $lateMinutes = (int) floor(($clockInTime - $todayShiftStart) / 60);
    $isLate = $lateMinutes > 0;
}

$undertimeSeconds = 0;
$undertimeLabel = 'On track';
$undertimeClass = 'detector-good';

if ($hasClockIn && $hasClockOut && $workDurationSeconds < $requiredWorkSeconds) {
    $undertimeSeconds = $requiredWorkSeconds - $workDurationSeconds;
    $undertimeLabel = 'Under by ' . formatHoursMinutes($undertimeSeconds);
    $undertimeClass = 'detector-danger';
} elseif ($isClockedIn && time() > ($todayShiftStart + $requiredWorkSeconds) && $workDurationSeconds < $requiredWorkSeconds) {
    $undertimeSeconds = $requiredWorkSeconds - $workDurationSeconds;
    $undertimeLabel = 'Risk: ' . formatHoursMinutes($undertimeSeconds) . ' remaining';
    $undertimeClass = 'detector-warning';
} elseif ($overtimeSeconds > 0) {
    $undertimeLabel = 'Overtime ' . formatHoursMinutes($overtimeSeconds);
    $undertimeClass = 'detector-info';
}

$breakWarningLabel = 'Break Safe';
$breakWarningText = 'You still have break time available.';
$breakWarningClass = 'warning-safe';

if ($currentBreakSeconds >= $allowedBreakSeconds) {
    $breakWarningLabel = 'Break Limit Exceeded';
    $breakWarningText = 'You already reached or exceeded the allowed 30-minute break.';
    $breakWarningClass = 'warning-danger';
} elseif ($currentBreakSeconds >= ($allowedBreakSeconds - 5 * 60)) {
    $breakWarningLabel = 'Break Almost Full';
    $breakWarningText = 'You only have ' . max(0, (int) floor(($allowedBreakSeconds - $currentBreakSeconds) / 60)) . ' minute(s) left.';
    $breakWarningClass = 'warning-warning';
}

$remainingBreakSeconds = max(0, $allowedBreakSeconds - $currentBreakSeconds);
$remainingBreakDisplay = formatHoursMinutes($remainingBreakSeconds);

$breakHours = floor($currentBreakSeconds / 3600);
$breakMinutes = floor(($currentBreakSeconds % 3600) / 60);
$breakSecondsRemainder = $currentBreakSeconds % 60;
$breakDisplayCompact = "{$breakHours}h {$breakMinutes}m";
$breakDisplayFull = sprintf('%02d:%02d:%02d', $breakHours, $breakMinutes, $breakSecondsRemainder);
$breakProgressPercent = min(100, ($allowedBreakSeconds > 0 ? ($currentBreakSeconds / $allowedBreakSeconds) * 100 : 0));

$breaksTakenToday = ($currentBreakSeconds > 0 || $isOnBreak) ? 1 : 0;
$avgBreakDisplay = $breaksTakenToday > 0 ? gmdate('H:i:s', (int) floor($currentBreakSeconds / max(1, $breaksTakenToday))) : '--';

$employeeCode = !empty($employee['id']) ? 'EM' . str_pad((string) $employee['id'], 4, '0', STR_PAD_LEFT) : strtoupper(substr(preg_replace('/\s+/', '', $employee['name']), 0, 6));

// Weekly insights
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$weekRows = fetchAllAssocPrepared(
    $conn,
    "SELECT * FROM attendance WHERE employee_username = ? AND date BETWEEN ? AND ? ORDER BY date ASC, clock_in ASC",
    'sss',
    [$username, $weekStart, $weekEnd]
);

$dailyClockIns = [];
$dailyWorkedSeconds = [];
$dailyLateFlags = [];

foreach ($weekRows as $weekRow) {
    $rowDate = $weekRow['date'];
    $rowClockIn = !empty($weekRow['clock_in']) ? strtotime($weekRow['clock_in']) : false;
    $rowClockOut = (!empty($weekRow['clock_out']) && $weekRow['clock_out'] !== '0000-00-00 00:00:00') ? strtotime($weekRow['clock_out']) : false;
    $rowBreakTotal = isset($weekRow['break_total']) && is_numeric($weekRow['break_total']) ? (int) $weekRow['break_total'] : 0;

    if ($rowClockIn) {
        if (!isset($dailyClockIns[$rowDate]) || $rowClockIn < $dailyClockIns[$rowDate]) {
            $dailyClockIns[$rowDate] = $rowClockIn;
        }

        $rowShiftStart = strtotime($rowDate . sprintf(' %02d:%02d:00', $shiftStartHour, $shiftStartMinute));
        if (!isset($dailyLateFlags[$rowDate])) {
            $dailyLateFlags[$rowDate] = $rowClockIn > $rowShiftStart;
        }
    }

    if (!isset($dailyWorkedSeconds[$rowDate])) {
        $dailyWorkedSeconds[$rowDate] = 0;
    }

    if ($rowClockIn && $rowClockOut) {
        $dailyWorkedSeconds[$rowDate] += max(0, $rowClockOut - $rowClockIn - $rowBreakTotal);
    }
}

$weeklyDaysPresent = count($dailyClockIns);
$weeklyHoursSeconds = array_sum($dailyWorkedSeconds);
$weeklyLateCount = count(array_filter($dailyLateFlags));
$weeklyAverageClockSeconds = null;

if ($weeklyDaysPresent > 0) {
    $clockSum = 0;
    foreach ($dailyClockIns as $ts) {
        $clockSum += ((int) date('H', $ts) * 3600) + ((int) date('i', $ts) * 60);
    }
    $weeklyAverageClockSeconds = (int) floor($clockSum / $weeklyDaysPresent);
}

$shiftStatusTitle = 'NOT CLOCKED IN';
$shiftStatusSubtitle = '(Ready to Start)';
$shiftStatusDescription = 'You are ready to start your shift.';
$shiftStatusClass = 'status-offline';

if ($isOnBreak) {
    $shiftStatusTitle = 'ON BREAK';
    $shiftStatusSubtitle = '(Break Active)';
    $shiftStatusDescription = 'You are currently on break (' . $breakDisplayFull . ')';
    $shiftStatusClass = 'status-break';
} elseif ($isClockedIn) {
    $shiftStatusTitle = 'WORKING';
    $shiftStatusSubtitle = '(Shift Ongoing)';
    $shiftStatusDescription = 'You are currently working.';
    $shiftStatusClass = 'status-working';
}

$breakPanelTitle = 'CLOCK IN FIRST';
$breakPanelSubtext = 'Breaks are available after clock in.';
$breakPanelClass = 'break-idle';

if ($isOnBreak) {
    $breakPanelTitle = 'ON BREAK';
    $breakPanelSubtext = 'You are currently on break.';
    $breakPanelClass = 'break-live';
} elseif ($isClockedIn) {
    $breakPanelTitle = 'NOT ON BREAK';
    $breakPanelSubtext = 'You are currently not on break.';
    $breakPanelClass = 'break-ready';
}

$lateStatusLabel = !$hasClockIn ? 'Waiting for clock-in' : ($isLate ? 'Late by ' . $lateMinutes . ' min' : 'On time');
$lateStatusClass = !$hasClockIn ? 'detector-neutral' : ($isLate ? 'detector-danger' : 'detector-good');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - L LE JOSE</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-main: #09111f;
            --line: rgba(255, 255, 255, 0.09);
            --text-main: #f8fafc;
            --text-soft: #cbd5e1;
            --text-muted: #94a3b8;
            --cyan: #22d3ee;
        }

        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at top left, rgba(124, 58, 237, 0.22), transparent 28%),
                radial-gradient(circle at top right, rgba(34, 211, 238, 0.18), transparent 26%),
                radial-gradient(circle at bottom center, rgba(59, 130, 246, 0.14), transparent 24%),
                linear-gradient(135deg, #050a14 0%, #0a1424 45%, #08101b 100%);
            display: flex;
        }

        .sidebar {
            width: 160px;
            min-height: 100vh;
            background: linear-gradient(180deg, rgba(130, 114, 255, 0.95) 0%, rgba(109, 93, 252, 0.92) 100%);
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 10px 0 35px rgba(12, 18, 34, 0.4);
            padding: 24px 14px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .sidebar-header {
            text-align: center;
            padding: 4px 6px 0;
        }

        .sidebar-logo {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
            margin-bottom: 10px;
        }

        .sidebar-tag {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.72);
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            margin-top: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid transparent;
            font-size: 0.95rem;
            font-weight: 600;
            transition: 0.25s ease;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.16);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .nav-item i {
            width: 18px;
            text-align: center;
            font-size: 0.95rem;
        }

        .sidebar-footer {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 14px 12px;
            backdrop-filter: blur(14px);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            display: grid;
            place-items: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .user-meta {
            min-width: 0;
        }

        .user-code {
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .user-role {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.72);
        }

        .logout-btn {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 11px 12px;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.25s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.24);
        }

        .main-content {
            flex: 1;
            padding: 22px 24px 28px;
            overflow: auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            gap: 16px;
        }

        .welcome-header {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            text-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        .dashboard-shell {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .hero-grid,
        .smart-grid {
            display: grid;
            gap: 16px;
            align-items: stretch;
        }

        .hero-grid {
            grid-template-columns: 220px minmax(420px, 1fr) 260px;
        }

        .smart-grid {
            grid-template-columns: 1.25fr 1fr;
        }

        .panel {
            position: relative;
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.8) 0%, rgba(11, 18, 32, 0.88) 100%);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow:
                0 15px 40px rgba(0, 0, 0, 0.34),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            overflow: hidden;
            backdrop-filter: blur(18px);
        }

        .panel::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            border-radius: inherit;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.02);
        }

        .panel-inner {
            padding: 18px 18px 16px;
            height: 100%;
        }

        .panel-title {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.88);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .clock-card .clock-stack {
            min-height: 190px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .tiny-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .live-time {
            font-family: 'Georgia', serif;
            font-size: 3rem;
            line-height: 0.95;
            letter-spacing: 0.04em;
            text-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
        }

        .live-time .meridian {
            display: block;
            font-size: 1.9rem;
            margin-top: 8px;
        }

        .control-card .panel-inner {
            padding: 16px;
        }

        .control-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: stretch;
        }

        .control-box {
            min-height: 210px;
            border-radius: 18px;
            padding: 18px 16px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: inset 0 0 0 1px rgba(16, 24, 40, 0.06);
        }

        .shift-box {
            background: linear-gradient(180deg, rgba(246, 251, 248, 0.98) 0%, rgba(235, 245, 239, 0.98) 100%);
            color: #0f172a;
        }

        .break-box {
            background: linear-gradient(180deg, rgba(255, 248, 235, 0.98) 0%, rgba(250, 240, 223, 0.98) 100%);
            color: #0f172a;
        }

        .control-label {
            text-align: center;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(15, 23, 42, 0.52);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .control-value {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.03em;
            margin-bottom: 3px;
        }

        .control-subtext {
            text-align: center;
            font-size: 0.76rem;
            color: rgba(15, 23, 42, 0.7);
            min-height: 18px;
        }

        .status-glow {
            margin: 12px 0 8px;
            text-align: center;
            font-size: 2.15rem;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .status-working .status-glow {
            color: #29c45f;
            text-shadow: 0 0 10px rgba(41, 196, 95, 0.32), 0 0 18px rgba(41, 196, 95, 0.2);
        }

        .status-break .status-glow {
            color: #ef8c25;
            text-shadow: 0 0 10px rgba(239, 140, 37, 0.28), 0 0 18px rgba(239, 140, 37, 0.18);
        }

        .status-offline .status-glow {
            color: #64748b;
        }

        .break-ready .control-value {
            color: #d39c1b;
        }

        .break-live .control-value {
            color: #ef8c25;
            text-shadow: 0 0 12px rgba(239, 140, 37, 0.22);
        }

        .break-idle .control-value {
            color: #94a3b8;
        }

        .break-timer {
            text-align: center;
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 6px;
            letter-spacing: 0.03em;
        }

        .action-btn {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 13px 14px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.25s ease;
            letter-spacing: 0.01em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.16);
        }

        .action-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            filter: brightness(1.03);
        }

        .action-btn:disabled {
            opacity: 0.62;
            cursor: not-allowed;
            transform: none;
        }

        .action-clock {
            background: linear-gradient(90deg, #aa4df7 0%, #8b5cf6 45%, #d16cf9 100%);
        }

        .action-break {
            background: linear-gradient(90deg, #f5a000 0%, #f59e0b 48%, #f2b43c 100%);
        }

        .break-progress {
            height: 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.1);
            overflow: hidden;
            margin-top: 14px;
        }

        .break-progress-bar {
            height: 100%;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, #22c55e 0%, #84cc16 100%);
            transition: width 0.3s ease, background 0.3s ease;
        }

        .warning-chip {
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .warning-safe {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
        }

        .warning-warning {
            background: rgba(245, 158, 11, 0.16);
            color: #b45309;
        }

        .warning-danger {
            background: rgba(239, 68, 68, 0.14);
            color: #b91c1c;
        }

        .summary-card {
            box-shadow:
                0 0 0 1px rgba(34, 211, 238, 0.24),
                0 0 24px rgba(34, 211, 238, 0.2),
                0 14px 30px rgba(0, 0, 0, 0.32);
        }

        .summary-card .panel-inner {
            padding: 18px 16px 14px;
        }

        .summary-main-label {
            font-size: 0.74rem;
            color: rgba(255, 255, 255, 0.92);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .summary-copy {
            font-size: 0.9rem;
            color: #e2e8f0;
            font-weight: 700;
            line-height: 1.35;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .summary-total {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            color: #33d0f1;
            text-shadow: 0 0 18px rgba(51, 208, 241, 0.28);
            margin-bottom: 16px;
        }

        .summary-stats {
            display: grid;
            gap: 8px;
        }

        .mini-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 8px 10px;
            font-size: 0.8rem;
        }

        .mini-stat-label {
            color: #dbeafe;
        }

        .mini-stat-value {
            color: #fff;
            font-weight: 700;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 14px;
        }

        .metric-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .metric-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
        }

        .metric-sub {
            font-size: 0.8rem;
            color: var(--text-soft);
            margin-top: 6px;
        }

        .progress-strip {
            margin-top: 14px;
            height: 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, #22c55e 0%, #14b8a6 100%);
            transition: width 0.3s ease;
        }

        .detector-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 14px;
        }

        .detector-chip {
            border-radius: 14px;
            padding: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .detector-good {
            background: rgba(34, 197, 94, 0.12);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.18);
        }

        .detector-warning {
            background: rgba(245, 158, 11, 0.12);
            color: #fcd34d;
            border-color: rgba(245, 158, 11, 0.18);
        }

        .detector-danger {
            background: rgba(239, 68, 68, 0.12);
            color: #fda4af;
            border-color: rgba(239, 68, 68, 0.18);
        }

        .detector-info {
            background: rgba(34, 211, 238, 0.12);
            color: #67e8f9;
            border-color: rgba(34, 211, 238, 0.18);
        }

        .detector-neutral {
            background: rgba(148, 163, 184, 0.12);
            color: #cbd5e1;
            border-color: rgba(148, 163, 184, 0.18);
        }

        .history-card .panel-inner {
            padding: 16px;
        }

        .history-wrap {
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.025);
        }

        .history-table-scroll {
            max-height: 310px;
            overflow: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        thead {
            background: rgba(255, 255, 255, 0.05);
        }

        th {
            text-align: left;
            padding: 14px 16px;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #cbd5e1;
            font-weight: 700;
        }

        td {
            padding: 14px 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.9rem;
            color: #f8fafc;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .ongoing {
            color: #67e8f9;
        }

        .empty-state {
            text-align: center;
            padding: 26px;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .feedback-line {
            margin-top: 8px;
            text-align: center;
            min-height: 18px;
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .feedback-line.error {
            color: #fda4af;
        }

        .feedback-line.success {
            color: #86efac;
        }

        .toast-stack {
            position: fixed;
            top: 18px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            min-width: 280px;
            max-width: 360px;
            padding: 14px 16px;
            border-radius: 14px;
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 18px 30px rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.08);
            animation: toastIn 0.25s ease;
            pointer-events: auto;
        }

        .toast.success {
            background: linear-gradient(135deg, rgba(21, 128, 61, 0.95), rgba(22, 163, 74, 0.92));
        }

        .toast.error {
            background: linear-gradient(135deg, rgba(185, 28, 28, 0.96), rgba(239, 68, 68, 0.92));
        }

        .toast.info {
            background: linear-gradient(135deg, rgba(8, 47, 73, 0.96), rgba(14, 116, 144, 0.92));
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateY(-8px) translateX(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0) translateX(0);
            }
        }

        @media (max-width: 1260px) {
            .hero-grid {
                grid-template-columns: 1fr;
            }

            .smart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                border-right: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .main-content {
                padding: 18px 14px;
            }

            .control-layout,
            .metric-grid,
            .detector-row {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .welcome-header {
                font-size: 1.65rem;
            }
        }

        @media (max-width: 640px) {
            .sidebar-nav {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .sidebar-footer {
                margin-top: 8px;
            }

            .live-time {
                font-size: 2.4rem;
            }

            .summary-total {
                font-size: 2.35rem;
            }

            .toast {
                min-width: 240px;
                max-width: calc(100vw - 30px);
            }
        }
    </style>
</head>
<body>
    <div id="toastStack" class="toast-stack"></div>

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">L LE JOSE</div>
            <div class="sidebar-tag">Employee Portal</div>
        </div>

        <nav class="sidebar-nav">
            <a href="employee_dashboard.php" class="nav-item active">
                <i class="fas fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="pos.php" class="nav-item">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>
            <a href="my_payroll.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>My Payroll</span>
            </a>
            <a href="request_time_off.php" class="nav-item">
                <i class="fas fa-calendar-days"></i>
                <span>Request Time Off</span>
            </a>
            <a href="my_profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-meta">
                    <div class="user-code"><?php echo htmlspecialchars($employeeCode); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($employee['role']); ?></div>
                </div>
            </div>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-right-from-bracket"></i> Logout
            </button>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="welcome-header">Welcome, <?php echo htmlspecialchars($employeeCode); ?>.</div>
        </div>

        <div class="dashboard-shell">
            <section class="hero-grid">
                <div class="panel clock-card">
                    <div class="panel-inner">
                        <div class="panel-title">Attendance Clock</div>
                        <div class="clock-stack">
                            <div class="tiny-label">Time Now</div>
                            <div id="currentTime" class="live-time">00:00:00<span class="meridian">AM</span></div>
                        </div>
                    </div>
                </div>

                <div class="panel control-card">
                    <div class="panel-inner">
                        <div class="panel-title">Shift & Break Control</div>
                        <div class="control-layout">
                            <div class="control-box shift-box">
                                <div>
                                    <div class="control-label">Shift Status</div>
                                    <div id="shiftStatusBox" class="<?php echo $shiftStatusClass; ?>">
                                        <div id="shiftStatusValue" class="status-glow"><?php echo $shiftStatusTitle; ?></div>
                                        <div id="shiftStatusSubtext" class="control-subtext"><?php echo $shiftStatusSubtitle; ?></div>
                                    </div>
                                    <div id="shiftStatusDescription" class="feedback-line"><?php echo htmlspecialchars($shiftStatusDescription); ?></div>
                                </div>
                                <button id="clockBtn" class="action-btn action-clock" onclick="toggleClock()" <?php echo $isOnBreak ? 'disabled' : ''; ?>>
                                    <i class="fas <?php echo $isClockedIn ? 'fa-right-from-bracket' : 'fa-right-to-bracket'; ?>"></i>
                                    <span id="clockBtnLabel"><?php echo $isClockedIn ? 'Clock Out' : 'Clock In'; ?></span>
                                </button>
                            </div>

                            <div class="control-box break-box">
                                <div>
                                    <div class="control-label">Break Timer</div>
                                    <div id="breakStatusBox" class="<?php echo $breakPanelClass; ?>">
                                        <div id="breakStatusValue" class="control-value"><?php echo $breakPanelTitle; ?></div>
                                        <div id="breakTimer" class="break-timer"><?php echo $breakDisplayCompact . ' ' . $breakSecondsRemainder . 's'; ?></div>
                                    </div>
                                    <div id="breakStatusDescription" class="feedback-line"><?php echo htmlspecialchars($breakPanelSubtext); ?></div>
                                    <div class="break-progress">
                                        <div id="breakProgressBar" class="break-progress-bar" style="width: <?php echo $breakProgressPercent; ?>%;"></div>
                                    </div>
                                    <div id="breakWarningChip" class="warning-chip <?php echo $breakWarningClass; ?>">
                                        <i class="fas fa-triangle-exclamation"></i>
                                        <span id="breakWarningText"><?php echo htmlspecialchars($breakWarningLabel); ?></span>
                                    </div>
                                </div>
                                <button id="breakBtn" class="action-btn action-break" onclick="handleBreakClick()" <?php echo !$isClockedIn ? 'disabled' : ''; ?>>
                                    <i class="fas <?php echo $isOnBreak ? 'fa-play' : 'fa-mug-hot'; ?>"></i>
                                    <span id="breakBtnLabel"><?php echo $isOnBreak ? 'End Break' : 'Start Break'; ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel summary-card">
                    <div class="panel-inner">
                        <div class="summary-main-label">Today's Total Break</div>
                        <div class="summary-copy">Your total accumulated break time</div>
                        <div id="breakSummary" class="summary-total"><?php echo $breakDisplayCompact; ?></div>

                        <div class="summary-stats">
                            <div class="mini-stat">
                                <span class="mini-stat-label">Breaks taken:</span>
                                <span id="breaksTakenValue" class="mini-stat-value"><?php echo $breaksTakenToday; ?></span>
                            </div>
                            <div class="mini-stat">
                                <span class="mini-stat-label">Remaining allowed:</span>
                                <span id="remainingBreakValue" class="mini-stat-value"><?php echo $remainingBreakDisplay; ?></span>
                            </div>
                            <div class="mini-stat">
                                <span class="mini-stat-label">Avg. break duration:</span>
                                <span id="avgBreakValue" class="mini-stat-value"><?php echo $avgBreakDisplay; ?></span>
                            </div>
                            <div class="mini-stat">
                                <span class="mini-stat-label">Break warning:</span>
                                <span id="breakWarningSummary" class="mini-stat-value"><?php echo htmlspecialchars($breakWarningLabel); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="smart-grid">
                <div class="panel">
                    <div class="panel-inner">
                        <div class="panel-title">Live Shift Progress</div>

                        <div class="metric-grid">
                            <div class="metric-card">
                                <div class="metric-title">Worked Today</div>
                                <div id="workedTodayValue" class="metric-value"><?php echo formatHoursMinutes($workDurationSeconds); ?></div>
                                <div class="metric-sub">Net worked hours today</div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-title">Remaining to Complete</div>
                                <div id="remainingWorkValue" class="metric-value"><?php echo formatHoursMinutes($remainingWorkSeconds); ?></div>
                                <div class="metric-sub">Toward your <?php echo $shiftLengthHours; ?>h shift</div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-title">Expected Clock Out</div>
                                <div id="expectedClockOutValue" class="metric-value"><?php echo formatClockDisplay($estimatedClockOutTimestamp); ?></div>
                                <div class="metric-sub"><?php echo $isOnBreak ? 'May move later while on break' : 'Estimated based on remaining hours'; ?></div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-title">Overtime</div>
                                <div id="overtimeValue" class="metric-value"><?php echo formatHoursMinutes($overtimeSeconds); ?></div>
                                <div class="metric-sub">Starts after required shift hours</div>
                            </div>
                        </div>

                        <div class="progress-strip">
                            <div id="shiftProgressBar" class="progress-fill" style="width: <?php echo $progressPercent; ?>%;"></div>
                        </div>

                        <div class="detector-row">
                            <div id="lateDetector" class="detector-chip <?php echo $lateStatusClass; ?>">
                                <i class="fas fa-clock"></i>
                                <span>Late Detector: <?php echo htmlspecialchars($lateStatusLabel); ?></span>
                            </div>
                            <div id="undertimeDetector" class="detector-chip <?php echo $undertimeClass; ?>">
                                <i class="fas fa-chart-line"></i>
                                <span>Shift Status: <?php echo htmlspecialchars($undertimeLabel); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-inner">
                        <div class="panel-title">Weekly Insights</div>

                        <div class="metric-grid">
                            <div class="metric-card">
                                <div class="metric-title">Days Present</div>
                                <div class="metric-value"><?php echo $weeklyDaysPresent; ?></div>
                                <div class="metric-sub"><?php echo date('M j', strtotime($weekStart)); ?> - <?php echo date('M j', strtotime($weekEnd)); ?></div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-title">Total Hours</div>
                                <div class="metric-value"><?php echo formatHoursMinutes($weeklyHoursSeconds); ?></div>
                                <div class="metric-sub">Completed time this week</div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-title">Average Clock In</div>
                                <div class="metric-value"><?php echo formatAverageClockTime($weeklyAverageClockSeconds); ?></div>
                                <div class="metric-sub">Based on first clock-in each day</div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-title">Late Arrivals</div>
                                <div class="metric-value"><?php echo $weeklyLateCount; ?></div>
                                <div class="metric-sub"><?php echo $weeklyLateCount > 0 ? 'Needs improvement' : 'Perfect this week'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="attendanceHistorySection" class="panel history-card">
                <div class="panel-inner">
                    <div class="panel-title">Attendance History</div>

                    <div class="history-wrap">
                        <div class="history-table-scroll">
                            <table id="attendanceHistory">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Break Total</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $historyRows = fetchAllAssocPrepared(
                                        $conn,
                                        "SELECT * FROM attendance WHERE employee_username = ? ORDER BY clock_in DESC LIMIT 20",
                                        's',
                                        [$username]
                                    );

                                    if (!empty($historyRows)) {
                                        foreach ($historyRows as $row) {
                                            $clockInTimestamp = !empty($row['clock_in']) ? strtotime($row['clock_in']) : false;
                                            $clockOutTimestamp = (!empty($row['clock_out']) && $row['clock_out'] !== '0000-00-00 00:00:00') ? strtotime($row['clock_out']) : false;
                                            $breakTotal = isset($row['break_total']) && is_numeric($row['break_total']) ? (int) $row['break_total'] : 0;

                                            $rowIsOnBreak = !empty($row['break_started']) && !$clockOutTimestamp;
                                            $rowCurrentBreak = $breakTotal;

                                            if ($rowIsOnBreak) {
                                                $rowBreakStarted = strtotime($row['break_started']);
                                                if ($rowBreakStarted) {
                                                    $rowCurrentBreak += max(0, time() - $rowBreakStarted);
                                                }
                                            }

                                            $inTime = $clockInTimestamp ? date('g:i A', $clockInTimestamp) : '-';
                                            $outTime = $clockOutTimestamp ? date('g:i A', $clockOutTimestamp) : 'Ongoing';
                                            $breakFormatted = $rowCurrentBreak > 0 ? gmdate('H:i:s', $rowCurrentBreak) : '-';

                                            if ($clockInTimestamp && $clockOutTimestamp) {
                                                $totalSeconds = max(0, $clockOutTimestamp - $clockInTimestamp - $breakTotal);
                                                $duration = gmdate('H:i:s', $totalSeconds);
                                                $durationClass = '';
                                            } else {
                                                $duration = 'Ongoing';
                                                $durationClass = 'ongoing';
                                            }

                                            echo '<tr>';
                                            echo '<td>' . date('M j', strtotime($row['date'])) . '</td>';
                                            echo '<td>' . $inTime . '</td>';
                                            echo '<td>' . $outTime . '</td>';
                                            echo '<td>' . $breakFormatted . '</td>';
                                            echo '<td class="' . $durationClass . '">' . $duration . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="empty-state">No attendance records yet.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        const dashboardState = {
            isClockedIn: <?php echo $isClockedIn ? 'true' : 'false'; ?>,
            isOnBreak: <?php echo $isOnBreak ? 'true' : 'false'; ?>,
            breakSeconds: <?php echo (int) $currentBreakSeconds; ?>,
            workSeconds: <?php echo (int) $workDurationSeconds; ?>,
            allowedBreakSeconds: <?php echo (int) $allowedBreakSeconds; ?>,
            requiredWorkSeconds: <?php echo (int) $requiredWorkSeconds; ?>,
            lateMinutes: <?php echo (int) $lateMinutes; ?>,
            breaksTaken: <?php echo (int) $breaksTakenToday; ?>,
            requestInFlight: false
        };

        function showToast(message, type = 'info') {
            const stack = document.getElementById('toastStack');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            stack.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-6px)';
                toast.style.transition = '0.2s ease';
            }, 2800);

            setTimeout(() => {
                toast.remove();
            }, 3200);
        }

        function restoreToastFromStorage() {
            const message = sessionStorage.getItem('attendance_toast_message');
            const type = sessionStorage.getItem('attendance_toast_type') || 'success';

            if (message) {
                showToast(message, type);
                sessionStorage.removeItem('attendance_toast_message');
                sessionStorage.removeItem('attendance_toast_type');
            }
        }

        function formatClock(now) {
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const meridian = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;

            return `${String(hours).padStart(2, '0')}:${minutes}:${seconds}<span class="meridian">${meridian}</span>`;
        }

        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').innerHTML = formatClock(now);
        }

        function formatHoursMinutes(seconds) {
            const safe = Math.max(0, seconds);
            const hours = Math.floor(safe / 3600);
            const minutes = Math.floor((safe % 3600) / 60);
            return `${hours}h ${minutes}m`;
        }

        function formatBreakTimer(totalSeconds) {
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            return `${hours}h ${minutes}m ${seconds}s`;
        }

        function formatClockTimeFromNow(secondsAhead) {
            if (secondsAhead <= 0) {
                return '--';
            }

            const estimate = new Date(Date.now() + (secondsAhead * 1000));
            return estimate.toLocaleTimeString('en-PH', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function updateBreakWarningUI() {
            const breakWarningChip = document.getElementById('breakWarningChip');
            const breakWarningText = document.getElementById('breakWarningText');
            const breakWarningSummary = document.getElementById('breakWarningSummary');
            const breakProgressBar = document.getElementById('breakProgressBar');

            const breakPercent = Math.min(100, (dashboardState.breakSeconds / dashboardState.allowedBreakSeconds) * 100);
            breakProgressBar.style.width = `${breakPercent}%`;

            let warningLabel = 'Break Safe';
            let warningClass = 'warning-safe';
            let warningBar = 'linear-gradient(90deg, #22c55e 0%, #84cc16 100%)';

            if (dashboardState.breakSeconds >= dashboardState.allowedBreakSeconds) {
                warningLabel = 'Break Limit Exceeded';
                warningClass = 'warning-danger';
                warningBar = 'linear-gradient(90deg, #ef4444 0%, #f97316 100%)';
            } else if (dashboardState.breakSeconds >= dashboardState.allowedBreakSeconds - 300) {
                warningLabel = 'Break Almost Full';
                warningClass = 'warning-warning';
                warningBar = 'linear-gradient(90deg, #f59e0b 0%, #f97316 100%)';
            }

            breakWarningChip.className = `warning-chip ${warningClass}`;
            breakWarningText.textContent = warningLabel;
            breakWarningSummary.textContent = warningLabel;
            breakProgressBar.style.background = warningBar;
        }

        function updateShiftInsights() {
            const workedTodayValue = document.getElementById('workedTodayValue');
            const remainingWorkValue = document.getElementById('remainingWorkValue');
            const expectedClockOutValue = document.getElementById('expectedClockOutValue');
            const overtimeValue = document.getElementById('overtimeValue');
            const shiftProgressBar = document.getElementById('shiftProgressBar');
            const lateDetector = document.getElementById('lateDetector');
            const undertimeDetector = document.getElementById('undertimeDetector');

            const remainingWork = Math.max(0, dashboardState.requiredWorkSeconds - dashboardState.workSeconds);
            const overtime = Math.max(0, dashboardState.workSeconds - dashboardState.requiredWorkSeconds);
            const progress = Math.min(100, (dashboardState.workSeconds / dashboardState.requiredWorkSeconds) * 100);

            workedTodayValue.textContent = formatHoursMinutes(dashboardState.workSeconds);
            remainingWorkValue.textContent = formatHoursMinutes(remainingWork);
            overtimeValue.textContent = formatHoursMinutes(overtime);
            expectedClockOutValue.textContent = dashboardState.isClockedIn ? formatClockTimeFromNow(remainingWork) : '--';
            shiftProgressBar.style.width = `${progress}%`;

            if (!dashboardState.isClockedIn && dashboardState.workSeconds === 0) {
                lateDetector.className = 'detector-chip detector-neutral';
                lateDetector.innerHTML = '<i class="fas fa-clock"></i><span>Late Detector: Waiting for clock-in</span>';
            } else if (dashboardState.lateMinutes > 0) {
                lateDetector.className = 'detector-chip detector-danger';
                lateDetector.innerHTML = `<i class="fas fa-clock"></i><span>Late Detector: Late by ${dashboardState.lateMinutes} min</span>`;
            } else {
                lateDetector.className = 'detector-chip detector-good';
                lateDetector.innerHTML = '<i class="fas fa-clock"></i><span>Late Detector: On time</span>';
            }

            if (overtime > 0) {
                undertimeDetector.className = 'detector-chip detector-info';
                undertimeDetector.innerHTML = `<i class="fas fa-chart-line"></i><span>Shift Status: Overtime ${formatHoursMinutes(overtime)}</span>`;
            } else if (dashboardState.isClockedIn && remainingWork > 0 && new Date().getHours() >= 16) {
                undertimeDetector.className = 'detector-chip detector-warning';
                undertimeDetector.innerHTML = `<i class="fas fa-chart-line"></i><span>Shift Status: Risk ${formatHoursMinutes(remainingWork)} remaining</span>`;
            } else if (!dashboardState.isClockedIn && remainingWork > 0 && dashboardState.workSeconds > 0) {
                undertimeDetector.className = 'detector-chip detector-danger';
                undertimeDetector.innerHTML = `<i class="fas fa-chart-line"></i><span>Shift Status: Under by ${formatHoursMinutes(remainingWork)}</span>`;
            } else {
                undertimeDetector.className = 'detector-chip detector-good';
                undertimeDetector.innerHTML = '<i class="fas fa-chart-line"></i><span>Shift Status: On track</span>';
            }
        }

        function updateDashboardUI(message = '', type = '') {
            const shiftStatusBox = document.getElementById('shiftStatusBox');
            const shiftStatusValue = document.getElementById('shiftStatusValue');
            const shiftStatusSubtext = document.getElementById('shiftStatusSubtext');
            const shiftStatusDescription = document.getElementById('shiftStatusDescription');

            const breakStatusBox = document.getElementById('breakStatusBox');
            const breakStatusValue = document.getElementById('breakStatusValue');
            const breakStatusDescription = document.getElementById('breakStatusDescription');
            const breakTimer = document.getElementById('breakTimer');

            const clockBtn = document.getElementById('clockBtn');
            const clockBtnLabel = document.getElementById('clockBtnLabel');
            const breakBtn = document.getElementById('breakBtn');
            const breakBtnLabel = document.getElementById('breakBtnLabel');

            const breakSummary = document.getElementById('breakSummary');
            const remainingBreakValue = document.getElementById('remainingBreakValue');
            const breaksTakenValue = document.getElementById('breaksTakenValue');
            const avgBreakValue = document.getElementById('avgBreakValue');

            shiftStatusDescription.className = `feedback-line ${type}`;
            breakStatusDescription.className = 'feedback-line';

            breakTimer.textContent = formatBreakTimer(dashboardState.breakSeconds);
            breakSummary.textContent = formatHoursMinutes(dashboardState.breakSeconds);
            remainingBreakValue.textContent = formatHoursMinutes(Math.max(0, dashboardState.allowedBreakSeconds - dashboardState.breakSeconds));
            breaksTakenValue.textContent = dashboardState.breaksTaken > 0 ? dashboardState.breaksTaken : 0;
            avgBreakValue.textContent = dashboardState.breaksTaken > 0 ? formatBreakTimer(Math.floor(dashboardState.breakSeconds / dashboardState.breaksTaken)) : '--';

            shiftStatusBox.className = '';
            breakStatusBox.className = '';

            if (!dashboardState.isClockedIn) {
                shiftStatusBox.classList.add('status-offline');
                shiftStatusValue.textContent = 'NOT CLOCKED IN';
                shiftStatusSubtext.textContent = '(Ready to Start)';
                shiftStatusDescription.textContent = message || 'You are ready to start your shift.';

                breakStatusBox.classList.add('break-idle');
                breakStatusValue.textContent = 'CLOCK IN FIRST';
                breakStatusDescription.textContent = 'Breaks are available after clock in.';
                breakBtnLabel.textContent = 'Start Break';
                breakBtn.querySelector('i').className = 'fas fa-mug-hot';

                clockBtnLabel.textContent = 'Clock In';
                clockBtn.querySelector('i').className = 'fas fa-right-to-bracket';

                clockBtn.disabled = dashboardState.requestInFlight;
                breakBtn.disabled = true;

                updateBreakWarningUI();
                updateShiftInsights();
                return;
            }

            if (dashboardState.isOnBreak) {
                shiftStatusBox.classList.add('status-break');
                shiftStatusValue.textContent = 'ON BREAK';
                shiftStatusSubtext.textContent = '(Break Active)';
                shiftStatusDescription.textContent = message || `You are currently on break (${formatBreakTimer(dashboardState.breakSeconds)})`;

                breakStatusBox.classList.add('break-live');
                breakStatusValue.textContent = 'ON BREAK';
                breakStatusDescription.textContent = 'Break warning is tracked live.';
                breakBtnLabel.textContent = 'End Break';
                breakBtn.querySelector('i').className = 'fas fa-play';

                clockBtnLabel.textContent = 'Clock Out';
                clockBtn.querySelector('i').className = 'fas fa-right-from-bracket';

                clockBtn.disabled = true;
                breakBtn.disabled = dashboardState.requestInFlight;

                updateBreakWarningUI();
                updateShiftInsights();
                return;
            }

            shiftStatusBox.classList.add('status-working');
            shiftStatusValue.textContent = 'WORKING';
            shiftStatusSubtext.textContent = '(Shift Ongoing)';
            shiftStatusDescription.textContent = message || 'You are currently working.';

            breakStatusBox.classList.add('break-ready');
            breakStatusValue.textContent = 'NOT ON BREAK';
            breakStatusDescription.textContent = 'You are currently not on break.';
            breakBtnLabel.textContent = 'Start Break';
            breakBtn.querySelector('i').className = 'fas fa-mug-hot';

            clockBtnLabel.textContent = 'Clock Out';
            clockBtn.querySelector('i').className = 'fas fa-right-from-bracket';

            clockBtn.disabled = dashboardState.requestInFlight;
            breakBtn.disabled = dashboardState.requestInFlight;

            updateBreakWarningUI();
            updateShiftInsights();
        }

        async function sendAttendanceAction(action) {
            const response = await fetch('attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ action }).toString()
            });

            const result = await response.json();

            if (!result.success && result.status !== 'success') {
                throw new Error(result.message || 'Request failed.');
            }

            return result;
        }

        async function toggleClock() {
            if (dashboardState.requestInFlight || dashboardState.isOnBreak) {
                return;
            }

            dashboardState.requestInFlight = true;
            updateDashboardUI();

            const action = dashboardState.isClockedIn ? 'clock_out' : 'clock_in';

            try {
                const result = await sendAttendanceAction(action);
                sessionStorage.setItem('attendance_toast_message', result.message || 'Attendance updated successfully.');
                sessionStorage.setItem('attendance_toast_type', 'success');
                window.location.reload();
            } catch (error) {
                dashboardState.requestInFlight = false;
                updateDashboardUI(error.message, 'error');
                showToast(error.message, 'error');
            }
        }

        async function handleBreakClick() {
            if (dashboardState.requestInFlight || !dashboardState.isClockedIn) {
                return;
            }

            dashboardState.requestInFlight = true;
            updateDashboardUI();

            const action = dashboardState.isOnBreak ? 'end_break' : 'start_break';

            try {
                const result = await sendAttendanceAction(action);
                sessionStorage.setItem('attendance_toast_message', result.message || 'Break updated successfully.');
                sessionStorage.setItem('attendance_toast_type', 'success');
                window.location.reload();
            } catch (error) {
                dashboardState.requestInFlight = false;
                updateDashboardUI(error.message, 'error');
                showToast(error.message, 'error');
            }
        }

        restoreToastFromStorage();
        updateTime();
        updateDashboardUI();

        setInterval(updateTime, 1000);
        setInterval(() => {
            if (dashboardState.isClockedIn && !dashboardState.isOnBreak) {
                dashboardState.workSeconds += 1;
            }

            if (dashboardState.isOnBreak) {
                dashboardState.breakSeconds += 1;
            }

            updateDashboardUI();
        }, 1000);
    </script>
</body>
</html>
