<?php
require_once 'config.php';

// Skip auth for debugging
$conn = getDbConnection();

echo "<h2>Debug: Artist Applications</h2>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'artist_applications'");
if ($result->num_rows === 0) {
    echo "<p style='color:red'>❌ Table 'artist_applications' does NOT exist!</p>";
    $conn->close();
    exit;
}
echo "<p style='color:green'>✓ Table exists</p>";

// Get all records without any filter
$result = $conn->query("SELECT * FROM artist_applications");
echo "<h3>Total records: " . $result->num_rows . "</h3>";

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse:collapse; width:100%'>";
    echo "<tr style='background:#333;color:white'>";
    echo "<th>ID</th><th>Full Name</th><th>Status</th><th>is_archived</th><th>Submitted At</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>" . ($row['is_archived'] ?? 'NULL') . "</td>";
        echo "<td>{$row['submitted_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check filtered results
    echo "<h3>Filtered Results (Active Only):</h3>";
    $filtered = $conn->query("SELECT * FROM artist_applications WHERE COALESCE(is_archived, 0) = 0");
    echo "<p>Count: " . $filtered->num_rows . "</p>";
    
} else {
    echo "<p>No records found in the table!</p>";
}

$conn->close();
