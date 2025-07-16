<?php
session_start(); // Start the session

// Database Connection
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Load Composer's libraries
require_once 'vendor/autoload.php';

// Use statements for namespaced libraries
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// The "use Fpdf;" line has been removed

// --- Security & Input Validation ---
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Access denied.");
}

$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$format = $_POST['format'] ?? 'csv';
$artist_id_filter = $_POST['artist_id'] ?? 'all';

if (empty($start_date) || empty($end_date) || !in_array($format, ['csv', 'xlsx', 'pdf'])) {
    die("Invalid input.");
}

// --- Build Query ---
$sql = "
    SELECT 
        a.artist_name,
        p.product_name,
        COALESCE(v.variant_name, p.base_variant_name) as variant,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue,
        p.quantity as stock_on_hand
    FROM products p
    JOIN artists a ON p.artist_id = a.id
    LEFT JOIN order_items oi ON p.id = oi.product_id AND oi.order_id IN (SELECT id FROM orders WHERE created_at BETWEEN ? AND ?)
    LEFT JOIN variants v ON oi.variant_id = v.id
";

$params = [$start_date, $end_date];
$types = 'ss';

if ($artist_id_filter !== 'all') {
    $sql .= " WHERE p.artist_id = ?";
    $params[] = $artist_id_filter;
    $types .= 'i';
}

$sql .= " GROUP BY a.id, p.id, v.id ORDER BY a.artist_name, p.product_name, v.variant_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$filename = "admin_report_" . date('Y-m-d') . "." . $format;
$headers = ['Artist', 'Product', 'Variant', 'Units Sold', 'Total Revenue (PHP)', 'Current Stock'];

// --- File Generation ---
switch ($format) {
    case 'xlsx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, NULL, 'A1');
        $sheet->fromArray($data, NULL, 'A2');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        break;

    case 'pdf':
        $pdf = new FPDF('L', 'mm', 'A4'); // 'L' for Landscape, 'mm' for millimeters, 'A4' for page size
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 10);
        foreach ($headers as $header) {
            $pdf->Cell(45, 7, $header, 1, 0, 'C'); // Added centering (C)
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 9);
        foreach ($data as $row) {
            foreach ($row as $cell) {
                $pdf->Cell(45, 6, $cell, 1);
            }
            $pdf->Ln();
        }
        $pdf->Output('D', $filename); // 'D' forces download
        break;

    case 'csv':
    default:
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        break;
}
exit;