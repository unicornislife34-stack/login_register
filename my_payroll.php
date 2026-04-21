<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$employeeQuery = "SELECT * FROM employee WHERE LOWER(TRIM(name)) = LOWER(TRIM('$username')) LIMIT 1";
$employeeResult = mysqli_query($conn, $employeeQuery);
$employee = mysqli_fetch_assoc($employeeResult);

if (!$employee) {
    $nameEscaped = mysqli_real_escape_string($conn, $username);
    mysqli_query($conn, "INSERT INTO employee (name, role, attendance_days, salary, date_hired) VALUES ('$nameEscaped', 'Staff Member', 0, 0, NOW())");
    $employeeId = mysqli_insert_id($conn);
    $employee = [
        'id' => $employeeId,
        'name' => $username,
        'role' => 'Staff Member',
        'attendance_days' => 0,
        'salary' => 0,
        'date_hired' => date('Y-m-d H:i:s')
    ];
} else {
    $employeeId = (int) $employee['id'];
}

$currentMonth = date('Y-m');
$salary = isset($employee['salary']) ? (float) $employee['salary'] : 0;
$adminAttendanceDays = isset($employee['attendance_days']) ? (int) $employee['attendance_days'] : 0;
$workingDaysPerMonth = 22;
$dailyRate = $workingDaysPerMonth > 0 ? $salary / $workingDaysPerMonth : 0;

