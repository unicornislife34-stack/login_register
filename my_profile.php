<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$profileTableSql = "CREATE TABLE IF NOT EXISTS employee_profiles (
    employee_id INT PRIMARY KEY,
    date_of_birth DATE DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone_number VARCHAR(50) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    reporting_to VARCHAR(150) DEFAULT NULL,
    employment_status VARCHAR(30) DEFAULT 'Active',
    profile_photo VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $profileTableSql);

$skillsTableSql = "CREATE TABLE IF NOT EXISTS employee_profile_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    skill_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $skillsTableSql);

$contactsTableSql = "CREATE TABLE IF NOT EXISTS employee_emergency_contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(100) DEFAULT NULL,
    primary_phone VARCHAR(50) DEFAULT NULL,
    secondary_phone VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $contactsTableSql);

function saveProfilePhoto($file, $uploadDir)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('profile_', true) . '.' . $ext;
    $target = rtrim($uploadDir, '/') . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $target;
    }

    return null;
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
        'salary' => '0.00',
        'date_hired' => date('Y-m-d H:i:s')
    ];
} else {
    $employeeId = (int) $employee['id'];
}

$profileResult = mysqli_query($conn, "SELECT * FROM employee_profiles WHERE employee_id = $employeeId LIMIT 1");
$profile = $profileResult ? mysqli_fetch_assoc($profileResult) : null;

