<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

$editStudent = null;
$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$courseOptions = ['BSIT', 'BIT', 'BSME', 'BSEE', 'BSCE', 'BSCpE', 'BSFT', 'BSCrim', 'BSHM', 'BSTM'];
function student_email_is_valid(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $domain = substr(strrchr($email, '@'), 1);
    return (bool) preg_match('/\A(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}\z/', $domain);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($action === 'import') {
        csrf_verify();
        $imported = 0;
        $duplicates = 0;
        $invalid = 0;
        $rejected = [];
        try {
            $file = $_FILES['students_csv'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
                throw new RuntimeException('Choose a readable CSV file to import.');
            }
            $handle = fopen($file['tmp_name'], 'rb');
            if ($handle === false) throw new RuntimeException('Unable to open the uploaded CSV file.');
            $header = fgetcsv($handle);
            if (!$header) throw new RuntimeException('The CSV file is empty.');
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $expected = ['student_id', 'first_name', 'last_name', 'email', 'course'];
            if (array_map('trim', $header) !== $expected) {
                fclose($handle);
                throw new RuntimeException('CSV header must be: student_id, first_name, last_name, email, course.');
            }
            $database = db();
            $exists = $database->prepare('SELECT 1 FROM students WHERE student_id = ?');
            $insert = $database->prepare('INSERT INTO students (student_id, first_name, last_name, email, course) VALUES (?, ?, ?, ?, ?)');
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if (count($row) === 1 && trim((string) $row[0]) === '') continue;
                $values = array_map(static fn($value): string => trim((string) $value), array_pad(array_slice($row, 0, 5), 5, ''));
                [$studentId, $firstName, $lastName, $email, $course] = $values;
                $reason = '';
                if (count($row) !== 5) $reason = 'Expected five columns';
                elseif (!preg_match('/\A[0-9]{7}-[0-9]\z/', $studentId)) $reason = 'Invalid student ID';
                elseif (!student_email_is_valid($email)) $reason = 'Invalid email (use a domain such as gmail.com)';
                elseif ($firstName === '' || $lastName === '' || $course === '') $reason = 'Name and course are required';
                if ($reason !== '') {
                    $invalid++;
                    $rejected[] = [$line, ...$values, $reason];
                    continue;
                }
                $exists->execute([$studentId]);
                if ($exists->fetchColumn()) {
                    $duplicates++;
                    continue;
                }
                try {
                    $insert->execute($values);
                    $imported++;
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') $duplicates++;
                    else throw $exception;
                }
            }
            fclose($handle);
            if ($rejected) $_SESSION['rejected_students'] = $rejected;
            else unset($_SESSION['rejected_students']);
            $_SESSION['students_import_result'] = compact('imported', 'duplicates', 'invalid');
            flash_message('admin_success', "$imported imported, $duplicates duplicates, $invalid invalid.");
        } catch (Throwable $exception) {
            if (isset($handle) && is_resource($handle)) fclose($handle);
            flash_message('admin_error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to import the CSV file.');
        }
        header('Location: students.php');
        exit;
    }

    $studentId = trim(is_string($_POST['student_id'] ?? null) ? $_POST['student_id'] : '');
    try {
        $database = db();
        if ($action === 'delete') {
            $statement = $database->prepare('DELETE FROM students WHERE student_id = ?');
            $statement->execute([$studentId]);
            flash_message('admin_success', 'Student deleted successfully.');
        } else {
            $firstName = trim(is_string($_POST['first_name'] ?? null) ? $_POST['first_name'] : '');
            $lastName = trim(is_string($_POST['last_name'] ?? null) ? $_POST['last_name'] : '');
            $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
            $course = trim(is_string($_POST['course'] ?? null) ? $_POST['course'] : '');
            if ($studentId === '' || $firstName === '' || $lastName === '' || $email === '' || !in_array($course, $courseOptions, true)) throw new RuntimeException('Please fill in all required fields and select a valid course.');
            if (!student_email_is_valid($email)) throw new RuntimeException('Enter a valid email address with a domain ending, such as name@gmail.com.');
            if ($action === 'add') {
                $statement = $database->prepare('INSERT INTO students (student_id, first_name, last_name, email, course) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$studentId, $firstName, $lastName, $email, $course]);
                flash_message('admin_success', 'Student added successfully.');
            } elseif ($action === 'edit') {
                $statement = $database->prepare('UPDATE students SET first_name = ?, last_name = ?, email = ?, course = ? WHERE student_id = ?');
                $statement->execute([$firstName, $lastName, $email, $course, $studentId]);
                flash_message('admin_success', 'Student updated successfully.');
            } else {
                throw new RuntimeException('Unknown student action.');
            }
        }
    } catch (PDOException $exception) {
        flash_message('admin_error', $exception->getCode() === '23000' ? 'Student ID already exists.' : 'Unable to save the student record.');
    } catch (RuntimeException $exception) {
        flash_message('admin_error', $exception->getMessage());
    }
    if ($action === 'edit' && !empty($_SESSION['admin_error'])) {
        $_SESSION['students_edit_form'] = ['student_id' => $studentId, 'first_name' => $_POST['first_name'] ?? '', 'last_name' => $_POST['last_name'] ?? '', 'email' => $_POST['email'] ?? '', 'course' => $_POST['course'] ?? ''];
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
    $statement = $database->prepare('SELECT * FROM students WHERE student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR course LIKE ? ORDER BY last_name, first_name');
    $searchTerm = '%' . $search . '%';
    $statement->execute(array_fill(0, 5, $searchTerm));
    $students = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $students = [];
    $loadError = 'Unable to load students.';
}
$savedEdit = $_SESSION['students_edit_form'] ?? null;
unset($_SESSION['students_edit_form']);
if ($savedEdit) $editStudent = $savedEdit;
$hasRejectedRows = !empty($_SESSION['rejected_students']);
$importResult = $_SESSION['students_import_result'] ?? null;
unset($_SESSION['students_import_result']);
admin_header('Students', 'students');
admin_notice();
?>
<?php if (!empty($loadError)): ?><p class="notice error"><?= h($loadError) ?></p><?php endif; ?>
<section class="content-card import-toolbar"><div class="section-heading"><div><h2>Bulk Import</h2><p class="muted-text">Import enrollment details from a CSV file.</p></div><button type="button" class="import-trigger" data-open-import><i data-lucide="upload" aria-hidden="true"></i> Import CSV</button></div>
    <?php if ($importResult): ?><div class="import-results"><span class="import-chip"><strong><?= (int) $importResult['imported'] ?></strong> Imported</span><span class="import-chip"><strong><?= (int) $importResult['duplicates'] ?></strong> Duplicates</span><span class="import-chip"><strong><?= (int) $importResult['invalid'] ?></strong> Invalid</span><?php if ($hasRejectedRows): ?><a href="download_rejected.php">Download rejected rows</a><?php endif; ?></div><?php elseif ($hasRejectedRows): ?><p><a href="download_rejected.php">Download rejected rows</a></p><?php endif; ?>
</section>
<dialog id="students-import-modal" class="admin-modal import-modal" aria-labelledby="students-import-title">
    <div class="modal-header"><h2 id="students-import-title"><i data-lucide="file-up" aria-hidden="true"></i>Import students from CSV</h2><button type="button" class="modal-close" data-close-import aria-label="Close import dialog"><i data-lucide="x" aria-hidden="true"></i></button></div>
    <form id="students-import-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="import">
        <input class="import-file-input" type="file" name="students_csv" accept=".csv,text/csv" tabindex="-1" aria-hidden="true">
        <button type="button" class="import-dropzone" tabindex="0" aria-describedby="import-meta"><i data-lucide="upload" aria-hidden="true"></i><strong>Drag &amp; drop a CSV file here or browse</strong><span id="import-meta">CSV only · Max 2 MB</span></button>
        <div class="import-file-row" hidden><span class="import-file-name"></span><span class="import-file-size"></span><button type="button" class="import-remove">Remove</button></div>
        <p class="import-error" role="alert" hidden></p><p class="import-helper">Required columns: student_id, first_name, last_name, email, course</p><a class="import-template" href="data:text/csv;charset=utf-8,student_id%2Cfirst_name%2Clast_name%2Cemail%2Ccourse%0A" download="students-template.csv">Download template</a>
        <div class="modal-actions import-actions"><button type="button" class="button-secondary" data-close-import>Cancel</button><button type="submit" disabled><span>Import</span><i data-lucide="loader-circle" aria-hidden="true"></i></button></div>
    </form>
</dialog>
<section class="content-card form-card"><div class="section-heading"><h2>Add Student</h2></div>
    <form class="admin-form" method="post"><input type="hidden" name="action" value="add">
        <label>Student ID<input name="student_id" required pattern="[0-9]{7}-[0-9]"></label><label>First Name<input name="first_name" required></label><label>Last Name<input name="last_name" required></label><label>Email<input name="email" type="email" pattern="[^@\s]+@([A-Za-z0-9-]+\.)+[A-Za-z]{2,}" title="Enter a valid email with a domain ending, such as name@gmail.com." required></label><label>Course<select name="course" required><option value="" disabled selected>Select course</option><?php foreach ($courseOptions as $courseOption): ?><option value="<?= h($courseOption) ?>"><?= h($courseOption) ?></option><?php endforeach; ?></select></label><button>Add Student</button>
    </form>
</section>
<dialog id="students-edit-modal" class="admin-modal" aria-labelledby="students-edit-title" data-open="<?= $editStudent ? 'true' : 'false' ?>">
    <div class="modal-header"><h2 id="students-edit-title"><i data-lucide="graduation-cap" aria-hidden="true"></i>Edit Student</h2><a class="modal-close" href="students.php" data-close-modal aria-label="Close Edit Student"><i data-lucide="x" aria-hidden="true"></i></a></div>
    <?php if ($savedEdit) admin_notice(); ?>
    <form class="admin-form record-edit-form" method="post"><input type="hidden" name="action" value="edit">
        <label>Student ID<input name="student_id" required value="<?= h($editStudent['student_id'] ?? '') ?>" readonly></label><label>First Name<input name="first_name" required value="<?= h($editStudent['first_name'] ?? '') ?>" autofocus></label><label>Last Name<input name="last_name" required value="<?= h($editStudent['last_name'] ?? '') ?>"></label><label>Email<input name="email" type="email" pattern="[^@\s]+@([A-Za-z0-9-]+\.)+[A-Za-z]{2,}" title="Enter a valid email with a domain ending, such as name@gmail.com." required value="<?= h($editStudent['email'] ?? '') ?>"></label><label>Course<select name="course" required><option value="" disabled <?= !in_array($editStudent['course'] ?? '', $courseOptions, true) ? 'selected' : '' ?>>Select course</option><?php foreach ($courseOptions as $courseOption): ?><option value="<?= h($courseOption) ?>" <?= ($editStudent['course'] ?? '') === $courseOption ? 'selected' : '' ?>><?= h($courseOption) ?></option><?php endforeach; ?></select></label>
        <div class="modal-actions"><button type="submit">Save Changes</button><a class="button-secondary" href="students.php" data-close-modal>Cancel</a></div>
    </form>
</dialog>
<noscript><style>#students-edit-modal[data-open="true"] { display:block; position:static; margin:0 0 24px; }</style></noscript>
<section class="content-card"><div class="section-heading"><h2>Student Records</h2><?php admin_search('Search ID, name, email or course', $search); ?></div>
    <div class="table-wrap"><table><thead><tr><th>Student ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Course</th><th>History</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($students as $student): ?><tr><td><?= h($student['student_id']) ?></td><td><?= h($student['first_name']) ?></td><td><?= h($student['last_name']) ?></td><td><?= h($student['email']) ?></td><td><?= h($student['course']) ?></td><td><a href="student_history.php?id=<?= urlencode($student['student_id']) ?>">View history</a></td><td class="actions"><div class="action-group"><a href="students.php?edit=<?= urlencode($student['student_id']) ?>" data-edit-modal="students-edit-modal" data-record="<?= h(json_encode($student)) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this student permanently?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= h($student['student_id']) ?>"><button class="link-danger">Delete</button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div><?php if (!$students): ?><p class="empty-state">No students found.</p><?php endif; ?>
</section>
<?php admin_footer(); ?>
