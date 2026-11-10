<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $computers = db()->query(
        "SELECT c.pc_id, c.pc_number,
            CASE WHEN c.status = 'In Use' THEN 'Occupied' ELSE c.status END AS status,
            s.first_name, s.last_name, u.student_id,
            COALESCE(u.time_in_dt, TIMESTAMP(u.date, u.time_in)) AS started_at,
            CASE WHEN u.session_id IS NULL THEN NULL
                 ELSE TIMESTAMPDIFF(SECOND, COALESCE(u.time_in_dt, TIMESTAMP(u.date, u.time_in)), NOW()) END AS elapsed_seconds
         FROM computers c
         LEFT JOIN usage_sessions u ON u.pc_id = c.pc_id AND u.time_out IS NULL
         LEFT JOIN students s ON s.student_id = u.student_id
         ORDER BY CAST(SUBSTRING_INDEX(c.pc_number, '-', -1) AS UNSIGNED), c.pc_number, u.session_id"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($computers as &$computer) {
        $computer['pc_id'] = (int) $computer['pc_id'];
        $computer['elapsed_seconds'] = $computer['elapsed_seconds'] === null ? null : (int) $computer['elapsed_seconds'];
        $computer['started_display'] = $computer['started_at'] ? date('M j, Y g:i A', strtotime($computer['started_at'])) : null;
    }
    unset($computer);
    echo json_encode(['computers' => $computers, 'server_time' => date(DATE_ATOM)], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load computer status.']);
}
