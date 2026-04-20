<?php
include 'config.php';

// Add batch_number column if it doesn't exist
$sql = "ALTER TABLE inventory ADD COLUMN IF NOT EXISTS batch_number INT NOT NULL DEFAULT 1";
if ($conn->query($sql) === TRUE) {
    echo "Batch number column added successfully\n";
} else {
    echo "Error adding batch number column: " . $conn->error . "\n";
}

$conn->close();
?>