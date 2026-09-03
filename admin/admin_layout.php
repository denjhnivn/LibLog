<?php

/**
 * Outputs the shared sidebar and the opening tags for every admin page.
 */
function admin_header(string $title, string $activePage): void
{
    $adminName = h($_SESSION['admin_username'] ?? 'Admin');
    $navigation = [
        'dashboard.php' => ['Dashboard', 'dashboard'],
        'students.php' => ['Students', 'students'],
        'computers.php' => ['Computers', 'computers'],
        'usage_logs.php' => ['Usage Logs', 'logs'],
        'staff.php' => ['Staff', 'staff'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> | LibLog</title>
    <link rel="icon" type="image/jpeg" href="../logo.jpg">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="brand" href="dashboard.php">
                <img class="brand-logo" src="../logo.jpg" alt="LibLog logo">
                <span>LibLog<small>Library Management</small></span>
            </a>

            <nav class="admin-nav" aria-label="Admin navigation">
                <?php foreach ($navigation as $url => [$label, $pageKey]): ?>
                    <a href="<?= $url ?>" class="<?= $pageKey === $activePage ? 'active' : '' ?>">
                        <?= h($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="admin-profile">
                    <img src="admin.png" alt="Admin profile">
                    <span>Signed in as<br><strong><?= $adminName ?></strong></span>
                </div>
                <a href="logout.php">Logout</a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <p class="eyebrow">LIBRARY COMPUTER USAGE</p>
                <h1><?= h($title) ?></h1>
            </header>
    <?php
}

/** Outputs the shared closing tags for every admin page. */
function admin_footer(): void
{
    ?>
        </main>
    </div>
</body>
</html>
    <?php
}

/** Displays and clears one-time success or error messages. */
function admin_notice(): void
{
    $success = flash_message('admin_success');
    $error = flash_message('admin_error');

    if ($success !== '') {
        echo '<p class="notice success">' . h($success) . '</p>';
    }

    if ($error !== '') {
        echo '<p class="notice error">' . h($error) . '</p>';
    }
}
