<?php
session_start();

if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

$checkins = $_SESSION['checkins'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="wrapper">
        <section class="login-panel" aria-labelledby="dashboard-title">
            <div class="intro">
                <h1 id="dashboard-title">Welcome,<br><?= $adminUsername ?></h1>
                <p>You are logged in as an administrator.</p>
            </div>

            <?php if (empty($checkins)): ?>
                <p>No student check-ins yet.</p>
            <?php else: ?>
                <p><strong><?= count($checkins) ?></strong> check-in(s) recorded this session:</p>
                <ul>
                    <?php foreach (array_reverse($checkins) as $entry): ?>
                        <li>
                            <?= htmlspecialchars($entry['id_number'], ENT_QUOTES, 'UTF-8') ?>
                            &mdash; <?= htmlspecialchars($entry['pc'], ENT_QUOTES, 'UTF-8') ?>
                            (<?= htmlspecialchars($entry['time'], ENT_QUOTES, 'UTF-8') ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <a class="logout-link" href="logout.php">Log out</a>
        </section>
        <div class="image-panel" aria-hidden="true"></div>
    </main>
</body>
</html>
