<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/admin_layout.php';

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-29 days'));
$dateFrom = is_string($_GET['date_from'] ?? null) ? $_GET['date_from'] : $defaultFrom;
$dateTo = is_string($_GET['date_to'] ?? null) ? $_GET['date_to'] : $today;
$preset = is_string($_GET['preset'] ?? null) ? $_GET['preset'] : '';
if ($preset === '7' || $preset === '30') { $dateTo = $today; $dateFrom = date('Y-m-d', strtotime('-' . ((int) $preset - 1) . ' days')); }
elseif ($preset === 'month') { $dateFrom = date('Y-m-01'); $dateTo = $today; }
$validDate = static function (string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (!$validDate($dateFrom) || !$validDate($dateTo) || $dateFrom > $dateTo) {
    $dateFrom = $defaultFrom;
    $dateTo = $today;
    $rangeError = 'Date range was invalid. Showing the last 30 days.';
}
$summary = ['sessions' => 0, 'students' => 0, 'average_minutes' => 0, 'busiest_pc' => '—'];
$chartData = ['days' => [], 'sessions' => [], 'hours' => array_fill(0, 24, 0), 'courses' => [], 'courseCounts' => [], 'pcs' => [], 'pcCounts' => []];
$loadError = '';
try {
    $database = db();
    $range = ' WHERE DATE(COALESCE(u.time_in_dt, TIMESTAMP(u.date, u.time_in))) BETWEEN ? AND ?';
    $statement = $database->prepare('SELECT COUNT(*), COUNT(DISTINCT u.student_id), AVG(CASE WHEN u.time_out IS NOT NULL THEN COALESCE(u.duration_minutes, TIMESTAMPDIFF(MINUTE, u.time_in_dt, u.time_out_dt)) END) FROM usage_sessions u' . $range);
    $statement->execute([$dateFrom, $dateTo]);
    $row = $statement->fetch(PDO::FETCH_NUM);
    $summary['sessions'] = (int) $row[0];
    $summary['students'] = (int) $row[1];
    $summary['average_minutes'] = (int) round((float) ($row[2] ?? 0));
    $busy = $database->prepare('SELECT c.pc_number, COUNT(*) AS sessions FROM usage_sessions u JOIN computers c ON c.pc_id = u.pc_id' . $range . ' GROUP BY c.pc_id, c.pc_number ORDER BY sessions DESC, c.pc_number LIMIT 1');
    $busy->execute([$dateFrom, $dateTo]);
    $summary['busiest_pc'] = $busy->fetchColumn() ?: '—';
    $daily = $database->prepare('SELECT DATE(COALESCE(u.time_in_dt, TIMESTAMP(u.date, u.time_in))) AS day, COUNT(*) AS sessions FROM usage_sessions u' . $range . ' GROUP BY day ORDER BY day');
    $daily->execute([$dateFrom, $dateTo]);
    $dailyRows = $daily->fetchAll(PDO::FETCH_KEY_PAIR);
    $cursor = new DateTimeImmutable($dateFrom);
    $last = new DateTimeImmutable($dateTo);
    while ($cursor <= $last) {
        $key = $cursor->format('Y-m-d');
        $chartData['days'][] = $key;
        $chartData['sessions'][] = (int) ($dailyRows[$key] ?? 0);
        $cursor = $cursor->modify('+1 day');
    }
    $hours = $database->prepare('SELECT HOUR(u.time_in_dt) AS hour_num, COUNT(*) AS sessions FROM usage_sessions u' . $range . ' GROUP BY hour_num');
    $hours->execute([$dateFrom, $dateTo]);
    foreach ($hours->fetchAll(PDO::FETCH_KEY_PAIR) as $hour => $number) $chartData['hours'][(int) $hour] = (int) $number;
    $courses = $database->prepare('SELECT COALESCE(s.course, "Unknown") AS course_name, COUNT(*) AS sessions FROM usage_sessions u JOIN students s ON s.student_id = u.student_id' . $range . ' GROUP BY course_name ORDER BY sessions DESC');
    $courses->execute([$dateFrom, $dateTo]);
    foreach ($courses->fetchAll(PDO::FETCH_ASSOC) as $courseRow) { $chartData['courses'][] = $courseRow['course_name']; $chartData['courseCounts'][] = (int) $courseRow['sessions']; }
    $pcs = $database->prepare('SELECT c.pc_number, COUNT(*) AS sessions FROM usage_sessions u JOIN computers c ON c.pc_id = u.pc_id' . $range . ' GROUP BY c.pc_id, c.pc_number ORDER BY c.pc_number');
    $pcs->execute([$dateFrom, $dateTo]);
    foreach ($pcs->fetchAll(PDO::FETCH_ASSOC) as $pcRow) { $chartData['pcs'][] = $pcRow['pc_number']; $chartData['pcCounts'][] = (int) $pcRow['sessions']; }
} catch (Throwable $exception) {
    $loadError = 'Unable to load reports. Make sure database/migration_001.sql has been applied.';
}
$averageDisplay = intdiv($summary['average_minutes'], 60) . 'h ' . ($summary['average_minutes'] % 60) . 'm';
$chartJson = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
$printAction = '<button type="button" class="button-secondary rep-print" onclick="window.print()"><i data-lucide="printer" aria-hidden="true"></i>Print</button>';
admin_header('Reports', 'reports', 'Explore computer usage and activity trends.', $printAction);
admin_notice();
?>
<?php if (!empty($rangeError)): ?><p class="notice error"><?= h($rangeError) ?></p><?php endif; ?>
<?php if ($loadError): ?><p class="notice error"><?= h($loadError) ?></p><?php endif; ?>
<header class="report-print-heading"><p><?= h(get_setting('library_name')) ?></p><h1>Usage Report</h1><span><?= h(date('M j, Y', strtotime($dateFrom))) ?> – <?= h(date('M j, Y', strtotime($dateTo))) ?></span></header>
<section class="content-card rep-filters"><form method="get" class="report-date-form rep-date-form"><label>Date from<input type="date" name="date_from" value="<?= h($dateFrom) ?>" required></label><label>Date to<input type="date" name="date_to" value="<?= h($dateTo) ?>" required></label><button type="submit">Apply</button><div class="rep-presets"><button type="submit" name="preset" value="7">7 days</button><button type="submit" name="preset" value="30">30 days</button><button type="submit" name="preset" value="month">This month</button></div></form></section>
<section class="stat-grid report-summary rep-summary"><article class="stat-card"><span>Total Sessions</span><strong><?= $summary['sessions'] ?></strong></article><article class="stat-card"><span>Unique Students</span><strong><?= $summary['students'] ?></strong></article><article class="stat-card"><span>Average Duration</span><strong><?= h($averageDisplay) ?></strong></article><article class="stat-card"><span>Busiest PC</span><strong><?= h($summary['busiest_pc']) ?></strong></article></section>
<section class="report-chart-grid rep-chart-grid"><article class="content-card report-chart-card rep-chart-card rep-daily"><h2>Sessions per Day</h2><div class="rep-chart-wrap"><canvas id="daily-chart"></canvas><p class="rep-empty" hidden>No data for this period</p></div></article><article class="content-card report-chart-card rep-chart-card"><h2>Peak Hours</h2><div class="rep-chart-wrap"><canvas id="hours-chart"></canvas><p class="rep-empty" hidden>No data for this period</p></div></article><article class="content-card report-chart-card rep-chart-card"><h2>Usage by Course</h2><div class="rep-chart-wrap"><canvas id="course-chart"></canvas><p class="rep-empty" hidden>No data for this period</p></div></article><article class="content-card report-chart-card rep-chart-card rep-pc-chart"><h2>Usage per PC</h2><div class="rep-chart-wrap"><canvas id="pc-chart"></canvas><p class="rep-empty" hidden>No data for this period</p></div></article></section>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
<script>window.liblogReportData = <?= $chartJson ?: '{}' ?>;</script><script src="reports.js" defer></script>
<?php admin_footer(); ?>
