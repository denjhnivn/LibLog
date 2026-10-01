<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$counts = ['total' => 0, 'available' => 0, 'occupied' => 0, 'today' => 0];
$recentSessions = [];
$databaseError = '';

try {
    $database = db();

    $counts['total'] = (int) $database->query('SELECT COUNT(*) FROM computers')->fetchColumn();
    $counts['available'] = (int) $database->query("SELECT COUNT(*) FROM computers WHERE status = 'Available'")->fetchColumn();
    // In Use is retained here to support existing records created by the student page.
    $counts['occupied'] = (int) $database->query("SELECT COUNT(*) FROM computers WHERE status IN ('Occupied', 'In Use')")->fetchColumn();

    $todayStatement = $database->prepare('SELECT COUNT(*) FROM usage_sessions WHERE date = CURDATE()');
    $todayStatement->execute();
    $counts['today'] = (int) $todayStatement->fetchColumn();

    $recentSessions = $database->query(
        'SELECT s.first_name, s.last_name, u.student_id, c.pc_number, u.date, u.time_in, u.time_out
         FROM usage_sessions u
         JOIN students s ON s.student_id = u.student_id
         JOIN computers c ON c.pc_id = u.pc_id
         ORDER BY u.date DESC, u.time_in DESC
         LIMIT 8'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $databaseError = 'Database connection failed. Check the LibLog configuration.';
}

admin_header('Dashboard', 'dashboard');
admin_notice();
?>

<?php if ($databaseError): ?>
    <p class="notice error"><?= h($databaseError) ?></p>
<?php else: ?>
    <section class="stat-grid">
        <article class="stat-card"><span>Total Computers</span><strong><?= $counts['total'] ?></strong></article>
        <article class="stat-card available"><span>Available Computers</span><strong><?= $counts['available'] ?></strong></article>
        <article class="stat-card occupied"><span>Occupied Computers</span><strong><?= $counts['occupied'] ?></strong></article>
        <article class="stat-card sessions"><span>Today's Sessions</span><strong><?= $counts['today'] ?></strong></article>
    </section>

    <section class="content-card">
        <div class="section-heading"><h2>Recent Usage Sessions</h2><a href="usage_logs.php">View all logs</a></div>
        <?php if (!$recentSessions): ?>
            <p class="empty-state">No usage sessions have been recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Student Name</th><th>Student ID</th><th>PC Number</th><th>Date</th><th>Time In</th><th>Time Out</th></tr></thead><tbody>
            <?php foreach ($recentSessions as $session): ?>
                <tr><td><?= h($session['first_name'] . ' ' . $session['last_name']) ?></td><td><?= h($session['student_id']) ?></td><td><?= h($session['pc_number']) ?></td><td><?= h($session['date']) ?></td><td><?= h($session['time_in']) ?></td><td><?= h($session['time_out'] ?: '-') ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php admin_footer(); ?>
