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
                header("Location: employee_page.php");
            }
            exit();

        }
    }

    $_SESSION['login_error'] = 'Invalid username or password!';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

// Check if user is already logged in
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_page.php");
    } else {
        header("Location: employee_page.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L LE JOSE - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
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
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo/Brand -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent mb-2">
                L LE JOSE
            </h1>
            <p class="text-gray-600">Point of Sale System</p>
        </div>

        <!-- Form Container -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <!-- Tab Navigation -->
            <div class="flex mb-6 bg-gray-100 rounded-lg p-1">
                <button type="button" id="loginTab" class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors
                    <?php echo (!isset($_SESSION['active_form']) || $_SESSION['active_form'] !== 'register') ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-800'; ?>">
                    Login
                </button>
                <button type="button" id="registerTab" class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors
                    <?php echo (isset($_SESSION['active_form']) && $_SESSION['active_form'] === 'register') ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-800'; ?>">
                    Register
                </button>
            </div>

            <!-- Login Form -->
            <form id="loginForm" method="POST" class="<?php echo (!isset($_SESSION['active_form']) || $_SESSION['active_form'] !== 'register') ? 'block' : 'hidden'; ?>">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Welcome Back</h2>

                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                        <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                    </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <div>
                        <label for="login_username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="login_username" name="username" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                    </div>

                    <div>
                        <label for="login_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" id="login_password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                    </div>

                    <button type="submit" name="login"
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        Sign In
                    </button>
                </div>
            </form>

            <!-- Register Form -->
            <form id="registerForm" method="POST" class="<?php echo (isset($_SESSION['active_form']) && $_SESSION['active_form'] === 'register') ? 'block' : 'hidden'; ?>">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Create Account</h2>

                <?php if (isset($_SESSION['register_error'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                        <?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?>
                    </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <div>
                        <label for="register_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" id="register_name" name="name" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                    </div>

                    <div>
                        <label for="register_username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="register_username" name="username" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                    </div>

                    <div>
                        <label for="register_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" id="register_password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                    </div>

                    <div>
                        <label for="register_role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select id="register_role" name="role" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                            <option value="employee">Employee</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <button type="submit" name="register"
                            class="w-full bg-purple-500 hover:bg-purple-600 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        Create Account
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <footer class="text-center mt-8 text-gray-500 text-sm">
            © 2026 L LE JOSE - Point of Sale System. All rights reserved.
        </footer>
    </div>

    <script>
        // Tab switching functionality
        const loginTab = document.getElementById('loginTab');
        const registerTab = document.getElementById('registerTab');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');

        loginTab.addEventListener('click', function() {
            loginTab.className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors bg-white text-blue-600 shadow-sm';
            registerTab.className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-gray-800';
            loginForm.classList.remove('hidden');
            loginForm.classList.add('block');
            registerForm.classList.remove('block');
            registerForm.classList.add('hidden');
        });

        registerTab.addEventListener('click', function() {
            registerTab.className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors bg-white text-blue-600 shadow-sm';
            loginTab.className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-gray-800';
            registerForm.classList.remove('hidden');
            registerForm.classList.add('block');
            loginForm.classList.remove('block');
            loginForm.classList.add('hidden');
        });

        // Clear session messages on page load
        <?php unset($_SESSION['active_form']); ?>
    </script>
</body>
</html>