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

// Provides one session-bound token for protected admin form submissions.
function csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(?string $token = null): void
{
    $provided = $token ?? (is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '');
    if (!is_string($_SESSION['admin_csrf_token'] ?? null) || !hash_equals($_SESSION['admin_csrf_token'], $provided)) {
        http_response_code(403);
        exit('Invalid or expired request token. Reload the page and try again.');
    }
}