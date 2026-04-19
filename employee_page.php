
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
    <link rel="stylesheet" href="style.css">
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
                <button id="pos-menu-btn" class="btn-action btn-secondary" id="posBadgeBtn">
                    POS System <span id="posCartBadge" class="cart-badge" style="display:none;">0</span>
                </button>
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
                <div class="info-card">
                    <div class="info-card-label">Break Time</div>
                    <div id="breakDuration" class="info-card-value">0m</div>
                </div>
                <div class="info-card">
                    <div class="info-card-label">Status</div>
                    <div id="summaryStatus" class="info-card-value" style="font-size: 1rem; overflow: hidden; text-overflow: ellipsis;">Not clocked in</div>
                </div>
                <div class="info-card">
                    <div class="info-card-label">Overtime</div>
                    <div id="summaryOvertime" class="info-card-value">0m</div>
                </div>
            </div>
        </div>

        <button onclick="window.location.href='logout.php'" class="btn-logout">Logout</button>
    </div>

<script>
let statusInterval;
let clockInterval;

function formatDuration(seconds) {
    seconds = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const parts = [];
    if (hours) parts.push(hours + 'h');
    parts.push(minutes.toString().padStart(2, '0') + 'm');
    return parts.join(' ');
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('attendanceToast');
    const text = document.getElementById('toastMessage');
    toast.className = `notification-toast show ${type}`;
    text.textContent = message;
    clearTimeout(showToast.timeout);
    showToast.timeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 4000);
}

function setButtonVisibility(state) {
    document.getElementById('clock-in-btn').disabled = !state.canClockIn;
    document.getElementById('clock-out-btn').disabled = !state.canClockOut;
    document.getElementById('start-break-btn').disabled = !state.canStartBreak;
    document.getElementById('end-break-btn').disabled = !state.canEndBreak;
}

function updateDashboard(data) {
    document.getElementById('attendanceStatus').textContent = data.status;
    document.getElementById('attendanceStatusMeta').textContent = data.statusDetail;
    document.getElementById('workDuration').textContent = data.workDuration;
    document.getElementById('breakDuration').textContent = data.breakDuration;
    document.getElementById('summaryStatus').textContent = data.summaryStatus;
    document.getElementById('summaryOvertime').textContent = data.overtimeLabel;
    setButtonVisibility(data.buttonState);
}

function refreshLiveClock() {
    const now = new Date();
    document.getElementById('live-clock').textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
}

function updateStatus(silent = false) {
    fetch('fetch_status.php')
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            if (!silent) showToast(data.error, 'error');
            return;
        }
        updateDashboard(data);
    })
    .catch(err => {
        console.error('Status check failed:', err);
        if (!silent) showToast('Unable to update attendance status.', 'error');
    });
}

function sendAttendanceAction(action) {
    const button = this;
    if (!button) return;

    // Store original content
    const originalHTML = button.innerHTML;

    // Disable button and show processing
    button.disabled = true;
    button.classList.add('processing');
    button.innerHTML = '<span class="spinner"></span>Processing...';

    fetch('attendance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}`
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text().then(text => {
            console.log('Raw response:', text);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response: ' + text);
            }
        });
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            updateStatus(true);
        } else {
            showToast(data.message || 'Action failed', 'error');
        }
    })
    .catch(err => {
        console.error('Attendance action error:', err);
        showToast('Failed to process request. Please try again.', 'error');
    })
    .finally(() => {
        // Restore button state
        button.disabled = false;
        button.classList.remove('processing');
        button.innerHTML = originalHTML;
    });
}

function showAttendanceSection() {
    document.getElementById('employee-menu').style.display = 'none';
    document.getElementById('attendance-section').classList.add('active');
    updateStatus();
}

function hideAttendanceSection() {
    document.getElementById('attendance-section').classList.remove('active');
    document.getElementById('employee-menu').style.display = 'flex';
}

window.addEventListener('DOMContentLoaded', () => {
    document.getElementById('attendance-menu-btn').onclick = showAttendanceSection;
    document.getElementById('pos-menu-btn').onclick = () => window.location.href = 'pos.php';

    // NEW: Pending cart badge updater
    async function updatePosBadge() {
        try {
            const response = await fetch('pending_order_handler.php?action=get_pending');
            const data = await response.json();
            const badge = document.getElementById('posCartBadge');
            const btn = document.getElementById('posBadgeBtn');
            
            if (data.success && data.order && data.order.length > 0) {
                const itemCount = data.order.reduce((sum, item) => sum + item.qty, 0);
                badge.textContent = itemCount > 99 ? '99+' : itemCount;
                badge.style.display = 'inline';
                btn.classList.add('has-items');
            } else {
                badge.style.display = 'none';
                btn.classList.remove('has-items');
            }
        } catch (err) {
            console.error('Badge update failed:', err);
        }
    }

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
    
    // NEW: Update cart badge every 30s
    updatePosBadge();
    setInterval(updatePosBadge, 30000);
});
</script>
</body>
</html>
