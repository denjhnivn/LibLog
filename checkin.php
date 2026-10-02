<?php
session_start();
require_once __DIR__ . '/db.php';
header('Cache-Control: no-store, max-age=0');

$_SESSION['kiosk_token'] ??= bin2hex(random_bytes(32));
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$state = ['mode' => 'initial', 'error' => '', 'id' => '', 'id_error' => false, 'success' => null];

function kiosk_escape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function kiosk_input(string $name): string {
    return is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
}

// Always build the displayed identity, current session, and PC list from the database.
function kiosk_student_state(PDO $database, string $studentId): array {
    $student = $database->prepare('SELECT student_id, first_name, last_name FROM students WHERE student_id = ?');
    $student->execute([$studentId]);
    $identity = $student->fetch(PDO::FETCH_ASSOC);
    if (!$identity) {
        unset($_SESSION['kiosk_student_id']);
        throw new RuntimeException('Student ID not found. Please approach the librarian.');
    }
    $session = $database->prepare(
        'SELECT u.session_id, u.pc_id, u.date, u.time_in, c.pc_number
         FROM usage_sessions u JOIN computers c ON c.pc_id = u.pc_id
         WHERE u.student_id = ? AND u.time_out IS NULL
         ORDER BY u.session_id LIMIT 1'
    );
    $session->execute([$studentId]);
    $active = $session->fetch(PDO::FETCH_ASSOC);
    $computers = [];
    if (!$active) {
        // A PC with an active log stays unavailable even if its status was changed manually.
        $computers = $database->query(
            "SELECT c.pc_number, c.status,
             EXISTS(SELECT 1 FROM usage_sessions u WHERE u.pc_id = c.pc_id AND u.time_out IS NULL) AS has_session
             FROM computers c ORDER BY c.pc_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($computers as &$computer) {
            if ($computer['status'] === 'In Use' || ($computer['status'] === 'Available' && $computer['has_session'])) {
                $computer['status'] = 'Occupied';
            }
        }
        unset($computer);
    }
    return ['mode' => $active ? 'end' : 'start', 'student' => $identity, 'session' => $active, 'computers' => $computers];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = kiosk_input('action');
    try {
        if (!hash_equals($_SESSION['kiosk_token'], kiosk_input('token'))) {
            unset($_SESSION['kiosk_student_id']);
            throw new RuntimeException('Please enter your Student ID again.');
        }
        if ($action === 'cancel') {
            unset($_SESSION['kiosk_student_id']);
        } elseif ($action === 'continue') {
            unset($_SESSION['kiosk_student_id']);
            $state['id'] = kiosk_input('id_number');
            if (!preg_match('/\A[0-9]{7}-[0-9]\z/', $state['id'])) {
                $state['id_error'] = true;
                throw new RuntimeException($state['id'] === '' ? 'ID number is required.' : 'Please enter a valid Student ID.');
            }
            $state = array_merge($state, kiosk_student_state(db(), $state['id']));
            $_SESSION['kiosk_student_id'] = $state['student']['student_id'];
            $state['id'] = '';
        } elseif (in_array($action, ['start', 'end'], true)) {
            $studentId = $_SESSION['kiosk_student_id'] ?? '';
            if ($studentId === '') {
                throw new RuntimeException('Please enter your Student ID again.');
            }
            $database = db();
            $database->beginTransaction();
            // Lock the student first in both flows. Concurrent starts for the same student serialize here.
            $student = $database->prepare('SELECT student_id FROM students WHERE student_id = ? FOR UPDATE');
            $student->execute([$studentId]);
            if (!$student->fetchColumn()) {
                unset($_SESSION['kiosk_student_id']);
                throw new RuntimeException('Student ID not found. Please approach the librarian.');
            }
            $session = $database->prepare(
                'SELECT session_id, pc_id FROM usage_sessions
                 WHERE student_id = ? AND time_out IS NULL ORDER BY session_id LIMIT 1 FOR UPDATE'
            );
            $session->execute([$studentId]);
            $active = $session->fetch(PDO::FETCH_ASSOC);

            if ($action === 'start') {
                if ($active) {
                    throw new RuntimeException('You already have an active session. You can end it below.');
                }
                $pcNumber = kiosk_input('pc_number');
                if ($pcNumber === '') {
                    throw new RuntimeException('Please select an available PC.');
                }
                $computer = $database->prepare('SELECT pc_id, status FROM computers WHERE pc_number = ? FOR UPDATE');
                $computer->execute([$pcNumber]);
                $pc = $computer->fetch(PDO::FETCH_ASSOC);
                if (!$pc || $pc['status'] !== 'Available') {
                    throw new RuntimeException('That computer is no longer available. Please select another PC.');
                }
                $pcSession = $database->prepare('SELECT session_id FROM usage_sessions WHERE pc_id = ? AND time_out IS NULL LIMIT 1 FOR UPDATE');
                $pcSession->execute([$pc['pc_id']]);
                if ($pcSession->fetchColumn()) {
                    throw new RuntimeException('That computer is no longer available. Please select another PC.');
                }
                $database->prepare('INSERT INTO usage_sessions (student_id, pc_id, date, time_in) VALUES (?, ?, CURDATE(), CURTIME())')
                    ->execute([$studentId, $pc['pc_id']]);
                // Occupied is the canonical status also used by admin computer editing.
                $database->prepare("UPDATE computers SET status = 'Occupied' WHERE pc_id = ?")->execute([$pc['pc_id']]);
                $success = ['title' => 'Session Started', 'text' => $pcNumber . ' is now assigned to you.'];
            } else {
                if (!$active || (string) $active['session_id'] !== kiosk_input('session_id')) {
                    unset($_SESSION['kiosk_student_id']);
                    throw new RuntimeException('This session has already ended or changed. Please enter your ID again.');
                }
                $computer = $database->prepare('SELECT pc_id FROM computers WHERE pc_id = ? FOR UPDATE');
                $computer->execute([$active['pc_id']]);
                if (!$computer->fetchColumn()) {
                    throw new RuntimeException('Unable to end the session. Please approach the librarian.');
                }
                $database->prepare('UPDATE usage_sessions SET time_out = CURTIME() WHERE session_id = ? AND time_out IS NULL')
                    ->execute([$active['session_id']]);
                $pcSession = $database->prepare('SELECT session_id FROM usage_sessions WHERE pc_id = ? AND time_out IS NULL LIMIT 1 FOR UPDATE');
                $pcSession->execute([$active['pc_id']]);
                if (!$pcSession->fetchColumn()) {
                    $database->prepare("UPDATE computers SET status = 'Available' WHERE pc_id = ?")->execute([$active['pc_id']]);
                }
                $success = ['title' => 'Session Ended', 'text' => 'Your computer session has ended successfully.'];
            }
            $database->commit();
            unset($_SESSION['kiosk_student_id']);
            $state['success'] = $success;
        } else {
            throw new RuntimeException('Please enter your Student ID again.');
        }
    } catch (Throwable $exception) {
        if (isset($database) && $database->inTransaction()) $database->rollBack();
        $state['error'] = $exception instanceof RuntimeException && !($exception instanceof PDOException)
            ? $exception->getMessage() : 'Unable to connect right now. Please try again or approach the librarian.';
        // Refresh availability after a failed start, or show an active session created by another request.
        if (!empty($_SESSION['kiosk_student_id'])) {
            try {
                $state = array_merge($state, kiosk_student_state(db(), $_SESSION['kiosk_student_id']));
            } catch (Throwable $loadError) {
                unset($_SESSION['kiosk_student_id']);
                $state['mode'] = 'initial';
                $state['error'] = 'Unable to load your session. Please try again or approach the librarian.';
            }
        }
    }
    if (!$isAjax && ($state['success'] || $action === 'cancel')) {
        if ($state['success']) $_SESSION['kiosk_success'] = $state['success'];
        header('Location: checkin.php');
        exit;
    }
} else {
    // Returning to the kiosk always clears temporary identity; active database sessions remain intact.
    unset($_SESSION['kiosk_student_id']);
    $state['success'] = $_SESSION['kiosk_success'] ?? null;
    unset($_SESSION['kiosk_success']);
}

// One shared fragment serves full-page requests and JavaScript updates.
function kiosk_panel(array $state): string {
    ob_start();
    $initial = $state['mode'] === 'initial';
    $buttonText = $initial ? 'Continue' : ($state['mode'] === 'start' ? 'Start Session' : 'End Session');
    ?>
    <div class="intro">
        <h1 id="checkin-title">Student<br>Computer Session</h1>
        <p><?= $initial ? 'Enter your ID number to continue.' : ($state['mode'] === 'start' ? 'Choose an available computer to start.' : 'End your session when you leave.') ?></p>
    </div>
    <?php if (!$initial): ?>
        <div class="kiosk-identity<?= $state['mode'] === 'end' ? ' kiosk-identity-end' : '' ?>"><strong><?= kiosk_escape(trim($state['student']['first_name'] . ' ' . $state['student']['last_name'])) ?></strong><span><?= kiosk_escape($state['student']['student_id']) ?></span></div>
    <?php endif; ?>
    <form id="checkin-form" method="post" action="checkin.php" data-mode="<?= kiosk_escape($state['mode']) ?>" novalidate>
        <input type="hidden" name="action" value="<?= $initial ? 'continue' : kiosk_escape($state['mode']) ?>">
        <input type="hidden" name="token" value="<?= kiosk_escape($_SESSION['kiosk_token']) ?>">
        <?php if ($initial): ?>
            <div class="field<?= $state['id_error'] ? ' incorrect is-invalid' : '' ?>" id="id-field">
                <label for="id-number"><img src="images/id_card_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt=""><span class="sr-only">Student ID</span></label>
                <input type="text" id="id-number" name="id_number" value="<?= kiosk_escape($state['id']) ?>" placeholder="Student ID" inputmode="numeric" autocomplete="off" maxlength="9" required pattern="[0-9]{7}-[0-9]" aria-describedby="id-number-error">
                <p class="field-error" id="id-number-error"><?= kiosk_escape($state['error'] ?: 'Please enter a valid Student ID.') ?></p>
            </div>
        <?php elseif ($state['mode'] === 'start'): ?>
            <fieldset class="kiosk-pcs"><legend>Computers</legend><div class="kiosk-pc-grid">
                <?php foreach ($state['computers'] as $computer): $available = $computer['status'] === 'Available'; ?>
                    <label class="kiosk-pc-card"><input type="radio" name="pc_number" value="<?= kiosk_escape($computer['pc_number']) ?>" <?= $available ? 'required' : 'disabled' ?>><span class="kiosk-pc-content"><strong><?= kiosk_escape($computer['pc_number']) ?></strong><small><?= kiosk_escape($computer['status']) ?></small></span></label>
                <?php endforeach; ?>
            </div>
            <?php if (!$state['computers']): ?><p class="kiosk-empty">No computers are listed. Please approach the librarian.</p><?php elseif (!array_filter($state['computers'], fn($pc) => $pc['status'] === 'Available')): ?><p class="kiosk-empty">No computers are available right now.</p><?php endif; ?>
            </fieldset>
        <?php else: ?>
            <input type="hidden" name="session_id" value="<?= kiosk_escape($state['session']['session_id']) ?>">
            <section class="kiosk-current" aria-labelledby="current-session-title">
                <div class="kiosk-session-heading"><h2 id="current-session-title">Current Session</h2><span class="kiosk-active-badge">Active</span></div>
                <div class="kiosk-assigned-pc">
                    <span class="kiosk-computer-icon"><img src="images/desktop_windows_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt=""></span>
                    <div><span class="kiosk-detail-label">Assigned computer</span><strong><?= kiosk_escape($state['session']['pc_number']) ?></strong></div>
                </div>
                <dl><div><dt>Started</dt><dd><time datetime="<?= kiosk_escape($state['session']['date'] . 'T' . $state['session']['time_in']) ?>"><?= kiosk_escape(date('g:i A', strtotime($state['session']['time_in']))) ?><small><?= kiosk_escape(date('M j, Y', strtotime($state['session']['date']))) ?></small></time></dd></div></dl>
            </section>
        <?php endif; ?>
        <p class="form-error<?= $state['error'] ? ' is-visible' : '' ?>" id="checkin-error" role="alert"><?= kiosk_escape($state['error']) ?></p>
        <button type="submit" id="checkin-button" class="kiosk-submit" aria-label="<?= $buttonText ?>"><span class="button-label"><?= $buttonText ?></span><span class="kiosk-spinner" aria-hidden="true" hidden></span></button>
        <?php if (!$initial): ?><button type="submit" class="kiosk-cancel" name="action" value="cancel" formnovalidate>Cancel</button><?php endif; ?>
    </form>
    <?php if ($initial): ?><p class="checkin">Administrator? <a href="login.php">Admin Login</a></p><?php endif; ?>
    <?php if ($state['success']): ?><p id="kiosk-success" class="form-success is-visible" role="status" data-title="<?= kiosk_escape($state['success']['title']) ?>"><?= kiosk_escape($state['success']['text']) ?></p><?php endif; ?>
    <?php
    return ob_get_clean();
}

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['html' => kiosk_panel($state), 'mode' => $state['mode'], 'success' => $state['success']], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Computer Session</title>
    <link rel="icon" type="image/png" href="U2.png">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js" defer></script>
    <script src="checkin.js" defer></script>
</head>
<body class="kiosk-body">
    <main class="wrapper">
        <section class="login-panel kiosk-panel" id="kiosk-panel" aria-labelledby="checkin-title"><?= kiosk_panel($state) ?></section>
        <div class="image-panel" aria-hidden="true"></div>
    </main>
</body>
</html>
