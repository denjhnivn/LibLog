<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$statuses = ['Available', 'Occupied', 'Maintenance'];
$editComputer = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $computerId = (int) ($_POST['pc_id'] ?? 0);
    $pcNumber = trim($_POST['pc_number'] ?? '');
    $status = $_POST['status'] ?? '';

    try {
        $database = db();

        if ($action === 'delete') {
            $statement = $database->prepare('DELETE FROM computers WHERE pc_id = ?');
            $statement->execute([$computerId]);
            flash_message('admin_success', 'Computer deleted successfully.');
        } else {
            if ($pcNumber === '' || !in_array($status, $statuses, true)) {
                throw new RuntimeException('Please fill in all required fields.');
            }

            if ($action === 'add') {
                $statement = $database->prepare('INSERT INTO computers (pc_number, status) VALUES (?, ?)');
                $statement->execute([$pcNumber, $status]);
                flash_message('admin_success', 'Computer added successfully.');
            }

            if ($action === 'edit') {
                $statement = $database->prepare('UPDATE computers SET pc_number = ?, status = ? WHERE pc_id = ?');
                $statement->execute([$pcNumber, $status, $computerId]);
                flash_message('admin_success', 'Computer updated successfully.');
            }
        }
    } catch (RuntimeException $exception) {
        flash_message('admin_error', $exception->getMessage());
    } catch (PDOException $exception) {
        $message = $exception->getCode() === '23000' ? 'PC number already exists.' : 'Unable to save the computer record.';
        flash_message('admin_error', $message);
    }

    header('Location: computers.php');
    exit;
}

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
    $computers = $database->query('SELECT * FROM computers ORDER BY pc_number')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $computers = [];
    $loadError = 'Unable to load computers.';
}

admin_header('Computers', 'computers');
admin_notice();
?>
<?php if (!empty($loadError)): ?><p class="notice error"><?= h($loadError) ?></p><?php endif; ?>

<section class="content-card form-card">
    <div class="section-heading"><h2><?= $editComputer ? 'Edit Computer' : 'Add Computer' ?></h2><?php if ($editComputer): ?><a href="computers.php">Cancel</a><?php endif; ?></div>
    <form class="admin-form compact-form" method="post">
        <input type="hidden" name="action" value="<?= $editComputer ? 'edit' : 'add' ?>"><input type="hidden" name="pc_id" value="<?= h($editComputer['pc_id'] ?? '') ?>">
        <label>PC Number<input name="pc_number" required value="<?= h($editComputer['pc_number'] ?? '') ?>"></label>
        <label>Status<select name="status"><?php foreach ($statuses as $status): ?><option <?= $status === ($editComputer['status'] ?? 'Available') ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label>
        <button><?= $editComputer ? 'Save Changes' : 'Add Computer' ?></button>
    </form>
</section>

<section class="content-card"><h2>Computer Records</h2><div class="table-wrap"><table><thead><tr><th>PC Number</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($computers as $computer): ?><tr><td><?= h($computer['pc_number']) ?></td><td><span class="status <?= strtolower(str_replace(' ', '-', $computer['status'])) ?>"><?= h($computer['status']) ?></span></td><td class="actions"><a href="computers.php?edit=<?= $computer['pc_id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this computer permanently?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="pc_id" value="<?= $computer['pc_id'] ?>"><button class="link-danger">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php admin_footer(); ?>
