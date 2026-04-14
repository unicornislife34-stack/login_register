<?php
session_start();
include 'config.php';

// --- Handle Add / Edit / Delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = isset($_POST['name']) ? mysqli_real_escape_string($conn, $_POST['name']) : '';
    $role = isset($_POST['role']) ? mysqli_real_escape_string($conn, $_POST['role']) : '';
    $salary = isset($_POST['salary']) ? floatval($_POST['salary']) : 0;
    $attendance_days = isset($_POST['attendance_days']) ? intval($_POST['attendance_days']) : 0;
    $date_hired = isset($_POST['date_hired']) ? $_POST['date_hired'] : '';

    if ($action === 'add') {
        $sql = "INSERT INTO employee (name, role, salary, attendance_days, date_hired) 
                VALUES ('$name', '$role', $salary, $attendance_days, '$date_hired')";
    }
    if ($action === 'update') {
        $id = intval($_POST['id']);
        $sql = "UPDATE employee SET name='$name', role='$role', salary=$salary, attendance_days=$attendance_days, date_hired='$date_hired' WHERE id=$id";
    }
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM employee WHERE id=$id";
    }

    if (isset($sql)) {
        mysqli_query($conn, $sql);
        header("Location: employee.php");
        exit();
    }
}

// --- Fetch Employees ---
$result = mysqli_query($conn, "SELECT * FROM employee ORDER BY id ASC");
$employees = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --danger: #e74a3b;
            --dark: #5a5c69;
            --light: #f8f9fc;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f2f5; 
            padding: 40px; 
            margin: 0;
            color: #333;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        /* BACK BUTTON */
        .btn-back {
            text-decoration: none;
            background: white;
            color: var(--dark);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: 0.3s;
            border: 1px solid #ddd;
        }
        .btn-back:hover { background: #eee; }

        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            background: white; 
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #edf2f7; }
        th { background: #f8fafc; color: var(--dark); font-weight: 600; text-transform: uppercase; font-size: 12px; }

        .btn { 
            padding: 8px 16px; 
            border: none; 
            cursor: pointer; 
            border-radius: 6px; 
            font-weight: 500;
            transition: 0.2s;
        }
        .btn-add { background: var(--primary); color: white; }
        .btn-edit { background: var(--success); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-attendance { background: var(--dark); color: white; font-size: 12px; }
        .btn:hover { opacity: 0.85; transform: translateY(-1px); }

        tr.selected { background: #eef2ff !important; }
        tr:hover { background: #fcfcfc; cursor: pointer; }

        .modal { display:none; position:fixed; width:100%; height:100%; top:0; left:0; background:rgba(0,0,0,0.6); z-index: 100; }
        .modal-content { 
            background: white; 
            margin: 5% auto; 
            padding: 30px; 
            width: 450px; 
            border-radius: 15px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0 20px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
        }
    </style>
</head>

<body>

<div class="header-container">
    <a href="admin_page.php" class="btn-back">← Back to Admin</a>
    <h1 style="margin:0;">Employee Management</h1>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-add" onclick="openModal('add')">Add New</button>
        <button class="btn btn-edit" onclick="editSelected()">Edit Selected</button>
        <button class="btn btn-delete" onclick="deleteSelected()">Delete Selected</button>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Role</th>
            <th>Attendance</th>
            <th>Salary</th>
            <th>Date Hired</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($employees as $emp): ?>
        <tr onclick="selectRow(<?php echo $emp['id']; ?>, this)">
            <td>#<?php echo $emp['id']; ?></td>
            <td style="font-weight:600;"><?php echo htmlspecialchars($emp['name']); ?></td>
            <td><span style="background:#e2e8f0; padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo $emp['role']; ?></span></td>
            <td>
                <button class="btn btn-attendance" onclick="event.stopPropagation(); showAttendance(<?php echo $emp['id']; ?>)">View Records</button>
            </td>
            <td>₱<?php echo number_format($emp['salary'], 2); ?></td>
            <td><?php echo date('M d, Y', strtotime($emp['date_hired'])); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="modal" class="modal">
    <div class="modal-content">
        <h2 id="title" style="margin-top:0;"></h2>
        <form method="POST">
            <input type="hidden" name="action" id="action">
            <input type="hidden" name="id" id="id">
            
            <label>Full Name</label>
            <input type="text" name="name" id="name" required>

            <label>Job Role</label>
            <input type="text" name="role" id="role" required>

            <label>Attendance Days</label>
            <input type="number" name="attendance_days" id="attendance_days">

            <label>Monthly Salary</label>
            <input type="number" step="0.01" name="salary" id="salary" required>

            <label>Date Hired</label>
            <input type="date" name="date_hired" id="date_hired" required>

            <div style="display:flex; gap:10px; margin-top:10px;">
                <button type="submit" class="btn btn-add" style="flex:1;">Save Changes</button>
                <button type="button" class="btn" style="background:#ddd;" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="attendanceModal" class="modal">
    <div class="modal-content" style="width: 600px;">
        <h3 style="margin-top:0;">Attendance Details</h3>
        <div id="attendanceDetails"></div>
        <button class="btn" style="background:#555; color:white; width:100%; margin-top:20px;" onclick="closeAttendance()">Close Window</button>
    </div>
</div>

<script>
let selectedId = null;
const employees = <?php echo json_encode($employees); ?>;

function selectRow(id, row) {
    selectedId = id;
    document.querySelectorAll("tr").forEach(r => r.classList.remove("selected"));
    row.classList.add("selected");
}

function openModal(mode) {
    document.getElementById('modal').style.display = 'block';
    if (mode === 'add') {
        document.getElementById('title').innerText = 'Add Employee';
        document.getElementById('action').value = 'add';
        document.getElementById('name').value = '';
        document.getElementById('role').value = '';
        document.getElementById('attendance_days').value = 0;
        document.getElementById('salary').value = 0;
        document.getElementById('date_hired').value = '';
    }
}

function editSelected() {
    if (!selectedId) { alert("Please select an employee first"); return; }
    const emp = employees.find(e => e.id == selectedId);
    openModal('edit');
    document.getElementById('title').innerText = 'Edit Employee';
    document.getElementById('action').value = 'update';
    document.getElementById('id').value = emp.id;
    document.getElementById('name').value = emp.name;
    document.getElementById('role').value = emp.role;
    document.getElementById('attendance_days').value = emp.attendance_days;
    document.getElementById('salary').value = emp.salary;
    document.getElementById('date_hired').value = emp.date_hired;
}

function deleteSelected() {
    if (!selectedId) { alert("Please select an employee first"); return; }
    if (confirm("Are you sure you want to delete this employee?")) {
        let form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${selectedId}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

async function showAttendance(id) {
    const emp = employees.find(e => e.id == id);
    document.getElementById('attendanceModal').style.display = 'block';
    document.getElementById('attendanceDetails').innerHTML = '<div style="padding:20px; text-align:center;">Loading records...</div>';
    
    try {
        // Fetching with corrected handling for JSON
        const sumRes = await fetch(`get_employee_summary.php?employee_id=${id}`);
        const summary = await sumRes.json();
        
        const detRes = await fetch(`admin_get_attendance_details.php?employee_id=${id}`);
        const detailsData = await detRes.json();
        
        // Corrected variable access: detailsData contains the 'details' array
        const details = detailsData.details || [];

        document.getElementById('attendanceDetails').innerHTML = `
            <h4 style="margin-bottom:10px;">${emp.name} Summary</h4>
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; background:#f8fafc; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; font-size:13px;">
                <div><strong>Present</strong><br>${summary.Present || 0}</div>
                <div><strong>Absent</strong><br>${summary.Absent || 0}</div>
                <div><strong>Late</strong><br>${summary.Late || 0}</div>
                <div><strong>Total Hours</strong><br>${summary.Hours || 0}h</div>
            </div>
            <table style="width:100%; border-collapse:collapse; font-size:13px; box-shadow:none; border: 1px solid #eee;">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th style="padding:8px;">Date</th>
                        <th style="padding:8px;">Time In</th>
                        <th style="padding:8px;">Time Out</th>
                        <th style="padding:8px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${details.length > 0 ? details.map(row => `
                        <tr>
                            <td style="padding:8px;">${row.date}</td>
                            <td style="padding:8px;">${row.clock_in || '--:--'}</td>
                            <td style="padding:8px;">${row.clock_out || '--:--'}</td>
                            <td style="padding:8px;"><span style="color:${row.status === 'Present' ? 'green' : 'red'}">${row.status}</span></td>
                        </tr>
                    `).join('') : '<tr><td colspan="4" style="text-align:center; padding:20px;">No records found.</td></tr>'}
                </tbody>
            </table>
        `;
    } catch(e) {
        document.getElementById('attendanceDetails').innerHTML = '<div style="color:red; padding:20px; text-align:center;">Error: Could not retrieve data from server.</div>';
        console.error("Fetch Error:", e);
    }
}

function closeModal() { document.getElementById('modal').style.display = 'none'; }
function closeAttendance() { document.getElementById('attendanceModal').style.display = 'none'; }
</script>

</body>
</html>