<?php
// Test attendance table/DB - run http://localhost/login_register/test_clock.php
include 'config.php';
echo "<h2>DB Test</h2>";
echo "Connected: " . ($conn ? 'Yes' : 'No') . "<br>";

// Check table
$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
echo "Attendance table exists: " . ($table_check->num_rows ? 'Yes' : 'No') . "<br>";

// Describe if exists
if ($table_check->num_rows) {
    $result = $conn->query("DESCRIBE attendance");
    echo "<table border=1><tr><th>Field</th><th>Type</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Create table? <a href='#' onclick='document.getElementById(\"create\").style.display=\"block\"'>Create now</a>";
    echo '<div id="create" style="display:none;"><form method=post><input type=hidden name=create value=1><button>CREATE attendance table</button></form></div>';
}

// Temp create
if (isset($_POST['create'])) {
    $sql = "CREATE TABLE attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_username VARCHAR(50) NOT NULL,
        clock_in DATETIME,
        clock_out DATETIME,
        date DATE,
        INDEX(employee_username)
    )";
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>Table created!</p>";
    } else {
        echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}
?>

