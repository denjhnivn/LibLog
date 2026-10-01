<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$editStudent = null;
$search = trim($_GET['search'] ?? '');

// Process add, edit, and delete requests before rendering the page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $studentId = trim($_POST['student_id'] ?? '');

    try {
        $database = db();

        if ($action === 'delete') {
            $statement = $database->prepare('DELETE FROM students WHERE student_id = ?');
            $statement->execute([$studentId]);
            flash_message('admin_success', 'Student deleted successfully.');
        } else {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $course = trim($_POST['course'] ?? '');

            if ($studentId === '' || $firstName === '' || $lastName === '' || $email === '' || $course === '') {
                throw new RuntimeException('Please fill in all required fields.');
            }

            if ($action === 'add') {
                $statement = $database->prepare(
                    'INSERT INTO students (student_id, first_name, last_name, email, course) VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([$studentId, $firstName, $lastName, $email, $course]);
                flash_message('admin_success', 'Student added successfully.');
            }

            if ($action === 'edit') {
                $statement = $database->prepare(
                    'UPDATE students SET first_name = ?, last_name = ?, email = ?, course = ? WHERE student_id = ?'
                );
                $statement->execute([$firstName, $lastName, $email, $course, $studentId]);
                flash_message('admin_success', 'Student updated successfully.');
            }
        }
    } catch (RuntimeException $exception) {
        flash_message('admin_error', $exception->getMessage());
    } catch (PDOException $exception) {
        $message = $exception->getCode() === '23000'
            ? 'Student ID already exists.'
            : 'Unable to save the student record.';
        flash_message('admin_error', $message);
    }

    header('Location: students.php');
    exit;
}

try {
    $database = db();

    if (isset($_GET['edit'])) {
        $statement = $database->prepare('SELECT * FROM students WHERE student_id = ?');
        $statement->execute([$_GET['edit']]);
        $editStudent = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $statement = $database->prepare(
        'SELECT * FROM students
         WHERE student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR course LIKE ?
         ORDER BY last_name, first_name'
    );
    $searchTerm = '%' . $search . '%';
    $statement->execute(array_fill(0, 5, $searchTerm));
    $students = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $students = [];
    $loadError = 'Unable to load students.';
}

admin_header('Students', 'students');
admin_notice();
?>

<?php if (!empty($loadError)): ?>
    <p class="notice error"><?= h($loadError) ?></p>
<?php endif; ?>

<section class="content-card form-card">
    <div class="section-heading">
        <h2><?= $editStudent ? 'Edit Student' : 'Add Student' ?></h2>
        <?php if ($editStudent): ?><a href="students.php">Cancel</a><?php endif; ?>
    </div>

    <form class="admin-form" method="post">
        <input type="hidden" name="action" value="<?= $editStudent ? 'edit' : 'add' ?>">
        <label>Student ID<input name="student_id" required <?= $editStudent ? 'readonly' : '' ?> value="<?= h($editStudent['student_id'] ?? '') ?>"></label>
        <label>First Name<input name="first_name" required value="<?= h($editStudent['first_name'] ?? '') ?>"></label>
        <label>Last Name<input name="last_name" required value="<?= h($editStudent['last_name'] ?? '') ?>"></label>
        <label>Email<input type="email" name="email" required value="<?= h($editStudent['email'] ?? '') ?>"></label>
        <label>Course<input name="course" required value="<?= h($editStudent['course'] ?? '') ?>"></label>
        <button><?= $editStudent ? 'Save Changes' : 'Add Student' ?></button>
    </form>
</section>

<section class="content-card">
    <div class="section-heading">
        <h2>Student Records</h2>
        <?php admin_search('Search ID, name, email or course', $search); ?>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Student ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Course</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($students as $student): ?>
        <tr><td><?= h($student['student_id']) ?></td><td><?= h($student['first_name']) ?></td><td><?= h($student['last_name']) ?></td><td><?= h($student['email']) ?></td><td><?= h($student['course']) ?></td><td class="actions"><a href="students.php?edit=<?= urlencode($student['student_id']) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this student permanently?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= h($student['student_id']) ?>"><button class="link-danger">Delete</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php admin_footer(); ?>
