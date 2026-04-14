<?php
include 'config.php';

echo "DB: users_db connected.\n";

$result = $conn->query("SHOW TABLES");
if ($result) {
  echo "Tables:\n";
  while ($row = $result->fetch_array()) {
    echo "- " . $row[0] . "\n";
  }
} else {
  echo "No tables or error: " . $conn->error . "\n";
}

$tables = ['employee', 'attendance'];
foreach ($tables as $table) {
  $count = $conn->query("SELECT COUNT(*) as c FROM `$table`");
  if ($count) {
    $row = $count->fetch_assoc();
    echo "`$table`: " . $row['c'] . " rows\n";
  } else {
    echo "Missing `$table` or error: " . $conn->error . "\n";
  }
}

$test_query = $conn->query("SELECT * FROM attendance WHERE employee_username = 'test' LIMIT 1");
if (!$test_query) {
  echo "Attendance query fails: " . $conn->error . "\n";
}
?>