$attendanceMonths = [];
$monthResult = mysqli_query($conn, "
    SELECT DATE_FORMAT(date, '%Y-%m') AS payroll_month
    FROM attendance
    WHERE employee_username = '" . mysqli_real_escape_string($conn, $username) . "'
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY payroll_month DESC
    LIMIT 6
");
if ($monthResult) {
    while ($monthRow = mysqli_fetch_assoc($monthResult)) {
        $attendanceMonths[] = $monthRow['payroll_month'];
    }
}
if (!in_array($currentMonth, $attendanceMonths, true)) {
    array_unshift($attendanceMonths, $currentMonth);
}
$attendanceMonths = array_values(array_unique($attendanceMonths));

$payrollHistory = [];
foreach ($attendanceMonths as $month) {
    $monthEscaped = mysqli_real_escape_string($conn, $month);
    $attendanceSummaryResult = mysqli_query($conn, "
        SELECT
            COUNT(DISTINCT date) AS days_present,
            SUM(
                CASE
                    WHEN clock_in IS NOT NULL AND clock_out IS NOT NULL
                    THEN GREATEST(TIMESTAMPDIFF(SECOND, clock_in, clock_out) - COALESCE(break_total, 0), 0)
                    ELSE 0
                END
            ) AS worked_seconds
        FROM attendance
        WHERE employee_username = '" . mysqli_real_escape_string($conn, $username) . "'
            AND DATE_FORMAT(date, '%Y-%m') = '$monthEscaped'
    ");
    $attendanceSummary = $attendanceSummaryResult ? mysqli_fetch_assoc($attendanceSummaryResult) : ['days_present' => 0, 'worked_seconds' => 0];

    $daysPresent = (int) ($attendanceSummary['days_present'] ?? 0);
    $workedSeconds = (int) ($attendanceSummary['worked_seconds'] ?? 0);
    $estimatedGross = $dailyRate * $daysPresent;
    $netPay = max(0, $estimatedGross);

    $payrollHistory[] = [
        'month' => $month,
        'days_present' => $daysPresent,
        'worked_seconds' => $workedSeconds,
        'estimated_gross' => $estimatedGross,
        'net_pay' => $netPay
    ];
}

$currentPayroll = $payrollHistory[0] ?? [
    'month' => $currentMonth,
    'days_present' => 0,
    'worked_seconds' => 0,
    'estimated_gross' => 0,
    'net_pay' => 0
];

$currentPayroll['days_present'] = $adminAttendanceDays;
$currentPayroll['estimated_gross'] = $dailyRate * $adminAttendanceDays;
$currentPayroll['net_pay'] = max(0, $currentPayroll['estimated_gross']);

$employeeCode = 'EM' . str_pad((string) $employeeId, 4, '0', STR_PAD_LEFT);

function formatHoursMinutesPayroll($seconds)
{
    $seconds = max(0, (int) $seconds);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return $hours . 'h ' . $minutes . 'm';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payroll - L LE JOSE</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-main: #09111f;
            --line: rgba(255, 255, 255, 0.09);
            --text-main: #f8fafc;
            --text-soft: #cbd5e1;
            --text-muted: #94a3b8;
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
            width: 160px; min-height: 100vh; background: linear-gradient(180deg, rgba(130,114,255,0.95) 0%, rgba(109,93,252,0.92) 100%);
            border-right: 1px solid rgba(255,255,255,0.12); box-shadow: 10px 0 35px rgba(12,18,34,0.4); padding: 24px 14px;
            display: flex; flex-direction: column; gap: 22px;
        }
        .sidebar-header { text-align: center; padding: 4px 6px 0; }
        .sidebar-logo { font-size: 1.9rem; font-weight: 800; line-height: 1; letter-spacing: -0.03em; margin-bottom: 10px; }
        .sidebar-tag { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.72); }
        .sidebar-nav { display: flex; flex-direction: column; gap: 12px; flex: 1; margin-top: 8px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; text-decoration: none;
            color: rgba(255,255,255,0.9); background: rgba(255,255,255,0.06); border: 1px solid transparent; font-size: 0.95rem; font-weight: 600;
        }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.16); border-color: rgba(255,255,255,0.16); }
        .nav-item i { width: 18px; text-align: center; font-size: 0.95rem; }
        .sidebar-footer {
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; padding: 14px 12px;
            backdrop-filter: blur(14px);
        }
        .user-card { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.18); display: grid; place-items: center; }
        .user-code { font-size: 0.95rem; font-weight: 700; line-height: 1.1; }
        .user-role { font-size: 0.78rem; color: rgba(255,255,255,0.72); }
        .logout-btn { width: 100%; border: 0; border-radius: 12px; padding: 11px 12px; background: rgba(255,255,255,0.16); color: #fff; font-weight: 700; font-size: 0.9rem; cursor: pointer; }
        .main-content { flex: 1; padding: 22px 24px 28px; overflow: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 16px; }
        .welcome-header { font-size: 2rem; font-weight: 800; letter-spacing: -0.03em; }
        .subheader { color: var(--text-soft); font-size: 0.95rem; }
        .shell { display: flex; flex-direction: column; gap: 16px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .content-grid { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 16px; }
        .panel {
            position: relative; background: linear-gradient(180deg, rgba(17,24,39,0.8) 0%, rgba(11,18,32,0.88) 100%);
            border: 1px solid var(--line); border-radius: 22px; box-shadow: 0 15px 40px rgba(0,0,0,0.34); overflow: hidden;
            backdrop-filter: blur(18px);
        }
        .panel-inner { padding: 18px; }
        .panel-title { font-size: 0.78rem; color: rgba(255,255,255,0.88); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 18px; }
        .metric-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 16px; }
        .metric-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 8px; font-weight: 700; }
        .metric-value { font-size: 1.45rem; font-weight: 800; color: #fff; }
        .metric-sub { font-size: 0.82rem; color: var(--text-soft); margin-top: 6px; }
        .summary-violet { background: linear-gradient(180deg, rgba(92,73,189,0.24) 0%, rgba(40,23,97,0.22) 100%); }
        .summary-cyan { background: linear-gradient(180deg, rgba(8,145,178,0.22) 0%, rgba(14,116,144,0.18) 100%); }
        .summary-emerald { background: linear-gradient(180deg, rgba(5,150,105,0.22) 0%, rgba(6,95,70,0.18) 100%); }
        .summary-amber { background: linear-gradient(180deg, rgba(217,119,6,0.2) 0%, rgba(146,64,14,0.18) 100%); }
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field-group { display: grid; gap: 8px; }
        .field-group label { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; color: #cbd5e1; }
        .field-group input, .field-group select, .field-group textarea {
            width: 100%; border-radius: 14px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05);
            color: #f8fafc; padding: 12px 14px; font: inherit; outline: none;
        }
        .field-group textarea { min-height: 96px; resize: vertical; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
        .btn {
            border: 0; border-radius: 12px; padding: 12px 15px; color: #fff; font-size: 0.92rem; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary { background: linear-gradient(90deg, #8b5cf6 0%, #22d3ee 100%); }
        .table-wrap { border-radius: 18px; overflow: hidden; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.025); }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { text-align: left; padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.06); font-size: 0.9rem; color: #f8fafc; }
        th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: #cbd5e1; font-weight: 700; background: rgba(255,255,255,0.05); }
        .adjustment-chip {
            display: inline-flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 999px; font-size: 0.78rem; margin: 4px 6px 0 0;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .bonus-chip { background: rgba(34,197,94,0.14); color: #86efac; }
        .deduction-chip { background: rgba(239,68,68,0.14); color: #fecaca; }
        .chip-form { display: inline; }
        .chip-form button { border: 0; background: transparent; color: inherit; cursor: pointer; }
        .empty-state { text-align: center; padding: 26px; color: var(--text-muted); font-size: 0.95rem; }
        .toast-stack { position: fixed; top: 18px; right: 20px; z-index: 2000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .toast {
            min-width: 280px; max-width: 360px; padding: 14px 16px; border-radius: 14px; color: #fff; font-size: 0.9rem; font-weight: 600;
            box-shadow: 0 18px 30px rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.08);
        }
        .toast.success { background: linear-gradient(135deg, rgba(21,128,61,0.95), rgba(22,163,74,0.92)); }
        .toast.error { background: linear-gradient(135deg, rgba(185,28,28,0.96), rgba(239,68,68,0.92)); }
        @media (max-width: 1260px) {
            .summary-grid, .content-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
            .main-content { padding: 18px 14px; }
            .field-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .sidebar-nav { display: grid; grid-template-columns: 1fr 1fr; }
            .welcome-header { font-size: 1.65rem; }
            .toast { min-width: 240px; max-width: calc(100vw - 30px); }
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
            <a href="employee_dashboard.php" class="nav-item">
                <i class="fas fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="pos.php" class="nav-item">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>
            <a href="my_payroll.php" class="nav-item active">
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
                <div class="user-avatar"><i class="fas fa-user"></i></div>
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
            <div>
                <div class="welcome-header">My Payroll, <?php echo htmlspecialchars($employeeCode); ?>.</div>
                <div class="subheader">Track your payroll based on the salary and attendance values maintained in employee management.</div>
            </div>
        </div>

        <div class="shell">
            <section class="summary-grid">
                <div class="metric-card summary-violet">
                    <div class="metric-title">Monthly Salary</div>
                    <div class="metric-value">₱<?php echo number_format($salary, 2); ?></div>
                    <div class="metric-sub">Base monthly salary from your employee record</div>
                </div>
                <div class="metric-card summary-cyan">
                    <div class="metric-title">Daily Rate</div>
                    <div class="metric-value">₱<?php echo number_format($dailyRate, 2); ?></div>
                    <div class="metric-sub">Based on a <?php echo $workingDaysPerMonth; ?>-day payroll month</div>
                </div>
                <div class="metric-card summary-emerald">
                    <div class="metric-title">Current Net Estimate</div>
                    <div class="metric-value">₱<?php echo number_format($currentPayroll['net_pay'], 2); ?></div>
                    <div class="metric-sub"><?php echo date('F Y', strtotime($currentPayroll['month'] . '-01')); ?> after adjustments</div>
                </div>
                <div class="metric-card summary-amber">
                    <div class="metric-title">Days Present</div>
                    <div class="metric-value"><?php echo (int) $currentPayroll['days_present']; ?></div>
                    <div class="metric-sub">Current admin payroll value from employee management</div>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel">
                    <div class="panel-inner">
                        <div class="panel-title">Current Payroll Snapshot</div>
                        <div class="summary-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                            <div class="metric-card">
                                <div class="metric-title">Estimated Gross</div>
                                <div class="metric-value">₱<?php echo number_format($currentPayroll['estimated_gross'], 2); ?></div>
                                <div class="metric-sub">Daily rate multiplied by admin-managed attendance days</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-title">Worked Time</div>
                                <div class="metric-value"><?php echo formatHoursMinutesPayroll($currentPayroll['worked_seconds']); ?></div>
                                <div class="metric-sub">Attendance log total for <?php echo date('F Y', strtotime($currentPayroll['month'] . '-01')); ?></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-title">Admin Attendance Days</div>
                                <div class="metric-value"><?php echo (int) $adminAttendanceDays; ?></div>
                                <div class="metric-sub">Pulled directly from `employee.php` payroll management</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-title">Net Pay</div>
                                <div class="metric-value">₱<?php echo number_format($currentPayroll['net_pay'], 2); ?></div>
                                <div class="metric-sub">Current payroll estimate with no employee-side adjustments</div>
                            </div>
                        </div>

                        <div style="margin-top: 16px;">
                            <div class="panel-title" style="margin-bottom: 10px;">Payroll Source</div>
                            <div class="metric-card">
                                <div class="metric-sub">Current payroll snapshot is now linked to the admin employee management page.</div>
                                <div class="metric-sub">Formula used: monthly salary / 22 working days x `attendance_days` from the employee record.</div>
                                <div class="metric-sub">Monthly history below still shows attendance-log-based month summaries for reference.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-inner">
                        <div class="panel-title">Payroll Details</div>
                        <div class="field-grid">
                            <div class="metric-card">
                                <div class="metric-title">Position</div>
                                <div class="metric-value"><?php echo htmlspecialchars($employee['role']); ?></div>
                                <div class="metric-sub">Pulled from employee management</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-title">Hire Date</div>
                                <div class="metric-value"><?php echo !empty($employee['date_hired']) ? date('M j, Y', strtotime($employee['date_hired'])) : '--'; ?></div>
                                <div class="metric-sub">Employee record start date</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-title">Payroll Basis</div>
                                <div class="metric-value">22 Days</div>
                                <div class="metric-sub">Same basis used by admin payroll calculation</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-title">History Basis</div>
                                <div class="metric-value">Attendance Logs</div>
                                <div class="metric-sub">Monthly totals are summarized from actual clock data</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-inner">
                    <div class="panel-title">Payroll History</div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Payroll Month</th>
                                    <th>Days Present</th>
                                    <th>Worked Time</th>
                                    <th>Estimated Gross</th>
                                    <th>Net Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($payrollHistory)): ?>
                                    <?php foreach ($payrollHistory as $entry): ?>
                                        <tr>
                                            <td><?php echo date('F Y', strtotime($entry['month'] . '-01')); ?></td>
                                            <td><?php echo (int) $entry['days_present']; ?></td>
                                            <td><?php echo formatHoursMinutesPayroll($entry['worked_seconds']); ?></td>
                                            <td>₱<?php echo number_format($entry['estimated_gross'], 2); ?></td>
                                            <td>₱<?php echo number_format($entry['net_pay'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="empty-state">No payroll data yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        function showToast(message, type = 'success') {
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

    </script>
</body>
</html>
