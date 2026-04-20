<?php
require_once 'config.php';

// Create users table (for login)
$usersTable = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($usersTable) === TRUE) {
    echo "Users table OK\n";
} else {
    echo "Users error: " . $conn->error . "\n";
}

// Create employee table
$employeeTable = "CREATE TABLE IF NOT EXISTS employee (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    role VARCHAR(50) DEFAULT 'employee',
    salary DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($employeeTable) === TRUE) {
    echo "Employee table OK\n";
} else {
    echo "Employee error: " . $conn->error . "\n";
}

// Create attendance table
$attendanceTable = "CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_username VARCHAR(50) NOT NULL,
    clock_in TIMESTAMP NULL,
    clock_out TIMESTAMP NULL DEFAULT NULL,
    break_started TIMESTAMP NULL,
    break_total INT DEFAULT 0,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee_date (employee_username, date),
    INDEX idx_date (date)
)";

if ($conn->query($attendanceTable) === TRUE) {
    echo "Attendance table OK\n";
} else {
    echo "Attendance error: " . $conn->error . "\n";
}

// Sample data
$conn->query("INSERT IGNORE INTO users (name, username, password, role) VALUES 
    ('Test Employee', 'employee', '" . password_hash('123456', PASSWORD_DEFAULT) . "', 'employee'),
    ('Admin User', 'admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin')");

$conn->query("INSERT IGNORE INTO employee (name, username, role) VALUES 
    ('Test Employee', 'employee', 'employee')");

echo "Setup complete! Test login: employee/123456 or admin/admin123\n";
echo "Run: http://localhost/login_register/setup.php";
?>
