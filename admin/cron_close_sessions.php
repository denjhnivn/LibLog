<?php
// Schedule every 10 minutes: set a repeating 10-minute Task Scheduler trigger for php.exe with this file, or use cron: */10 * * * * php /path/to/admin/cron_close_sessions.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only run from the command line.\n");
}

require_once __DIR__ . '/../settings.php';

try {
    $database = db();
    $settings = get_settings();
    $closingTime = $settings['closing_time'];
    $maxMinutes = (int) $settings['max_session_minutes'];
    $now = new DateTimeImmutable('now');
    $todayClosing = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $closingTime);
    $lastClosing = $now >= $todayClosing ? $todayClosing : $todayClosing->modify('-1 day');
    $pastClosing = $now >= $todayClosing;
    $cutoff = $now->modify('-' . $maxMinutes . ' minutes');
    $closedCount = 0;
    $database->beginTransaction();
    $open = $database->query(
        'SELECT session_id, pc_id, date, time_in, time_in_dt FROM usage_sessions WHERE time_out IS NULL ORDER BY session_id FOR UPDATE'
    )->fetchAll(PDO::FETCH_ASSOC);
    $close = $database->prepare("UPDATE usage_sessions SET time_out = CURTIME(), time_out_dt = NOW(), duration_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, COALESCE(time_in_dt, TIMESTAMP(date, time_in)), NOW())), ended_by = 'auto' WHERE session_id = ? AND time_out IS NULL");
    $lockPc = $database->prepare('SELECT pc_id FROM computers WHERE pc_id = ? FOR UPDATE');
    $stillOpen = $database->prepare('SELECT session_id FROM usage_sessions WHERE pc_id = ? AND time_out IS NULL LIMIT 1 FOR UPDATE');
    $freePc = $database->prepare("UPDATE computers SET status = 'Available' WHERE pc_id = ?");
    foreach ($open as $session) {
        $startedAtValue = $session['time_in_dt'] ?: ($session['date'] . ' ' . $session['time_in']);
        $startedAt = new DateTimeImmutable($startedAtValue);
        $isStale = ($pastClosing || $startedAt <= $lastClosing) || $startedAt <= $cutoff;
        if (!$isStale) continue;
        $lockPc->execute([(int) $session['pc_id']]);
        $close->execute([(int) $session['session_id']]);
        $stillOpen->execute([(int) $session['pc_id']]);
        if (!$stillOpen->fetchColumn()) $freePc->execute([(int) $session['pc_id']]);
        $closedCount += $close->rowCount();
    }
    $database->commit();
    echo 'Closed ' . $closedCount . " sessions\n";
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) $database->rollBack();
    fwrite(STDERR, 'Auto-close failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
