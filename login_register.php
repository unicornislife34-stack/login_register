<?php
session_start();
require_once "config.php";

// Registration handling
if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $checkUsername = $conn->query("SELECT username FROM users WHERE username = '$username'");
    if ($checkUsername->num_rows >0) {
        $_SESSION['register_error'] = 'Username is already registered!';
        $_SESSION['active_form'] = 'register';
    } else {
        $conn->query("INSERT INTO users (name, username, password, role) VALUES ('$name', '$username', '$password', '$role')");
    }

    header("Location: index.php");
    exit();
}

// Login handling
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE username = '$username'");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                header("Location: admin_page.php");
            } else {
                header("Location: employee_dashboard.php");
            }
            exit();

        }
    }

    $_SESSION['login_error'] = 'Invalid username or password!';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

?>