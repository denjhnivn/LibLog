<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$editStaff = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $staffId = (int) ($_POST['staff_id'] ?? 0);

    try {
        $database = db();

        if ($action === 'delete') {
            if ($staffId === (int) ($_SESSION['admin_staff_id'] ?? -1)) {
                throw new RuntimeException('You cannot delete your own active account.');
            }
            $database->prepare('DELETE FROM staff WHERE staff_id = ?')->execute([$staffId]);
            flash_message('admin_success', 'Staff member deleted successfully.');
        } else {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($firstName === '' || $lastName === '' || $email === '' || $username === '' || ($action === 'add' && $password === '')) {
                throw new RuntimeException('Please fill in all required fields.');
            }

            if ($action === 'add') {
                $statement = $database->prepare('INSERT INTO staff (first_name, last_name, email, username, password) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$firstName, $lastName, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);
                flash_message('admin_success', 'Staff member added successfully.');
            }

            if ($action === 'edit') {
                if ($password !== '') {
                    $statement = $database->prepare('UPDATE staff SET first_name=?, last_name=?, email=?, username=?, password=? WHERE staff_id=?');
                    $statement->execute([$firstName, $lastName, $email, $username, password_hash($password, PASSWORD_DEFAULT), $staffId]);
                } else {
                    $statement = $database->prepare('UPDATE staff SET first_name=?, last_name=?, email=?, username=? WHERE staff_id=?');
                    $statement->execute([$firstName, $lastName, $email, $username, $staffId]);
                }
                flash_message('admin_success', 'Staff member updated successfully.');
            }
        }
    } catch (PDOException $exception) {
        $message = $exception->getCode() === '23000' ? 'Username already exists.' : 'Unable to save the staff record.';
        flash_message('admin_error', $message);
    } catch (RuntimeException $exception) {
        flash_message('admin_error', $exception->getMessage());
    }

    if ($action === 'edit' && !empty($_SESSION['admin_error'])) {
        $_SESSION['staff_edit_form'] = ['staff_id' => $staffId, 'first_name' => trim($_POST['first_name'] ?? ''), 'last_name' => trim($_POST['last_name'] ?? ''), 'email' => trim($_POST['email'] ?? ''), 'username' => trim($_POST['username'] ?? '')];
    }

    header('Location: staff.php');
    exit;
}

try {
    $database = db();
    if (isset($_GET['edit'])) {
        $statement = $database->prepare('SELECT staff_id, first_name, last_name, email, username FROM staff WHERE staff_id = ?');
        $statement->execute([(int) $_GET['edit']]);
        $editStaff = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $staffMembers = $database->query('SELECT staff_id, first_name, last_name, email, username FROM staff ORDER BY last_name, first_name')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $staffMembers = [];
    $loadError = 'Unable to load staff.';
}

$savedEdit = $_SESSION['staff_edit_form'] ?? null;
unset($_SESSION['staff_edit_form']);
if ($savedEdit) $editStaff = $savedEdit;

admin_header('Staff', 'staff');
if (!$savedEdit) admin_notice();
?>
<?php if (!empty($loadError)): ?><p class="notice error"><?= h($loadError) ?></p><?php endif; ?>
<section class="content-card form-card">
    <div class="section-heading"><h2>Add Staff Member</h2></div>
    <form class="admin-form" method="post">
        <input type="hidden" name="action" value="add">
        <label>First Name<input name="first_name" required></label>
        <label>Last Name<input name="last_name" required></label>
        <label>Email<input name="email" type="email" required></label>
        <label>Username<input name="username" required></label>
        <label>Password<input name="password" type="password" autocomplete="new-password" required></label>
        <button>Add Staff Member</button>
    </form>
</section>

<dialog id="staff-edit-modal" class="admin-modal" aria-labelledby="staff-edit-title" data-open="<?= $editStaff ? 'true' : 'false' ?>">
    <div class="modal-header"><h2 id="staff-edit-title"><i data-lucide="users" aria-hidden="true"></i>Edit Staff Member</h2><a class="modal-close" href="staff.php" data-close-modal aria-label="Close Edit Staff Member"><i data-lucide="x" aria-hidden="true"></i></a></div>
    <?php if ($savedEdit) admin_notice(); ?>
    <form class="admin-form record-edit-form" method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="staff_id" value="<?= h($editStaff['staff_id'] ?? '') ?>">
        <label>First Name<input name="first_name" required value="<?= h($editStaff['first_name'] ?? '') ?>" autofocus></label>
        <label>Last Name<input name="last_name" required value="<?= h($editStaff['last_name'] ?? '') ?>"></label>
        <label>Email<input name="email" type="email" required value="<?= h($editStaff['email'] ?? '') ?>"></label>
        <label>Username<input name="username" required value="<?= h($editStaff['username'] ?? '') ?>"></label>
        <label>Password<input name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
        <div class="modal-actions"><button type="submit"><i data-lucide="save" aria-hidden="true"></i>Save Changes</button><a class="button-secondary" href="staff.php" data-close-modal>Cancel</a></div>
    </form>
</dialog>
<noscript><style>#staff-edit-modal[data-open="true"] { display: block; position: static; margin: 0 0 24px; }</style></noscript>

<section class="content-card"><h2>Staff Records</h2><div class="table-wrap"><table><thead><tr><th>Staff ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Username</th><th class="actions-heading">Actions</th></tr></thead><tbody><?php foreach ($staffMembers as $staff): ?><tr><td><?= h($staff['staff_id']) ?></td><td><?= h($staff['first_name']) ?></td><td><?= h($staff['last_name']) ?></td><td><?= h($staff['email']) ?></td><td><?= h($staff['username']) ?></td><td class="actions"><div class="action-group"><a href="staff.php?edit=<?= $staff['staff_id'] ?>" data-edit-modal="staff-edit-modal" data-record="<?= h(json_encode($staff)) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this staff member permanently?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="staff_id" value="<?= $staff['staff_id'] ?>"><button class="link-danger">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_footer(); ?>
