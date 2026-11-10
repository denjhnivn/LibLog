<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/admin_layout.php';

$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$dateFrom = trim(is_string($_GET['date_from'] ?? null) ? $_GET['date_from'] : '');
$dateTo = trim(is_string($_GET['date_to'] ?? null) ? $_GET['date_to'] : '');
$course = trim(is_string($_GET['course'] ?? null) ? $_GET['course'] : '');
$pcNumber = trim(is_string($_GET['pc_number'] ?? null) ? $_GET['pc_number'] : '');
$status = trim(is_string($_GET['status'] ?? null) ? $_GET['status'] : '');
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$perPage = min(100, max(10, (int) get_setting('logs_per_page')));
$filterError = '';
foreach (['from' => $dateFrom, 'to' => $dateTo] as $name => $value) {
    $parsedDate = $value === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($value !== '' && (!$parsedDate || $parsedDate->format('Y-m-d') !== $value)) {
        if ($name === 'from') $dateFrom = ''; else $dateTo = '';
        $filterError = 'Enter dates in YYYY-MM-DD format.';
    }
}
if (!in_array($status, ['', 'Active', 'Completed'], true)) $status = '';

function log_duration($minutes): string
{
    $minutes = max(0, (int) $minutes);
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    return $hours > 0 ? $hours . 'h ' . $remaining . 'm' : $remaining . 'm';
}
function safe_csv_cell($value): string
{
    $cell = (string) ($value ?? '');
    return preg_match('/\A[=+@-]/u', $cell) ? "'" . $cell : $cell;
}

