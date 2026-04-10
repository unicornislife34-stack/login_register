
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
</head>
<body>  
    <div class="container">
        <h1>Welcome, <span><?= $_SESSION['name'] ?></span></h1>
<p>This is the employee page. You can view your information and perform employee-specific tasks here.</p>

<div class="clock-section">
    <h2>Attendance Clock</h2>
    <div id="status-display" style="font-size: 1.2em; margin-bottom: 20px; padding: 10px; border-radius: 5px; background: #f0f8ff;">
        Loading status...
    </div>
    <button id="clock-in-btn" class="clock-btn" style="background: #4CAF50; color: white; padding: 12px 24px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">Clock In</button>
    <button id="clock-out-btn" class="clock-btn" style="background: #f44336; color: white; padding: 12px 24px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer;">Clock Out</button>
</div>
    <button onclick="window.location.href='logout.php'">Logout</button>

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
    const btn = document.querySelector(`#${action}-btn`);
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
        updateStatus(); // Refresh
        if (data.status === 'success') {
            btn.textContent = action === 'clock_in' ? 'Clocked In!' : 'Clocked Out!';
            setTimeout(() => {
                btn.textContent = action.charAt(0).toUpperCase() + action.slice(1).replace('_', ' ');
                btn.disabled = false;
            }, 2000);
        } else {
            btn.disabled = false;
            btn.textContent = action.charAt(0).toUpperCase() + action.slice(1).replace('_', ' ');
        }
    })
    .catch(err => {
        alert('Error: ' + err);
        btn.disabled = false;
        btn.textContent = action.charAt(0).toUpperCase() + action.slice(1).replace('_', ' ');
    });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    updateStatus();
    statusInterval = setInterval(updateStatus, 30000); // Update every 30s
    
    document.getElementById('clock-in-btn').onclick = () => clockAction('clock_in');
    document.getElementById('clock-out-btn').onclick = () => clockAction('clock_out');
});
</script>
    </div>

</body>
</html>

