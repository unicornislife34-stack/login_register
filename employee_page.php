
<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'employee') {
    header("Location: index.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #e9d5ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1f2937;
        }
        .container {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
            padding: 40px 32px;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1f2937;
        }
        .subtitle {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        #employee-menu {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        .btn-action {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        .btn-action:disabled {
            background: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-primary {
            background: #3b82f6;
            color: #fff;
        }
        .btn-secondary {
            background: #8b5cf6;
            color: #fff;
        }
        .btn-accent {
            background: #10b981;
            color: #fff;
        }
        .btn-warning {
            background: #f59e0b;
            color: #fff;
        }
        .clock-section {
            display: none;
        }
        .clock-section.active {
            display: block;
        }
        .clock-header {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        .clock-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        .status-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #f5f3ff 100%);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin-bottom: 24px;
            border: 1px solid rgba(59, 130, 246, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .status-label {
            font-size: 0.85rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .status-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .status-detail {
            font-size: 0.95rem;
            color: #6b7280;
        }
        .time-display {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .time-label {
            font-size: 0.9rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        #live-clock {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2563eb;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.05em;
        }
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }
        .action-buttons button {
            padding: 16px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .action-buttons button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        .action-buttons button:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .action-buttons button .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        .action-buttons button.processing .spinner {
            display: block;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .info-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .info-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .info-card-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .info-card-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1f2937;
        }
        .notification-toast {
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            color: #1f2937;
            padding: 14px 16px;
            margin-bottom: 16px;
            display: none;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .notification-toast.show {
            display: flex;
            animation: slideDown 0.3s ease;
        }
        .notification-toast.success {
            background: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }
        .notification-toast.error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .notification-toast.info {
            background: #f0f9ff;
            border-color: #bae6fd;
            color: #0c2340;
        }
        .btn-logout {
            width: 100%;
            padding: 12px;
            background: #f3f4f6;
            color: #4b5563;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-logout:hover {
            background: #e5e7eb;
        }
        
        .cart-badge {
            background: #e74c3c;
            color: white;
            border-radius: 12px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            margin-left: 8px;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @media (max-width: 480px) {
            .container {
                padding: 28px 20px;
            }
            h1 {
                font-size: 1.6rem;
            }
            .clock-header h2 {
                font-size: 1.5rem;
            }
            .action-buttons,
            .info-cards {
                grid-template-columns: 1fr;
            }
            .status-value {
                font-size: 1.75rem;
            }
            #live-clock {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>  
    <div class="container">
        <div id="employee-menu" style="display: flex; flex-direction: column; gap: 20px;">
            <div>
                <h1>Welcome, <?= $_SESSION['name'] ?></h1>
                <p class="subtitle">Choose an option to get started.</p>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <button id="attendance-menu-btn" class="btn-action btn-primary">Attendance</button>
                <button id="pos-menu-btn" class="btn-action btn-secondary">POS System</button>
            </div>
        </div>

        <div id="attendance-section" class="clock-section">
            <div class="clock-header">
                <h2>Attendance Clock</h2>
            </div>

            <div id="attendanceToast" class="notification-toast info">
                <span id="toastMessage">Ready to track your shift.</span>
            </div>

            <div class="status-box">
                <div class="status-label">Current Status</div>
                <div id="attendanceStatus" class="status-value">Connecting...</div>
                <div id="attendanceStatusMeta" class="status-detail">Checking your shift status.</div>
            </div>

            <div class="time-display">
                <div class="time-label">Time Now</div>
                <div id="live-clock">--:--:--</div>
            </div>

            <div class="action-buttons">
                <button id="clock-in-btn" class="btn-accent"><span class="spinner"></span>Clock In</button>
                <button id="clock-out-btn" class="btn-secondary"><span class="spinner"></span>Clock Out</button>
                <button id="start-break-btn" class="btn-warning"><span class="spinner"></span>Start Break</button>
                <button id="end-break-btn" class="btn-secondary"><span class="spinner"></span>End Break</button>
            </div>

            <div class="info-cards">
                <div class="info-card">
                    <div class="info-card-label">Work Duration</div>
                    <div id="workDuration" class="info-card-value">0h 00m</div>
                </div>
                <div class="flex flex-col gap-3">
    <button id="dashboard-menu-btn" class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-6 py-4 rounded-xl text-base font-semibold cursor-pointer transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-1" onclick="window.location.href='employee_dashboard.php'">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </button>

                    <button id="pos-menu-btn" class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-4 rounded-xl text-base font-semibold cursor-pointer transition-all shadow-md hover:shadow-lg transform hover:-translate-y-1">
                        <i class="fas fa-cash-register mr-2"></i>POS System
                    </button>
        </div>



        <button onclick="window.location.href='logout.php'" class="btn-logout">Logout</button>
    </div>

    <footer class="bg-gray-800 text-white text-center py-4 mt-10">
        <p class="text-sm">© 2026 L LE JOSE - Point of Sale System. All rights reserved.</p>
    </footer>

<script>




window.addEventListener('DOMContentLoaded', () => {
    document.getElementById('pos-menu-btn').onclick = () => window.location.href = 'pos.php';

    // Attach event listeners using addEventListener for better control
    document.getElementById('clock-in-btn').addEventListener('click', function() {
        sendAttendanceAction.call(this, 'clock_in');
    });
    document.getElementById('clock-out-btn').addEventListener('click', function() {
        sendAttendanceAction.call(this, 'clock_out');
    });
    document.getElementById('start-break-btn').addEventListener('click', function() {
        sendAttendanceAction.call(this, 'start_break');
    });
    document.getElementById('end-break-btn').addEventListener('click', function() {
        sendAttendanceAction.call(this, 'end_break');
    });

    refreshLiveClock();
    clockInterval = setInterval(refreshLiveClock, 1000);
    statusInterval = setInterval(() => updateStatus(true), 30000);
});
</script>
</body>
</html>
