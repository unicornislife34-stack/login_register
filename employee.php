<?php
session_start();
include 'config.php';

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $salary = isset($_POST['salary']) ? floatval(preg_replace('/[^0-9\.]/', '', $_POST['salary'])) : 0;
    $attendance_days = max(0, intval($_POST['attendance_days'] ?? 0));
    $date_hired = trim($_POST['date_hired'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $employment_status = trim($_POST['employment_status'] ?? 'Active');

    if ($action === 'delete') {
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_filter(array_map('intval', $_POST['ids']));
            if ($ids) {
                $sql = 'DELETE FROM employee WHERE id IN (' . implode(',', $ids) . ')';
            }
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $sql = "DELETE FROM employee WHERE id=$id";
            }
        }
    } else {
        if ($name === '' || $role === '' || $salary <= 0 || $date_hired === '') {
            $errorMessage = 'Please complete the required fields and enter a valid salary.';
        }

        if (!$errorMessage) {
            $nameEscaped = mysqli_real_escape_string($conn, $name);
            $roleEscaped = mysqli_real_escape_string($conn, $role);
            $contactEscaped = mysqli_real_escape_string($conn, $contact_number);
            $emailEscaped = mysqli_real_escape_string($conn, $email);
            $statusEscaped = mysqli_real_escape_string($conn, $employment_status);
            $dateHiredEscaped = mysqli_real_escape_string($conn, $date_hired);

            $duplicateQuery = "SELECT id FROM employee WHERE name='$nameEscaped' AND role='$roleEscaped'";
            if ($action === 'update') {
                $id = intval($_POST['id'] ?? 0);
                $duplicateQuery .= " AND id <> $id";
            }

            $duplicateResult = mysqli_query($conn, $duplicateQuery);
            if ($duplicateResult && mysqli_num_rows($duplicateResult) > 0) {
                $errorMessage = 'A record with the same name and role already exists.';
            } else {
                if ($action === 'add') {
                    $sql = "INSERT INTO employee (name, role, salary, attendance_days, date_hired) VALUES ('$nameEscaped', '$roleEscaped', $salary, $attendance_days, '$dateHiredEscaped')";
                }
                if ($action === 'update') {
                    $id = intval($_POST['id'] ?? 0);
                    $sql = "UPDATE employee SET name='$nameEscaped', role='$roleEscaped', salary=$salary, attendance_days=$attendance_days, date_hired='$dateHiredEscaped' WHERE id=$id";
                }
            }
        }
    }

    if (isset($sql) && !$errorMessage) {
        mysqli_query($conn, $sql);
        $successMessage = $action === 'delete' ? 'Employee record deleted.' : 'Employee details saved successfully.';
        header('Location: employee.php');
        exit();
    }
}

