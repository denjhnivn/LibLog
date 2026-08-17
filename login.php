<?php
// This page checks the login again on the server.
session_start();

$correctUsername = 'admin01';
$correctPassword = 'password';
$username = '';
$formError = '';
$usernameError = false;
$passwordError = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username == '' || $password == '') {
        $formError = 'Please enter both username and password.';
        $usernameError = ($username == '');
        $passwordError = ($password == '');
    } elseif ($username != $correctUsername || $password != $correctPassword) {
        $formError = 'Incorrect admin username or password.';
        $usernameError = true;
        $passwordError = true;
    } else {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <main class="wrapper">
        <section class="login-panel">
            <div class="intro">
                <h1>Hello,<br>Welcome Admin!</h1>
                <p>Sign in to access your account</p>
            </div>

            <div id="login-container">
                <form id="login-form" method="POST" action="login.php" novalidate>
                    <div class="field <?php if ($usernameError) { echo 'error'; } ?>">
                        <label for="username-input">
                            <img src="images/person_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt="">
                            <span class="sr-only">Username</span>
                        </label>
                        <input type="text" id="username-input" name="username"
                               value="<?php echo htmlspecialchars($username); ?>" placeholder="Enter username">
                    </div>

                    <div class="field <?php if ($passwordError) { echo 'error'; } ?>">
                        <label for="password-input">
                            <img src="images/lock_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt="">
                            <span class="sr-only">Password</span>
                        </label>
                        <input type="password" id="password-input" name="password" placeholder="Enter password">
                    </div>

                    <button type="button" id="sync-login-button" class="sync-button">
                        Login - Synchronous
                    </button>
                    <button type="submit" id="async-login-button">
                        Login - Asynchronous
                    </button>
                    <p class="form-error <?php if ($formError != '') { echo 'is-visible'; } ?>" id="form-error">
                        <?php echo $formError; ?>
                    </p>
                </form>
            </div>

            <p class="checkin">Are you a student? <a href="checkin.php">Check-In</a></p>
        </section>
        <div class="image-panel"></div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="login.js"></script>
</body>
</html>