if (!$profile) {
    mysqli_query($conn, "INSERT INTO employee_profiles (employee_id, employment_status) VALUES ($employeeId, 'Active')");
    $profileResult = mysqli_query($conn, "SELECT * FROM employee_profiles WHERE employee_id = $employeeId LIMIT 1");
    $profile = $profileResult ? mysqli_fetch_assoc($profileResult) : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $toastMessage = '';
    $toastType = 'success';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $dateHired = trim($_POST['date_hired'] ?? '');
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $reportingTo = trim($_POST['reporting_to'] ?? '');
        $employmentStatus = trim($_POST['employment_status'] ?? 'Active');

        if ($fullName === '' || $role === '' || $dateHired === '') {
            $toastMessage = 'Full name, position, and hire date are required.';
            $toastType = 'error';
        } else {
            $fullNameEscaped = mysqli_real_escape_string($conn, $fullName);
            $roleEscaped = mysqli_real_escape_string($conn, $role);
            $dateHiredEscaped = mysqli_real_escape_string($conn, $dateHired);
            $dateOfBirthSql = $dateOfBirth !== '' ? "'" . mysqli_real_escape_string($conn, $dateOfBirth) . "'" : "NULL";
            $emailSql = $email !== '' ? "'" . mysqli_real_escape_string($conn, $email) . "'" : "NULL";
            $phoneSql = $phoneNumber !== '' ? "'" . mysqli_real_escape_string($conn, $phoneNumber) . "'" : "NULL";
            $addressSql = $address !== '' ? "'" . mysqli_real_escape_string($conn, $address) . "'" : "NULL";
            $departmentSql = $department !== '' ? "'" . mysqli_real_escape_string($conn, $department) . "'" : "NULL";
            $reportingToSql = $reportingTo !== '' ? "'" . mysqli_real_escape_string($conn, $reportingTo) . "'" : "NULL";
            $statusEscaped = mysqli_real_escape_string($conn, $employmentStatus ?: 'Active');

            mysqli_query($conn, "UPDATE employee SET name='$fullNameEscaped', role='$roleEscaped', date_hired='$dateHiredEscaped' WHERE id=$employeeId");

            $photoPathToSave = null;
            $newPhotoPath = saveProfilePhoto($_FILES['profile_photo'] ?? null, 'uploads/profile_photos/');
            if ($newPhotoPath) {
                $photoPathToSave = "'" . mysqli_real_escape_string($conn, $newPhotoPath) . "'";
            }

            $profileUpdateSql = "
                UPDATE employee_profiles
                SET
                    date_of_birth = $dateOfBirthSql,
                    email = $emailSql,
                    phone_number = $phoneSql,
                    address = $addressSql,
                    department = $departmentSql,
                    reporting_to = $reportingToSql,
                    employment_status = '$statusEscaped'
                    " . ($photoPathToSave ? ", profile_photo = $photoPathToSave" : "") . "
                WHERE employee_id = $employeeId
            ";
            mysqli_query($conn, $profileUpdateSql);

            $toastMessage = 'Your profile has been updated.';
        }
    }

    if ($action === 'add_skill') {
        $skillName = trim($_POST['skill_name'] ?? '');
        if ($skillName === '') {
            $toastMessage = 'Please enter a skill name.';
            $toastType = 'error';
        } else {
            $skillEscaped = mysqli_real_escape_string($conn, $skillName);
            $duplicateSkill = mysqli_query($conn, "SELECT id FROM employee_profile_skills WHERE employee_id = $employeeId AND LOWER(skill_name) = LOWER('$skillEscaped') LIMIT 1");
            if ($duplicateSkill && mysqli_num_rows($duplicateSkill) > 0) {
                $toastMessage = 'That skill is already listed.';
                $toastType = 'error';
            } else {
                mysqli_query($conn, "INSERT INTO employee_profile_skills (employee_id, skill_name) VALUES ($employeeId, '$skillEscaped')");
                $toastMessage = 'Skill added to your profile.';
            }
        }
    }

    if ($action === 'delete_skill') {
        $skillId = (int) ($_POST['skill_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM employee_profile_skills WHERE id = $skillId AND employee_id = $employeeId");
        $toastMessage = 'Skill removed from your profile.';
    }

    if ($action === 'add_contact') {
        $contactName = trim($_POST['contact_name'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');
        $primaryPhone = trim($_POST['primary_phone'] ?? '');
        $secondaryPhone = trim($_POST['secondary_phone'] ?? '');

        if ($contactName === '' || $primaryPhone === '') {
            $toastMessage = 'Contact name and primary phone are required.';
            $toastType = 'error';
        } else {
            $contactNameEscaped = mysqli_real_escape_string($conn, $contactName);
            $relationshipSql = $relationship !== '' ? "'" . mysqli_real_escape_string($conn, $relationship) . "'" : "NULL";
            $primaryPhoneEscaped = mysqli_real_escape_string($conn, $primaryPhone);
            $secondaryPhoneSql = $secondaryPhone !== '' ? "'" . mysqli_real_escape_string($conn, $secondaryPhone) . "'" : "NULL";

            mysqli_query($conn, "
                INSERT INTO employee_emergency_contacts (employee_id, contact_name, relationship, primary_phone, secondary_phone)
                VALUES ($employeeId, '$contactNameEscaped', $relationshipSql, '$primaryPhoneEscaped', $secondaryPhoneSql)
            ");
            $toastMessage = 'Emergency contact added.';
        }
    }

    if ($action === 'delete_contact') {
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM employee_emergency_contacts WHERE id = $contactId AND employee_id = $employeeId");
        $toastMessage = 'Emergency contact removed.';
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $toastMessage = 'Please complete all password fields.';
            $toastType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $toastMessage = 'New password and confirmation do not match.';
            $toastType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $toastMessage = 'New password must be at least 6 characters.';
            $toastType = 'error';
        } else {
            $usernameEscaped = mysqli_real_escape_string($conn, $username);
            $userResult = mysqli_query($conn, "SELECT id, password FROM users WHERE username = '$usernameEscaped' LIMIT 1");
            $userRow = $userResult ? mysqli_fetch_assoc($userResult) : null;

            if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
                $toastMessage = 'Your current password is incorrect.';
                $toastType = 'error';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $newHashEscaped = mysqli_real_escape_string($conn, $newHash);
                mysqli_query($conn, "UPDATE users SET password = '$newHashEscaped' WHERE id = " . (int) $userRow['id']);
                $toastMessage = 'Your password has been changed.';
            }
        }
    }

    $_SESSION['profile_toast_message'] = $toastMessage;
    $_SESSION['profile_toast_type'] = $toastType;
    header('Location: my_profile.php');
    exit();
}

$profileResult = mysqli_query($conn, "SELECT * FROM employee_profiles WHERE employee_id = $employeeId LIMIT 1");
$profile = $profileResult ? mysqli_fetch_assoc($profileResult) : [];

$skills = [];
$skillsResult = mysqli_query($conn, "SELECT id, skill_name FROM employee_profile_skills WHERE employee_id = $employeeId ORDER BY skill_name ASC");
if ($skillsResult) {
    $skills = mysqli_fetch_all($skillsResult, MYSQLI_ASSOC);
}

$contacts = [];
$contactsResult = mysqli_query($conn, "SELECT id, contact_name, relationship, primary_phone, secondary_phone FROM employee_emergency_contacts WHERE employee_id = $employeeId ORDER BY id ASC");
if ($contactsResult) {
    $contacts = mysqli_fetch_all($contactsResult, MYSQLI_ASSOC);
}

$pageToastMessage = $_SESSION['profile_toast_message'] ?? '';
$pageToastType = $_SESSION['profile_toast_type'] ?? 'success';
unset($_SESSION['profile_toast_message'], $_SESSION['profile_toast_type']);

$employeeCode = 'EM' . str_pad((string) $employeeId, 4, '0', STR_PAD_LEFT);
$employmentStatus = $profile['employment_status'] ?? 'Active';
$profilePhoto = $profile['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - L LE JOSE</title>
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
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; text-decoration: none;
            color: rgba(255, 255, 255, 0.9); background: rgba(255, 255, 255, 0.06); border: 1px solid transparent;
            font-size: 0.95rem; font-weight: 600; transition: 0.25s ease;
        }
        .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.16); border-color: rgba(255,255,255,0.16); }
        .nav-item i { width: 18px; text-align: center; font-size: 0.95rem; }
        .sidebar-footer {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 14px 12px;
            backdrop-filter: blur(14px);
        }
        .user-card { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.18); display: grid; place-items: center; }
        .user-code { font-size: 0.95rem; font-weight: 700; line-height: 1.1; }
        .user-role { font-size: 0.78rem; color: rgba(255,255,255,0.72); }
        .logout-btn {
            width: 100%; border: 0; border-radius: 12px; padding: 11px 12px; background: rgba(255,255,255,0.16);
            color: #fff; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.25s ease;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.24); }
        .main-content { flex: 1; padding: 22px 24px 28px; overflow: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 16px; }
        .welcome-header { font-size: 2rem; font-weight: 800; letter-spacing: -0.03em; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px;
            background: rgba(34, 197, 94, 0.14); color: #86efac; font-size: 0.86rem; font-weight: 700;
            border: 1px solid rgba(34, 197, 94, 0.22);
        }
        .shell { display: flex; flex-direction: column; gap: 16px; }
        .profile-grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: 16px; }
        .panel {
            position: relative; background: linear-gradient(180deg, rgba(17,24,39,0.8) 0%, rgba(11,18,32,0.88) 100%);
            border: 1px solid var(--line); border-radius: 22px; box-shadow: 0 15px 40px rgba(0,0,0,0.34); overflow: hidden;
            backdrop-filter: blur(18px);
        }
        .panel-inner { padding: 18px; }
        .panel-title { font-size: 0.78rem; color: rgba(255,255,255,0.88); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 18px; }
        .personal-card {
            background: linear-gradient(145deg, rgba(21, 94, 117, 0.48) 0%, rgba(17, 24, 39, 0.72) 100%);
        }
        .personal-layout { display: grid; grid-template-columns: 150px 1fr; gap: 18px; }
        .photo-block { display: grid; gap: 12px; justify-items: center; }
        .photo-frame {
            width: 124px; height: 124px; border-radius: 50%; overflow: hidden; border: 3px solid rgba(255,255,255,0.22);
            background: rgba(255,255,255,0.08); display: grid; place-items: center; color: rgba(255,255,255,0.7); font-size: 3rem;
        }
        .photo-frame img { width: 100%; height: 100%; object-fit: cover; }
        .mini-btn {
            border: 0; border-radius: 10px; padding: 10px 12px; font-size: 0.82rem; font-weight: 700; cursor: pointer;
            width: 100%;
        }
        .mini-btn-primary { background: linear-gradient(90deg, #8b5cf6 0%, #a855f7 100%); color: #fff; }
        .mini-btn-dark { background: rgba(15, 23, 42, 0.7); color: #fff; border: 1px solid rgba(255,255,255,0.12); }
        .info-list { display: grid; gap: 12px; }
        .info-row { display: grid; gap: 2px; }
        .info-label { font-size: 0.82rem; color: var(--text-muted); }
        .info-value { font-size: 0.96rem; color: #fff; }
        .stack { display: grid; gap: 16px; }
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field-group { display: grid; gap: 8px; }
        .field-group label { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; color: #cbd5e1; }
        .field-group input, .field-group textarea, .field-group select {
            width: 100%; border-radius: 14px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05);
            color: #f8fafc; padding: 12px 14px; font: inherit; outline: none;
        }
        .field-group textarea { min-height: 110px; resize: vertical; }
        .chips { display: flex; flex-wrap: wrap; gap: 10px; }
        .chip {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 999px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); color: #f8fafc; font-size: 0.88rem;
        }
        .chip form { margin: 0; }
        .chip button {
            border: 0; background: transparent; color: #cbd5e1; cursor: pointer; font-size: 0.78rem;
        }
        .actions { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
        .btn {
            border: 0; border-radius: 12px; padding: 12px 15px; color: #fff; font-size: 0.92rem; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary { background: linear-gradient(90deg, #8b5cf6 0%, #22d3ee 100%); }
        .btn-secondary { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); color: #e2e8f0; }
        .table-wrap { border-radius: 18px; overflow: hidden; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.025); }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { text-align: left; padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.06); font-size: 0.9rem; color: #f8fafc; }
        th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: #cbd5e1; font-weight: 700; background: rgba(255,255,255,0.05); }
        .table-btn { border: 1px solid rgba(239,68,68,0.22); background: rgba(239,68,68,0.14); color: #fecaca; border-radius: 10px; padding: 8px 10px; font-size: 0.8rem; font-weight: 700; cursor: pointer; }
        .empty-state { text-align: center; padding: 26px; color: var(--text-muted); font-size: 0.95rem; }
        .toast-stack { position: fixed; top: 18px; right: 20px; z-index: 2000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .toast {
            min-width: 280px; max-width: 360px; padding: 14px 16px; border-radius: 14px; color: #fff; font-size: 0.9rem;
            font-weight: 600; box-shadow: 0 18px 30px rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.08);
        }
        .toast.success { background: linear-gradient(135deg, rgba(21,128,61,0.95), rgba(22,163,74,0.92)); }
        .toast.error { background: linear-gradient(135deg, rgba(185,28,28,0.96), rgba(239,68,68,0.92)); }
        @media (max-width: 1260px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
            .main-content { padding: 18px 14px; }
            .field-grid, .personal-layout { grid-template-columns: 1fr; }
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
            <a href="my_payroll.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>My Payroll</span>
            </a>
            <a href="request_time_off.php" class="nav-item">
                <i class="fas fa-calendar-days"></i>
                <span>Request Time Off</span>
            </a>
            <a href="my_profile.php" class="nav-item active">
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
            <div class="welcome-header">My Profile, <?php echo htmlspecialchars($employeeCode); ?>.</div>
            <div class="status-badge"><i class="fas fa-circle"></i> STATUS: <?php echo htmlspecialchars($employmentStatus); ?> Employee</div>
        </div>

        <div class="shell">
            <div class="profile-grid">
                <section class="panel personal-card">
                    <div class="panel-inner">
                        <div class="panel-title">Personal Information</div>
                        <div class="personal-layout">
                            <div class="photo-block">
                                <div class="photo-frame">
                                    <?php if ($profilePhoto): ?>
                                        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <label class="mini-btn mini-btn-primary" for="profilePhotoInput">Edit Photo</label>
                                <button type="button" class="mini-btn mini-btn-dark" onclick="document.getElementById('passwordSection').scrollIntoView({ behavior: 'smooth' });">Change Password</button>
                            </div>
                            <div class="info-list">
                                <div class="info-row">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($employee['name']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Date of Birth</div>
                                    <div class="info-value"><?php echo !empty($profile['date_of_birth']) ? date('F j, Y', strtotime($profile['date_of_birth'])) : 'Not set'; ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($profile['email'] ?? 'Not set'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($profile['phone_number'] ?? 'Not set'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($profile['address'] ?? 'Not set'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="stack">
                    <section class="panel">
                        <div class="panel-inner">
                            <div class="panel-title">Employment Details</div>
                            <div class="field-grid">
                                <div class="field-group">
                                    <label>Position</label>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['role']); ?>" readonly>
                                </div>
                                <div class="field-group">
                                    <label>Department</label>
                                    <input type="text" value="<?php echo htmlspecialchars($profile['department'] ?? ''); ?>" readonly>
                                </div>
                                <div class="field-group">
                                    <label>Hire Date</label>
                                    <input type="text" value="<?php echo !empty($employee['date_hired']) ? date('F j, Y', strtotime($employee['date_hired'])) : ''; ?>" readonly>
                                </div>
                                <div class="field-group">
                                    <label>Reporting To</label>
                                    <input type="text" value="<?php echo htmlspecialchars($profile['reporting_to'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-inner">
                            <div class="panel-title">Certifications & Skills</div>
                            <div class="chips">
                                <?php if ($skills): ?>
                                    <?php foreach ($skills as $skill): ?>
                                        <div class="chip">
                                            <span><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete_skill">
                                                <input type="hidden" name="skill_id" value="<?php echo (int) $skill['id']; ?>">
                                                <button type="submit"><i class="fas fa-times"></i></button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="info-value">No skills added yet.</div>
                                <?php endif; ?>
                            </div>
                            <form method="POST" style="margin-top: 16px;">
                                <input type="hidden" name="action" value="add_skill">
                                <div class="actions">
                                    <input type="text" name="skill_name" placeholder="Add a skill or certification" style="flex:1; min-width: 220px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #fff; padding: 12px 14px; font: inherit;">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Skill</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>

            <section class="panel">
                <div class="panel-inner">
                    <div class="panel-title">Update Profile</div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        <input id="profilePhotoInput" type="file" name="profile_photo" accept="image/*" style="display:none;">
                        <div class="field-grid">
                            <div class="field-group">
                                <label for="fullName">Full Name</label>
                                <input id="fullName" type="text" name="full_name" value="<?php echo htmlspecialchars($employee['name']); ?>" required>
                            </div>
                            <div class="field-group">
                                <label for="role">Position</label>
                                <input id="role" type="text" name="role" value="<?php echo htmlspecialchars($employee['role']); ?>" required>
                            </div>
                            <div class="field-group">
                                <label for="department">Department</label>
                                <input id="department" type="text" name="department" value="<?php echo htmlspecialchars($profile['department'] ?? ''); ?>">
                            </div>
                            <div class="field-group">
                                <label for="reportingTo">Reporting To</label>
                                <input id="reportingTo" type="text" name="reporting_to" value="<?php echo htmlspecialchars($profile['reporting_to'] ?? ''); ?>">
                            </div>
                            <div class="field-group">
                                <label for="dateHired">Hire Date</label>
                                <input id="dateHired" type="date" name="date_hired" value="<?php echo !empty($employee['date_hired']) ? date('Y-m-d', strtotime($employee['date_hired'])) : date('Y-m-d'); ?>" required>
                            </div>
                            <div class="field-group">
                                <label for="dateOfBirth">Date of Birth</label>
                                <input id="dateOfBirth" type="date" name="date_of_birth" value="<?php echo !empty($profile['date_of_birth']) ? htmlspecialchars($profile['date_of_birth']) : ''; ?>">
                            </div>
                            <div class="field-group">
                                <label for="email">Email Address</label>
                                <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>">
                            </div>
                            <div class="field-group">
                                <label for="phoneNumber">Phone Number</label>
                                <input id="phoneNumber" type="text" name="phone_number" value="<?php echo htmlspecialchars($profile['phone_number'] ?? ''); ?>">
                            </div>
                            <div class="field-group">
                                <label for="employmentStatus">Employment Status</label>
                                <select id="employmentStatus" name="employment_status">
                                    <option value="Active" <?php echo $employmentStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $employmentStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="actions" style="margin-top: 16px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Update Info</button>
                        </div>
                    </form>
                </div>
            </section>

            <section id="passwordSection" class="panel">
                <div class="panel-inner">
                    <div class="panel-title">Change Password</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="field-grid">
                            <div class="field-group">
                                <label for="currentPassword">Current Password</label>
                                <input id="currentPassword" type="password" name="current_password" required>
                            </div>
                            <div class="field-group">
                                <label for="newPassword">New Password</label>
                                <input id="newPassword" type="password" name="new_password" required>
                            </div>
                            <div class="field-group">
                                <label for="confirmPassword">Confirm Password</label>
                                <input id="confirmPassword" type="password" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="actions" style="margin-top: 16px;">
                            <button type="submit" class="btn btn-secondary"><i class="fas fa-key"></i> Change Password</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-inner">
                    <div class="panel-title">Emergency Contacts</div>
                    <form method="POST" style="margin-bottom: 16px;">
                        <input type="hidden" name="action" value="add_contact">
                        <div class="field-grid">
                            <div class="field-group">
                                <label for="contactName">Name</label>
                                <input id="contactName" type="text" name="contact_name" required>
                            </div>
                            <div class="field-group">
                                <label for="relationship">Relationship</label>
                                <input id="relationship" type="text" name="relationship">
                            </div>
                            <div class="field-group">
                                <label for="primaryPhone">Primary Phone</label>
                                <input id="primaryPhone" type="text" name="primary_phone" required>
                            </div>
                            <div class="field-group">
                                <label for="secondaryPhone">Secondary Phone</label>
                                <input id="secondaryPhone" type="text" name="secondary_phone">
                            </div>
                        </div>
                        <div class="actions" style="margin-top: 16px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Contact</button>
                        </div>
                    </form>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Relationship</th>
                                    <th>Primary Phone</th>
                                    <th>Secondary Phone</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($contacts): ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($contact['contact_name']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['relationship'] ?: 'Not set'); ?></td>
                                            <td><?php echo htmlspecialchars($contact['primary_phone']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['secondary_phone'] ?: 'Not set'); ?></td>
                                            <td>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="delete_contact">
                                                    <input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
                                                    <button type="submit" class="table-btn">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="empty-state">No emergency contacts added yet.</td></tr>
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

        const pageToastMessage = <?php echo json_encode($pageToastMessage); ?>;
        const pageToastType = <?php echo json_encode($pageToastType); ?>;
        if (pageToastMessage) {
            showToast(pageToastMessage, pageToastType || 'success');
        }

        const profilePhotoInput = document.getElementById('profilePhotoInput');
        if (profilePhotoInput) {
            profilePhotoInput.addEventListener('change', () => {
                if (profilePhotoInput.files.length > 0) {
                    showToast('Profile photo selected. Click Update Info to save it.', 'success');
                }
            });
        }
    </script>
</body>
</html>
