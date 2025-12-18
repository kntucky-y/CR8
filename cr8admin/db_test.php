<?php
// Database Connection Test
echo "<h2>Database Connection Test</h2>";

$host = 'dbadmin.dcism.org';
$user = 's24102191_cr8db';
$pass = 'cr8db!!!';
$dbname = 's24102191_cr8db';

echo "<p><strong>Testing with credentials:</strong></p>";
echo "<ul>";
echo "<li>Host: $host</li>";
echo "<li>User: $user</li>";
echo "<li>Database: $dbname</li>";
echo "<li>Password: " . str_repeat('*', strlen($pass)) . "</li>";
echo "</ul>";

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'><strong>❌ CONNECTION FAILED!</strong></p>";
        echo "<p>Error: " . $conn->connect_error . "</p>";
        echo "<p>Error Code: " . $conn->connect_errno . "</p>";
        
        echo "<hr>";
        echo "<h3>How to Fix:</h3>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin at <a href='http://localhost/phpmyadmin'>http://localhost/phpmyadmin</a></li>";
        echo "<li>Log in with root (no password)</li>";
        echo "<li>Click 'User accounts' tab</li>";
        echo "<li>Look for user 's24102191_cr8db'</li>";
        echo "<li>If it doesn't exist or has wrong password, run this SQL:</li>";
        echo "</ol>";
        echo "<pre style='background: #f0f0f0; padding: 10px;'>";
        echo "-- Delete old user if exists\n";
        echo "DROP USER IF EXISTS 's24102191_cr8db'@'localhost';\n\n";
        echo "-- Create new user\n";
        echo "CREATE USER 's24102191_cr8db'@'localhost' IDENTIFIED BY 'cr8db!!!';\n\n";
        echo "-- Grant all privileges\n";
        echo "GRANT ALL PRIVILEGES ON s24102191_cr8db.* TO 's24102191_cr8db'@'localhost';\n\n";
        echo "-- Flush privileges\n";
        echo "FLUSH PRIVILEGES;";
        echo "</pre>";
    } else {
        echo "<p style='color: green;'><strong>✅ CONNECTION SUCCESSFUL!</strong></p>";
        
        // Test if database exists
        $result = $conn->query("SELECT DATABASE()");
        $row = $result->fetch_row();
        echo "<p>Connected to database: <strong>" . $row[0] . "</strong></p>";
        
        // Check if admins table exists
        $result = $conn->query("SHOW TABLES LIKE 'admins'");
        if ($result->num_rows > 0) {
            echo "<p>✅ 'admins' table exists</p>";
            
            // Count admins
            $result = $conn->query("SELECT COUNT(*) as count FROM admins");
            $row = $result->fetch_assoc();
            echo "<p>Number of admin accounts: <strong>" . $row['count'] . "</strong></p>";
            
            // Show usernames
            $result = $conn->query("SELECT username FROM admins");
            echo "<p>Admin usernames:</p><ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($row['username']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ 'admins' table does NOT exist</p>";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ ERROR:</strong> " . $e->getMessage() . "</p>";
}
?>