try {
    $database = db();
    $courseQuery = $database->prepare('SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course <> ? ORDER BY course');
    $courseQuery->execute(['']);
    $courses = $courseQuery->fetchAll(PDO::FETCH_COLUMN);
    $pcQuery = $database->prepare("SELECT pc_number FROM computers ORDER BY CAST(SUBSTRING_INDEX(pc_number, '-', -1) AS UNSIGNED), pc_number");
    $pcQuery->execute();
    $computers = $pcQuery->fetchAll(PDO::FETCH_COLUMN);

    $conditions = ['(u.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR c.pc_number LIKE ?)'];
    $params = [];
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
    if ($dateFrom !== '') { $conditions[] = 'DATE(COALESCE(u.time_in_dt, TIMESTAMP(u.date, u.time_in))) >= ?'; $params[] = $dateFrom; }
    if ($dateTo !== '') { $conditions[] = 'COALESCE(u.time_in_dt, TIMESTAMP(u.date, u.time_in)) < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $dateTo; }
    if ($course !== '') { $conditions[] = 's.course = ?'; $params[] = $course; }
    if ($pcNumber !== '') { $conditions[] = 'c.pc_number = ?'; $params[] = $pcNumber; }
    if ($status === 'Active') $conditions[] = 'u.time_out IS NULL';
    if ($status === 'Completed') $conditions[] = 'u.time_out IS NOT NULL';
    $fromWhere = ' FROM usage_sessions u JOIN students s ON s.student_id = u.student_id JOIN computers c ON c.pc_id = u.pc_id WHERE ' . implode(' AND ', $conditions);
    $select = 'SELECT u.student_id, s.first_name, s.last_name, s.course, c.pc_number, u.date, u.time_in, u.time_out, u.purpose, u.ended_by, CASE WHEN u.time_out IS NULL THEN NULL ELSE COALESCE(u.duration_minutes, TIMESTAMPDIFF(MINUTE, u.time_in_dt, u.time_out_dt)) END AS calculated_duration';

    if (($_GET['export'] ?? '') === 'csv') {
        $export = $database->prepare($select . $fromWhere . " ORDER BY CAST(SUBSTRING_INDEX(c.pc_number, '-', -1) AS UNSIGNED), c.pc_number, u.date DESC, u.time_in DESC");
        $export->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="liblog-usage-logs.csv"');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Student ID', 'Student Name', 'Course', 'PC Number', 'Date', 'Time In', 'Time Out', 'Duration', 'Purpose', 'Ended By'], ',', '"', '\\');
        while ($log = $export->fetch(PDO::FETCH_ASSOC)) {
            $duration = $log['time_out'] === null ? 'Active' : log_duration($log['calculated_duration']);
            $cells = [$log['student_id'], $log['first_name'] . ' ' . $log['last_name'], $log['course'], $log['pc_number'], $log['date'], $log['time_in'], $log['time_out'], $duration, $log['purpose'], $log['ended_by']];
            fputcsv($output, array_map('safe_csv_cell', $cells), ',', '"', '\\');
        }
        fclose($output);
        exit;
    }

    $count = $database->prepare('SELECT COUNT(*)' . $fromWhere);
    $count->execute($params);
    $totalRows = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $list = $database->prepare($select . $fromWhere . " ORDER BY CAST(SUBSTRING_INDEX(c.pc_number, '-', -1) AS UNSIGNED), c.pc_number, u.date DESC, u.time_in DESC LIMIT ? OFFSET ?");
    foreach ($params as $index => $value) $list->bindValue($index + 1, $value);
    $list->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $list->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
    $list->execute();
    $usageLogs = $list->fetchAll(PDO::FETCH_ASSOC);
    $loadError = '';
} catch (Throwable $exception) {
    $courses = $computers = $usageLogs = [];
    $totalRows = $totalPages = 0;
    $loadError = 'Unable to load usage logs. Make sure database/migration_001.sql has been applied.';
}
$filterParams = $_GET;
unset($filterParams['page'], $filterParams['export']);
$exportUrl = 'usage_logs.php?' . http_build_query($filterParams + ['export' => 'csv']);
$exportAction = '<a class="button-secondary logs-export' . (empty($totalRows) ? ' is-disabled' : '') . '" href="' . h($exportUrl) . '" title="' . (empty($totalRows) ? 'No data to export' : 'Exports all rows matching your filters.') . '"' . (empty($totalRows) ? ' aria-disabled="true" tabindex="-1"' : '') . '><i data-lucide="download" aria-hidden="true"></i><span>Export CSV</span></a>';
admin_header('Usage Logs', 'logs', 'Review and filter student computer sessions.', $exportAction);
admin_notice();
?>
<?php if ($loadError): ?><p class="notice error"><?= h($loadError) ?></p><?php endif; ?>
<?php if ($filterError): ?><p class="notice error"><?= h($filterError) ?></p><?php endif; ?>
<section class="content-card logs-table-card">
    <div class="section-heading"><h2>Session History</h2></div>
    <div class="logs-filter-panel">
    <form class="log-filter-form logs-filter-form" method="get" action="usage_logs.php">
        <label>Search<input type="search" name="search" value="<?= h($search) ?>" placeholder="Student or PC"></label>
        <label>Date from<input type="date" name="date_from" value="<?= h($dateFrom) ?>"></label>
        <label>Date to<input type="date" name="date_to" value="<?= h($dateTo) ?>"></label>
        <label>Course<select name="course"><option value="">All courses</option><?php foreach ($courses as $option): ?><option value="<?= h($option) ?>" <?= $course === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
        <label>PC<select name="pc_number"><option value="">All PCs</option><?php foreach ($computers as $option): ?><option value="<?= h($option) ?>" <?= $pcNumber === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
        <label>Status<select name="status"><option value="">All statuses</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option></select></label>
        <div class="logs-filter-actions"><button type="submit">Apply filters</button><a class="logs-clear" href="usage_logs.php">Clear</a></div>
    </form>
    <?php $activeFilters = ['search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'course' => $course, 'pc_number' => $pcNumber, 'status' => $status]; $activeFilters = array_filter($activeFilters, static fn($value) => $value !== ''); ?>
    <?php if ($activeFilters): ?><div class="logs-active-filters" aria-label="Active filters"><strong>Active filters</strong><?php foreach ($activeFilters as $key => $value): $remaining = $activeFilters; unset($remaining[$key]); ?><a href="usage_logs.php<?= $remaining ? '?' . h(http_build_query($remaining)) : '' ?>" class="logs-chip"><?= h(str_replace('_', ' ', ucfirst($key))) ?>: <?= h($value) ?><i data-lucide="x" aria-hidden="true"></i><span class="sr-only"> Remove filter</span></a><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Student ID</th><th>Student Name</th><th>Course</th><th>PC Number</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th></tr></thead><tbody>
    <?php foreach ($usageLogs as $log): ?><tr><td><?= h($log['student_id']) ?></td><td><?= h($log['first_name'] . ' ' . $log['last_name']) ?></td><td><?= h($log['course']) ?></td><td><?= h($log['pc_number']) ?></td><td><?= h($log['date']) ?></td><td><?= h($log['time_in']) ?></td><td><?= h($log['time_out'] ?? '-') ?></td><td><?= $log['time_out'] === null ? 'Active' : h(log_duration($log['calculated_duration'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if (!$usageLogs): ?><div class="logs-empty-state"><span class="logs-empty-icon" aria-hidden="true"><i data-lucide="search-x"></i></span><strong>No sessions match your filters</strong><p>Try changing your search or filter selections.</p><a href="usage_logs.php">Clear filters</a></div><?php endif; ?>
    <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Usage log pages"><?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): $pageQuery = http_build_query($filterParams + ['page' => $pageNumber]); ?><a href="?<?= h($pageQuery) ?>" <?= $pageNumber === $page ? 'aria-current="page" class="active"' : '' ?>><?= $pageNumber ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<?php admin_footer(); ?>
