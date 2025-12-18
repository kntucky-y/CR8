<?php
require_once 'config.php';

$conn = getDbConnection();

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'artist_applications'");
if ($result->num_rows > 0) {
    echo "✓ Table 'artist_applications' exists\n\n";
    
    // Get table structure
    $structure = $conn->query("DESCRIBE artist_applications");
    echo "Table Structure:\n";
    while ($row = $structure->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    echo "\n";
    
    // Count records
    $count = $conn->query("SELECT COUNT(*) as total FROM artist_applications")->fetch_assoc();
    echo "Total records: {$count['total']}\n\n";
    
    // Show all records
    if ($count['total'] > 0) {
        $records = $conn->query("SELECT * FROM artist_applications");
        echo "Records:\n";
        while ($row = $records->fetch_assoc()) {
            print_r($row);
            echo "\n";
        }
    }
} else {
    echo "✗ Table 'artist_applications' does NOT exist\n";
    echo "\nAvailable tables:\n";
    $tables = $conn->query("SHOW TABLES");
    while ($row = $tables->fetch_array()) {
        echo "  - {$row[0]}\n";
    }
}

$conn->close();
