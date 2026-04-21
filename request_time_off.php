<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$timeOffTableSql = "CREATE TABLE IF NOT EXISTS time_off_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT DEFAULT NULL,
    employee_username VARCHAR(150) NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL DEFAULT 1,
    reason TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    reviewed_by VARCHAR(150) DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $timeOffTableSql);

$username = $_SESSION['username'];
$employeeQuery = "SELECT * FROM employee WHERE LOWER(TRIM(name)) = LOWER(TRIM('$username')) LIMIT 1";
$employeeResult = mysqli_query($conn, $employeeQuery);
$employee = mysqli_fetch_assoc($employeeResult);

if (!$employee) {
    $employee = ['name' => $username, 'role' => 'Staff Member', 'id' => 0];
}

$employeeCode = !empty($employee['id']) ? 'EM' . str_pad((string) $employee['id'], 4, '0', STR_PAD_LEFT) : strtoupper(substr(preg_replace('/\s+/', '', $employee['name']), 0, 6));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'request_time_off') {
        $leaveType = trim($_POST['leave_type'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        $allowedLeaveTypes = ['Vacation', 'Sick', 'Personal', 'Emergency', 'Unpaid'];
        $errors = [];

        if (!in_array($leaveType, $allowedLeaveTypes, true)) {
            $errors[] = 'Please select a valid leave type.';
        }

        if (!$startDate || !$endDate) {
            $errors[] = 'Start date and end date are required.';
        } elseif (!strtotime($startDate) || !strtotime($endDate)) {
            $errors[] = 'Please provide valid leave dates.';
        } elseif ($endDate < $startDate) {
            $errors[] = 'End date cannot be earlier than start date.';
        } elseif ($startDate < date('Y-m-d')) {
            $errors[] = 'Time-off requests must start today or later.';
        }

        $reasonEscaped = mysqli_real_escape_string($conn, $reason);
        $leaveTypeEscaped = mysqli_real_escape_string($conn, $leaveType);
        $usernameEscaped = mysqli_real_escape_string($conn, $username);
        $employeeId = (int) ($employee['id'] ?? 0);
        $totalDays = 0;

        if (!$errors) {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $totalDays = (int) $start->diff($end)->format('%a') + 1;

            $overlapSql = "
                SELECT id
                FROM time_off_requests
                WHERE employee_username = '$usernameEscaped'
                    AND status IN ('Pending', 'Approved')
                    AND start_date <= '$endDate'
                    AND end_date >= '$startDate'
                LIMIT 1
            ";
            $overlapResult = mysqli_query($conn, $overlapSql);
            if ($overlapResult && mysqli_num_rows($overlapResult) > 0) {
                $errors[] = 'You already have a pending or approved request that overlaps those dates.';
            }
        }

        if ($errors) {
            $_SESSION['time_off_toast_message'] = implode(' ', $errors);
            $_SESSION['time_off_toast_type'] = 'error';
        } else {
            $insertSql = "
                INSERT INTO time_off_requests (employee_id, employee_username, leave_type, start_date, end_date, total_days, reason)
                VALUES ($employeeId, '$usernameEscaped', '$leaveTypeEscaped', '$startDate', '$endDate', $totalDays, " . ($reason !== '' ? "'$reasonEscaped'" : "NULL") . ")
            ";

            if (mysqli_query($conn, $insertSql)) {
                $_SESSION['time_off_toast_message'] = 'Your time-off request has been submitted.';
                $_SESSION['time_off_toast_type'] = 'success';
            } else {
                $_SESSION['time_off_toast_message'] = 'Unable to submit your request right now.';
                $_SESSION['time_off_toast_type'] = 'error';
            }
        }

        header('Location: request_time_off.php');
        exit();
    }

    if ($action === 'cancel_time_off') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $usernameEscaped = mysqli_real_escape_string($conn, $username);

        $cancelSql = "
            UPDATE time_off_requests
            SET status = 'Cancelled'
            WHERE id = $requestId
                AND employee_username = '$usernameEscaped'
                AND status = 'Pending'
            LIMIT 1
        ";

        if (mysqli_query($conn, $cancelSql) && mysqli_affected_rows($conn) > 0) {
            $_SESSION['time_off_toast_message'] = 'The time-off request was cancelled.';
            $_SESSION['time_off_toast_type'] = 'success';
        } else {
            $_SESSION['time_off_toast_message'] = 'Only pending requests can be cancelled.';
            $_SESSION['time_off_toast_type'] = 'error';
        }

        header('Location: request_time_off.php');
        exit();
    }
}

