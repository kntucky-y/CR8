<?php
require_once 'config.php';

echo "<h2>Artists Table Structure</h2>";

$conn = getDbConnection();

// Show table structure
$result = $conn->query("DESCRIBE artists");
echo "<table border='1' style='border-collapse:collapse'>";
echo "<tr style='background:#333;color:white'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if user_id 110 exists
echo "<h3>Check for user_id 110:</h3>";
$check = $conn->query("SELECT * FROM artists WHERE user_id = 110");
if ($check->num_rows > 0) {
    echo "<p style='color:green'>✓ Artist with user_id 110 EXISTS</p>";
    print_r($check->fetch_assoc());
} else {
    echo "<p style='color:red'>✗ Artist with user_id 110 does NOT exist</p>";
}

// Check user 110 details
echo "<h3>User 110 details:</h3>";
$user = $conn->query("SELECT id, username, email, role FROM users WHERE id = 110");
if ($user->num_rows > 0) {
    print_r($user->fetch_assoc());
} else {
    echo "<p style='color:red'>User 110 not found</p>";
}

$conn->close();

