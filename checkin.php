<?php
session_start();

$_SESSION['checkins'] ??= [];

$pcChoices = ['PC 1', 'PC 2', 'PC 3', 'PC 4', 'PC 5'];

$checkinError    = '';
$checkinSuccess  = '';
$idValue         = '';
$selectedPc      = '';
$idHasError      = false;
$pcHasError      = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $idNumber   = trim($_POST['id_number'] ?? '');
    $selectedPc = trim($_POST['pc_number'] ?? '');

    $idValue = htmlspecialchars($idNumber, ENT_QUOTES, 'UTF-8');

    $idHasError = ($idNumber === '');
    $pcHasError = !in_array($selectedPc, $pcChoices, true);

    if ($idHasError || $pcHasError) {
        $checkinError = 'Please complete the required fields.';
    } else {
        $_SESSION['checkins'][] = [
            'id_number' => $idNumber,
            'pc'        => $selectedPc,
            'time'      => date('Y-m-d H:i:s'),
        ];

        $checkinSuccess = "Check-in submitted for {$selectedPc}.";

        $idValue    = '';
        $selectedPc = '';
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
    <title>Student Check-In</title>
    <link rel="stylesheet" href="style.css">
    <script src="checkin.js" defer></script>
</head>
<body>
    <main class="wrapper">
        <section class="login-panel" aria-labelledby="checkin-title">
            <div class="intro">
                <h1 id="checkin-title">Student<br>Check-In</h1>
                <p>Enter your ID number and select your PC.</p>
            </div>

            <form id="checkin-form" method="POST" action="checkin.php" novalidate>
                <div class="field<?= field_class($idHasError) ?>" id="id-field">
                    <label for="id-number">
                        <img src="images/id_card_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt="">
                        <span class="sr-only">ID Number</span>
                    </label>
                    <input type="text" id="id-number" name="id_number"
                           value="<?= $idValue ?>"
                           placeholder="ID Number" inputmode="numeric">
                    <p class="field-error">ID number is required.</p>
                </div>

                <div class="field pc-field<?= field_class($pcHasError) ?>" id="pc-field">
                    <label for="pc-select">
                        <img src="images/desktop_windows_24dp_1F1F1F_FILL1_wght400_GRAD0_opsz24.svg" alt="">
                        <span class="sr-only">PC Number</span>
                    </label>
                    
                    <input type="hidden" id="pc-number-input" name="pc_number" value="<?= htmlspecialchars($selectedPc, ENT_QUOTES, 'UTF-8') ?>">

                    <button class="pc-select" id="pc-select" type="button" aria-expanded="false" aria-label="Select a PC">
                        <span class="pc-select-value<?= $selectedPc ? ' has-value' : '' ?>">
                            <?= $selectedPc ? htmlspecialchars($selectedPc, ENT_QUOTES, 'UTF-8') : 'Select PC' ?>
                        </span>
                    </button>
                    <div class="pc-options" role="listbox" aria-label="Available PCs" hidden>
                        <?php foreach ($pcChoices as $pc): ?>
                            <button class="pc-option" type="button" role="option" data-value="<?= htmlspecialchars($pc, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($pc, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="field-error">Please select a PC.</p>
                </div>

                <button type="submit">Check In</button>
                <p class="form-error<?= $checkinError ? ' is-visible' : '' ?>" id="checkin-error" role="alert">
                    <?= htmlspecialchars($checkinError) ?>
                </p>
                <p class="form-success<?= $checkinSuccess ? ' is-visible' : '' ?>" id="checkin-success" role="status">
                    <?= htmlspecialchars($checkinSuccess) ?>
                </p>
            </form>

            <p class="checkin">Administrator? <a href="login.php">Admin Login</a></p>
        </section>
        <div class="image-panel" aria-hidden="true"></div>
    </main>
</body>
</html>