$pageToastMessage = $_SESSION['time_off_toast_message'] ?? '';
$pageToastType = $_SESSION['time_off_toast_type'] ?? 'success';
unset($_SESSION['time_off_toast_message'], $_SESSION['time_off_toast_type']);

$usernameEscaped = mysqli_real_escape_string($conn, $username);
$timeOffSummary = [
    'requests_total' => 0,
    'pending_count' => 0,
    'approved_days' => 0,
    'upcoming_count' => 0
];

$timeOffSummarySql = "
    SELECT
        COUNT(*) AS requests_total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Approved' THEN total_days ELSE 0 END) AS approved_days,
        SUM(CASE WHEN status = 'Approved' AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_count
    FROM time_off_requests
    WHERE employee_username = '$usernameEscaped'
";
$timeOffSummaryResult = mysqli_query($conn, $timeOffSummarySql);
if ($timeOffSummaryResult) {
    $summaryRow = mysqli_fetch_assoc($timeOffSummaryResult);
    if ($summaryRow) {
        $timeOffSummary['requests_total'] = (int) ($summaryRow['requests_total'] ?? 0);
        $timeOffSummary['pending_count'] = (int) ($summaryRow['pending_count'] ?? 0);
        $timeOffSummary['approved_days'] = (int) ($summaryRow['approved_days'] ?? 0);
        $timeOffSummary['upcoming_count'] = (int) ($summaryRow['upcoming_count'] ?? 0);
    }
}

$timeOffHistory = [];
$timeOffHistorySql = "
    SELECT id, leave_type, start_date, end_date, total_days, reason, status, created_at
    FROM time_off_requests
    WHERE employee_username = '$usernameEscaped'
    ORDER BY created_at DESC
    LIMIT 20
";
$timeOffHistoryResult = mysqli_query($conn, $timeOffHistorySql);
if ($timeOffHistoryResult) {
    while ($requestRow = mysqli_fetch_assoc($timeOffHistoryResult)) {
        $timeOffHistory[] = $requestRow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Time Off - L LE JOSE</title>
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
        .sidebar-header { text-align: center; padding: 4px 6px 0; }
        .sidebar-logo { font-size: 1.9rem; font-weight: 800; line-height: 1; letter-spacing: -0.03em; margin-bottom: 10px; }
        .sidebar-tag { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.72); }
        .sidebar-nav { display: flex; flex-direction: column; gap: 12px; flex: 1; margin-top: 8px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px;
            text-decoration: none; color: rgba(255, 255, 255, 0.9); background: rgba(255, 255, 255, 0.06);
            border: 1px solid transparent; font-size: 0.95rem; font-weight: 600; transition: 0.25s ease;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.16);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }
        .nav-item i { width: 18px; text-align: center; font-size: 0.95rem; }
        .sidebar-footer {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 14px 12px;
            backdrop-filter: blur(14px);
        }
        .user-card { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: rgba(255, 255, 255, 0.18);
            display: grid; place-items: center; font-size: 1rem; flex-shrink: 0;
        }
        .user-code { font-size: 0.95rem; font-weight: 700; line-height: 1.1; }
        .user-role { font-size: 0.78rem; color: rgba(255, 255, 255, 0.72); }
        .logout-btn {
            width: 100%; border: 0; border-radius: 12px; padding: 11px 12px; background: rgba(255, 255, 255, 0.16);
            color: #fff; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.25s ease;
        }
        .logout-btn:hover { background: rgba(255, 255, 255, 0.24); }
        .main-content { flex: 1; padding: 22px 24px 28px; overflow: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 16px; }
        .welcome-header { font-size: 2rem; font-weight: 800; letter-spacing: -0.03em; }
        .subheader { color: var(--text-soft); font-size: 0.95rem; }
        .shell { display: flex; flex-direction: column; gap: 18px; }
        .panel {
            position: relative;
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.8) 0%, rgba(11, 18, 32, 0.88) 100%);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.34), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            overflow: hidden;
            backdrop-filter: blur(18px);
        }
        .panel-inner { padding: 18px; }
        .panel-title {
            font-size: 0.78rem; color: rgba(255, 255, 255, 0.88); text-transform: uppercase;
            letter-spacing: 0.08em; font-weight: 700; margin-bottom: 18px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }
        .metric-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px;
        }
        .metric-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 8px; font-weight: 700; }
        .metric-value { font-size: 1.5rem; font-weight: 800; color: #fff; }
        .metric-sub { font-size: 0.82rem; color: var(--text-soft); margin-top: 6px; }
        .summary-violet { background: linear-gradient(180deg, rgba(92, 73, 189, 0.24) 0%, rgba(40, 23, 97, 0.22) 100%); }
        .summary-cyan { background: linear-gradient(180deg, rgba(8, 145, 178, 0.22) 0%, rgba(14, 116, 144, 0.18) 100%); }
        .summary-emerald { background: linear-gradient(180deg, rgba(5, 150, 105, 0.22) 0%, rgba(6, 95, 70, 0.18) 100%); }
        .summary-amber { background: linear-gradient(180deg, rgba(217, 119, 6, 0.2) 0%, rgba(146, 64, 14, 0.18) 100%); }
        .request-layout { display: grid; grid-template-columns: 1.1fr 1.3fr; gap: 16px; }
        .request-form { display: grid; gap: 14px; }
        .request-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field-group { display: grid; gap: 8px; }
        .field-group label {
            font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; color: #cbd5e1;
        }
        .field-group input, .field-group select, .field-group textarea {
            width: 100%; border-radius: 14px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05);
            color: #f8fafc; padding: 12px 14px; font: inherit; outline: none;
        }
        .field-group textarea { min-height: 132px; resize: vertical; }
        .field-group input:focus, .field-group select:focus, .field-group textarea:focus {
            border-color: rgba(34, 211, 238, 0.48);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.12);
            background: rgba(255,255,255,0.07);
        }
        .field-help { font-size: 0.78rem; color: var(--text-muted); }
        .request-actions { display: flex; justify-content: flex-end; gap: 10px; }
        .btn {
            border: 0; border-radius: 12px; padding: 13px 16px; color: #fff; font-size: 0.95rem; font-weight: 700;
            cursor: pointer; transition: 0.25s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-secondary { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); color: #e2e8f0; }
        .btn-primary { background: linear-gradient(90deg, #7c3aed 0%, #8b5cf6 50%, #22d3ee 100%); }
        .history-wrap {
            border-radius: 18px; overflow: hidden; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.025);
        }
        .history-table-scroll { max-height: 420px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        thead { background: rgba(255,255,255,0.05); }
        th, td { text-align: left; padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.06); font-size: 0.9rem; color: #f8fafc; }
        th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: #cbd5e1; font-weight: 700; }
        tbody tr:hover { background: rgba(255,255,255,0.03); }
        .status-pill {
            display: inline-flex; align-items: center; justify-content: center; min-width: 96px; padding: 7px 12px;
            border-radius: 999px; font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .status-pending { background: rgba(245,158,11,0.16); color: #fcd34d; }
        .status-approved { background: rgba(34,197,94,0.16); color: #86efac; }
        .status-rejected { background: rgba(239,68,68,0.16); color: #fda4af; }
        .status-cancelled { background: rgba(148,163,184,0.16); color: #cbd5e1; }
        .table-action-btn {
            border: 1px solid rgba(239,68,68,0.22); background: rgba(239,68,68,0.14); color: #fecaca; border-radius: 10px;
            padding: 8px 10px; font-size: 0.8rem; font-weight: 700; cursor: pointer;
        }
        .table-action-btn:hover { background: rgba(239,68,68,0.2); }
        .empty-state { text-align: center; padding: 26px; color: var(--text-muted); font-size: 0.95rem; }
        .toast-stack {
            position: fixed; top: 18px; right: 20px; z-index: 2000; display: flex; flex-direction: column; gap: 10px; pointer-events: none;
        }
        .toast {
            min-width: 280px; max-width: 360px; padding: 14px 16px; border-radius: 14px; color: #fff; font-size: 0.9rem;
            font-weight: 600; box-shadow: 0 18px 30px rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.08);
        }
        .toast.success { background: linear-gradient(135deg, rgba(21, 128, 61, 0.95), rgba(22, 163, 74, 0.92)); }
        .toast.error { background: linear-gradient(135deg, rgba(185, 28, 28, 0.96), rgba(239, 68, 68, 0.92)); }
        @media (max-width: 1260px) {
            .summary-grid, .request-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
            .main-content { padding: 18px 14px; }
            .request-form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .sidebar-nav { display: grid; grid-template-columns: 1fr 1fr; }
            .welcome-header { font-size: 1.6rem; }
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
            <a href="my_payroll.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>My Payroll</span>
            </a>
            <a href="request_time_off.php" class="nav-item active">
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
            <div>
                <div class="welcome-header">Request Time Off, <?php echo htmlspecialchars($employeeCode); ?>.</div>
                <div class="subheader">Submit leave requests, review approvals, and manage pending requests in one place.</div>
            </div>
        </div>

        <div class="shell">
            <section class="panel">
                <div class="panel-inner">
                    <div class="panel-title">Leave Summary</div>
                    <div class="summary-grid">
                        <div class="metric-card summary-violet">
                            <div class="metric-title">Total Requests</div>
                            <div class="metric-value"><?php echo $timeOffSummary['requests_total']; ?></div>
                            <div class="metric-sub">All requests you have filed so far</div>
                        </div>
                        <div class="metric-card summary-amber">
                            <div class="metric-title">Pending Approval</div>
                            <div class="metric-value"><?php echo $timeOffSummary['pending_count']; ?></div>
                            <div class="metric-sub">Requests waiting for review</div>
                        </div>
                        <div class="metric-card summary-emerald">
                            <div class="metric-title">Approved Days</div>
                            <div class="metric-value"><?php echo $timeOffSummary['approved_days']; ?></div>
                            <div class="metric-sub">Days already approved</div>
                        </div>
                        <div class="metric-card summary-cyan">
                            <div class="metric-title">Upcoming Leave</div>
                            <div class="metric-value"><?php echo $timeOffSummary['upcoming_count']; ?></div>
                            <div class="metric-sub">Approved requests still ahead</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="request-layout">
                <div class="panel">
                    <div class="panel-inner">
                        <div class="panel-title">Request Guidelines</div>
                        <div class="metric-card summary-cyan">
                            <div class="metric-title">Before You Submit</div>
                            <div class="metric-sub">Choose a date range starting today or later.</div>
                            <div class="metric-sub">Overlapping pending or approved requests are blocked automatically.</div>
                            <div class="metric-sub">Pending requests can still be cancelled from the history table below.</div>
                            <div class="metric-sub">Approved and rejected requests remain visible as your leave record.</div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-inner">
                        <div class="panel-title">New Time-Off Request</div>
                        <form method="POST" class="request-form">
                            <input type="hidden" name="action" value="request_time_off">

                            <div class="request-form-grid">
                                <div class="field-group">
                                    <label for="startDate">Start Date</label>
                                    <input type="date" id="startDate" name="start_date" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="field-group">
                                    <label for="endDate">End Date</label>
                                    <input type="date" id="endDate" name="end_date" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="field-group">
                                <label for="leaveType">Type of Leave</label>
                                <select id="leaveType" name="leave_type" required>
                                    <option value="">Select leave type</option>
                                    <option value="Vacation">Vacation</option>
                                    <option value="Sick">Sick</option>
                                    <option value="Personal">Personal</option>
                                    <option value="Emergency">Emergency</option>
                                    <option value="Unpaid">Unpaid</option>
                                </select>
                            </div>

                            <div class="field-group">
                                <label for="leaveReason">Reason for Request</label>
                                <textarea id="leaveReason" name="reason" placeholder="Share a short reason or note for the request."></textarea>
                                <div class="field-help">This system stores your request immediately and keeps it in your employee leave history.</div>
                            </div>

                            <div class="request-actions">
                                <button type="reset" class="btn btn-secondary">Clear</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Submit Request</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-inner">
                    <div class="panel-title">Request History</div>
                    <div class="history-wrap">
                        <div class="history-table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date Filed</th>
                                        <th>Leave Type</th>
                                        <th>Dates Requested</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($timeOffHistory)): ?>
                                        <?php foreach ($timeOffHistory as $request): ?>
                                            <?php
                                                $statusClass = 'status-cancelled';
                                                if ($request['status'] === 'Pending') {
                                                    $statusClass = 'status-pending';
                                                } elseif ($request['status'] === 'Approved') {
                                                    $statusClass = 'status-approved';
                                                } elseif ($request['status'] === 'Rejected') {
                                                    $statusClass = 'status-rejected';
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?> to <?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                                                <td><?php echo (int) $request['total_days']; ?></td>
                                                <td><?php echo htmlspecialchars($request['reason'] !== '' ? $request['reason'] : 'No reason provided'); ?></td>
                                                <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                                <td>
                                                    <?php if ($request['status'] === 'Pending'): ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="cancel_time_off">
                                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                                            <button type="submit" class="table-action-btn">Cancel</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="field-help">No action</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="empty-state">No time-off requests yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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

        function restorePageToast() {
            const message = <?php echo json_encode($pageToastMessage); ?>;
            const type = <?php echo json_encode($pageToastType); ?>;
            if (message) {
                showToast(message, type || 'success');
            }
        }

        function syncDates() {
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            if (!startDateInput || !endDateInput) {
                return;
            }

            startDateInput.addEventListener('change', () => {
                endDateInput.min = startDateInput.value || '<?php echo date('Y-m-d'); ?>';
                if (endDateInput.value && startDateInput.value && endDateInput.value < startDateInput.value) {
                    endDateInput.value = startDateInput.value;
                }
            });
        }

        restorePageToast();
        syncDates();
    </script>
</body>
</html>
