<?php

session_start();
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['is_admin'])) {
    header('Location: admin/dashboard.php');
    exit;
}
$accessError = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_error']);

$formError         = '';   
$usernameValue     = '';   
$usernameHasError  = false;
$passwordHasError  = false;
$credentialError   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? ''); 
    $password = $_POST['password'] ?? '';       

    $usernameValue = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    if ($username === '' || $password === '') {
        $usernameHasError = ($username === '');
        $passwordHasError = ($password === '');
        $formError = 'Please complete the required fields.';

    } else {
        try {
            $statement = db()->prepare(
                'SELECT staff_id, username, password FROM staff WHERE username = ? LIMIT 1'
            );
            $statement->execute([$username]);
            $staff = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$staff || !password_verify($password, $staff['password'])) {
                $usernameHasError = true;
                $passwordHasError = true;
                $credentialError = true;
                $formError = 'Incorrect admin username or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_username'] = $staff['username'];
                $_SESSION['admin_staff_id'] = $staff['staff_id'];
                $_SESSION['login_success'] = true;
                header('Location: admin/dashboard.php');
                exit;
            }
        } catch (PDOException $exception) {
            $formError = 'Unable to sign in right now. Please try again.';
        }
    }
}

function field_class(bool $hasError): string {
    return $hasError ? ' incorrect is-invalid' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="icon" type="image/png" href="U2.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="wrapper">
        <section class="login-panel" aria-labelledby="login-title">
            <div class="intro">
                <h1 id="login-title">Admin Login</h1>
                <p>Sign in to access your account</p>
            </div>

            <div id="login-container" class="login-container">
                <form id="login-form" method="POST" action="login.php" novalidate>
                <div class="field<?= field_class($usernameHasError) ?>" id="username-field">
                    <label for="username-input">
                        <img src="images/person_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt="">
                        <span class="sr-only">Username</span>
                    </label>
                    <input type="text" id="username-input" name="username"
                           value="<?= $usernameValue ?>"
                           placeholder="Username" autocomplete="username">
                    <?php if (!$credentialError): ?><p class="field-error">Username is required.</p><?php endif; ?>
                </div>

                <div class="field<?= field_class($passwordHasError) ?>" id="password-field">
                    <label for="password-input">
                        <img src="images/lock_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt="">
                        <span class="sr-only">Password</span>
                    </label>
                    <input type="password" id="password-input" name="password"
                           placeholder="Password" autocomplete="current-password">
                    <?php if (!$credentialError): ?><p class="field-error">Password is required.</p><?php endif; ?>
                </div>
                    <button type="submit" id="login-button" aria-label="Login"><span class="login-button-label">Login</span><span class="login-spinner" aria-hidden="true" hidden></span></button>
                <p class="form-error<?= ($formError || $accessError) ? ' is-visible' : '' ?>" id="form-error" role="alert">
                    <?= htmlspecialchars($formError ?: $accessError) ?>
                </p>
                <p class="form-success" id="form-success" role="status"></p> 
                </form>

            </div>

            <p class="checkin">Are you a student? <a href="checkin.php">Start Session</a></p>
        </section>
        <div class="image-panel" aria-hidden="true"></div>
    </main>
    <script src="login.js"></script>
</body>
</html>