$result = mysqli_query($conn, 'SELECT * FROM employee ORDER BY id ASC');
$employees = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #f3f6fb;
            --surface: #ffffff;
            --surface-strong: #f8fafc;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --primary: #2563eb;
            --primary-soft: #eff6ff;
            --success: #16a34a;
            --danger: #dc2626;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
            color: var(--text);
        }

        .page-shell {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 40px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }

        .logo-section {
            flex: 1;
        }

        .business-name {
            margin: 0;
            font-size: 1.35rem;
            color: white;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: white;
            padding: 12px 20px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: white;
            color: #667eea;
        }

        .page-heading {
            display: grid;
            gap: 18px;
            margin-bottom: 28px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #667eea;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.16);
            transition: transform 0.15s ease, background 0.15s ease;
            width: fit-content;
        }

        .back-btn:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }

        .topbar {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: center;
        }

        .page-title p {
            margin: 0 0 6px;
            font-size: 0.9rem;
            color: var(--primary);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .page-title h1 {
            margin: 0;
            font-size: clamp(2rem, 2.7vw, 2.9rem);
            line-height: 1.05;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.16);
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { background: var(--surface); color: var(--text); box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06); }
        .btn-danger { background: #f97316; color: #fff; }
        .btn-disabled { background: #d1d5db; color: #9ca3af; cursor: not-allowed; }

        .table-card {
            background: var(--surface);
            border-radius: 28px;
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .controls {
            display: grid;
            grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(180px, 1fr));
            gap: 14px;
            align-items: center;
            margin-bottom: 20px;
        }

        .controls input,
        .controls select {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 14px 16px;
            background: #fff;
            color: var(--text);
            font-size: 0.95rem;
        }

        .controls input::placeholder {
            color: var(--muted);
        }

        .bulk-banner {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 18px;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            margin-bottom: 18px;
        }

        .bulk-banner.active { display: flex; }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 860px;
            border-collapse: separate;
            border-spacing: 0;
        }

        thead th {
            position: sticky;
            top: 0;
            background: var(--surface);
            text-align: left;
            padding: 16px 14px;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        tbody tr.selected {
            background: #e0efff;
        }

        td {
            padding: 16px 14px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            font-size: 0.95rem;
        }

        .checkbox-cell {
            display: inline-flex;
            align-items: center;
        }

        .checkbox-cell input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            background: #eff6ff;
        }

        .badge.active { color: #134e4a; background: #dcfce7; }
        .badge.inactive { color: #831843; background: #fde2e8; }

        .tooltip {
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 8px;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.42);
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
            z-index: 300;
            backdrop-filter: blur(2px);
        }

        .modal-overlay.open { display: flex; }

        .modal-card {
            width: min(760px, 100%);
            max-height: 90vh;
            background: var(--surface);
            border-radius: 28px;
            box-shadow: 0 32px 90px rgba(15, 23, 42, 0.16);
            padding: 28px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            transform: translateY(20px);
            opacity: 0;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }

        .modal-overlay.open .modal-card {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 22px;
        }

        .form-progress {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 20px;
            padding: 16px 18px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            color: var(--muted);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .completion-bar {
            flex: 1;
            height: 10px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .completion-fill {
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: var(--primary);
            transition: width 0.25s ease;
        }

        .modal-section {
            grid-column: span 2;
            display: grid;
            gap: 16px;
            margin-bottom: 24px;
        }

        .modal-section h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--primary);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .section-divider {
            height: 1px;
            background: var(--border);
            border-radius: 999px;
        }

        .role-suggestion,
        .attendance-warning,
        .duplicate-warning {
            margin-top: 8px;
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.4;
        }

        .duplicate-warning {
            color: var(--danger);
        }

        .back-dashboard {
            position: sticky;
            top: 16px;
            z-index: 2;
            margin-bottom: 18px;
            width: fit-content;
        }

        .back-dashboard .btn {
            padding: 10px 16px;
            border-radius: 999px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.8rem;
        }

        .close-button {
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 1.4rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close-button:hover {
            color: var(--text);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            align-items: start;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group label {
            margin: 0;
            font-weight: 600;
            color: var(--text);
        }

        .input-field {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            min-height: 52px;
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 0 14px;
            background: #fff;
        }

        .input-field i {
            color: var(--muted);
            font-size: 18px;
            min-width: 18px;
        }

        .input-field input,
        .input-field select {
            width: 100%;
            border: 0;
            outline: none;
            min-height: 52px;
            font-size: 0.95rem;
            color: var(--text);
            background: transparent;
        }

        .input-field input[type="file"] {
            padding: 12px 0;
        }

        .input-field select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background: transparent;
        }

        .input-field select::-ms-expand {
            display: none;
        }

        .input-field select {
            background-image: linear-gradient(45deg, transparent 50%, var(--muted) 50%),
                              linear-gradient(135deg, var(--muted) 50%, transparent 50%),
                              linear-gradient(to right, #fff, #fff);
            background-position: calc(100% - 22px) calc(50% - 3px), calc(100% - 15px) calc(50% - 3px), 100% 0;
            background-size: 6px 6px, 6px 6px, 1px 100%;
            background-repeat: no-repeat;
        }

        .field-note {
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .error-text {
            margin-top: 8px;
            color: var(--danger);
            font-size: 0.85rem;
            min-height: 20px;
        }

        .toggle-panel {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 16px;
            border-radius: 18px;
            background: var(--surface-strong);
            border: 1px solid var(--border);
            margin-top: 4px;
        }

        .toggle-panel span { font-weight: 600; color: var(--text); }

        .toggle-track {
            position: relative;
            width: 60px;
            height: 32px;
            border-radius: 999px;
            background: #e5e7eb;
            cursor: pointer;
            flex-shrink: 0;
        }

        .toggle-track::after {
            content: '';
            position: absolute;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: transform 0.2s ease, background 0.2s ease;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
        }

        .toggle-track.active {
            background: var(--primary);
        }

        .toggle-track.active::after {
            transform: translateX(28px);
        }

        .photo-preview {
            min-height: 120px;
            border-radius: 18px;
            border: 1px dashed var(--border);
            background: #f8fafc;
            display: grid;
            place-items: center;
            color: var(--muted);
            text-align: center;
            padding: 18px;
        }

        .photo-preview img {
            max-width: 100%;
            border-radius: 16px;
            display: block;
        }

        .form-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            justify-content: flex-end;
            margin-top: 26px;
        }

        .sort-indicator {
            opacity: 0.5;
            margin-left: 8px;
            font-size: 0.88rem;
        }

        @media (max-width: 980px) {
            .controls { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            thead th { font-size: 0.78rem; }
        }

        @media (max-width: 640px) {
            .topbar { display: flex; flex-direction: column; align-items: flex-start; }
            .actions { width: 100%; justify-content: stretch; }
            .actions .btn { width: 100%; justify-content: center; }
            td, thead th { padding: 14px 12px; }
            .modal-card { padding: 22px; }
        }
    </style>
</head>
<body class="admin-page">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="logo-section">
                <h1 class="business-name">L LE JOSE</h1>
            </div>
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
    </header>

    <div class="page-shell">
        <div class="page-heading">
            <a href="admin_page.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <div class="topbar">
                <div class="page-title">
                    <p>Employee Center</p>
                    <h1>Staff roster & payroll</h1>
                </div>
                <div class="actions">
                <button class="btn btn-secondary" onclick="openEmployeeModal('add')">+ Add New Employee</button>
                <button id="editButton" class="btn btn-primary btn-disabled" onclick="openEmployeeModal('edit')" disabled>Edit Selected</button>
                <button id="deleteButton" class="btn btn-danger btn-disabled" onclick="openConfirmModal()" disabled>Delete Selected</button>
                <button class="btn btn-secondary" onclick="exportCsv()">Export to CSV</button>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div class="bulk-banner" style="display:flex; background:#fde2e8; border-color:#fecdd3; color:#b91c1c;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php elseif ($successMessage): ?>
            <div class="bulk-banner" style="display:flex; background:#dcfce7; border-color:#bbf7d0; color:#166534;">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="controls">
                <input id="searchInput" type="text" placeholder="Search by name, role, or status" aria-label="Search employees">
                <select id="roleFilter" aria-label="Filter by role">
                    <option value="All">All Roles</option>
                    <option value="Barista">Barista</option>
                    <option value="Cashier">Cashier</option>
                    <option value="Manager">Manager</option>
                </select>
                <select id="statusFilter" aria-label="Filter by status">
                    <option value="All">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <div style="font-size:0.95rem; color:var(--muted);">Rows are sortable by name, date hired, and salary.</div>
            </div>

            <div id="bulkBanner" class="bulk-banner">
                <div id="bulkLabel">0 employees selected</div>
                <button class="btn btn-danger" onclick="openConfirmModal()">Delete selected</button>
            </div>

            <div class="table-wrapper">
                <table aria-label="Employee list">
                    <thead>
                        <tr>
                            <th><span class="checkbox-cell"><input type="checkbox" id="selectAll"></span></th>
                            <th data-sort="id">ID <span class="sort-indicator">↕</span></th>
                            <th data-sort="name">Name <span class="sort-indicator">↕</span></th>
                            <th data-sort="role">Role <span class="sort-indicator">↕</span></th>
                            <th data-sort="status">Status <span class="sort-indicator">↕</span></th>
                            <th data-sort="salary">Salary <span class="sort-indicator">↕</span></th>
                            <th data-sort="date_hired">Date Hired <span class="sort-indicator">↕</span></th>
                        </tr>
                    </thead>
                    <tbody id="employeeTableBody">
                        <?php foreach ($employees as $emp): ?>
                            <tr data-id="<?php echo intval($emp['id']); ?>">
                                <td><input type="checkbox" data-id="<?php echo intval($emp['id']); ?>"></td>
                                <td>#<?php echo intval($emp['id']); ?></td>
                                <td><?php echo htmlspecialchars($emp['name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['role']); ?></td>
                                <td><span class="badge <?php echo (isset($emp['employment_status']) && $emp['employment_status'] === 'Inactive') ? 'inactive' : 'active'; ?>"><?php echo htmlspecialchars($emp['employment_status'] ?? 'Active'); ?></span></td>
                                <td>₱<?php echo number_format($emp['salary'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($emp['date_hired'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="employeeModal" class="modal-overlay" aria-hidden="true">
        <article class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <div>
                    <p style="margin:0;color:var(--primary);font-size:0.9rem;letter-spacing:0.08em;text-transform:uppercase;">Staff profile</p>
                    <h2 id="modalTitle">Add New Employee</h2>
                </div>
                <button class="close-button" aria-label="Close modal" onclick="closeEmployeeModal()">×</button>
            </div>

            <form id="employeeForm" method="POST" onsubmit="handleFormSubmit(event)">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId">

                <div class="form-progress" aria-hidden="true">
                <span id="progressText">Form Completion: 0%</span>
                <div class="completion-bar"><span id="completionFill" class="completion-fill"></span></div>
            </div>

            <div class="form-grid">
                <div class="modal-section">
                    <h3>Personal Info</h3>
                    <div class="section-divider"></div>

                    <div class="input-group">
                        <label for="fullName">Full Name</label>
                        <div class="input-field">
                            <i class="fas fa-user"></i>
                            <input id="fullName" name="name" type="text" placeholder="e.g., Juan Dela Cruz" autocomplete="name" required>
                        </div>
                        <div class="field-note">Enter the employee's full legal name.</div>
                        <div class="error-text" id="nameError"></div>
                    </div>

                    <div class="input-group">
                        <label for="jobRole">Job Role</label>
                        <div class="input-field">
                            <i class="fas fa-briefcase"></i>
                            <select id="jobRole" name="role" onchange="handleRoleChange()" required>
                                <option value="Barista">Barista</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Manager">Manager</option>
                                <option value="Custom">Custom</option>
                            </select>
                        </div>
                        <div class="field-note">Pick the most appropriate role.</div>
                        <div class="role-suggestion" id="roleSuggestion"></div>
                        <div class="duplicate-warning" id="duplicateWarning"></div>
                        <div class="error-text" id="roleError"></div>
                    </div>

                    <div class="input-group">
                        <label for="contactNumber">Contact Number</label>
                        <div class="input-field">
                            <i class="fas fa-phone"></i>
                            <input id="contactNumber" name="contact_number" type="text" inputmode="numeric" placeholder="09xxxxxxxxx" minlength="10" maxlength="13">
                        </div>
                        <div class="field-note">Only digits are accepted.</div>
                        <div class="error-text" id="contactError"></div>
                    </div>

                    <div class="input-group">
                        <label for="emailAddress">Email Address</label>
                        <div class="input-field">
                            <i class="fas fa-envelope"></i>
                            <input id="emailAddress" name="email" type="email" placeholder="example@domain.com">
                        </div>
                        <div class="field-note">Optional. Use a valid email format.</div>
                        <div class="error-text" id="emailError"></div>
                    </div>
                </div>

                <div class="modal-section">
                    <h3>Work Details</h3>
                    <div class="section-divider"></div>

                    <div class="input-group">
                        <label for="attendanceDays">Attendance Days</label>
                        <div class="input-field">
                            <i class="fas fa-calendar-day"></i>
                            <input id="attendanceDays" name="attendance_days" type="number" min="0" step="1" value="0" required>
                        </div>
                        <div class="field-note">Enter total working days for the month.</div>
                        <div class="attendance-warning" id="attendanceWarning"></div>
                        <div class="error-text" id="attendanceError"></div>
                    </div>

                    <div class="input-group">
                        <label for="monthlySalary">Monthly Salary</label>
                        <div class="input-field">
                            <i class="fas fa-money-bill-wave"></i>
                            <input id="monthlySalary" name="salary" type="text" placeholder="₱ 10,000.00" required>
                        </div>
                        <div class="field-note" id="salaryHint">Auto-formats Philippine Peso as you type.</div>
                        <div class="error-text" id="salaryError"></div>
                    </div>

                    <div class="input-group">
                        <label for="dateHired">Date Hired</label>
                        <div class="input-field">
                            <i class="fas fa-calendar-alt"></i>
                            <input id="dateHired" name="date_hired" type="date" required>
                        </div>
                        <div class="field-note">Pick the official start date.</div>
                        <div class="error-text" id="dateError"></div>
                    </div>

                    <div class="input-group" style="grid-column: span 2;">
                        <label>Employment Status</label>
                        <div class="toggle-panel" onclick="toggleEmploymentStatus()">
                            <div class="toggle-track" id="statusToggle"></div>
                            <span id="statusLabel">Active</span>
                            <input id="employmentStatus" name="employment_status" type="hidden" value="Active">
                        </div>
                        <div class="field-note">Toggle between active and inactive.</div>
                    </div>
                </div>

                <div class="modal-section">
                    <h3>System Info</h3>
                    <div class="section-divider"></div>

                    <div class="input-group" style="grid-column: span 2;">
                        <label for="profilePhoto">Profile Photo</label>
                        <div class="input-field">
                            <i class="fas fa-image"></i>
                            <input id="profilePhoto" name="profile_photo" type="file" accept="image/*">
                        </div>
                        <div class="field-note">Optional. Preview shown below.</div>
                        <div class="photo-preview" id="photoPreview">No photo selected</div>
                    </div>
                </div>
            </div>

                <div class="form-footer">
                    <button id="saveButton" type="submit" class="btn btn-primary btn-disabled" disabled>Save Employee</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">Cancel</button>
                </div>
            </form>
        </article>
    </div>

    <div id="confirmModal" class="modal-overlay" aria-hidden="true">
        <article class="modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
            <div class="modal-header">
                <div>
                    <p style="margin:0;color:#f97316;font-size:0.9rem;letter-spacing:0.08em;text-transform:uppercase;">Confirm delete</p>
                    <h2 id="confirmTitle">Delete selected employees</h2>
                </div>
                <button class="close-button" aria-label="Close confirmation" onclick="closeConfirmModal()">×</button>
            </div>
            <p style="color:var(--muted); line-height:1.7;">This action cannot be undone. You are about to remove the selected employee record(s) from the system.</p>
            <div class="form-footer" style="margin-top:24px;">
                <button class="btn btn-danger" onclick="confirmDeletion()">Yes, delete</button>
                <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
            </div>
        </article>
    </div>

    <script>
        const employees = <?php echo json_encode($employees); ?>;
        let selectedIds = new Set();
        let currentSort = { field: 'id', direction: 'asc' };
        let currentFilters = { search: '', role: 'All', status: 'All' };

        const employeeTableBody = document.getElementById('employeeTableBody');
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const selectAllCheckbox = document.getElementById('selectAll');
        const bulkBanner = document.getElementById('bulkBanner');
        const bulkLabel = document.getElementById('bulkLabel');
        const editButton = document.getElementById('editButton');
        const deleteButton = document.getElementById('deleteButton');
        const saveButton = document.getElementById('saveButton');

        const fullName = document.getElementById('fullName');
        const jobRole = document.getElementById('jobRole');
        const contactNumber = document.getElementById('contactNumber');
        const emailAddress = document.getElementById('emailAddress');
        const attendanceDays = document.getElementById('attendanceDays');
        const monthlySalary = document.getElementById('monthlySalary');
        const dateHired = document.getElementById('dateHired');
        const employmentStatus = document.getElementById('employmentStatus');
        const statusLabel = document.getElementById('statusLabel');
        const statusToggle = document.getElementById('statusToggle');
        const photoPreview = document.getElementById('photoPreview');
        const profilePhoto = document.getElementById('profilePhoto');
        const formAction = document.getElementById('formAction');
        const formId = document.getElementById('formId');

        const inputErrors = {
            name: document.getElementById('nameError'),
            role: document.getElementById('roleError'),
            contact: document.getElementById('contactError'),
            email: document.getElementById('emailError'),
            attendance: document.getElementById('attendanceError'),
            salary: document.getElementById('salaryError'),
            date: document.getElementById('dateError')
        };

        const hintSalary = document.getElementById('salaryHint');
        const duplicateWarning = document.getElementById('duplicateWarning');
        const roleSuggestion = document.getElementById('roleSuggestion');
        const attendanceWarning = document.getElementById('attendanceWarning');
        const completionFill = document.getElementById('completionFill');
        const progressText = document.getElementById('progressText');

        function renderTable() {
            const rows = employees
                .filter(employee => {
                    const query = currentFilters.search.toLowerCase();
                    const statusValue = employee.employment_status || 'Active';
                    return (
                        employee.name.toLowerCase().includes(query) ||
                        employee.role.toLowerCase().includes(query) ||
                        statusValue.toLowerCase().includes(query) ||
                        String(employee.id).includes(query)
                    ) &&
                    (currentFilters.role === 'All' || employee.role === currentFilters.role) &&
                    (currentFilters.status === 'All' || statusValue === currentFilters.status);
                })
                .sort((a, b) => {
                    const field = currentSort.field;
                    let av = a[field] ?? '';
                    let bv = b[field] ?? '';

                    if (field === 'salary') {
                        av = parseFloat(av) || 0;
                        bv = parseFloat(bv) || 0;
                    }
                    if (field === 'date_hired') {
                        av = new Date(av);
                        bv = new Date(bv);
                    }

                    if (av < bv) return currentSort.direction === 'asc' ? -1 : 1;
                    if (av > bv) return currentSort.direction === 'asc' ? 1 : -1;
                    return 0;
                });

            employeeTableBody.innerHTML = rows.map(emp => {
                const statusValue = emp.employment_status || 'Active';
                const isChecked = selectedIds.has(parseInt(emp.id, 10));
                return `
                    <tr data-id="${emp.id}" class="${isChecked ? 'selected' : ''}">
                        <td><input type="checkbox" data-id="${emp.id}" ${isChecked ? 'checked' : ''}></td>
                        <td>#${emp.id}</td>
                        <td>${escapeHtml(emp.name)}</td>
                        <td>${escapeHtml(emp.role)}</td>
                        <td><span class="badge ${statusValue === 'Inactive' ? 'inactive' : 'active'}">${escapeHtml(statusValue)}</span></td>
                        <td>₱${Number(emp.salary).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                        <td>${formatDate(emp.date_hired)}</td>
                    </tr>
                `;
            }).join('');

            attachRowEvents();
            updateBulkState();
            updateSelectAll();
        }

        function escapeHtml(value) {
            return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function formatDate(value) {
            if (!value) return '--';
            const date = new Date(value);
            if (Number.isNaN(date.valueOf())) return value;
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
        }

        function attachRowEvents() {
            document.querySelectorAll('#employeeTableBody tr').forEach(row => {
                row.onclick = event => {
                    if (event.target.tagName === 'INPUT') return;
                    const id = parseInt(row.dataset.id, 10);
                    toggleSelection(id);
                };
            });

            document.querySelectorAll('#employeeTableBody input[type="checkbox"]').forEach(checkbox => {
                checkbox.onchange = event => {
                    const id = parseInt(event.target.dataset.id, 10);
                    if (event.target.checked) {
                        selectedIds.add(id);
                    } else {
                        selectedIds.delete(id);
                    }
                    updateBulkState();
                    updateSelectAll();
                };
            });
        }

        function toggleSelection(id) {
            if (selectedIds.has(id)) {
                selectedIds.delete(id);
            } else {
                selectedIds.add(id);
            }
            renderTable();
        }

        function updateBulkState() {
            const count = selectedIds.size;
            if (count > 0) {
                bulkBanner.classList.add('active');
                bulkLabel.textContent = `${count} employee${count === 1 ? '' : 's'} selected`;
                editButton.disabled = count !== 1;
                deleteButton.disabled = false;
                editButton.classList.toggle('btn-disabled', count !== 1);
                deleteButton.classList.toggle('btn-disabled', false);
            } else {
                bulkBanner.classList.remove('active');
                bulkLabel.textContent = '0 employees selected';
                editButton.disabled = true;
                deleteButton.disabled = true;
                editButton.classList.add('btn-disabled');
                deleteButton.classList.add('btn-disabled');
            }
        }

        function updateSelectAll() {
            const rows = document.querySelectorAll('#employeeTableBody input[type="checkbox"]');
            const checked = Array.from(rows).filter(cb => cb.checked).length;
            selectAllCheckbox.checked = rows.length > 0 && checked === rows.length;
        }

        selectAllCheckbox.onchange = () => {
            document.querySelectorAll('#employeeTableBody input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
                const id = parseInt(checkbox.dataset.id, 10);
                if (selectAllCheckbox.checked) selectedIds.add(id);
                else selectedIds.delete(id);
            });
            updateBulkState();
        };

        document.querySelectorAll('th[data-sort]').forEach(header => {
            header.addEventListener('click', () => {
                const field = header.dataset.sort;
                if (currentSort.field === field) {
                    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.field = field;
                    currentSort.direction = 'asc';
                }
                renderTable();
            });
        });

        searchInput.addEventListener('input', () => {
            currentFilters.search = searchInput.value.trim();
            renderTable();
        });
        roleFilter.addEventListener('change', () => {
            currentFilters.role = roleFilter.value;
            renderTable();
        });
        statusFilter.addEventListener('change', () => {
            currentFilters.status = statusFilter.value;
            renderTable();
        });

        function openEmployeeModal(mode) {
            resetForm();
            document.getElementById('employeeModal').classList.add('open');
            document.getElementById('employeeModal').setAttribute('aria-hidden', 'false');
            document.getElementById('modalTitle').textContent = mode === 'edit' ? 'Edit Employee' : 'Add New Employee';
            formAction.value = mode === 'edit' ? 'update' : 'add';
            saveButton.disabled = true;
            saveButton.classList.add('btn-disabled');

            if (mode === 'edit') {
                if (selectedIds.size !== 1) {
                    alert('Please select exactly one employee to edit.');
                    closeEmployeeModal();
                    return;
                }
                const id = Array.from(selectedIds)[0];
                const emp = employees.find(item => parseInt(item.id, 10) === id);
                if (!emp) return;
                formId.value = emp.id;
                fullName.value = emp.name || '';
                contactNumber.value = emp.contact_number || '';
                emailAddress.value = emp.email || '';
                attendanceDays.value = parseInt(emp.attendance_days, 10) || 0;
                monthlySalary.value = formatPeso(parseFloat(emp.salary) || 0);
                dateHired.value = emp.date_hired ? new Date(emp.date_hired).toISOString().split('T')[0] : new Date().toISOString().split('T')[0];
                const roleValue = ['Barista','Cashier','Manager'].includes(emp.role) ? emp.role : 'Custom';
                jobRole.value = roleValue;
                handleRoleChange();
                if (roleValue === 'Custom') {
                    jobRole.value = 'Custom';
                    document.getElementById('customRoleInput')?.remove();
                    const customInput = document.createElement('input');
                    customInput.id = 'customRoleInput';
                    customInput.name = 'role';
                    customInput.type = 'text';
                    customInput.value = emp.role || '';
                    customInput.placeholder = 'Enter custom role name';
                    customInput.style = 'width:100%; border-radius:16px; border:1px solid var(--border); padding:14px 16px; margin-top:8px; font-size:0.95rem;';
                    jobRole.parentElement.appendChild(customInput);
                }
                const isActive = (emp.employment_status || 'Active') === 'Active';
                setEmploymentStatus(isActive);
                updateSalaryEstimate();
            } else {
                const today = new Date().toISOString().split('T')[0];
                dateHired.value = today;
                attendanceDays.value = 0;
                monthlySalary.value = '';
                setEmploymentStatus(true);
                handleRoleChange();
                photoPreview.innerHTML = 'No photo selected';
            }

            validateForm();
        }

        function closeEmployeeModal() {
            document.getElementById('employeeModal').classList.remove('open');
            document.getElementById('employeeModal').setAttribute('aria-hidden', 'true');
        }

        function openConfirmModal() {
            if (selectedIds.size === 0) {
                alert('Please select one or more employees to delete.');
                return;
            }
            document.getElementById('confirmModal').classList.add('open');
            document.getElementById('confirmModal').setAttribute('aria-hidden', 'false');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('open');
            document.getElementById('confirmModal').setAttribute('aria-hidden', 'true');
        }

        function confirmDeletion() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = '<input type="hidden" name="action" value="delete">';
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }

        function handleRoleChange() {
            const customInput = document.getElementById('customRoleInput');
            if (jobRole.value === 'Custom') {
                jobRole.name = '';
                if (!customInput) {
                    const input = document.createElement('input');
                    input.id = 'customRoleInput';
                    input.name = 'role';
                    input.type = 'text';
                    input.placeholder = 'Enter custom role name';
                    input.style = 'width:100%; border-radius:16px; border:1px solid var(--border); padding:14px 16px; margin-top:8px; font-size:0.95rem;';
                    jobRole.parentElement.appendChild(input);
                    input.addEventListener('input', validateForm);
                }
            } else {
                if (customInput) {
                    customInput.remove();
                }
                jobRole.name = 'role';
            }
            updateRoleSuggestion();
            validateForm();
        }

        function toggleEmploymentStatus() {
            const active = employmentStatus.value === 'Active';
            setEmploymentStatus(!active);
        }

        function getRoleSalaryRange(role) {
            switch (role) {
                case 'Barista': return 'Suggested salary range: ₱8,000–₱12,000';
                case 'Cashier': return 'Suggested salary range: ₱9,000–₱13,000';
                case 'Manager': return 'Suggested salary range: ₱15,000+';
                default: return 'Custom role may vary; set salary according to experience.';
            }
        }

        function updateRoleSuggestion() {
            const roleValue = jobRole.value === 'Custom' ? document.getElementById('customRoleInput')?.value.trim() || 'Custom' : jobRole.value;
            roleSuggestion.textContent = getRoleSalaryRange(roleValue);
        }

        function updateCompletionMeter() {
            const values = [
                fullName.value.trim(),
                (jobRole.value === 'Custom' ? document.getElementById('customRoleInput')?.value.trim() : jobRole.value) || '',
                attendanceDays.value.trim(),
                monthlySalary.value.trim(),
                dateHired.value.trim()
            ];
            const completed = values.filter(value => value !== '').length;
            const percent = Math.round((completed / values.length) * 100);
            progressText.textContent = `Form Completion: ${percent}%`;
            completionFill.style.width = `${percent}%`;
        }

        function setEmploymentStatus(isActive) {
            employmentStatus.value = isActive ? 'Active' : 'Inactive';
            statusLabel.textContent = isActive ? 'Active' : 'Inactive';
            statusToggle.classList.toggle('active', isActive);
        }

        function resetForm() {
            document.getElementById('employeeForm').reset();
            const customInput = document.getElementById('customRoleInput');
            if (customInput) customInput.remove();
            jobRole.value = 'Barista';
            contactNumber.value = '';
            emailAddress.value = '';
            attendanceDays.value = 0;
            monthlySalary.value = '';
            dateHired.value = new Date().toISOString().split('T')[0];
            setEmploymentStatus(true);
            photoPreview.innerHTML = 'No photo selected';
            Object.values(inputErrors).forEach(el => el.textContent = '');
            hintSalary.textContent = 'Auto-formats Philippine Peso as you type.';
            duplicateWarning.textContent = '';
            attendanceWarning.textContent = '';
            formId.value = '';
            updateRoleSuggestion();
            updateCompletionMeter();
        }

        function handleFormSubmit(event) {
            event.preventDefault();
            if (!validateForm()) return;
            document.getElementById('employeeForm').submit();
        }

        function validateForm() {
            let valid = true;
            const nameValue = fullName.value.trim();
            const roleValue = jobRole.value === 'Custom' ? document.getElementById('customRoleInput')?.value.trim() : jobRole.value;
            const contactValue = contactNumber.value.trim();
            const emailValue = emailAddress.value.trim();
            const attendanceValue = Number(attendanceDays.value);
            const salaryValue = parseFloat(monthlySalary.value.replace(/[^0-9.]/g, ''));
            const dateValue = dateHired.value;

            inputErrors.name.textContent = nameValue ? '' : 'Full name is required.';
            if (!nameValue) valid = false;

            inputErrors.role.textContent = roleValue ? '' : 'Please select or type a job role.';
            if (!roleValue) valid = false;

            if (contactValue && !/^\d{10,13}$/.test(contactValue)) {
                inputErrors.contact.textContent = 'Enter a valid 10-13 digit number.';
                valid = false;
            } else {
                inputErrors.contact.textContent = '';
            }

            if (emailValue && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(emailValue)) {
                inputErrors.email.textContent = 'Email must be in the correct format.';
                valid = false;
            } else {
                inputErrors.email.textContent = '';
            }

            if (!Number.isInteger(attendanceValue) || attendanceValue < 0) {
                inputErrors.attendance.textContent = 'Attendance days must be a non-negative whole number.';
                valid = false;
            } else {
                inputErrors.attendance.textContent = '';
            }

            if (!salaryValue || salaryValue <= 0) {
                inputErrors.salary.textContent = 'Monthly salary must be greater than ₱0.00.';
                valid = false;
            } else {
                inputErrors.salary.textContent = '';
            }

            if (!dateValue) {
                inputErrors.date.textContent = 'A hire date is required.';
                valid = false;
            } else {
                inputErrors.date.textContent = '';
            }

            if (attendanceValue > 0 && attendanceValue < 12) {
                attendanceWarning.textContent = 'Low attendance may affect salary calculation.';
            } else {
                attendanceWarning.textContent = '';
            }

            const duplicateEmp = employees.find(emp => parseInt(emp.id, 10) !== Number(formId.value) && emp.name.trim().toLowerCase() === nameValue.toLowerCase() && emp.role.trim().toLowerCase() === (roleValue || '').toLowerCase());
            if (duplicateEmp) {
                duplicateWarning.textContent = 'Possible duplicate employee detected.';
                valid = false;
            } else {
                duplicateWarning.textContent = '';
            }

            updateRoleSuggestion();
            updateSalaryEstimate();
            updateCompletionMeter();
            saveButton.disabled = !valid;
            saveButton.classList.toggle('btn-disabled', !valid);
            return valid;
        }

        function updateSalaryEstimate() {
            const salaryValue = parseFloat(monthlySalary.value.replace(/[^0-9.]/g, '')) || 0;
            const attendanceValue = Number(attendanceDays.value) || 0;
            if (salaryValue > 0) {
                const dailyRate = salaryValue / 22;
                if (attendanceValue > 0) {
                    const estimate = dailyRate * attendanceValue;
                    hintSalary.textContent = `Daily rate: ₱ ${dailyRate.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})} · Estimated salary for ${attendanceValue} days: ₱ ${estimate.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
                } else {
                    hintSalary.textContent = `Daily rate estimate: ₱ ${dailyRate.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})} based on 22 working days.`;
                }
            } else {
                hintSalary.textContent = 'Auto-formats Philippine Peso as you type.';
            }
        }

        function formatPeso(amount) {
            if (Number.isNaN(amount) || amount === null) return '';
            return '₱ ' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        monthlySalary.addEventListener('input', event => {
            const clean = event.target.value.replace(/[^0-9.]/g, '');
            const numeric = clean ? parseFloat(clean) : 0;
            if (clean === '') {
                event.target.value = '';
            } else {
                event.target.value = formatPeso(numeric);
            }
            validateForm();
        });

        attendanceDays.addEventListener('input', () => {
            attendanceDays.value = Math.max(0, Math.floor(Number(attendanceDays.value) || 0));
            validateForm();
        });

        contactNumber.addEventListener('input', () => {
            contactNumber.value = contactNumber.value.replace(/\D/g, '');
            validateForm();
        });

        emailAddress.addEventListener('input', validateForm);
        fullName.addEventListener('input', validateForm);
        jobRole.addEventListener('change', validateForm);
        dateHired.addEventListener('change', validateForm);

        profilePhoto.addEventListener('change', () => {
            const file = profilePhoto.files[0];
            if (!file) {
                photoPreview.innerHTML = 'No photo selected';
                return;
            }
            const reader = new FileReader();
            reader.onload = () => {
                photoPreview.innerHTML = `<img src="${reader.result}" alt="Profile preview">`;
            };
            reader.readAsDataURL(file);
        });

        function exportCsv() {
            const headers = ['ID', 'Name', 'Role', 'Status', 'Salary', 'Date Hired'];
            const rows = Array.from(document.querySelectorAll('#employeeTableBody tr')).map(row => {
                return Array.from(row.querySelectorAll('td')).slice(1).map(cell => cell.textContent.trim());
            });
            const csv = [headers, ...rows].map(r => r.map(value => `"${value.replace(/"/g, '""')}"`).join(',')).join('\n');
            const link = document.createElement('a');
            link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            link.download = 'employees.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
        }

        document.addEventListener('click', event => {
            if (event.target.closest('.modal-overlay') && !event.target.closest('.modal-card')) {
                closeEmployeeModal();
                closeConfirmModal();
            }
        });

        renderTable();
    </script>
</body>
</html>
