<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$studentId = is_string($_GET['id'] ?? null) ? trim($_GET['id']) : '';
$student = null;
$sessions = [];
$totalMinutes = 0;
try {
    $database = db();
    $lookup = $database->prepare('SELECT student_id, first_name, last_name, course FROM students WHERE student_id = ?');
    $lookup->execute([$studentId]);
    $student = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($student) {
        $query = $database->prepare('SELECT u.session_id, c.pc_number, u.date, u.time_in, u.time_out, u.time_in_dt, u.time_out_dt, CASE WHEN u.time_out IS NULL THEN NULL ELSE COALESCE(u.duration_minutes, TIMESTAMPDIFF(MINUTE, u.time_in_dt, u.time_out_dt)) END AS calculated_duration, u.ended_by FROM usage_sessions u JOIN computers c ON c.pc_id = u.pc_id WHERE u.student_id = ? ORDER BY u.date DESC, u.time_in DESC');
        $query->execute([$studentId]);
        $sessions = $query->fetchAll(PDO::FETCH_ASSOC);
        $sum = $database->prepare('SELECT COALESCE(SUM(CASE WHEN time_out IS NULL THEN 0 ELSE COALESCE(duration_minutes, TIMESTAMPDIFF(MINUTE, time_in_dt, time_out_dt)) END), 0) FROM usage_sessions WHERE student_id = ?');
        $sum->execute([$studentId]);
        $totalMinutes = (int) $sum->fetchColumn();
    }
} catch (Throwable $exception) {
    $loadError = 'Unable to load student history. Make sure database/migration_001.sql has been applied.';
}
admin_header('Student History', 'students');
admin_notice();
?>
<?php if (!empty($loadError)): ?><p class="notice error"><?= h($loadError) ?></p><?php elseif (!$student): ?><p class="notice error">Student not found.</p><?php else: ?>
<section class="stat-grid"><article class="stat-card"><span>Student</span><strong><?= h($student['first_name'] . ' ' . $student['last_name']) ?></strong></article><article class="stat-card"><span>Student ID</span><strong><?= h($student['student_id']) ?></strong></article><article class="stat-card"><span>Course</span><strong><?= h($student['course']) ?></strong></article><article class="stat-card"><span>Total Hours</span><strong><?= number_format($totalMinutes / 60, 2) ?></strong></article></section>
<section class="content-card"><div class="section-heading"><h2>Session History</h2><a href="students.php">Back to Students</a></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>PC</th><th>Started</th><th>Ended</th><th>Duration</th><th>Ended By</th></tr></thead><tbody>
<?php foreach ($sessions as $session): $minutes = (int) ($session['calculated_duration'] ?? 0); ?><tr><td><?= h($session['date']) ?></td><td><?= h($session['pc_number']) ?></td><td><?= h($session['time_in_dt'] ?? $session['time_in']) ?></td><td><?= h($session['time_out_dt'] ?? $session['time_out'] ?? '—') ?></td><td><?= $session['time_out'] === null ? 'Active' : h(intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm') ?></td><td><?= h($session['ended_by'] ?? '—') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$sessions): ?><p class="empty-state">No sessions found for this student.</p><?php endif; ?></section>
<?php endif; ?>
<?php admin_footer(); ?>
