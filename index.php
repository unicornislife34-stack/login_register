<?php
session_start();

// Redirect based on login status
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_page.php');
    } else {
        header('Location: employee_dashboard.php');
    }
} else {
    header('Location: login_register.html');
}
exit;
?>

