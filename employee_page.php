
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
        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .container {
            max-width: 920px;
            margin: 40px auto;
            padding: 32px;
            background: rgba(255,255,255,0.12);
            border-radius: 24px;
            box-shadow: 0 20px 80px rgba(0,0,0,0.24);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        h1 {
            margin-bottom: 12px;
            font-size: 2.3rem;
            letter-spacing: 0.02em;
        }
        p {
            margin-top: 0;
            font-size: 1rem;
            color: #e5ecff;
            max-width: 760px;
            line-height: 1.6;
        }
        #employee-menu {
            margin: 32px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }
        .btn-action {
            flex: 1 1 240px;
            padding: 16px 24px;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
            box-shadow: 0 12px 28px rgba(0,0,0,0.18);
        }
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(0,0,0,0.22);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #8b67d9 0%, #a86ee7 100%);
            color: #fff;
        }
        .btn-accent {
            background: linear-gradient(135deg, #5a77d1 0%, #7f62d6 100%);
            color: #fff;
        }
        .btn-back,
        .btn-logout {
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .clock-section {
            background: rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid rgba(255,255,255,0.14);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06);
        }
        .status-display {
            font-size: 1.1em;
            margin-bottom: 22px;
            padding: 18px 16px;
            border-radius: 14px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            min-height: 84px;
        }
        .clock-button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 24px;
        }
        .btn-sm {
            min-width: 130px;
            padding: 14px 22px;
        }
        .btn-back {
            background: rgba(255,255,255,0.14);
            color: #fff;
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.22);
        }
        .btn-logout {
            margin-top: 16px;
            background: rgba(0,0,0,0.24);
            color: #f7f7f7;
        }
        .btn-logout:hover {
            background: rgba(0,0,0,0.32);
        }
    </style>
</head>
<body>  
    <div class="container">
        <h1>Welcome, <span><?= $_SESSION['name'] ?></span></h1>
        <p>This is the employee dashboard. Choose Attendance or the POS System below.</p>

        <div id="employee-menu">
            <button id="attendance-menu-btn" class="btn-action btn-primary">Attendance</button>
            <button id="pos-menu-btn" class="btn-action btn-secondary">POS System</button>
        </div>

        <div id="attendance-section" class="clock-section" style="display: none;">
            <h2>Attendance Clock</h2>
            <div id="status-display" class="status-display">
                Loading status...
            </div>
            <div class="clock-button-row">
                <button id="clock-in-btn" class="btn-action btn-accent btn-sm">Clock In</button>
                <button id="clock-out-btn" class="btn-action btn-secondary btn-sm">Clock Out</button>
            </div>
            <div>
                <button id="attendance-back-btn" class="btn-back btn-sm">Back to Menu</button>
            </div>
        </div>

        <button onclick="window.location.href='logout.php'" class="btn-logout btn-sm">Logout</button>

<script>
let statusInterval;

function updateStatus() {
    fetch('clock_handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=status'
    })
    .then(response => response.json())
    .then(data => {
        const statusEl = document.getElementById('status-display');
        const inBtn = document.getElementById('clock-in-btn');
        const outBtn = document.getElementById('clock-out-btn');
        
        if (data.status === 'in') {
            statusEl.innerHTML = `<strong>Status: Clocked IN</strong><br>Since: ${data.clock_in}`;
            statusEl.style.background = '#d4edda';
            inBtn.disabled = true;
            outBtn.disabled = false;
        } else if (data.status === 'out') {
            statusEl.innerHTML = `<strong>Status: Clocked OUT</strong><br>In: ${data.clock_in} | Out: ${data.clock_out}`;
            statusEl.style.background = '#fff3cd';
            inBtn.disabled = true;
            outBtn.disabled = true;
        } else {
            statusEl.innerHTML = '<strong>Status: Not clocked in today</strong>';
            statusEl.style.background = '#f0f8ff';
            inBtn.disabled = false;
            outBtn.disabled = true;
        }
    })
    .catch(err => {
        console.error('Status check failed:', err);
        document.getElementById('status-display').innerHTML = 'Error checking status';
    });
}

function clockAction(action) {
    const id = action === 'clock_in' ? 'clock-in-btn' : 'clock-out-btn';
    const btn = document.getElementById(id);
    if (!btn) return;

    btn.disabled = true;
    btn.textContent = 'Processing...';
    
    fetch('clock_handler.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message || data.error);
        updateStatus();
        if (data.status === 'success') {
            btn.textContent = action === 'clock_in' ? 'Clocked In!' : 'Clocked Out!';
            setTimeout(() => {
                btn.textContent = action === 'clock_in' ? 'Clock In' : 'Clock Out';
                btn.disabled = false;
            }, 2000);
        } else {
            btn.disabled = false;
            btn.textContent = action === 'clock_in' ? 'Clock In' : 'Clock Out';
        }
    })
    .catch(err => {
        alert('Error: ' + err);
        btn.disabled = false;
        btn.textContent = action === 'clock_in' ? 'Clock In' : 'Clock Out';
    });
}

function showAttendanceSection() {
    document.getElementById('employee-menu').style.display = 'none';
    document.getElementById('attendance-section').style.display = 'block';
    updateStatus();
}

function hideAttendanceSection() {
    document.getElementById('attendance-section').style.display = 'none';
    document.getElementById('employee-menu').style.display = 'block';
}

// Init
 document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('attendance-menu-btn').onclick = showAttendanceSection;
    document.getElementById('pos-menu-btn').onclick = () => window.location.href = 'pos.php';
    document.getElementById('attendance-back-btn').onclick = hideAttendanceSection;
    document.getElementById('clock-in-btn').onclick = () => clockAction('clock_in');
    document.getElementById('clock-out-btn').onclick = () => clockAction('clock_out');
    statusInterval = setInterval(updateStatus, 30000);
});
</script>
    </div>

</body>
</html>

