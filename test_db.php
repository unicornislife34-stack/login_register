<?php
include 'config.php';
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Users count: " . $row['count'] . PHP_EOL;
} else {
    echo "Error querying users: " . $conn->error . PHP_EOL;
}

$result = $conn->query("SELECT COUNT(*) as count FROM inventory");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Inventory count: " . $row['count'] . PHP_EOL;
} else {
    echo "Error querying inventory: " . $conn->error . PHP_EOL;
}
?>