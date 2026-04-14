<?php
session_start();
require_once "config.php";

if (isset($_POST['reset'])) {
    $username = $_POST['username'];
    $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $newPassword, $username);
    if ($stmt->execute()) {
        $_SESSION['login_error'] = "Password reset successful. Please log in again.";
        $_SESSION['active_form'] = "login";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['login_error'] = "Password reset failed.";
        $_SESSION['active_form'] = "login";
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-box forgot active">
        <h2>Reset Password</h2>
        <form action="forgot_password.php" method="post">
            <input type="text" name="username" placeholder="Enter your username" required>
            <input type="password" name="new_password" placeholder="Enter new password" required>
            <button type="submit" name="reset">Reset Password</button>
        </form>
    </div>
</body>
</html>