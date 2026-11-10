<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/admin_layout.php';

$values = get_settings();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'save';
    if ($action === 'reset') {
        $values = SETTING_DEFAULTS;
        $errors = save_settings(SETTING_DEFAULTS);
        if (!$errors) {
            flash_message('admin_success', 'Settings reset to defaults.');
            header('Location: settings.php');
            exit;
        }
    } else {
        $values = [
            'library_name' => is_string($_POST['library_name'] ?? null) ? $_POST['library_name'] : '',
            'opening_time' => is_string($_POST['opening_time'] ?? null) ? $_POST['opening_time'] : '',
            'closing_time' => is_string($_POST['closing_time'] ?? null) ? $_POST['closing_time'] : '',
            'max_session_minutes' => is_string($_POST['max_session_minutes'] ?? null) ? $_POST['max_session_minutes'] : '',
            'logs_per_page' => is_string($_POST['logs_per_page'] ?? null) ? $_POST['logs_per_page'] : '',
        ];
        $errors = save_settings($values);
        if (!$errors) {
            flash_message('admin_success', 'Settings saved.');
            header('Location: settings.php');
            exit;
        }
    }
}

$pageTitle = 'Settings';
admin_header($pageTitle, 'settings', 'Manage library hours, sessions, and report display.');
admin_notice();
?>
<section class="content-card form-card settings-card">
    <?php if (isset($errors['_form'])): ?><p class="settings-form-error" role="alert"><?= h($errors['_form']) ?></p><?php endif; ?>
    <form class="admin-form settings-form" method="post" novalidate>
        <?= csrf_field() ?>
        <section class="settings-group">
            <label class="settings-field" for="settings-library-name">Library name
                <input id="settings-library-name" type="text" name="library_name" maxlength="60" required value="<?= h($values['library_name']) ?>" aria-invalid="<?= isset($errors['library_name']) ? 'true' : 'false' ?>" aria-describedby="settings-library-name-help<?= isset($errors['library_name']) ? ' settings-library-name-error' : '' ?>">
                <small id="settings-library-name-help">Shown on the kiosk, login page, and reports.</small>
                <?php if (isset($errors['library_name'])): ?><small class="settings-field-error" id="settings-library-name-error" role="alert"><?= h($errors['library_name']) ?></small><?php endif; ?>
            </label>
        </section>

        <section class="settings-group">
            <div class="settings-fields">
                <label class="settings-field" for="settings-opening-time">Opening time
                    <input id="settings-opening-time" type="time" name="opening_time" required value="<?= h($values['opening_time']) ?>" aria-invalid="<?= isset($errors['opening_time']) ? 'true' : 'false' ?>" aria-describedby="settings-opening-time-help<?= isset($errors['opening_time']) ? ' settings-opening-time-error' : '' ?>">
                    <small id="settings-opening-time-help">Students can start sessions from this time.</small>
                    <?php if (isset($errors['opening_time'])): ?><small class="settings-field-error" id="settings-opening-time-error" role="alert"><?= h($errors['opening_time']) ?></small><?php endif; ?>
                </label>
                <label class="settings-field" for="settings-closing-time">Closing time
                    <input id="settings-closing-time" type="time" name="closing_time" required value="<?= h($values['closing_time']) ?>" aria-invalid="<?= isset($errors['closing_time']) ? 'true' : 'false' ?>" aria-describedby="settings-closing-time-help<?= isset($errors['closing_time']) ? ' settings-closing-time-error' : '' ?>">
                    <small id="settings-closing-time-help">Sessions still open after this time are ended automatically.</small>
                    <?php if (isset($errors['closing_time'])): ?><small class="settings-field-error" id="settings-closing-time-error" role="alert"><?= h($errors['closing_time']) ?></small><?php endif; ?>
                </label>
            </div>
        </section>

        <section class="settings-group">
            <label class="settings-field" for="settings-session-limit">Max session length
                <span class="settings-suffix"><input id="settings-session-limit" type="number" name="max_session_minutes" min="15" max="720" step="1" required value="<?= h($values['max_session_minutes']) ?>" aria-invalid="<?= isset($errors['max_session_minutes']) ? 'true' : 'false' ?>" aria-describedby="settings-session-limit-help<?= isset($errors['max_session_minutes']) ? ' settings-session-limit-error' : '' ?>"><span>minutes</span></span>
                <small id="settings-session-limit-help">Longer sessions are ended automatically.</small>
                <?php if (isset($errors['max_session_minutes'])): ?><small class="settings-field-error" id="settings-session-limit-error" role="alert"><?= h($errors['max_session_minutes']) ?></small><?php endif; ?>
            </label>
        </section>

        <section class="settings-group">
            <label class="settings-field" for="settings-logs-per-page">Logs per page
                <select id="settings-logs-per-page" name="logs_per_page" required aria-invalid="<?= isset($errors['logs_per_page']) ? 'true' : 'false' ?>" aria-describedby="settings-logs-per-page-help<?= isset($errors['logs_per_page']) ? ' settings-logs-per-page-error' : '' ?>">
                    <?php if (!in_array((string) $values['logs_per_page'], ['10', '25', '50', '100'], true)): ?><option value="<?= h($values['logs_per_page']) ?>" selected><?= h($values['logs_per_page'] === '' ? 'Invalid selection' : $values['logs_per_page'] . ' logs (invalid)') ?></option><?php endif; ?>
                    <?php foreach (['10', '25', '50', '100'] as $option): ?><option value="<?= $option ?>" <?= (string) $values['logs_per_page'] === $option ? 'selected' : '' ?>><?= $option ?> logs</option><?php endforeach; ?>
                </select>
                <small id="settings-logs-per-page-help">Choose how many usage log rows appear on each page.</small>
                <?php if (isset($errors['logs_per_page'])): ?><small class="settings-field-error" id="settings-logs-per-page-error" role="alert"><?= h($errors['logs_per_page']) ?></small><?php endif; ?>
            </label>
        </section>

        <div class="settings-actions">
            <button class="settings-reset" type="submit" name="action" value="reset">Reset to defaults</button>
            <button class="settings-save" type="submit" name="action" value="save">Save changes</button>
        </div>
    </form>
</section>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.settings-form');
    const resetButton = form?.querySelector('[name="action"][value="reset"]');
    if (!form || !resetButton) return;
    form.addEventListener('submit', event => {
        if (event.submitter !== resetButton || resetButton.dataset.confirmed === 'true') return;
        event.preventDefault();
        const submitReset = () => {
            resetButton.dataset.confirmed = 'true';
            form.requestSubmit(resetButton);
        };
        if (window.Swal) {
            window.Swal.fire({
                title: 'Reset settings?',
                text: 'All five settings will return to their default values.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Reset settings',
                cancelButtonText: 'Keep current settings',
                confirmButtonColor: '#5c87f4'
            }).then(result => { if (result.isConfirmed) submitReset(); });
        } else if (window.confirm('Reset all settings to their default values?')) {
            submitReset();
        }
    });
});
</script>
<?php admin_footer(); ?>
