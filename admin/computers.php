<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$statuses = ['Available', 'Occupied', 'Maintenance'];
$editComputer = null;

function computer_pc_suffix($value): string
{
    $value = trim((string) $value);
    return preg_replace('/^(?:PC[\s-]*)+/i', '', $value) ?? $value;
}

function computer_pc_label($value): string
{
    $suffix = computer_pc_suffix($value);
    return preg_match('/\A[1-9][0-9]*\z/', $suffix) ? 'PC-' . $suffix : trim((string) $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $computerId = (int) ($_POST['pc_id'] ?? 0);
    $pcNumber = trim($_POST['pc_number'] ?? '');
    $status = $action === 'add' ? 'Available' : ($_POST['status'] ?? '');

    try {
        $database = db();

        if ($action === 'delete') {
            $statement = $database->prepare('DELETE FROM computers WHERE pc_id = ?');
            $statement->execute([$computerId]);
            flash_message('admin_success', 'Computer deleted successfully.');
        } else {
            if (!preg_match('/\APC-[1-9][0-9]*\z/', $pcNumber) || strlen($pcNumber) > 10) {
                throw new RuntimeException('PC number must use the format PC-1 (up to 10 characters).');
            }
            if (!in_array($action, ['add', 'edit'], true) || ($action === 'edit' && $computerId < 1) || !in_array($status, $statuses, true)) {
                throw new RuntimeException('Please fill in all required fields.');
            }

            if ($action === 'add') {
                $statement = $database->prepare('INSERT INTO computers (pc_number, status) VALUES (?, ?)');
                $statement->execute([$pcNumber, $status]);
                flash_message('admin_success', 'Computer added successfully.');
            }

            if ($action === 'edit') {
                if ($status === 'Maintenance') {
                    $database->beginTransaction();
                    $locked = $database->prepare('SELECT pc_id FROM computers WHERE pc_id = ? FOR UPDATE');
                    $locked->execute([$computerId]);
                    if (!$locked->fetchColumn()) throw new RuntimeException('Computer not found.');
                    $active = $database->prepare('SELECT session_id FROM usage_sessions WHERE pc_id = ? AND time_out IS NULL LIMIT 1 FOR UPDATE');
                    $active->execute([$computerId]);
                    if ($active->fetchColumn()) {
                        $database->rollBack();
                        throw new RuntimeException('This computer has an active session. End the session before setting Maintenance.');
                    }
                }
                $statement = $database->prepare('UPDATE computers SET pc_number = ?, status = ? WHERE pc_id = ?');
                $statement->execute([$pcNumber, $status, $computerId]);
                if ($database->inTransaction()) $database->commit();
                flash_message('admin_success', 'Computer updated successfully.');
            }
        }
    } catch (PDOException $exception) {
        if (isset($database) && $database->inTransaction()) $database->rollBack();
        $message = $exception->getCode() === '23000' ? ($action === 'delete' ? 'This computer has usage logs and cannot be deleted.' : 'PC number already exists.') : 'Unable to save the computer record.';
        flash_message('admin_error', $message);
        $_SESSION['computer_form'] = ['action' => $action, 'pc_id' => $computerId, 'pc_number' => $pcNumber, 'status' => $status];
    } catch (RuntimeException $exception) {
        if (isset($database) && $database->inTransaction()) $database->rollBack();
        flash_message('admin_error', $exception->getMessage());
        $_SESSION['computer_form'] = ['action' => $action, 'pc_id' => $computerId, 'pc_number' => $pcNumber, 'status' => $status];
    }

    header('Location: computers.php');
    exit;
}

$savedForm = $_SESSION['computer_form'] ?? [];
unset($_SESSION['computer_form']);

try {
    $database = db();
    if (isset($_GET['edit'])) {
        $statement = $database->prepare('SELECT * FROM computers WHERE pc_id = ?');
        $statement->execute([(int) $_GET['edit']]);
        $editComputer = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

        // Older check-ins used "In Use". Show it as the current Occupied status.
        if ($editComputer && $editComputer['status'] === 'In Use') {
            $editComputer['status'] = 'Occupied';
        }
    }
    $computers = $database->query("SELECT * FROM computers ORDER BY CAST(SUBSTRING_INDEX(pc_number, '-', -1) AS UNSIGNED), pc_number")->fetchAll(PDO::FETCH_ASSOC);
    usort($computers, static function (array $left, array $right): int {
        $numberOrder = (int) computer_pc_suffix($left['pc_number']) <=> (int) computer_pc_suffix($right['pc_number']);
        return $numberOrder !== 0 ? $numberOrder : strcasecmp(computer_pc_label($left['pc_number']), computer_pc_label($right['pc_number']));
    });
    foreach ($computers as &$computer) {
        if ($computer['status'] === 'In Use') $computer['status'] = 'Occupied';
    }
    unset($computer);
} catch (PDOException $exception) {
        if (isset($database) && $database->inTransaction()) $database->rollBack();
    $computers = [];
    $loadError = 'Unable to load computers.';
}

if (($savedForm['action'] ?? '') === 'edit') {
    $editComputer = $savedForm;
}
admin_header('Computers', 'computers');
if (($savedForm['action'] ?? '') !== 'edit') admin_notice();
?>
<?php if (!empty($loadError)): ?><p class="notice error"><?= h($loadError) ?></p><?php endif; ?>

<section class="content-card form-card computer-add-card">
    <div class="section-heading"><h2>Add Computer</h2></div>
    <form class="admin-form computer-add-form" method="post">
        <input type="hidden" name="action" value="add">
        <label>PC Number<input name="pc_number" type="text" required maxlength="10" pattern="PC-[1-9][0-9]*" placeholder="PC-1" value="<?= h(($savedForm['action'] ?? '') === 'add' ? $savedForm['pc_number'] : '') ?>"></label>
        <button>Add Computer</button>
    </form>
</section>

<dialog id="computer-edit-modal" class="admin-modal" aria-labelledby="computer-edit-title" data-open="<?= $editComputer ? 'true' : 'false' ?>">
    <div class="modal-header"><h2 id="computer-edit-title"><i data-lucide="monitor" aria-hidden="true"></i>Edit Computer</h2><a class="modal-close" href="computers.php" data-close-modal aria-label="Close Edit Computer"><i data-lucide="x" aria-hidden="true"></i></a></div>
    <?php if (($savedForm['action'] ?? '') === 'edit') admin_notice(); ?>
    <form class="admin-form computer-edit-form" method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="pc_id" value="<?= h($editComputer['pc_id'] ?? '') ?>">
        <label>PC Number<input name="pc_number" type="text" autofocus required maxlength="10" pattern="PC-[1-9][0-9]*" placeholder="PC-1" value="<?= h(isset($editComputer['pc_number']) ? computer_pc_label($editComputer['pc_number']) : '') ?>"></label>
        <fieldset class="status-fieldset">
            <legend>Status</legend>
            <div class="status-segments">
                <?php foreach ($statuses as $status): ?>
                <label class="status-segment"><input type="radio" name="status" value="<?= h($status) ?>" required <?= $status === ($editComputer['status'] ?? 'Available') ? 'checked' : '' ?>><span><?= h($status) ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="modal-actions"><button type="submit"><i data-lucide="save" aria-hidden="true"></i>Save Changes</button><a class="button-secondary" href="computers.php" data-close-modal>Cancel</a></div>
    </form>
</dialog>
<noscript><style>#computer-edit-modal[data-open="true"] { display: block; position: static; margin: 0 0 24px; }</style></noscript>

<section class="content-card"><h2>Computer Records</h2><div class="table-wrap"><table><thead><tr><th>PC Number</th><th>Status</th><th class="actions-heading">Actions</th></tr></thead><tbody>
<?php foreach ($computers as $computer): ?><tr><td><?= h(computer_pc_label($computer['pc_number'])) ?></td><td><span class="status <?= strtolower(str_replace(' ', '-', $computer['status'])) ?>"><?= h($computer['status']) ?></span></td><td class="actions"><div class="action-group"><a href="computers.php?edit=<?= $computer['pc_id'] ?>" data-edit-modal="computer-edit-modal" data-record="<?= h(json_encode($computer)) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this computer permanently?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="pc_id" value="<?= $computer['pc_id'] ?>"><button class="link-danger">Delete</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php admin_footer(); ?>
