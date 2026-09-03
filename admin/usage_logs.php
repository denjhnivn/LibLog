<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$search = trim($_GET['search'] ?? '');

try {
    $database = db();
    $statement = $database->prepare(
        'SELECT u.student_id, s.first_name, s.last_name, c.pc_number, u.date, u.time_in, u.time_out
         FROM usage_sessions u
         JOIN students s ON s.student_id = u.student_id
         JOIN computers c ON c.pc_id = u.pc_id
         WHERE u.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR c.pc_number LIKE ?
         ORDER BY u.date DESC, u.time_in DESC'
    );

    $searchTerm = '%' . $search . '%';
    $statement->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $usageLogs = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $usageLogs = [];
    $loadError = 'Unable to load usage logs.';
}

admin_header('Usage Logs', 'logs');
admin_notice();
?>

<?php if (!empty($loadError)): ?>
    <p class="notice error"><?= h($loadError) ?></p>
<?php endif; ?>

<section class="content-card">
    <div class="section-heading">
        <h2>Session History</h2>
        <form class="search-form" method="get">
            <input name="search" value="<?= h($search) ?>" placeholder="Search student or PC">
            <button>Search</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Student ID</th><th>Student Name</th><th>PC Number</th><th>Date</th><th>Time In</th><th>Time Out</th></tr>
            </thead>
            <tbody>
                <?php foreach ($usageLogs as $log): ?>
                    <tr>
                        <td><?= h($log['student_id']) ?></td>
                        <td><?= h($log['first_name'] . ' ' . $log['last_name']) ?></td>
                        <td><?= h($log['pc_number']) ?></td>
                        <td><?= h($log['date']) ?></td>
                        <td><?= h($log['time_in']) ?></td>
                        <td><?= h($log['time_out'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$usageLogs): ?><p class="empty-state">No matching usage sessions found.</p><?php endif; ?>
</section>
<?php admin_footer(); ?>
