<?php
require_once __DIR__ . '/admin_auth.php';
if (!is_array($_SESSION['rejected_students'] ?? null)) {
    http_response_code(404);
    exit('No rejected rows are available.');
}
function rejected_csv_cell($value): string
{
    $cell = (string) $value;
    return preg_match('/\A[=+@-]/u', $cell) ? "'" . $cell : $cell;
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="rejected-students.csv"');
$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['CSV Row', 'Student ID', 'First Name', 'Last Name', 'Email', 'Course', 'Reason'], ',', '"', '\\');
foreach ($_SESSION['rejected_students'] as $row) fputcsv($output, array_map('rejected_csv_cell', $row), ',', '"', '\\');
fclose($output);
