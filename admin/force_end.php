<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'POST required.']);
    exit;
}
csrf_verify();
$pcId = filter_input(INPUT_POST, 'pc_id', FILTER_VALIDATE_INT);
if (!$pcId || $pcId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid computer.']);
    exit;
}

$database = db();
try {
    $database->beginTransaction();
    $computer = $database->prepare('SELECT pc_id FROM computers WHERE pc_id = ? FOR UPDATE');
    $computer->execute([$pcId]);
    if (!$computer->fetchColumn()) {
        throw new RuntimeException('Computer not found.');
    }
    $active = $database->prepare('SELECT session_id FROM usage_sessions WHERE pc_id = ? AND time_out IS NULL ORDER BY session_id LIMIT 1 FOR UPDATE');
    $active->execute([$pcId]);
    $sessionId = $active->fetchColumn();
    if (!$sessionId) {
        throw new RuntimeException('There is no active session on this computer.');
    }
    $close = $database->prepare("UPDATE usage_sessions SET time_out = CURTIME(), time_out_dt = NOW(), duration_minutes = TIMESTAMPDIFF(MINUTE, time_in_dt, NOW()), ended_by = 'admin' WHERE session_id = ? AND time_out IS NULL");
    $close->execute([(int) $sessionId]);
    $stillActive = $database->prepare('SELECT session_id FROM usage_sessions WHERE pc_id = ? AND time_out IS NULL LIMIT 1 FOR UPDATE');
    $stillActive->execute([$pcId]);
    if (!$stillActive->fetchColumn()) {
        $database->prepare("UPDATE computers SET status = 'Available' WHERE pc_id = ?")->execute([$pcId]);
    }
    $database->commit();
    echo json_encode(['ok' => true]);
} catch (RuntimeException $exception) {
    if ($database->inTransaction()) $database->rollBack();
    http_response_code(409);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($database->inTransaction()) $database->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Unable to end the session.']);
}
