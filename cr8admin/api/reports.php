<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendError('Unauthorized', 401);
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    sendError('Session expired', 401);
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = getDbConnection();

// GET - Fetch all active artists for filter
$artists = [];
$artist_sql = "SELECT id, artist_name FROM artists WHERE status = 'active' ORDER BY artist_name ASC";
$artist_result = $conn->query($artist_sql);
while($row = $artist_result->fetch_assoc()) {
    $artists[] = $row;
}

$conn->close();
sendResponse(['artists' => $artists]);
