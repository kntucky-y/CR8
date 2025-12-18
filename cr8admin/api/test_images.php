<?php
require_once 'config.php';

$conn = getDbConnection();
$result = $conn->query('SELECT id, product_name, image FROM products LIMIT 5');

echo "Image paths in database:\n\n";
while($row = $result->fetch_assoc()) {
    echo $row['product_name'] . " => " . $row['image'] . "\n";
}
$conn->close();
