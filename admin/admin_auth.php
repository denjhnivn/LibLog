<?php

// Every admin page includes this file before showing protected content.
session_start();

if (empty($_SESSION['is_admin'])) {
    $_SESSION['admin_error'] = 'You must be logged in to access this page.';
    header('Location: ../login.php');
    exit;
}

// Stores a one-time message when given a value, or returns and clears it otherwise.
function flash_message(string $key, ?string $message = null): string
{
    if ($message !== null) {
        $_SESSION[$key] = $message;
        return '';
    }
    $value = $_SESSION[$key] ?? '';
    unset($_SESSION[$key]);
    return $value;
}

// Escapes text before it is inserted into HTML.
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
