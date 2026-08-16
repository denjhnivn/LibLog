<?php

session_start();

$VALID_USERNAME = 'admin01';
$VALID_PASSWORD = 'password';

if (!empty($_SESSION['is_admin'])) {
    header('Location: dashboard.php');
    exit;
}

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

    } elseif ($username !== $VALID_USERNAME || $password !== $VALID_PASSWORD) {
        $usernameHasError = true;
        $passwordHasError = true;
        $credentialError = true;

        $formError = 'Incorrect admin username or password.';

    } else {
        $_SESSION['is_admin']       = true;
        $_SESSION['admin_username'] = $username;

        header('Location: dashboard.php');
        exit;
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="wrapper">
        <section class="login-panel" aria-labelledby="login-title">
            <div class="intro">
                <h1 id="login-title">Hello,<br>Welcome Admin!</h1>
                <p>Sign in to access your account</p>
            </div>

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
                <button type="submit">Login</button>
                <p class="form-error<?= $formError ? ' is-visible' : '' ?>" id="form-error" role="alert">
                    <?= htmlspecialchars($formError) ?>
                </p>
                <p class="form-success" id="form-success" role="status"></p>
            </form>

            <p class="checkin">Are you a student? <a href="checkin.php">Check-In</a></p>
        </section>
        <div class="image-panel" aria-hidden="true"></div>
    </main>
</body>
</html>
